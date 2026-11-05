<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceConsumable;
use App\Services\BusinessResolver;
use App\Services\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Huduma (services) management for a business administrator.
 *
 * A service is a sellable activity whose execution consumes existing inventory
 * products. The recipe lives in `service_consumables` — one relational row per
 * product — never a comma-separated/JSON column, so quantities can be edited
 * and a product can belong to many services.
 *
 * Tenant safety: every read and write is scoped by the caller's verified
 * business id (JWT). Product ids arriving from the client are checked against
 * the business's own catalogue before they are associated, which blocks IDOR on
 * another business's inventory.
 *
 * Reads are open to every approved member (sellers need the recipe to sell);
 * mutations are restricted to business admins.
 */
class ServiceController extends BaseController
{
    /** Columns a product exposes to the service screens. */
    private const PRODUCT_COLUMNS = [
        'id', 'name', 'unit', 'price', 'expected_selling_price', 'stock',
        'is_active', 'seller_id', 'category',
    ];

    /** GET /api/services */
    public function index(Request $request)
    {
        try {
            $businessId = $this->businessId($request);

            if (!$businessId) {
                return $this->json([]);
            }

            $query = Service::where('business_id', $businessId);

            // A seller only ever sells active services. An admin also sees the
            // paused ones so they can be reactivated.
            if (!$this->isAdmin($request)) {
                $query->where('status', 'active');
            }

            $services = $query->orderBy('name')->get()->toArray();

            $this->log($this->userId($request), 'SERVICES_VIEW', '/api/services', [
                'count' => count($services),
            ], $this->ip($request), 'success');

            return $this->json($this->hydrate($services));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'SERVICES_VIEW_ERROR', '/api/services', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->dbError($e);
        }
    }

    /** GET /api/services/{id} */
    public function show(Request $request, $id)
    {
        $service = $this->findService($request, $id);

        if (!$service) {
            return $this->json([
                'success' => false,
                'error' => 'Huduma haipatikani',
                'code' => 'SERVICE_NOT_FOUND',
            ], 404);
        }

        return $this->json($this->hydrate([$service->toArray()])[0]);
    }

    /** POST /api/services */
    public function store(Request $request)
    {
        if (!$this->isAdmin($request)) {
            return $this->forbidden();
        }

        $businessId = $this->businessId($request);

        if (!$businessId) {
            return $this->json([
                'success' => false,
                'error' => 'Biashara haijapatikana',
                'code' => 'NO_BUSINESS',
            ], 403);
        }

        $name = trim((string) $request->input('name', ''));

        if ($name === '' || mb_strlen($name) > 120) {
            return $this->json([
                'success' => false,
                'error' => 'Jina la huduma linahitajika (herufi 120 au chini)',
                'code' => 'INVALID_NAME',
            ], 400);
        }

        $duplicate = Service::where('business_id', $businessId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->select(['id'])
            ->first();

        if ($duplicate) {
            return $this->json([
                'success' => false,
                'error' => 'Huduma yenye jina hili ipo tayari',
                'code' => 'SERVICE_EXISTS',
            ], 409);
        }

        $parsed = $this->parseConsumables($request->input('consumables', $request->input('products', [])));

        if (isset($parsed['error'])) {
            return $this->json([
                'success' => false,
                'error' => $parsed['error'],
                'code' => 'INVALID_CONSUMABLES',
            ], 400);
        }

        $validated = $this->validateProducts($businessId, $parsed['items']);

        if (isset($validated['error'])) {
            return $this->json([
                'success' => false,
                'error' => $validated['error'],
                'invalidProducts' => $validated['invalid'] ?? [],
                'code' => 'INVALID_PRODUCTS',
            ], 400);
        }

        $now = $this->isoNow();

        try {
            $service = Service::create([
                'id' => Str::uuid()->toString(),
                'business_id' => $businessId,
                'name' => $name,
                'description' => $this->nullableText($request->input('description')),
                'status' => $this->status($request->input('status')),
                'created_by' => $this->userId($request),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'SERVICE_CREATE_FAILED', '/api/services', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Imeshindikana kuhifadhi huduma',
                'code' => 'SERVICE_SAVE_ERROR',
            ], 500);
        }

        if (!$service || !$service->id) {
            return $this->json([
                'success' => false,
                'error' => 'Imeshindikana kuhifadhi huduma',
                'code' => 'SERVICE_SAVE_ERROR',
            ], 500);
        }

        $this->writeConsumables($service->id, $parsed['items']);

        $fresh = $this->findService($request, $service->id);

        $this->log($this->userId($request), 'SERVICE_CREATE', '/api/services', [
            'service_id' => $service->id,
            'consumables' => count($parsed['items']),
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Huduma imeundwa kikamilifu',
            'service' => $fresh ? $this->hydrate([$fresh->toArray()])[0] : null,
        ], 201);
    }

    /** PUT /api/services/{id} */
    public function update(Request $request, $id)
    {
        if (!$this->isAdmin($request)) {
            return $this->forbidden();
        }

        $service = $this->findService($request, $id);

        if (!$service) {
            return $this->notFound();
        }

        $businessId = $this->businessId($request);
        $changes = [];

        if ($request->has('name')) {
            $name = trim((string) $request->input('name', ''));

            if ($name === '' || mb_strlen($name) > 120) {
                return $this->json([
                    'success' => false,
                    'error' => 'Jina la huduma linahitajika (herufi 120 au chini)',
                    'code' => 'INVALID_NAME',
                ], 400);
            }

            $duplicate = Service::where('business_id', $businessId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->select(['id'])
                ->first();

            if ($duplicate && $duplicate->id !== $service->id) {
                return $this->json([
                    'success' => false,
                    'error' => 'Huduma yenye jina hili ipo tayari',
                    'code' => 'SERVICE_EXISTS',
                ], 409);
            }

            $changes['name'] = $name;
        }

        if ($request->has('description')) {
            $changes['description'] = $this->nullableText($request->input('description'));
        }

        if ($request->has('status')) {
            $changes['status'] = $this->status($request->input('status'));
        }

        if ($changes !== []) {
            $changes['updated_at'] = $this->isoNow();

            try {
                Service::where('id', $service->id)
                    ->where('business_id', $businessId)
                    ->update($changes);
            } catch (\Throwable $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Imeshindikana kuhifadhi mabadiliko',
                    'code' => 'SERVICE_UPDATE_ERROR',
                ], 500);
            }
        }

        $fresh = $this->findService($request, $service->id);

        $this->log($this->userId($request), 'SERVICE_UPDATE', '/api/services/' . $service->id, [
            'fields' => array_keys($changes),
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Huduma imehaririwa',
            'service' => $fresh ? $this->hydrate([$fresh->toArray()])[0] : null,
        ]);
    }

    /** DELETE /api/services/{id} */
    public function destroy(Request $request, $id)
    {
        if (!$this->isAdmin($request)) {
            return $this->forbidden();
        }

        $service = $this->findService($request, $id);

        if (!$service) {
            return $this->notFound();
        }

        try {
            // The recipe rows cascade with the parent. Inventory products are
            // NOT touched, and completed sales keep their name/price snapshots
            // (service_sales.service_id becomes NULL).
            Service::where('id', $service->id)
                ->where('business_id', $this->businessId($request))
                ->delete();
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Imeshindikana kufuta huduma',
                'code' => 'SERVICE_DELETE_ERROR',
            ], 500);
        }

        $this->log($this->userId($request), 'SERVICE_DELETE', '/api/services/' . $service->id, [
            'name' => $service->name,
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Huduma imefutwa',
        ]);
    }

    /** POST /api/services/{id}/consumables */
    public function addConsumables(Request $request, $id)
    {
        if (!$this->isAdmin($request)) {
            return $this->forbidden();
        }

        $service = $this->findService($request, $id);

        if (!$service) {
            return $this->notFound();
        }

        $payload = $request->input('consumables', $request->input('products'));

        // Also accept a single product directly: {product_id, required_quantity}
        if (!is_array($payload) && $request->input('product_id')) {
            $payload = [[
                'product_id' => $request->input('product_id'),
                'required_quantity' => $request->input('required_quantity', 1),
            ]];
        }

        $parsed = $this->parseConsumables($payload);

        if (isset($parsed['error'])) {
            return $this->json([
                'success' => false,
                'error' => $parsed['error'],
                'code' => 'INVALID_CONSUMABLES',
            ], 400);
        }

        $validated = $this->validateProducts($this->businessId($request), $parsed['items']);

        if (isset($validated['error'])) {
            return $this->json([
                'success' => false,
                'error' => $validated['error'],
                'invalidProducts' => $validated['invalid'] ?? [],
                'code' => 'INVALID_PRODUCTS',
            ], 400);
        }

        $this->writeConsumables($service->id, $parsed['items'], true);

        $fresh = $this->findService($request, $service->id);

        $this->log($this->userId($request), 'SERVICE_CONSUMABLE_ADD', '/api/services/' . $service->id . '/consumables', [
            'products' => array_column($parsed['items'], 'product_id'),
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Bidhaa zimeongezwa kwenye huduma',
            'service' => $fresh ? $this->hydrate([$fresh->toArray()])[0] : null,
        ]);
    }

    /** PUT /api/services/{id}/consumables/{productId} */
    public function updateConsumable(Request $request, $id, $productId)
    {
        if (!$this->isAdmin($request)) {
            return $this->forbidden();
        }

        $service = $this->findService($request, $id);

        if (!$service) {
            return $this->notFound();
        }

        $quantity = $request->input('required_quantity', $request->input('quantity'));

        if (!is_numeric($quantity) || (float) $quantity < 0) {
            return $this->json([
                'success' => false,
                'error' => 'Kiasi kinachohitajika si sahihi',
                'code' => 'INVALID_QUANTITY',
            ], 400);
        }

        $link = ServiceConsumable::where('service_id', $service->id)
            ->where('product_id', trim((string) $productId))
            ->select(['id'])
            ->first();

        if (!$link) {
            return $this->json([
                'success' => false,
                'error' => 'Bidhaa hii haipo kwenye huduma',
                'code' => 'CONSUMABLE_NOT_FOUND',
            ], 404);
        }

        ServiceConsumable::where('id', $link->id)->update([
            'required_quantity' => round((float) $quantity, 3),
            'updated_at' => $this->isoNow(),
        ]);

        $fresh = $this->findService($request, $service->id);

        $this->log($this->userId($request), 'SERVICE_CONSUMABLE_UPDATE', '/api/services/' . $service->id . '/consumables/' . $productId, [
            'required_quantity' => $quantity,
        ], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Kiasi kimehifadhiwa',
            'service' => $fresh ? $this->hydrate([$fresh->toArray()])[0] : null,
        ]);
    }

    /** DELETE /api/services/{id}/consumables/{productId} */
    public function removeConsumable(Request $request, $id, $productId)
    {
        if (!$this->isAdmin($request)) {
            return $this->forbidden();
        }

        $service = $this->findService($request, $id);

        if (!$service) {
            return $this->notFound();
        }

        $link = ServiceConsumable::where('service_id', $service->id)
            ->where('product_id', trim((string) $productId))
            ->select(['id'])
            ->first();

        if (!$link) {
            return $this->json([
                'success' => false,
                'error' => 'Bidhaa hii haipo kwenye huduma',
                'code' => 'CONSUMABLE_NOT_FOUND',
            ], 404);
        }

        ServiceConsumable::where('id', $link->id)->delete();

        $fresh = $this->findService($request, $service->id);

        $this->log($this->userId($request), 'SERVICE_CONSUMABLE_REMOVE', '/api/services/' . $service->id . '/consumables/' . $productId, [], $this->ip($request), 'success');

        return $this->json([
            'success' => true,
            'message' => 'Bidhaa imeondolewa kwenye huduma. Bidhaa yenyewe haijafutwa kwenye stoo.',
            'service' => $fresh ? $this->hydrate([$fresh->toArray()])[0] : null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function findService(Request $request, $id)
    {
        $businessId = $this->businessId($request);
        $id = trim((string) $id);

        if (!$businessId || $id === '') {
            return null;
        }

        return Service::where('id', $id)->where('business_id', $businessId)->first();
    }

    /**
     * Attach recipes, product names/prices/stock and the default cost to a set
     * of services. All lookups are batched: one query for recipes, one for
     * products, regardless of how many services were asked for.
     */
    private function hydrate(array $services): array
    {
        if ($services === []) {
            return [];
        }

        $serviceIds = array_values(array_filter(array_column($services, 'id')));
        $links = $serviceIds
            ? ServiceConsumable::whereIn('service_id', $serviceIds)->get()->toArray()
            : [];

        $productIds = array_values(array_unique(array_filter(array_column($links, 'product_id'))));
        $productsById = [];

        if ($productIds) {
            foreach (Product::whereIn('id', $productIds)->select(self::PRODUCT_COLUMNS)->get() as $product) {
                $productsById[$product->id] = $product->toArray();
            }
        }

        $linksByService = [];
        foreach ($links as $link) {
            $linksByService[$link['service_id']][] = $link;
        }

        foreach ($services as &$service) {
            $consumables = [];
            $cost = 0.0;

            foreach ($linksByService[$service['id']] ?? [] as $link) {
                $product = $productsById[$link['product_id']] ?? null;
                $quantity = (float) ($link['required_quantity'] ?? 0);
                $buyingPrice = $product !== null ? (float) ($product['price'] ?? 0) : 0.0;

                $consumables[] = [
                    'id' => $link['id'],
                    'product_id' => $link['product_id'],
                    'product_name' => $product['name'] ?? null,
                    'required_quantity' => $quantity,
                    'buying_price' => $product['price'] ?? null,
                    'selling_price' => $product['expected_selling_price'] ?? null,
                    'stock' => $product['stock'] ?? 0,
                    'unit' => $product['unit'] ?? null,
                    'category' => $product['category'] ?? null,
                    'is_active' => $product['is_active'] ?? true,
                    'line_cost' => round($quantity * $buyingPrice, 2),
                ];

                $cost += $quantity * $buyingPrice;
            }

            $service['consumables'] = $consumables;
            $service['consumables_count'] = count($consumables);
            $service['estimated_cost'] = round($cost, 2);
        }
        unset($service);

        return array_values($services);
    }

    /**
     * @return array{items?: list<array{product_id:string,required_quantity:float}>, error?: string}
     */
    private function parseConsumables($input): array
    {
        if (!is_array($input) || $input === []) {
            return ['items' => []];
        }

        $items = [];

        foreach ($input as $row) {
            if (!is_array($row)) {
                return ['error' => 'Orodha ya bidhaa si sahihi'];
            }

            $productId = trim((string) ($row['product_id'] ?? ''));

            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $productId)) {
                return ['error' => 'Bidhaa iliyochaguliwa si sahihi'];
            }

            $quantity = $row['required_quantity'] ?? $row['quantity'] ?? 1;

            if (!is_numeric($quantity) || (float) $quantity < 0 || (float) $quantity > 1000000) {
                return ['error' => 'Kiasi kinachohitajika si sahihi (kianze 0)'];
            }

            // The same product may not appear twice in one service. The last
            // value wins, which matches editing a row rather than failing.
            $items[$productId] = [
                'product_id' => $productId,
                'required_quantity' => round((float) $quantity, 3),
            ];
        }

        return ['items' => array_values($items)];
    }

    /**
     * Every product id must be active and owned by a member of THIS business.
     *
     * @return array{pool?: array<string,array>, error?: string, invalid?: list<string>}
     */
    private function validateProducts(string $businessId, array $items): array
    {
        if ($items === []) {
            return ['pool' => []];
        }

        $memberIds = BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved');

        if ($memberIds === []) {
            return ['error' => 'Hakuna wanabiashara waliothibitishwa', 'invalid' => []];
        }

        $products = Product::whereIn('id', array_column($items, 'product_id'))
            ->select(self::PRODUCT_COLUMNS)
            ->get()
            ->toArray();

        $allowedMembers = array_flip($memberIds);
        $pool = [];
        $invalid = [];

        foreach ($products as $product) {
            if (($product['is_active'] ?? true) === false
                || !isset($allowedMembers[(string) ($product['seller_id'] ?? '')])) {
                $invalid[] = (string) $product['id'];
                continue;
            }

            $pool[(string) $product['id']] = $product;
        }

        $known = array_flip(array_keys($pool));
        foreach ($items as $item) {
            if (!isset($known[$item['product_id']])) {
                $invalid[] = $item['product_id'];
            }
        }

        $invalid = array_values(array_unique($invalid));

        if ($invalid !== []) {
            return [
                'error' => 'Bidhaa zilizochaguliwa hazipo kwenye biashara yako',
                'invalid' => $invalid,
            ];
        }

        return ['pool' => $pool];
    }

    /**
     * Insert or update the recipe rows. `$merge` preserves an existing
     * required_quantity when the same product is added again, so re-adding a
     * product never silently resets a quantity the admin tuned.
     */
    private function writeConsumables(string $serviceId, array $items, bool $merge = false): void
    {
        if ($items === []) {
            return;
        }

        $existing = [];

        if ($merge) {
            foreach (ServiceConsumable::where('service_id', $serviceId)->select(['id', 'product_id'])->get() as $row) {
                $existing[(string) $row->product_id] = (string) $row->id;
            }
        }

        $now = $this->isoNow();
        $inserts = [];

        foreach ($items as $item) {
            if (isset($existing[$item['product_id']])) {
                ServiceConsumable::where('id', $existing[$item['product_id']])->update([
                    'required_quantity' => $item['required_quantity'],
                    'updated_at' => $now,
                ]);
                continue;
            }

            $inserts[] = [
                'id' => Str::uuid()->toString(),
                'service_id' => $serviceId,
                'product_id' => $item['product_id'],
                'required_quantity' => $item['required_quantity'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($inserts !== []) {
            try {
                ServiceConsumable::insert($inserts);
            } catch (\Throwable $e) {
                // A duplicate slipped through (concurrent add): fall back to
                // updating the existing rows individually rather than failing.
                foreach ($inserts as $row) {
                    $link = ServiceConsumable::where('service_id', $serviceId)
                        ->where('product_id', $row['product_id'])
                        ->select(['id'])
                        ->first();

                    if ($link) {
                        ServiceConsumable::where('id', $link->id)->update([
                            'required_quantity' => $row['required_quantity'],
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    private function status($value): string
    {
        return $value === 'inactive' ? 'inactive' : 'active';
    }

    private function nullableText($value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }

    private function forbidden()
    {
        return $this->json([
            'success' => false,
            'error' => 'Huna ruhusa kusimamia huduma',
            'code' => 'FORBIDDEN',
        ], 403);
    }

    private function notFound()
    {
        return $this->json([
            'success' => false,
            'error' => 'Huduma haipatikani',
            'code' => 'SERVICE_NOT_FOUND',
        ], 404);
    }
}
