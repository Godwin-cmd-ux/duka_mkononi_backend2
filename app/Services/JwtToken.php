<?php

namespace App\Services;

/**
 * Minimal HS256 JWT implementation matching Node's `jsonwebtoken` output
 * byte-for-byte for the payloads used by this app, with no key-length
 * restrictions (the shared secret JWT_SECRET is a short string).
 */
class JwtToken
{
    public const ALGO = 'HS256';

    public static function encode(array $payload): string
    {
        if (!array_key_exists('iat', $payload)) {
            $payload['iat'] = time();
        }

        $header = json_encode(['alg' => self::ALGO, 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $segments = [
            self::base64UrlEncode($header),
            self::base64UrlEncode($body),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, config('jwt.secret'), true);

        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): object
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Firebase\JWT\SignatureInvalidException('Malformed token');
        }

        [$headB64, $bodyB64, $sigB64] = $parts;

        $signingInput = $headB64 . '.' . $bodyB64;
        $expected = self::base64UrlDecode($sigB64);
        $actual = hash_hmac('sha256', $signingInput, config('jwt.secret'), true);

        if (!hash_equals($expected, $actual)) {
            throw new \Firebase\JWT\SignatureInvalidException('Signature verification failed');
        }

        $payload = json_decode(self::base64UrlDecode($bodyB64), false);
        if ($payload === null) {
            throw new \Firebase\JWT\UnexpectedValueException('Invalid payload');
        }

        if (isset($payload->exp) && is_numeric($payload->exp) && time() >= (int) $payload->exp) {
            throw new \Firebase\JWT\ExpiredException('Expired token');
        }

        return $payload;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}