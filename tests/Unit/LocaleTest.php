<?php

namespace Tests\Unit;

use App\Services\Ai\Locale;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Capability #22 - Truly Multilingual Assistant.
 *
 * What these tests pin:
 *  - the allow-list is exactly the eight catalogue languages, and a code
 *    outside it can never reach an AI capability;
 *  - the documented fallback order (request field -> account language ->
 *    configured default) - the behaviour the old 21 duplicated copies did not
 *    have;
 *  - Urdu is the only RTL language and every locale maps to a BCP-47 tag;
 *  - number/amount/date formatting is genuinely locale-specific rather than a
 *    single hard-coded style;
 *  - every AI controller now uses the shared resolver instead of carrying its
 *    own copy - this is the guard against the drift the capability was created
 *    to remove.
 */
class LocaleTest extends TestCase
{
    private const LOCALES = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

    public function test_the_allow_list_is_the_eight_catalogue_languages(): void
    {
        $this->assertSame(self::LOCALES, Locale::supported());
    }

    public function test_normalize_accepts_only_allow_listed_codes(): void
    {
        $this->assertSame('fr', Locale::normalize(' FR '));
        $this->assertSame('zh', Locale::normalize('zh'));
        $this->assertNull(Locale::normalize('xx'));
        $this->assertNull(Locale::normalize(''));
        $this->assertNull(Locale::normalize(null));
        $this->assertNull(Locale::normalize('english'));
    }

    public function test_only_urdu_is_right_to_left(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->assertSame(
                $locale === 'ur',
                Locale::isRtl($locale),
                "{$locale} RTL flag is wrong."
            );
            $this->assertSame($locale === 'ur' ? 'rtl' : 'ltr', Locale::direction($locale));
        }

        // An unknown code is never RTL (and never throws).
        $this->assertFalse(Locale::isRtl('xx'));
        $this->assertSame('ltr', Locale::direction('xx'));
    }

    public function test_every_locale_maps_to_a_bcp47_tag(): void
    {
        $expected = [
            'sw' => 'sw-TZ', 'en' => 'en-TZ', 'fr' => 'fr-FR', 'hi' => 'hi-IN',
            'es' => 'es-ES', 'ur' => 'ur-PK', 'de' => 'de-DE', 'zh' => 'zh-CN',
        ];

        foreach ($expected as $code => $tag) {
            $this->assertSame($tag, Locale::tag($code));
        }

        // An unknown code falls back to the default's tag, never to a broken one.
        $this->assertSame(Locale::tag(Locale::default()), Locale::tag('xx'));
    }

    public function test_resolve_prefers_the_request_then_the_account_then_the_default(): void
    {
        $withRequest = Request::create('/api/ai/test', 'POST', ['locale' => 'fr']);
        $this->assertSame('fr', Locale::resolve($withRequest, 'de'));

        // No usable request value -> the caller's stored account language.
        $noRequest = Request::create('/api/ai/test', 'POST');
        $this->assertSame('de', Locale::resolve($noRequest, 'de'));
        $this->assertSame('de', Locale::resolve(null, 'de'));

        // Nothing usable anywhere -> the documented default.
        $this->assertSame(Locale::default(), Locale::resolve($noRequest, null));
        $this->assertSame(Locale::default(), Locale::resolve(null, 'not-a-locale'));
    }

    public function test_an_unsupported_request_locale_is_ignored_not_trusted(): void
    {
        $request = Request::create('/api/ai/test', 'POST', ['locale' => 'xx']);

        // Falls through to the account language rather than echoing "xx".
        $this->assertSame('hi', Locale::resolve($request, 'hi'));
        $this->assertSame(Locale::default(), Locale::resolve($request, null));
    }

    public function test_the_documented_default_is_configurable(): void
    {
        config(['ai.default_locale' => 'de']);
        $this->assertSame('de', Locale::default());
        $this->assertSame('de', Locale::resolve(null, null));

        // An invalid configured default still cannot escape the allow-list.
        config(['ai.default_locale' => 'klingon']);
        $this->assertSame('sw', Locale::default());
    }

    public function test_numbers_use_each_locales_separators(): void
    {
        $this->assertSame('1,234,567', Locale::number(1234567, 'sw'));
        $this->assertSame('1,234,567', Locale::number(1234567, 'en'));
        $this->assertSame('1,234,567', Locale::number(1234567, 'zh'));
        $this->assertSame('1.234.567', Locale::number(1234567, 'de'));
        $this->assertSame('1.234.567', Locale::number(1234567, 'es'));
        $this->assertSame("1\u{202F}234\u{202F}567", Locale::number(1234567, 'fr'));
        // Hindi uses lakh/crore grouping rather than three-digit grouping.
        $this->assertSame('12,34,567', Locale::number(1234567, 'hi'));

        $this->assertSame('1,234.50', Locale::number(1234.5, 'en', 2));
        $this->assertSame('1.234,50', Locale::number(1234.5, 'de', 2));
        $this->assertSame('-1,234', Locale::number(-1234, 'en'));
    }

    public function test_amounts_carry_the_configured_currency(): void
    {
        config(['ai.currency' => 'TZS']);

        $this->assertSame('TZS 1,234', Locale::amount(1234, 'en'));
        $this->assertSame('TZS 1.234', Locale::amount(1234, 'de'));
        $this->assertSame('TZS 12,34,567', Locale::amount(1234567, 'hi'));
    }

    public function test_dates_use_the_locale_pattern(): void
    {
        $this->assertSame('14/10/2026', Locale::date('2026-10-14', 'en'));
        $this->assertSame('14/10/2026', Locale::date('2026-10-14', 'sw'));
        $this->assertSame('14.10.2026', Locale::date('2026-10-14', 'de'));
        $this->assertSame('2026-10-14', Locale::date('2026-10-14', 'zh'));
        $this->assertSame('Y-m-d', Locale::datePattern('zh'));
        $this->assertSame('d.m.Y', Locale::datePattern('de'));
        $this->assertSame('d/m/Y', Locale::datePattern('ur'));
    }

    /**
     * The whole point of the capability: the rule lives once. Every AI
     * controller must resolve its locale through BaseController (the single
     * implementation), and the health digest's business-locale variant must
     * delegate to the same service rather than re-implement the rule.
     */
    public function test_every_ai_controller_uses_the_shared_resolver(): void
    {
        $base = 'App\\Http\\Controllers\\Api\\BaseController';

        $files = glob(base_path('app/Http/Controllers/Api/Ai*.php'));
        $this->assertNotEmpty($files, 'Expected to find the AI controllers.');

        foreach ($files as $file) {
            $class = 'App\\Http\\Controllers\\Api\\'.basename($file, '.php');
            $this->assertTrue(class_exists($class), "{$class} must be loadable.");

            $method = new \ReflectionMethod($class, 'resolveLocale');
            $this->assertSame(
                $base,
                $method->getDeclaringClass()->getName(),
                "{$class} must not carry its own resolveLocale() - use BaseController's shared one."
            );
            $this->assertTrue($method->isProtected(), "{$class}::resolveLocale must be the shared protected method.");
        }

        // The health digest has an extra business-locale fallback; even that
        // private variant must go through Locale rather than duplicate the rule.
        $health = 'App\\Http\\Controllers\\Api\\AiHealthController';
        $this->assertTrue(method_exists($health, 'resolveLocaleForBusiness'));
        $this->assertStringContainsString(
            'Locale::resolve(',
            file_get_contents(base_path('app/Http/Controllers/Api/AiHealthController.php')),
            'AiHealthController::resolveLocaleForBusiness must delegate to Locale::resolve().'
        );
    }
}
