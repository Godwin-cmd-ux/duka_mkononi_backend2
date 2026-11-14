<?php

namespace App\Services;

use App\Models\FridgeStockAlert;
use Illuminate\Support\Str;

/**
 * Persists the three independent low-stock alert families:
 *   - business_low  : products.stock <= products.min_stock_level
 *   - outside_low   : outside stock <= products.min_outside_stock
 *   - fridge_low    : fridge_stock.quantity <= fridge_stock.min_stock
 *
 * The computation is pure (FridgeStockMath::candidateAlerts); this service only
 * opens/resolves the history rows and keeps one OPEN alert per scope/type.
 */
class FridgeAlertService
{
    public static function sync(
        string $businessId,
        array $products,
        array $fridges,
        array $fridgeStock
    ): array {
        $candidates = FridgeStockMath::candidateAlerts($products, $fridges, $fridgeStock);

        $existing = [];
        try {
            $rows = FridgeStockAlert::where('business_id', $businessId)
                ->where('status', 'open')
                ->get()
                ->toArray();
            foreach ($rows as $row) {
                $existing[self::key($row)] = $row;
            }
        } catch (\Throwable $e) {
            // Alerts are advisory; a failed history read must never block the
            // inventory response.
        }

        $openKeys = [];
        $open = [];

        foreach ($candidates as $candidate) {
            $key = self::key($candidate);
            $openKeys[$key] = true;

            $payload = [
                'business_id' => $businessId,
                'branch_id' => $candidate['branch_id'] ?? null,
                'fridge_id' => $candidate['fridge_id'] ?? null,
                'product_id' => $candidate['product_id'],
                'alert_type' => $candidate['alert_type'],
                'threshold' => $candidate['threshold'],
                'quantity' => $candidate['quantity'],
                'status' => 'open',
                'updated_at' => now()->toISOString(true),
            ];

            if (isset($existing[$key])) {
                try {
                    FridgeStockAlert::where('id', $existing[$key]['id'])->update($payload);
                } catch (\Throwable $e) {
                }
                $open[] = array_merge($existing[$key], $payload, [
                    'product_name' => $candidate['product_name'] ?? null,
                ]);
                continue;
            }

            try {
                $payload['id'] = (string) Str::uuid();
                $payload['created_at'] = now()->toISOString(true);
                FridgeStockAlert::create($payload);
                $open[] = array_merge($payload, [
                    'product_name' => $candidate['product_name'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // A concurrent request may have inserted the same open alert
                // between our read and write. Ignore; the next sync will see
                // it and update it normally.
            }
        }

        foreach ($existing as $key => $row) {
            if (isset($openKeys[$key])) {
                continue;
            }
            try {
                FridgeStockAlert::where('id', $row['id'])->update([
                    'status' => 'resolved',
                    'resolved_at' => now()->toISOString(true),
                    'updated_at' => now()->toISOString(true),
                ]);
            } catch (\Throwable $e) {
            }
        }

        usort($open, function ($a, $b) {
            return [$a['alert_type'] ?? '', $a['product_name'] ?? ''] <=> [$b['alert_type'] ?? '', $b['product_name'] ?? ''];
        });

        return $open;
    }

    private static function key(array $row): string
    {
        return implode('|', [
            (string) ($row['alert_type'] ?? ''),
            (string) ($row['branch_id'] ?? ''),
            (string) ($row['fridge_id'] ?? ''),
            (string) ($row['product_id'] ?? ''),
        ]);
    }
}
