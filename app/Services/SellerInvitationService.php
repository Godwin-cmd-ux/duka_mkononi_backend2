<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SellerInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SellerInvitationService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function normalizeCode($code): string
    {
        if (! is_string($code)) {
            return '';
        }

        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /**
     * Resolve an active invitation and verify the business, approved admin and
     * branch against the database. No client-supplied business/branch ids are
     * ever consulted.
     *
     * @return array{invitation_id?:string,code?:string,business_id?:string,business_name?:string,business_structure?:string,business_location?:?string,branch_id?:?string,branch_name?:?string,error?:string,error_code?:string,status?:int}
     */
    public static function resolve(string $code): array
    {
        if ($code === '') {
            return ['error' => 'Msimbo wa mwaliko unahitajika.', 'error_code' => 'INVITATION_REQUIRED', 'status' => 400];
        }

        try {
            $invitation = SellerInvitation::where('code', $code)->first();
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $missing = str_contains($message, 'could not find the table')
                || str_contains($message, 'schema cache')
                || str_contains($message, '42p01')
                || str_contains($message, 'does not exist');

            return [
                'error' => $missing
                    ? 'Mfumo wa mwaliko haujawekwa. Wasiliana na msimamizi wa mfumo.'
                    : 'Mfumo wa mwaliko haupatikani kwa sasa. Tafadhali jaribu tena.',
                'error_code' => $missing ? 'INVITATIONS_NOT_INSTALLED' : 'INVITATIONS_UNAVAILABLE',
                'status' => 503,
            ];
        }

        if (! $invitation) {
            return ['error' => 'Msimbo wa mwaliko si sahihi.', 'error_code' => 'INVITATION_NOT_FOUND', 'status' => 404];
        }

        $status = strtolower((string) ($invitation->status ?? 'active'));

        if ($status === 'used') {
            return ['error' => 'Msimbo huu wa mwaliko umetumika tayari.', 'error_code' => 'INVITATION_USED', 'status' => 409];
        }

        if ($status === 'revoked') {
            return ['error' => 'Msimbo huu wa mwaliko umeghairiwa.', 'error_code' => 'INVITATION_REVOKED', 'status' => 410];
        }

        if ($status !== 'active') {
            return ['error' => 'Msimbo huu wa mwaliko haupo katika hali inayokubalika.', 'error_code' => 'INVITATION_INACTIVE', 'status' => 409];
        }

        if ($invitation->expires_at && strtotime((string) $invitation->expires_at) < time()) {
            return ['error' => 'Muda wa msimbo wa mwaliko umeisha. Omba msimbo mpya.', 'error_code' => 'INVITATION_EXPIRED', 'status' => 410];
        }

        $business = Business::where('id', (string) $invitation->business_id)->first();

        if (! $business) {
            return ['error' => 'Biashara ya mwaliko huu haipatikani.', 'error_code' => 'BUSINESS_NOT_FOUND', 'status' => 404];
        }

        $businessId = (string) $business->id;
        $businessName = trim((string) $business->business_name);

        $hasApprovedAdmin = User::where('business_id', $businessId)
            ->whereIn('role', Roles::BUSINESS_OWNERS)
            ->where('status', 'approved')
            ->count() > 0;

        if (! $hasApprovedAdmin) {
            $hasApprovedAdmin = User::whereRaw('LOWER(business_name) = ?', [mb_strtolower($businessName)])
                ->whereIn('role', Roles::BUSINESS_OWNERS)
                ->where('status', 'approved')
                ->count() > 0;
        }

        if (! $hasApprovedAdmin) {
            return ['error' => 'Biashara hii haina msimamizi aliyethibitishwa.', 'error_code' => 'BUSINESS_NO_ADMIN', 'status' => 409];
        }

        $structure = in_array((string) ($business->business_structure ?? ''), BranchContext::STRUCTURES, true)
            ? (string) $business->business_structure
            : BranchContext::NON_BRANCH;
        $branchId = null;
        $branchName = null;
        $branchLocation = null;

        if ($structure === 'branch') {
            $branch = $invitation->branch_id
                ? BranchContext::activeBranch((string) $invitation->branch_id, $businessId)
                : null;

            if (! $branch) {
                return [
                    'error' => 'Tawi la mwaliko huu halipatikani kwenye biashara hiyo au limezimwa.',
                    'error_code' => 'BRANCH_NOT_FOUND',
                    'status' => 410,
                ];
            }

            $branchId = (string) $branch->id;
            $branchName = (string) $branch->branch_name;
            $branchLocation = $branch->location ?: null;
        }

        return [
            'invitation_id' => (string) $invitation->id,
            'code' => (string) $invitation->code,
            'business_id' => $businessId,
            'business_name' => $businessName,
            'business_structure' => $structure,
            'business_location' => $business->business_location ?: null,
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'branch_location' => $branchLocation,
            'expires_at' => $invitation->expires_at,
        ];
    }

    /**
     * Claim the active invitation for one user. The follow-up read verifies
     * that this user won, so concurrent claimers cannot both continue.
     */
    public static function claim(string $invitationId, string $userId): bool
    {
        SellerInvitation::where('id', $invitationId)->where('status', 'active')->update([
            'status' => 'used',
            'used_by' => $userId,
            'used_at' => now()->toISOString(true),
        ]);

        $invitation = SellerInvitation::where('id', $invitationId)->first();

        return $invitation !== null
            && $invitation->status === 'used'
            && (string) $invitation->used_by === $userId;
    }

    public static function release(string $invitationId, ?string $userId): void
    {
        try {
            SellerInvitation::where('id', $invitationId)
                ->where('used_by', $userId ?: '')
                ->update([
                    'status' => 'active',
                    'used_by' => null,
                    'used_at' => null,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Could not release seller invitation after registration failure', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function createForBusiness(
        string $businessId,
        ?string $branchId,
        ?string $createdBy,
        string $expiresAt
    ): SupabaseRow {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $code = 'DM';

            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }

            try {
                return SellerInvitation::create([
                    'code' => $code,
                    'business_id' => $businessId,
                    'branch_id' => $branchId,
                    'created_by' => $createdBy,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                    'created_at' => now()->toISOString(true),
                ]);
            } catch (\Throwable $e) {
                $message = strtolower($e->getMessage());
                $duplicate = str_contains($message, 'duplicate')
                    || str_contains($message, 'unique')
                    || str_contains($message, '23505');

                if (! $duplicate || $attempt === 5) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not generate a unique invitation code.');
    }

    public static function generateCode(): string
    {
        $code = 'DM';

        for ($i = 0; $i < 8; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }
}
