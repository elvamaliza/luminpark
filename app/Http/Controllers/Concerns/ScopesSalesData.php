<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Butir d.4: pemisahan data antar sales. Dipakai modul booking, pembayaran, dan
 * tindak lanjut agar batas aksesnya identik dengan modul calon konsumen.
 */
trait ScopesSalesData
{
    /**
     * null berarti peran bebas menyaring sendiri; angka berarti daftar dipaksa
     * ke profil sales tersebut. Akun sales tanpa profil memakai 0 supaya
     * tidak ikut membaca data siapa pun.
     */
    protected function salesIdTerbatas(Request $request): ?int
    {
        $user = $request->user();

        if (! $user || $user->role !== User::ROLE_SALES) {
            return null;
        }

        return (int) $user->salesProfile?->id;
    }

    /** Respons 403 bila baris milik sales lain, null bila boleh dilanjutkan. */
    protected function tolakDataSalesLain(Request $request, ?int $empunya): ?JsonResponse
    {
        $batas = $this->salesIdTerbatas($request);

        if ($batas === null || $batas === $empunya) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Data ini berada di bawah penanganan sales lain, sehingga tidak dapat dibuka.',
        ], 403);
    }
}
