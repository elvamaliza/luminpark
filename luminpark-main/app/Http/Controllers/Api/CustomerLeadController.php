<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NotifiesOperationalUsers;
use App\Http\Controllers\Concerns\ScopesSalesData;
use App\Models\Booking;
use App\Models\CustomerLead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CustomerLeadController extends Controller
{
    use NotifiesOperationalUsers;
    use ScopesSalesData;

    public const PESAN_TELEPON_DIPAKAI = 'Nomor handphone ini sudah dipakai konsumen lain dan hanya dapat digunakan satu kali.';
    // 1. Tampilkan semua data (lengkap dengan filter & pencarian)
    public function index(Request $request)
    {
        // Status pemesanan dibaca dari relasi bookings; relasinya diambil sekali
        // agar kategori tiap baris tidak membangkitkan query per baris.
        $query = CustomerLead::with([
            'salesProfile.user', 'unit',
            'bookings:id,lead_id,status,cancelled_at,booking_code,booking_date,unit_id,total_price,delivery_status,cancellation_reason,addition_cost,addition_description',
            'bookings.payments:id,booking_id,verification_status,amount',
            'bookings.unit:id,unit_code',
        ]);

        // Sales hanya melihat calon konsumen yang menjadi tanggung jawabnya.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $query->where('sales_id', $milik);
        } elseif ($request->filled('sales_id')) {
            $query->where('sales_id', $request->sales_id);
        }

        // Search berdasarkan nama, phone, email, atau interested_unit
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('interested_unit', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan status prospek ('cold','warm','hot')
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Filter kategori: prospek (masih murni calon), konsumen (sudah ada pembayaran
        // terverifikasi), dibatalkan (seluruh pemesanannya batal), atau riwayat
        // (lead yang sudah berpindah dari Database Konsumen).
        if ($request->filled('category')) {
            match ($request->category) {
                CustomerLead::CATEGORY_PROSPEK => $query->prospects(),
                CustomerLead::CATEGORY_KONSUMEN => $query->customers(),
                CustomerLead::CATEGORY_DIBATALKAN => $query->cancelled(),
                CustomerLead::CATEGORY_RIWAYAT => $query->history(),
                'booked' => $query->everBooked(),
                default => null,
            };
        }

        $rows = $query->latest()->get();

        // Halaman History membutuhkan waktu, alasan, dan nilai yang sudah
        // disetujui; dihitung dari relasi yang terlanjur dimuat sehingga tidak
        // menambah query per baris.
        $untukRiwayat = in_array($request->category, [
            CustomerLead::CATEGORY_DIBATALKAN,
            CustomerLead::CATEGORY_RIWAYAT,
        ], true);

        // Daftar cukup membawa kategorinya saja, bukan isi seluruh relasi pemesanan.
        $rows->each(function (CustomerLead $lead) use ($untukRiwayat) {
            $lead->setAttribute('category', $lead->category());

            // Revisi 41: kolom Unit Ditempati dan Pesanan Tambahan pada daftar konsumen
            // dibaca dari pemesanan yang sedang dipegang, atau pembatalan terakhirnya.
            $aktif = $lead->bookingAktif();
            $dipegang = $aktif ?? $lead->bookingPembatalanTerakhir();

            $lead->setAttribute('punya_booking_aktif', (bool) $aktif);
            $lead->setAttribute('unit_ditempati', optional($dipegang?->unit)->unit_code);
            $lead->setAttribute('pesanan_tambahan', $dipegang?->addition_description);

            if ($untukRiwayat) {
                $lead->setAttribute('pembatalan', $lead->pembatalanTerakhir());
                $lead->setAttribute('riwayat', $lead->riwayatRingkas());
            }

            $lead->makeHidden('bookings');
        });

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ], 200);
    }

    // 2. Tambah data Customer Lead baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sales_id'        => 'nullable|exists:sales_profiles,id',
            'unit_id'         => 'nullable|exists:units,id',
            'name'            => 'required|string|max:100',
            'phone'           => $this->aturanTelepon(),
            'first_chat_date' => 'nullable|date',
            'email'           => 'nullable|email|max:100',
            'address'         => 'nullable|string',
            'status'          => ['required', Rule::in(CustomerLead::STATUSES)],
            'interested_unit' => 'nullable|string|max:50',
            'budget'          => 'nullable|numeric',
            'note'            => 'nullable|string',
            'last_follow_up'  => 'nullable|date',
            'source'          => 'nullable|string|max:50',
        ], [
            'phone.unique' => self::PESAN_TELEPON_DIPAKAI,
        ]);

        // Sales selalu menambah lead atas namanya sendiri.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $validated['sales_id'] = $milik;
        }

        $lead = CustomerLead::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Calon konsumen berhasil ditambahkan',
            'data'    => $this->denganKategori($lead->load(['salesProfile.user', 'unit']))
        ], 201);
    }

    // 3. Detail satu Customer Lead
    public function show(Request $request, $id)
    {
        $lead = CustomerLead::with(['salesProfile.user', 'unit', 'followUpHistories.salesProfile.user', 'bookings'])
            ->find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat mengakses data calon konsumen Anda sendiri.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->denganKategori($lead)], 200);
    }

    // 4. Update data & Status Follow Up
    public function update(Request $request, $id)
    {
        $lead = CustomerLead::find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat mengubah data calon konsumen Anda sendiri.'], 403);
        }

        $validated = $request->validate([
            'sales_id'        => 'sometimes|nullable|exists:sales_profiles,id',
            'unit_id'         => 'sometimes|nullable|exists:units,id',
            'name'            => 'sometimes|string|max:100',
            'phone'           => $this->aturanTelepon($lead->id, false),
            'first_chat_date' => 'sometimes|nullable|date',
            'email'           => 'nullable|email|max:100',
            'address'         => 'nullable|string',
            'status'          => ['sometimes', Rule::in(CustomerLead::STATUSES)],
            'interested_unit' => 'nullable|string|max:50',
            'budget'          => 'nullable|numeric',
            'note'            => 'nullable|string',
            'last_follow_up'  => 'nullable|date',
            'source'          => 'nullable|string|max:50',
        ], [
            'phone.unique' => self::PESAN_TELEPON_DIPAKAI,
        ]);

        $lead->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data calon konsumen berhasil diperbarui',
            'data'    => $this->denganKategori($lead->fresh(['salesProfile.user', 'unit']))
        ], 200);
    }

    // 5. Hapus data
    public function destroy(Request $request, $id)
    {
        $lead = CustomerLead::find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat menghapus data calon konsumen Anda sendiri.'], 403);
        }

        if ($lead->bookings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Calon konsumen tidak dapat dihapus karena sudah memiliki pemesanan.',
            ], 422);
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data calon konsumen berhasil dihapus'
        ], 200);
    }

    /**
     * Pembatalan konsumen dari menu Data Konsumen (revisi 18). Seluruh pemesanan
     * aktifnya ditutup memakai alasan serta tanggal yang dicatat sales, unitnya
     * kembali tersedia, dan konsumen tersebut berpindah ke kategori Dibatalkan
     * bersama riwayat pembeliannya.
     */
    public function batalkan(Request $request, $id)
    {
        $lead = CustomerLead::with(['bookings.unit', 'bookings.payments', 'salesProfile.user'])->find($id);

        if (!$lead) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($this->deniedForSales($request, $lead)) {
            return response()->json(['success' => false, 'message' => 'Anda hanya dapat membatalkan data konsumen Anda sendiri.'], 403);
        }

        $validated = $request->validate([
            'reason'       => 'required|string|min:5|max:255',
            'cancelled_at' => 'nullable|date',
        ]);

        $aktif = $lead->bookings->where('status', '!=', Booking::STATUS_DIBATALKAN);

        if ($aktif->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Konsumen ini tidak memiliki pemesanan aktif yang dapat dibatalkan.',
            ], 422);
        }

        $tanggal = isset($validated['cancelled_at']) ? Carbon::parse($validated['cancelled_at']) : null;

        foreach ($aktif as $booking) {
            $booking->batalkan($validated['reason'], $tanggal);
        }

        $this->notifyOperationalUsers(
            'Pemesanan konsumen dibatalkan',
            sprintf('%s membatalkan %d pemesanan. Alasan: %s', $lead->name, $aktif->count(), $validated['reason']),
            'booking',
            '/data-konsumen?kategori=dibatalkan'
        );

        return response()->json([
            'success'      => true,
            'message'      => sprintf('%d pemesanan atas nama %s berhasil dibatalkan. Data konsumen dipindahkan ke kategori Dibatalkan beserta riwayat pembeliannya.', $aktif->count(), $lead->name),
            'dibatalkan'   => $aktif->count(),
            'data'         => $this->denganKategori($lead->fresh(['salesProfile.user', 'unit'])),
            'pembatalan'   => $lead->pembatalanTerakhir(),
        ], 200);
    }

    /**
     * Nomor handphone hanya sekali pakai: satu nomor hanya boleh melekat pada satu
     * konsumen, apa pun status follow up-nya (cold/warm/hot) dan meskipun
     * pemesanannya sudah dibatalkan. Hanya data yang dihapus lunak membebaskan
     * nomornya kembali.
     */
    private function aturanTelepon(?int $kecualikan = null, bool $wajib = true): array
    {
        $aturan = Rule::unique('customer_leads', 'phone')
            ->where(fn ($query) => $query->whereNull('deleted_at'));

        if ($kecualikan) {
            $aturan->ignore($kecualikan);
        }

        return [$wajib ? 'required' : 'sometimes', 'string', 'max:15', $aturan];
    }

    /**
     * Menempelkan kategori konsumen/prospek pada hasil serialisasi lead.
     */
    private function denganKategori(CustomerLead $lead): CustomerLead
    {
        $lead->setAttribute('category', $lead->category());

        return $lead;
    }

    /**
     * Seorang Sales hanya boleh mengelola lead yang melekat pada profilnya.
     */
    private function deniedForSales(Request $request, CustomerLead $lead): bool
    {
        $batas = $this->salesIdTerbatas($request);

        return $batas !== null && $batas !== (int) $lead->sales_id;
    }
}