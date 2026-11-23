<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\CustomerLead;
use App\Models\FollowUpHistory;
use App\Models\Payment;
use App\Models\PaymentScheme;
use App\Models\SalesProfile;
use App\Models\SalesTarget;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Alur transaksi contoh: lead -> follow up -> booking -> DP -> verifikasi -> pelunasan.
 * Berguna untuk menguji dashboard, laporan, dan notifikasi dengan data yang nyata.
 */
class DemoFlowSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = PaymentScheme::where('scheme_name', 'Cash Keras')->first();
        $kpr = PaymentScheme::where('scheme_name', 'KPR')->first();

        if (!$scheme || !$kpr) {
            $this->command?->warn('Skema pembayaran belum ada, jalankan PaymentSchemeSeeder lebih dulu.');

            return;
        }

        $profiles = SalesProfile::with('user')->where('status', 'aktif')->get();

        if ($profiles->isEmpty()) {
            return;
        }

        // Lead lengkap dengan riwayat follow up.
        $leadNames = [
            ['Rina Wulandari', '081233000001', CustomerLead::STATUS_WARM],
            ['Dimas Prakoso', '081233000002', CustomerLead::STATUS_HOT],
            ['Yoga Saputra', '081233000003', CustomerLead::STATUS_HOT],
        ];

        $leads = [];

        foreach ($leadNames as $i => [$name, $phone, $status]) {
            $profile = $profiles[$i % $profiles->count()];

            $lead = CustomerLead::updateOrCreate(
                ['phone' => $phone],
                [
                    'name'            => $name,
                    'email'           => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                    'first_chat_date' => now()->subDays(20 - $i)->toDateString(),
                    'address'         => 'Jl. Melati No. ' . ($i + 1) . ', Semarang',
                    'status'          => $status,
                    'sales_id'        => $profile->id,
                    'budget'          => 800000000 + $i * 100000000,
                    'note'            => 'Sumber: telemarketing event akhir tahun.',
                    'source'          => 'manual',
                    'last_follow_up'  => now()->subDays($i + 1),
                ]
            );

            FollowUpHistory::updateOrCreate(
                ['lead_id' => $lead->id, 'follow_up_date' => now()->subDays($i + 1)],
                [
                    'sales_id'      => $profile->id,
                    'status_before' => CustomerLead::STATUS_COLD,
                    'status_after'  => $status,
                    'method'        => $i % 2 === 0 ? 'whatsapp' : 'phone',
                    'note'          => 'Menjelaskan skema pembayaran dan mengajak survey lokasi.',
                ]
            );

            $leads[] = $lead->fresh();
        }

        // Pemesanan + pembayaran contoh pada unit yang masih tersedia.
        $available = Unit::where('status', Unit::STATUS_AVAILABLE)->orderBy('unit_code')->get();

        if ($available->count() < 2) {
            return;
        }

        $this->makeBooking($leads[1], $available[0], $profiles[0], $scheme, fullyPaid: true);
        $this->makeBooking($leads[2], $available[1], $profiles->count() > 1 ? $profiles[1] : $profiles[0], $kpr, fullyPaid: false);

        // Target penjualan periode berjalan.
        foreach ($profiles as $profile) {
            $achievedUnits = Booking::where('sales_id', $profile->id)
                ->where('status', Booking::STATUS_LUNAS)
                ->count();

            $achievedAmount = (float) Payment::verified()
                ->whereIn('booking_id', Booking::where('sales_id', $profile->id)->pluck('id'))
                ->sum('amount');

            SalesTarget::updateOrCreate(
                [
                    'sales_id'     => $profile->id,
                    'period_start' => now()->startOfMonth()->toDateString(),
                    'period_end'   => now()->endOfMonth()->toDateString(),
                ],
                [
                    'target_units'    => 3,
                    'target_amount'   => 1000000000,
                    'achieved_units'  => $achievedUnits,
                    'achieved_amount' => $achievedAmount,
                ]
            );
        }
    }

    private function makeBooking(CustomerLead $lead, Unit $unit, SalesProfile $profile, PaymentScheme $scheme, bool $fullyPaid): void
    {
        $breakdown = $scheme->calculateBreakdown((float) $unit->price);

        $existing = Booking::where('lead_id', $lead->id)->where('unit_id', $unit->id)->first();

        $booking = Booking::updateOrCreate(
            ['lead_id' => $lead->id, 'unit_id' => $unit->id],
            [
                'booking_code'        => $existing?->booking_code ?: $this->nextBookingCode(),
                'sales_id'            => $profile->id,
                'payment_scheme_id'   => $scheme->id,
                'booking_date'        => now()->subDays(10),
                'total_price'         => $unit->price,
                'down_payment_amount' => $breakdown['down_payment_amount'],
                'installment_amount'  => $breakdown['installment_amount'],
                'remaining_amount'    => $unit->price,
                'status'              => Booking::STATUS_MENUNGGU_DP,
                'delivery_status'     => 'belum proses',
                'notes'               => 'Dibuat oleh seeder contoh.',
            ]
        );

        $superAdmin = User::where('role', User::ROLE_SUPER_ADMIN)->first();

        $payments = [
            ['type' => $scheme->scheme_type, 'amount' => $breakdown['down_payment_amount'], 'days' => 8],
        ];

        if ($fullyPaid) {
            $payments[] = ['type' => $scheme->scheme_type, 'amount' => (float) $unit->price - $breakdown['down_payment_amount'], 'days' => 2];
        } else {
            $payments[] = ['type' => $scheme->scheme_type, 'amount' => $breakdown['installment_amount'], 'days' => 3];
        }

        foreach ($payments as $p) {
            $payment = $booking->payments()->updateOrCreate(
                ['payment_date' => now()->subDays($p['days'])->toDateString(), 'amount' => $p['amount']],
                [
                    'payment_type'        => $p['type'],
                    'payment_method'      => Payment::METHOD_TRANSFER,
                    'bank_name'           => 'Bank Central Asia',
                    'account_number'      => '8890122345',
                    'account_holder'      => 'PT Lumin Park Properti',
                    'verification_status' => Payment::VERIFY_VERIFIED,
                    'verified_by'         => $superAdmin?->id,
                    'verified_at'         => now()->subDays($p['days'] - 1),
                    'notes'               => 'Terverifikasi saat seed data contoh.',
                ]
            );
        }

        $verified = (float) $booking->payments()->verified()->sum('amount');
        $remaining = max(0.0, round((float) $unit->price - $verified, 2));

        $booking->remaining_amount = $remaining;
        $booking->status = $booking->settlementStatus();
        $booking->save();

        $unit->update([
            'status' => $remaining <= 0 ? Unit::STATUS_SOLD_OUT : ($fullyPaid ? Unit::STATUS_SOLD_OUT : Unit::STATUS_BOOKING),
        ]);

        // Nilai status 'booking' tidak dipakai lagi: lead yang sudah memesan ditampilkan
        // sebagai Hot dan kategorinya dihitung dari pembayaran terverifikasi.
        $lead->update(['status' => CustomerLead::STATUS_HOT]);
    }

    /** Kode booking berikutnya untuk bulan berjalan, mengikuti kode terbesar yang sudah ada. */
    private function nextBookingCode(): string
    {
        $prefix = 'BK' . now()->format('Ym');
        $last = Booking::where('booking_code', 'like', $prefix . '%')->orderByDesc('booking_code')->value('booking_code');
        $sequence = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
