<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Website language system
|--------------------------------------------------------------------------
| The supported locale codes mirror the mobile application exactly
| (Duka_mkononi/context/LanguageContext.tsx) — never add or remove one.
| `locales.json` (project root) is the single translation source and is
| served to the browser so pages can translate themselves client-side,
| while the chosen locale is kept in the Laravel session so it survives
| navigation and refreshes across every page.
*/
$dukamkononiLocales = ['sw', 'en', 'fr', 'hi', 'es', 'ur', 'de', 'zh'];

Route::get('/locales.json', function () {
    return response()->file(base_path('locales.json'), [
        'Content-Type' => 'application/json',
    ]);
});

Route::get('/language/{code}', function (string $code) use ($dukamkononiLocales) {
    if (! in_array($code, $dukamkononiLocales, true)) {
        return response()->json(['ok' => false, 'error' => 'Unsupported language'], 422);
    }

    session(['locale' => $code]);

    return response()->json(['ok' => true, 'locale' => $code]);
});

Route::view('/', 'index');
Route::view('/home', 'home');

// Public shopping experience (website-only)
Route::view('/shop', 'shop');
Route::view('/track-orders', 'track-orders');
Route::view('/advertisements', 'advertisements');
Route::view('/businesses', 'businesses');
Route::view('/login', 'login');
Route::view('/forgot', 'forgot');
Route::view('/admin-signup', 'admin-signup');
Route::view('/cashier-signup', 'cashier-signup');
Route::view('/client-signup', 'client-signup');

Route::view('/msimamizi', 'msimamizi.index');
Route::view('/msimamizi/index', 'msimamizi.index');
Route::view('/msimamizi/bidhaa-mpya', 'msimamizi.bidhaa-mpya');
Route::view('/msimamizi/ripoti', 'msimamizi.ripoti');
Route::view('/msimamizi/preview', 'msimamizi.preview');
Route::view('/msimamizi/tangaza', 'msimamizi.tangaza');

Route::view('/mteja', 'mteja.biashara');
Route::view('/mteja/biashara', 'mteja.biashara');
Route::view('/mteja/matangazo', 'mteja.matangazo');
Route::view('/mteja/profaili', 'mteja.profaili');

Route::view('/muuzaji', 'muuzaji.profaili');
Route::view('/muuzaji/profaili', 'muuzaji.profaili');
Route::view('/muuzaji/orders', 'muuzaji.orders');
Route::view('/muuzaji/mauzo', 'muuzaji.mauzo');
Route::view('/muuzaji/matumizi', 'muuzaji.matumizi');
Route::view('/muuzaji/uza', 'muuzaji.uza');

Route::view('/system_admin', 'system_admin.index');
Route::view('/system_admin/index', 'system_admin.index');
Route::view('/system_admin/dashboard', 'system_admin.dashboard');
Route::view('/system_admin/notify', 'system_admin.notify');
Route::view('/system_admin/inquiries', 'system_admin.inquiries');

Route::redirect('/bidhaa-mpya', '/msimamizi/bidhaa-mpya');
Route::redirect('/ripoti', '/msimamizi/ripoti');
Route::redirect('/preview', '/msimamizi/preview');
Route::redirect('/tangaza', '/msimamizi/tangaza');
Route::redirect('/biashara', '/mteja/biashara');
Route::redirect('/matangazo', '/mteja/matangazo');
Route::redirect('/mauzo', '/muuzaji/mauzo');
Route::redirect('/uza', '/muuzaji/uza');
Route::redirect('/matumizi', '/muuzaji/matumizi');
Route::redirect('/profaili', '/muuzaji/profaili');