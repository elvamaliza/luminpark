<?php

namespace App\Support;

use App\Http\Controllers\Api\SettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Penghitung percobaan masuk yang gagal untuk satu kombinasi email dan alamat IP.
 * Sengaja tidak memakai middleware throttle pada rute /login: middleware mencatat
 * setiap permintaan sehingga pengguna yang berhasil masuk berulang kali ikut
 * menghabiskan jatah, padahal yang perlu dibatasi hanyalah percobaan salah.
 */
class LoginThrottle
{
    /** Revisi 34: tiga kali salah sudah cukup menghentikan percobaan berikutnya. */
    public const ATTEMPTS = 3;

    public static function kunci(Request $request): string
    {
        $email = $request->input('email');

        // Nilai bukan teks (mis. email[]) tidak boleh ikut membentuk kunci karena
        // kunci harus tetap satu nilai yang stabil untuk setiap percobaan.
        $email = is_string($email) ? Str::transliterate(Str::lower($email)) : '-';

        return 'login|' . $email . '|' . ($request->ip() ?? '-');
    }

    public static function terlaluBanyak(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(self::kunci($request), self::ATTEMPTS);
    }

    /** Detik tersisa sebelum akun dapat dicoba kembali. */
    public static function tunggu(Request $request): int
    {
        return RateLimiter::availableIn(self::kunci($request));
    }

    /**
     * Masa tunggu dihitung ulang setiap kegagalan, jadi kunci akun selalu sepanjang
     * nilai "Durasi penguncian" yang berlaku pada percobaan terakhir.
     */
    public static function catatGagal(Request $request): void
    {
        RateLimiter::hit(self::kunci($request), SettingController::lockoutMinutes() * 60);
    }

    public static function bersihkan(Request $request): void
    {
        RateLimiter::clear(self::kunci($request));
    }
}
