<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\BusinessBlock;
use App\Services\BusinessResolver;
use App\Services\JwtToken;
use App\Services\Roles;
use App\Services\SellerInvitationService;
use App\Services\Supabase;
use App\Services\ThemePreference;
use Firebase\JWT\ExpiredException;
use UnexpectedValueException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    private const BUSINESS_TYPE_ALLOWED = [
        'spare_parts', 'motorcycle_spares', 'pharmacy', 'supermarket', 'clothing', 'electronics',
        'restaurant', 'hardware', 'cosmetics', 'perfume', 'mobile_accessories',
        'furniture', 'stationery', 'agriculture', 'construction_materials',
        'beauty_salon', 'barbershop', 'auto_repair', 'phone_shop', 'computer_shop',
        'general_retail', 'wholesale', 'other'
    ];

    private const OTP_EXPIRY_MS = 600000;

    private const OTP_MAX_ATTEMPTS = 5;

    private const OTP_RESEND_COOLDOWN_MS = 30000;

    /**
     * The eight languages the Dukamkononi app supports (Duka_mkononi/context/
     * LanguageContext.tsx). A user preference is only ever stored as one of
     * these codes; anything else is ignored and the existing value is kept.
     */
    private const LANGUAGES_ALLOWED = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    /**
     * Returns the incoming language when it is one of the supported codes,
     * otherwise null.
     */
    private function normalizeLanguage($language): ?string
    {
        if (!is_string($language)) {
            return null;
        }

        $language = strtolower(trim($language));

        return in_array($language, self::LANGUAGES_ALLOWED, true) ? $language : null;
    }

    public function register(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $verificationToken = $request->input('verificationToken');

        if (!$verificationToken) {
            $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Missing verification token'], $ip, 'failed');
            return $this->json(['error' => 'Uthibitisho wa barua pepe unahitajika. Tafadhali kamilisha msimbo wa uthibitisho.'], 400);
        }

        try {
            $verified = JwtToken::decode($verificationToken);
            if (!isset($verified->type) || $verified->type !== 'registration_verified') {
                throw new UnexpectedValueException('Invalid token type');
            }
        } catch (\Throwable $tokenError) {
            $isExpired = $tokenError instanceof ExpiredException;
            $this->log(null, 'REGISTER_FAILED', '/api/register', [
                'reason' => 'Invalid verification token',
                'error' => $tokenError->getMessage(),
                'expired' => $isExpired
            ], $ip, 'failed');
            return $this->json([
                'error' => $isExpired
                    ? 'Uthibitisho wa barua pepe umeisha muda wake (dakika 10). Tafadhali jisajili tena.'
                    : 'Msimbo wa uthibitisho si sahihi au umeisha muda wake. Tafadhali jisajili tena.',
                'code' => $isExpired ? 'VERIFICATION_EXPIRED' : 'INVALID_TOKEN'
            ], 400);
        }

        $email = trim((string) $verified->email);
        $role = $verified->role;
        $passwordHash = $verified->passwordHash;

        // Defense in depth: even a signed verification token may only carry
        // a self-registerable role (the allow-list was enforced at initiate).
        if (!Roles::isRegisterable($role)) {
            $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Role not self-registerable', 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Role hii haijajiandikishwa'], 403);
        }
        $full_name = $verified->full_name ?? null;
        $phone = $verified->phone ?? null;
        $business_name = $verified->business_name ?? null;
        $business_location = $verified->business_location ?? null;
        $business_type = $verified->business_type ?? null;
        $business_description = $verified->business_description ?? null;
        $business_structure = $verified->business_structure ?? 'non_branch';
        $branches = $verified->branches ?? [];
        $seller_business_id = $verified->business_id ?? null;
        $seller_branch_id = $verified->branch_id ?? null;
        $invitationId = $verified->invitation_id ?? null;
        $invitationCode = $this->normalizeInvitationCode($verified->invitation_code ?? '');
        $invitation = null;
        $language = $verified->language ?? 'sw';

        if ($request->input('email') && strtolower(trim($request->input('email'))) !== strtolower(trim($email))) {
            $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Email mismatch with verified token'], $ip, 'failed');
            return $this->json(['error' => 'Barua pepe hailingani na iliyothibitishwa.'], 400);
        }

        $claimedInvitationId = null;

        try {
            $existingUsers = User::select('id')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('role', $role)->get();

            if ($existingUsers && $existingUsers->count() > 0) {
                $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Account already exists', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Akaunti na barua pepe hii tayari ipo'], 400);
            }

            if ($role === 'seller') {
                // Re-validate against the database, not the OTP cache: the
                // business must exist and the branch must still be active and
                // owned by that business at the moment the account is created.
                if (is_string($invitationId) && $invitationId !== '') {
                    $invitation = $this->resolveInvitation($invitationCode);

                    if (isset($invitation['error'])) {
                        $this->log(null, 'REGISTER_FAILED', '/api/register', [
                            'reason' => 'Seller invitation no longer valid',
                            'code' => $invitation['error_code'] ?? 'INVITATION_INVALID',
                            'email' => $email,
                        ], $ip, 'failed');

                        return $this->json([
                            'error' => $invitation['error'],
                            'code' => $invitation['error_code'] ?? 'INVITATION_INVALID',
                        ], $invitation['status'] ?? 400);
                    }

                    if ((string) ($invitation['invitation_id'] ?? '') !== (string) $invitationId) {
                        return $this->json([
                            'error' => 'Mwaliko huu umebadilishwa. Tafadhali anza usajili upya.',
                            'code' => 'INVITATION_MISMATCH',
                        ], 409);
                    }

                    $business_name = $invitation['business_name'];
                    $seller_business_id = $invitation['business_id'];
                    $seller_branch_id = $invitation['branch_id'];
                    $business_structure = $invitation['business_structure'];
                    $business_location = $invitation['business_location'] ?? $business_location;
                } else {
                    return $this->json([
                        'error' => 'Usajili wa muuzaji unahitaji msimbo wa mwaliko kutoka kwa msimamizi.',
                        'code' => 'INVITATION_REQUIRED',
                    ], 400);
                }
            }

            if ($role === 'admin' || $role === 'seller') {
                if (!$business_name) {
                    $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Business name required', 'email' => $email, 'role' => $role], $ip, 'failed');
                    return $this->json(['error' => 'Jina la biashara linahitajika'], 400);
                }

                $adminUsers = User::select('id', 'email', 'role', 'status')
                    ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                    ->whereIn('role', Roles::BUSINESS_OWNERS)
                    ->where('status', 'approved')
                    ->get();

                $hasApprovedAdmin = $adminUsers && $adminUsers->count() > 0;

                if ($role === 'seller') {
                    if (!$hasApprovedAdmin) {
                        $this->log(null, 'REGISTER_FAILED', '/api/register', [
                            'reason' => 'No approved admin for business',
                            'business_name' => $business_name,
                            'email' => $email,
                            'role' => $role
                        ], $ip, 'failed');

                        return $this->json([
                            'error' => 'Biashara hii haina msimamizi. Tafadhali jisajili kama msimamizi kwanza.'
                        ], 400);
                    }
                }

                if ($role === 'admin') {
                    $existingBusiness = User::select('id', 'email', 'role')
                        ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                        ->whereIn('role', ['admin', 'seller'])
                        ->get();

                    if ($existingBusiness && $existingBusiness->count() > 0) {
                        $this->log(null, 'REGISTER_FAILED', '/api/register', [
                            'reason' => 'Business name already taken',
                            'business_name' => $business_name,
                            'existing_user' => $existingBusiness[0]->email
                        ], $ip, 'failed');

                        return $this->json([
                            'error' => 'Jina la biashara tayari limeshasajiliwa',
                            'existingUser' => $existingBusiness[0]->email
                        ], 400);
                    }
                }
            }

            $userStatus = ($role === 'seller') ? 'pending' : 'approved';
            $now = $this->isoNow();

            // Business identity is resolved before the user row so business_type
            // and business_description have a home: they were previously written
            // to users, where the columns do not exist, and silently discarded.
            $businessId = null;
            if ($role === 'seller' && $seller_business_id) {
                // Sellers join an existing business; they never create one.
                $businessId = (string) $seller_business_id;
            } elseif ($business_name) {
                $registration = $this->createBusinessWithBranches(
                    (string) $business_name,
                    (string) ($business_structure ?: 'non_branch'),
                    is_array($branches) ? $branches : [],
                    [
                        'business_type' => $business_type ?: null,
                        'business_description' => $business_description ?: null,
                        'business_location' => $business_location ?: null,
                    ]
                );

                if (! empty($registration['error'])) {
                    $this->log(null, 'REGISTER_FAILED', '/api/register', [
                        'reason' => 'Business/branch creation failed',
                        'error' => $registration['error'],
                    ], $ip, 'failed');

                    return $this->json([
                        'error' => 'Biashara na matawi hayakuweza kuhifadhiwa. Tafadhali jaribu tena.',
                        'code' => 'BUSINESS_CREATE_FAILED',
                    ], 500);
                }

                $businessId = $registration['business_id'];
            }

            $userData = [
                'id' => (string) Str::uuid(),
                'email' => $email,
                'password' => $passwordHash,
                'role' => $role,
                'full_name' => $full_name ?: null,
                'phone' => $phone ?: null,
                'business_name' => $business_name ?: null,
                'business_id' => $businessId,
                'branch_id' => $role === 'seller' ? ($seller_branch_id ?: null) : null,
                'business_location' => $business_location ?: null,
                'language' => $language ?: 'sw',
                'status' => $userStatus,
                'is_online' => false,
                'last_seen' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $id = $userData['id'];

            if ($invitation !== null) {
                // A seller invitation is single-use. Claim it before inserting
                // the user and release it again if the account insert fails.
                if (! $this->claimInvitation((string) $invitation['invitation_id'], $id)) {
                    $this->log(null, 'REGISTER_FAILED', '/api/register', [
                        'reason' => 'Seller invitation already used',
                        'invitation_id' => $invitation['invitation_id'],
                        'email' => $email,
                    ], $ip, 'failed');

                    return $this->json([
                        'error' => 'Msimbo wa mwaliko umetumika tayari. Omba msimbo mpya kwa msimamizi.',
                        'code' => 'INVITATION_USED',
                    ], 409);
                }

                $claimedInvitationId = (string) $invitation['invitation_id'];
            }

            User::create($userData);
            $newUser = User::where('id', $id)->first();

            $this->log($newUser->id, 'REGISTER_SUCCESS', '/api/register', [
                'role' => $newUser->role,
                'status' => $newUser->status,
                'business_name' => $newUser->business_name
            ], $ip, 'success');

            $setupToken = JwtToken::encode([
                'userId' => $newUser->id,
                'email' => $newUser->email,
                'role' => $newUser->role,
                'type' => 'onboarding',
                'exp' => time() + 3600
            ]);

            return $this->json([
                'message' => $role === 'seller'
                    ? 'Akaunti ya muuzaji imeundwa kikamilifu! Umewasilisha ombi lako. Tafadhali subiri uthibitisho wa msimamizi kabla ya kuingia.'
                    : 'Akaunti ya msimamizi imeundwa kikamilifu!',
                'userId' => $newUser->id,
                'status' => 'success',
                'requiresApproval' => $role === 'seller',
                'setupToken' => $setupToken,
                'user' => [
                    'id' => $newUser->id,
                    'email' => $newUser->email,
                    'role' => $newUser->role,
                    'status' => $newUser->status,
                    'business_name' => $newUser->business_name
                ]
            ], 201);
        } catch (\Throwable $error) {
            if ($claimedInvitationId !== null) {
                $this->releaseInvitation($claimedInvitationId, $userData['id'] ?? null);
            }

            $this->log(null, 'REGISTER_ERROR', '/api/register', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    /**
     * POST /api/register/check-business
     *
     * The first step of the admin signup wizard. It validates the business
     * details exactly like initiate does and reports a name conflict before the
     * visitor moves on, so a duplicate is caught on step 1 rather than after
     * the whole form and an OTP round-trip.
     */
    public function checkBusinessStep(Request $request)
    {
        $check = $this->businessStepValidation($request);

        if (! $check['ok']) {
            return $this->json([
                'success' => false,
                'error' => $check['error'],
                'code' => $check['code'],
            ] + (isset($check['existing_user']) ? ['existingUser' => $check['existing_user']] : []), $check['status']);
        }

        return $this->json([
            'success' => true,
            'available' => true,
            'business' => $check['business'],
        ]);
    }

    /**
     * POST /api/register/check-personal
     *
     * The second step of the wizard: name, email, phone and password are
     * checked for shape and for an existing account before the review page.
     */
    public function checkPersonalStep(Request $request)
    {
        $check = $this->personalStepValidation($request);

        if (! $check['ok']) {
            return $this->json([
                'success' => false,
                'error' => $check['error'],
                'code' => $check['code'],
            ], $check['status']);
        }

        return $this->json(['success' => true, 'available' => true]);
    }

    /**
     * POST /api/register/precheck
     *
     * The "Verify" button on the review page: re-checks every value that is
     * about to be sent to initiate. A conflict here is shown on the review page
     * and no OTP is sent.
     */
    public function precheckRegistration(Request $request)
    {
        $role = (string) $request->input('role', 'admin');

        if (! Roles::isRegisterable($role)) {
            return $this->json(['success' => false, 'error' => 'Role hii haijajiandikishwa.'], 400);
        }

        if ($role === 'admin') {
            $business = $this->businessStepValidation($request);

            if (! $business['ok']) {
                return $this->json([
                    'success' => false,
                    'step' => 'business',
                    'error' => $business['error'],
                    'code' => $business['code'],
                ] + (isset($business['existing_user']) ? ['existingUser' => $business['existing_user']] : []), $business['status']);
            }
        } else {
            $invitationCode = $this->normalizeInvitationCode($request->input('invitation_code'));

            if ($invitationCode === '') {
                return $this->json([
                    'success' => false,
                    'step' => 'invitation',
                    'error' => 'Msimbo wa mwaliko unahitajika.',
                    'code' => 'INVITATION_REQUIRED',
                ], 400);
            }

            $invitation = $this->resolveInvitation($invitationCode);

            if (isset($invitation['error'])) {
                return $this->json([
                    'success' => false,
                    'step' => 'invitation',
                    'error' => $invitation['error'],
                    'code' => $invitation['error_code'] ?? 'INVITATION_INVALID',
                ], $invitation['status'] ?? 400);
            }
        }

        $personal = $this->personalStepValidation($request);

        if (! $personal['ok']) {
            return $this->json([
                'success' => false,
                'step' => 'personal',
                'error' => $personal['error'],
                'code' => $personal['code'],
            ], $personal['status']);
        }

        return $this->json(['success' => true, 'verified' => true]);
    }

    /**
     * Validate the business fields a registration would write.
     *
     * @return array{ok:bool,business?:array<string,mixed>,error?:string,code?:string,status?:int,existing_user?:string}
     */
    private function businessStepValidation(Request $request): array
    {
        $businessName = trim((string) $request->input('business_name', ''));
        $businessLocation = trim((string) $request->input('business_location', ''));
        $businessType = trim((string) $request->input('business_type', ''));
        $businessDescription = trim((string) $request->input('business_description', ''));
        $businessStructure = trim((string) $request->input('business_structure', ''));

        if ($businessName === '' || mb_strlen($businessName) < 2 || mb_strlen($businessName) > 150) {
            return ['ok' => false, 'error' => 'Jina la biashara linahitajika (herufi 2 hadi 150).', 'code' => 'BUSINESS_NAME_REQUIRED', 'status' => 422];
        }

        if ($businessLocation === '' || mb_strlen($businessLocation) < 2 || mb_strlen($businessLocation) > 255) {
            return ['ok' => false, 'error' => 'Mahali pa biashara linahitajika.', 'code' => 'BUSINESS_LOCATION_REQUIRED', 'status' => 422];
        }

        if (! in_array($businessType, self::BUSINESS_TYPE_ALLOWED, true)) {
            return ['ok' => false, 'error' => 'Aina ya biashara inahitajika.', 'code' => 'BUSINESS_TYPE_REQUIRED', 'status' => 422];
        }

        if ($businessDescription !== '' && mb_strlen($businessDescription) > 1000) {
            return ['ok' => false, 'error' => 'Maelezo ya biashara yasizidi herufi 1000.', 'code' => 'BUSINESS_DESCRIPTION_TOO_LONG', 'status' => 422];
        }

        if ($businessStructure === '') {
            $businessStructure = 'non_branch';
        }

        if (! in_array($businessStructure, BranchContext::STRUCTURES, true)) {
            return ['ok' => false, 'error' => 'Aina ya biashara (matawi) si sahihi.', 'code' => 'BUSINESS_STRUCTURE_INVALID', 'status' => 422];
        }

        $parsed = $this->normalizeBranches($request->input('branches'));

        if (isset($parsed['error'])) {
            return ['ok' => false, 'error' => $parsed['error'], 'code' => 'BRANCHES_INVALID', 'status' => 422];
        }

        $branches = $parsed['branches'];

        if ($businessStructure === 'branch' && count($branches) === 0) {
            return ['ok' => false, 'error' => 'Biashara yenye matawi inahitaji tawi moja au zaidi.', 'code' => 'BRANCH_REQUIRED', 'status' => 422];
        }

        $conflict = $this->businessNameConflict($businessName);

        if ($conflict !== null) {
            return [
                'ok' => false,
                'error' => 'Jina la biashara tayari limeshasajiliwa.',
                'code' => 'BUSINESS_NAME_TAKEN',
                'status' => 409,
                'existing_user' => $conflict,
            ];
        }

        return [
            'ok' => true,
            'business' => [
                'business_name' => $businessName,
                'business_location' => $businessLocation,
                'business_type' => $businessType,
                'business_description' => $businessDescription,
                'business_structure' => $businessStructure,
                'branches' => $branches,
            ],
        ];
    }

    /**
     * Validate the personal fields (and account uniqueness) for a signup.
     *
     * @return array{ok:bool,error?:string,code?:string,status?:int}
     */
    private function personalStepValidation(Request $request): array
    {
        $role = (string) $request->input('role', 'admin');
        $fullName = trim((string) $request->input('full_name', ''));
        $email = trim((string) $request->input('email', ''));
        $phone = trim((string) $request->input('phone', ''));
        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('confirm_password', $request->input('confirmPassword', ''));

        if ($confirmPassword === '') {
            return ['ok' => false, 'error' => 'Hakiki nenosiri lako.', 'code' => 'PASSWORD_CONFIRMATION_REQUIRED', 'status' => 422];
        }

        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            return ['ok' => false, 'error' => 'Jina kamili linahitajika.', 'code' => 'FULL_NAME_REQUIRED', 'status' => 422];
        }

        if (! preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
            return ['ok' => false, 'error' => 'Barua pepe si sahihi.', 'code' => 'EMAIL_INVALID', 'status' => 422];
        }

        if ($phone !== '' && (mb_strlen($phone) > 30 || ! preg_match('/^[0-9+\-\s()]*$/', $phone))) {
            return ['ok' => false, 'error' => 'Namba ya simu si sahihi.', 'code' => 'PHONE_INVALID', 'status' => 422];
        }

        if (mb_strlen($password) < 6) {
            return ['ok' => false, 'error' => 'Nenosiri lazima liwe na herufi 6 au zaidi.', 'code' => 'PASSWORD_TOO_SHORT', 'status' => 422];
        }

        if ($password !== $confirmPassword) {
            return ['ok' => false, 'error' => 'Nenosiri hazifanani.', 'code' => 'PASSWORD_MISMATCH', 'status' => 422];
        }

        if ($this->registrationAlreadyExists($email, $role)) {
            return ['ok' => false, 'error' => 'Akaunti na barua pepe hii tayari ipo.', 'code' => 'EMAIL_TAKEN', 'status' => 409];
        }

        return ['ok' => true];
    }

    /**
     * The existing signup error when a business name is already in use by an
     * admin or seller. Returns the first existing email, or null when free.
     */
    private function businessNameConflict(string $businessName): ?string
    {
        $business = Business::whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($businessName))])->first();

        if ($business) {
            return '';
        }

        $existing = User::select('id', 'email', 'role')
            ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($businessName))])
            ->whereIn('role', ['admin', 'seller'])
            ->get();

        if ($existing && $existing->count() > 0) {
            return (string) ($existing[0]->email ?? '');
        }

        return null;
    }

    public function registerInitiate(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = trim((string) $request->input('email'));
        $password = $request->input('password');
        $role = $request->input('role');
        $full_name = $request->input('full_name');
        $phone = $request->input('phone');
        $business_name = $request->input('business_name');
        $business_location = $request->input('business_location');
        $business_type = $request->input('business_type');
        $business_description = $request->input('business_description');
        $business_structure = $request->input('business_structure');
        $branchesInput = $request->input('branches');
        $seller_business_id = $request->input('business_id');
        $seller_branch_id = $request->input('branch_id');
        $language = $request->input('language');
        $invitationCode = $this->normalizeInvitationCode($request->input('invitation_code'));
        $invitation = null;

        if (!$email || !$password || !$role) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Missing required fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email, password na role zinahitajika'], 400);
        }

        // Self-service signup may ONLY produce an ordinary role. Platform
        // roles (super_admin, legacy system_admin) are granted exclusively by
        // a technician updating the database - never through the API.
        if (!Roles::isRegisterable($role)) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Role not self-registerable', 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Role hii haijajiandikishwa. Chagua client, seller au admin.'], 400);
        }

        if ($role === 'admin' && (!$business_type || !in_array($business_type, self::BUSINESS_TYPE_ALLOWED, true))) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Invalid business_type', 'email' => $email, 'role' => $role, 'business_type' => $business_type], $ip, 'failed');
            return $this->json(['error' => 'Aina ya biashara inahitajika'], 400);
        }

        // Branch/non-branch choice. `business_type` above is the industry; this
        // is a separate field so the existing registration rules keep working.
        $business_structure = is_string($business_structure) ? trim($business_structure) : '';
        $branches = [];

        if ($role === 'admin') {
            if ($business_structure !== '' && ! in_array($business_structure, BranchContext::STRUCTURES, true)) {
                return $this->json(['error' => 'Aina ya biashara (matawi) si sahihi'], 400);
            }
            $business_structure = $business_structure !== '' ? $business_structure : 'non_branch';

            $parsed = $this->normalizeBranches($branchesInput);
            if (isset($parsed['error'])) {
                return $this->json(['error' => $parsed['error']], 400);
            }
            $branches = $parsed['branches'];

            if ($business_structure === 'branch' && count($branches) === 0) {
                return $this->json(['error' => 'Biashara yenye matawi inahitaji tawi moja au zaidi'], 400);
            }
        } else {
            $business_structure = 'non_branch';
            $branches = [];
        }

        if ($role === 'seller') {
            if ($invitationCode === '') {
                return $this->json([
                    'error' => 'Usajili wa muuzaji unahitaji msimbo wa mwaliko kutoka kwa msimamizi.',
                    'code' => 'INVITATION_REQUIRED',
                ], 400);
            }

            $invitation = $this->resolveInvitation($invitationCode);

            if (isset($invitation['error'])) {
                $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', [
                    'reason' => 'Invalid seller invitation',
                    'code' => $invitation['error_code'] ?? 'INVITATION_INVALID',
                    'email' => $email,
                ], $ip, 'failed');

                return $this->json([
                    'error' => $invitation['error'],
                    'code' => $invitation['error_code'] ?? 'INVITATION_INVALID',
                ], $invitation['status'] ?? 400);
            }

            $business_name = $invitation['business_name'];
            $business_structure = $invitation['business_structure'];
            $seller_business_id = $invitation['business_id'];
            $seller_branch_id = $invitation['branch_id'];
            $business_location = $invitation['business_location'] ?? $business_location;
        }

        if (strlen($password) < 6) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Password too short', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Nenosiri lazima liwe na herufi 6 au zaidi'], 400);
        }

        $emailRegex = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
        if (!preg_match($emailRegex, $email)) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Invalid email format', 'email' => $email], $ip, 'failed');
            return $this->json(['error' => 'Barua pepe si sahihi'], 400);
        }

        try {
            $key = $this->registrationOtpKey($email, $role);
            $pendingEntry = Cache::get($key);

            if ($pendingEntry && (int) (microtime(true) * 1000) - $pendingEntry['lastSentAt'] < self::OTP_RESEND_COOLDOWN_MS) {
                $waitSec = (int) ceil((self::OTP_RESEND_COOLDOWN_MS - ((int) (microtime(true) * 1000) - $pendingEntry['lastSentAt'])) / 1000);
                $this->log(null, 'REGISTER_INITIATE_RATE_LIMITED', '/api/register/initiate', ['email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => "Subiri sekunde {$waitSec} kabla ya kujaribu tena."], 429);
            }

            if ($this->registrationAlreadyExists($email, $role)) {
                $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Account already exists', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Akaunti na barua pepe hii tayari ipo'], 400);
            }

            if ($role === 'admin' || $role === 'seller') {
                if (!$business_name) {
                    $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Business name required', 'email' => $email, 'role' => $role], $ip, 'failed');
                    return $this->json(['error' => 'Jina la biashara linahitajika'], 400);
                }

                $adminUsers = User::select('id', 'email', 'role', 'status')
                    ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                    ->whereIn('role', Roles::BUSINESS_OWNERS)
                    ->where('status', 'approved')
                    ->get();

                $hasApprovedAdmin = $adminUsers && $adminUsers->count() > 0;

                if ($role === 'seller' && !$hasApprovedAdmin) {
                    $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', [
                        'reason' => 'No approved admin for business',
                        'business_name' => $business_name,
                        'email' => $email,
                        'role' => $role
                    ], $ip, 'failed');
                    return $this->json([
                        'error' => 'Biashara hii haina msimamizi. Tafadhali jisajili kama msimamizi kwanza.'
                    ], 400);
                }

                if ($role === 'admin') {
                    $conflict = $this->businessNameConflict($business_name);

                    if ($conflict !== null) {
                        $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', [
                            'reason' => 'Business name already taken',
                            'business_name' => $business_name,
                            'existing_user' => $conflict
                        ], $ip, 'failed');
                        return $this->json([
                            'error' => 'Jina la biashara tayari limeshasajiliwa',
                            'existingUser' => $conflict
                        ], 400);
                    }
                }
            }

            $otpCode = $this->generateOtpCode();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            Cache::put($key, [
                'otp' => $otpCode,
                'passwordHash' => $passwordHash,
                'payload' => [
                    'email' => $email,
                    'role' => $role,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'business_name' => $business_name,
                    'business_location' => $business_location,
                    'business_type' => $business_type,
                    'business_description' => $business_description,
                    'business_structure' => $business_structure,
                    'branches' => $branches,
                    'business_id' => $seller_business_id,
                    'branch_id' => $seller_branch_id,
                    'invitation_id' => $invitation['invitation_id'] ?? null,
                    'invitation_code' => $invitation['code'] ?? null,
                    'language' => $language
                ],
                'expiresAt' => (int) (microtime(true) * 1000) + self::OTP_EXPIRY_MS,
                'lastSentAt' => (int) (microtime(true) * 1000),
                'attempts' => 0,
                'isUsed' => false,
                'createdAt' => $this->isoNow()
            ], now()->addMinutes(10));

            $emailResult = $this->sendRegistrationOtpEmail($email, $full_name, $otpCode);

            $this->log(null, 'REGISTER_INITIATE_SUCCESS', '/api/register/initiate', [
                'email' => $email,
                'role' => $role,
                'email_sent' => $emailResult['success']
            ], $ip, 'success');

            $response = [
                'success' => true,
                'requiresOtp' => true,
                'email' => $email,
                'role' => $role,
                'expiresIn' => '10 minutes',
                'message' => 'Msimbo wa uthibitisho umetumwa kwenye barua pepe yako.',
                'email_sent' => $emailResult['success']
            ];
            if ($emailResult['success'] === false) {
                $response['warning'] = 'Msimbo haukuweza kutumwa kwenye barua pepe. Tumia "Tuma msimbo tena" kujaribu tena.';
            }

            return $this->json($response, 200);
        } catch (\Throwable $error) {
            $this->log(null, 'REGISTER_INITIATE_ERROR', '/api/register/initiate', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = trim((string) $request->input('email'));
        $role = $request->input('role');
        $otp = $request->input('otp');

        if (!$email || !$role || !$otp) {
            $this->log(null, 'REGISTER_VERIFY_FAILED', '/api/register/verify-otp', ['reason' => 'Missing fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email, role na msimbo zinahitajika'], 400);
        }

        try {
            $key = $this->registrationOtpKey($email, $role);
            $entry = Cache::get($key);

            if (!$entry || $entry['isUsed']) {
                $this->log(null, 'REGISTER_VERIFY_FAILED', '/api/register/verify-otp', ['reason' => 'No pending registration', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Msimbo si sahihi au umeisha muda wake'], 400);
            }

            if ((int) (microtime(true) * 1000) > $entry['expiresAt']) {
                Cache::forget($key);
                $this->log(null, 'REGISTER_VERIFY_FAILED', '/api/register/verify-otp', ['reason' => 'OTP expired', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Msimbo umeisha muda wake. Tafadhali jisajili tena.'], 400);
            }

            if ($entry['otp'] !== trim((string) $otp)) {
                $entry['attempts'] += 1;
                if ($entry['attempts'] >= self::OTP_MAX_ATTEMPTS) {
                    Cache::forget($key);
                } else {
                    Cache::put($key, $entry, now()->addMinutes(10));
                }
                $this->log(null, 'REGISTER_VERIFY_FAILED', '/api/register/verify-otp', ['reason' => 'Wrong OTP', 'email' => $email, 'role' => $role, 'attempts' => $entry['attempts']], $ip, 'failed');
                return $this->json(['error' => 'Msimbo si sahihi'], 400);
            }

            if ($this->registrationAlreadyExists($email, $role)) {
                Cache::forget($key);
                $this->log(null, 'REGISTER_VERIFY_FAILED', '/api/register/verify-otp', ['reason' => 'Account already exists', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Akaunti na barua pepe hii tayari ipo'], 400);
            }

            $payload = $entry['payload'];

            $registrationToken = JwtToken::encode([
                'email' => $payload['email'],
                'role' => $payload['role'],
                'passwordHash' => $entry['passwordHash'],
                'full_name' => $payload['full_name'] ?: null,
                'phone' => $payload['phone'] ?: null,
                'business_name' => $payload['business_name'] ?: null,
                'business_location' => $payload['business_location'] ?: null,
                'business_type' => $payload['business_type'] ?: null,
                'business_description' => $payload['business_description'] ?: null,
                'business_structure' => $payload['business_structure'] ?: 'non_branch',
                'branches' => $payload['branches'] ?? [],
                'business_id' => $payload['business_id'] ?? null,
                'branch_id' => $payload['branch_id'] ?? null,
                'invitation_id' => $payload['invitation_id'] ?? null,
                'invitation_code' => $payload['invitation_code'] ?? null,
                'language' => $payload['language'] ?: 'sw',
                'type' => 'registration_verified',
                'verifiedAt' => (int) (microtime(true) * 1000),
                'exp' => time() + 600
            ]);

            Cache::forget($key);

            $this->log(null, 'REGISTER_OTP_VERIFIED', '/api/register/verify-otp', [
                'email' => $email,
                'role' => $role,
                'email_verified' => true
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'verified' => true,
                'message' => 'Msimbo umehakikiwa kikamilifu!',
                'registrationToken' => $registrationToken,
                'email' => $email,
                'role' => $role,
                'expiresIn' => '10 minutes'
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'REGISTER_VERIFY_ERROR', '/api/register/verify-otp', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function resendOtp(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = trim((string) $request->input('email'));
        $role = $request->input('role');

        if (!$email || !$role) {
            $this->log(null, 'REGISTER_RESEND_FAILED', '/api/register/resend-otp', ['reason' => 'Missing fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email na role zinahitajika'], 400);
        }

        try {
            $key = $this->registrationOtpKey($email, $role);
            $entry = Cache::get($key);

            if (!$entry || $entry['isUsed']) {
                $this->log(null, 'REGISTER_RESEND_FAILED', '/api/register/resend-otp', ['reason' => 'No pending registration', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Hakuna usajili unaosubiri uthibitisho. Tafadhali anza upya.'], 400);
            }

            $cooldownLeft = $entry['lastSentAt'] + self::OTP_RESEND_COOLDOWN_MS - (int) (microtime(true) * 1000);
            if ($cooldownLeft > 0) {
                $this->log(null, 'REGISTER_RESEND_FAILED', '/api/register/resend-otp', ['reason' => 'Cooldown', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => "Subiri sekunde " . (int) ceil($cooldownLeft / 1000) . " kabla ya kutuma tena."], 429);
            }

            $entry['otp'] = $this->generateOtpCode();
            $entry['lastSentAt'] = (int) (microtime(true) * 1000);
            $entry['expiresAt'] = (int) (microtime(true) * 1000) + self::OTP_EXPIRY_MS;
            $entry['attempts'] = 0;
            Cache::put($key, $entry, now()->addMinutes(10));

            $emailResult = $this->sendRegistrationOtpEmail($email, $entry['payload']['full_name'], $entry['otp']);

            $this->log(null, 'REGISTER_RESEND_SUCCESS', '/api/register/resend-otp', [
                'email' => $email,
                'role' => $role,
                'email_sent' => $emailResult['success']
            ], $ip, 'success');

            $response = [
                'success' => true,
                'message' => 'Msimbo mpya umetumwa kwenye barua pepe yako.',
                'expiresIn' => '10 minutes',
                'email_sent' => $emailResult['success']
            ];
            if ($emailResult['success'] === false) {
                $response['warning'] = 'Msimbo haukuweza kutumwa kwenye barua pepe. Tumia "Tuma msimbo tena" kujaribu tena.';
            }

            return $this->json($response, 200);
        } catch (\Throwable $error) {
            $this->log(null, 'REGISTER_RESEND_ERROR', '/api/register/resend-otp', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = trim((string) $request->input('email'));
        $password = $request->input('password');
        $role = $request->input('role');
        $language = $request->input('language');
        $theme = $request->input('theme');
        // True when the client's theme is a choice the visitor just made in
        // the picker (see DMTheme.wasPicked on the web / ThemeProvider.picked
        // in the app), as opposed to the value simply restored from storage.
        $themeExplicit = filter_var($request->input('theme_explicit'), FILTER_VALIDATE_BOOLEAN);

        if (!$email || !$password || !$role) {
            $this->log(null, 'LOGIN_FAILED', '/api/login', ['reason' => 'Missing fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email, password na role zinahitajika'], 400);
        }

        try {
            // Every row sharing this email is fetched and the FIRST one that
            // satisfies the requested role is used (Roles::loginSatisfies):
            // the msimamizi portal requests 'admin', and a promoted platform
            // super admin (super_admin, legacy system_admin) must keep signing
            // in through that same portal. Every other role requires an exact
            // match, so this never widens what a seller/customer token can
            // reach.
            //
            // The row must be chosen BY ROLE, never taken as the arbitrary
            // first row for the email: one email can own several accounts
            // (e.g. an admin and a seller of the same business), and the REST
            // layer returns them in id order — so a blind ->first() handed a
            // seller row to the admin portal, which then rejected the login
            // with "Akaunti haipo au role si sahihi" even though the admin
            // account existed and its password was correct.
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
                ->get()
                ->first(fn ($candidate) => Roles::loginSatisfies($candidate->role, $role));

            if (!$user) {
                $this->log(null, 'LOGIN_FAILED', '/api/login', ['reason' => 'Account not found or role mismatch', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Akaunti haipo au role si sahihi'], 401);
            }

            $isValid = password_verify($password, $user->password);

            if (!$isValid) {
                $this->log($user->id, 'LOGIN_FAILED', '/api/login', ['reason' => 'Invalid password', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Password si sahihi'], 401);
            }

            // A blocked business takes every account beneath it out of service,
            // including accounts created after the block. Checked before the
            // per-user approval rules so the reason shown is the real one.
            $accountBusinessId = $user->business_id ?: BusinessResolver::idForUser($user->id);
            if (BusinessBlock::isBlocked($accountBusinessId)) {
                $this->log($user->id, 'LOGIN_FAILED', '/api/login', [
                    'reason' => 'Business blocked',
                    'business_id' => $accountBusinessId,
                ], $ip, 'failed');
                return $this->json([
                    'error' => 'Biashara yako imezuiwa. Tafadhali wasiliana na msimamizi wa mfumo.',
                    'code' => 'BUSINESS_BLOCKED',
                ], 403);
            }

            if ($user->role === 'seller' && $user->status !== 'approved') {
                $this->log($user->id, 'LOGIN_FAILED', '/api/login', ['reason' => 'Account not approved', 'status' => $user->status], $ip, 'failed');
                return $this->json([
                    'error' => 'Akaunti yako bado haijaidhinishwa. Subiri msimamizi akuidhinishe.'
                ], 401);
            }

            if ($user->status !== 'approved' && $user->role !== 'seller') {
                $this->log($user->id, 'LOGIN_FAILED', '/api/login', ['reason' => 'Account not approved', 'status' => $user->status], $ip, 'failed');
                return $this->json(['error' => 'Akaunti bado haijaidhinishwa.'], 401);
            }

            if ($language) {
                $language = $this->normalizeLanguage($language);
                if ($language) {
                    User::where('id', $user->id)->update(['language' => $language]);
                    $user->language = $language;
                }
            }

            // Theme preference synchronization (same shape as the language
            // preference above, two ways in, one truth out):
            //   - a theme the visitor picked in the picker just before
            //     signing in (theme_explicit) is the freshest expression of
            //     intent, so it wins over the stored value and is persisted;
            //   - otherwise the account's established VALID preference wins,
            //     which is what carries a choice made on the phone or another
            //     browser into this one — a NON-DEFAULT stored theme can only
            //     exist because somebody chose it explicitly (the column
            //     default and the migration backfill both write White
            //     Professional), so it is never overwritten on login;
            //   - the browser value is also adopted when the account has no
            //     valid stored theme yet.
            // Neither present -> White Professional (reported, but not
            // written, so a later explicit choice can still be adopted).
            // Every step is guarded: a failed preference sync must never fail
            // the login itself, and the visitor keeps their local theme either
            // way.
            // The conflict rule itself lives in ThemePreference::resolve so
            // the website and the app are covered by the same unit test.
            $resolved = ThemePreference::resolve($user->preferred_theme ?? null, $theme, $themeExplicit);
            $preferredTheme = $resolved['theme'];

            if ($resolved['persist']) {
                try {
                    User::where('id', $user->id)->update(['preferred_theme' => $preferredTheme]);
                } catch (\Throwable $themeError) {
                    // Column missing (migration not run yet) or API unavailable:
                    // the answer below still carries the visitor's choice, so the
                    // session keeps it and login proceeds normally.
                    $this->log($user->id, 'LOGIN_THEME_SYNC_SKIPPED', '/api/login', ['error' => $themeError->getMessage()], $ip, 'failed');
                }
            }

            $now = $this->isoNow();
            User::where('id', $user->id)->update([
                'is_online' => true,
                'last_seen' => $now,
                'updated_at' => $now
            ]);

            // Multi-branch: the token carries the business structure and, for
            // a seller, their assigned branch. A branch-business administrator
            // starts UNSELECTED and must choose a branch (or cross-branch)
            // through POST /api/branch-context before operational endpoints
            // return any rows.
            $structure = BranchContext::structure($accountBusinessId ?: null);
            $branch = BranchContext::resolve(
                $accountBusinessId ?: null,
                $user->role,
                $user->id,
                null,
                null,
                $structure
            );

            // A seller is bound to one branch. If that branch is missing,
            // inactive, or belongs to another business, login is refused rather
            // than silently falling back to another branch or to a business-wide
            // view. Non-branch sellers and administrators are unaffected.
            if ($user->role === 'seller' && $structure === 'branch' && $branch['context'] !== BranchContext::INDIVIDUAL) {
                $this->log($user->id, 'LOGIN_FAILED', '/api/login', [
                    'reason' => 'Seller branch unavailable',
                    'business_id' => $accountBusinessId,
                ], $ip, 'failed');

                return $this->json([
                    'error' => 'Tawi ulilopewa halipatikani au limezimwa. Wasiliana na msimamizi wa biashara yako.',
                    'code' => 'BRANCH_UNAVAILABLE',
                ], 403);
            }

            $branchRow = $branch['branch_id']
                ? Branch::where('id', $branch['branch_id'])->first()
                : null;

            $token = JwtToken::encode([
                'userId' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'business_id' => $accountBusinessId ?: null,
                'business_structure' => $structure,
                'branch_id' => $branch['branch_id'],
                'branch_context' => $branch['context'],
            ]);

            $this->log($user->id, 'LOGIN_SUCCESS', '/api/login', [
                'role' => $user->role,
                'business_name' => $user->business_name,
                'is_online' => true
            ], $ip, 'success');

            // Monitoring (Phase 4A/5): open an auth session keyed by a
            // server-derived reference (sha256 of the fresh token - the token
            // itself is never stored). Best effort; login never fails on it.
            \App\Services\Monitoring\ActivityTracker::startSession(
                $user->id,
                $token,
                $request->userAgent(),
                $request->ip()
            );

            return $this->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'full_name' => $user->full_name,
                    'business_name' => $user->business_name,
                    'business_id' => $accountBusinessId ?: null,
                    'business_structure' => $structure,
                    'branch_id' => $branch['branch_id'],
                    'branch_name' => $branchRow->branch_name ?? null,
                    'branch_location' => $branchRow->location ?? null,
                    'branch_context' => $branch['context'],
                    'needs_branch_selection' => $structure === 'branch' && $branch['context'] === BranchContext::UNSELECTED,
                    'business_location' => $user->business_location,
                    'business_logo_url' => $user->business_logo_url ?: null,
                    'business_latitude' => $user->business_latitude ?: null,
                    'business_longitude' => $user->business_longitude ?: null,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'language' => $user->language ?: 'sw',
                    'preferred_theme' => $preferredTheme,
                    'is_online' => true,
                    'last_seen' => $now
                ]
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'LOGIN_ERROR', '/api/login', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    /**
     * Invitation codes are copy/pasted between devices. Accept spaces, dashes
     * and lower case, but always compare/store one canonical form.
     */
    private function normalizeInvitationCode($code): string
    {
        return SellerInvitationService::normalizeCode($code);
    }

    /**
     * Resolve an active invitation and re-check the business, approved admin
     * and branch against the database. Client-supplied business/branch ids are
     * never consulted.
     *
     * @return array{invitation_id?:string,code?:string,business_id?:string,business_name?:string,business_structure?:string,business_location?:?string,branch_id?:?string,branch_name?:?string,error?:string,error_code?:string,status?:int}
     */
    private function resolveInvitation(string $code): array
    {
        return SellerInvitationService::resolve($code);
    }

    /**
     * Atomically-enough single-use claim for the REST-backed model. The update
     * is filtered to active rows; the follow-up read verifies that this exact
     * user id won, so two simultaneous registrations cannot both proceed.
     */
    private function claimInvitation(string $invitationId, string $userId): bool
    {
        return SellerInvitationService::claim($invitationId, $userId);
    }

    private function releaseInvitation(string $invitationId, ?string $userId): void
    {
        SellerInvitationService::release($invitationId, $userId);
    }

    /**
     * Validate and normalise the branch rows sent at registration.
     *
     * Each branch requires a name and a location; coordinates are optional
     * (a denied GPS permission must not silently store 0,0 - missing stays
     * null and the administrator can capture the pin later).
     *
     * @return array{branches?: list<array<string,mixed>>, error?: string}
     */
    private function normalizeBranches($input): array
    {
        if ($input === null || $input === '') {
            return ['branches' => []];
        }

        if (! is_array($input)) {
            return ['error' => 'Taarifa za matawi si sahihi'];
        }

        $branches = [];

        foreach ($input as $raw) {
            if (! is_array($raw)) {
                return ['error' => 'Taarifa za tawi si sahihi'];
            }

            $name = trim((string) ($raw['branch_name'] ?? $raw['name'] ?? ''));
            $location = trim((string) ($raw['location'] ?? $raw['branch_location'] ?? ''));

            if ($name === '') {
                return ['error' => 'Jina la kila tawi linahitajika'];
            }
            if ($location === '') {
                return ['error' => 'Mahali pa kila tawi linahitajika'];
            }

            $latitude = $raw['latitude'] ?? null;
            $longitude = $raw['longitude'] ?? null;

            if ($latitude !== null && $latitude !== '') {
                if (! is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90) {
                    return ['error' => 'Latitudo ya tawi si sahihi'];
                }
                $latitude = (float) $latitude;
            } else {
                $latitude = null;
            }

            if ($longitude !== null && $longitude !== '') {
                if (! is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180) {
                    return ['error' => 'Longitudo ya tawi si sahihi'];
                }
                $longitude = (float) $longitude;
            } else {
                $longitude = null;
            }

            $branches[] = [
                'branch_name' => $name,
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        return ['branches' => $branches];
    }

    /**
     * Create the parent business and every branch in ONE database call.
     *
     * The SQL function register_business_with_branches runs in a single
     * transaction, so a missing/invalid branch rolls the business row back too.
     * For a non-branch business (or a deployment that has not applied the
     * migration yet) the proven BusinessResolver path remains the fallback.
     *
     * @return array{business_id?: ?string, branches?: array, error?: string}
     */
    private function createBusinessWithBranches(string $name, string $structure, array $branches, array $attributes): array
    {
        $key = BusinessResolver::key($name);

        if ($key === '') {
            return ['error' => 'Jina la biashara linahitajika'];
        }

        if (! in_array($structure, BranchContext::STRUCTURES, true)) {
            $structure = 'non_branch';
        }

        if ($structure === 'branch' && count($branches) === 0) {
            return ['error' => 'Tawi moja au zaidi linahitajika'];
        }

        try {
            $result = Supabase::callFunction('register_business_with_branches', [
                'p_business_name' => trim($name),
                'p_legacy_name_key' => $key,
                'p_business_structure' => $structure,
                'p_business_type' => $attributes['business_type'] ?? null,
                'p_business_description' => $attributes['business_description'] ?? null,
                'p_business_location' => $attributes['business_location'] ?? null,
                'p_branches' => array_values($branches),
            ]);

            if (isset($result[0]) && is_array($result[0]) && ! isset($result['business'])) {
                $result = $result[0];
            }

            $businessId = $result['business']['id'] ?? null;

            if ($businessId) {
                return [
                    'business_id' => (string) $businessId,
                    'branches' => is_array($result['branches'] ?? null) ? $result['branches'] : [],
                ];
            }
        } catch (\Throwable $e) {
            // A branch business MUST be atomic: never fall back to a business
            // row without its branches.
            if ($structure === 'branch') {
                return ['error' => $e->getMessage()];
            }
        }

        $business = BusinessResolver::resolveOrCreate($name, array_filter([
            'business_type' => $attributes['business_type'] ?? null,
            'business_description' => $attributes['business_description'] ?? null,
            'business_location' => $attributes['business_location'] ?? null,
            'business_structure' => 'non_branch',
        ], fn ($v) => $v !== null && $v !== ''));

        if (! $business) {
            return ['error' => 'Biashara haikuweza kuundwa'];
        }

        return ['business_id' => (string) $business->id, 'branches' => []];
    }

    private function registrationOtpKey($email, $role): string
    {
        return 'registration_otp:' . strtolower(trim((string) $email)) . ':' . ($role ?: '');
    }

    private function generateOtpCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function registrationAlreadyExists($email, $role): bool
    {
        $existing = User::select('id')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('role', $role)->get();
        return $existing && $existing->count() > 0;
    }

    private function sendRegistrationOtpEmail($to, $fullName, $otpCode): array
    {
        $subject = 'Msimbo wa Uthibitisho wa Usajili - DukaMkononi / Registration Code';
        $safeName = htmlspecialchars((string) ($fullName ?: 'Mteja'), ENT_QUOTES, 'UTF-8');
        $html = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #2c3e50;">Habari ' . $safeName . '!</h2>
                <p>Asante kwa kujisajili kwenye DukaMkononi.</p>
                <p>Msimbo wako wa uthibitisho wa barua pepe ni:</p>
                <div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;">
                    <h1 style="font-size: 36px; color: #2c3e50; letter-spacing: 8px; margin: 0;">' . $otpCode . '</h1>
                </div>
                <p><strong>Msimbo huu utaisha muda wake ndani ya dakika 10.</strong></p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="font-size: 12px; color: #7f8c8d;">
                    DukaMkononi Team<br>
                    Hii ni barua pepe ya kiotomatiki. Tafadhali usijibu.
                </p>
            </div>
        ';
        return $this->sendEmailWithRetry($to, $subject, $html);
    }

    private function sendEmailWithRetry($to, $subject, $html, $maxRetries = 2): array
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Mail::html($html, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);

                    $replyTo = config('mail.reply_to.address');
                    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                        $m->replyTo($replyTo, (string) config('mail.reply_to.name'));
                    }
                });
                return ['success' => true, 'messageId' => '', 'attempt' => $attempt];
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('Registration email attempt failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxRetries) {
                    usleep($attempt * 1000000);
                }
            }
        }

        Log::error('Registration email failed after retries', [
            'to' => $to,
            'subject' => $subject,
            'attempts' => $maxRetries,
            'error' => $lastError ? $lastError->getMessage() : 'Unknown error',
        ]);

        return ['success' => false, 'error' => $lastError ? $lastError->getMessage() : 'Unknown error', 'attempts' => $maxRetries];
    }
}