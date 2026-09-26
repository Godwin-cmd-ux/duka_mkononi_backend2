<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'index');
Route::view('/home', 'home');
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
Route::view('/muuzaji/mauzo', 'muuzaji.mauzo');
Route::view('/muuzaji/matumizi', 'muuzaji.matumizi');
Route::view('/muuzaji/uza', 'muuzaji.uza');

Route::view('/system_admin', 'system_admin.index');
Route::view('/system_admin/index', 'system_admin.index');
Route::view('/system_admin/dashboard', 'system_admin.dashboard');
Route::view('/system_admin/notify', 'system_admin.notify');

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