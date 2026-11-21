<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NotifiesOperationalUsers;
use App\Http\Controllers\Concerns\ScopesSalesData;
use App\Models\Booking;
use App\Models\CustomerLead;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentScheme;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    use NotifiesOperationalUsers;
    use ScopesSalesData;

    // 1. Lihat Daftar Booking (Read All)
    public function index(Request $request)
    {
        $query = Booking::with(['customerLead', 'salesProfile.user', 'unit.cluster', 'paymentScheme']);

        // Butir d.4: pemisahan data sales — Sales hanya menerima pesanannya sendiri.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $query->where('sales_id', $milik);
        } elseif ($request->filled('sales_id')) {
            $query->where('sales_id', $request->sales_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest('booking_date')->get(),
        ], 200);
    }

    // 2. Buat Booking Baru (Create)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id'              => 'required|exists:customer_leads,id',
            'sales_id'             => 'required|exists:sales_profiles,id',
            'unit_id'              => 'required|exists:units,id',
            'payment_scheme_id'    => 'required|exists:payment_schemes,id',
            'booking_date'         => 'required|date',
            'addition_cost'        => 'nullable|numeric|min:0',
            'addition_description' => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
            // Revisi 43: pembayaran pertama boleh dicatat sekaligus saat booking dibuat.
            'initial_payment'                  => 'nullable|array',
            'initial_payment.payment_category' => 'required_with:initial_payment|in:' . implode(',', Payment::CATEGORIES),
            'initial_payment.amount'           => 'required_with:initial_payment|numeric|min:0.01',
            'initial_payment.payment_date'     => 'required_with:initial_payment|date',
            'initial_payment.payment_method'   => 'nullable|in:' . implode(',', Payment::METHODS),
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);

        if ($tolak = $this->tolakTambahanTanpaKeterangan($validated)) {
            return $tolak;
        }

        if ($tolak = $this->tolakKategoriDiLuarBiayaTambahan($validated)) {
            return $tolak;
        }

        // Pemesanan yang dibuat Sales selalu melekat pada profilnya sendiri.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $validated['sales_id'] = $milik;
        }

        if ($unit->status !== Unit::STATUS_AVAILABLE) {
            return response()->json([
                'success' => false,
                'message' => 'Unit tidak dapat dipesan karena statusnya bukan tersedia.',
            ], 422);
        }

        // Revisi 42: satu unit hanya boleh memegang satu pemesanan aktif, jadi unit
        // yang bookingnya belum dibatalkan/dihapus ditolak walaupun statusnya tersedia.
        if ($unit->bookings()->where('status', '!=', Booking::STATUS_DIBATALKAN)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unit tidak dapat dipesan karena sudah memiliki booking aktif.',
            ], 422);
        }

        $lead = CustomerLead::findOrFail($validated['lead_id']);

        if ($lead->status !== CustomerLead::STATUS_HOT) {
            return response()->json([
                'success' => false,
                'message' => 'Pemesanan hanya dapat dibuat untuk calon konsumen berstatus hot.',
            ], 422);
        }

        $scheme = PaymentScheme::findOrFail($validated['payment_scheme_id']);

        // Rincian pembayaran diturunkan server dari harga unit + skema, bukan dari input klien.
        $breakdown = $scheme->calculateBreakdown((float) $unit->price);

        $booking = Booking::create([
            'booking_code'          => $this->generateBookingCode(),
            'lead_id'               => $lead->id,
            'sales_id'              => $validated['sales_id'],
            'unit_id'               => $unit->id,
            'payment_scheme_id'     => $scheme->id,
            'booking_date'          => $validated['booking_date'],
            'total_price'           => $unit->price,
            'down_payment_amount'   => $breakdown['down_payment_amount'],
            'installment_amount'    => $breakdown['installment_amount'],
            'remaining_amount'      => $unit->price,
            // Biaya tambahan konstruksi berada di luar harga utama unit.
            'addition_cost'           => (float) ($validated['addition_cost'] ?? 0),
            'addition_description'    => $validated['addition_description'] ?? null,
            'status'                => Booking::STATUS_MENUNGGU_DP,
            'delivery_status'       => 'belum proses',
            'notes'                 => $validated['notes'] ?? null,
        ]);

        $unit->update(['status' => Unit::STATUS_BOOKING]);

        $pesan = 'Pemesanan (Booking) berhasil dibuat';

        // Revisi 43: pembayaran pertama yang diisikan pada form booking masuk ledger
        // dengan status menunggu verifikasi, sama seperti catatan dari Data Pembayaran.
        if ($paymentAwal = $this->catatPembayaranAwal($booking, $scheme, $validated['initial_payment'] ?? null)) {
            $this->notifyOperationalUsers(
                'Pembayaran menunggu verifikasi',
                sprintf(
                    'Pembayaran %s (%s) sebesar %s untuk booking %s menunggu verifikasi.',
                    $paymentAwal->categoryLabel(),
                    $paymentAwal->payment_type,
                    'Rp ' . number_format((float) $paymentAwal->amount, 0, ',', '.'),
                    $booking->booking_code
                ),
                'payment',
                '/pembayaran'
            );

            $pesan = 'Pemesanan (Booking) berhasil dibuat dan pembayaran pertama menunggu verifikasi';
        }

        $this->notifyOperationalUsers(
            'Booking baru dibuat',
            sprintf('%s memesan unit %s (%s)', $lead->name, $unit->unit_code, $booking->booking_code),
            'booking',
            '/booking'
        );

        return response()->json([
            'success' => true,
            'message' => $pesan,
            'data'    => $booking->load(['customerLead', 'salesProfile.user', 'unit', 'paymentScheme']),
        ], 201);
    }

    // 3. Detail Booking (Read Single)
    public function show(Request $request, $id)
    {
        $booking = Booking::with([
            'customerLead', 'salesProfile.user', 'unit.cluster', 'paymentScheme', 'payments.verifier',
        ])->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data booking tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $booking->sales_id)) {
            return $tolak;
        }

        $booking->setAttribute('settlement_status', $booking->settlementStatus());

        return response()->json(['success' => true, 'data' => $booking], 200);
    }

    // 4. Update Booking
    public function update(Request $request, $id)
    {
        $booking = Booking::with('payments')->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data booking tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $booking->sales_id)) {
            return $tolak;
        }

        $milik = $this->salesIdTerbatas($request);

        if ($booking->status === Booking::STATUS_DIBATALKAN) {
            return response()->json([
                'success' => false,
                'message' => 'Booking yang sudah dibatalkan tidak dapat diubah lagi. Riwayat pembeliannya tetap tersimpan.',
            ], 422);
        }

        $validated = $request->validate([
            'lead_id'              => 'sometimes|exists:customer_leads,id',
            'sales_id'             => 'sometimes|exists:sales_profiles,id',
            'unit_id'              => 'sometimes|exists:units,id',
            'payment_scheme_id'    => 'sometimes|exists:payment_schemes,id',
            'booking_date'         => 'sometimes|date',
            'delivery_status'      => 'sometimes|in:belum proses,sedang proses,selesai',
            'addition_cost'        => 'nullable|numeric|min:0',
            'addition_description' => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
        ]);

        // Sales tidak dapat memindahkan pesanannya ke profil sales lain.
        if ($milik !== null && array_key_exists('sales_id', $validated)) {
            $validated['sales_id'] = $milik;
        }

        // Kolom addition_cost tidak boleh null; field yang dikosongkan berarti biaya tambahannya nol.
        if (array_key_exists('addition_cost', $validated)) {
            $validated['addition_cost'] = (float) $validated['addition_cost'];
        }

        if (array_key_exists('addition_cost', $validated)
            && ($tolak = $this->tolakTambahanTanpaKeterangan($validated, $booking->addition_description))) {
            return $tolak;
        }

        if (isset($validated['payment_scheme_id']) && (int) $validated['payment_scheme_id'] !== (int) $booking->payment_scheme_id) {
            $scheme = PaymentScheme::findOrFail($validated['payment_scheme_id']);
            $breakdown = $scheme->calculateBreakdown((float) $booking->total_price);

            $booking->fill([
                'payment_scheme_id'   => $scheme->id,
                'down_payment_amount' => $breakdown['down_payment_amount'],
                'installment_amount'  => $breakdown['installment_amount'],
            ]);
        }

        $booking->fill($validated);
        $booking->status = $booking->settlementStatus();
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Data booking berhasil diperbarui',
            'data'    => $booking->fresh(['customerLead', 'salesProfile.user', 'unit', 'paymentScheme']),
        ], 200);
    }

    // 5. Hapus Booking
    public function destroy(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data booking tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $booking->sales_id)) {
            return $tolak;
        }

        $unit = Unit::find($booking->unit_id);
        if ($unit && $unit->status === Unit::STATUS_BOOKING) {
            $unit->update(['status' => Unit::STATUS_AVAILABLE]);
        }

        // Status lead tidak diubah: kategori konsumen/prospek dibaca dari ada
        // tidaknya pemesanan, sehingga menghapus booking otomatis mengembalikan
        // lead menjadi prospek.

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data booking berhasil dihapus',
        ], 200);
    }

    /**
     * Pembatalan pemesanan (revisi 5 & 15). Booking dan pembayaran terkait tidak
     * dihapus supaya riwayat pembelian tetap dapat dibuka, hanya unitnya yang
     * kembali tersedia. Pembatalan juga diterima untuk booking yang sudah lunas
     * atau selesai serah terima; konsumen tersebut keluar dari daftar Konsumen dan
     * tercatat pada kategori Dibatalkan.
     */
    public function cancel(Request $request, $id)
    {
        $booking = Booking::with('payments')->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data booking tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $booking->sales_id)) {
            return $tolak;
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:255',
            'cancelled_at' => 'nullable|date',
        ]);

        if ($booking->status === Booking::STATUS_DIBATALKAN) {
            return response()->json([
                'success' => false,
                'message' => 'Booking ini sudah dibatalkan sebelumnya.',
            ], 422);
        }

        // Tanggal pembatalan boleh diisi (mis. konsumen membatalkan setelah
        // menempati unit); tanpa isian sistem memakai waktu saat ini.
        $tanggal = isset($validated['cancelled_at'])
            ? Carbon::parse($validated['cancelled_at'])
            : null;

        $booking->batalkan($validated['reason'], $tanggal);

        $unit = Unit::find($booking->unit_id);

        $this->notifyOperationalUsers(
            'Booking dibatalkan',
            sprintf('%s membatalkan pemesanan unit %s (%s).', optional($booking->customerLead)->name, optional($unit)->unit_code, $booking->booking_code),
            'booking',
            '/booking'
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibatalkan. Riwayat pembayaran tetap tersimpan dan dapat dilihat.',
            'data'    => $booking->fresh(['customerLead', 'salesProfile.user', 'unit', 'paymentScheme', 'payments']),
        ], 200);
    }

    /**
     * Permintaan tambahan bangunan selalu berpasangan: biaya yang melebihi harga
     * utama unit harus disertai keterangan pekerjaan yang diminta konsumen.
     */
    private function tolakTambahanTanpaKeterangan(array $validated, ?string $keteranganTersimpan = null): ?JsonResponse
    {
        if ((float) ($validated['addition_cost'] ?? 0) <= 0) {
            return null;
        }

        $keterangan = $validated['addition_description'] ?? $keteranganTersimpan;

        if (trim((string) $keterangan) !== '') {
            return null;
        }

        $pesan = 'Keterangan tambahan bangunan wajib diisi bila biaya tambahan konstruksi diisi.';

        return response()->json(['success' => false, 'message' => $pesan, 'errors' => ['addition_description' => [$pesan]]], 422);
    }

    /**
     * Kategori biaya tambahan hanya sah bila pemesanan itu memang memegang pekerjaan
     * tambahan di luar harga utama unit (revisi 43).
     */
    private function tolakKategoriDiLuarBiayaTambahan(array $validated): ?JsonResponse
    {
        if (($validated['initial_payment']['payment_category'] ?? null) !== Payment::CATEGORY_BIAYA_TAMBAHAN) {
            return null;
        }

        if ((float) ($validated['addition_cost'] ?? 0) > 0) {
            return null;
        }

        $pesan = 'Kategori pembayaran biaya tambahan hanya dapat dipakai bila biaya tambahan konstruksi diisi.';

        return response()->json(['success' => false, 'message' => $pesan, 'errors' => ['initial_payment' => [$pesan]]], 422);
    }

    /**
     * Blok pembayaran pada form booking bersifat opsional; tanpa isian tidak ada
     * baris ledger yang dibuat.
     */
    private function catatPembayaranAwal(Booking $booking, PaymentScheme $scheme, ?array $payment): ?Payment
    {
        if ($payment === null) {
            return null;
        }

        return $booking->payments()->create([
            'payment_type'        => $scheme->scheme_type,
            'payment_category'    => $payment['payment_category'],
            'payment_method'      => $payment['payment_method'] ?? null,
            'amount'              => $payment['amount'],
            'payment_date'        => $payment['payment_date'],
            'verification_status' => Payment::VERIFY_PENDING,
        ]);
    }

    private function generateBookingCode(): string
    {
        $prefix = 'BK' . now()->format('Ym');

        // Booking yang dihapus lunak masih memegang booking_code-nya pada indeks unik,
        // jadi penomorannya harus ikut membaca baris terhapus.
        $last = Booking::withTrashed()->where('booking_code', 'like', $prefix . '%')
            ->orderByDesc('booking_code')
            ->value('booking_code');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
