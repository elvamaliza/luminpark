<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Halaman publik (konsumen) — tanpa login
|--------------------------------------------------------------------------
*/
Route::view('/', 'public.beranda')->name('public.beranda');
Route::view('/unit', 'public.daftar-unit')->name('public.unit');
Route::view('/unit/{id}', 'public.detail-unit')->name('public.unit-detail');
Route::view('/skema-pembayaran', 'public.skema')->name('public.skema');
Route::view('/kontak', 'public.kontak')->name('public.kontak');
Route::view('/tentang', 'public.tentang')->name('public.tentang');

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
*/
Route::view('/login', 'auth.login')->name('login');
Route::view('/reset-password', 'auth.reset-password')->name('reset-password');
Route::view('/ganti-sandi', 'auth.ganti-sandi')->name('ganti-sandi');

// Masuk dengan Google: pengalihan browser, bukan panggilan fetch, jadi ada di grup web.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

/*
|--------------------------------------------------------------------------
| Halaman internal — dijaga token Sanctum di sisi klien (layouts/app.blade.php)
|--------------------------------------------------------------------------
*/
Route::view('/dashboard', 'app.dashboard')->name('app.dashboard');
Route::view('/data-unit', 'app.data-unit')->name('app.data-unit');
Route::view('/data-unit/{id}', 'app.detail-unit')->name('app.detail-unit');
Route::view('/data-sales', 'app.data-sales')->name('app.data-sales');
Route::view('/data-sales/{id}', 'app.detail-sales')->name('app.detail-sales');
Route::view('/data-konsumen', 'app.data-konsumen')->name('app.data-konsumen');
Route::view('/data-konsumen/{id}', 'app.detail-konsumen')->name('app.detail-konsumen');
Route::view('/booking', 'app.booking')->name('app.booking');
Route::view('/pembayaran', 'app.pembayaran')->name('app.pembayaran');
Route::view('/laporan', 'app.laporan')->name('app.laporan');
Route::view('/laporan-sales', 'app.laporan-sales')->name('app.laporan-sales');
Route::view('/pengaturan', 'app.pengaturan')->name('app.pengaturan');
Route::view('/pengaturan-publik', 'app.pengaturan-publik')->name('app.pengaturan-publik');
Route::view('/profil', 'app.profil')->name('app.profil');
Route::view('/notifikasi', 'app.notifikasi')->name('app.notifikasi');
Route::view('/sesi', 'app.sesi')->name('app.sesi');
Route::view('/log-aktivitas', 'app.log-aktivitas')->name('app.log-aktivitas');
