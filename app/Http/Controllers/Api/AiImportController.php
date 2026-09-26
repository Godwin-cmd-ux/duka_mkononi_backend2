<?php

namespace App\Http\Controllers\Api;

use App\Models\AiImportVerification;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Supabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AiImportController extends BaseController
{
    private const AI_IMPORT_MAX_IMAGES = 6;
    private const AI_IMPORT_MAX_IMAGE_B64 = 6 * 1024 * 1024;
    private const AI_IMPORT_MAX_TOTAL_B64 = 9.5 * 1024 * 1024;
    private const MAX_CANDIDATES = 400;
    private const SMALL_CATALOGUE_LIMIT = 200;
    private const MAX_ITEMS = 500;

    private static array $aiImportVerified = [];

    private function geminiBaseUrl(): string
    {
        return config('ai.base_url', env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'));
    }

    private function geminiModel(): string
    {
        return config('ai.model', env('GEMINI_MODEL', 'gemini-3.6-flash'));
    }

    private function geminiApiKey(): string
    {
        $key = config('ai.api_key', env('GEMINI_API_KEY'));
        if (!$key) {
            throw new \RuntimeException('[GEMINI_KEY_MISSING] GEMINI_API_KEY haijawekwa kwenye server');
        }
        return $key;
    }

    private function detectMimeType(string $base64Data): string
    {
        $raw = base64_decode($base64Data, true);
        if ($raw === false) return 'image/jpeg';
        $head = substr($raw, 0, 12);
        $hex = bin2hex($head);
        if (str_starts_with($hex, '89504e47')) return 'image/png';
        if (str_contains($hex, '57454250')) return 'image/webp';
        if (str_starts_with($hex, '47494638')) return 'image/gif';
        if (str_starts_with($hex, '424d')) return 'image/bmp';
        if (str_starts_with($hex, '49492a00') || str_starts_with($hex, '4d4d002a')) return 'image/tiff';
        if (str_contains($hex, '66747970')) return 'image/heic';
        return 'image/jpeg';
    }

    private function geminiError(string $message, string $code): \RuntimeException
    {
        return new \RuntimeException('[' . $code . '] ' . $message);
    }

    private function errorCode(\Throwable $e): string
    {
        if (preg_match('/^\[([A-Z_]+)\]/', $e->getMessage(), $m)) {
            return $m[1];
        }
        return 'AI_IMPORT_ERROR';
    }

    private function stripErrorToken(\Throwable $e): string
    {
        return preg_replace('/^\[[A-Z_]+\]\s*/', '', $e->getMessage());
    }

    private function generateGeminiContent(array $parts, ?string $systemInstruction = null, array $extra = []): array
    {
        $key = $this->geminiApiKey();
        $url = $this->geminiBaseUrl() . '/models/' . rawurlencode($this->geminiModel()) . ':generateContent?key=' . rawurlencode($key);

        $requestBody = [
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
        ];

        if ($systemInstruction && trim($systemInstruction) !== '') {
            $requestBody['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $requestBody['generationConfig'] = [
            'responseMimeType' => 'application/json',
            'temperature' => $extra['temperature'] ?? 0.2,
            'maxOutputTokens' => $extra['maxOutputTokens'] ?? 8192,
        ];

        $maxRetries = $extra['maxRetries'] ?? 3;
        $baseDelayMs = $extra['retryBaseDelayMs'] ?? 1500;
        $timeoutMs = $extra['timeoutMs'] ?? 120000;

        $lastError = null;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                usleep((int) ($baseDelayMs * pow(2, $attempt - 1) * 1000));
            }

            $requestBody['generationConfig']['temperature'] = $extra['temperature'] ?? 0.2;
            $requestBody['generationConfig']['maxOutputTokens'] = $extra['maxOutputTokens'] ?? 8192;

            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout($timeoutMs)
                    ->post($url, $requestBody);
            } catch (Throwable $networkError) {
                throw $this->geminiError('Gemini timeout - server ilichukua muda mrefu kupata majibu', 'GEMINI_TIMEOUT');
            }

            if ($response->ok()) {
                return $response->json();
            }

            $status = $response->status();
            $detail = '';
            $body = $response->json();
            if ($body && isset($body['error']['message'])) {
                $detail = $body['error']['message'];
            } elseif ($body) {
                $detail = json_encode($body);
            } else {
                $detail = (string) $response->body();
            }

            $transient = $status === 429 || $status === 503;
            if ($attempt < $maxRetries && $transient) {
                $lastError = $this->geminiError('Gemini API error ' . $status . ': ' . $detail, 'GEMINI_RATE_LIMIT');
                continue;
            }

            throw $this->geminiError('Gemini API error ' . $status . ': ' . $detail, $status === 429 ? 'GEMINI_RATE_LIMIT' : 'GEMINI_API_ERROR');
        }

        throw $lastError;
    }

    private function extractTextFromResponse(array $payload): string
    {
        if (empty($payload['candidates']) || !is_array($payload['candidates']) || count($payload['candidates']) === 0) {
            if (isset($payload['promptFeedback']['blockReason'])) {
                throw $this->geminiError('Gemini blocked the request: ' . $payload['promptFeedback']['blockReason'], 'GEMINI_BLOCKED');
            }
            throw $this->geminiError('Gemini returned no candidates', 'GEMINI_EMPTY');
        }
        $texts = [];
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $p) {
            if (isset($p['text']) && is_string($p['text'])) {
                $texts[] = $p['text'];
            }
        }
        return trim(implode("\n", $texts));
    }

    private function safelyParseJson(?string $text): ?array
    {
        if ($text === null || trim($text) === '') return null;
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $decoded = json_decode(trim($m[1]), true);
            if (is_array($decoded)) return $decoded;
        }
        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last > $first) {
            $decoded = json_decode(substr($text, $first, $last - $first + 1), true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }

    private function extractTextFromImages(array $images): array
    {
        if (!is_array($images) || count($images) === 0) {
            throw new \RuntimeException('Hakuna picha zilizotumwa');
        }

        $parts = [];
        $names = [];
        foreach ($images as $img) {
            $mime = $this->detectMimeType($img['base64']);
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => $img['base64'],
                ],
            ];
            $names[] = $img['name'] ?: ('image' . (count($parts)));
        }

        $promptText = 'Transcribe all readable text from the attached inventory images.' . "\n"
            . 'There are ' . count($images) . ' image(s) in this order: '
            . implode(' | ', array_map(function ($n, $i) {
                return ($i + 1) . '. ' . $n;
            }, $names, array_keys($names))) . '.' . "\n"
            . 'For each image return its transcribed text under its exact file name.';
        $parts[] = ['text' => $promptText];

        $systemInstruction = 'You are an OCR / handwriting transcription engine for a business inventory mobile app called "Duka Mkononi".

Given one or more photos of inventory lists, order sheets, stock sheets or handwritten notes, your ONLY job is to transcribe the readable text EXACTLY as it appears — do NOT interpret, correct, fix spelling, add prices, guess missing data, or convert to a product catalog.

Rules:
1. Transcribe words, numbers, prices and quantities exactly as written.
2. Preserve layout as much as possible (columns separated by spaces/tabs, rows on separate lines).
3. If handwriting is hard to read, write down your best literal reading of the characters; do not "improve" the words.
4. Keep currency symbols such as TZS, Tsh, $ if present.
5. If you cannot read something, write [?] for the unreadable part.
6. Do NOT summarize or translate the text.
7. Return STRICT JSON only, with this exact shape:
   {
     "images": [
       { "image": "<image file name>", "extractedText": "..." }
     ]
   }';

        $payload = $this->generateGeminiContent($parts, $systemInstruction, [
            'temperature' => 0.1,
            'maxOutputTokens' => 8192,
        ]);

        $raw = $this->extractTextFromResponse($payload);
        $parsed = $this->safelyParseJson($raw);

        if (!$parsed || !isset($parsed['images']) || !is_array($parsed['images'])) {
            $parsed = ['images' => array_map(function ($img) use ($raw) {
                return ['image' => $img['name'], 'extractedText' => $raw];
            }, $images)];
        }

        $out = [];
        foreach ($images as $img) {
            $name = $img['name'];
            $entry = null;
            foreach (($parsed['images'] ?? []) as $e) {
                if (isset($e['image']) && $e['image'] === $name) {
                    $entry = $e;
                    break;
                }
            }
            $out[] = [
                'image' => $name,
                'extractedText' => $entry['extractedText'] ?? '',
            ];
        }

        return ['images' => $out];
    }

    private function businessTypeLabel(?string $type): string
    {
        $types = [
            'spare_parts' => 'Spare Parts',
            'pharmacy' => 'Pharmacy',
            'supermarket' => 'Supermarket',
            'clothing' => 'Clothing',
            'electronics' => 'Electronics',
            'restaurant' => 'Restaurant',
            'hardware' => 'Hardware',
            'cosmetics' => 'Cosmetics',
            'perfume' => 'Perfume',
            'mobile_accessories' => 'Mobile Accessories',
            'furniture' => 'Furniture',
            'stationery' => 'Stationery',
            'agriculture' => 'Agriculture',
            'construction_materials' => 'Construction Materials',
            'beauty_salon' => 'Beauty Salon',
            'barbershop' => 'Barbershop',
            'auto_repair' => 'Auto Repair',
            'phone_shop' => 'Phone Shop',
            'computer_shop' => 'Computer Shop',
            'general_retail' => 'General Retail',
            'wholesale' => 'Wholesale',
            'other' => 'Other',
        ];
        if (!$type) return 'Not specified';
        return $types[$type] ?? $type;
    }

    private function buildSystemInstruction(): string
    {
        return 'You are an inventory interpretation assistant for the Duka Mkononi business app.

TASK
Interpret inventory information that was extracted (via OCR) from one or more photos of paper lists, order sheets, screenshots or handwritten notes. Produce a clean, structured inventory interpretation.

CRITICAL HONESTY RULES
1. Never invent quantities or prices. If a value is missing or unreadable, return null.
2. Never invent a product that is not supported by the input.
3. If a term is ambiguous, use needsReview=true and keep your best literal guess rather than guessing confidently.
4. Use the business context ONLY to resolve plausible OCR/handwriting errors. Do not blindly rewrite words.
5. Correct obvious OCR mistakes when the context strongly supports it (e.g. "Radiatr" -> "Radiator" for a spare-parts business).
6. Live with uncertainty: an uncertain reading still maps to needsReview=true.
7. Normalize prices to plain numbers (e.g. "150,000", "150 000", "Tsh 150000", "150k" -> 150000). Keep the same currency.
8. PRICES: if the source shows only ONE price per item, put it in sellingPrice and leave buyingPrice null. Never copy the same number into both buyingPrice and sellingPrice — that falsely reports zero profit.
9. Never combine two different products merely because their names look similar. Preserve meaningful product distinctions (brand, model, size, variant).
10. Do not invent a category when the input gives no clue; use a sensible business-appropriate default if the item is clearly part of the business\'s trade.
11. Respond with STRICT JSON only. No markdown fences, no commentary.

EXISTING vs NEW
- Compare every extracted item against the EXISTING INVENTORY list below.
- If an item clearly corresponds to an existing product: status="EXISTING", matchedExistingItemId=that product\'s id.
- Otherwise: status="NEW", matchedExistingItemId=null.
- A near match with medium confidence is still EXISTING but with a lower confidence and needsReview=true when you are not sure.
- Never return a matchedExistingItemId that does not appear in the existing inventory.

DUPLICATES ACROSS IMAGES
- If the same item appears on more than one image, return it as ONE row and SUM the quantities into that row (do not create two rows with the same matched id / same normalized name).
- If two rows look like they MIGHT be duplicates but you are not sure, keep them separate and set needsReview=true with a warning in the "warnings" array.

CONFIDENCE
- confidence is a number 0..1 reflecting how certain you are the interpretation (name + values) is correct.
- Strong readable data = high confidence (>= 0.90). Uncertain handwriting/OCR = lower.

OUTPUT SCHEMA (strict)
Return exactly:
{
  "items": [
    {
      "name": "...",
      "category": "...",
      "description": "...",
      "quantity": <number|null>,
      "buyingPrice": <number|null>,
      "sellingPrice": <number|null>,
      "status": "EXISTING" | "NEW",
      "matchedExistingItemId": <existing id string|null>,
      "confidence": <0..1>,
      "needsReview": <boolean>,
      "sourceImage": "<image file name>",
      "warnings": ["..."]
    }
  ]
}';
    }

    private function serializeExistingItems(array $items): string
    {
        if (count($items) === 0) {
            return '[] (no existing products found for this business)';
        }
        return json_encode(array_map(function ($p) {
            return [
                'id' => (string) $p['id'],
                'name' => $p['name'],
                'sellingPrice' => $p['expected_selling_price'] ?: ($p['price'] ?? null),
                'buyingPrice' => $p['cost_price'] ?? null,
                'quantity' => $p['stock'] ?? null,
                'category' => $p['category'] ?? null,
            ];
        }, $items), JSON_PRETTY_PRINT);
    }

    private function serializeExtractedText($extracted): string
    {
        $images = is_array($extracted) && count($extracted) > 0
            ? $extracted
            : [['image' => 'image1', 'extractedText' => $extracted]];
        return json_encode(array_map(function ($img) {
            return ['image' => $img['image'], 'extractedText' => $img['extractedText'] ?? ''];
        }, $images), JSON_PRETTY_PRINT);
    }

    private function buildUserPrompt(array $ctx): string
    {
        return implode("\n", [
            'BUSINESS CONTEXT',
            'Business type: ' . $this->businessTypeLabel($ctx['businessType']),
            'Business description: ' . ($ctx['businessDescription'] ?: '(not provided — rely on the product names and common trade terminology)'),
            '',
            'EXISTING INVENTORY (only this business\'s products — treat these ids as references, do NOT create new rows for them)',
            $this->serializeExistingItems($ctx['existingItems']),
            '',
            'EXTRACTED TEXT (grouped by source image — use image boundaries when reasoning)',
            $this->serializeExtractedText($ctx['extractedSections']),
            '',
            'INSTRUCTIONS',
            '1. Interpret the extracted inventory into individual line items.',
            '2. Correct obvious OCR/spelling errors only when the business context supports it.',
            '3. Carefully interpret handwriting; when in doubt mark needsReview=true.',
            '4. Use the business type and description as semantic context for ambiguous words.',
            '5. Compare each item to the existing inventory and decide EXISTING or NEW.',
            '6. If EXISTING, set matchedExistingItemId to the matching candidate id.',
            '7. Never return a matchedExistingItemId that is not in the provided list.',
            '8. Never invent missing quantities or prices — use null.',
            '9. If an item has only one price, treat it as the SELLING price (sellingPrice) and leave buyingPrice null. Do not duplicate one price into both fields.',
            '10. Mark uncertain records with needsReview=true and a short warning.',
            '11. Merge the same item appearing on multiple images into one row, summing quantities.',
            '12. Normalize item names (spacing, capitalization) but preserve product distinctions.',
            '13. Return ONLY the JSON object described in your instructions.',
        ]);
    }

    private function normalizeDedupName($name): string
    {
        $name = strtolower((string) $name);
        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return substr($name, 0, 120);
    }

    private function parseNullableNumber($value): ?float
    {
        if ($value === null || $value === '') return null;
        $str = str_replace([',', ' '], '', (string) $value);
        $str = preg_replace('/[^\d.\-]/', '', $str);
        if ($str === '' || $str === '-' || $str === '.') return null;
        $num = (float) $str;
        return is_finite($num) ? $num : null;
    }

    private function cleanItemName($raw): string
    {
        if (!is_string($raw)) return '';
        return substr(preg_replace('/^\s+|\s+$/', '', preg_replace('/\s+/', ' ', $raw)), 0, 200);
    }

    private function stripMarkdownJson($text)
    {
        if (!is_string($text)) return $text;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) return trim($m[1]);
        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last > $first) return substr($text, $first, $last - $first + 1);
        return $text;
    }

    private function validateItem($raw, int $index): ?array
    {
        if (!is_array($raw)) return null;

        $name = $this->cleanItemName($raw['name'] ?? null);
        if ($name === '') return null;

        $quantity = $this->parseNullableNumber($raw['quantity'] ?? null);
        $buyingPrice = $this->parseNullableNumber($raw['buyingPrice'] ?? null);
        $sellingPrice = $this->parseNullableNumber($raw['sellingPrice'] ?? null);
        $price = array_key_exists('price', $raw) ? $this->parseNullableNumber($raw['price']) : $sellingPrice;

        $rawStatus = strtoupper((string) ($raw['status'] ?? 'NEW'));
        $status = $rawStatus === 'EXISTING' ? 'EXISTING' : 'NEW';

        $matchedExistingItemId = null;
        if (isset($raw['matchedExistingItemId']) && $raw['matchedExistingItemId'] !== '' && $raw['matchedExistingItemId'] !== null) {
            $matchedExistingItemId = (string) $raw['matchedExistingItemId'];
        }

        $confidence = $this->parseNullableNumber($raw['confidence'] ?? null);
        if ($confidence === null) $confidence = 0.5;
        $confidence = max(0, min(1, $confidence));

        $needsReview = ($raw['needsReview'] ?? false) === true || ($raw['needsReview'] ?? '') === 'true';

        $category = is_string($raw['category'] ?? '') && trim($raw['category']) !== ''
            ? substr(trim($raw['category']), 0, 100)
            : '';

        $description = is_string($raw['description'] ?? '') && trim($raw['description']) !== ''
            ? substr(trim($raw['description']), 0, 500)
            : '';

        $warnings = is_array($raw['warnings'] ?? null)
            ? array_map(function ($w) {
                return substr((string) $w, 0, 300);
            }, $raw['warnings'])
            : [];

        if ($status === 'EXISTING' && !$matchedExistingItemId) {
            return [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'quantity' => $quantity,
                'buyingPrice' => $buyingPrice,
                'sellingPrice' => $sellingPrice,
                'price' => $price,
                'status' => 'NEW',
                'matchedExistingItemId' => null,
                'confidence' => $confidence,
                'needsReview' => true,
                'sourceImage' => (string) ($raw['sourceImage'] ?? ''),
                'warnings' => [...$warnings, 'Gemini marked the item as existing but gave no matching product id — treated as a new item for review.'],
            ];
        }

        if ($status === 'NEW') $matchedExistingItemId = null;

        return [
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'quantity' => $quantity,
            'buyingPrice' => $buyingPrice,
            'sellingPrice' => $sellingPrice,
            'price' => $price,
            'status' => $status,
            'matchedExistingItemId' => $matchedExistingItemId,
            'confidence' => $confidence,
            'needsReview' => $needsReview,
            'sourceImage' => (string) ($raw['sourceImage'] ?? ''),
            'warnings' => $warnings,
        ];
    }

    private function validateAIResponse(string $rawText): array
    {
        $text = $rawText;
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            $candidate = $this->stripMarkdownJson($text);
            $parsed = json_decode($candidate, true);
        }
        if (!is_array($parsed)) {
            return ['valid' => false, 'items' => [], 'raw' => substr((string) $rawText, 0, 2000)];
        }

        $rows = is_array($parsed) && array_key_exists(0, $parsed) && !empty($parsed)
            ? $parsed
            : (isset($parsed['items']) && is_array($parsed['items']) ? $parsed['items'] : null);

        if ($rows === null) {
            return ['valid' => false, 'items' => [], 'raw' => substr((string) $rawText, 0, 2000)];
        }

        $items = [];
        foreach ($rows as $i => $row) {
            if (count($items) >= self::MAX_ITEMS) break;
            $cleaned = $this->validateItem($row, $i);
            if ($cleaned) $items[] = $cleaned;
        }

        return ['valid' => true, 'items' => $items];
    }

    private function getBusinessSellerIds(string $businessName): array
    {
        $businessUsers = User::where('business_name', $businessName)
            ->whereIn('role', ['admin', 'seller'])
            ->where('status', 'approved')
            ->select('id')
            ->get()
            ->pluck('id')
            ->map(fn($v) => (string) $v)
            ->all();
        return $businessUsers;
    }

    private function tokenize(string $text): array
    {
        if ($text === '') return [];
        $words = preg_split('/\s+/', strtolower(preg_replace('/[^a-z0-9\u0600-\u06FF\u0900-\u097F\u4e00-\u9fff\s]/', ' ', $text)));
        $unique = [];
        $seen = [];
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') >= 3 && !isset($seen[$w])) {
                $seen[$w] = true;
                $unique[] = $w;
            }
        }
        return $unique;
    }

    private function fetchBusinessCatalogue(array $sellerIds): array
    {
        return Product::whereIn('seller_id', $sellerIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20000)
            ->get()
            ->toArray();
    }

    private function searchCandidates(array $sellerIds, array $tokens): ?array
    {
        if (count($tokens) === 0) return [];
        $likeTokens = array_slice($tokens, 0, 10);
        $query = Product::whereIn('seller_id', $sellerIds)->where('is_active', true);
        $query->where(function ($q) use ($likeTokens) {
            $first = true;
            foreach ($likeTokens as $tok) {
                if ($first) {
                    $q->where('name', 'like', '%' . $tok . '%');
                    $first = false;
                } else {
                    $q->orWhere('name', 'like', '%' . $tok . '%');
                }
            }
        });
        return $query->limit(self::MAX_CANDIDATES)->get()->toArray();
    }

    private function findExistingProducts(array $sellerIds, array $extractedSections): array
    {
        if (count($sellerIds) === 0) return [];

        $catalogue = $this->fetchBusinessCatalogue($sellerIds);

        if (count($catalogue) <= self::SMALL_CATALOGUE_LIMIT) {
            return array_slice($catalogue, 0, self::SMALL_CATALOGUE_LIMIT);
        }

        $allText = '';
        foreach ($extractedSections as $s) {
            if (isset($s['extractedText'])) {
                $allText .= ' ' . $s['extractedText'];
            }
        }
        $tokens = $this->tokenize($allText);

        $candidates = $this->searchCandidates($sellerIds, $tokens);
        if ($candidates !== null && count($candidates) > 0) {
            return array_slice($candidates, 0, self::MAX_CANDIDATES);
        }

        return array_slice($catalogue, 0, 100);
    }

    private function presentAsGeminiList(array $products): array
    {
        return array_map(function ($p) {
            return [
                'id' => (string) $p['id'],
                'name' => $p['name'],
                'category' => $p['category'] ?? null,
                'expected_selling_price' => $p['expected_selling_price'] ?: ($p['price'] ?? null),
                'cost_price' => $p['cost_price'] ?? null,
                'price' => $p['price'] ?? null,
                'stock' => $p['stock'] ?? null,
            ];
        }, $products);
    }

    private function ensureAiImportSchema(): array
    {
        $migrations = [
            'ALTER TABLE users ADD COLUMN business_type TEXT',
            'ALTER TABLE users ADD COLUMN business_description TEXT',
            'CREATE TABLE ai_import_verifications',
        ];
        $results = [];
        foreach ($migrations as $migration) {
            try {
                if ($migration === 'CREATE TABLE ai_import_verifications') {
                    $exists = $this->tableExists('ai_import_verifications');
                    $results[] = ['migration' => 'CREATE TABLE IF NOT EXISTS ai_import_verifications', 'status' => $exists ? 'success' : 'failed', 'error' => $exists ? null : 'Table ai_import_verifications is missing in Supabase; create it in the Supabase SQL Editor.'];
                } else {
                    $col = $migration === 'ALTER TABLE users ADD COLUMN business_type TEXT' ? 'business_type' : 'business_description';
                    $hasCol = $this->columnExists('users', $col);
                    $results[] = ['migration' => $migration, 'status' => $hasCol ? 'success' : 'failed', 'error' => $hasCol ? null : 'Column users.' . $col . ' is missing in Supabase; add it in the Supabase SQL Editor.'];
                }
            } catch (Throwable $error) {
                $results[] = ['migration' => substr($migration, 0, 60), 'status' => 'failed', 'error' => $error->getMessage()];
            }
        }
        return $results;
    }

    private function tableExists(string $table): bool
    {
        try {
            Supabase::table($table)->select('id')->limit(1)->get();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            Supabase::table($table)->select($column)->limit(1)->get();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function markAiImportVerified(string $token): array
    {
        if (isset(self::$aiImportVerified[$token])) {
            return ['already' => true];
        }
        self::$aiImportVerified[$token] = true;
        return ['already' => false];
    }

    public function migration(Request $request): JsonResponse
    {
        try {
            if ($this->role($request) !== 'admin') {
                return $this->error('Unauthorized', 403);
            }
            $results = $this->ensureAiImportSchema();
            $this->log($this->userId($request), 'AI_IMPORT_MIGRATION', '/api/setup/ai-import-migration', [
                'successful' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'success')),
            ], $this->ip($request), 'success');
            return $this->json(['success' => true, 'message' => 'AI import migration completed', 'results' => $results]);
        } catch (Throwable $error) {
            return $this->json(['error' => 'Database migration failed', 'details' => $error->getMessage()], 500);
        }
    }

    public function import(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $businessName = $this->businessName($request);
            $ip = $this->ip($request);

            if (!$businessName) {
                return $this->error('Biashara inahitajika. Sajili biashara yako kwanza.', 400);
            }

            $businessType = null;
            $businessDescription = null;
            try {
                $bizRow = User::where('id', $userId)->select('business_type', 'business_description')->first();
                if ($bizRow) {
                    $businessType = $bizRow->business_type;
                    $businessDescription = $bizRow->business_description;
                }
            } catch (Throwable $e) {
            }

            $images = is_array($request->input('images')) ? $request->input('images') : [];

            if (count($images) === 0) {
                $this->log($userId, 'AI_IMPORT_NO_IMAGES', '/api/inventory/ai-import', [], $ip, 'failed');
                return $this->json(['error' => 'Hakuna picha zilizochaguliwa', 'code' => 'NO_IMAGES'], 400);
            }
            if (count($images) > self::AI_IMPORT_MAX_IMAGES) {
                return $this->json(['error' => 'Unaweza kuchagua hadi picha ' . self::AI_IMPORT_MAX_IMAGES, 'code' => 'TOO_MANY_IMAGES'], 400);
            }

            $totalB64 = 0;
            $cleanImages = [];
            foreach ($images as $img) {
                $b64 = is_array($img) && isset($img['base64']) && is_string($img['base64']) ? $img['base64'] : '';
                $name = is_array($img) && isset($img['name']) && is_string($img['name']) ? substr($img['name'], 0, 200) : 'image';
                if ($b64 === '') continue;
                if (strlen($b64) > self::AI_IMPORT_MAX_IMAGE_B64) {
                    return $this->json(['error' => 'Picha moja ni kubwa sana', 'code' => 'IMAGE_TOO_LARGE'], 400);
                }
                $totalB64 += strlen($b64);
                $cleanImages[] = ['name' => $name, 'base64' => $b64];
            }
            if (count($cleanImages) === 0) {
                return $this->json(['error' => 'Hakuna picha halali zilizotumwa', 'code' => 'NO_IMAGES'], 400);
            }
            if ($totalB64 > self::AI_IMPORT_MAX_TOTAL_B64) {
                return $this->json(['error' => 'Picha ni nyingi mno kwa ukubwa. Bana picha au punguza idadi.', 'code' => 'TOTAL_TOO_LARGE'], 400);
            }

            try {
                $this->ensureAiImportSchema();
            } catch (Throwable $e) {
            }

            $extraction = $this->extractTextFromImages($cleanImages);

            $sellerIds = $this->getBusinessSellerIds($businessName);
            $candidates = count($sellerIds) > 0
                ? $this->findExistingProducts($sellerIds, $extraction['images'])
                : [];

            $prompt = $this->buildUserPrompt([
                'businessType' => $businessType,
                'businessDescription' => $businessDescription,
                'existingItems' => $this->presentAsGeminiList($candidates),
                'extractedSections' => $extraction['images'],
            ]);

            $payload = $this->generateGeminiContent(
                [['text' => $prompt]],
                $this->buildSystemInstruction(),
                ['temperature' => 0.2, 'maxOutputTokens' => 8192]
            );
            $rawText = $this->extractTextFromResponse($payload);

            $result = $this->validateAIResponse($rawText);
            $valid = $result['valid'];
            $items = $result['items'];

            if (!$valid || count($items) === 0) {
                $this->log($userId, 'AI_IMPORT_NO_VALID_ITEMS', '/api/inventory/ai-import', [
                    'imageCount' => count($cleanImages),
                    'rawPreview' => substr((string) $rawText, 0, 300),
                ], $ip, 'warning');
                return $this->json([
                    'error' => 'Hakuna bidhaa zilizoweza kutambuliwa kutoka kwenye picha. Jaribu picha nyingine zenye mwanga mzuri.',
                    'code' => 'NO_VALID_ITEMS',
                ], 422);
            }

            $allowedIds = [];
            foreach ($candidates as $c) {
                $allowedIds[(string) $c['id']] = true;
            }
            $rowIndexById = [];
            foreach ($candidates as $c) {
                $rowIndexById[(string) $c['id']] = $c;
            }

            $sanitizedItems = [];
            foreach ($items as $item) {
                $row = $item;
                if ($row['status'] === 'EXISTING' && (!isset($row['matchedExistingItemId']) || !isset($allowedIds[$row['matchedExistingItemId']]))) {
                    $row['status'] = 'NEW';
                    $row['matchedExistingItemId'] = null;
                    $row['needsReview'] = true;
                    $row['warnings'] = isset($row['warnings']) && is_array($row['warnings'])
                        ? array_slice([...$row['warnings'], 'Gemini alibainisha bidhaa isiyo ndani ya biashara hii — imekuwa bidhaa mpya kwa uhakiki.'], 0, 6)
                        : ['Gemini alibainisha bidhaa isiyo ndani ya biashara hii — imekuwa bidhaa mpya kwa uhakiki.'];
                }
                if (isset($row['matchedExistingItemId']) && $row['matchedExistingItemId']) {
                    $p = $rowIndexById[$row['matchedExistingItemId']] ?? null;
                    if ($p) {
                        $row['currentStock'] = $p['stock'] ?? 0;
                        $row['buyingPrice'] = $row['buyingPrice'] ?? ($p['cost_price'] ?? null);
                        $row['sellingPrice'] = $row['sellingPrice'] ?? ($p['expected_selling_price'] ?? null);
                    } else {
                        $row['status'] = 'NEW';
                        $row['matchedExistingItemId'] = null;
                        $row['needsReview'] = true;
                    }
                }
                $row['belongsToBusiness'] = $row['status'] === 'NEW'
                    ? true
                    : (isset($row['matchedExistingItemId']) && isset($allowedIds[$row['matchedExistingItemId']]));
                $sanitizedItems[] = $row;
            }

            $dedupMap = [];
            foreach ($sanitizedItems as $row) {
                $key = ($row['status'] === 'EXISTING' && isset($row['matchedExistingItemId']) && $row['matchedExistingItemId'])
                    ? 'existing:' . $row['matchedExistingItemId']
                    : 'new:' . $this->normalizeDedupName($row['name']);
                if (isset($dedupMap[$key])) {
                    $existing = $dedupMap[$key];
                    $qOld = $existing['quantity'] ?? 0;
                    $qAdd = $row['quantity'] ?? 0;
                    $existing['quantity'] = ($qOld + $qAdd) ?: null;
                    $existing['needsReview'] = ($existing['needsReview'] ?? false) || ($row['needsReview'] ?? false);
                    $warnings = array_merge($existing['warnings'] ?? [], $row['warnings'] ?? [], ['Bidhaa hii imeonekana kwenye picha nyingi — idadi imejumlishwa.']);
                    $existing['warnings'] = array_slice($warnings, 0, 6);
                    $existing['confidence'] = max($existing['confidence'] ?? 0, $row['confidence'] ?? 0);
                    $dedupMap[$key] = $existing;
                } else {
                    $dedupMap[$key] = $row;
                }
            }

            $finalItems = array_map(function ($row) {
                return [
                    'id' => Str::uuid()->toString(),
                    'verificationToken' => Str::uuid()->toString(),
                    'name' => $row['name'],
                    'category' => $row['category'] ?? '',
                    'description' => $row['description'] ?? '',
                    'quantity' => $row['quantity'] ?? null,
                    'buyingPrice' => $row['buyingPrice'] ?? null,
                    'sellingPrice' => $row['sellingPrice'] ?? null,
                    'price' => $row['price'] ?? ($row['sellingPrice'] ?? null),
                    'status' => $row['status'],
                    'matchedExistingItemId' => $row['matchedExistingItemId'],
                    'currentStock' => $row['currentStock'] ?? null,
                    'confidence' => $row['confidence'] ?? 0.5,
                    'needsReview' => !!($row['needsReview'] ?? false),
                    'sourceImage' => $row['sourceImage'] ?? '',
                    'warnings' => $row['warnings'] ?? [],
                    'belongsToBusiness' => !!($row['belongsToBusiness'] ?? false),
                ];
            }, array_values($dedupMap));

            $this->log($userId, 'AI_IMPORT_PROCESSED', '/api/inventory/ai-import', [
                'imageCount' => count($cleanImages),
                'itemsReturned' => count($finalItems),
                'existingCandidates' => count($candidates),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'items' => $finalItems,
                'meta' => [
                    'businessType' => $businessType,
                    'businessDescription' => $businessDescription,
                    'imageCount' => count($cleanImages),
                    'existingCandidateCount' => count($candidates),
                    'model' => $this->geminiModel(),
                ],
            ]);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'AI_IMPORT_ERROR', '/api/inventory/ai-import', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');

            $code = $this->errorCode($error);
            $bodyCode = $code !== 'AI_IMPORT_ERROR' ? $code : 'AI_IMPORT_ERROR';
            $status = in_array($code, ['GEMINI_RATE_LIMIT', 'GEMINI_TIMEOUT']) ? 503 : (($bodyCode === 'NO_VALID_ITEMS') ? 422 : 500);
            return $this->json([
                'error' => 'Server imekumbana na tatizo wakati wa kuchambua picha: ' . $this->stripErrorToken($error),
                'code' => $bodyCode,
            ], $status);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $businessName = $this->businessName($request);
            $ip = $this->ip($request);

            if (!$businessName) {
                return $this->error('Biashara inahitajika.', 400);
            }

            $body = is_array($request->input('item')) ? $request->input('item') : $request->all();

            $verificationToken = isset($body['verificationToken']) && is_string($body['verificationToken']) && trim($body['verificationToken']) !== ''
                ? trim($body['verificationToken'])
                : null;
            $action = strtoupper((string) ($body['action'] ?? ''));

            if (!$verificationToken) {
                return $this->json(['error' => 'verificationToken inahitajika', 'code' => 'MISSING_TOKEN'], 400);
            }
            if (!in_array($action, ['EXISTING', 'NEW'], true)) {
                return $this->json(['error' => 'action lazima iwe EXISTING au NEW', 'code' => 'BAD_ACTION'], 400);
            }

            $quantityRaw = (float) ($body['quantity'] ?? 0);
            if (!is_finite($quantityRaw) || $quantityRaw != (int) $quantityRaw || $quantityRaw <= 0) {
                return $this->json(['error' => 'Idadi lazima iwe nambari nzima kubwa kuliko sifuri', 'code' => 'BAD_QUANTITY'], 400);
            }
            $quantityRaw = (int) $quantityRaw;

            try {
                $this->ensureAiImportSchema();
            } catch (Throwable $e) {
            }

            $sellerIds = $this->getBusinessSellerIds($businessName);
            $owned = [];
            foreach ($sellerIds as $id) {
                $owned[$id] = true;
            }

            $newProductsCount = 0;
            $duplicatesSkipped = 0;

            if ($action === 'EXISTING') {
                $productId = (string) ($body['matchedExistingItemId'] ?? '');
                if ($productId === '') {
                    return $this->json(['error' => 'Bidhaa yenye upatanifu haikutambuliwa', 'code' => 'NO_MATCH'], 400);
                }

                $product = Product::where('id', $productId)->first();
                if (!$product) {
                    $this->log($userId, 'AI_IMPORT_VERIFY_NOT_FOUND', '/api/inventory/ai-import/verify', ['productId' => $productId], $ip, 'failed');
                    return $this->json(['error' => 'Bidhaa haipatikani', 'code' => 'PRODUCT_NOT_FOUND'], 404);
                }
                if (!isset($owned[$product->seller_id])) {
                    $this->log($userId, 'AI_IMPORT_VERIFY_FORBIDDEN', '/api/inventory/ai-import/verify', ['productId' => $productId], $ip, 'failed');
                    return $this->json(['error' => 'Bidhaa hii si ya biashara yako', 'code' => 'FORBIDDEN'], 403);
                }

                $already = $this->markAiImportVerified($verificationToken)['already'];
                if ($already) {
                    $this->log($userId, 'AI_IMPORT_VERIFY_DUPLICATE', '/api/inventory/ai-import/verify', ['productId' => $productId], $ip, 'success');
                    return $this->json(['success' => true, 'alreadyVerified' => true, 'message' => 'Bidhaa hii tayari imethibitishwa.', 'product' => $product]);
                }

                $updateData = [
                    'stock' => ($product->stock ?? 0) + $quantityRaw,
                    'updated_at' => now()->toISOString(true),
                ];
                $buying = (float) ($body['buyingPrice'] ?? 0);
                $selling = (float) ($body['sellingPrice'] ?? 0);
                if (is_finite($buying) && $buying > 0) $updateData['cost_price'] = $buying;
                // The selling price the user confirmed wins over any stale
                // "price" payload, otherwise their table edits never stick.
                if (is_finite($selling) && $selling > 0) {
                    $updateData['price'] = $selling;
                    $updateData['expected_selling_price'] = $selling;
                } else {
                    $updateData['expected_selling_price'] = $product->expected_selling_price ?: ($product->price ?? 0);
                }

                try {
                    Product::where('id', $productId)->update($updateData);
                } catch (Throwable $uErr) {
                    unset(self::$aiImportVerified[$verificationToken]);
                    $this->log($userId, 'AI_IMPORT_VERIFY_APPLY_FAILED', '/api/inventory/ai-import/verify', ['productId' => $productId, 'error' => $uErr->getMessage()], $ip, 'failed');
                    return $this->json(['error' => 'Imeshindwa kuongeza bidhaa kwenye stoo: ' . $uErr->getMessage(), 'code' => 'STOCK_UPDATE_FAILED'], 500);
                }

                $this->log($userId, 'AI_IMPORT_VERIFY', '/api/inventory/ai-import/verify', [
                    'action' => 'EXISTING',
                    'productId' => $productId,
                    'addedStock' => $quantityRaw,
                    'oldStock' => $product->stock ?? 0,
                    'newStock' => $updateData['stock'],
                ], $ip, 'success');

                // Audit row is best-effort: a missing ai_import_verifications
                // table must not turn an already-successful stock update into
                // a user-visible failure (it previously reported 500 AFTER the
                // stock was saved, so re-clicking then hit PRODUCT_EXISTS).
                try {
                    AiImportVerification::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $userId,
                        'business_name' => $businessName,
                        'total_products' => 1,
                        'duplicates_skipped' => 0,
                        'new_products' => 0,
                        'source_name' => $request->input('source_name') ?: 'ai_import',
                        'created_at' => now()->toISOString(true),
                    ]);
                } catch (Throwable $auditErr) {
                }

                return $this->json([
                    'success' => true,
                    'alreadyVerified' => false,
                    'message' => 'Sasa idadi ya bidhaa imeongezeka.',
                    'product' => [
                        'id' => $productId,
                        'name' => $product->name,
                        'stock' => $updateData['stock'],
                    ],
                ]);
            }

            $name = trim((string) ($body['name'] ?? ''));
            if ($name === '') {
                return $this->json(['error' => 'Jina la bidhaa linahitajika', 'code' => 'MISSING_NAME'], 400);
            }

            // products table has seller_id (no user_id column) — the old
            // user_id filter made PostgREST reject the whole request, so every
            // NEW-product verification failed before it could save.
            $dupes = Product::where('is_active', true)
                ->whereIn('seller_id', $sellerIds)
                ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                ->select('id', 'name')
                ->get();
            if ($dupes->count() > 0) {
                $this->log($userId, 'AI_IMPORT_VERIFY_DUP', '/api/inventory/ai-import/verify', ['name' => $name], $ip, 'failed');
                return $this->json([
                    'error' => 'Bidhaa hii tayari ipo kwenye biashara yako. Ongeza idadi badala ya kuunda bidhaa mpya.',
                    'code' => 'PRODUCT_EXISTS',
                    'existingProductId' => $dupes->first()->id,
                    'existingProductName' => $dupes->first()->name,
                ], 409);
            }

            $buying = (float) ($body['buyingPrice'] ?? 0);
            $selling = (float) ($body['sellingPrice'] ?? 0);
            $validBuying = is_finite($buying) && $buying > 0 ? $buying : null;
            $validSelling = is_finite($selling) && $selling > 0 ? $selling : null;

            // The SELLING price is what the rest of the app treats as the
            // product's selling price. Falling back to the buying price here
            // (old behaviour) made selling == buying and reported zero
            // expected profit, so it is now required on its own.
            if (!$validSelling) {
                return $this->json([
                    'error' => 'Weka bei ya kuuzia kabla ya kuthibitisha. Bei ya kununua ni ya hiari.',
                    'code' => 'MISSING_PRICE',
                ], 400);
            }

            $already = $this->markAiImportVerified($verificationToken)['already'];
            if ($already) {
                return $this->json(['success' => true, 'alreadyVerified' => true, 'message' => 'Bidhaa hii tayari imethibitishwa.']);
            }

            $category = substr(trim((string) ($body['category'] ?? 'Mengineyo')), 0, 100);
            if ($category === '') $category = 'Mengineyo';

            $now = now()->toISOString(true);
            $newProductData = [
                'id' => (string) Str::uuid(),
                'name' => $name,
                'category' => $category,
                'description' => substr(trim((string) ($body['description'] ?? '')), 0, 500),
                'price' => $validSelling,
                'cost_price' => $validBuying,
                'expected_selling_price' => $validSelling,
                'stock' => $quantityRaw,
                'is_active' => true,
                'seller_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            try {
                $created = Product::create($newProductData);
            } catch (Throwable $cErr) {
                unset(self::$aiImportVerified[$verificationToken]);
                if (str_contains(strtolower($cErr->getMessage()), 'unique')) {
                    return $this->json(['error' => 'Bidhaa yenye jina hili tayari ipo.', 'code' => 'PRODUCT_EXISTS'], 409);
                }
                $this->log($userId, 'AI_IMPORT_VERIFY_INSERT_FAILED', '/api/inventory/ai-import/verify', ['name' => $name, 'error' => $cErr->getMessage()], $ip, 'failed');
                return $this->json(['error' => 'Imeshindwa kuunda bidhaa: ' . $cErr->getMessage(), 'code' => 'PRODUCT_CREATE_FAILED'], 500);
            }

            $this->log($userId, 'AI_IMPORT_VERIFY', '/api/inventory/ai-import/verify', [
                'action' => 'NEW',
                'productId' => $created->id,
                'name' => $name,
                'stock' => $quantityRaw,
            ], $ip, 'success');

            // Audit row is best-effort (see the EXISTING branch) — the product
            // has already been created at this point.
            try {
                AiImportVerification::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'business_name' => $businessName,
                    'total_products' => 1,
                    'duplicates_skipped' => 0,
                    'new_products' => 1,
                    'source_name' => $request->input('source_name') ?: 'ai_import',
                    'created_at' => now()->toISOString(true),
                ]);
            } catch (Throwable $auditErr) {
            }

            return $this->json([
                'success' => true,
                'alreadyVerified' => false,
                'message' => 'Bidhaa mpya imeundwa kikamilifu!',
                'product' => ['id' => $created->id, 'name' => $created->name, 'stock' => $created->stock],
            ], 201);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'AI_IMPORT_VERIFY', '/api/inventory/ai-import/verify', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');
            return $this->json(['error' => 'Imeshindwa kuthibitisha bidhaa: ' . $error->getMessage(), 'code' => 'VERIFY_ERROR'], 500);
        }
    }

    /* ====================================================================
     |  AI SALES IMPORT (muuzaji "uza" page)
     |  Paper-based sales: photo → OCR → Gemini matches handwritten items
     |  against the business's EXISTING products → user reviews → commit.
     * ==================================================================== */

    private function buildSalesSystemInstruction(): string
    {
        return 'You are a sales-entry assistant for the Duka Mkononi business app.

TASK
A merchant recorded sales on paper. You receive OCR text extracted from photos of that paper plus the business\'s EXISTING product list. Your job: turn the handwritten sales lines into structured sale items, matching every handwritten item to the correct EXISTING product.

MATCHING RULES
1. Every handwritten item is a product the business already sells. Find its match in the EXISTING PRODUCTS list by name similarity (abbreviations, spelling variations, singular/plural, Swahili/English mix count as matches, e.g. "sk 2" -> "Sukari", "maziwa 5L" -> "Maziwa 5L").
2. If NOTHING in the product list could plausibly be the handwritten item, status="UNMATCHED" and matchedProductId=null. Never invent products.
3. When two products could match, pick the closest name and lower confidence; set needsReview=true.
4. A handwritten item appearing twice is TWO separate sale lines (they may be two customers); do NOT merge rows. Only merge when the SAME line was obviously transcribed twice with identical qty and price.

PRICE RULES
5. quantity: the number sold (positive integer). If unreadable, null.
6. unitPrice: the price WRITTEN for that line, normalized to a plain number ("1,500" "Tsh 1500" "1.5k" -> 1500). If no price is written, unitPrice=null — the system will use the product\'s default selling price. NEVER invent a price.
7. A total written at the end of a line is that line\'s price only if it equals qty × a plausible unit price; otherwise ignore it and set unitPrice=null.

CONFIDENCE & REVIEW
8. confidence 0..1. Clean handwriting + clear match = >=0.9. Uncertain = lower + needsReview=true.
9. warnings: short Swahili messages for anything the user should check.

OUTPUT SCHEMA (strict JSON only, no markdown fences)
{
  "items": [
    {
      "handwrittenName": "text exactly as written",
      "matchedProductId": "<id from EXISTING PRODUCTS|null>",
      "matchedProductName": "<matched product name|null>",
      "quantity": <positive integer|null>,
      "unitPrice": <number|null>,
      "status": "MATCHED" | "UNMATCHED",
      "confidence": <0..1>,
      "needsReview": <boolean>,
      "sourceImage": "<image file name>",
      "warnings": ["..."]
    }
  ]
}';
    }

    private function buildSalesUserPrompt(array $ctx): string
    {
        return implode("\n", [
            'BUSINESS',
            'Business type: ' . $this->businessTypeLabel($ctx['businessType']),
            $ctx['businessDescription'] ? 'Description: ' . $ctx['businessDescription'] : '',
            '',
            'EXISTING PRODUCTS (the ONLY ids you may return in matchedProductId)',
            json_encode($ctx['products'], JSON_PRETTY_PRINT),
            '',
            'OCR TEXT FROM THE PAPER SALES RECORDS (grouped by image)',
            $this->serializeExtractedText($ctx['extractedSections']),
            '',
            'INSTRUCTIONS',
            '1. Parse every handwritten sale line into an item.',
            '2. Match each item to the EXISTING PRODUCTS list (rule 1 above).',
            '3. Use the written price as unitPrice; when no price is written use null (default selling price will be applied).',
            '4. Never merge distinct sale lines.',
            '5. Return ONLY the JSON object described in your instructions.',
        ]);
    }

    private function validateSaleItem(array $raw, array $productIndex): ?array
    {
        $hand = $this->cleanItemName($raw['handwrittenName'] ?? null);
        if ($hand === '') return null;

        $matchedId = isset($raw['matchedProductId']) && $raw['matchedProductId'] !== '' && $raw['matchedProductId'] !== null
            ? (string) $raw['matchedProductId']
            : null;

        $status = strtoupper((string) ($raw['status'] ?? '')) === 'UNMATCHED' ? 'UNMATCHED' : 'MATCHED';
        if ($status === 'MATCHED' && (!$matchedId || !isset($productIndex[$matchedId]))) {
            // Guard against hallucinated ids not in the business catalogue.
            $status = 'UNMATCHED';
            $matchedId = null;
        }

        $qty = $this->parseNullableNumber($raw['quantity'] ?? null);
        $qty = ($qty !== null && $qty > 0) ? (int) floor($qty) : null;

        $unitPrice = $this->parseNullableNumber($raw['unitPrice'] ?? null);
        if ($unitPrice !== null && $unitPrice <= 0) $unitPrice = null;

        $confidence = $this->parseNullableNumber($raw['confidence'] ?? null);
        $confidence = $confidence === null ? 0.5 : max(0, min(1, $confidence));

        $needsReview = ($raw['needsReview'] ?? false) === true || ($raw['needsReview'] ?? '') === 'true';

        $warnings = is_array($raw['warnings'] ?? null)
            ? array_map(fn($w) => substr((string) $w, 0, 300), array_slice($raw['warnings'], 0, 6))
            : [];

        $product = $matchedId ? ($productIndex[$matchedId] ?? null) : null;

        return [
            'handwrittenName' => $hand,
            'matchedProductId' => $matchedId,
            'matchedProductName' => $product['name'] ?? ($raw['matchedProductName'] ?? null),
            'defaultUnitPrice' => $product ? ($product['expected_selling_price'] ?: ($product['price'] ?? null)) : null,
            'currentStock' => $product['stock'] ?? null,
            'quantity' => $qty,
            'unitPrice' => $unitPrice,
            'status' => $status,
            'confidence' => $confidence,
            'needsReview' => $needsReview,
            'sourceImage' => (string) ($raw['sourceImage'] ?? ''),
            'warnings' => $warnings,
        ];
    }

    /** POST /api/sales/ai-import — photos of paper sales -> structured rows. */
    public function salesImport(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $businessName = $this->businessName($request);
            $ip = $this->ip($request);

            if (!$businessName) {
                return $this->error('Biashara inahitajika. Sajili biashara yako kwanza.', 400);
            }

            $images = is_array($request->input('images')) ? $request->input('images') : [];
            if (count($images) === 0) {
                return $this->json(['error' => 'Hakuna picha zilizochaguliwa', 'code' => 'NO_IMAGES'], 400);
            }
            if (count($images) > self::AI_IMPORT_MAX_IMAGES) {
                return $this->json(['error' => 'Unaweza kuchagua hadi picha ' . self::AI_IMPORT_MAX_IMAGES, 'code' => 'TOO_MANY_IMAGES'], 400);
            }

            $totalB64 = 0;
            $cleanImages = [];
            foreach ($images as $img) {
                $b64 = is_array($img) && isset($img['base64']) && is_string($img['base64']) ? $img['base64'] : '';
                $name = is_array($img) && isset($img['name']) && is_string($img['name']) ? substr($img['name'], 0, 200) : 'image';
                if ($b64 === '') continue;
                if (strlen($b64) > self::AI_IMPORT_MAX_IMAGE_B64) {
                    return $this->json(['error' => 'Picha moja ni kubwa sana', 'code' => 'IMAGE_TOO_LARGE'], 400);
                }
                $totalB64 += strlen($b64);
                $cleanImages[] = ['name' => $name, 'base64' => $b64];
            }
            if (count($cleanImages) === 0) {
                return $this->json(['error' => 'Hakuna picha halali zilizotumwa', 'code' => 'NO_IMAGES'], 400);
            }
            if ($totalB64 > self::AI_IMPORT_MAX_TOTAL_B64) {
                return $this->json(['error' => 'Picha ni nyingi mno kwa ukubwa. Bana picha au punguza idadi.', 'code' => 'TOTAL_TOO_LARGE'], 400);
            }

            $this->touchLastSeen($userId);

            // 1) OCR the paper (same extraction as inventory import)
            $extraction = $this->extractTextFromImages($cleanImages);

            // 2) Load the business product list for matching (same as inventory)
            $sellerIds = $this->getBusinessSellerIds($businessName);
            $candidates = count($sellerIds) > 0
                ? $this->findExistingProducts($sellerIds, $extraction['images'])
                : [];

            if (count($candidates) === 0) {
                return $this->json(['error' => 'Hakuna bidhaa zilizorekodiwa kwenye biashara hii — ingiza bidhaa kwanza.', 'code' => 'NO_PRODUCTS'], 422);
            }

            $productIndex = [];
            foreach ($candidates as $c) {
                $productIndex[(string) $c['id']] = $c;
            }

            // 3) Ask Gemini to parse + match
            $prompt = $this->buildSalesUserPrompt([
                'businessType' => $this->businessTypeOf($userId),
                'businessDescription' => $this->businessDescriptionOf($userId),
                'products' => $this->presentAsGeminiList($candidates),
                'extractedSections' => $extraction['images'],
            ]);

            $payload = $this->generateGeminiContent(
                [['text' => $prompt]],
                $this->buildSalesSystemInstruction(),
                ['temperature' => 0.2, 'maxOutputTokens' => 8192]
            );
            $rawText = $this->extractTextFromResponse($payload);

            $parsed = json_decode($rawText, true);
            if (!is_array($parsed)) {
                $parsed = json_decode($this->stripMarkdownJson($rawText), true);
            }
            $rows = is_array($parsed)
                ? (isset($parsed['items']) && is_array($parsed['items']) ? $parsed['items'] : (isset($parsed[0]) ? $parsed : null))
                : null;

            if (!is_array($rows) || count($rows) === 0) {
                $this->log($userId, 'AI_SALES_IMPORT_NO_ITEMS', '/api/sales/ai-import', [
                    'rawPreview' => substr((string) $rawText, 0, 300),
                ], $ip, 'warning');
                return $this->json(['error' => 'Hakuna mauzo yaliyotambuliwa kwenye picha. Jaribu picha nyepesi zenye mwanga mzuri.', 'code' => 'NO_VALID_ITEMS'], 422);
            }

            // 4) Validate + enrich with product defaults (stock, default price)
            $items = [];
            foreach (array_slice($rows, 0, self::MAX_ITEMS) as $row) {
                if (!is_array($row)) continue;
                $clean = $this->validateSaleItem($row, $productIndex);
                if ($clean) $items[] = $clean;
            }

            if (count($items) === 0) {
                return $this->json(['error' => 'Hakuna mauzo yaliyoweza kusomeka kwenye picha.', 'code' => 'NO_VALID_ITEMS'], 422);
            }

            $this->log($userId, 'AI_SALES_IMPORT', '/api/sales/ai-import', [
                'imageCount' => count($cleanImages),
                'itemsReturned' => count($items),
                'matched' => count(array_filter($items, fn($i) => $i['status'] === 'MATCHED')),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'items' => array_map(fn($i) => array_merge(['id' => (string) Str::uuid()], $i), $items),
                'meta' => ['imageCount' => count($cleanImages), 'model' => $this->geminiModel()],
            ]);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'AI_SALES_IMPORT_ERROR', '/api/sales/ai-import', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');

            $code = $this->errorCode($error);
            $status = in_array($code, ['GEMINI_RATE_LIMIT', 'GEMINI_TIMEOUT']) ? 503 : 500;
            return $this->json([
                'error' => 'Server imekumbana na tatizo wakati wa kuchambua picha: ' . $this->stripErrorToken($error),
                'code' => $code === 'AI_IMPORT_ERROR' ? 'AI_SALES_IMPORT_ERROR' : $code,
            ], $status);
        }
    }

    /** Same numbering scheme as SaleController::generateInvoiceNumber. */
    private function generateInvoiceNumber(?string $sellerId = null): string
    {
        try {
            $yearSuffix = substr((string) date('Y'), -2);
            $query = Sale::select(['invoice_number', 'created_at'])
                ->where('invoice_number', 'like', $yearSuffix . '-%')
                ->orderBy('created_at', 'desc');
            if ($sellerId) {
                $query->where('seller_id', $sellerId);
            }
            $maxSequence = 0;
            foreach ($query->get()->toArray() as $sale) {
                $inv = $sale['invoice_number'] ?? '';
                $parts = explode('-', (string) $inv);
                if (count($parts) === 2 && is_numeric($parts[1])) {
                    $maxSequence = max($maxSequence, (int) $parts[1]);
                }
            }
            return $yearSuffix . '-' . str_pad((string) ($maxSequence + 1), 4, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            $yearSuffix = substr((string) date('Y'), -2);
            return $yearSuffix . '-' . substr((string) (int) (microtime(true) * 1000), -4);
        }
    }

    private function businessTypeOf(string $userId): ?string
    {
        try {
            $row = User::where('id', $userId)->select('business_type')->first();
            return $row->business_type ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function businessDescriptionOf(string $userId): ?string
    {
        try {
            $row = User::where('id', $userId)->select('business_description')->first();
            return $row->business_description ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * POST /api/sales/ai-commit — create a real sale from AI-imported rows
     * after user review. Reuses the same stock/customer/total rules as the
     * manual sale endpoint; every quantity is re-checked against LIVE stock.
     */
    public function commitAiSale(Request $request): JsonResponse
    {
        try {
            $userId = $this->userId($request);
            $ip = $this->ip($request);

            $items = is_array($request->input('items')) ? $request->input('items') : [];
            if (count($items) === 0) {
                return $this->json(['error' => 'Hakuna bidhaa za kutosha kuokoa mauzo', 'code' => 'NO_ITEMS'], 400);
            }

            $this->touchLastSeen($userId);

            $normalized = [];
            foreach ($items as $item) {
                $productId = (string) ($item['product_id'] ?? '');
                $qty = (int) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                if ($productId === '' || $qty <= 0 || !is_finite($unitPrice) || $unitPrice <= 0) continue;
                // Merge duplicate product rows (AI may list the same product twice)
                if (isset($normalized[$productId])) {
                    $normalized[$productId]['quantity'] += $qty;
                } else {
                    $normalized[$productId] = ['product_id' => $productId, 'quantity' => $qty, 'unit_price' => $unitPrice];
                }
            }
            if (count($normalized) === 0) {
                return $this->json(['error' => 'Taarifa za bidhaa hazijakamilika au si sahihi', 'code' => 'INVALID_ITEMS'], 400);
            }

            // Idempotency for AI commits too (double-tap protection).
            $clientSaleKey = substr(trim((string) $request->input('clientSaleKey', '')), 0, 80);
            if ($clientSaleKey !== '') {
                try {
                    $existing = Sale::where('seller_id', $userId)->where('notes', 'like', 'ai_dup_' . $clientSaleKey . '%')->select(['id', 'invoice_number', 'total_amount'])->first();
                    if ($existing) {
                        return $this->json([
                            'success' => true,
                            'duplicate' => true,
                            'message' => 'Mauzo haya yameshahifadhiwa tayari.',
                            'invoice_number' => $existing->invoice_number,
                            'sale' => $existing->toArray(),
                        ], 200);
                    }
                } catch (Throwable $e) {
                }
            }

            // Validate products + stock against LIVE database values.
            $stockIssues = [];
            $total_amount = 0.0;
            foreach ($normalized as &$n) {
                $product = Product::where('id', $n['product_id'])->where('is_active', true)->first();
                if (!$product) {
                    $stockIssues[] = ['product_id' => $n['product_id'], 'reason' => 'Product not found'];
                    continue;
                }
                if ((float) $product->stock < $n['quantity']) {
                    $stockIssues[] = [
                        'product_id' => $n['product_id'],
                        'product_name' => $product->name,
                        'reason' => 'Insufficient stock',
                        'available' => $product->stock,
                        'requested' => $n['quantity'],
                    ];
                    continue;
                }
                $total_amount += $n['quantity'] * $n['unit_price'];
            }
            unset($n);

            if (count($stockIssues) > 0) {
                return $this->json(['success' => false, 'error' => 'Matatizo ya kiasi cha bidhaa', 'stockIssues' => $stockIssues, 'code' => 'STOCK_ISSUES'], 400);
            }

            // Customer: get-or-create by name (same behaviour as manual sales).
            $customer_id = null;
            $customerName = trim((string) $request->input('customer_name', ''));
            if ($customerName !== '') {
                try {
                    $existingCustomer = Customer::where('seller_id', $userId)->whereRaw('LOWER(name) = LOWER(?)', [$customerName])->select(['id', 'name'])->first();
                    if ($existingCustomer) {
                        $customer_id = $existingCustomer->id;
                    } else {
                        $nowC = $this->isoNow();
                        $createdCustomer = Customer::create([
                            'id' => (string) Str::uuid(),
                            'seller_id' => $userId,
                            'name' => $customerName,
                            'phone' => trim((string) $request->input('customer_phone', '')),
                            'email' => '',
                            'total_purchases' => 0,
                            'purchases_count' => 0,
                            'created_at' => $nowC,
                            'updated_at' => $nowC,
                        ]);
                        $customer_id = $createdCustomer->id;
                    }
                } catch (Throwable $cErr) {
                }
            }

            $invoice_number = $this->generateInvoiceNumber($userId);
            $now = $this->isoNow();
            $newSaleId = (string) Str::uuid();

            $saleData = [
                'id' => $newSaleId,
                'seller_id' => $userId,
                'customer_id' => $customer_id,
                'sale_date' => trim((string) $request->input('sale_date', '')) ?: gmdate('Y-m-d'),
                'total_amount' => $total_amount,
                'payment_method' => trim((string) $request->input('payment_method', '')) ?: 'cash',
                'notes' => trim((string) $request->input('notes', '')) ?: null,
                'invoice_number' => $invoice_number,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($clientSaleKey !== '') {
                $saleData['notes'] = 'ai_dup_' . $clientSaleKey . ($saleData['notes'] ? ' | ' . $saleData['notes'] : '');
            }

            try {
                Sale::create($saleData);
            } catch (Throwable $e) {
                $this->log($userId, 'AI_SALE_COMMIT_FAILED', '/api/sales/ai-commit', ['error' => $e->getMessage()], $ip, 'failed');
                return $this->json(['error' => 'Hitilafu ya kuhifadhi rekodi ya mauzo', 'details' => $e->getMessage(), 'code' => 'SALE_SAVE_ERROR'], 500);
            }

            $saleItems = [];
            foreach ($normalized as $n) {
                $saleItems[] = [
                    'id' => (string) Str::uuid(),
                    'sale_id' => $newSaleId,
                    'product_id' => $n['product_id'],
                    'quantity' => $n['quantity'],
                    'unit_price' => $n['unit_price'],
                    'total_price' => $n['quantity'] * $n['unit_price'],
                    'created_at' => $now,
                ];
            }
            try {
                SaleItem::insert($saleItems);
            } catch (Throwable $e) {
                Sale::where('id', $newSaleId)->delete();
                $this->log($userId, 'AI_SALE_COMMIT_FAILED', '/api/sales/ai-commit', ['error' => $e->getMessage()], $ip, 'failed');
                return $this->json(['error' => 'Hitilafu ya kuhifadhi bidhaa za mauzo', 'details' => $e->getMessage(), 'code' => 'SALE_ITEMS_SAVE_ERROR'], 500);
            }

            // Stock deduction — same rules as the manual sale endpoint.
            $stockUpdates = [];
            foreach ($normalized as $n) {
                try {
                    $current = Product::where('id', $n['product_id'])->select(['stock'])->first();
                    if ($current) {
                        $newStock = $current->stock - $n['quantity'];
                        Product::where('id', $n['product_id'])->update(['stock' => $newStock, 'updated_at' => $this->isoNow()]);
                        $stockUpdates[] = ['product_id' => $n['product_id'], 'old_stock' => $current->stock, 'new_stock' => $newStock, 'quantity_sold' => $n['quantity']];
                    }
                } catch (Throwable $sErr) {
                }
            }

            // Customer purchase stats (same as manual sales).
            if ($customer_id) {
                try {
                    $currentCustomer = Customer::where('id', $customer_id)->select(['total_purchases', 'purchases_count'])->first();
                    if ($currentCustomer) {
                        Customer::where('id', $customer_id)->update([
                            'total_purchases' => ($currentCustomer->total_purchases ?? 0) + $total_amount,
                            'purchases_count' => ($currentCustomer->purchases_count ?? 0) + 1,
                            'last_purchase_date' => $this->isoNow(),
                            'updated_at' => $this->isoNow(),
                        ]);
                    }
                } catch (Throwable $e) {
                }
            }

            $this->log($userId, 'AI_SALE_COMMIT', '/api/sales/ai-commit', [
                'sale_id' => $newSaleId,
                'invoice_number' => $invoice_number,
                'total_amount' => $total_amount,
                'items_count' => count($saleItems),
                'stock_updates' => $stockUpdates,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Mauzo ya AI yamekamilika kikamilifu!',
                'invoice_number' => $invoice_number,
                'sale' => ['id' => $newSaleId, 'invoice_number' => $invoice_number, 'total_amount' => $total_amount],
            ], 201);
        } catch (Throwable $error) {
            $this->log($this->userId($request), 'AI_SALE_COMMIT_ERROR', '/api/sales/ai-commit', [
                'error' => $error->getMessage(),
            ], $this->ip($request), 'failed');
            return $this->json(['error' => 'Hitilafu isiyotarajiwa katika kuhifadhi mauzo ya AI: ' . $error->getMessage(), 'code' => 'AI_SALE_COMMIT_ERROR'], 500);
        }
    }
}