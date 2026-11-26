<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\SellerInvitation;
use App\Services\BranchContext;
use App\Services\SellerInvitationService;
use Illuminate\Http\Request;

class SellerInvitationController extends BaseController
{
    /**
     * GET /api/admin/invitations
     *
     * Latest invitations for the caller's own business only.
     */
    public function index(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kusimamia mialiko.'], 403);
        }

        $businessId = $this->businessId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana.'], 403);
        }

        try {
            $invitations = SellerInvitation::where('business_id', $businessId)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get();
            $branchNames = $this->branchNames($businessId);

            return $this->json([
                'success' => true,
                'invitations' => $invitations->map(fn ($invitation) => $this->payload(
                    $invitation,
                    $branchNames[(string) $invitation->branch_id] ?? null
                ))->all(),
            ]);
        } catch (\Throwable $e) {
            return $this->tableFailure($e);
        }
    }

    /**
     * POST /api/admin/invitations
     *
     * Creates a single-use code for the verified JWT business. A branchful
     * business must name one of its own active branches.
     */
    public function store(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kuunda mialiko.'], 403);
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana.'], 403);
        }

        $days = (int) $request->input('expires_in_days', 30);

        if ($days < 1 || $days > 90) {
            return $this->json(['success' => false, 'error' => 'Muda wa mwaliko uwe kati ya siku 1 na 90.'], 422);
        }

        try {
            $business = Business::where('id', $businessId)->first();

            if (! $business) {
                return $this->json(['success' => false, 'error' => 'Biashara haipatikani.'], 404);
            }

            $structure = in_array((string) ($business->business_structure ?? ''), BranchContext::STRUCTURES, true)
                ? (string) $business->business_structure
                : BranchContext::NON_BRANCH;
            $branchId = null;
            $branchName = null;

            if ($structure === 'branch') {
                $branchId = trim((string) $request->input('branch_id', ''));

                if ($branchId === '') {
                    return $this->json([
                        'success' => false,
                        'error' => 'Chagua tawi ambalo mwaliko utatumika.',
                        'code' => 'BRANCH_REQUIRED',
                    ], 422);
                }

                $branch = BranchContext::activeBranch($branchId, $businessId);

                if (! $branch) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Tawi ulilochagua halipatikani kwenye biashara hii au limezimwa.',
                        'code' => 'BRANCH_NOT_FOUND',
                    ], 404);
                }

                $branchName = (string) $branch->branch_name;
            }

            $invitation = SellerInvitationService::createForBusiness(
                $businessId,
                $branchId,
                $userId,
                now()->addDays($days)->toISOString(true)
            );

            $this->log($userId, 'SELLER_INVITATION_CREATED', '/api/admin/invitations', [
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'invitation_id' => (string) $invitation->id,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Msimbo wa mwaliko umeundwa.',
                'invitation' => $this->payload($invitation, $branchName),
                'business' => [
                    'id' => $businessId,
                    'business_name' => $business->business_name,
                    'business_structure' => $structure,
                ],
                'branch' => $branchId ? [
                    'id' => $branchId,
                    'branch_name' => $branchName,
                ] : null,
            ], 201);
        } catch (\Throwable $e) {
            return $this->tableFailure($e);
        }
    }

    /**
     * POST /api/admin/invitations/{id}/revoke
     */
    public function revoke(Request $request, string $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kughairi mialiko.'], 403);
        }

        $businessId = $this->businessId($request);
        $userId = $this->userId($request);

        if (! $businessId) {
            return $this->json(['success' => false, 'error' => 'Biashara haijapatikana.'], 403);
        }

        try {
            $invitation = SellerInvitation::where('id', $id)->where('business_id', $businessId)->first();

            if (! $invitation) {
                return $this->json(['success' => false, 'error' => 'Mwaliko haupatikani.'], 404);
            }

            if ($invitation->status === 'used') {
                return $this->json(['success' => false, 'error' => 'Msimbo huu umetumika tayari.'], 409);
            }

            SellerInvitation::where('id', $id)->where('business_id', $businessId)->update([
                'status' => 'revoked',
            ]);

            $this->log($userId, 'SELLER_INVITATION_REVOKED', '/api/admin/invitations/' . $id . '/revoke', [
                'business_id' => $businessId,
                'invitation_id' => $id,
            ], $this->ip($request), 'success');

            return $this->json(['success' => true, 'message' => 'Msimbo wa mwaliko umeghairiwa.']);
        } catch (\Throwable $e) {
            return $this->tableFailure($e);
        }
    }

    /**
     * GET /api/seller-registration/invitations/{code}
     *
     * Public but narrow: only the business and branch identity needed to
     * confirm the invitation on the seller signup screen.
     */
    public function lookup(string $code)
    {
        $resolved = SellerInvitationService::resolve(SellerInvitationService::normalizeCode($code));

        if (isset($resolved['error'])) {
            return $this->json([
                'success' => false,
                'error' => $resolved['error'],
                'code' => $resolved['error_code'] ?? 'INVITATION_INVALID',
            ], $resolved['status'] ?? 400);
        }

        return $this->json([
            'success' => true,
            'invitation' => [
                'code' => $resolved['code'],
                'expires_at' => $resolved['expires_at'] ?? null,
            ],
            'business' => [
                'id' => $resolved['business_id'],
                'business_name' => $resolved['business_name'],
                'business_structure' => $resolved['business_structure'],
                'business_location' => $resolved['business_location'] ?? null,
            ],
            'branch' => $resolved['branch_id'] ? [
                'id' => $resolved['branch_id'],
                'branch_name' => $resolved['branch_name'],
                'location' => $resolved['branch_location'] ?? null,
            ] : null,
        ]);
    }

    private function branchNames(string $businessId): array
    {
        try {
            return Branch::where('business_id', $businessId)->get()
                ->mapWithKeys(fn ($branch) => [(string) $branch->id => (string) $branch->branch_name])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function payload($invitation, ?string $branchName): array
    {
        return [
            'id' => (string) $invitation->id,
            'code' => (string) $invitation->code,
            'business_id' => (string) $invitation->business_id,
            'branch_id' => $invitation->branch_id ? (string) $invitation->branch_id : null,
            'branch_name' => $branchName,
            'status' => (string) ($invitation->status ?? 'active'),
            'expires_at' => $invitation->expires_at,
            'created_at' => $invitation->created_at,
            'used_at' => $invitation->used_at,
        ];
    }

    private function tableFailure(\Throwable $e)
    {
        $message = strtolower($e->getMessage());
        $missing = str_contains($message, 'could not find the table')
            || str_contains($message, 'schema cache')
            || str_contains($message, '42p01')
            || str_contains($message, 'does not exist');

        if ($missing) {
            return $this->json([
                'success' => false,
                'error' => 'Jedwali la mialiko halipo. Wasiliana na msimamizi wa mfumo kuweka migration.',
                'code' => 'INVITATIONS_NOT_INSTALLED',
            ], 503);
        }

        return $this->dbError($e, 'Imeshindikana kusimamia mialiko');
    }
}
