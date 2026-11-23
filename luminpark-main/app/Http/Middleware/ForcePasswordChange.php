<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales dibuat dengan kata sandi awal berupa tanggal lahir, sehingga wajib menggantinya
 * sebelum memakai modul apa pun. Hanya rute autentikasi yang boleh dilewati.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && !$request->is('api/me', 'api/logout', 'api/reset-password', 'api/auth/*')) {
            return response()->json([
                'success' => false,
                'code'    => 'password_change_required',
                'message' => 'Ganti kata sandi awal Anda terlebih dahulu sebelum menggunakan sistem.',
            ], 403);
        }

        return $next($request);
    }
}
