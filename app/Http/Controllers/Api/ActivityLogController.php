<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Jejak audit tabel activity_logs (rancangan ERD). Bedanya dengan /system-logs:
 * log aktivitas berisi perubahan data per model, sedangkan system-logs berisi
 * kejadian autentikasi dan aksi sistemik.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', $request->string('action')->toString() . '%');
        }

        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->date('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->date('sampai'));
        }

        if ($request->filled('q')) {
            $kata = '%' . str_replace(['%', '_'], ['\%', '\_'], $request->string('q')->toString()) . '%';
            $query->where(fn ($inner) => $inner
                ->where('description', 'like', $kata)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $kata)));
        }

        $batas = min(max($request->integer('per_page', 25), 1), 100);
        $halaman = $query->latest('created_at')->latest('id')->paginate($batas, ['*'], 'page', max($request->integer('page', 1), 1));

        return response()->json([
            'success' => true,
            'data' => $halaman->items(),
            'meta' => [
                'total' => $halaman->total(),
                'per_page' => $batas,
                'current_page' => $halaman->currentPage(),
                'last_page' => $halaman->lastPage(),
            ],
        ], 200);
    }
}
