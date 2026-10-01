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
    $dmLocale = session('locale');
    if (! in_array($dmLocale, $dmLocales, true)) {
        $dmLocale = 'sw';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $dmLocale }}" data-dm-locale="{{ $dmLocale }}">
