<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerLead;
use App\Models\Notification;
use App\Models\PaymentScheme;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // 1. Lihat Daftar Booking (Read All)
    public function index(Request $request)
    {
        $query = Booking::with(['customerLead', 'salesProfile.user', 'unit.cluster', 'paymentScheme']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        if ($request->filled('sales_id')) {
            $query->where('sales_id', $request->sales_id);
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
            'lead_id'           => 'required|exists:customer_leads,id',
            'sales_id'          => 'required|exists:sales_profiles,id',
            'unit_id'           => 'required|exists:units,id',
            'payment_scheme_id' => 'required|exists:payment_schemes,id',
            'booking_date'      => 'required|date',
            'notes'             => 'nullable|string',
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);

        if ($unit->status !== Unit::STATUS_AVAILABLE) {
            return response()->json([
                'success' => false,
                'message' => 'Unit tidak dapat dipesan karena statusnya bukan tersedia.',
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
            'status'                => Booking::STATUS_MENUNGGU_DP,
            'delivery_status'       => 'belum proses',
            'notes'                 => $validated['notes'] ?? null,
        ]);

        $unit->update(['status' => Unit::STATUS_BOOKING]);
        $lead->update(['status' => CustomerLead::STATUS_BOOKING]);

        $this->notifyOperationalUsers(
            'Booking baru dibuat',
            sprintf('%s memesan unit %s (%s)', $lead->name, $unit->unit_code, $booking->booking_code),
            'booking',
            '/booking'
        );

        return response()->json([
            'success' => true,
            'message' => 'Pemesanan (Booking) berhasil dibuat',
            'data'    => $booking->load(['customerLead', 'salesProfile.user', 'unit', 'paymentScheme']),
        ], 201);
    }

    // 3. Detail Booking (Read Single)
    public function show($id)
    {
        $booking = Booking::with([
            'customerLead', 'salesProfile.user', 'unit.cluster', 'paymentScheme', 'payments.verifier',
        ])->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data booking tidak ditemukan'], 404);
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

        $validated = $request->validate([
            'lead_id'           => 'sometimes|exists:customer_leads,id',
            'sales_id'          => 'sometimes|exists:sales_profiles,id',
            'unit_id'           => 'sometimes|exists:units,id',
            'payment_scheme_id' => 'sometimes|exists:payment_schemes,id',
            'booking_date'      => 'sometimes|date',
            'delivery_status'   => 'sometimes|in:belum proses,sedang proses,selesai',
            'notes'             => 'nullable|string',
        ]);

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
    public function destroy($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data booking tidak ditemukan'], 404);
        }

        $unit = Unit::find($booking->unit_id);
        if ($unit && $unit->status === Unit::STATUS_BOOKING) {
            $unit->update(['status' => Unit::STATUS_AVAILABLE]);
        }

        $lead = CustomerLead::find($booking->lead_id);
        if ($lead && $lead->status === CustomerLead::STATUS_BOOKING) {
            $lead->update(['status' => CustomerLead::STATUS_HOT]);
        }

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data booking berhasil dihapus',
        ], 200);
    }

    private function generateBookingCode(): string
    {
        $prefix = 'BK' . now()->format('Ym');

        $last = Booking::where('booking_code', 'like', $prefix . '%')
            ->orderByDesc('booking_code')
            ->value('booking_code');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function notifyOperationalUsers(string $title, string $message, string $type, ?string $link): void
    {
        $recipients = User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN_SALES])
            ->where('status', User::STATUS_AKTIF)
            ->pluck('id');

        foreach ($recipients as $userId) {
            Notification::createForUser((int) $userId, $title, $message, $type, $link);
        }
    }
}
