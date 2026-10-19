{{--
    Dukamkononi website locale.

    Usage in a page Blade file: include this partial as the very first thing,
    before the verbatim block that wraps the page markup. It emits the doctype
    and the opening html tag in the language the visitor last chose.

    The language picked with the floating button is written to the Laravel
    session by routes/web.php (GET /language/{code}), so every page renders in
    that language instead of snapping back to Swahili. The allowed codes mirror
    the mobile app (Duka_mkononi/context/LanguageContext.tsx) and must match the
    allow-list in routes/web.php and in the language widget partial.
--}}
@php
    $dmLocales = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];
    // Capability #22: the one right-to-left language in the catalogue. The
    // `dir` attribute is what browsers use to mirror alignment, forms, modals
    // and flex rows, so it belongs on <html>, not per-element.
    $dmRtlLocales = ['ur'];
    $dmLocale = session('locale');
    if (! in_array($dmLocale, $dmLocales, true)) {
        $dmLocale = 'sw';
    }
    $dmDirection = in_array($dmLocale, $dmRtlLocales, true) ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $dmLocale }}" dir="{{ $dmDirection }}" data-dm-locale="{{ $dmLocale }}" data-theme="white-professional">
{{-- Theme restore, runs before first paint so a visitor who chose any approved
     theme never sees a flash of the default design. The allow-list must mirror
     public/js/dm-theme.js (THEMES) and app/Services/ThemePreference.php — a theme
     missing here is remembered but silently dropped on every other page. The
     stored value is only ever COMPARED to the approved identifiers — raw
     storage/request data is never written into markup, attributes or CSS, so
     localStorage cannot inject anything. --}}
<script>
(function () {
    var approved = ['white-professional', 'royal-champagne-cream', 'midnight-royal-gold', 'emerald-bazaar', 'oceanic-sapphire'];
    try {
        var stored = window.localStorage.getItem('dukamkononi_theme');
        if (approved.indexOf(stored) === -1) {
            // Pre-release key: migrate the value once, then restore it too.
            stored = window.localStorage.getItem('lukamkononi_theme');
            if (approved.indexOf(stored) > -1) {
                window.localStorage.setItem('dukamkononi_theme', stored);
            }
        }
        if (approved.indexOf(stored) > -1) {
            document.documentElement.setAttribute('data-theme', stored);
        }
    } catch (e) { /* storage unavailable: stay on White Professional */ }
})();
</script>
{{-- Activity monitoring (advanced user activity monitoring & security audit
     system - see MONITORING.md). ONE shared script included from this
     universal partial covers every page: it delegates from document level, so
     dynamically rendered controls/forms are instrumented without per-page
     code and rerenders never duplicate listeners. It records interaction
     METADATA only (never field values, query text, file names or contents),
     batches requests, and stops entirely after logout. The server treats
     everything it sends as untrusted. --}}
<script src="/js/dm-monitor.js" defer></script>
