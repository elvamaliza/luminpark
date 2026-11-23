<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    // 1. Tampilkan semua log aktivitas (dengan filter user_id)
    public function index(Request $request)
    {
        $query = SystemLog::with('user');

        if ($request->has('user_id') && $request->user_id != '') {
            $query->where('user_id', $request->user_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest('created_at')->get()
        ], 200);
    }

    // 2. Catat log baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'activity'    => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->header('User-Agent');

        $log = SystemLog::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Log aktivitas berhasil dicatat',
            'data'    => $log
        ], 201);
    }

    // 3. Detail log
    public function show($id)
    {
        $log = SystemLog::with('user')->find($id);

        if (!$log) {
            return response()->json(['message' => 'Log tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $log], 200);
    }
}