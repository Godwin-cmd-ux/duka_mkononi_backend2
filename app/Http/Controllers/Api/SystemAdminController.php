<?php

namespace App\Http\Controllers\Api;

use App\Models\Business;
use Illuminate\Http\Request;

/**
 * Super Admin platform management that is not tied to a single business.
 * Currently: certifying / un-certifying businesses for the public page.
 */
class SystemAdminController extends BaseController
{
    public function businesses(Request $request)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        try {
            $businesses = Business::orderBy('business_name')->get()->toArray();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata biashara.'], 500);
        }

        return $this->json(array_map(function ($b) {
            return [
                'id' => $b['id'] ?? null,
                'business_name' => $b['business_name'] ?? null,
                'business_location' => $b['business_location'] ?? null,
                'business_type' => $b['business_type'] ?? null,
                'business_logo_url' => $b['business_logo_url'] ?? null,
                'is_certified' => (bool) ($b['is_certified'] ?? false),
                'certified_at' => $b['certified_at'] ?? null,
                'is_public' => (bool) ($b['is_public'] ?? true),
            ];
        }, $businesses));
    }

    public function certify(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $business = Business::where('id', $id)->first();
        if (! $business) {
            return $this->json(['error' => 'Biashara haipatikani.'], 404);
        }

        $certified = $request->boolean('is_certified', true);
        $now = now()->toISOString();

        Business::where('id', $id)->update([
            'is_certified' => $certified,
            'certified_at' => $certified ? ($business->certified_at ?: $now) : null,
            'certification_note' => $request->input('note'),
            'updated_at' => $now,
        ]);

        $this->log($this->userId($request), $certified ? 'BUSINESS_CERTIFY' : 'BUSINESS_UNCERTIFY', '/api/system-admin/businesses/' . $id, [], $this->ip($request), 'success');

        return $this->json([
            'message' => $certified ? 'Biashara imethibitishwa.' : 'Uthibitisho umeondolewa.',
            'is_certified' => $certified,
        ]);
    }

    /** Mirrors InquiryController: platform Super Admin = system_admin or admin. */
    private function isSuperAdmin(Request $request): bool
    {
        return in_array($this->role($request), ['system_admin', 'admin'], true);
    }
}
