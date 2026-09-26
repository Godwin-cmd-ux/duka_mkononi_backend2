<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtToken;
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
        $full_name = $verified->full_name ?? null;
        $phone = $verified->phone ?? null;
        $business_name = $verified->business_name ?? null;
        $business_location = $verified->business_location ?? null;
        $business_type = $verified->business_type ?? null;
        $business_description = $verified->business_description ?? null;
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

            if ($role === 'admin' || $role === 'seller') {
                if (!$business_name) {
                    $this->log(null, 'REGISTER_FAILED', '/api/register', ['reason' => 'Business name required', 'email' => $email, 'role' => $role], $ip, 'failed');
                    return $this->json(['error' => 'Jina la biashara linahitajika'], 400);
                }

                $adminUsers = User::select('id', 'email', 'role', 'status')
                    ->whereRaw('LOWER(business_name) = ?', [mb_strtolower(trim($business_name))])
                    ->where('role', 'admin')
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

            $userData = [
                'id' => (string) Str::uuid(),
                'email' => $email,
                'password' => $passwordHash,
                'role' => $role,
                'full_name' => $full_name ?: null,
                'phone' => $phone ?: null,
                'business_name' => $business_name ?: null,
                'business_location' => $business_location ?: null,
                'business_type' => $business_type ?: null,
                'business_description' => $business_description ?: null,
                'language' => $language ?: 'sw',
                'status' => $userStatus,
                'is_online' => false,
                'last_seen' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ];

            $newUser = null;
            try {
                $id = $userData['id'];
                User::create($userData);
                $newUser = User::where('id', $id)->first();
            } catch (\Throwable $insertError) {
                if (preg_match('/does not exist|column.*not found|no such column|could not find|PGRST/i', $insertError->getMessage())) {
                    $safeUserData = $userData;
                    unset($safeUserData['business_type']);
                    unset($safeUserData['business_description']);
                    $id = $safeUserData['id'];
                    User::create($safeUserData);
                    $newUser = User::where('id', $id)->first();
                } else {
                    throw $insertError;
                }
            }

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
        $language = $request->input('language');

        if (!$email || !$password || !$role) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Missing required fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email, password na role zinahitajika'], 400);
        }

        if ($role === 'admin' && (!$business_type || !in_array($business_type, self::BUSINESS_TYPE_ALLOWED, true))) {
            $this->log(null, 'REGISTER_INITIATE_FAILED', '/api/register/initiate', ['reason' => 'Invalid business_type', 'email' => $email, 'role' => $role, 'business_type' => $business_type], $ip, 'failed');
            return $this->json(['error' => 'Aina ya biashara inahitajika'], 400);
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
                    ->where('role', 'admin')
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

        if (!$email || !$password || !$role) {
            $this->log(null, 'LOGIN_FAILED', '/api/login', ['reason' => 'Missing fields', 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Email, password na role zinahitajika'], 400);
        }

        try {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('role', $role)->first();

            if (!$user) {
                $this->log(null, 'LOGIN_FAILED', '/api/login', ['reason' => 'Account not found', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Akaunti haipo au role si sahihi'], 401);
            }

            $isValid = password_verify($password, $user->password);

            if (!$isValid) {
                $this->log($user->id, 'LOGIN_FAILED', '/api/login', ['reason' => 'Invalid password', 'email' => $email, 'role' => $role], $ip, 'failed');
                return $this->json(['error' => 'Password si sahihi'], 401);
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
                User::where('id', $user->id)->update(['language' => $language]);
            }

            $now = $this->isoNow();
            User::where('id', $user->id)->update([
                'is_online' => true,
                'last_seen' => $now,
                'updated_at' => $now
            ]);

            $token = JwtToken::encode([
                'userId' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);

            $this->log($user->id, 'LOGIN_SUCCESS', '/api/login', [
                'role' => $user->role,
                'business_name' => $user->business_name,
                'is_online' => true
            ], $ip, 'success');

            return $this->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'full_name' => $user->full_name,
                    'business_name' => $user->business_name,
                    'business_location' => $user->business_location,
                    'business_logo_url' => $user->business_logo_url ?: null,
                    'business_latitude' => $user->business_latitude ?: null,
                    'business_longitude' => $user->business_longitude ?: null,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'language' => $user->language ?: 'sw',
                    'is_online' => true,
                    'last_seen' => $now
                ]
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'LOGIN_ERROR', '/api/login', ['error' => $error->getMessage(), 'email' => $email, 'role' => $role], $ip, 'failed');
            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
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
                Mail::html($html, [], function ($m) use ($to, $subject) {
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