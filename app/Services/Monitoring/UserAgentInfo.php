<?php

namespace App\Services\Monitoring;

/**
 * Best-effort User-Agent facts for audit records (Phase 5).
 *
 * Deliberate honesty rules:
 *  - a User-Agent string cannot identify a physical handset model, so `model`
 *    is ALWAYS "Unknown model" on mobile devices - nothing is invented;
 *  - anything unrecognised degrades to "Unknown ..." rather than a guess;
 *  - the parsed facts are advisory metadata only and are never treated as
 *    proof of identity.
 *
 * No external dependency (engineering rule 15): a handful of token checks
 * covers every browser this application actually sees.
 */
class UserAgentInfo
{
    public const UNKNOWN_MODEL = 'Unknown model';

    /**
     * @return array{
     *   browser: string, browser_version: ?string,
     *   os: ?string, os_version: ?string,
     *   device_category: string, model: ?string, display: string
     * }
     */
    public static function parse(?string $ua): array
    {
        $ua = trim((string) $ua);
        if ($ua === '') {
            return self::unknown('Unknown', null, null, null, 'Unknown');
        }

        /* ---------------- operating system ---------------- */

        $os = null;
        $osVersion = null;

        if (preg_match('/HarmonyOS[\/ ]([0-9.]+)/i', $ua, $m)) {
            $os = 'HarmonyOS';
            $osVersion = self::major($m[1]);
        } elseif (preg_match('/Android\s+([0-9.]+)/i', $ua, $m)) {
            $os = 'Android';
            $osVersion = self::major($m[1]);
        } elseif (preg_match('/\b(iPhone|iPad|iPod)\b.*OS\s([0-9_]+)/i', $ua, $m)) {
            $os = 'iOS';
            $osVersion = self::major(str_replace('_', '.', $m[2]));
            if (strtolower($m[1]) === 'ipad') {
                $isTablet = true;
            }
        } elseif (preg_match('/\bWindows NT\s([0-9.]+)/i', $ua, $m)) {
            $os = 'Windows';
            $osVersion = self::windowsVersion($m[1]);
        } elseif (preg_match('/Mac OS X\s([0-9_./]+)/i', $ua, $m)) {
            // iPadOS in desktop mode still leaks "Mobile/"; without it this
            // really is a Mac.
            $os = stripos($ua, 'Mobile/') !== false ? 'iOS' : 'macOS';
            $osVersion = self::major(str_replace('_', '.', $m[1]));
        } elseif (preg_match('/\bCrOS\b/i', $ua)) {
            $os = 'ChromeOS';
        } elseif (preg_match('/\bUbuntu|\bLinux\b/i', $ua)) {
            $os = 'Linux';
        }

        /* ---------------- device category ---------------- */

        $isTablet = $isTablet ?? false;
        $isPhone = false;

        if ($os === 'iOS') {
            $isPhone = stripos($ua, 'iPhone') !== false || stripos($ua, 'iPod') !== false;
        } elseif ($os === 'Android') {
            if (stripos($ua, 'Mobile') !== false) {
                $isPhone = true;
            } elseif (stripos($ua, 'Tablet') !== false || stripos($ua, 'Kindle') !== false) {
                $isTablet = true;
            } else {
                // Android without "Mobile" is usually a tablet, but not
                // certainly - classified as tablet only on the tablet token,
                // otherwise treated as a phone-shaped mobile device.
                $isPhone = stripos($ua, 'SM-') !== false;
                $isTablet = ! $isPhone;
            }
        }

        if (preg_match('/bot|crawler|spider|crawling|slurp|curl\/|wget|python-requests|okhttp|postman/i', $ua)) {
            $deviceCategory = 'Bot';
        } elseif ($isTablet) {
            $deviceCategory = 'Tablet';
        } elseif ($isPhone) {
            $deviceCategory = 'Mobile';
        } elseif ($os === null) {
            $deviceCategory = 'Unknown';
        } else {
            $deviceCategory = 'Desktop';
        }

        /* ---------------- browser ---------------- */

        $browser = 'Unknown';
        $browserVersion = null;

        if (preg_match('/Edg(?:e|A|iOS)?\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Edge';
            $browserVersion = self::major($m[1]);
        } elseif (preg_match('/OPR\/([0-9.]+)|Opera/', $ua, $m)) {
            $browser = 'Opera';
            $browserVersion = isset($m[1]) ? self::major($m[1]) : null;
        } elseif (preg_match('/SamsungBrowser\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Samsung Internet';
            $browserVersion = self::major($m[1]);
        } elseif (preg_match('/FxiOS\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Firefox';
            $browserVersion = self::major($m[1]);
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Firefox';
            $browserVersion = self::major($m[1]);
        } elseif (preg_match('/CriOS\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Chrome';
            $browserVersion = self::major($m[1]);
        } elseif (preg_match('/Chrome\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Chrome';
            $browserVersion = self::major($m[1]);
        } elseif (preg_match('/Version\/([0-9.]+).*Safari|Safari\/([0-9.]+)/', $ua, $m)) {
            $browser = 'Safari';
            $browserVersion = self::major($m[1] ?: ($m[2] ?? ''));
        }

        return self::unknown($browser, $browserVersion, $os, $osVersion, $deviceCategory);
    }

    /**
     * Human display, e.g. "Android phone - Chrome 120" or "Windows desktop -
     * Edge 121". Never contains a claimed physical model.
     */
    public static function display(array $info): string
    {
        $device = $info['device_category'] === 'Unknown'
            ? 'device'
            : strtolower($info['device_category']);

        $os = $info['os'] ?? 'Unknown OS';
        $browser = $info['browser'] . ($info['browser_version'] ? ' ' . $info['browser_version'] : '');

        return trim($os . ' ' . $device . ' - ' . $browser);
    }

    private static function unknown(?string $browser, ?string $browserVersion, ?string $os, ?string $osVersion, string $deviceCategory): array
    {
        $mobile = $deviceCategory === 'Mobile' || $deviceCategory === 'Tablet';

        return [
            'browser' => $browser ?: 'Unknown',
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
            'device_category' => $deviceCategory,
            'model' => $mobile ? self::UNKNOWN_MODEL : null,
            'display' => self::display([
                'browser' => $browser ?: 'Unknown',
                'browser_version' => $browserVersion,
                'os' => $os,
                'device_category' => $deviceCategory,
            ]),
        ];
    }

    private static function major(string $version): ?string
    {
        $version = trim($version, '. /');
        if ($version === '') {
            return null;
        }
        preg_match('/[0-9]+/', $version, $m);

        return $m[0] ?? null;
    }

    /** Windows NT 10.0 -> 10, NT 6.1 -> 7 (display-friendly, no invention). */
    private static function windowsVersion(string $nt): ?string
    {
        $map = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista'];

        return $map[trim($nt)] ?? self::major($nt);
    }
}
