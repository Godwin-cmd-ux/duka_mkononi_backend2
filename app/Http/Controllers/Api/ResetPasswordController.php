<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtToken;
use App\Services\Supabase;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ResetPasswordController extends BaseController
{
    public function request(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = $request->input('email');
        $role = $request->input('role');

        if (!$email || !$role) {
            $this->log(null, 'PASSWORD_RESET_REQUEST_FAILED', '/api/password-reset/request', [
                'reason' => 'Missing fields',
                'email' => $email,
                'role' => $role
            ], $ip, 'failed');

            return $this->json(['error' => 'Email na role zinahitajika'], 400);
        }

        try {
            $adjustedRole = $this->adjustRoleForDatabase($role);

            $tableExists = false;
            try {
                Supabase::table('password_reset_codes')->select('id')->limit(1)->get();
                $tableExists = true;
            } catch (\Throwable $tableError) {
                $tableExists = false;
            }

            $user = User::select('id', 'email', 'role', 'full_name')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
                ->where('role', $adjustedRole)
                ->first();

            if (!$user) {
                $this->log(null, 'PASSWORD_RESET_REQUEST_FAILED', '/api/password-reset/request', [
                    'reason' => 'User not found',
                    'email' => $email,
                    'role' => $role,
                    'adjustedRole' => $adjustedRole
                ], $ip, 'failed');

                return $this->json([
                    'success' => true,
                    'message' => 'Ikiwa barua pepe ipo kwenye mfumo, utapokea msimbo wa kubadilisha nenosiri kwenye barua pepe yako.',
                    'requiresCode' => true
                ], 200);
            }

            $resetCode = (string) random_int(100000, 999999);

            if ($this->isTestMode()) {
                if ($tableExists) {
                    try {
Supabase::table('password_reset_codes')->insert([
                            'id' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'email' => $email,
                            'code' => $resetCode,
                            'status' => 'pending',
                            'expires_at' => now()->addMinutes(15)->toISOString(true),
                            'created_at' => $this->isoNow(),
                            'updated_at' => $this->isoNow()
                        ]);
                    } catch (\Throwable $dbError) {
                        // Could not save code to database
                    }
                }

                $this->log($user->id, 'PASSWORD_RESET_DEVELOPMENT', '/api/password-reset/request', [
                    'code_generated' => $resetCode,
                    'development_mode' => true,
                    'table_exists' => $tableExists
                ], $ip, 'success');

                return $this->json([
                    'success' => true,
                    'message' => 'Development mode: Tumia msimbo huu wa kubadilisha nenosiri',
                    'resetCode' => $resetCode,
                    'email' => $email,
                    'userId' => $user->id,
                    'role' => $role,
                    'expiresIn' => '15 minutes',
                    'warning' => 'Running in development mode - email haikutumwa',
                    'development' => true,
                    'timestamp' => $this->isoNow()
                ], 200);
            }

            if (!$tableExists) {
                $expiresAt = now()->addMinutes(15)->toISOString(true);
                $tempCodeData = [
                    'user_id' => $user->id,
                    'email' => $email,
                    'reset_code' => $resetCode,
                    'is_used' => false,
                    'expires_at' => $expiresAt,
                    'created_at' => $this->isoNow()
                ];

                Cache::put('temp_reset_code:' . $email . ':' . $role, $tempCodeData, now()->addMinutes(15));
                if ($adjustedRole !== $role) {
                    Cache::put('temp_reset_code:' . $email . ':' . $adjustedRole, $tempCodeData, now()->addMinutes(15));
                }

                $emailSubject = 'Msimbo wa Kubadilisha Nenosiri - DukaMkononi (Mfumo wa Dharura)';
                $emailHtml = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2c3e50;">Habari ' . ($user->full_name ?: 'Mteja') . '!</h2>
                        <p>Umeomba kubadilisha nenosiri lako la akaunti ya DukaMkononi.</p>
                        <p><strong style="color: #e74c3c;">âš ï¸ KUMBUKA: Hii ni mfumo wa dharura. Wasiliana na msimamizi kuhusu kusanidi database.</strong></p>
                        <p>Msimbo wako wa uthibitishaji ni:</p>
                        <div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;">
                            <h1 style="font-size: 32px; color: #2c3e50; letter-spacing: 5px; margin: 0;">' . $resetCode . '</h1>
                        </div>
                        <p><strong>Msimbo huu utaisha muda wake ndani ya dakika 15.</strong></p>
                        <p style="color: #e74c3c; font-weight: bold;">
                            âš ï¸ KUMBUKA: Hii ni mfumo wa dharura. Msimbo huu utapotea server ikizima upya.
                        </p>
                        <p>Ikiwa hukuomba kubadilisha nenosiri, unaweza kupuuza barua pepe hii.</p>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                        <p style="font-size: 12px; color: #7f8c8d;">
                            DukaMkononi Team<br>
                            Hii ni barua pepe ya kiotomatiki. Tafadhali usijibu.
                        </p>
                    </div>
                ';

                $emailResult = $this->sendEmailWithRetry($email, $emailSubject, $emailHtml);

                if (!$emailResult['success']) {
                    $this->log($user->id, 'PASSWORD_RESET_EMAIL_FAILED', '/api/password-reset/request', [
                        'error' => $emailResult['error'],
                        'using_fallback' => true,
                        'code_returned_directly' => true
                    ], $ip, 'partial');

                    return $this->json([
                        'success' => true,
                        'message' => 'Msimbo wa kubadilisha nenosiri (email imeshindikana):',
                        'resetCode' => $resetCode,
                        'email' => $email,
                        'userId' => $user->id,
                        'role' => $role,
                        'expiresIn' => '15 minutes',
                        'warning' => 'Email haikutumwa. Tumia msimbo huu.',
                        'email_failed' => true,
                        'fallback' => true
                    ], 200);
                }

                $this->log($user->id, 'PASSWORD_RESET_REQUEST_FALLBACK', '/api/password-reset/request', [
                    'code_sent' => true,
                    'email' => $email,
                    'original_role' => $role,
                    'adjusted_role' => $adjustedRole,
                    'user_found' => true,
                    'using_fallback' => true,
                    'warning' => 'password_reset_codes table not found, using in-memory storage'
                ], $ip, 'success');

                return $this->json([
                    'success' => true,
                    'message' => 'Msimbo wa kubadilisha nenosiri umepelekwa kwenye barua pepe yako.',
                    'requiresCode' => true,
                    'email' => $email,
                    'userId' => $user->id,
                    'role' => $role,
                    'expiresIn' => '15 minutes',
                    'warning' => 'Database table not configured. Code stored temporarily.',
                    'fallback' => true
                ], 200);
            }

            $expiresAt = now()->addMinutes(15)->toISOString(true);

            $existingCodes = Supabase::table('password_reset_codes')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', $this->isoNow())
                ->limit(1)
                ->count();

            if ($existingCodes > 0) {
                try {
                    Supabase::table('password_reset_codes')
                        ->where('user_id', $user->id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'used',
                            'updated_at' => $this->isoNow()
                        ]);
                } catch (\Throwable $error) {
                    // Error invalidating old codes
                }
            }

            Supabase::table('password_reset_codes')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'email' => $email,
                'code' => $resetCode,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => $this->isoNow(),
                'updated_at' => $this->isoNow()
            ]);

            $emailSubject = 'Msimbo wa Kubadilisha Nenosiri - DukaMkononi';
            $emailHtml = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <h2 style="color: #2c3e50;">Habari ' . ($user->full_name ?: 'Mteja') . '!</h2>
                    <p>Umeomba kubadilisha nenosiri lako la akaunti ya DukaMkononi.</p>
                    <p>Msimbo wako wa uthibitishaji ni:</p>
                    <div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0;">
                        <h1 style="font-size: 32px; color: #2c3e50; letter-spacing: 5px; margin: 0;">' . $resetCode . '</h1>
                    </div>
                    <p><strong>Msimbo huu utaisha muda wake ndani ya dakika 15.</strong></p>
                    <p>Ikiwa hukuomba kubadilisha nenosiri, unaweza kupuuza barua pepe hii.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="font-size: 12px; color: #7f8c8d;">
                        DukaMkononi Team<br>
                        Hii ni barua pepe ya kiotomatiki. Tafadhali usijibu.
                    </p>
                </div>
            ';

            $emailResult = $this->sendEmailWithRetry($email, $emailSubject, $emailHtml);

            if (!$emailResult['success']) {
                $this->log($user->id, 'PASSWORD_RESET_EMAIL_FAILED', '/api/password-reset/request', [
                    'error' => $emailResult['error'],
                    'code_saved_to_db' => true,
                    'reset_code' => $resetCode
                ], $ip, 'partial');

                return $this->json([
                    'success' => true,
                    'message' => 'Msimbo wa kubadilisha nenosiri umehifadhiwa kwenye mfumo lakini imeshindikana kutumwa kwenye barua pepe.',
                    'resetCode' => $resetCode,
                    'email' => $email,
                    'userId' => $user->id,
                    'role' => $role,
                    'expiresIn' => '15 minutes',
                    'database_stored' => true,
                    'email_failed' => true,
                    'note' => 'Code is saved in database. Contact support with your email.',
                    'warning' => $this->isProduction() ? 'Code not shown in production' : 'Use this code for testing'
                ], 200);
            }

            $this->log($user->id, 'PASSWORD_RESET_REQUEST', '/api/password-reset/request', [
                'code_sent' => true,
                'email' => $email,
                'original_role' => $role,
                'adjusted_role' => $adjustedRole,
                'user_found' => true,
                'table_exists' => true
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Msimbo wa kubadilisha nenosiri umepelekwa kwenye barua pepe yako.',
                'requiresCode' => true,
                'email' => $email,
                'userId' => $user->id,
                'role' => $role,
                'expiresIn' => '15 minutes',
                'database_stored' => true
            ], 200);
        } catch (\Throwable $error) {
            $logDetails = [
                'error' => $error->getMessage(),
                'email' => $email ?: 'unknown',
                'role' => $role ?: 'unknown',
                'adjustedRole' => $role ? $this->adjustRoleForDatabase($role) : 'unknown'
            ];

            $this->log(null, 'PASSWORD_RESET_REQUEST_ERROR', '/api/password-reset/request', $logDetails, $ip, 'failed');

            $errorMessage = 'Hitilafu ya ndani ya server: ' . $error->getMessage();

            if (str_contains($error->getMessage(), 'fetch') || str_contains($error->getMessage(), 'network')) {
                $errorMessage = 'Hitilafu ya muunganisho na database. Tafadhali jaribu tena baadaye.';
            } elseif (str_contains($error->getMessage(), 'Could not find the table')) {
                $errorMessage = 'Mfumo wa kubadilisha nenosiri haujasanidiwa vyema. Tafadhali wasiliana na msimamizi.';
            } elseif (str_contains($error->getMessage(), 'password_reset_codes')) {
                $errorMessage = 'Hitilafu ya mfumo wa kubadilisha nenosiri. Tafadhali jaribu tena baadaye.';
            }

            return $this->json([
                'success' => false,
                'error' => $errorMessage,
                'requiresCode' => false
            ], 500);
        }
    }

    public function verifyCode(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = $request->input('email');
        $role = $request->input('role');
        $resetCode = $request->input('resetCode');

        if (!$email || !$role || !$resetCode) {
            $this->log(null, 'RESET_CODE_VERIFY_FAILED', '/api/password-reset/verify-code', [
                'reason' => 'Missing fields'
            ], $ip, 'failed');

            return $this->json(['error' => 'Email, role na msimbo zinahitajika'], 400);
        }

        try {
            $adjustedRole = $this->adjustRoleForDatabase($role);

            $user = User::select('id')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
                ->where('role', $adjustedRole)
                ->first();

            if (!$user) {
                $this->log(null, 'RESET_CODE_VERIFY_FAILED', '/api/password-reset/verify-code', [
                    'reason' => 'User not found',
                    'email' => $email,
                    'original_role' => $role,
                    'adjusted_role' => $adjustedRole
                ], $ip, 'failed');

                return $this->json(['error' => 'Msimbo si sahihi'], 400);
            }

            $codeRecord = Supabase::table('password_reset_codes')
                ->where('user_id', $user->id)
                ->where('code', $resetCode)
                ->where('status', 'pending')
                ->where('expires_at', '>', $this->isoNow())
                ->orderByDesc('created_at')
                ->limit(1)
                ->first();

            if (!$codeRecord) {
                $this->log($user->id, 'RESET_CODE_VERIFY_FAILED', '/api/password-reset/verify-code', [
                    'reason' => 'Invalid or expired code',
                    'reset_code' => $resetCode,
                    'original_role' => $role,
                    'adjusted_role' => $adjustedRole
                ], $ip, 'failed');

                return $this->json(['error' => 'Msimbo ulioweka sio sahihi au umeisha muda wake'], 400);
            }

            Supabase::table('password_reset_codes')
                ->where('id', $codeRecord->id)
                ->update([
                    'status' => 'used',
                    'updated_at' => $this->isoNow()
                ]);

            $verificationToken = JwtToken::encode([
                'userId' => $user->id,
                'email' => $email,
                'role' => $role,
                'adjustedRole' => $adjustedRole,
                'codeId' => $codeRecord->id,
                'type' => 'password_reset_verified',
                'verifiedAt' => (int) (microtime(true) * 1000),
                'exp' => time() + 600
            ]);

            $this->log($user->id, 'RESET_CODE_VERIFIED', '/api/password-reset/verify-code', [
                'code_id' => $codeRecord->id,
                'verified' => true,
                'original_role' => $role,
                'adjusted_role' => $adjustedRole
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Msimbo umehakikiwa kikamilifu!',
                'verified' => true,
                'verificationToken' => $verificationToken,
                'expiresIn' => '10 minutes'
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'RESET_CODE_VERIFY_ERROR', '/api/password-reset/verify-code', [
                'error' => $error->getMessage(),
                'role' => $role ?: 'unknown',
                'adjustedRole' => $role ? $this->adjustRoleForDatabase($role) : 'unknown'
            ], $ip, 'failed');

            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function confirm(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $verificationToken = $request->input('verificationToken');
        $newPassword = $request->input('newPassword');
        $confirmPassword = $request->input('confirmPassword');

        if (!$verificationToken || !$newPassword || !$confirmPassword) {
            $this->log(null, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                'reason' => 'Missing fields'
            ], $ip, 'failed');

            return $this->json(['error' => 'Token, nenosiri jipya na uthibitishaji zinahitajika'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            $this->log(null, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                'reason' => 'Passwords do not match'
            ], $ip, 'failed');

            return $this->json(['error' => 'Nenosiri jipya na uthibitishaji havifanani'], 400);
        }

        if (strlen($newPassword) < 6) {
            $this->log(null, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                'reason' => 'Password too short'
            ], $ip, 'failed');

            return $this->json(['error' => 'Nenosiri jipya lazima liwe na herufi 6 au zaidi'], 400);
        }

        try {
            $decoded = JwtToken::decode($verificationToken);

            if (!isset($decoded->type) || $decoded->type !== 'password_reset_verified') {
                $this->log($decoded->userId ?? null, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                    'reason' => 'Invalid token type',
                    'token_type' => $decoded->type ?? null,
                    'role_in_token' => $decoded->role ?? null,
                    'adjusted_role' => $decoded->adjustedRole ?? null
                ], $ip, 'failed');

                return $this->json(['error' => 'Token si sahihi'], 400);
            }

            $userId = $decoded->userId;
            $originalRole = $decoded->role;
            $adjustedRole = $decoded->adjustedRole ?? $this->adjustRoleForDatabase($originalRole);

            if (isset($decoded->codeId)) {
                try {
$codeRecord = Supabase::table('password_reset_codes')
                        ->select('status')
                        ->where('id', $decoded->codeId)
                        ->first();
                } catch (\Throwable $codeError) {
                    $codeRecord = null;
                }

                if ($codeRecord && $codeRecord->status !== 'used') {
                    $this->log($userId, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                        'reason' => 'Code not marked as used',
                        'code_id' => $decoded->codeId,
                        'original_role' => $originalRole,
                        'adjusted_role' => $adjustedRole
                    ], $ip, 'failed');

                    return $this->json(['error' => 'Msimbo wa uthibitishaji haujatumika'], 400);
                }
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            User::where('id', $userId)->update([
                'password' => $hashedPassword,
                'updated_at' => $this->isoNow()
            ]);

            $user = User::select('email', 'full_name', 'role')->where('id', $userId)->first();

            if ($user) {
                $emailSubject = 'Nenosiri Limebadilishwa - DukaMkononi';
                $emailHtml = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <h2 style="color: #2c3e50;">Habari ' . ($user->full_name ?: 'Mteja') . '!</h2>
                        <p>Nenosiri lako la akaunti ya DukaMkononi limebadilishwa kikamilifu.</p>
                        <p><strong>Ikiwa hukuomba kubadilisha nenosiri, tafadhali wasiliana na msimamizi mara moja.</strong></p>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                        <p style="font-size: 12px; color: #7f8c8d;">
                            DukaMkononi Team<br>
                            Hii ni barua pepe ya kiotomatiki. Tafadhali usijibu.
                        </p>
                    </div>
                ';

                $this->sendEmail($user->email, $emailSubject, $emailHtml);
            }

            $this->log($userId, 'PASSWORD_RESET_COMPLETED', '/api/password-reset/confirm', [
                'reset_successful' => true,
                'original_role' => $originalRole,
                'adjusted_role' => $adjustedRole,
                'database_role' => $user->role ?? 'unknown'
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Nenosiri limebadilishwa kikamilifu! Unaweza kuingia sasa kwa nenosiri jipya.',
                'passwordChanged' => true,
                'role' => $originalRole
            ], 200);
        } catch (\Throwable $error) {
            if ($error instanceof ExpiredException) {
                $this->log(null, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                    'reason' => 'Token expired'
                ], $ip, 'failed');

                return $this->json(['error' => 'Token imeisha muda wake. Tafadhali anza upya.'], 400);
            } elseif ($error instanceof SignatureInvalidException || $error instanceof UnexpectedValueException) {
                $this->log(null, 'PASSWORD_RESET_CONFIRM_FAILED', '/api/password-reset/confirm', [
                    'reason' => 'Invalid token'
                ], $ip, 'failed');

                return $this->json(['error' => 'Token si sahihi'], 400);
            }

            $this->log(null, 'PASSWORD_RESET_CONFIRM_ERROR', '/api/password-reset/confirm', [
                'error' => $error->getMessage()
            ], $ip, 'failed');

            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function checkStatus(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = $request->input('email');
        $role = $request->input('role');

        try {
            if (!$email || !$role) {
                return $this->json(['error' => 'Email na role zinahitajika'], 400);
            }

            $adjustedRole = $this->adjustRoleForDatabase($role);

            $user = User::select('id')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
                ->where('role', $adjustedRole)
                ->first();

            if (!$user) {
                return $this->json([
                    'hasActiveReset' => false,
                    'note' => 'User not found or role mismatch'
                ], 200);
            }

            $activeCode = Supabase::table('password_reset_codes')
                ->select('created_at', 'expires_at')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', $this->isoNow())
                ->orderByDesc('created_at')
                ->limit(1)
                ->first();

            $hasActiveReset = (bool) $activeCode;

            return $this->json([
                'hasActiveReset' => $hasActiveReset,
                'activeCode' => $hasActiveReset ? [
                    'createdAt' => $activeCode->created_at,
                    'expiresAt' => $activeCode->expires_at
                ] : null,
                'originalRole' => $role,
                'adjustedRole' => $adjustedRole
            ], 200);
        } catch (\Throwable $error) {
            return $this->json(['error' => 'Hitilafu ya ndani ya server'], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $email = $request->input('email');
        $role = $request->input('role');

        if (!$email || !$role) {
            $this->log(null, 'FORGOT_PASSWORD_FAILED', '/api/forgot-password', [
                'reason' => 'Missing fields',
                'email' => $email,
                'role' => $role
            ], $ip, 'failed');

            return $this->json(['error' => 'Email na role zinahitajika'], 400);
        }

        try {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('role', $role)->first();

            if (!$user) {
                $this->log(null, 'FORGOT_PASSWORD_FAILED', '/api/forgot-password', [
                    'reason' => 'Account not found',
                    'email' => $email,
                    'role' => $role
                ], $ip, 'failed');

                return $this->json(['error' => 'Akaunti haipo au role si sahihi'], 404);
            }

            $resetToken = JwtToken::encode([
                'userId' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'type' => 'password_reset',
                'exp' => time() + 3600
            ]);

            $this->log($user->id, 'FORGOT_PASSWORD_REQUEST', '/api/forgot-password', [
                'token_generated' => true
            ], $ip, 'success');

            return $this->json([
                'message' => 'Ombi la kubadilisha nenosiri limetumwa!',
                'resetToken' => $resetToken,
                'expiresIn' => '1 hour'
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'FORGOT_PASSWORD_ERROR', '/api/forgot-password', [
                'error' => $error->getMessage(),
                'email' => $email,
                'role' => $role
            ], $ip, 'failed');

            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        $token = $request->input('token');
        $newPassword = $request->input('newPassword');

        if (!$token || !$newPassword) {
            $this->log(null, 'RESET_PASSWORD_FAILED', '/api/reset-password', [
                'reason' => 'Missing token or password'
            ], $ip, 'failed');

            return $this->json(['error' => 'Token na nenosiri jipya zinahitajika'], 400);
        }

        if (strlen($newPassword) < 6) {
            $this->log(null, 'RESET_PASSWORD_FAILED', '/api/reset-password', [
                'reason' => 'Password too short'
            ], $ip, 'failed');

            return $this->json(['error' => 'Nenosiri jipya lazima liwe na herufi 6 au zaidi'], 400);
        }

        try {
            $decoded = JwtToken::decode($token);

            if (!isset($decoded->type) || $decoded->type !== 'password_reset') {
                $this->log($decoded->userId ?? null, 'RESET_PASSWORD_FAILED', '/api/reset-password', [
                    'reason' => 'Invalid token type',
                    'token_type' => $decoded->type ?? null
                ], $ip, 'failed');

                return $this->json(['error' => 'Token si sahihi'], 400);
            }

            $userId = $decoded->userId;

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            User::where('id', $userId)->update([
                'password' => $hashedPassword,
                'updated_at' => $this->isoNow()
            ]);

            $this->log($userId, 'PASSWORD_RESET', '/api/reset-password', [
                'reset_successful' => true
            ], $ip, 'success');

            return $this->json([
                'message' => 'Nenosiri limebadilishwa kikamilifu!',
                'success' => true
            ], 200);
        } catch (\Throwable $error) {
            if ($error instanceof ExpiredException) {
                $this->log(null, 'RESET_PASSWORD_FAILED', '/api/reset-password', [
                    'reason' => 'Token expired'
                ], $ip, 'failed');

                return $this->json(['error' => 'Token imeisha muda wake. Tafadhali tuma ombi jipya.'], 400);
            } elseif ($error instanceof SignatureInvalidException || $error instanceof UnexpectedValueException) {
                $this->log(null, 'RESET_PASSWORD_FAILED', '/api/reset-password', [
                    'reason' => 'Invalid token'
                ], $ip, 'failed');

                return $this->json(['error' => 'Token si sahihi'], 400);
            }

            $this->log(null, 'RESET_PASSWORD_ERROR', '/api/reset-password', [
                'error' => $error->getMessage()
            ], $ip, 'failed');

            return $this->json(['error' => 'Hitilafu ya ndani ya server: ' . $error->getMessage()], 500);
        }
    }

    public function checkTable(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $data = Supabase::table('password_reset_codes')->select('id')->limit(1)->get()->toArray();

            $this->log(null, 'CHECK_PASSWORD_RESET_TABLE', '/api/check/password-reset-table', [
                'exists' => true,
                'sample_data' => $data
            ], $ip, 'success');

            return $this->json([
                'exists' => true,
                'message' => 'password_reset_codes table exists and is accessible',
                'sample' => $data
            ], 200);
        } catch (\Throwable $error) {
            if (!$this->tableMissingError($error)) {
                $this->log(null, 'CHECK_PASSWORD_RESET_TABLE_ERROR', '/api/check/password-reset-table', [
                    'error' => $error->getMessage()
                ], $ip, 'failed');

                return $this->json([
                    'error' => 'Failed to check password_reset_codes table',
                    'details' => $error->getMessage()
                ], 500);
            }

            $this->log(null, 'CHECK_PASSWORD_RESET_TABLE', '/api/check/password-reset-table', [
                'exists' => false,
                'error_code' => '42P01'
            ], $ip, 'success');

            return $this->json([
                'exists' => false,
                'message' => 'password_reset_codes table does not exist',
                'error' => $error->getMessage()
            ], 200);
        }
    }

    public function setupTable(Request $request)
    {
        $ip = $request->header('x-forwarded-for') ?: $request->ip();

        try {
            $createTableSQL = 'CREATE TABLE IF NOT EXISTS password_reset_codes (
                id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
                user_id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                code VARCHAR(10) NOT NULL,
                status VARCHAR(20) DEFAULT \'pending\',
                expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
                created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
                updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
            )';

            $tableExists = false;
            try {
                Supabase::table('password_reset_codes')->select('id')->limit(1)->get();
                $tableExists = true;
            } catch (\Throwable $e) {
                $tableExists = false;
            }

            if (!$tableExists) {
                throw new \RuntimeException('password_reset_codes table is missing in Supabase; create it in the Supabase SQL Editor first.');
            }

            $this->log(null, 'SETUP_PASSWORD_RESET_TABLE', '/api/setup/password-reset-table', [
                'success' => true,
                'table_created' => false,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'password_reset_codes table created successfully!',
                'table' => 'password_reset_codes',
                'indexes' => ['user_id', 'email', 'code', 'expires_at']
            ], 200);
        } catch (\Throwable $error) {
            $this->log(null, 'SETUP_PASSWORD_RESET_TABLE_ERROR', '/api/setup/password-reset-table', [
                'error' => $error->getMessage()
            ], $ip, 'failed');

            $manualSQL = '-- Run this in your SQL Editor:
CREATE TABLE IF NOT EXISTS password_reset_codes (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id UUID NOT NULL,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT \'pending\',
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_password_reset_codes_user_id ON password_reset_codes(user_id);
CREATE INDEX idx_password_reset_codes_email ON password_reset_codes(email);
CREATE INDEX idx_password_reset_codes_code ON password_reset_codes(code);
CREATE INDEX idx_password_reset_codes_expires_at ON password_reset_codes(expires_at);';

            return $this->json([
                'error' => 'Failed to create password_reset_codes table',
                'details' => $error->getMessage(),
                'manual_sql' => $manualSQL,
                'instructions' => 'Please run the SQL above in your SQL Editor'
            ], 500);
        }
    }

    private function adjustRoleForDatabase($role)
    {
        if ($role === 'client') {
            return 'customer';
        }
        return $role;
    }

    private function isTestMode(): bool
    {
        $testMode = config('app.enable_test_mode', env('ENABLE_TEST_MODE'));
        $testMode = $testMode === true || $testMode === 1 || $testMode === 'true' || $testMode === '1' || $testMode === 'on';
        return config('app.env') === 'development' || config('app.env') === 'local' || $testMode;
    }

    private function isProduction(): bool
    {
        return config('app.env') === 'production';
    }

    private function tableMissingError(\Throwable $error): bool
    {
        $message = $error->getMessage();
        return str_contains($message, '42P01')
            || str_contains($message, 'no such table')
            || str_contains($message, 'Could not find the table')
            || str_contains($message, 'does not exist');
    }

    private function sendEmail($to, $subject, $html): array
    {
        try {
            Mail::html($html, [], function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
            return ['success' => true, 'messageId' => ''];
        } catch (\Throwable $error) {
            return ['success' => false, 'error' => $error->getMessage()];
        }
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
            } catch (\Throwable $error) {
                $lastError = $error;
                if ($attempt < $maxRetries) {
                    usleep($attempt * 1000000);
                }
            }
        }
        return ['success' => false, 'error' => $lastError ? $lastError->getMessage() : 'Unknown error', 'attempts' => $maxRetries];
    }
}