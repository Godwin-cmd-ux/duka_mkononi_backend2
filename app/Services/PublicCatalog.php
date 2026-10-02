<?php

namespace App\Services;

use App\Models\Advertisement;
use App\Models\Business;
use App\Models\CustomerReview;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only public catalogue. Single source of truth for everything the public
 * website (homepage, Shop, Businesses, Advertisements, Reviews) may see.
 *
 * Rules baked in here, never in the Blade layer:
 *  - only `is_public` businesses are shoppable/listed;
 *  - only `is_certified` businesses appear on the certified Businesses page;
 *  - only `is_active` products that actually have a selling price are shown;
 *  - only `is_published` reviews are returned.
 *
 * Selling price is `expected_selling_price`. `price` is the BUYING price and
 * must never be shown to a customer (existing platform convention).
 *
 * All lookups bulk-load users and products ONCE and join in PHP, because the
 * Supabase REST backend charges a round trip per query and a per-business loop
 * makes the Shop page unusably slow.
 */
class PublicCatalog
{
    /**
     * @return array{members:array<string,array<int,string>>,sellerToBusiness:array<string,string>}
     */
    private static function memberMap(): array
    {
        return Cache::store('file')->remember('dm_public_member_map', 90, function () {
            return self::buildMemberMap();
        });
    }

    private static function buildMemberMap(): array
    {
        $users = User::select('id', 'business_id', 'role', 'status')->get()->toArray();

        $members = [];          // business id => approved admin/seller user ids
        $sellerToBusiness = []; // user id => business id

        foreach ($users as $u) {
            if (! in_array($u['role'] ?? '', ['admin', 'seller'], true)) {
                continue;
            }
            if (($u['status'] ?? '') !== 'approved') {
                continue;
            }
            $businessId = $u['business_id'] ?? null;
            if (! $businessId) {
                continue;
            }
            $members[$businessId][] = $u['id'];
            $sellerToBusiness[$u['id']] = $businessId;
        }

        return ['members' => $members, 'sellerToBusiness' => $sellerToBusiness];
    }

    /** @return array<int,array<string,mixed>> active products that carry a selling price */
    private static function sellableProducts(): array
    {
        return Cache::store('file')->remember('dm_public_sellable_products', 90, function () {
            $rows = Product::select(
                'id', 'seller_id', 'name', 'description', 'category', 'image_url', 'unit',
                'stock', 'is_active', 'expected_selling_price'
            )->where('is_active', true)->get()->toArray();

            return array_values(array_filter($rows, function ($p) {
                $selling = $p['expected_selling_price'] ?? null;

                return $selling !== null && $selling !== '' && (float) $selling > 0;
            }));
        });
    }

    /** Public businesses available for shopping, each with a live product count. */
    public static function shopBusinesses(): array
    {
        $businesses = Business::where('is_public', true)->orderBy('business_name')->get()->toArray();
        $memberMap = self::memberMap();
        $products = self::sellableProducts();

        $counts = [];
        foreach ($products as $p) {
            $bizId = $memberMap['sellerToBusiness'][$p['seller_id'] ?? ''] ?? null;
            if ($bizId) {
                $counts[$bizId] = ($counts[$bizId] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($businesses as $business) {
            $out[] = [
                'id' => $business['id'],
                'business_name' => $business['business_name'],
                'business_location' => $business['business_location'] ?? null,
                'business_type' => $business['business_type'] ?? null,
                'business_description' => $business['business_description'] ?? null,
                'business_logo_url' => $business['business_logo_url'] ?? null,
                'is_certified' => (bool) ($business['is_certified'] ?? false),
                'product_count' => $counts[$business['id']] ?? 0,
            ];
        }

        return $out;
    }

    /** Businesses the platform has actually certified (never all registrations). */
    public static function certifiedBusinesses(): array
    {
        $businesses = Business::where('is_public', true)
            ->where('is_certified', true)
            ->orderBy('business_name')
            ->get()
            ->toArray();

        $memberMap = self::memberMap();
        $products = self::sellableProducts();

        $counts = [];
        foreach ($products as $p) {
            $bizId = $memberMap['sellerToBusiness'][$p['seller_id'] ?? ''] ?? null;
            if ($bizId) {
                $counts[$bizId] = ($counts[$bizId] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($businesses as $business) {
            $out[] = [
                'id' => $business['id'],
                'business_name' => $business['business_name'],
                'business_location' => $business['business_location'] ?? null,
                'business_type' => $business['business_type'] ?? null,
                'business_description' => $business['business_description'] ?? null,
                'business_logo_url' => $business['business_logo_url'] ?? null,
                'is_certified' => true,
                'certified_at' => $business['certified_at'] ?? null,
                'product_count' => $counts[$business['id']] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Products eligible for public sale, optionally filtered by the stable
     * business id. Server-side filtering, never names.
     */
    public static function products(?string $businessId = null, ?string $search = null): array
    {
        $businesses = Business::where('is_public', true)->get()->toArray();
        $businessById = [];
        foreach ($businesses as $b) {
            $businessById[$b['id']] = $b;
        }

        $memberMap = self::memberMap();
        $sellerToBusiness = $memberMap['sellerToBusiness'];

        $needle = $search !== null ? mb_strtolower(trim($search)) : '';

        $out = [];
        foreach (self::sellableProducts() as $p) {
            $bizId = $sellerToBusiness[$p['seller_id'] ?? ''] ?? null;
            if ($bizId === null || ! isset($businessById[$bizId])) {
                continue;
            }
            if ($businessId !== null && $businessId !== '' && $bizId !== $businessId) {
                continue;
            }

            $business = $businessById[$bizId];
            $stock = (int) ($p['stock'] ?? 0);

            if ($needle !== '') {
                $haystack = mb_strtolower(($p['name'] ?? '') . ' ' . ($p['category'] ?? '') . ' ' . ($business['business_name'] ?? ''));
                if (! str_contains($haystack, $needle)) {
                    continue;
                }
            }

            $out[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'description' => $p['description'] ?? null,
                'category' => $p['category'] ?? null,
                'image_url' => $p['image_url'] ?? null,
                'unit' => $p['unit'] ?? null,
                'selling_price' => (float) $p['expected_selling_price'],
                'currency' => 'TZS',
                'stock' => $stock,
                'available' => $stock > 0,
                'business_id' => $bizId,
                'business_name' => $business['business_name'] ?? null,
                'business_location' => $business['business_location'] ?? null,
                'business_logo_url' => $business['business_logo_url'] ?? null,
                'business_is_certified' => (bool) ($business['is_certified'] ?? false),
            ];
        }

        usort($out, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $out;
    }

    /** A single publicly sellable product (for the detail / order view). */
    public static function product(string $id): ?array
    {
        if (! OrderService::isUuid($id)) {
            return null;
        }

        try {
            $product = Product::where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
        if (! $product || ! $product->is_active) {
            return null;
        }

        $selling = $product->expected_selling_price;
        if ($selling === null || $selling === '' || (float) $selling <= 0) {
            return null;
        }

        $businessId = BusinessResolver::idForUser($product->seller_id);
        if (! $businessId) {
            return null;
        }
        $business = Business::where('id', $businessId)->first();
        if (! $business || ! $business->is_public) {
            return null;
        }

        $stock = (int) ($product->stock ?? 0);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category,
            'image_url' => $product->image_url,
            'unit' => $product->unit,
            'selling_price' => (float) $selling,
            'currency' => 'TZS',
            'stock' => $stock,
            'available' => $stock > 0,
            'business_id' => $businessId,
            'business_name' => $business->business_name,
            'business_location' => $business->business_location,
            'business_logo_url' => $business->business_logo_url,
        ];
    }

    /**
     * Advertisements eligible for public display, applying exactly the same
     * rule as the existing public API endpoint (is_active, and either free or
     * paid + not expired).
     */
    public static function eligibleAdvertisements(): array
    {
        $matangazo = Advertisement::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        $now = time();

        return array_values(array_filter($matangazo, function ($m) use ($now) {
            $isFree = (bool) ($m['is_free'] ?? false);
            $paidNotExpired = ($m['payment_status'] ?? null) === 'completed'
                && ! empty($m['expires_at'])
                && strtotime($m['expires_at']) > $now;

            return $isFree || $paidNotExpired;
        }));
    }

    /** Only published reviews; never pending/private inquiries. */
    public static function publishedReviews(int $limit = 12): array
    {
        $rows = CustomerReview::where('is_published', true)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->toArray();

        return array_map(function ($r) {
            return [
                'id' => $r['id'],
                'display_name' => $r['display_name'] ?? null,
                'title' => $r['title'] ?? null,
                'body' => $r['body'] ?? '',
                'rating' => $r['rating'] ?? null,
                'published_at' => $r['published_at'] ?? null,
            ];
        }, $rows);
    }
}
