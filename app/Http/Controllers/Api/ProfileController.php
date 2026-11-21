<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginSession;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Profil akun pengguna yang sedang login, beserta profil sales bila ada.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load(['salesProfile.targets' => fn ($q) => $q->latest('period_start')]);
        $profile = $user->salesProfile;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'username'        => $user->email,
                'role'            => $user->role,
                'phone'           => $user->phone,
                'address'         => $user->address,
                'avatar_url'      => $user->avatar_url,
                'status'          => $user->status,
                'sales_code'      => $profile?->sales_code,
                'join_date'       => $profile?->join_date,
                'whatsapp_number' => $profile?->whatsapp_number,
                'targets'         => $profile?->targets ?? [],
                'active_sessions' => LoginSession::where('user_id', $user->id)->whereNull('logout_at')->count(),
                'google_linked'   => (bool) $user->google_id,
                'google_ready'    => GoogleAuthController::enabled(),
                'created_at'      => $user->created_at,
            ],
        ], 200);
    }

    /**
     * Pembaruan mandiri: nama, kontak, alamat, dan foto profil. Role, status,
     * dan email tetap menjadi kewenangan Super Admin/Monitoring Manajemen.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'            => 'sometimes|required|string|max:120',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:255',
            'whatsapp_number' => 'nullable|string|max:20',
            'avatar'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data profil tidak valid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }

        $whatsapp = $data['whatsapp_number'] ?? null;
        unset($data['whatsapp_number']);

        $user->update($data);

        $profile = $user->salesProfile;
        if ($profile && $whatsapp !== null) {
            $profile->update(['whatsapp_number' => $whatsapp]);
        }

        SystemLog::create([
            'user_id'     => $user->id,
            'activity'    => 'update_profile',
            'description' => 'Profil diperbarui oleh pemilik akun',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $user->fresh()->load('salesProfile'),
        ], 200);
    }
}
