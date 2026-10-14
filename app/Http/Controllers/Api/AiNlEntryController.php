<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\UserLog;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\NlEntry;
use App\Services\Ai\ProductSearch;
use App\Services\BusinessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #25 - Schema-Free Natural-Language Data Entry.
 *
 * POST /api/ai/entry/preview  - one sentence in, one EDITABLE preview out.
 * POST /api/ai/entry/commit   - the confirmed preview, routed through the
 *                               EXISTING validated endpoints.
 * GET  /api/ai/entry/examples - the allow-listed record types + worked examples.
 *
 * Grounding and safety rules this controller enforces:
 *
 *  - The record type is an ALLOW-LIST (product, sale, expense, customer). No
 *    model output can introduce a new type, a new table or a column: the model
 *    is only ever asked to pick one of those four and to copy a phrase that the
 *    sentence already contains (NlEntry::sanitizeModel + modelNameIsGrounded).
 *  - Product references are resolved against the CALLER'S OWN catalogue through
 *    capability #24's ProductSearch. A near-tie is returned as `ambiguous` with
 *    NO product id - the capability never silently picks one for the user.
 *  - Nothing is written by `preview`. `commit` calls the EXISTING controller for
 *    the confirmed type (ProductController / SaleController / ExpenseController
 *    / CustomerController) with a synthesized request that carries the caller's
 *    JWT attributes, so all of the existing validation, ownership checks, stock
 *    rules, invoice numbering and audit trail apply unchanged. This capability
 *    deliberately owns no inventory, sales or expense logic of its own.
 *  - Every id a client sends back is RE-VERIFIED against the caller's business
 *    before it is forwarded (a product id from another shop is refused, not
 *    relayed).
 *  - Committed operations are idempotent per (user, idempotency_key): the key
 *    and the created record are recorded in `user_logs`, and replaying the key
 *    returns the ORIGINAL result instead of writing twice.
 */
class AiNlEntryController extends BaseController
{
    /** Roles allowed to record business data. */
    private const WRITE_ROLES = ['admin', 'seller', 'muuzaji', 'mteja_admin'];

    /** Where each confirmed type is actually written. */
    private const WORKFLOW = [
        NlEntry::TYPE_PRODUCT => ['endpoint' => '/api/products', 'controller' => ProductController::class, 'method' => 'store'],
        NlEntry::TYPE_SALE => ['endpoint' => '/api/sales', 'controller' => SaleController::class, 'method' => 'store'],
        NlEntry::TYPE_EXPENSE => ['endpoint' => '/api/office-expenses', 'controller' => ExpenseController::class, 'method' => 'store'],
        NlEntry::TYPE_CUSTOMER => ['endpoint' => '/api/customers', 'controller' => CustomerController::class, 'method' => 'store'],
    ];

    /* ------------------------------------------------------------------ */
    /* GET /api/ai/entry/examples                                         */
    /* ------------------------------------------------------------------ */

    public function examples(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        if (! $this->enabled()) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        return $this->json([
            'success' => true,
            'capability' => NlEntry::CAPABILITY,
            'locale' => $locale,
            'types' => array_map(
                static fn (string $type): array => ['type' => $type, 'label' => NlEntry::typeLabel($type, $locale)],
                NlEntry::TYPES
            ),
            'examples' => NlEntry::examples($locale),
            'categories' => NlEntry::categories(),
            'limits' => [
                'max_text' => (int) config('ai.entry.max_text', NlEntry::MAX_TEXT),
                'record_types' => NlEntry::TYPES,
            ],
            'messages' => NlEntry::MESSAGES[$locale] ?? NlEntry::MESSAGES['en'],
            'limitations' => NlEntry::limitations($locale),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* POST /api/ai/entry/preview                                         */
    /* ------------------------------------------------------------------ */

    public function preview(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! $this->enabled()) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $text = $this->cleanText((string) $request->input('text', ''));

        if ($text === '') {
            $this->log($userId, 'AI_ENTRY_PREVIEW_ERROR', '/api/ai/entry/preview', ['error' => 'TEXT_REQUIRED'], $ip, 'failed');

            return $this->json([
                'success' => false,
                'error' => NlEntry::message('placeholder', $locale),
                'code' => 'TEXT_REQUIRED',
                'examples' => NlEntry::examples($locale),
            ], 422);
        }

        try {
            // The clock the existing write paths use: "today" here means the day
            // the confirmed record would really be written under.
            $today = gmdate('Y-m-d');
            $classification = NlEntry::classify($text);
            $type = $classification['type'];
            $ai = ['available' => false, 'code' => null, 'reason' => null, 'used' => false];

            if ($type === null) {
                // The model may still recognise a type it was given the
                // allow-list for, but only the allow-list, and only when the
                // deterministic pass found nothing.
                $type = $this->modelType($text, $locale, $userId, $ip, $ai);
            }

            if ($type === null) {
                // Two honest explanations: "I cannot tell which of the four this
                // is" (keywords pointed at several) versus "this is not one of
                // the four kinds of record we can write".
                $explanation = $classification['decision'] === 'ambiguous_type'
                    ? NlEntry::flagMessage(NlEntry::FLAG_TYPE_UNCERTAIN, $locale)
                    : NlEntry::flagMessage(NlEntry::FLAG_TYPE_UNSUPPORTED, $locale);

                $this->log($userId, 'AI_ENTRY_PREVIEW', '/api/ai/entry/preview', [
                    'status' => 'unsupported',
                    'decision' => $classification['decision'],
                    'scores' => $classification['scores'],
                    'ai_used' => $ai['used'],
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ], $ip, 'success');

                return $this->json([
                    'success' => true,
                    'capability' => NlEntry::CAPABILITY,
                    'locale' => $locale,
                    'status' => 'unsupported',
                    'type' => null,
                    'type_label' => null,
                    'text' => $text,
                    'preview' => null,
                    'explanation' => $explanation,
                    'classification' => $classification,
                    'ai' => $ai,
                    'types' => array_map(
                        static fn (string $t): array => ['type' => $t, 'label' => NlEntry::typeLabel($t, $locale)],
                        NlEntry::TYPES
                    ),
                    'examples' => NlEntry::examples($locale),
                    'messages' => NlEntry::MESSAGES[$locale] ?? NlEntry::MESSAGES['en'],
                    'limitations' => NlEntry::limitations($locale),
                ]);
            }

            $draft = NlEntry::draft($type, $text, $locale, $today);
            $resolution = $this->resolve($type, $draft, $userId);

            if ($type === NlEntry::TYPE_SALE) {
                $draft['items'][0]['product_id'] = $resolution['items'][0]['product_id'] ?? null;
                $draft['items'][0]['product_name'] = $resolution['items'][0]['product_name'] ?? null;
                $draft['items'][0]['match'] = $resolution['items'][0]['match'] ?? null;
                $draft['customer_id'] = $resolution['customer_id'] ?? null;
                $draft['customer_status'] = $resolution['customer_status'] ?? null;
            }

            // Whether the product already exists decides which sentence the
            // user sees: "it will be created" versus "this already exists".
            if ($type === NlEntry::TYPE_PRODUCT) {
                $inspection = NlEntry::inspect($type, $draft, $today);
                $status = $resolution['product_status'] ?? 'not_found';
                $hasName = trim((string) ($draft['name'] ?? '')) !== '';
                if ($status === 'matched') {
                    $inspection['flags'][] = NlEntry::FLAG_PRODUCT_EXISTS;
                } elseif ($status !== 'ambiguous' && $hasName) {
                    // Only claim "it will be created" once we actually have a
                    // name to create it with.
                    $inspection['flags'][] = NlEntry::FLAG_PRODUCT_NEW;
                }
                $inspection['flags'] = array_values(array_unique($inspection['flags']));
            } else {
                $inspection = NlEntry::inspect($type, $draft, $today);
            }

            $flags = $this->flagPayload($inspection['flags'], $locale, $today);

            $this->log($userId, 'AI_ENTRY_PREVIEW', '/api/ai/entry/preview', [
                'status' => 'ready',
                'type' => $type,
                'flags' => $inspection['flags'],
                'missing_required' => $inspection['missing_required'],
                'candidates' => $resolution['catalogue']['candidates'] ?? 0,
                'ai_used' => $ai['used'],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => NlEntry::CAPABILITY,
                'locale' => $locale,
                'status' => $inspection['missing_required'] === [] ? 'ready' : 'needs_input',
                'type' => $type,
                'type_label' => NlEntry::typeLabel($type, $locale),
                'text' => $text,
                'preview' => $draft,
                'preview_editable' => true,
                'resolution' => $resolution,
                'flags' => $flags,
                'missing_required' => $inspection['missing_required'],
                'classification' => $classification,
                'ai' => $ai,
                'categories' => NlEntry::categories(),
                'units' => $this->unitOptions(),
                'payment_methods' => ['cash', 'mobile_money', 'bank', 'credit'],
                'commit' => [
                    'endpoint' => '/api/ai/entry/commit',
                    'method' => 'POST',
                    'requires' => ['confirm' => true, 'idempotency_key' => 'a stable key for this attempt'],
                    'workflow' => self::WORKFLOW[$type]['endpoint'],
                ],
                'messages' => NlEntry::MESSAGES[$locale] ?? NlEntry::MESSAGES['en'],
                'examples' => NlEntry::examples($locale),
                'limitations' => NlEntry::limitations($locale),
            ]);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_ENTRY_PREVIEW_ERROR', '/api/ai/entry/preview', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Imeshindwa kuchambua maelezo kwa sasa. Tafadhali jaribu tena.',
                'code' => 'ENTRY_PREVIEW_ERROR',
                'details' => (bool) config('app.debug') ? $error->getMessage() : null,
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* POST /api/ai/entry/commit                                          */
    /* ------------------------------------------------------------------ */

    public function commit(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);
        $locale = $this->resolveLocale($request);

        if (! $this->enabled()) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        // 1. An explicit human confirmation is required - a preview can never
        //    commit itself.
        if ($request->input('confirm') !== true && $request->input('confirm') !== 'true' && $request->input('confirm') !== 1) {
            return $this->json([
                'success' => false,
                'error' => 'Thibitisha kwanza kabla ya kuhifadhi.',
                'code' => 'CONFIRMATION_REQUIRED',
            ], 422);
        }

        // 2. The type must be one of the four the capability supports.
        $type = is_string($request->input('type')) ? mb_strtolower(trim($request->input('type'))) : '';
        if (! in_array($type, NlEntry::TYPES, true)) {
            return $this->json([
                'success' => false,
                'error' => NlEntry::flagMessage(NlEntry::FLAG_TYPE_UNSUPPORTED, $locale),
                'code' => 'TYPE_REQUIRED',
                'types' => NlEntry::TYPES,
            ], 422);
        }

        // 3. Permission: only staff who may already record data.
        $role = strtolower((string) $this->role($request));
        if (! in_array($role, self::WRITE_ROLES, true)) {
            $this->log($userId, 'AI_ENTRY_COMMIT_FAILED', '/api/ai/entry/commit', [
                'reason' => 'FORBIDDEN_ROLE',
                'role' => $role,
            ], $ip, 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Huna ruhusa ya kurekodi taarifa hizi.',
                'code' => 'FORBIDDEN_ROLE',
            ], 403);
        }

        // 4. Idempotency key: a stable per-attempt token from the client.
        $idempotencyKey = trim((string) $request->input('idempotency_key', ''));
        if (preg_match('/^[A-Za-z0-9._:\-]{6,120}$/', $idempotencyKey) !== 1) {
            return $this->json([
                'success' => false,
                'error' => 'Kitambulisho cha jaribio linahitajika (herufi 6 au zaidi).',
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ], 422);
        }

        try {
            // 5. A replayed key returns the ORIGINAL result: never a second write.
            $previous = $this->findCommitted($userId, $idempotencyKey);
            if ($previous !== null) {
                $this->log($userId, 'AI_ENTRY_COMMIT_DUPLICATE', '/api/ai/entry/commit', [
                    'type' => $type,
                    'idempotency_key' => $idempotencyKey,
                ], $ip, 'success');

                return $this->json([
                    'success' => true,
                    'committed' => true,
                    'duplicate' => true,
                    'type' => $type,
                    'message' => NlEntry::MESSAGES[$locale]['saved'] ?? NlEntry::MESSAGES['en']['saved'],
                    'idempotency_key' => $idempotencyKey,
                    'record' => $previous,
                ]);
            }

            $preview = is_array($request->input('preview')) ? $request->input('preview') : [];
            $validation = $this->validateCommit($type, $preview, $userId, $locale);
            if ($validation['error'] !== null) {
                $this->log($userId, 'AI_ENTRY_COMMIT_FAILED', '/api/ai/entry/commit', [
                    'type' => $type,
                    'reason' => $validation['error']['code'] ?? 'INVALID',
                    'missing_required' => $validation['missing_required'],
                ], $ip, 'failed');

                return $this->json(array_merge([
                    'success' => false,
                    'committed' => false,
                    'type' => $type,
                ], $validation['error']), $validation['status']);
            }

            $payload = $validation['payload'];
            $endpoint = self::WORKFLOW[$type]['endpoint'];
            $response = $this->forward($type, $payload);
            $status = $response->getStatusCode();
            $body = json_decode($response->getContent(), true);
            $body = is_array($body) ? $body : [];

            if ($status >= 400) {
                // The EXISTING workflow rejected it (its own validation, stock
                // rules or permissions). Report exactly that, never a silent
                // success and never a competing error of our own.
                $this->log($userId, 'AI_ENTRY_COMMIT_FAILED', '/api/ai/entry/commit', [
                    'type' => $type,
                    'reason' => 'WORKFLOW_REJECTED',
                    'workflow' => $endpoint,
                    'workflow_status' => $status,
                    'workflow_code' => $body['code'] ?? null,
                ], $ip, 'failed');

                return $this->json([
                    'success' => false,
                    'committed' => false,
                    'type' => $type,
                    'code' => 'WORKFLOW_REJECTED',
                    'workflow' => ['endpoint' => $endpoint, 'status' => $status],
                    'message' => $body['error'] ?? 'Rekodi haikukubaliwa na mfumo.',
                    'details' => $body,
                ], $status);
            }

            $record = $this->recordFrom($type, $body, $payload);

            $this->recordCommit($userId, $idempotencyKey, $type, $endpoint, $record, $ip);

            return $this->json([
                'success' => true,
                'committed' => true,
                'duplicate' => false,
                'type' => $type,
                'type_label' => NlEntry::typeLabel($type, $locale),
                'message' => $body['message'] ?? (NlEntry::MESSAGES[$locale]['saved'] ?? NlEntry::MESSAGES['en']['saved']),
                'idempotency_key' => $idempotencyKey,
                'workflow' => ['endpoint' => $endpoint, 'status' => $status],
                'record' => $record,
                'details' => $body,
            ], $status === 201 ? 201 : 200);
        } catch (Throwable $error) {
            $this->log($userId, 'AI_ENTRY_COMMIT_ERROR', '/api/ai/entry/commit', [
                'type' => $type,
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'success' => false,
                'committed' => false,
                'type' => $type,
                'error' => 'Hitilafu ya kuhifadhi rekodi.',
                'code' => 'ENTRY_COMMIT_ERROR',
                'details' => (bool) config('app.debug') ? $error->getMessage() : null,
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Resolution against the caller's OWN business                       */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve the draft's references against the caller's catalogue/customers.
     * Read-only, business-scoped, and it never invents an identifier.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function resolve(string $type, array $draft, ?string $userId): array
    {
        $catalogue = $this->authorizedCatalogue($userId);

        $out = [
            'catalogue' => [
                'products' => count($catalogue['rows']),
                'scanned' => $catalogue['scanned'],
                'degraded' => $catalogue['degraded'],
                'degraded_reason' => $catalogue['degraded_reason'],
            ],
            'items' => [],
        ];

        if ($type === NlEntry::TYPE_SALE) {
            foreach ($draft['items'] ?? [] as $item) {
                $reference = (string) ($item['reference'] ?? '');
                $match = $this->matchProduct($catalogue['rows'], $reference);
                $out['items'][] = [
                    'reference' => $reference,
                    'match' => $match,
                    'product_id' => $match['product_id'] ?? null,
                    'product_name' => $match['product_name'] ?? null,
                ];
            }

            $customerName = (string) ($draft['customer_name'] ?? '');
            $customer = $this->resolveCustomer($userId, $customerName);
            $out['customer_id'] = $customer['customer_id'];
            $out['customer_status'] = $customer['status'];
            $out['customer_candidates'] = $customer['candidates'];
        }

        if ($type === NlEntry::TYPE_PRODUCT) {
            // matchProduct() reports `not_found` for an empty reference itself,
            // so there is exactly ONE place that builds the match shape (an
            // inline short-circuit here once produced a response with no
            // candidates key at all and a 500 on a live row).
            $existing = $this->matchProduct($catalogue['rows'], (string) ($draft['name'] ?? ''));
            $out['existing_product'] = $existing['status'] === 'matched' ? [
                'product_id' => $existing['product_id'],
                'name' => $existing['product_name'],
                'candidates' => $existing['candidates'],
            ] : null;
            $out['product_status'] = $existing['status'];
            $out['product_candidates'] = $existing['candidates'];
        }

        if ($type === NlEntry::TYPE_EXPENSE) {
            $out['categories'] = NlEntry::categories();
        }

        return $out;
    }

    /**
     * The caller's own sellable products, shaped exactly like the public
     * catalogue rows PRODUCTSEARCH expects. Scoped through the business's
     * member ids, so one shop can never see another's products.
     *
     * @return array{rows: array<int, array<string, mixed>>, scanned: int, degraded: bool, degraded_reason: ?string}
     */
    private function authorizedCatalogue(?string $userId): array
    {
        $empty = ['rows' => [], 'scanned' => 0, 'degraded' => false, 'degraded_reason' => null];
        if (! $userId) {
            return $empty;
        }

        try {
            $memberIds = $this->memberIds($userId);
            $rows = Product::whereIn('seller_id', $memberIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(max(1, (int) config('ai.entry.max_catalogue', 20000)))
                ->get()
                ->toArray();

            $businessId = BusinessResolver::idForUser($userId);
            $shaped = [];
            foreach ($rows as $row) {
                $stock = (int) ($row['stock'] ?? 0);
                $shaped[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'description' => $row['description'] ?? null,
                    'category' => $row['category'] ?? null,
                    'unit' => $row['unit'] ?? null,
                    'stock' => $stock,
                    'selling_price' => (float) ($row['expected_selling_price'] ?? 0),
                    'available' => $stock > 0,
                    'business_id' => $businessId,
                    'business_name' => null,
                ];
            }

            return [
                'rows' => $shaped,
                'scanned' => count($shaped),
                'degraded' => false,
                'degraded_reason' => null,
            ];
        } catch (Throwable $error) {
            // A catalogue that cannot be read is SAID OUT LOUD: the caller must
            // never mistake an outage for "your product does not exist".
            return [
                'rows' => [],
                'scanned' => 0,
                'degraded' => true,
                'degraded_reason' => 'Catalogue read failed: '.$error->getMessage(),
            ];
        }
    }

    /**
     * Member ids of the caller's business, memoised for the life of the
     * request. Every Supabase roundtrip costs a few hundred milliseconds here,
     * and preview/commit ask for the same list more than once - measuring that
     * is what prompted this cache.
     *
     * @return array<int, string>
     */
    private function memberIds(string $userId): array
    {
        if (isset($this->memberIdCache[$userId])) {
            return $this->memberIdCache[$userId];
        }

        $businessId = BusinessResolver::idForUser($userId);
        if (! $businessId) {
            return $this->memberIdCache[$userId] = [$userId];
        }

        $members = BusinessResolver::memberIds($businessId, ['admin', 'seller'], 'approved');

        return $this->memberIdCache[$userId] = ($members === [] ? [$userId] : $members);
    }

    /** @var array<string, array<int, string>> */
    private array $memberIdCache = [];

    /**
     * Rank the reference against the caller's catalogue and turn the ranking
     * into a decision. A near-tie stays ambiguous and carries no id.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function matchProduct(array $rows, string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return ['status' => 'not_found', 'product_id' => null, 'product_name' => null, 'confidence' => 0.0, 'candidates' => []];
        }

        try {
            $ranked = ProductSearch::rank($rows, $reference, [
                'limit' => 5,
                'min_score' => (float) config('ai.entry.match_min_score', 0.2),
            ]);

            return NlEntry::chooseMatch($ranked['results'] ?? [], (float) config('ai.entry.match_margin', 0.85));
        } catch (Throwable $error) {
            return ['status' => 'not_found', 'product_id' => null, 'product_name' => null, 'confidence' => 0.0, 'candidates' => []];
        }
    }

    /**
     * An existing customer with that exact name, or the fact that they are new.
     * Two customers with the same name stay ambiguous - the capability never
     * picks one of them.
     *
     * @return array{status: string, customer_id: ?string, candidates: array<int, array<string, mixed>>}
     */
    private function resolveCustomer(?string $userId, string $name): array
    {
        $name = trim($name);
        if ($userId === null || $name === '') {
            return ['status' => $name === '' ? 'none' : 'new', 'customer_id' => null, 'candidates' => []];
        }

        try {
            $rows = Customer::whereIn('seller_id', $this->memberIds($userId))
                ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                ->select(['id', 'name', 'phone'])
                ->limit(5)
                ->get()
                ->toArray();
        } catch (Throwable $error) {
            return ['status' => 'new', 'customer_id' => null, 'candidates' => []];
        }

        if (count($rows) === 1) {
            return [
                'status' => 'existing',
                'customer_id' => (string) ($rows[0]['id'] ?? ''),
                'candidates' => array_map(fn (array $r): array => [
                    'customer_id' => (string) ($r['id'] ?? ''),
                    'name' => (string) ($r['name'] ?? ''),
                    'phone' => $r['phone'] ?? null,
                ], $rows),
            ];
        }

        if (count($rows) > 1) {
            return [
                'status' => 'ambiguous',
                'customer_id' => null,
                'candidates' => array_map(fn (array $r): array => [
                    'customer_id' => (string) ($r['id'] ?? ''),
                    'name' => (string) ($r['name'] ?? ''),
                    'phone' => $r['phone'] ?? null,
                ], $rows),
            ];
        }

        return ['status' => 'new', 'customer_id' => null, 'candidates' => []];
    }

    /* ------------------------------------------------------------------ */
    /* Commit helpers                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Re-validate the submitted preview BEFORE anything is forwarded. Every
     * identifier is verified against the caller's own business here.
     *
     * @param  array<string, mixed>  $preview
     * @return array{error: ?array<string, mixed>, status: int, payload: array<string, mixed>, missing_required: array<int, string>}
     */
    private function validateCommit(string $type, array $preview, ?string $userId, string $locale): array
    {
        $fail = static fn (string $code, string $message, int $status, array $missing = []): array => [
            'error' => ['error' => $message, 'code' => $code, 'missing_required' => $missing],
            'status' => $status,
            'payload' => [],
            'missing_required' => $missing,
        ];

        if ($type === NlEntry::TYPE_PRODUCT) {
            $name = trim((string) ($preview['name'] ?? ''));
            $category = trim((string) ($preview['category'] ?? ''));
            $price = $this->number($preview['price'] ?? null);
            $selling = $this->number($preview['expected_selling_price'] ?? null);
            $stock = $preview['stock'] ?? null;
            $stock = is_numeric($stock) ? (int) $stock : null;

            $missing = [];
            if ($name === '') {
                $missing[] = 'name';
            }
            if ($category === '') {
                $missing[] = 'category';
            }
            if ($price === null || $price <= 0) {
                $missing[] = 'price';
            }
            if ($selling === null || $selling <= 0) {
                $missing[] = 'expected_selling_price';
            }
            if ($stock === null || $stock < 0) {
                $missing[] = 'stock';
            }

            if ($missing !== []) {
                return $fail('MISSING_FIELDS', NlEntry::flagMessage(NlEntry::FLAG_NAME_MISSING, $locale), 422, $missing);
            }

            return [
                'error' => null,
                'status' => 200,
                'missing_required' => [],
                'payload' => [
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                    'expected_selling_price' => $selling,
                    'stock' => $stock,
                    'description' => trim((string) ($preview['description'] ?? '')),
                    'unit' => trim((string) ($preview['unit'] ?? '')),
                ],
            ];
        }

        if ($type === NlEntry::TYPE_SALE) {
            $items = is_array($preview['items'] ?? null) ? $preview['items'] : [];
            $saleItems = [];
            $missing = [];

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $productId = trim((string) ($item['product_id'] ?? ''));
                $quantity = is_numeric($item['quantity'] ?? null) ? (int) $item['quantity'] : 0;
                $unitPrice = $this->number($item['unit_price'] ?? null);

                if ($productId === '' || ! $this->productBelongsToCaller($userId, $productId)) {
                    $missing[] = 'items['.$index.'].product_id';
                    continue;
                }
                if ($quantity <= 0) {
                    $missing[] = 'items['.$index.'].quantity';
                    continue;
                }
                if ($unitPrice === null || $unitPrice <= 0) {
                    $missing[] = 'items['.$index.'].unit_price';
                    continue;
                }

                $saleItems[] = ['product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice];
            }

            if ($missing !== [] || $saleItems === []) {
                return $fail(
                    'PRODUCT_NOT_RESOLVED',
                    NlEntry::flagMessage(NlEntry::FLAG_PRODUCT_NOT_FOUND, $locale),
                    422,
                    $missing
                );
            }

            $payload = [
                'items' => $saleItems,
                'sale_date' => $this->date((string) ($preview['sale_date'] ?? '')),
                'payment_method' => trim((string) ($preview['payment_method'] ?? 'cash')) ?: 'cash',
                'notes' => trim((string) ($preview['notes'] ?? '')),
            ];

            // Customer: an id we already verified, or a name the EXISTING
            // customer endpoint will create/return.
            $customerId = trim((string) ($preview['customer_id'] ?? ''));
            if ($customerId !== '' && $this->customerBelongsToCaller($userId, $customerId)) {
                $payload['customer_id'] = $customerId;
            } elseif (trim((string) ($preview['customer_name'] ?? '')) !== '') {
                $payload['customer_name'] = trim((string) $preview['customer_name']);
            }

            return ['error' => null, 'status' => 200, 'missing_required' => [], 'payload' => $payload];
        }

        if ($type === NlEntry::TYPE_EXPENSE) {
            $amount = $this->number($preview['amount'] ?? null);
            $description = trim((string) ($preview['description'] ?? ''));
            $category = trim((string) ($preview['category'] ?? ''));

            $missing = [];
            if ($amount === null || $amount <= 0) {
                $missing[] = 'amount';
            }
            if (mb_strlen($description) < 3) {
                $missing[] = 'description';
            }
            if ($category === '') {
                $missing[] = 'category';
            }
            if ($missing !== []) {
                return $fail('MISSING_FIELDS', NlEntry::flagMessage(NlEntry::FLAG_AMOUNT_MISSING, $locale), 422, $missing);
            }

            return [
                'error' => null,
                'status' => 200,
                'missing_required' => [],
                'payload' => [
                    'amount' => $amount,
                    'description' => $description,
                    'category' => $category,
                    'notes' => trim((string) ($preview['notes'] ?? '')),
                ],
            ];
        }

        // Customer
        $name = trim((string) ($preview['name'] ?? ''));
        if ($name === '') {
            return $fail('MISSING_FIELDS', NlEntry::flagMessage(NlEntry::FLAG_NAME_MISSING, $locale), 422, ['name']);
        }

        return [
            'error' => null,
            'status' => 200,
            'missing_required' => [],
            'payload' => [
                'name' => $name,
                'phone' => trim((string) ($preview['phone'] ?? '')),
                'email' => trim((string) ($preview['email'] ?? '')),
            ],
        ];
    }

    /** A product id is only accepted if it is in the caller's own catalogue. */
    private function productBelongsToCaller(?string $userId, string $productId): bool
    {
        if (! $userId) {
            return false;
        }

        try {
            return Product::whereIn('seller_id', $this->memberIds($userId))
                ->where('id', $productId)
                ->where('is_active', true)
                ->select(['id'])
                ->first() !== null;
        } catch (Throwable $error) {
            return false;
        }
    }

    /** A customer id is only accepted if the caller's business owns it. */
    private function customerBelongsToCaller(?string $userId, string $customerId): bool
    {
        if (! $userId) {
            return false;
        }

        try {
            return Customer::whereIn('seller_id', $this->memberIds($userId))
                ->where('id', $customerId)
                ->select(['id'])
                ->first() !== null;
        } catch (Throwable $error) {
            return false;
        }
    }

    /**
     * Send the confirmed payload through the EXISTING endpoint's controller with
     * a request that carries the caller's JWT attributes, so its validation,
     * ownership checks, business rules and audit trail all apply.
     *
     * @param  array<string, mixed>  $payload
     */
    private function forward(string $type, array $payload): JsonResponse
    {
        $source = request();

        // A customer created as part of a sale goes through its own existing
        // endpoint first, so customer creation keeps its one code path.
        if ($type === NlEntry::TYPE_SALE && isset($payload['customer_name']) && ! isset($payload['customer_id'])) {
            $resolved = $this->resolveCustomer($this->userId($source), (string) $payload['customer_name']);
            if ($resolved['status'] === 'existing') {
                $payload['customer_id'] = $resolved['customer_id'];
            } else {
                $created = $this->dispatch(CustomerController::class, 'store', $source, [
                    'name' => $payload['customer_name'],
                    'phone' => (string) ($payload['customer_phone'] ?? ''),
                ]);
                $body = json_decode($created->getContent(), true);
                $newId = is_array($body) ? ($body['customer']['id'] ?? null) : null;
                if ($created->getStatusCode() < 400 && is_string($newId) && $newId !== '') {
                    $payload['customer_id'] = $newId;
                }
            }
            unset($payload['customer_name'], $payload['customer_phone']);
        }

        $workflow = self::WORKFLOW[$type];

        return $this->dispatch($workflow['controller'], $workflow['method'], $source, $payload);
    }

    /**
     * Call another controller's action with a synthesized request. The JWT
     * attributes are copied over, so `userId()`, `role()`, `businessId()` and
     * `resolveLocale()` inside that controller behave exactly as they would for
     * a direct request.
     *
     * @param  class-string  $controller
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $controller, string $method, Request $source, array $payload): JsonResponse
    {
        $sub = Request::create('/api/internal/nl-entry', 'POST', $payload);

        foreach (['jwt_user', 'jwt_user_id', 'jwt_role', 'jwt_business_id', 'jwt_business_name', 'jwt_language', 'jwt_ip'] as $key) {
            if ($source->attributes->has($key)) {
                $sub->attributes->set($key, $source->attributes->get($key));
            }
        }

        $sub->setUserResolver(static fn () => $source->user());

        return app($controller)->{$method}($sub);
    }

    /** The created record, normalized for the response. */
    private function recordFrom(string $type, array $body, array $payload): array
    {
        return match ($type) {
            NlEntry::TYPE_PRODUCT => [
                'id' => $body['product']['id'] ?? null,
                'name' => $body['product']['name'] ?? ($payload['name'] ?? null),
                'stock' => $body['product']['stock'] ?? null,
            ],
            NlEntry::TYPE_EXPENSE => [
                'id' => $body['expense']['id'] ?? null,
                'amount' => $body['expense']['amount'] ?? null,
                'category' => $body['expense']['category'] ?? null,
                'expense_date' => $body['expense']['expense_date'] ?? null,
            ],
            NlEntry::TYPE_CUSTOMER => [
                'id' => $body['customer']['id'] ?? null,
                'name' => $body['customer']['name'] ?? null,
            ],
            default => [
                'id' => $body['sale']['id'] ?? null,
                'invoice_number' => $body['sale']['invoice_number'] ?? ($body['invoice_number'] ?? null),
                'total_amount' => $body['sale']['total_amount'] ?? null,
                'items' => $payload['items'] ?? [],
            ],
        };
    }

    /* ------------------------------------------------------------------ */
    /* Idempotency (durable, per user, in the existing audit table)        */
    /* ------------------------------------------------------------------ */

    /** Was this exact attempt already committed? Returns the stored record. */
    private function findCommitted(?string $userId, string $key): ?array
    {
        if (! $userId || $key === '') {
            return null;
        }

        try {
            $row = UserLog::where('user_id', $userId)
                ->where('action', 'AI_ENTRY_COMMIT')
                ->where('details', 'like', '%"idempotency_key":"'.$key.'"%')
                ->orderByDesc('created_at')
                ->limit(1)
                ->first();

            if (! $row) {
                return null;
            }

            $details = $row['details'] ?? null;
            if (is_string($details)) {
                $details = json_decode($details, true);
            }

            return is_array($details) ? ($details['record'] ?? null) : null;
        } catch (Throwable $error) {
            // If the log cannot be read we continue - and the existing
            // workflow's own duplicate rules are still in force.
            return null;
        }
    }

    /**
     * Record the committed attempt. This row IS the idempotency ledger: the key
     * plus what was created, scoped to the user who created it.
     *
     * @param  array<string, mixed>  $record
     */
    private function recordCommit(?string $userId, string $key, string $type, string $endpoint, array $record, ?string $ip): void
    {
        $this->log($userId, 'AI_ENTRY_COMMIT', '/api/ai/entry/commit', [
            'type' => $type,
            'workflow' => $endpoint,
            'idempotency_key' => $key,
            'record' => $record,
        ], $ip, 'success');
    }

    /* ------------------------------------------------------------------ */
    /* Small helpers                                                      */
    /* ------------------------------------------------------------------ */

    private function enabled(): bool
    {
        return (bool) config('ai.features.nl_entry', true) && (bool) config('ai.entry.enabled', true);
    }

    private function cleanText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $max = (int) config('ai.entry.max_text', NlEntry::MAX_TEXT);

        return mb_substr($text, 0, max(20, $max));
    }

    private function number($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : gmdate('Y-m-d');
    }

    /** @return array<string, string> */
    private function unitOptions(): array
    {
        return [
            'bottle' => 'bottle/chupa',
            'piece' => 'piece/kipande',
            'kg' => 'kg/kilo',
            'litre' => 'litre/lita',
            'box' => 'box/sanduku',
            'packet' => 'packet/pakiti',
            'bag' => 'bag/gunia',
            'pair' => 'pair/jozi',
            'dozen' => 'dozen/dazani',
            'carton' => 'carton/katoni',
            'roll' => 'roll/roli',
            'sheet' => 'sheet/karatasi',
            'metre' => 'metre/mita',
        ];
    }

    /**
     * Localized flag payload (code + message) for the client to render.
     *
     * @param  array<int, string>  $codes
     * @return array<int, array<string, string>>
     */
    private function flagPayload(array $codes, string $locale, string $today): array
    {
        $out = [];
        foreach ($codes as $code) {
            $out[] = [
                'code' => $code,
                'message' => NlEntry::flagMessage($code, $locale, ['date' => $today, 'count' => 0]),
            ];
        }

        return $out;
    }

    /**
     * Ask the model for a type ONLY when the deterministic classifier found
     * none, and only to choose from the allow-list. A proposed name is accepted
     * only if the sentence really contains it.
     */
    private function modelType(string $text, string $locale, ?string $userId, ?string $ip, array &$ai): ?string
    {
        // The model step is optional and OFF-able: the deterministic classifier
        // handles the ordinary sentences, and it is only consulted when that
        // pass is not confident. A live measurement put this step at ~13 s, so
        // an operator can turn it off entirely (ai.entry.model_assist).
        if (! (bool) config('ai.entry.model_assist', true)) {
            $ai['code'] = 'AI_ASSIST_DISABLED';

            return null;
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $ai['code'] = $client->isEnabled() ? 'AI_NOT_CONFIGURED' : 'AI_DISABLED';

            return null;
        }

        try {
            $result = $client->generateJson(
                [['text' => NlEntry::userPrompt($text, $locale)]],
                NlEntry::systemInstruction(),
                [
                    'capability' => NlEntry::CAPABILITY,
                    'user_id' => $userId,
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.1,
                    'maxOutputTokens' => 512,
                ],
            );

            $ai['used'] = true;
            $model = NlEntry::sanitizeModel($result->data);

            if ($model === null || $model['type'] === null) {
                $ai['code'] = 'AI_NO_TYPE';

                return null;
            }

            $ai['available'] = true;

            return $model['type'];
        } catch (AiException $error) {
            $ai['code'] = $error->errorCode;
            $ai['reason'] = $error->userReason($locale);

            return null;
        } catch (Throwable $error) {
            $ai['code'] = 'AI_ERROR';
            $ai['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

            return null;
        }
    }
}
