<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\ProductSearch;
use App\Services\PublicCatalog;
use Illuminate\Http\Request;

/**
 * Public, read-only catalogue endpoints for the website and the mobile app.
 * No authentication: these expose only publicly eligible records.
 */
class ShopController extends BaseController
{
    public function businesses(Request $request)
    {
        return $this->json(PublicCatalog::shopBusinesses());
    }

    public function certifiedBusinesses(Request $request)
    {
        return $this->json(PublicCatalog::certifiedBusinesses());
    }

    public function products(Request $request)
    {
        $businessId = $request->query('business_id');
        $search = $request->query('search');

        $businessId = is_string($businessId) && $businessId !== '' ? $businessId : null;
        $hasSearch = is_string($search) && trim($search) !== '';

        /* Capability #24: when the search box is used and the capability is on,
           rank the same public rows with the typo-tolerant matcher instead of
           the old single substring test. Same fields, same pagination - the
           ordering is simply the relevance one, and each row additionally
           carries how it matched. With the flag off (or no query) the previous
           behaviour is byte-for-byte preserved. */
        $ranked = $hasSearch
            && (bool) config('ai.features.search', true)
            && (bool) config('ai.search.enabled', true);

        if ($ranked) {
            $result = ProductSearch::rank(PublicCatalog::products($businessId), trim((string) $search), [
                // Keep every match here; the pagination below decides the page.
                'limit' => max(1, (int) config('ai.search.max_candidates', 2000)),
            ]);
            $all = $result['results'];
        } else {
            $all = PublicCatalog::products($businessId, $hasSearch ? $search : null);
        }

        $perPage = (int) $request->query('per_page', 24);
        $perPage = $perPage > 0 && $perPage <= 60 ? $perPage : 24;
        $page = max(1, (int) $request->query('page', 1));
        $total = count($all);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

        return $this->json([
            'data' => $slice,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function product(Request $request, string $id)
    {
        $product = PublicCatalog::product($id);

        if (! $product) {
            return $this->json(['error' => 'Bidhaa haipatikani'], 404);
        }

        return $this->json($product);
    }

    public function advertisements(Request $request)
    {
        $advertisements = PublicCatalog::eligibleAdvertisements();

        // Never expose the owner's private contact data; return only what the
        // public page needs.
        $out = array_map(function ($m) {
            return [
                'id' => $m['id'] ?? null,
                'title' => $m['title'] ?? null,
                'description' => $m['description'] ?? null,
                'media_url' => $m['media_url'] ?? null,
                'media_type' => $m['media_type'] ?? 'image',
                'thumbnail_url' => $m['thumbnail_url'] ?? ($m['media_url'] ?? null),
                'category' => $m['category'] ?? null,
                'starts_at' => $m['starts_at'] ?? null,
                'expires_at' => $m['expires_at'] ?? null,
                'created_at' => $m['created_at'] ?? null,
                'business_name' => $m['title'] ?? null,
            ];
        }, $advertisements);

        return $this->json($out);
    }

    public function reviews(Request $request)
    {
        return $this->json(PublicCatalog::publishedReviews());
    }
}
