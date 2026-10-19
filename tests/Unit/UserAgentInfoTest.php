<?php

namespace Tests\Unit;

use App\Services\Monitoring\UserAgentInfo;
use Tests\TestCase;

/**
 * Phase 5 device/browser intelligence. The point of these tests is honesty:
 * real families/versions/OS are detected, everything unrecognised degrades to
 * "Unknown ...", and a physical handset model is NEVER invented.
 */
class UserAgentInfoTest extends TestCase
{
    private const ANDROID_CHROME = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.230 Mobile Safari/537.36';

    private const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1';

    private const IPAD_SAFARI = 'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';

    private const WINDOWS_EDGE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.2277.83';

    private const FIREFOX_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:122.0) Gecko/20100101 Firefox/122.0';

    private const SAMSUNG_BROWSER = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/21.0 Chrome/117.0.0.0 Mobile Safari/537.36';

    public function test_android_phone_chrome_is_detected_without_claiming_a_model(): void
    {
        $info = UserAgentInfo::parse(self::ANDROID_CHROME);

        $this->assertSame('Android', $info['os']);
        $this->assertSame('14', $info['os_version']);
        $this->assertSame('Chrome', $info['browser']);
        $this->assertSame('120', $info['browser_version']);
        $this->assertSame('Mobile', $info['device_category']);
        $this->assertSame('Unknown model', $info['model'], 'A UA cannot identify a handset model - never invent one.');
        $this->assertStringContainsString('Android', $info['display']);
        $this->assertStringContainsString('Chrome 120', $info['display']);
        $this->assertStringNotContainsString('Pixel', $info['display'], 'Model hints from UA tokens must not be surfaced as identification.');
    }

    public function test_iphone_safari_is_ios_mobile_with_unknown_model(): void
    {
        $info = UserAgentInfo::parse(self::IPHONE_SAFARI);

        $this->assertSame('iOS', $info['os']);
        $this->assertSame('17', $info['os_version']);
        $this->assertSame('Safari', $info['browser']);
        $this->assertSame('17', $info['browser_version']);
        $this->assertSame('Mobile', $info['device_category']);
        $this->assertSame('Unknown model', $info['model']);
    }

    public function test_ipad_is_a_tablet(): void
    {
        $info = UserAgentInfo::parse(self::IPAD_SAFARI);

        $this->assertSame('iOS', $info['os']);
        $this->assertSame('Tablet', $info['device_category']);
        $this->assertSame('Unknown model', $info['model']);
    }

    public function test_windows_edge_desktop_has_no_model_claim(): void
    {
        $info = UserAgentInfo::parse(self::WINDOWS_EDGE);

        $this->assertSame('Windows', $info['os']);
        $this->assertSame('10/11', $info['os_version']);
        $this->assertSame('Edge', $info['browser']);
        $this->assertSame('121', $info['browser_version']);
        $this->assertSame('Desktop', $info['device_category']);
        $this->assertNull($info['model']);
        $this->assertStringContainsString('Windows desktop', $info['display']);
    }

    public function test_firefox_on_linux_is_detected(): void
    {
        $info = UserAgentInfo::parse(self::FIREFOX_LINUX);

        $this->assertSame('Firefox', $info['browser']);
        $this->assertSame('122', $info['browser_version']);
        $this->assertSame('Linux', $info['os']);
        $this->assertSame('Desktop', $info['device_category']);
    }

    public function test_samsung_internet_is_not_mislabelled_as_plain_chrome(): void
    {
        $info = UserAgentInfo::parse(self::SAMSUNG_BROWSER);

        $this->assertSame('Samsung Internet', $info['browser']);
        $this->assertSame('21', $info['browser_version']);
        $this->assertSame('Mobile', $info['device_category']);
        $this->assertSame('Unknown model', $info['model'], 'SM- codes are hints, not identification.');
    }

    public function test_crawlers_are_classified_as_bots(): void
    {
        $info = UserAgentInfo::parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $this->assertSame('Bot', $info['device_category']);
    }

    public function test_empty_and_garbage_user_agents_degrade_to_unknown(): void
    {
        $empty = UserAgentInfo::parse(null);
        $this->assertSame('Unknown', $empty['browser']);
        $this->assertSame('Unknown', $empty['device_category']);
        $this->assertNull($empty['os']);
        $this->assertStringContainsString('Unknown', $empty['display']);

        $garbage = UserAgentInfo::parse('zzz-not-a-browser');
        $this->assertSame('Unknown', $garbage['browser']);
        $this->assertNotSame('Mobile', $garbage['device_category']);
        $this->assertStringContainsString('Unknown', $garbage['display']);

        $blank = UserAgentInfo::parse('   ');
        $this->assertSame('Unknown', $blank['browser']);
    }

    public function test_display_never_contains_a_model_for_any_mobile_profile(): void
    {
        foreach ([self::ANDROID_CHROME, self::IPHONE_SAFARI, self::IPAD_SAFARI, self::SAMSUNG_BROWSER] as $ua) {
            $info = UserAgentInfo::parse($ua);
            $this->assertStringNotContainsString('Pixel', $info['display']);
            $this->assertStringNotContainsString('SM-S918', $info['display']);
            $this->assertSame('Unknown model', $info['model']);
        }
    }
}
