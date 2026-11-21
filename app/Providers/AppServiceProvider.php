<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Cluster;
use App\Models\CustomerLead;
use App\Models\Payment;
use App\Models\PaymentScheme;
use App\Models\Project;
use App\Models\SalesProfile;
use App\Models\Unit;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Model yang dituliskan jejak auditnya ke tabel activity_logs. */
    private const AUDITABLE = [
        Booking::class,
        Cluster::class,
        CustomerLead::class,
        Payment::class,
        PaymentScheme::class,
        Project::class,
        SalesProfile::class,
        Unit::class,
        User::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::AUDITABLE as $model) {
            $model::observe(AuditObserver::class);
        }

        // Ganti/reset sandi dan taut akun Google ikut dibatasi, sepuluh kali per menit.
        RateLimiter::for('auth-password', function (Request $request) {
            $kunci = 'auth-password|' . ($request->user()?->id ?? 'tamu') . '|' . $request->ip();

            return Limit::perMinute(10)
                ->by($kunci)
                ->response(function ($request, array $headers = []) {
                    $tunggu = (int) ($headers['Retry-After'] ?? 60);

                    return response()->json([
                        'success' => false,
                        'message' => 'Terlalu banyak percobaan terkait kata sandi. '
                            . 'Coba lagi dalam ' . $tunggu . ' detik.',
                        'retry_after' => $tunggu,
                    ], 429);
                });
        });

        // Permintaan tautan atur ulang sandi dibatasi sendiri: yang mau dihemat adalah
        // kuota pengiriman email, bukan percobaan menebak token (tokennya acak 64 karakter).
        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(6)
                ->by('forgot-password|' . $request->ip())
                ->response(function ($request, array $headers = []) {
                    $tunggu = (int) ($headers['Retry-After'] ?? 60);

                    return response()->json([
                        'success' => false,
                        'message' => 'Terlalu banyak permintaan tautan verifikasi. '
                            . 'Coba lagi dalam ' . $tunggu . ' detik.',
                        'retry_after' => $tunggu,
                    ], 429);
                });
        });
    }
}
