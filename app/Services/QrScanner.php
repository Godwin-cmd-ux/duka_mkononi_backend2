<?php

namespace App\Services;

use App\Models\ProductItem;
use RuntimeException;
use Throwable;

/**
 * The ONLY application path for physical-item (QR) operations.
 *
 * A camera detecting a code proves nothing: every scan is re-validated by a
 * PostgreSQL function that locks the item row and re-checks the business,
 * branch, product, status and location before it changes anything. This class
 * is a thin, typed wrapper around those functions so controllers cannot invent
 * their own rules.
 *
 * Item lifecycle:
 *   pending -> in_stock (outside) -> in_stock (fridge) -> reserved -> sold
 *   pending -> deleted (receiving cancelled)
 * A sold item can never become available again, and the same token can never
 * be registered against a second physical item.
 */
final class QrScanner
{
    /** A QR may encode a plain token or DMK's own prefixed token. */
    public const TOKEN_PREFIXES = ['dmk1:', 'dmk:', 'dukamkononi:'];

    public const MIN_TOKEN_LENGTH = 4;
    public const MAX_TOKEN_LENGTH = 200;

    /** Cart claims live this long before the item returns to the shelf. */
    public const RESERVATION_TTL_MINUTES = 30;

    /**
     * Normalise a scanned code: trim it, drop a Duka Mkononi prefix, collapse
     * inner whitespace. Returns null when the code cannot identify an item.
     */
    public static function normalizeToken($raw): ?string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $token = trim((string) $raw);
        if ($token === '') {
            return null;
        }

        $lower = mb_strtolower($token);
        foreach (self::TOKEN_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                $token = trim(substr($token, strlen($prefix)));
                break;
            }
        }

        // A QR that encodes a URL keeps only its last path segment, which is
        // how Duka Mkononi prints item labels (/items/<token>).
        if (preg_match('#^https?://#i', $token)) {
            $path = (string) parse_url($token, PHP_URL_PATH);
            $segment = trim(basename($path));
            $token = $segment !== '' ? $segment : $token;
        }

        $token = preg_replace('/\s+/', '', $token) ?? $token;

        if ($token === '' || mb_strlen($token) < self::MIN_TOKEN_LENGTH || mb_strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        return $token;
    }

    /** True when the caller's business has the Scanner feature switched on. */
    public static function enabled(?string $businessId): bool
    {
        return BusinessSettings::enabled('scanner', $businessId);
    }

    /* ------------------------------------------------------------------ */
    /* Administrator receiving                                             */
    /* ------------------------------------------------------------------ */

    public static function openSession(string $businessId, ?string $branchId, string $productId, string $userId): array
    {
        return self::call('qr_open_receiving_session', [
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_product_id' => $productId,
            'p_user_id' => $userId,
        ]);
    }

    public static function receiveItem(string $sessionId, string $token, string $businessId, ?string $branchId, string $productId, string $userId): array
    {
        return self::call('qr_receive_item', [
            'p_session_id' => $sessionId,
            'p_qr_token' => $token,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_product_id' => $productId,
            'p_user_id' => $userId,
        ]);
    }

    public static function completeSession(string $sessionId, string $businessId, ?string $branchId, string $userId): array
    {
        return self::call('qr_complete_receiving', [
            'p_session_id' => $sessionId,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_user_id' => $userId,
        ]);
    }

    public static function cancelSession(string $sessionId, string $businessId, string $userId): array
    {
        return self::call('qr_cancel_receiving', [
            'p_session_id' => $sessionId,
            'p_business_id' => $businessId,
            'p_user_id' => $userId,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Selling                                                             */
    /* ------------------------------------------------------------------ */

    public static function reserveItem(
        string $token,
        string $productId,
        string $businessId,
        ?string $branchId,
        string $userId,
        string $clientRef
    ): array {
        return self::call('qr_reserve_item', [
            'p_qr_token' => $token,
            'p_product_id' => $productId,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_user_id' => $userId,
            'p_client_ref' => $clientRef,
            'p_ttl_minutes' => self::RESERVATION_TTL_MINUTES,
        ]);
    }

    /**
     * @param  array<int,string>  $itemIds
     */
    public static function releaseItems(array $itemIds, string $businessId, string $userId, string $clientRef): array
    {
        return self::call('qr_release_sale_items', [
            'p_item_ids' => array_values($itemIds),
            'p_business_id' => $businessId,
            'p_user_id' => $userId,
            'p_client_ref' => $clientRef,
        ]);
    }

    /**
     * Commit a sale whose lines contain scanned physical items, in ONE
     * transaction (items are marked sold only if the sale itself commits).
     *
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<int,string>  $itemIds
     */
    public static function commitSale(
        string $saleId,
        string $sellerId,
        string $businessId,
        ?string $branchId,
        string $invoiceNumber,
        ?string $customerId,
        ?string $saleDate,
        ?string $paymentMethod,
        ?string $notes,
        array $items,
        array $itemIds,
        string $clientRef
    ): array {
        return self::call('qr_commit_sale', [
            'p_sale_id' => $saleId,
            'p_seller_id' => $sellerId,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_invoice_number' => $invoiceNumber,
            'p_customer_id' => $customerId,
            'p_sale_date' => $saleDate,
            'p_payment_method' => $paymentMethod,
            'p_notes' => $notes,
            'p_items' => array_values($items),
            'p_item_ids' => array_values($itemIds),
            'p_client_ref' => $clientRef,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Fridge transfers                                                    */
    /* ------------------------------------------------------------------ */

    public static function moveToFridge(string $token, string $fridgeId, string $productId, string $businessId, ?string $branchId, string $userId): array
    {
        return self::call('qr_move_item_to_fridge', [
            'p_qr_token' => $token,
            'p_fridge_id' => $fridgeId,
            'p_product_id' => $productId,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_user_id' => $userId,
        ]);
    }

    public static function moveOutOfFridge(string $token, string $fridgeId, string $productId, string $businessId, ?string $branchId, string $userId): array
    {
        return self::call('qr_move_item_out_of_fridge', [
            'p_qr_token' => $token,
            'p_fridge_id' => $fridgeId,
            'p_product_id' => $productId,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_user_id' => $userId,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Reads                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve a token inside the caller's business. Used by the "identify"
     * endpoints (orders, preparation, verification) which must not mutate
     * anything.
     */
    public static function findItem(string $token, string $businessId): ?array
    {
        $row = ProductItem::where('business_id', $businessId)
            ->whereRaw('LOWER(qr_token) = ?', [mb_strtolower($token)])
            ->select(array_merge(ProductItem::PUBLIC_COLUMNS, ['qr_token']))
            ->first();

        return $row ? $row->toArray() : null;
    }

    /**
     * Items of one product, for preparation / stock verification screens.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function itemsForProduct(string $productId, string $businessId, ?string $branchId, ?string $status = null, int $limit = 100): array
    {
        $query = ProductItem::where('business_id', $businessId)
            ->where('product_id', $productId);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->select(array_merge(ProductItem::PUBLIC_COLUMNS, ['qr_token']))
            ->orderBy('created_at')
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->toArray();
    }

    /**
     * Snapshot of per-item tracking against the stock ledger. Never changes
     * anything; used by the verification endpoint and by operators checking a
     * deployment.
     */
    public static function stockSummary(string $businessId): array
    {
        $result = Supabase::callFunction('qr_item_stock_summary', ['p_business_id' => $businessId]);

        return is_array($result) ? $result : [];
    }

    /* ------------------------------------------------------------------ */
    /* Errors                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Map a database function's message onto a stable API code so web and
     * mobile can present a precise reason instead of a generic failure.
     */
    public static function errorCode(string $message): string
    {
        $m = mb_strtolower($message);

        return match (true) {
            str_contains($m, 'tayari imesajiliwa') => 'QR_ALREADY_REGISTERED',
            str_contains($m, 'haijasajiliwa') => 'QR_UNKNOWN',
            str_contains($m, 'imeshauzwa') => 'ITEM_SOLD',
            str_contains($m, 'bidhaa tofauti') => 'PRODUCT_MISMATCH',
            str_contains($m, 'tayari kiko kwenye mauzo haya'),
            str_contains($m, 'mara zaidi ya moja') => 'ITEM_DUPLICATE',
            str_contains($m, 'kimehifadhiwa') => 'ITEM_RESERVED',
            str_contains($m, 'tawi lingine') => 'BRANCH_MISMATCH',
            str_contains($m, 'biashara nyingine') => 'BUSINESS_MISMATCH',
            str_contains($m, 'friji') => 'FRIDGE_MISMATCH',
            str_contains($m, 'haijakamilika kupokelewa') => 'ITEM_PENDING',
            str_contains($m, 'kimeondolewa') => 'ITEM_VOID',
            str_contains($m, 'stock haitoshi'),
            str_contains($m, 'stock ya nje haitoshi') => 'INSUFFICIENT_STOCK',
            str_contains($m, 'stock ya friji haitoshi') => 'INSUFFICIENT_FRIDGE_STOCK',
            str_contains($m, 'hakiendani') => 'QUANTITY_MISMATCH',
            str_contains($m, 'ruhusa') => 'FORBIDDEN',
            str_contains($m, 'kimeshakamilika') => 'SESSION_COMPLETED',
            str_contains($m, 'kimeghairiwa') => 'SESSION_CANCELLED',
            str_contains($m, 'hakuna kipande') => 'NO_ITEMS',
            default => 'SCANNER_FAILED',
        };
    }

    /** True when the deployment has not applied the scanner migration yet. */
    public static function isUnavailable(Throwable $error): bool
    {
        $message = mb_strtolower($error->getMessage());

        return str_contains($message, 'pgrst202')
            || str_contains($message, 'could not find the function')
            || str_contains($message, 'undefined function')
            || (str_contains($message, 'product_items') && str_contains($message, 'does not exist'))
            || (str_contains($message, 'relation') && str_contains($message, 'does not exist'));
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed> */
    private static function call(string $function, array $params): array
    {
        $result = Supabase::callFunction($function, $params);

        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
            $result = $result[0];
        }

        if (! is_array($result)) {
            throw new RuntimeException('Jibu la mfumo wa skena si sahihi.');
        }

        return $result;
    }
}
