<?php

namespace App\Http\Controllers\Api;

use App\Models\Fridge;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ScannerReceivingSession;
use App\Services\BusinessResolver;
use App\Services\QrScanner;
use App\Services\Roles;
use Illuminate\Http\Request;

/**
 * One API surface for every QR workflow. The controller only authorises the
 * request and shapes the answer: all state transitions happen inside the
 * PostgreSQL functions wrapped by App\Services\QrScanner, which lock the item
 * row and re-check business, branch, product, status and location.
 *
 *   Administrator receiving : start -> scan* -> complete | cancel
 *   Seller selling          : sale/scan* (claims the item) -> POST /api/sales
 *   Fridge transfer         : fridge/in*  |  fridge/out*
 *   Order page scanning     : order/scan (identifies the product, no mutation)
 */
class ScannerController extends BaseController
{
    /* ------------------------------------------------------------------ */
    /* Administrator: receiving new stock                                  */
    /* ------------------------------------------------------------------ */

    /** POST /api/scanner/receiving/start  { product_id } */
    public function startReceiving(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }
        if (! $this->isAdmin($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Muuzaji hawezi kupokea stock mpya. Msimamizi pekee anaruhusiwa.',
                'code' => 'STOCK_RECEIVING_FORBIDDEN',
            ], 403);
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);
        if (! $businessId || ! $userId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        [$branchId, $branchError] = $this->requireWriteBranch($request);
        if ($branchError) {
            return $branchError;
        }

        $product = $this->findProduct($request, (string) $request->input('product_id', ''));
        if (! $product) {
            return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani', 'code' => 'PRODUCT_NOT_FOUND'], 404);
        }
        if ($branchId !== null && (string) ($product['branch_id'] ?? '') !== (string) $branchId) {
            return $this->json(['success' => false, 'error' => 'Bidhaa ni ya tawi lingine', 'code' => 'BRANCH_MISMATCH'], 422);
        }

        try {
            $result = QrScanner::openSession($businessId, $branchId, (string) $product['id'], $userId);
        } catch (\Throwable $e) {
            return $this->scannerError($e, 'Imeshindikana kuanzisha kipindi cha kupokea stock');
        }

        $session = $result['session'] ?? [];

        $this->log($userId, 'SCANNER_RECEIVING_START', '/api/scanner/receiving/start', [
            'product_id' => $product['id'],
            'session_id' => $session['id'] ?? null,
        ], $this->ip($request));

        return $this->json([
            'success' => true,
            'session' => $session,
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'] ?? null,
                'stock' => (int) ($product['stock'] ?? 0),
            ],
            'resumed' => (bool) ($result['resumed'] ?? false),
        ], 201);
    }

    /** POST /api/scanner/receiving/scan  { session_id, qr_token } */
    public function scanReceiving(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }
        if (! $this->isAdmin($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Muuzaji hawezi kupokea stock mpya.',
                'code' => 'STOCK_RECEIVING_FORBIDDEN',
            ], 403);
        }

        return $this->withReceivingSession($request, function (array $session) use ($request) {
            $token = QrScanner::normalizeToken($request->input('qr_token') ?? $request->input('token'));
            if ($token === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'QR code si sahihi. Skani tena.',
                    'code' => 'QR_INVALID',
                ], 422);
            }

            try {
                $result = QrScanner::receiveItem(
                    (string) $session['id'],
                    $token,
                    (string) $session['business_id'],
                    $session['branch_id'] ?? null,
                    (string) $session['product_id'],
                    (string) $this->userId($request)
                );
            } catch (\Throwable $e) {
                return $this->scannerError($e, 'Kipande hakikusajiliwa');
            }

            $this->log($this->userId($request), 'SCANNER_ITEM_RECEIVED', '/api/scanner/receiving/scan', [
                'session_id' => $session['id'],
                'product_id' => $session['product_id'],
                'item_id' => $result['item']['id'] ?? null,
                'scanned_count' => $result['scanned_count'] ?? null,
            ], $this->ip($request));

            return $this->json([
                'success' => true,
                'message' => 'Kipande kimeskaniwa. Kiasi kitakamilika ukipress Maliza.',
                'item' => $result['item'] ?? null,
                'scanned_count' => (int) ($result['scanned_count'] ?? 0),
            ], 201);
        });
    }

    /** POST /api/scanner/receiving/complete  { session_id } */
    public function completeReceiving(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }
        if (! $this->isAdmin($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Muuzaji hawezi kupokea stock mpya.',
                'code' => 'STOCK_RECEIVING_FORBIDDEN',
            ], 403);
        }

        return $this->withReceivingSession($request, function (array $session) use ($request) {
            try {
                $result = QrScanner::completeSession(
                    (string) $session['id'],
                    (string) $session['business_id'],
                    $session['branch_id'] ?? null,
                    (string) $this->userId($request)
                );
            } catch (\Throwable $e) {
                return $this->scannerError($e, 'Stock haikupokelewa');
            }

            $this->log($this->userId($request), 'SCANNER_RECEIVING_COMPLETE', '/api/scanner/receiving/complete', [
                'session_id' => $session['id'],
                'product_id' => $session['product_id'],
                'quantity' => $result['quantity'] ?? null,
                'reference' => $result['receiving_reference'] ?? null,
            ], $this->ip($request));

            return $this->json([
                'success' => true,
                'message' => 'Stock imepokewa kikamilifu.',
                'session_id' => $session['id'],
                'product_id' => $session['product_id'],
                'quantity' => (int) ($result['quantity'] ?? 0),
                'stock_before' => $result['stock_before'] ?? null,
                'stock_after' => $result['stock_after'] ?? null,
                'receiving_reference' => $result['receiving_reference'] ?? null,
            ]);
        });
    }

    /** POST /api/scanner/receiving/cancel  { session_id } */
    public function cancelReceiving(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }
        if (! $this->isAdmin($request)) {
            return $this->json([
                'success' => false,
                'error' => 'Muuzaji hawezi kughairi upokeaji wa stock.',
                'code' => 'STOCK_RECEIVING_FORBIDDEN',
            ], 403);
        }

        return $this->withReceivingSession($request, function (array $session) use ($request) {
            try {
                $result = QrScanner::cancelSession(
                    (string) $session['id'],
                    (string) $session['business_id'],
                    (string) $this->userId($request)
                );
            } catch (\Throwable $e) {
                return $this->scannerError($e, 'Kipindi hakikughairiwa');
            }

            $this->log($this->userId($request), 'SCANNER_RECEIVING_CANCEL', '/api/scanner/receiving/cancel', [
                'session_id' => $session['id'],
                'removed' => $result['removed'] ?? 0,
            ], $this->ip($request));

            return $this->json([
                'success' => true,
                'message' => 'Kipindi kimeghairiwa. Hakuna stock iliyoongezwa.',
                'removed' => (int) ($result['removed'] ?? 0),
            ]);
        });
    }

    /** GET /api/scanner/receiving/{sessionId} */
    public function showReceiving(Request $request, $sessionId)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa', 'code' => 'FORBIDDEN'], 403);
        }

        return $this->withReceivingSession($request, function (array $session) use ($request) {
            $items = ProductItem::where('receiving_session_id', $session['id'])
                ->select(array_merge(ProductItem::PUBLIC_COLUMNS, ['qr_token']))
                ->orderBy('created_at')
                ->get()
                ->toArray();

            $product = $this->findProduct($request, (string) $session['product_id']);

            return $this->json([
                'success' => true,
                'session' => $session,
                'product' => $product ? ['id' => $product['id'], 'name' => $product['name'] ?? null, 'stock' => (int) ($product['stock'] ?? 0)] : null,
                'items' => $items,
                'scanned_count' => count($items),
            ]);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Seller: scanning items into a sale cart                             */
    /* ------------------------------------------------------------------ */

    /** POST /api/scanner/sale/scan  { product_id, qr_token, client_ref } */
    public function scanSale(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);
        if (! $businessId || ! $userId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        [$branchId, $branchError] = $this->requireWriteBranch($request);
        if ($branchError) {
            return $branchError;
        }

        $clientRef = $this->clientRef($request);
        if ($clientRef === null) {
            return $this->json([
                'success' => false,
                'error' => 'Kumbukumbu ya mauzo inahitajika kabla ya kuskani.',
                'code' => 'CLIENT_REF_REQUIRED',
            ], 422);
        }

        $product = $this->findProduct($request, (string) $request->input('product_id', ''));
        if (! $product) {
            return $this->json(['success' => false, 'error' => 'Chagua bidhaa kabla ya kuskani.', 'code' => 'PRODUCT_NOT_FOUND'], 404);
        }

        $token = QrScanner::normalizeToken($request->input('qr_token') ?? $request->input('token'));
        if ($token === null) {
            return $this->json(['success' => false, 'error' => 'QR code si sahihi. Skani tena.', 'code' => 'QR_INVALID'], 422);
        }

        try {
            $result = QrScanner::reserveItem($token, (string) $product['id'], $businessId, $branchId, $userId, $clientRef);
        } catch (\Throwable $e) {
            return $this->scannerError($e, 'Kipande hakikuongezwa kwenye mauzo');
        }

        $item = QrScanner::findItem($token, $businessId);

        $this->log($userId, 'SCANNER_SALE_ITEM', '/api/scanner/sale/scan', [
            'product_id' => $product['id'],
            'item_id' => $result['item_id'] ?? null,
            'client_ref' => $clientRef,
        ], $this->ip($request));

        return $this->json([
            'success' => true,
            'message' => 'Kipande kimeongezwa: ' . ($product['name'] ?? ''),
            'item' => [
                'item_id' => $result['item_id'] ?? null,
                'product_id' => $product['id'],
                'product_name' => $product['name'] ?? null,
                'qr_token' => $item['qr_token'] ?? $token,
                'location' => $result['location'] ?? null,
                'fridge_id' => $result['fridge_id'] ?? null,
                'fridge_name' => $this->fridgeName($businessId, $result['fridge_id'] ?? null),
            ],
            'reservation_expires_at' => $result['expires_at'] ?? null,
        ], 201);
    }

    /** POST /api/scanner/sale/release  { item_ids: [], client_ref } */
    public function releaseSale(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);
        if (! $businessId || ! $userId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        $itemIds = $this->uuidList($request->input('item_ids'));
        if ($itemIds === []) {
            return $this->json(['success' => true, 'released' => 0]);
        }

        try {
            $result = QrScanner::releaseItems($itemIds, $businessId, $userId, $this->clientRef($request) ?? '');
        } catch (\Throwable $e) {
            return $this->scannerError($e, 'Vipande havikuachiliwa');
        }

        return $this->json(['success' => true, 'released' => (int) ($result['released'] ?? 0)]);
    }

    /* ------------------------------------------------------------------ */
    /* Seller: fridge / freezer transfers                                  */
    /* ------------------------------------------------------------------ */

    /** POST /api/scanner/fridge/in  { fridge_id, product_id, qr_token } */
    public function scanFridgeIn(Request $request)
    {
        return $this->scanFridge($request, 'in');
    }

    /** POST /api/scanner/fridge/out  { fridge_id, product_id, qr_token } */
    public function scanFridgeOut(Request $request)
    {
        return $this->scanFridge($request, 'out');
    }

    /* ------------------------------------------------------------------ */
    /* Order page: scan first, identify the product automatically          */
    /* ------------------------------------------------------------------ */

    /**
     * POST /api/scanner/order/scan  { qr_token, cart_item_ids?: [] }
     *
     * Identifies the product from the physical item, so no product selection
     * is needed. It NEVER changes stock or the item: existing orders do not
     * reserve inventory (stock is deducted by the sale/fulfilment path), so
     * this endpoint only validates that the unit is genuinely available.
     */
    public function scanOrder(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }
        if ($this->userId($request) === null) {
            return $this->json(['success' => false, 'error' => 'Ingia kwanza', 'code' => 'UNAUTHENTICATED'], 401);
        }

        $token = QrScanner::normalizeToken($request->input('qr_token') ?? $request->input('token'));
        if ($token === null) {
            return $this->json(['success' => false, 'error' => 'QR code si sahihi. Skani tena.', 'code' => 'QR_INVALID'], 422);
        }

        $item = QrScanner::findItem($token, $businessId);
        if (! $item) {
            return $this->json([
                'success' => false,
                'error' => 'QR hii haijasajiliwa kwenye biashara yako.',
                'code' => 'QR_UNKNOWN',
            ], 404);
        }

        $product = $this->findProduct($request, (string) $item['product_id']);
        if (! $product) {
            return $this->json([
                'success' => false,
                'error' => 'Bidhaa ya kipande hiki haipatikani kwenye tawi lako.',
                'code' => 'PRODUCT_UNAVAILABLE',
            ], 404);
        }

        if (($item['status'] ?? '') === ProductItem::STATUS_SOLD) {
            return $this->json(['success' => false, 'error' => 'Kipande hiki kimeshauzwa.', 'code' => 'ITEM_SOLD'], 409);
        }
        if (($item['status'] ?? '') === ProductItem::STATUS_PENDING) {
            return $this->json(['success' => false, 'error' => 'Kipande hiki hakijakamilika kupokelewa.', 'code' => 'ITEM_PENDING'], 409);
        }
        if (($item['status'] ?? '') === ProductItem::STATUS_VOID) {
            return $this->json(['success' => false, 'error' => 'Kipande hiki kimeondolewa kwenye mfumo.', 'code' => 'ITEM_VOID'], 409);
        }
        if (($item['status'] ?? '') === ProductItem::STATUS_RESERVED) {
            return $this->json([
                'success' => false,
                'error' => 'Kipande hiki kimehifadhiwa kwenye mauzo yanayoendelea.',
                'code' => 'ITEM_RESERVED',
            ], 409);
        }

        $cartIds = $this->uuidList($request->input('cart_item_ids'));
        if (in_array((string) $item['id'], $cartIds, true)) {
            return $this->json([
                'success' => false,
                'error' => 'Kipande hiki kiko tayari kwenye oda hii.',
                'code' => 'ITEM_DUPLICATE',
            ], 409);
        }

        $this->log($this->userId($request), 'SCANNER_ORDER_ITEM', '/api/scanner/order/scan', [
            'item_id' => $item['id'],
            'product_id' => $product['id'],
        ], $this->ip($request));

        return $this->json([
            'success' => true,
            'message' => 'Bidhaa imetambulika: ' . ($product['name'] ?? ''),
            'product' => [
                'id' => $product['id'],
                'name' => $product['name'] ?? null,
                'category' => $product['category'] ?? null,
                'price' => $product['price'] ?? null,
                'expected_selling_price' => $product['expected_selling_price'] ?? null,
                'stock' => (int) ($product['stock'] ?? 0),
                'image_url' => $product['image_url'] ?? null,
            ],
            'item' => [
                'item_id' => $item['id'],
                'qr_token' => $item['qr_token'] ?? $token,
                'location' => $item['location'] ?? null,
                'fridge_id' => $item['fridge_id'] ?? null,
                'fridge_name' => $this->fridgeName($businessId, $item['fridge_id'] ?? null),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Verification / preparation reads                                    */
    /* ------------------------------------------------------------------ */

    /** GET /api/scanner/identify?qr_token=... - product + item lookup only. */
    public function identify(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        $token = QrScanner::normalizeToken($request->query('qr_token') ?? $request->query('token') ?? $request->query('barcode'));
        if ($token === null) {
            return $this->json(['success' => false, 'error' => 'QR code inahitajika', 'code' => 'QR_REQUIRED'], 422);
        }

        $item = QrScanner::findItem($token, $businessId);
        if (! $item) {
            return $this->json(['success' => false, 'error' => 'QR hii haijasajiliwa kwenye biashara yako.', 'code' => 'QR_UNKNOWN'], 404);
        }

        $product = $this->findProduct($request, (string) $item['product_id']);

        return $this->json([
            'success' => true,
            'item' => array_merge($item, [
                'fridge_name' => $this->fridgeName($businessId, $item['fridge_id'] ?? null),
            ]),
            'product' => $product ? [
                'id' => $product['id'],
                'name' => $product['name'] ?? null,
                'stock' => (int) ($product['stock'] ?? 0),
            ] : null,
        ]);
    }

    /**
     * GET /api/scanner/items?product_id=...&status=in_stock
     *
     * The exact physical units of a product and where each one is. Used for
     * sale preparation and stock verification: the backend's recorded location
     * - never a guess - decides whether staff look outside or in a fridge.
     */
    public function items(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        $productId = trim((string) $request->query('product_id', ''));
        if ($productId === '') {
            return $this->json(['success' => false, 'error' => 'Bidhaa inahitajika', 'code' => 'PRODUCT_REQUIRED'], 422);
        }

        $product = $this->findProduct($request, $productId);
        if (! $product) {
            return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani', 'code' => 'PRODUCT_NOT_FOUND'], 404);
        }

        $status = trim((string) $request->query('status', ''));
        $status = in_array($status, [
            ProductItem::STATUS_PENDING,
            ProductItem::STATUS_IN_STOCK,
            ProductItem::STATUS_RESERVED,
            ProductItem::STATUS_SOLD,
        ], true) ? $status : null;

        $items = QrScanner::itemsForProduct(
            $productId,
            $businessId,
            $this->branchId($request),
            $status,
            (int) $request->query('limit', 100)
        );

        $names = $this->fridgeNames($businessId);

        return $this->json([
            'success' => true,
            'product' => ['id' => $product['id'], 'name' => $product['name'] ?? null],
            'items' => array_map(function ($item) use ($names) {
                $fridgeId = (string) ($item['fridge_id'] ?? '');

                return array_merge($item, [
                    'fridge_name' => $fridgeId !== '' ? ($names[$fridgeId] ?? null) : null,
                ]);
            }, $items),
        ]);
    }

    /**
     * GET /api/scanner/verify
     *
     * Reconciles individually tracked units with the business stock ledger.
     * Read-only. A product without tracked units simply reports zero counts -
     * products that are not individually tracked keep working unchanged.
     */
    public function verify(Request $request)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        try {
            $rows = QrScanner::stockSummary($businessId);
        } catch (\Throwable $e) {
            return $this->scannerError($e, 'Imeshindikana kuthibitisha stock');
        }

        $products = [];
        $mismatches = 0;
        foreach ($rows as $row) {
            $businessStock = (float) ($row['business_stock'] ?? 0);
            $outside = (int) ($row['outside_items'] ?? 0);
            $fridge = (int) ($row['fridge_items'] ?? 0);
            $tracked = $outside + $fridge;

            // Only products with tracked units are comparable: an untracked
            // product legitimately has stock and zero item rows.
            $consistent = $tracked === 0 || $tracked === (int) $businessStock;
            if (! $consistent) {
                $mismatches++;
            }

            $products[] = [
                'product_id' => $row['product_id'] ?? null,
                'product_name' => $row['product_name'] ?? null,
                'business_stock' => $businessStock,
                'outside_items' => $outside,
                'fridge_items' => $fridge,
                'tracked_items' => $tracked,
                'sold_items' => (int) ($row['sold_items'] ?? 0),
                'consistent' => $consistent,
            ];
        }

        return $this->json([
            'success' => true,
            'products' => $products,
            'mismatch_count' => $mismatches,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function scanFridge(Request $request, string $direction)
    {
        if ($deny = $this->requireFeature($request, 'scanner')) {
            return $deny;
        }
        if ($deny = $this->requireFeature($request, 'fridge')) {
            return $deny;
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);
        if (! $businessId || ! $userId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        [$branchId, $branchError] = $this->requireWriteBranch($request);
        if ($branchError) {
            return $branchError;
        }

        // Sellers may move existing stock; the fridge RPC re-checks scope.
        if (! in_array($this->role($request), Roles::BUSINESS_MEMBERS, true)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kuhamisha stock.', 'code' => 'FORBIDDEN'], 403);
        }

        $fridgeId = trim((string) $request->input('fridge_id', ''));
        $fridge = $fridgeId !== '' ? $this->findFridge($request, $fridgeId) : null;
        if (! $fridge) {
            return $this->json(['success' => false, 'error' => 'Friji haipatikani', 'code' => 'FRIDGE_NOT_FOUND'], 404);
        }

        $product = $this->findProduct($request, (string) $request->input('product_id', ''));
        if (! $product) {
            return $this->json(['success' => false, 'error' => 'Bidhaa haipatikani', 'code' => 'PRODUCT_NOT_FOUND'], 404);
        }

        $token = QrScanner::normalizeToken($request->input('qr_token') ?? $request->input('token'));
        if ($token === null) {
            return $this->json(['success' => false, 'error' => 'QR code si sahihi. Skani tena.', 'code' => 'QR_INVALID'], 422);
        }

        try {
            $result = $direction === 'in'
                ? QrScanner::moveToFridge($token, $fridgeId, (string) $product['id'], $businessId, $branchId, $userId)
                : QrScanner::moveOutOfFridge($token, $fridgeId, (string) $product['id'], $businessId, $branchId, $userId);
        } catch (\Throwable $e) {
            return $this->scannerError($e, 'Kipande hakikuhamishwa');
        }

        $this->log($userId, $direction === 'in' ? 'SCANNER_FRIDGE_IN' : 'SCANNER_FRIDGE_OUT', '/api/scanner/fridge/' . $direction, [
            'fridge_id' => $fridgeId,
            'product_id' => $product['id'],
            'item_id' => $result['item_id'] ?? null,
        ], $this->ip($request));

        return $this->json([
            'success' => true,
            'message' => $direction === 'in'
                ? 'Kipande kimeingizwa kwenye ' . ($fridge['name'] ?? 'friji')
                : 'Kipande kimetolewa kutoka ' . ($fridge['name'] ?? 'friji'),
            'result' => $result,
        ], 201);
    }

    /**
     * Load the session and prove it belongs to the caller's business/branch.
     * The database functions re-check, but refusing here keeps error messages
     * precise and avoids a pointless round trip.
     */
    private function withReceivingSession(Request $request, callable $callback)
    {
        $businessId = $this->businessId($request);
        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijulikani', 'code' => 'BUSINESS_REQUIRED'], 400);
        }

        $sessionId = trim((string) ($request->input('session_id') ?: $request->route('sessionId')));
        if ($sessionId === '') {
            return $this->json(['success' => false, 'error' => 'Kipindi kinahitajika', 'code' => 'SESSION_REQUIRED'], 422);
        }

        $row = ScannerReceivingSession::where('id', $sessionId)
            ->where('business_id', $businessId)
            ->first();

        if (! $row) {
            return $this->json(['success' => false, 'error' => 'Kipindi hakipatikani', 'code' => 'SESSION_NOT_FOUND'], 404);
        }

        $session = $row->toArray();

        // Branch isolation: a session belongs to exactly one branch (or none).
        if ($this->branchId($request) !== null
            && (string) ($session['branch_id'] ?? '') !== (string) $this->branchId($request)) {
            return $this->json(['success' => false, 'error' => 'Kipindi ni cha tawi lingine', 'code' => 'BRANCH_MISMATCH'], 403);
        }

        return $callback($session);
    }

    /** An active product of the caller's business (and branch selection). */
    private function findProduct(Request $request, string $productId): ?array
    {
        if ($productId === '') {
            return null;
        }

        $businessId = $this->businessId($request);
        $sellerIds = $businessId
            ? BusinessResolver::memberIds($businessId, Roles::BUSINESS_MEMBERS, 'approved')
            : [];
        if ($sellerIds === []) {
            return null;
        }

        $query = Product::where('id', $productId)
            ->whereIn('seller_id', $sellerIds)
            ->where('is_active', true);
        $query = $this->scopeBranch($query, $request);
        $row = $query->first();

        return $row ? $row->toArray() : null;
    }

    private function findFridge(Request $request, string $fridgeId): ?array
    {
        $businessId = $this->businessId($request);
        if (! $businessId) {
            return null;
        }

        $query = Fridge::where('id', $fridgeId)->where('business_id', $businessId)->where('status', 'active');
        $query = $this->scopeBranch($query, $request);
        $row = $query->first();

        return $row ? $row->toArray() : null;
    }

    private function fridgeName(string $businessId, ?string $fridgeId): ?string
    {
        if ($fridgeId === null || $fridgeId === '') {
            return null;
        }

        try {
            $row = Fridge::where('id', $fridgeId)->where('business_id', $businessId)->first();
        } catch (\Throwable $e) {
            return null;
        }

        return $row?->name;
    }

    /** @return array<string,string> */
    private function fridgeNames(string $businessId): array
    {
        $map = [];
        try {
            foreach (Fridge::where('business_id', $businessId)->get()->toArray() as $row) {
                $map[(string) ($row['id'] ?? '')] = (string) ($row['name'] ?? '');
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $map;
    }

    /**
     * Idempotency key for one cart. Generated by the client, limited in size,
     * and never trusted for authorisation - only for grouping claims.
     */
    private function clientRef(Request $request): ?string
    {
        $raw = $request->input('client_ref') ?? $request->input('clientSaleKey') ?? $request->input('clientRef');
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $ref = substr(trim((string) $raw), 0, 80);

        return $ref === '' ? null : $ref;
    }

    /** @return array<int,string> */
    private function uuidList($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $value) {
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    /** Uniform error shape for every scanner endpoint. */
    private function scannerError(\Throwable $e, string $fallback)
    {
        $message = $e->getMessage();
        $current = request();

        $this->log($this->userId($current), 'SCANNER_OPERATION_FAILED', $current->path(), [
            'error' => $message,
        ], $this->ip($current), 'failed');

        if (QrScanner::isUnavailable($e)) {
            return $this->json([
                'success' => false,
                'error' => 'Mfumo wa skena haujasakinishwa. Msimamizi wa mfumo aendeshe migration ya scanner.',
                'code' => 'SCANNER_NOT_INSTALLED',
            ], 503);
        }

        return $this->json([
            'success' => false,
            'error' => $message !== '' ? $message : $fallback,
            'code' => QrScanner::errorCode($message),
        ], 422);
    }
}
