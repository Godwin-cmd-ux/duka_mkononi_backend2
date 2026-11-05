<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceConsumable;
use App\Models\ServiceSale;
use App\Models\ServiceSaleItem;
use App\Services\BusinessResolver;
use App\Services\Roles;
use App\Services\Supabase;
use Illuminate\Http\Request;

/**
 * Uza Huduma — selling a service.
 *
 * The seller chooses a service, adjusts how much of each consumable was
 * actually used, and types the amount charged. The browser previews the
 * expected profit, but nothing it sends is trusted:
 *
 *   - business, seller and branch come from the verified JWT;
 *   - buying prices are read from the database at sale time;
 *   - quantities, stock and the final revenue/cost/profit are computed inside
 *     `create_service_sale` (one database transaction with row locks).
 *
 * That makes a multi-product service sale all-or-nothing: if any consumable is
 * short, no stock is deducted and no sale row is written.
 */
class ServiceSaleController extends BaseController
{
    private const PRODUCT_COLUMNS = ['id', 'name', 'price', 'stock', 'is_active', 'seller_id'];

    /** GET /api/service-sales/my — the seller's own completed service sales. */
    public function my(Request $request)
    {
        try {
            $userId = $this->userId($request);

            $query = ServiceSale::where('seller_id', $userId);

            $dateFrom = trim((string) $request->query('date_from', ''));
            $dateTo = trim((string) $request->query('date_to', ''));

            if ($dateFrom !== '') {
                $query->where('sale_date', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $query->where('sale_date', '<=', $dateTo);
            }

            $sales = $query->orderByDesc('created_at')->get()->toArray();

            $this->log($userId, 'SERVICE_SALES_VIEW', '/api/service-sales/my', [
                'count' => count($sales),
            ], $this->ip($request), 'success');

            return $this->json($this->hydrate($sales));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'SERVICE_SALES_VIEW_ERROR', '/api/service-sales/my', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->dbError($e);
        }
    }

    /**
     * GET /api/admin/service-sales — every service sale in the caller's
     * business, for the administrator's Mauzo page and reports.
     *
     * Filters: date_from, date_to, seller_id, service_id. The business scope is
     * never taken from the query string.
     */
    public function adminIndex(Request $request)
    {
        if (!$this->isAdmin($request)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $businessId = $this->businessId($request);

            if (!$businessId) {
                return $this->json([]);
            }

            $memberIds = BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved');

            if ($memberIds === []) {
                return $this->json([]);
            }

            $query = ServiceSale::where('business_id', $businessId);

            $dateFrom = trim((string) ($request->query('date_from', '') ?: $request->query('start_date', '')));
            $dateTo = trim((string) ($request->query('date_to', '') ?: $request->query('end_date', '')));

            if ($dateFrom !== '') {
                $query->where('sale_date', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $query->where('sale_date', '<=', $dateTo);
            }

            $sellerFilter = trim((string) $request->query('seller_id', ''));
            if ($sellerFilter !== '' && in_array($sellerFilter, $memberIds, true)) {
                $query->where('seller_id', $sellerFilter);
            }

            $serviceFilter = trim((string) $request->query('service_id', ''));
            if ($serviceFilter !== '') {
                $query->where('service_id', $serviceFilter);
            }

            $sales = $query->orderByDesc('created_at')->get()->toArray();
            $sales = $this->hydrate($sales);

            $this->log($this->userId($request), 'ADMIN_SERVICE_SALES_VIEW', '/api/admin/service-sales', [
                'count' => count($sales),
            ], $this->ip($request), 'success');

            return $this->json([
                'service_sales' => $sales,
                'summary' => $this->summary($sales),
            ]);
        } catch (\Throwable $e) {
            return $this->dbError($e);
        }
    }

    /**
     * POST /api/service-sales
     *
     * Expected body:
     * {
     *   "service_id": "uuid",
     *   "service_quantity": 1,
     *   "selling_price": 35000,
     *   "consumables": [{"product_id": "uuid", "quantity": 2}],
     *   "customer_id": null,
     *   "payment_method": "cash",
     *   "sale_date": "2026-11-05",
     *   "notes": "",
     *   "clientSaleKey": "uza-huduma-..."
     * }
     *
     * `consumables` may be omitted; the service's saved recipe is then used.
     */
    public function store(Request $request)
    {
        // A service sale is one database call, but the pre-checks are several
        // Supabase roundtrips; give slow networks room (see SaleController).
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $userId = $this->userId($request);
        $businessId = $this->businessId($request);

        if (!in_array((string) $this->role($request), Roles::BUSINESS_MEMBERS, true)) {
            return $this->json([
                'success' => false,
                'error' => 'Huna ruhusa kuuza huduma',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        if (!$businessId) {
            return $this->json([
                'success' => false,
                'error' => 'Biashara haijapatikana',
                'code' => 'NO_BUSINESS',
            ], 403);
        }

        try {
            $serviceId = trim((string) $request->input('service_id', ''));

            $service = $serviceId === ''
                ? null
                : Service::where('id', $serviceId)->where('business_id', $businessId)->first();

            if (!$service) {
                return $this->json([
                    'success' => false,
                    'error' => 'Huduma haipatikani kwenye biashara yako',
                    'code' => 'SERVICE_NOT_FOUND',
                ], 404);
            }

            if ((string) ($service->status ?? 'active') !== 'active') {
                return $this->json([
                    'success' => false,
                    'error' => 'Huduma hii imezimwa',
                    'code' => 'SERVICE_INACTIVE',
                ], 422);
            }

            $serviceQuantity = $request->input('service_quantity', 1);
            if (!is_numeric($serviceQuantity) || (float) $serviceQuantity <= 0 || (float) $serviceQuantity > 100000) {
                return $this->json([
                    'success' => false,
                    'error' => 'Idadi ya huduma si sahihi',
                    'code' => 'SERVICE_QUANTITY_INVALID',
                ], 400);
            }
            $serviceQuantity = round((float) $serviceQuantity, 3);

            $sellingPrice = $request->input('selling_price');
            if (!is_numeric($sellingPrice) || (float) $sellingPrice < 0) {
                return $this->json([
                    'success' => false,
                    'error' => 'Bei ya huduma si sahihi (lazima iwe 0 au zaidi)',
                    'code' => 'SERVICE_PRICE_INVALID',
                ], 400);
            }
            $sellingPrice = round((float) $sellingPrice, 2);

            $consumables = $this->consumablesFromRequest($request, $service);

            if (isset($consumables['error'])) {
                return $this->json([
                    'success' => false,
                    'error' => $consumables['error'],
                    'code' => $consumables['code'] ?? 'INVALID_CONSUMABLES',
                    'invalidItems' => $consumables['invalid'] ?? [],
                ], 400);
            }

            $lines = $consumables['items'];

            if ($lines === []) {
                return $this->json([
                    'success' => false,
                    'error' => 'Huduma hii haina bidhaa zinazotumika',
                    'code' => 'SERVICE_CONSUMABLES_REQUIRED',
                ], 400);
            }

            // ---- Pre-flight (fast, friendly errors; RPC re-validates) ----
            $preflight = $this->preflight($businessId, $lines);

            if (isset($preflight['error'])) {
                $this->log($userId, 'SERVICE_SALE_FAILED', '/api/service-sales', [
                    'reason' => $preflight['code'],
                    'items' => $preflight['stockIssues'] ?? [],
                ], $this->ip($request), 'failed');

                return $this->json([
                    'success' => false,
                    'error' => $preflight['error'],
                    'code' => $preflight['code'],
                    'stockIssues' => $preflight['stockIssues'] ?? [],
                    'invalidItems' => $preflight['invalidItems'] ?? [],
                ], $preflight['status'] ?? 400);
            }

            $customerId = trim((string) $request->input('customer_id', ''));
            if ($customerId !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $customerId)) {
                $customerId = '';
            }

            $saleDate = trim((string) $request->input('sale_date', ''));
            if ($saleDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
                $saleDate = '';
            }

            $clientKey = substr(trim((string) $request->input('clientSaleKey', $request->input('client_key', ''))), 0, 80);

            $result = Supabase::callFunction('create_service_sale', [
                'p_seller_id' => $userId,
                'p_business_id' => $businessId,
                'p_service_id' => $service->id,
                'p_service_quantity' => $serviceQuantity,
                'p_selling_price' => $sellingPrice,
                'p_consumables' => array_values(array_map(fn ($line) => [
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                ], $lines)),
                'p_customer_id' => $customerId !== '' ? $customerId : null,
                'p_payment_method' => trim((string) $request->input('payment_method', 'cash')) ?: 'cash',
                'p_sale_date' => $saleDate !== '' ? $saleDate : null,
                'p_notes' => $this->nullableText($request->input('notes')),
                'p_client_key' => $clientKey !== '' ? $clientKey : null,
            ]);

            // PostgREST returns the function's jsonb object directly.
            if (isset($result[0]) && is_array($result[0]) && !isset($result['sale'])) {
                $result = $result[0];
            }

            $sale = is_array($result['sale'] ?? null) ? $result['sale'] : [];
            $sale['items'] = is_array($result['items'] ?? null) ? $result['items'] : [];
            $duplicate = (bool) ($result['duplicate'] ?? false);

            $this->log($userId, $duplicate ? 'SERVICE_SALE_DUPLICATE_SUPPRESSED' : 'SERVICE_SALE_CREATE', '/api/service-sales', [
                'service_sale_id' => $sale['id'] ?? null,
                'invoice_number' => $sale['invoice_number'] ?? null,
                'revenue' => $sale['selling_price'] ?? null,
                'cost' => $sale['total_cost'] ?? null,
                'profit' => $sale['profit'] ?? null,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'duplicate' => $duplicate,
                'message' => $duplicate
                    ? 'Mauzo ya huduma haya yameshahifadhiwa tayari.'
                    : 'Mauzo ya huduma yamekamilika kikamilifu!',
                'invoice_number' => $sale['invoice_number'] ?? null,
                'sale' => $sale,
            ], $duplicate ? 200 : 201);
        } catch (\Throwable $e) {
            return $this->rpcFailure($e, $request);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve the actual quantities to consume. When the client omits them,
     * the service's saved recipe is used (its non-zero default quantities).
     *
     * @return array{items?: list<array{product_id:string,quantity:float}>, error?: string, code?: string, invalid?: list<array>}
     */
    private function consumablesFromRequest(Request $request, $service): array
    {
        $input = $request->input('consumables');

        if (!is_array($input) || $input === []) {
            $links = ServiceConsumable::where('service_id', $service->id)->get()->toArray();
            $input = [];

            foreach ($links as $link) {
                $quantity = (float) ($link['required_quantity'] ?? 0);
                if ($quantity > 0) {
                    $input[] = [
                        'product_id' => $link['product_id'],
                        'quantity' => $quantity,
                    ];
                }
            }
        }

        $merged = [];
        $invalid = [];

        foreach ($input as $row) {
            if (!is_array($row)) {
                $invalid[] = ['reason' => 'Malformed consumable'];
                continue;
            }

            $productId = trim((string) ($row['product_id'] ?? ''));

            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
                $invalid[] = ['product_id' => $productId, 'reason' => 'Invalid product id'];
                continue;
            }

            $quantity = $row['quantity'] ?? $row['actual_quantity'] ?? null;

            if (!is_numeric($quantity) || (float) $quantity < 0) {
                $invalid[] = ['product_id' => $productId, 'reason' => 'Invalid quantity'];
                continue;
            }

            $quantity = (float) $quantity;

            // Zero means "not used this time" and is dropped rather than
            // written as a 0-cost line.
            if ($quantity === 0.0) {
                continue;
            }

            if ($quantity != floor($quantity)) {
                $invalid[] = [
                    'product_id' => $productId,
                    'reason' => 'Quantity must be a whole number',
                ];
                continue;
            }

            $merged[$productId] = ($merged[$productId] ?? 0) + $quantity;
        }

        if ($invalid !== []) {
            return [
                'error' => 'Taarifa za bidhaa zilizotumika si sahihi',
                'code' => 'INVALID_CONSUMABLES',
                'invalid' => $invalid,
            ];
        }

        $items = [];
        foreach ($merged as $productId => $quantity) {
            $items[] = ['product_id' => (string) $productId, 'quantity' => $quantity];
        }

        return ['items' => $items];
    }

    /**
     * Verify the products belong to this business and that stock covers the
     * request. The database function repeats all of this under row locks; this
     * pass exists so the seller sees precisely which product is short instead
     * of a generic failure.
     */
    private function preflight(string $businessId, array $lines): array
    {
        $memberIds = BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved');
        $allowed = array_flip($memberIds);

        $products = Product::whereIn('id', array_column($lines, 'product_id'))
            ->select(self::PRODUCT_COLUMNS)
            ->get()
            ->toArray();

        $byId = [];
        foreach ($products as $product) {
            $byId[(string) $product['id']] = $product;
        }

        $stockIssues = [];
        $invalidItems = [];

        foreach ($lines as $line) {
            $product = $byId[$line['product_id']] ?? null;

            if (!$product) {
                $invalidItems[] = ['product_id' => $line['product_id'], 'reason' => 'Product not found'];
                continue;
            }

            if (($product['is_active'] ?? true) === false
                || !isset($allowed[(string) ($product['seller_id'] ?? '')])) {
                $invalidItems[] = [
                    'product_id' => $line['product_id'],
                    'product_name' => $product['name'] ?? null,
                    'reason' => 'Product not in business',
                ];
                continue;
            }

            $available = (float) ($product['stock'] ?? 0);

            if ($available < $line['quantity']) {
                $stockIssues[] = [
                    'product_id' => $line['product_id'],
                    'product_name' => $product['name'] ?? null,
                    'available' => $available,
                    'requested' => $line['quantity'],
                    'reason' => 'Insufficient stock',
                ];
            }
        }

        if ($invalidItems !== []) {
            return [
                'error' => 'Bidhaa zilizotumika hazipo kwenye biashara yako',
                'code' => 'INVALID_CONSUMABLES',
                'invalidItems' => $invalidItems,
                'status' => 422,
            ];
        }

        if ($stockIssues !== []) {
            return [
                'error' => 'Bidhaa za kutosha hazipo kwenye stoo',
                'code' => 'STOCK_ISSUES',
                'stockIssues' => $stockIssues,
                'status' => 409,
            ];
        }

        return [];
    }

    private function rpcFailure(\Throwable $e, Request $request)
    {
        $raw = $e->getMessage();

        // PostgREST may wrap the SQL message in a JSON envelope.
        $decoded = json_decode($raw, true);
        $message = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : $raw;

        $this->log($this->userId($request), 'SERVICE_SALE_FAILED', '/api/service-sales', [
            'error' => $message,
        ], $this->ip($request), 'failed');

        if (str_starts_with($message, 'INSUFFICIENT_STOCK:')) {
            $parts = explode(':', $message, 4);

            return $this->json([
                'success' => false,
                'error' => 'Bidhaa za kutosha hazipo kwenye stoo',
                'code' => 'STOCK_ISSUES',
                'stockIssues' => [[
                    'product_id' => $parts[1] ?? null,
                    'available' => isset($parts[2]) ? (float) $parts[2] : null,
                    'requested' => isset($parts[3]) ? (float) $parts[3] : null,
                    'reason' => 'Insufficient stock',
                ]],
            ], 409);
        }

        $map = [
            'SERVICE_NOT_FOUND' => ['Huduma haipatikani kwenye biashara yako', 'SERVICE_NOT_FOUND', 404],
            'SERVICE_INACTIVE' => ['Huduma hii imezimwa', 'SERVICE_INACTIVE', 422],
            'SERVICE_QUANTITY_INVALID' => ['Idadi ya huduma si sahihi', 'SERVICE_QUANTITY_INVALID', 400],
            'SERVICE_PRICE_INVALID' => ['Bei ya huduma si sahihi', 'SERVICE_PRICE_INVALID', 400],
            'SERVICE_CONSUMABLES_REQUIRED' => ['Huduma hii haina bidhaa zinazotumika', 'SERVICE_CONSUMABLES_REQUIRED', 400],
            'SELLER_NOT_IN_BUSINESS' => ['Huna ruhusa kuuza huduma hii', 'FORBIDDEN', 403],
            'QUANTITY_NOT_INTEGER' => ['Kiasi cha bidhaa lazima kiwe namba kamili', 'INVALID_CONSUMABLES', 400],
        ];

        foreach ($map as $needle => [$error, $code, $status]) {
            if (str_starts_with($message, $needle)) {
                return $this->json([
                    'success' => false,
                    'error' => $error,
                    'code' => $code,
                ], $status);
            }
        }

        if (str_starts_with($message, 'PRODUCT_NOT_FOUND') || str_starts_with($message, 'PRODUCT_NOT_IN_BUSINESS')) {
            return $this->json([
                'success' => false,
                'error' => 'Bidhaa zilizotumika hazipo kwenye biashara yako',
                'code' => 'INVALID_CONSUMABLES',
            ], 422);
        }

        if (str_starts_with($message, 'CONSUMABLE_QUANTITY_INVALID')) {
            return $this->json([
                'success' => false,
                'error' => 'Kiasi cha bidhaa kilichotumika si sahihi',
                'code' => 'INVALID_CONSUMABLES',
            ], 400);
        }

        // The function is missing entirely: the SQL migration has not been run
        // yet. Say so plainly instead of pretending the sale failed randomly.
        if (str_contains($message, 'Could not find the function')
            || str_contains($message, 'PGRST202')
            || str_contains($message, 'does not exist')) {
            return $this->json([
                'success' => false,
                'error' => 'Mfumo wa mauzo ya huduma haujasanidiwa. Msimamizi wa mfumo anahitaji kuendesha migration ya huduma.',
                'code' => 'SERVICE_SALE_NOT_INSTALLED',
            ], 503);
        }

        return $this->json([
            'success' => false,
            'error' => 'Hitilafu isiyotarajiwa katika kurekodi mauzo ya huduma',
            'details' => $message,
            'code' => 'SERVICE_SALE_ERROR',
        ], 500);
    }

    /** Attach the consumed-product snapshot rows to a list of sales. */
    private function hydrate(array $sales): array
    {
        if ($sales === []) {
            return [];
        }

        $ids = array_values(array_filter(array_column($sales, 'id')));
        $itemsBySale = [];

        if ($ids) {
            foreach (ServiceSaleItem::whereIn('service_sale_id', $ids)->get() as $item) {
                $itemsBySale[(string) $item->service_sale_id][] = $item->toArray();
            }
        }

        foreach ($sales as &$sale) {
            $sale['items'] = $itemsBySale[(string) $sale['id']] ?? [];
            $sale['seller_name'] = $sale['seller_name'] ?? null;
        }
        unset($sale);

        return array_values($sales);
    }

    private function summary(array $sales): array
    {
        $revenue = 0.0;
        $cost = 0.0;
        $profit = 0.0;

        foreach ($sales as $sale) {
            $revenue += (float) ($sale['selling_price'] ?? 0);
            $cost += (float) ($sale['total_cost'] ?? 0);
            $profit += (float) ($sale['profit'] ?? 0);
        }

        return [
            'count' => count($sales),
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'profit' => round($profit, 2),
        ];
    }

    private function nullableText($value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, 1000);
    }
}
