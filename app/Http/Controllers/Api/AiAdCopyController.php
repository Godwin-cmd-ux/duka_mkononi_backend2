<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Services\Ai\AdCopyService;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #8 - Advertisement & Product-Copy Generator.
 *
 * POST /api/ai/ads/generate
 *
 * Read-only studio endpoint: it drafts headline/description/product-copy/promo
 * text grounded in the seller's OWN product rows (name, description, price,
 * stock) and their stated objective. It never writes to `advertisements` and
 * never calls the payment flow - the copy only reaches the platform when the
 * seller approves it through the existing POST /api/matangazo workflow, which
 * keeps its own validation, ownership checks and audit trail.
 *
 * Grounding: numbers in the draft must exist in the facts we sent, guarantee
 * language must already be in the seller's own text, and length is validated
 * against configured caps (AdCopyService::sanitize).
 */
class AiAdCopyController extends BaseController
{
    public function generate(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $debug = (bool) config('app.debug');

        if (! config('ai.features.ad_copy', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $contentType = (string) $request->input('content_type', 'description');
        if (! in_array($contentType, AdCopyService::CONTENT_TYPES, true)) {
            return $this->json(['error' => 'Aina ya maandishi si sahihi.', 'code' => 'INVALID_CONTENT_TYPE'], 422);
        }

        $tone = (string) $request->input('tone', 'friendly');
        if (! in_array($tone, AdCopyService::TONES, true)) {
            return $this->json(['error' => 'Mtindo (tone) si sahihi.', 'code' => 'INVALID_TONE'], 422);
        }

        $length = (string) $request->input('length', 'medium');
        if (! in_array($length, AdCopyService::LENGTHS, true)) {
            return $this->json(['error' => 'Urefu si sahihi.', 'code' => 'INVALID_LENGTH'], 422);
        }

        $productId = trim((string) $request->input('product_id', ''));
        if ($productId === '') {
            return $this->json(['error' => 'Chagua bidhaa kwanza.', 'code' => 'PRODUCT_REQUIRED'], 422);
        }

        $locale = $this->resolveLocale($request);
        $controls = [
            'content_type' => $contentType,
            'tone' => $tone,
            'length' => $length,
            'audience' => mb_substr(trim((string) $request->input('audience', '')), 0, 60),
            'objective' => mb_substr(trim((string) $request->input('objective', '')), 0, 140),
        ];

        $product = $this->ownedProduct($userId, $productId);
        if ($product === null) {
            $this->log($userId, 'AI_AD_COPY_ERROR', '/api/ai/ads/generate', [
                'error' => 'PRODUCT_NOT_FOUND',
                'product_id' => $productId,
            ], $ip, 'failed');

            return $this->json([
                'error' => 'Bidhaa haipatikani kwenye biashara yako.',
                'code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        $ai = [
            'available' => false,
            'degraded' => false,
            'code' => null,
            'reason' => null,
            'meta' => null,
        ];
        $san = [
            'ok' => false,
            'copy' => [],
            'flags' => [],
            'code' => null,
            'reason' => null,
        ];

        $client = new GeminiClient;

        if (! $client->isEnabled()) {
            $ai['degraded'] = true;
            $ai['code'] = 'AI_DISABLED';
            $ai['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);
        } elseif (! $client->isConfigured()) {
            $ai['degraded'] = true;
            $ai['code'] = 'AI_NOT_CONFIGURED';
            $ai['reason'] = AiException::reasonForCode('AI_NOT_CONFIGURED', $locale);
        } else {
            try {
                $result = $client->generateJson(
                    [['text' => AdCopyService::userPrompt($locale, $product, $controls)]],
                    AdCopyService::systemInstruction(),
                    [
                        'capability' => AdCopyService::CAPABILITY,
                        'user_id' => $userId,
                        'ip' => $ip,
                        'locale' => $locale,
                        'temperature' => 0.6,
                        'maxOutputTokens' => 1024,
                    ],
                );

                $ai['meta'] = $result->meta();
                $data = is_array($result->data) ? $result->data : null;
                $san = AdCopyService::sanitize($data, $product, $controls, $locale);

                if ($san['ok']) {
                    $ai['available'] = true;
                } else {
                    // A rejected or empty draft degrades honestly with the
                    // localized reason - the seller can adjust and retry.
                    $ai['degraded'] = true;
                    $ai['code'] = $san['code'];
                    $ai['reason'] = $san['reason'];
                }
            } catch (AiException $error) {
                $ai['degraded'] = true;
                $ai['code'] = $error->errorCode;
                $ai['reason'] = $error->userReason($locale);
            } catch (Throwable $error) {
                $ai['degraded'] = true;
                $ai['code'] = 'AI_ERROR';
                $ai['reason'] = AiException::reasonForCode('AI_ERROR', $locale);
            }
        }

        $this->log($userId, 'AI_AD_COPY_GENERATE', '/api/ai/ads/generate', [
            'content_type' => $contentType,
            'tone' => $tone,
            'length' => $length,
            'product_id' => $productId,
            'flag_codes' => array_column($san['flags'], 'code'),
            'copy_ok' => (bool) $san['ok'],
            'ai_code' => $ai['code'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ], $ip, $san['ok'] || $ai['degraded'] ? 'success' : 'failed');

        return $this->json([
            'success' => true,
            'capability' => AdCopyService::CAPABILITY,
            'locale' => $locale,
            'generated_at' => now()->toISOString(true),
            'product' => [
                'id' => (string) ($product['id'] ?? ''),
                'name' => (string) ($product['name'] ?? ''),
                'category' => (string) ($product['category'] ?? ''),
                'price' => (float) ($product['price'] ?? 0),
                'stock' => (float) ($product['stock'] ?? 0),
                'image_url' => (string) ($product['image_url'] ?? ''),
            ],
            'controls' => $controls,
            'limits' => AdCopyService::limits($contentType),
            'copy' => $san['copy'],
            'flags' => $san['flags'],
            'ai' => $ai,
            'limitations' => AdCopyService::limitations($locale),
        ]);
    }

    /**
     * Ownership-scoped product lookup: the id must belong to one of the
     * caller's own business member accounts and the product must be active.
     *
     * @return array<string, mixed>|null
     */
    private function ownedProduct(?string $userId, string $productId): ?array
    {
        try {
            $businessId = $userId ? BusinessResolver::idForUser($userId) : null;
            if (! $businessId) {
                return null;
            }

            $memberIds = BusinessResolver::memberIds($businessId);
            if (! is_array($memberIds) || $memberIds === []) {
                $memberIds = $userId ? [$userId] : [];
            }
            if ($memberIds === []) {
                return null;
            }

            $product = Product::whereIn('seller_id', $memberIds)
                ->where('id', $productId)
                ->where('is_active', true)
                ->select([
                    'id', 'name', 'category', 'description', 'price',
                    'expected_selling_price', 'stock', 'image_url', 'seller_id',
                ])
                ->first();

            return $product ? $product->toArray() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
