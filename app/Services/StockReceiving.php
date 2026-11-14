<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The single application path for increasing Business Stock.
 *
 * Sellers can sell and move stock between Outside and Fridges, but they must
 * never mint stock. This service calls the atomic receive_product_stock RPC,
 * which re-checks the administrator role, locks the product and writes an
 * append-only stock_receipts row. Controllers must not update products.stock
 * upward themselves.
 */
final class StockReceiving
{
    public const SOURCES = ['manual', 'scanner', 'ai_import'];

    public static function reference(): string
    {
        return 'RCV-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(8));
    }

    /**
     * @return array<string,mixed>
     *
     * @throws RuntimeException when the role, branch, product or stock
     *                          constraints fail, or the RPC is not installed.
     */
    public static function receive(
        string $businessId,
        ?string $branchId,
        string $productId,
        int $quantity,
        string $userId,
        string $source = 'manual',
        ?string $reference = null,
        ?string $notes = null
    ): array {
        if ($quantity <= 0) {
            throw new RuntimeException('Kiasi cha stock kinapaswa kuwa kikubwa kuliko sifuri.');
        }

        if (! in_array($source, self::SOURCES, true)) {
            throw new RuntimeException('Chanzo cha kupokea stock si sahihi.');
        }

        $result = Supabase::callFunction('receive_product_stock', [
            'p_product_id' => $productId,
            'p_business_id' => $businessId,
            'p_branch_id' => $branchId,
            'p_quantity' => $quantity,
            'p_user_id' => $userId,
            'p_source' => $source,
            'p_reference' => $reference ?: self::reference(),
            'p_notes' => $notes,
        ]);

        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
            $result = $result[0];
        }

        return is_array($result) ? $result : [];
    }

    /** True when the deployment has not applied the stock-receipts migration. */
    public static function isUnavailable(Throwable $error): bool
    {
        $message = strtolower($error->getMessage());

        $missingFunction = str_contains($message, 'pgrst202')
            || str_contains($message, 'could not find the function')
            || str_contains($message, 'undefined function');

        // A duplicate reference also mentions stock_receipts, so only treat
        // the table as missing when the database says it does not exist.
        $missingTable = str_contains($message, 'stock_receipts')
            && str_contains($message, 'does not exist');

        return $missingFunction || $missingTable;
    }
}
