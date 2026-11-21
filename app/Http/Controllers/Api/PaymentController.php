<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NotifiesOperationalUsers;
use App\Http\Controllers\Concerns\ScopesSalesData;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\SalesProfile;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    use ScopesSalesData;
    use NotifiesOperationalUsers;

    // 1. Riwayat & Monitoring Pembayaran (Read All)
    public function index(Request $request)
    {
        $query = Payment::with(['booking.customerLead', 'booking.unit', 'verifier']);

        // Butir d.4: pembayaran ikut terpisah menurut pemegang pemesanannya.
        $milik = $this->salesIdTerbatas($request);

        if ($milik !== null) {
            $query->whereHas('booking', fn ($booking) => $booking->where('sales_id', $milik));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->booking_id);
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        // Revisi 43: kategori dipakai terpisah dari skema pembayaran.
        if ($request->filled('payment_category')) {
            $query->where('payment_category', $request->payment_category);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->latest('payment_date')->get(),
        ], 200);
    }

    // 2. Catat Pembayaran Baru (Create)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id'       => 'required|exists:bookings,id',
            'payment_type'     => 'required|in:' . implode(',', Payment::TYPES),
            'payment_category' => 'nullable|in:' . implode(',', Payment::CATEGORIES),
            'payment_method'   => 'nullable|in:' . implode(',', Payment::METHODS),
            'amount'           => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'bank_name'        => 'nullable|string|max:50',
            'account_number'   => 'nullable|string|max:20',
            'account_holder'   => 'nullable|string|max:100',
            'transfer_receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'notes'            => 'nullable|string',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        if ($tolak = $this->tolakDataSalesLain($request, (int) $booking->sales_id)) {
            return $tolak;
        }

        if ($booking->status === Booking::STATUS_DIBATALKAN) {
            return response()->json([
                'success' => false,
                'message' => 'Booking yang dibatalkan tidak dapat diisi pembayaran baru.',
            ], 422);
        }

        // Revisi 43: biaya tambahan berdiri di luar harga utama unit, jadi pembayarannya
        // tetap boleh dicatat walaupun unitnya sudah lunas.
        if (($validated['payment_category'] ?? null) === Payment::CATEGORY_BIAYA_TAMBAHAN) {
            if ((float) $booking->addition_cost <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking ini tidak memiliki biaya tambahan, jadi pembayaran kategori biaya tambahan tidak dapat dicatat.',
                ], 422);
            }

            $terbayar = (float) $booking->payments()
                ->verified()
                ->where('payment_category', Payment::CATEGORY_BIAYA_TAMBAHAN)
                ->sum('amount');

            if ($terbayar >= (float) $booking->addition_cost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Biaya tambahan booking ini sudah dibayar penuh.',
                ], 422);
            }
        } elseif ($booking->status === Booking::STATUS_LUNAS) {
            return response()->json([
                'success' => false,
                'message' => 'Booking ini sudah lunas, pembayaran baru tidak dapat dicatat.',
            ], 422);
        }

        $validated['verification_status'] = Payment::VERIFY_PENDING;

        if ($request->hasFile('transfer_receipt')) {
            $validated['transfer_receipt'] = $request->file('transfer_receipt')->store('payment-receipts', 'public');
        } else {
            unset($validated['transfer_receipt']);
        }

        $payment = $booking->payments()->create($validated);

        $this->notifyVerificationUsers(
            'Pembayaran menunggu verifikasi',
            sprintf(
                'Pembayaran %s (%s) untuk booking %s menunggu verifikasi.',
                $payment->categoryLabel(),
                $payment->payment_type,
                $booking->booking_code
            ),
            $booking
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dicatat, menunggu verifikasi',
            'data'    => $payment->load('booking'),
        ], 201);
    }

    // 3. Detail Pembayaran
    public function show(Request $request, $id)
    {
        $payment = Payment::with(['booking.customerLead', 'booking.unit', 'verifier'])->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $payment->booking?->sales_id)) {
            return $tolak;
        }

        return response()->json(['success' => true, 'data' => $payment], 200);
    }

    // 4. Verifikasi Pembayaran (Update Status Verification)
    public function verify(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'verification_status' => 'required|in:verified,rejected',
            'notes'               => 'nullable|string',
        ]);

        $payment->fill([
            'verification_status' => $validated['verification_status'],
            'notes'               => $validated['notes'] ?? $payment->notes,
            'verified_by'         => $request->user()->id,
            'verified_at'         => now(),
        ])->save();

        $booking = Booking::find($payment->booking_id);

        if ($booking) {
            $this->recalculateBooking($booking);
            $booking->refresh();

            $labelVerifikasi = $validated['verification_status'] === 'verified' ? 'terverifikasi' : 'ditolak';
            $this->notifyVerificationUsers(
                'Pembayaran ' . $labelVerifikasi,
                sprintf('Pembayaran booking %s berstatus %s.', $booking->booking_code, $labelVerifikasi),
                $booking
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Status verifikasi pembayaran berhasil diperbarui',
            'data'    => $payment->load(['booking', 'verifier']),
        ], 200);
    }

    // 5. Hapus Catatan Pembayaran
    public function destroy(Request $request, $id)
    {
        $payment = Payment::with('booking')->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan'], 404);
        }

        if ($tolak = $this->tolakDataSalesLain($request, (int) $payment->booking?->sales_id)) {
            return $tolak;
        }

        $bookingId = $payment->booking_id;
        $receipt = $payment->transfer_receipt;

        $payment->delete();

        if ($receipt && Storage::disk('public')->exists($receipt)) {
            Storage::disk('public')->delete($receipt);
        }

        $booking = Booking::find($bookingId);
        if ($booking) {
            $this->recalculateBooking($booking);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data pembayaran berhasil dihapus',
        ], 200);
    }

    /**
     * Sisa pembayaran, status pelunasan, dan status unit selalu dihitung ulang dari
     * ledger pembayaran terverifikasi agar tetap benar saat pembayaran direject atau dihapus.
     *
     * Revisi 43: hanya pembayaran DP/cicilan (dan baris lama tanpa kategori) yang mengurangi
     * harga utama unit; pembayaran biaya tambahan tidak ikut melunaskan booking.
     */
    private function recalculateBooking(Booking $booking): void
    {
        $verified = (float) $booking->payments()->verified()->mengurangiHargaUnit()->sum('amount');
        $remaining = max(0.0, round((float) $booking->total_price - $verified, 2));

        $booking->remaining_amount = $remaining;
        $booking->status = $booking->settlementStatus();
        $booking->save();

        if ($booking->status === Booking::STATUS_LUNAS) {
            // Lewat model, bukan query builder, agar perubahan status unit ikut tercatat
            // pada jejak audit.
            Unit::find($booking->unit_id)?->update(['status' => Unit::STATUS_SOLD_OUT]);
        }
    }

    private function notifyVerificationUsers(string $title, string $message, Booking $booking): void
    {
        $handlerUserId = SalesProfile::where('id', $booking->sales_id)->value('user_id');

        if ($handlerUserId) {
            Notification::createForUser((int) $handlerUserId, $title, $message, 'payment', '/pembayaran');
        }

        // Siaran operasional hanya ke Super Admin; Monitoring Manajemen tidak ikut
        // menerima pemberitahuan alur sales (revisi 29).
        $this->notifyOperationalUsers($title, $message, 'payment', '/pembayaran');
    }
}
