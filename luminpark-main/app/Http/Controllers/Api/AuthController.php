<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LinkAturUlangSandi;
use App\Models\LoginSession;
use App\Models\SystemLog;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    /** Lama berlaku tautan atur ulang kata sandi yang dikirim lewat email. */
    private const RESET_TOKEN_MENIT = 10;

    /**
     * Id token yang sedang dipakai. Sanctum menyimpan hash SHA-256 dari bagian rahasia
     * saja (bearer "id|rahasia"), jadi pencocokan manual lewat hash seluruh string tidak
     * pernah berhasil. Guard Sanctum sudah menyediakan modelnya lewat currentAccessToken().
     */
    private function currentTokenId(Request $request): ?string
    {
        $token = $request->user()?->currentAccessToken();

        return $token ? (string) $token->id : null;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Revisi 34: tiga kali salah untuk satu email dan IP mengunci akun selama
        // durasi yang dipilih super admin pada Pengaturan Sistem.
        if (LoginThrottle::terlaluBanyak($request)) {
            $tunggu = LoginThrottle::tunggu($request);

            return response()->json([
                'success' => false,
                'message' => 'Akun dikunci ' . SettingController::lockoutMinutes() . ' menit karena '
                    . LoginThrottle::ATTEMPTS . ' kali percobaan login gagal. Coba lagi dalam '
                    . max(1, (int) ceil($tunggu / 60)) . ' menit.',
                'retry_after' => $tunggu,
                'locked_until' => now()->addSeconds($tunggu)->toIso8601String(),
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            LoginThrottle::catatGagal($request);
            $this->logActivity($user?->id, 'login_failed', 'Percobaan login gagal untuk ' . $request->email, $request);

            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        if ($user->status !== User::STATUS_AKTIF) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin atau Monitoring Manajemen.',
            ], 403);
        }

        // Konsumen tidak memiliki akun login: seluruh interaksinya lewat halaman publik.
        if (! in_array($user->role, User::ROLE_INTERNAL, true)) {
            $this->logActivity($user->id, 'login_denied', 'Percobaan login oleh akun konsumen', $request);

            return response()->json([
                'success' => false,
                'message' => 'Akun konsumen tidak dapat masuk ke sistem. Silakan hubungi sales melalui tautan WhatsApp pada halaman publik.',
            ], 403);
        }

        $expiresAt = now()->addMinutes(SettingController::sessionTimeoutMinutes());
        $token = $user->createToken('auth_token', ['*'], $expiresAt);

        // Masuk yang berhasil tidak boleh menghabiskan jatah pembatas laju: yang dihitung
        // hanyalah percobaan gagal, sesuai ancaman penebakan sandi.
        LoginThrottle::bersihkan($request);

        LoginSession::create([
            'user_id'    => $user->id,
            'token_id'   => (string) $token->accessToken->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at'   => now(),
            'status'     => LoginSession::STATUS_ACTIVE,
        ]);

        $this->logActivity($user->id, 'login', 'User berhasil login', $request);

        return response()->json([
            'success'         => true,
            'message'         => 'Login berhasil',
            'access_token'    => $token->plainTextToken,
            'token_type'      => 'Bearer',
            'token_expires_at'=> $expiresAt->toIso8601String(),
            'user'            => $user->load('salesProfile'),
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $tokenId = $this->currentTokenId($request) ?: optional($user->currentAccessToken())->id;

        $session = LoginSession::where('user_id', $user->id)
            ->where('token_id', (string) $tokenId)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first();

        if (!$session) {
            // Sesi lama tanpa padanan tetap dianggap berakhir.
            LoginSession::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->update(['logout_at' => now(), 'status' => LoginSession::STATUS_ENDED]);
        } else {
            $session->update([
                'logout_at' => now(),
                'status'    => LoginSession::STATUS_ENDED,
            ]);
        }

        $this->logActivity($user->id, 'logout', 'User berhasil logout', $request);

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user()->load('salesProfile'),
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'         => 'required|email',
            'password_lama' => 'required|string',
            // Revisi 8: kata sandi baru wajib huruf besar + huruf kecil + angka, minimal 8 karakter.
            'password_baru' => [
                'required', 'different:password_lama',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/',
            ],
        ]);

        $user = $request->user();

        if ($user->email !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak sesuai dengan akun yang sedang login',
            ], 403);
        }

        if (! Hash::check($request->password_lama, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai',
            ], 400);
        }

        $user->update([
            'password'              => Hash::make($request->password_baru),
            'must_change_password'  => false,
        ]);

        // Sesi aktif lain dicabut supaya password baru langsung berlaku.
        $user->tokens()->delete();

        $this->logActivity($user->id, 'reset_password', 'User berhasil mereset password', $request);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan login kembali.',
        ], 200);
    }

    /**
     * Langkah "kirim verifikasi" pada alur lupa password. Permintaan ini datang dari tamu,
     * jadi balasannya sengaja samar: email yang tidak terdaftar dan email milik konsumen
     * menjawab hal yang sama dengan email yang sah, agar halaman ini tidak dipakai untuk
     * memeriksa apakah sebuah akun ada di sistem.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $pesanUmum = 'Jika email tersebut terdaftar pada akun internal Luminpark, tautan verifikasi sudah dikirim ke kotak masuk Anda.';

        $user = User::where('email', $request->email)->first();

        if (! $user
            || ! in_array($user->role, User::ROLE_INTERNAL, true)
            || $user->status !== User::STATUS_AKTIF) {
            $this->logActivity($user?->id, 'forgot_password', 'Permintaan tautan atur ulang sandi ditolak tanpa notifikasi ke email ' . $request->email, $request);

            return response()->json([
                'success' => true,
                'message' => $pesanUmum,
            ], 200);
        }

        $token = Str::random(64);

        // Satu email hanya menyimpan satu tautan aktif: permintaan baru mengganti token lama.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        $url = $request->getSchemeAndHttpHost()
            . '/reset-password?token=' . $token
            . '&email=' . rawurlencode($user->email);

        try {
            Mail::to($user->email)->send(new LinkAturUlangSandi($user->name, $url, self::RESET_TOKEN_MENIT));
        } catch (Throwable $e) {
            Log::warning('Tautan atur ulang kata sandi gagal dikirim: ' . $e->getMessage());
            $this->logActivity($user->id, 'forgot_password_gagal', 'Tautan atur ulang sandi gagal dikirim ke ' . $user->email, $request);

            return response()->json([
                'success' => false,
                'message' => 'Tautan verifikasi gagal dikirim. Periksa pengaturan email sistem atau hubungi administrator.',
            ], 502);
        }

        $this->logActivity($user->id, 'forgot_password', 'Tautan atur ulang sandi dikirim ke ' . $user->email, $request);

        return response()->json([
            'success' => true,
            'message' => $pesanUmum,
            'data'    => ['kedaluwarsa_menit' => self::RESET_TOKEN_MENIT],
        ], 200);
    }

    /**
     * Langkah "simpan" pada alur lupa password: kata sandi baru diterima bersama token dari
     * email, tanpa pernah meminta kata sandi lama.
     */
    public function resetPasswordWithToken(Request $request)
    {
        $request->validate([
            'email'         => 'required|email',
            'token'         => 'required|string',
            // Revisi 8: kata sandi baru wajib huruf besar + huruf kecil + angka, minimal 8 karakter.
            'password_baru' => ['required', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/'],
        ]);

        $baris = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', hash('sha256', $request->token))
            ->first();

        if (! $baris) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan verifikasi tidak valid. Silakan minta tautan baru.',
            ], 400);
        }

        if (Carbon::parse($baris->created_at)->addMinutes(self::RESET_TOKEN_MENIT)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Tautan verifikasi sudah kedaluwarsa. Silakan minta tautan baru.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! in_array($user->role, User::ROLE_INTERNAL, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak memiliki akses ke sistem. Hubungi administrator.',
            ], 403);
        }

        $user->update([
            'password'             => Hash::make($request->password_baru),
            'must_change_password' => false,
        ]);

        // Tautan sekali pakai, lalu seluruh akses lama dicabut agar sandi baru langsung berlaku.
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        LoginSession::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->update(['logout_at' => now(), 'status' => LoginSession::STATUS_ENDED]);

        $this->logActivity($user->id, 'reset_password', 'Kata sandi diubah melalui tautan verifikasi email', $request);

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.',
        ], 200);
    }

    public function sessions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => LoginSession::where('user_id', $user->id)
                ->orderByDesc('login_at')
                ->limit(20)
                ->get(),
        ], 200);
    }

    /**
     * Sisa waktu token saat ini, dipakai penghitung waktu di bilah atas aplikasi.
     */
    public function sessionStatus(Request $request)
    {
        $kedaluwarsa = $this->kedaluwarsaToken($request);

        return response()->json([
            'success' => true,
            'data' => [
                'expires_at' => $kedaluwarsa?->toIso8601String(),
                'remaining_seconds' => $kedaluwarsa ? max(0, now()->diffInSeconds($kedaluwarsa, false)) : null,
                'session_timeout_minutes' => SettingController::sessionTimeoutMinutes(),
            ],
        ], 200);
    }

    /**
     * Memperpanjang sesi dengan token baru: token lama dicabut agar tidak ada dua
     * akses aktif untuk satu pengguna, dan sesi login ditutup lalu dibuka kembali
     * supaya riwayat sesi tetap mencerminkan satu akses yang sedang dipakai.
     */
    public function refreshSession(Request $request)
    {
        $user = $request->user();
        $tokenIdLama = $this->currentTokenId($request);

        $expiresAt = now()->addMinutes(SettingController::sessionTimeoutMinutes());
        $token = $user->createToken('auth_token', ['*'], $expiresAt);

        if ($tokenIdLama) {
            DB::table('personal_access_tokens')->where('id', $tokenIdLama)->delete();

            LoginSession::where('user_id', $user->id)
                ->where('token_id', (string) $tokenIdLama)
                ->whereNull('logout_at')
                ->update(['logout_at' => now(), 'status' => LoginSession::STATUS_ENDED]);
        }

        LoginSession::create([
            'user_id'    => $user->id,
            'token_id'   => (string) $token->accessToken->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at'   => now(),
            'status'     => LoginSession::STATUS_ACTIVE,
        ]);

        $this->logActivity($user->id, 'session_refresh', 'User memperpanjang sesi login', $request);

        return response()->json([
            'success' => true,
            'message' => 'Sesi berhasil diperpanjang.',
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'token_expires_at' => $expiresAt->toIso8601String(),
        ], 200);
    }

    private function kedaluwarsaToken(Request $request): ?\DateTimeInterface
    {
        $tokenId = $this->currentTokenId($request);

        if (! $tokenId) {
            return null;
        }

        $kedaluwarsa = DB::table('personal_access_tokens')->where('id', $tokenId)->value('expires_at');

        return $kedaluwarsa ? \Illuminate\Support\Carbon::parse($kedaluwarsa) : null;
    }

    private function logActivity(?int $userId, string $action, string $description, Request $request): void
    {
        SystemLog::create([
            'user_id'     => $userId,
            'activity'    => $action,
            'description' => $description,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);
    }
}
