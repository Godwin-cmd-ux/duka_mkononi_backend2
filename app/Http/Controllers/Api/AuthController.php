<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\BusinessBlock;
use App\Services\BusinessResolver;
use App\Services\JwtToken;
use App\Services\Supabase;
use App\Services\ThemePreference;
use Firebase\JWT\ExpiredException;
use UnexpectedValueException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $email = $verified->email;
        $role = $verified->role;
        $passwordHash = $verified->passwordHash;

        // Defense in depth: even a signed verification token may only carry
        // a self-registerable role (the allow-list was enforced at initiate).
        if (!\App\Services\Roles::isRegisterable($role)) {
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
        $language = $verified->language ?? 'sw';

        if ($request->input('email') && strtolower(trim($request->input('email'))) !== strtolower(trim($email))) {
            $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Email mismatch with verified token'], $ip, 'failed');
            return $this->json(['error' => 'Barua pepe hailingani na iliyothibitishwa.'], 400);
        }

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
                $assignment = $this->resolveSellerAssignment(
                    is_string($seller_business_id) ? $seller_business_id : null,
                    is_string($business_name) ? $business_name : null,
                    is_string($seller_branch_id) ? $seller_branch_id : null
                );

                if (isset($assignment['error'])) {
                    $this->log(null, 'REGISTER_FAILED', '/api/register', [
                        'reason' => 'Invalid seller business/branch',
                        'email' => $email,
                    ], $ip, 'failed');

                    return $this->json(['error' => $assignment['error'], 'code' => $assignment['code'] ?? 'BRANCH_REQUIRED'], 400);
                }

                $business_name = $assignment['business_name'];
                $seller_business_id = $assignment['business_id'];
                $seller_branch_id = $assignment['branch_id'];
                $business_structure = $assignment['business_structure'];
            }

            if ($role === 'admin' || $role === 'seller') {
                if (!$business_name) {
                    $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Business name required', 'email' => $email, 'role' => $role], $ip, 'failed');
                    return $this->json(['error' => 'Jina la biashara linahitajika'], 400);
                }

                $adminUsers = User::select('id', 'email', 'role', 'status')
                    ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                    ->whereIn('role', \App\Services\Roles::BUSINESS_OWNERS)
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
            $this->log(null, 'REGISTER_ERROR', '/api/register', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function registerInitiate(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = $request->input('email');
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

        if (!$email || !$password || !$role) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Missing required fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email, password na role zinahitajika'], 400);
        }

        // Self-service signup may ONLY produce an ordinary role. Platform
        // roles (super_admin, legacy system_admin) are granted exclusively by
        // a technician updating the database - never through the API.
        if (!\App\Services\Roles::isRegisterable($role)) {
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

        // A seller joins an existing, eligible business and (for a branch
        // business) exactly one active branch. The values are accepted here for
        // the OTP round-trip but re-validated against the database when the
        // account is actually created. They must NOT be cleared first: the
        // branch selector sends business_id + branch_id, and discarding them
        // here made every branch seller fail with BRANCH_REQUIRED.
        if ($role === 'seller') {
            $assignment = $this->resolveSellerAssignment(
                is_string($seller_business_id) ? $seller_business_id : null,
                is_string($business_name) ? $business_name : null,
                is_string($seller_branch_id) ? $seller_branch_id : null
            );

            if (isset($assignment['error'])) {
                $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', [
                    'reason' => 'Invalid seller business/branch',
                    'email' => $email,
                ], $ip, 'failed');

                return $this->json(['error' => $assignment['error'], 'code' => $assignment['code'] ?? 'BRANCH_REQUIRED'], 400);
            }

            $business_name = $assignment['business_name'];
            $business_structure = $assignment['business_structure'];
            $seller_business_id = $assignment['business_id'];
            $seller_branch_id = $assignment['branch_id'];
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
                    ->whereIn('role', \App\Services\Roles::BUSINESS_OWNERS)
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
                    $existingBusiness = User::select('id', 'email', 'role')
                        ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                        ->whereIn('role', ['admin', 'seller'])
                        ->get();

                    if ($existingBusiness && $existingBusiness->count() > 0) {
                        $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', [
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

        $email = $request->input('email');
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

        $email = $request->input('email');
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

        $email = $request->input('email');
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
                ->first(fn ($candidate) => \App\Services\Roles::loginSatisfies($candidate->role, $role));

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
     * Resolve the business a seller is joining and, for a branch business,
     * verify the selected branch belongs to that business and is active.
     *
     * The seller never creates a business. When an id is supplied it must
     * already exist and have an approved administrator; the legacy name-based
     * flow is kept for clients that do not send an id yet, and is subject to
     * the same branch rule so it cannot bypass branch assignment.
     *
     * @return array{business_id?:string,business_name?:string,business_structure?:string,branch_id?:?string,error?:string,code?:string}
     */
    private function resolveSellerAssignment(?string $businessId, ?string $businessName, ?string $branchId): array
    {
        $business = null;

        if ($businessId !== null && trim($businessId) !== '') {
            try {
                $business = Business::where('id', trim($businessId))->first();
            } catch (\Throwable $e) {
                $business = null;
            }
        }

        if (! $business && $businessName !== null && trim($businessName) !== '') {
            $business = BusinessResolver::findByName(trim($businessName));
        }

        if (! $business) {
            return ['error' => 'Biashara hii haipatikani kwenye mfumo.', 'code' => 'BUSINESS_NOT_FOUND'];
        }

        $canonicalName = trim((string) ($business->business_name ?? ''));

        $hasApprovedAdmin = User::where('business_id', (string) $business->id)
            ->whereIn('role', \App\Services\Roles::BUSINESS_OWNERS)
            ->where('status', 'approved')
            ->count() > 0;

        if (! $hasApprovedAdmin) {
            $hasApprovedAdmin = User::whereRaw('LOWER(business_name) = ?', [mb_strtolower($canonicalName)])
                ->whereIn('role', \App\Services\Roles::BUSINESS_OWNERS)
                ->where('status', 'approved')
                ->count() > 0;
        }

        if (! $hasApprovedAdmin) {
            return ['error' => 'Biashara hii haina msimamizi. Tafadhali jisajili kama msimamizi kwanza.', 'code' => 'BUSINESS_NO_ADMIN'];
        }

        $structure = in_array((string) ($business->business_structure ?? ''), BranchContext::STRUCTURES, true)
            ? (string) $business->business_structure
            : BranchContext::NON_BRANCH;

        $resolvedBranchId = null;

        if ($structure === 'branch') {
            $requested = $branchId !== null ? trim($branchId) : '';

            if ($requested === '') {
                return ['error' => 'Chagua tawi la biashara unayojiunga nayo.', 'code' => 'BRANCH_REQUIRED'];
            }

            $branch = BranchContext::activeBranch($requested, (string) $business->id);

            if (! $branch) {
                return ['error' => 'Tawi ulilochagua halipatikani kwenye biashara hii au limezimwa.', 'code' => 'BRANCH_NOT_FOUND'];
            }

            $resolvedBranchId = (string) $branch->id;
        }

        return [
            'business_id' => (string) $business->id,
            'business_name' => $canonicalName,
            'business_structure' => $structure,
            'branch_id' => $resolvedBranchId,
        ];
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
        $html = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #2c3e50;">Habari ' . ($fullName ?: 'Mteja') . '!</h2>
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
                });
                return ['success' => true, 'messageId' => '', 'attempt' => $attempt];
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < $maxRetries) {
                    usleep($attempt * 1000000);
                }
            }
        }
        return ['success' => false, 'error' => $lastError ? $lastError->getMessage() : 'Unknown error', 'attempts' => $maxRetries];
    }
}