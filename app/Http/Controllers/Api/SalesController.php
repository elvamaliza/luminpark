<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FiltersProjects;
use App\Http\Controllers\Concerns\ScopesSalesData;
use App\Models\Booking;
use App\Models\Cluster;
use App\Models\CustomerLead;
use App\Models\FollowUpHistory;
use App\Models\SalesProfile;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    use FiltersProjects;
    use ScopesSalesData;

    // 1. GET /api/sales (Menampilkan daftar semua sales)
    public function index(Request $request)
    {
        $query = User::where('role', User::ROLE_SALES)->with('salesProfile');

        if ($request->filled('status')) {
            $query->whereHas('salesProfile', fn ($q) => $q->where('status', $request->status));
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar data sales berhasil diambil',
            'data'    => $query->orderBy('name')->get(),
        ], 200);
    }

    // 2. POST /api/sales (Menambah sales baru + sales profile)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'email'            => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password'         => 'nullable|string|min:6',
            'phone'            => 'nullable|string|max:15',
            'address'          => 'nullable|string',
            'sales_code'       => ['required', 'string', 'max:30', Rule::unique('sales_profiles', 'sales_code')->whereNull('deleted_at')],
            'join_date'        => 'nullable|date',
            'birth_date'       => 'nullable|date|before_or_equal:today',
            'status'           => ['nullable', Rule::in(['aktif', 'nonaktif', 'resign'])],
            'whatsapp_number'  => 'nullable|string|max:20',
            'account_status'   => ['nullable', Rule::in(['aktif', 'nonaktif'])],
            'target_units'     => 'nullable|integer|min:0',
            'target_amount'    => 'nullable|numeric|min:0',
            'target_period_start' => 'nullable|date',
            'target_period_end'   => 'nullable|date',
        ]);

        $initialPassword = $validated['password'] ?? null;
        $fromBirthDate = false;

        if (!$initialPassword) {
            $initialPassword = $this->passwordFromBirthDate($validated['birth_date'] ?? null);

            if (!$initialPassword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Isi kata sandi, atau tanggal lahir agar kata sandi awal dibuat otomatis.',
                    'errors'  => ['birth_date' => ['Tanggal lahir wajib diisi bila kata sandi dikosongkan.']],
                ], 422);
            }

            $fromBirthDate = true;
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'                 => $validated['name'],
                'email'                => $validated['email'],
                'password'             => $initialPassword,
                'role'                 => User::ROLE_SALES,
                'phone'                => $validated['phone'] ?? null,
                'address'              => $validated['address'] ?? null,
                'status'               => $validated['account_status'] ?? User::STATUS_AKTIF,
                'must_change_password' => $fromBirthDate,
            ]);

            $profile = SalesProfile::create([
                'user_id'          => $user->id,
                'sales_code'       => $validated['sales_code'],
                'join_date'        => $validated['join_date'] ?? now()->toDateString(),
                'birth_date'       => $validated['birth_date'] ?? null,
                'status'           => $validated['status'] ?? 'aktif',
                'whatsapp_number'  => $validated['whatsapp_number'] ?? null,
            ]);

            if (!empty($validated['target_units']) || !empty($validated['target_amount'])) {
                $profile->targets()->create([
                    'period_start'  => $validated['target_period_start'] ?? now()->startOfMonth()->toDateString(),
                    'period_end'    => $validated['target_period_end'] ?? now()->endOfMonth()->toDateString(),
                    'target_units'  => (int) ($validated['target_units'] ?? 0),
                    'target_amount' => (float) ($validated['target_amount'] ?? 0),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $fromBirthDate
                    ? 'Sales baru berhasil ditambahkan dengan kata sandi awal dari tanggal lahir.'
                    : 'Sales baru berhasil ditambahkan',
                'data'    => $user->load(['salesProfile.targets']),
                'auth'    => [
                    'password_source'      => $fromBirthDate ? 'birth_date' : 'manual',
                    'password_default'     => $fromBirthDate ? $initialPassword : null,
                    'must_change_password' => $fromBirthDate,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan sales: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 3. GET /api/sales/{id} (Detail Sales)
    public function show($id)
    {
        $sales = User::where('role', User::ROLE_SALES)
            ->with(['salesProfile.targets', 'salesProfile.bookings.unit'])
            ->find($id);

        if (!$sales) {
            return response()->json(['message' => 'Sales tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $sales], 200);
    }

    // 4. PUT /api/sales/{id} (Update Data Sales)
    public function update(Request $request, $id)
    {
        $user = User::where('role', User::ROLE_SALES)->find($id);

        if (!$user) {
            return response()->json(['message' => 'Sales tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:100',
            'phone'           => 'nullable|string|max:15',
            'address'         => 'nullable|string',
            'status'          => ['nullable', Rule::in(['aktif', 'nonaktif', 'resign'])],
            'account_status'  => ['nullable', Rule::in(['aktif', 'nonaktif'])],
            'sales_code'      => ['sometimes', 'string', 'max:30', Rule::unique('sales_profiles', 'sales_code')->ignore($user->salesProfile?->id)->whereNull('deleted_at')],
            'join_date'       => 'nullable|date',
            'birth_date'      => 'nullable|date|before_or_equal:today',
            'whatsapp_number' => 'nullable|string|max:20',
            'avatar'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user->fill(array_intersect_key($validated, array_flip(['name', 'phone', 'address'])));

        if (array_key_exists('account_status', $validated)) {
            $user->status = $validated['account_status'];
        }

        // Revisi 36: foto yang diunggah pengelola dipakai kartu narahubung pada halaman publik.
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        $profileData = array_intersect_key($validated, array_flip(['sales_code', 'join_date', 'birth_date', 'whatsapp_number', 'status']));

        if ($profileData !== []) {
            if (!$user->salesProfile) {
                SalesProfile::create($profileData + ['user_id' => $user->id]);
            } else {
                $user->salesProfile->update($profileData);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data sales berhasil diperbarui',
            'data'    => $user->fresh('salesProfile'),
        ], 200);
    }

    // 5. DELETE /api/sales/{id} (Hapus Data Sales)
    public function destroy($id)
    {
        $user = User::where('role', User::ROLE_SALES)->find($id);

        if (!$user) {
            return response()->json(['message' => 'Sales tidak ditemukan'], 404);
        }

        if ($user->salesProfile && Booking::where('sales_id', $user->salesProfile->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Sales tidak dapat dihapus karena masih memiliki data pemesanan. Ubah status menjadi nonaktif.',
            ], 422);
        }

        $user->salesProfile?->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sales berhasil dihapus',
        ], 200);
    }

    // 6. GET /api/sales/ranking (Peringkat seluruh sales pada periode)
    public function ranking(Request $request)
    {
        [$periodStart, $periodEnd] = [
            $request->input('start_date', now()->startOfMonth()->toDateString()),
            $request->input('end_date', now()->endOfMonth()->toDateString()),
        ];
        $proyek = $this->clusterIdDari($request);
        $salesId = $this->salesIdTerbatas($request);
        $bukanBatal = fn ($query) => $this->padaProyek($query, $proyek, 'unit')
            ->whereBetween('booking_date', [$periodStart, $periodEnd])
            ->where('status', '!=', Booking::STATUS_DIBATALKAN);

        $rows = SalesProfile::query()
            ->with('user:id,name')
            ->withCount(['bookings' => $bukanBatal])
            ->withSum(['bookings' => $bukanBatal], 'total_price')
            ->with(['targets' => fn ($query) => $query
                ->whereDate('period_start', '<=', $periodEnd)
                ->whereDate('period_end', '>=', $periodStart)
                ->latest('period_start')])
            ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_SALES))
            ->when($salesId !== null, fn ($query) => $query->whereKey($salesId))
            ->get();

        $rows = $this->sortRankingRows($rows)
            ->map(function (SalesProfile $profile, int $index) {
                $target = $profile->targets->first();

                return [
                    'rank'             => $index + 1,
                    'sales_id'         => $profile->id,
                    'name'             => $profile->user?->name,
                    'jumlah_booking'   => (int) $profile->bookings_count,
                    'total_unit'       => (int) $profile->bookings_count,
                    'total_nilai'      => round((float) $profile->bookings_sum_total_price, 2),
                    'unit_progress'    => $target?->unit_progress,
                    'amount_progress'  => $target?->amount_progress,
                ];
            });

        return response()->json([
            'success' => true,
            'period'  => ['start_date' => $periodStart, 'end_date' => $periodEnd],
            'project_filter' => $proyek,
            'projects' => Cluster::query()->aktif()->orderBy('name')->get(['id', 'name']),
            'data' => $rows,
        ], 200);
    }

    private function sortRankingRows(Collection $rows): Collection
    {
        return $rows->sort(function (SalesProfile $a, SalesProfile $b) {
            return ($b->bookings_count <=> $a->bookings_count)
                ?: strcasecmp((string) ($a->user?->name ?? ''), (string) ($b->user?->name ?? ''));
        })->values();
    }

    // 7. GET /api/sales/{id}/performance (Kinerja Sales pada periode)
    public function performance(Request $request, $id)
    {
        $user = User::where('role', User::ROLE_SALES)->find($id);

        if (!$user || !$user->salesProfile) {
            return response()->json(['message' => 'Sales tidak ditemukan'], 404);
        }

        $profile = $user->salesProfile;
        $periodStart = $request->input('start_date', now()->startOfMonth()->toDateString());
        $periodEnd = $request->input('end_date', now()->endOfMonth()->toDateString());

        $bookedIds = Booking::where('sales_id', $profile->id)
            ->whereBetween('booking_date', [$periodStart, $periodEnd])
            ->pluck('id');

        $unitsSold = Booking::where('sales_id', $profile->id)
            ->whereIn('status', [Booking::STATUS_LUNAS])
            ->whereBetween('booking_date', [$periodStart, $periodEnd])
            ->count();

        $revenue = (float) Booking::where('sales_id', $profile->id)
            ->whereHas('payments', fn ($q) => $q->where('verification_status', 'verified')
                ->whereBetween('payment_date', [$periodStart, $periodEnd]))
            ->sum('total_price');

        $verifiedPayments = DB::table('payments')
            ->whereNull('payments.deleted_at')
            ->whereIn('booking_id', Booking::where('sales_id', $profile->id)->pluck('id'))
            ->where('verification_status', 'verified')
            ->whereBetween('payment_date', [$periodStart, $periodEnd])
            ->sum('amount');

        $target = SalesTarget::where('sales_id', $profile->id)
            ->whereDate('period_start', '<=', $periodEnd)
            ->whereDate('period_end', '>=', $periodStart)
            ->latest('period_start')
            ->first();

        if ($target) {
            $target->update([
                'achieved_units'  => $unitsSold,
                'achieved_amount' => (float) $verifiedPayments,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'sales' => [
                    'id'         => $profile->id,
                    'user_id'    => $user->id,
                    'name'       => $user->name,
                    'sales_code' => $profile->sales_code,
                ],
                'period' => [
                    'start_date' => $periodStart,
                    'end_date'   => $periodEnd,
                ],
                'metrics' => [
                    'total_leads'        => CustomerLead::where('sales_id', $profile->id)->count(),
                    'new_leads'          => (int) CustomerLead::where('sales_id', $profile->id)
                        ->whereBetween('created_at', [$periodStart, $periodEnd . ' 23:59:59'])->count(),
                    'total_follow_ups'   => FollowUpHistory::whereIn('lead_id', CustomerLead::where('sales_id', $profile->id)->pluck('id'))->count(),
                    'bookings'           => $bookedIds->count(),
                    'units_sold'         => $unitsSold,
                    'booked_revenue'     => round($revenue, 2),
                    'verified_payments'  => round((float) $verifiedPayments, 2),
                ],
                'target' => $target ? [
                    'target_units'     => (int) $target->target_units,
                    'achieved_units'   => (int) $target->achieved_units,
                    'unit_progress'    => $target->unit_progress,
                    'target_amount'    => (float) $target->target_amount,
                    'achieved_amount'  => (float) $target->achieved_amount,
                    'amount_progress'  => $target->amount_progress,
                ] : null,
            ],
        ], 200);
    }

    // 7. POST /api/sales/{id}/reset-password (Reset password sales oleh pengelola)
    public function resetPassword(Request $request, $id)
    {
        $user = User::where('role', User::ROLE_SALES)->with('salesProfile')->find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Sales tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'password' => 'nullable|string|min:6',
        ]);

        $password = $validated['password'] ?? null;
        $fromBirthDate = false;

        if (!$password) {
            $password = $this->passwordFromBirthDate($user->salesProfile?->birth_date?->toDateString());

            if (!$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kirim kata sandi baru, atau isi tanggal lahir sales ini lebih dulu.',
                    'errors'  => ['birth_date' => ['Tanggal lahir sales belum tersedia sehingga kata sandi awal tidak dapat dibuat.']],
                ], 422);
            }

            $fromBirthDate = true;
        }

        $user->update([
            'password'             => Hash::make($password),
            'must_change_password' => $fromBirthDate,
        ]);

        // Sesi lama dicabut agar password baru langsung berlaku.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => $fromBirthDate
                ? 'Kata sandi sales dikembalikan ke tanggal lahir dan wajib diganti saat login berikutnya.'
                : 'Password sales berhasil direset dan sesi login dihapus.',
            'auth'    => [
                'password_source'  => $fromBirthDate ? 'birth_date' : 'manual',
                'password_default' => $fromBirthDate ? $password : null,
            ],
        ], 200);
    }

    /** Kata sandi awal sales: tanggal lahir ditulis yyyymmdd, contoh 19950314. */
    private function passwordFromBirthDate(?string $birthDate): ?string
    {
        if (!$birthDate) {
            return null;
        }

        try {
            return Carbon::parse($birthDate)->format('Ymd');
        } catch (\Exception $e) {
            return null;
        }
    }
}
