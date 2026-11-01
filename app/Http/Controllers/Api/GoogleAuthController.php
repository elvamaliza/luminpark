<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginSession;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Masuk dengan Google memakai alur authorization code + OIDC.
 * Akun Google hanya diterima bila emailnya persis sama dengan akun staf internal
 * yang sudah dibuatkan pengelola; tidak ada akun baru yang dibuat otomatis.
 */
class GoogleAuthController extends Controller
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const STATE_TTL_SECONDS = 600;

    public static function enabled(): bool
    {
        return Setting::value('google_login_enabled') === '1'
            && (bool) Setting::value('google_client_id')
            && (bool) Setting::value('google_client_secret');
    }

    public static function redirectUri(): string
    {
        return url('/auth/google/callback');
    }

    /** GET /api/public/auth-config — halaman login memakai ini untuk mengaktifkan tombolnya. */
    public function config()
    {
        return response()->json([
            'success' => true,
            'data'    => ['google_login_enabled' => self::enabled()],
        ], 200);
    }

    /** GET /auth/google/redirect */
    public function redirect(Request $request)
    {
        if (! self::enabled()) {
            return redirect(self::loginUrl('belum-diaktifkan'));
        }

        $state = Str::random(40);
        $ticket = (string) $request->query('ticket', '');
        $userId = $ticket !== '' ? Cache::pull('lp_google_link_' . $ticket) : null;

        Cache::put(
            'lp_google_state_' . $state,
            $userId ? ['intent' => 'link', 'user_id' => (int) $userId] : true,
            now()->addSeconds(self::STATE_TTL_SECONDS),
        );

        $params = http_build_query([
            'client_id'     => Setting::value('google_client_id'),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        return redirect()->away(self::AUTH_URL . '?' . $params);
    }

    /** GET /auth/google/callback */
    public function callback(Request $request)
    {
        if (! self::enabled()) {
            return redirect(self::loginUrl('belum-diaktifkan'));
        }

        if ($request->filled('error')) {
            return redirect(self::loginUrl('ditolak-pengguna'));
        }

        $code  = (string) $request->input('code', '');
        $state = (string) $request->input('state', '');

        $maksud = $code === '' || $state === '' ? null : Cache::pull('lp_google_state_' . $state);

        if (! $maksud) {
            return redirect(self::loginUrl('state-tidak-valid'));
        }

        try {
            $accessToken = $this->exchangeCode($code);

            if (!$accessToken) {
                return redirect(self::loginUrl('pertukaran-gagal'));
            }

            $profile = $this->fetchProfile($accessToken);
        } catch (\Throwable $e) {
            report($e);

            return redirect(self::loginUrl('server-google-tidak-terjangkau'));
        }

        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        if ($email === '' || empty($profile['email_verified'])) {
            return redirect(self::loginUrl('email-belum-diverifikasi'));
        }

        if (is_array($maksud) && ($maksud['intent'] ?? '') === 'link') {
            return $this->finishLink((int) $maksud['user_id'], $email, $profile);
        }

        $user = User::where('email', $email)->first();

        if (!$user || ! in_array($user->role, User::ROLE_INTERNAL, true)) {
            return redirect(self::loginUrl('bukan-akun-internal'));
        }

        if ($user->status !== User::STATUS_AKTIF) {
            return redirect(self::loginUrl('akun-nonaktif'));
        }

        if ($user->google_id && $user->google_id !== ($profile['sub'] ?? null)) {
            return redirect(self::loginUrl('google-sudah-tertaut'));
        }

        $user->forceFill(['google_id' => $profile['sub'] ?? null])->save();

        $token = $this->issueSession($user, $request, 'login_google');

        return redirect(self::loginUrl(null, $token));
    }

    /**
     * POST /api/auth/google/link — halaman Profil meminta URL pengalihan
     * untuk menautkan akun Google ke akun yang sedang masuk.
     */
    public function linkStart(Request $request)
    {
        if (! self::enabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Masuk dengan Google belum diaktifkan oleh Manajer.',
            ], 422);
        }

        $ticket = Str::random(40);
        Cache::put('lp_google_link_' . $ticket, $request->user()->id, now()->addSeconds(self::STATE_TTL_SECONDS));

        return response()->json([
            'success' => true,
            'data'    => ['url' => '/auth/google/redirect?intent=link&ticket=' . $ticket],
        ], 200);
    }

    /** DELETE /api/auth/google/link — putuskan tautan akun Google milik sendiri. */
    public function unlink(Request $request)
    {
        $request->user()->forceFill(['google_id' => null])->save();

        return response()->json([
            'success' => true,
            'message' => 'Tautan akun Google telah dilepas.',
        ], 200);
    }

    /** Menautkan identitas Google ke akun staf yang sudah login (bukan membuat sesi baru). */
    private function finishLink(int $userId, string $email, array $profile)
    {
        $user = User::find($userId);

        if (! $user) {
            return redirect('/login?google_error=akun-tidak-diketahui');
        }

        $sub = $profile['sub'] ?? null;

        if ($sub && User::where('google_id', $sub)->where('id', '!=', $user->id)->exists()) {
            return redirect('/profil?google_error=sudah-dipakai-akun-lain');
        }

        $user->forceFill(['google_id' => $sub, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        return redirect('/profil?google=terhubung');
    }

    private function exchangeCode(string $code): ?string
    {
        $response = $this->client()->post(self::TOKEN_URL, [
            'form_params' => [
                'code'          => $code,
                'client_id'     => Setting::value('google_client_id'),
                'client_secret' => Setting::value('google_client_secret'),
                'redirect_uri'  => self::redirectUri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return $data['access_token'] ?? null;
    }

    private function fetchProfile(string $accessToken): array
    {
        $response = $this->client()->get(self::USERINFO_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);

        return json_decode((string) $response->getBody(), true) ?: [];
    }

    private function client(): Client
    {
        return new Client(['timeout' => 15, 'http_errors' => true]);
    }

    /** Samakan pencatatan sesi dengan login biasa supaya halaman Sesi Login tetap konsisten. */
    private function issueSession(User $user, Request $request, string $activity): string
    {
        $expiresAt = now()->addMinutes(SettingController::sessionTimeoutMinutes());
        $token = $user->createToken('auth_token', ['*'], $expiresAt);

        LoginSession::create([
            'user_id'    => $user->id,
            'token_id'   => (string) $token->accessToken->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at'   => now(),
            'status'     => LoginSession::STATUS_ACTIVE,
        ]);

        SystemLog::create([
            'user_id'     => $user->id,
            'activity'    => $activity,
            'description' => 'User berhasil login melalui Google',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return $token->plainTextToken;
    }

    private function loginUrl(?string $error, ?string $token = null): string
    {
        if ($token) {
            return '/login#lp_token=' . urlencode($token);
        }

        return $error ? '/login?google_error=' . urlencode($error) : '/login';
    }
}
