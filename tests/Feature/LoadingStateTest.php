<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * One loading animation for the whole website.
 *
 * Every page that tells the visitor to wait shows the same SmartCart: the
 * stylesheet /css/smartcart-loader.css, a `.dm-sc` cart, and a caption read
 * from site_common.loading. Two ways it was broken before:
 *
 *  - the page never carried the loader at all (a bare "Loading..." line, or
 *    shimmer skeleton cards), so there was simply no animation;
 *  - the page carried the cart but no rule gave it a width/height. The cart is
 *    drawn with a mask and paints no background, so an unsized cart has
 *    nothing to draw in and is invisible - it looks exactly like a page with
 *    no loader at all.
 *
 * These tests pin the loader on every page that loads something, pin the box it
 * needs, and pin the caption's translation.
 */
class LoadingStateTest extends TestCase
{
    /** Pages whose first paint is the loading state. */
    private const LOADING_PAGES = [
        '/', '/shop', '/advertisements', '/businesses',
        '/msimamizi', '/msimamizi/preview', '/msimamizi/bidhaa-mpya',
        '/msimamizi/nyingine', '/mteja', '/mteja/biashara', '/mteja/matangazo',
        '/mteja/profaili', '/muuzaji', '/muuzaji/mauzo', '/muuzaji/matumizi',
        '/muuzaji/uza', '/muuzaji/ushauri-bei', '/muuzaji/mpango-stock',
        '/muuzaji/tangaza', '/system_admin', '/system_admin/dashboard',
        '/system_admin/notify',
    ];

    /**
     * Pages whose loader is only built from script (a submitted Track Orders
     * lookup, a question to the assistant, an opened order), so no cart is in
     * the first paint and the size has to come from CSS the page ships with.
     */
    private const RUNTIME_LOADER_PAGES = ['/track-orders', '/mteja/nyingine', '/muuzaji/orders'];

    /** The three placeholders that used to be a bare "Loading..." line. */
    private const PLACEHOLDER_PAGES = ['/msimamizi', '/msimamizi/preview', '/msimamizi/bidhaa-mpya'];

    public function test_every_loading_page_carries_the_shared_animation(): void
    {
        foreach (self::LOADING_PAGES as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(
                '/css/smartcart-loader.css',
                $html,
                "{$path} must load the shared SmartCart stylesheet."
            );

            $this->assertStringContainsString(
                'class="loading-spinner dm-sc"',
                $html,
                "{$path} must paint the SmartCart"
            );

            $this->assertStringNotContainsString(
                'dm-skeleton',
                $html,
                "{$path} must show the SmartCart, not skeleton cards."
            );
        }
    }

    public function test_a_loader_built_at_runtime_still_gets_a_box_to_draw_in(): void
    {
        // Either the page describes .loading-spinner (a width for the cart) or
        // it sizes the element inline. Without one of the two the cart is
        // invisible, which is the regression this guards.
        $sized = '/(\.loading-spinner\s*\{[^}]*width\s*:)|(dm-sc[^>]*style="[^"]*width\s*:)/';

        foreach (self::RUNTIME_LOADER_PAGES as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(
                '/css/smartcart-loader.css',
                $html,
                "{$path} must load the shared SmartCart stylesheet."
            );

            $this->assertMatchesRegularExpression(
                $sized,
                $html,
                "{$path} builds its loader from script, so it must give the cart a box."
            );
        }
    }

    public function test_the_cart_comes_before_the_caption_the_placeholders_used_to_show_alone(): void
    {
        foreach (self::PLACEHOLDER_PAGES as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $cartAt = strpos($html, 'class="loading-spinner dm-sc"');
            $this->assertNotFalse($cartAt, "{$path} must paint the SmartCart while it loads.");

            preg_match('/data-i18n="[^"]*loading[^"]*"/', $html, $caption, PREG_OFFSET_CAPTURE);
            $this->assertNotEmpty($caption, "{$path} is expected to caption its loading state.");

            $this->assertTrue(
                $cartAt < $caption[0][1],
                "{$path} must draw the cart with its caption, never the caption on its own."
            );
        }
    }

    public function test_the_caption_is_translated_in_every_language(): void
    {
        $catalog = json_decode(file_get_contents(base_path('locales.json')), true);
        $translations = $catalog['translations'];

        $this->assertCount(8, $translations, 'The loader caption must cover the eight site locales.');

        foreach ($translations as $locale => $body) {
            $caption = $body['site_common']['loading'] ?? null;

            $this->assertIsString($caption, "site_common.loading is missing for {$locale}.");
            $this->assertNotSame('', trim($caption), "site_common.loading is blank for {$locale}.");
        }

        $this->assertSame('Inapakia...', $translations['sw']['site_common']['loading']);
        $this->assertSame('Loading...', $translations['en']['site_common']['loading']);
    }

    public function test_the_browser_reads_that_caption_from_the_locale_endpoint(): void
    {
        // /locales.json is a BinaryFileResponse (the file is streamed, not
        // rendered into the test response), so resolve the served file and read
        // the caption out of it the way the language widget does.
        $response = $this->get('/locales.json')->assertOk();

        $served = $response->baseResponse->getFile()->getPathname();
        $this->assertStringEndsWith('locales.json', $served, 'The language widget must read the project catalogue.');

        $data = json_decode(file_get_contents($served), true);
        $this->assertSame('Loading...', $data['translations']['en']['site_common']['loading']);
    }
}
