<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginSession;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Sanctum menyimpan token sebagai hash SHA-256, jadi id personal_access_tokens
     * tidak dapat diambil dari plain text token. Sesi dicari lewat users.token_id.
     */
    private function currentTokenId(Request $request): ?string
    {
        $plain = $request->bearerToken();

        if (!$plain) {
            return null;
        }

        return DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id)
            ->where('token', hash('sha256', $plain))
            ->value('id');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->logActivity($user?->id, 'login_failed', 'Percobaan login gagal untuk ' . $request->email, $request);

            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        if ($user->status !== User::STATUS_AKTIF) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi Manajer atau Admin Sales.',
            ], 403);
        }

        // Konsumen tidak memiliki akun login: seluruh interaksinya lewat halaman publik.
        if (! in_array($user->role, User::ROLE_INTERNAL, true)) {
            $this->logActivity($user->id, 'login_denied', 'Percobaan login oleh akun konsumen', $request);

            return response()->json([
                'success' => false,
                'message' => 'Akun konsumen tidak dapat masuk ke sistem. Silakan hubungi sales atau gunakan formulir pada halaman publik.',
            ], 403);
        }

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
            'password_baru' => 'required|string|min:6|different:password_lama',
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
