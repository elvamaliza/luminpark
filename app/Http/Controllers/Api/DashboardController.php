<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerLead;
use App\Models\Payment;
use App\Models\SalesProfile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Deretan penjualan enam bulan terakhir dari pembayaran terverifikasi.
     */
    private function monthlySeries(int $months = 6): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);

        $revenue = Payment::where('verification_status', Payment::VERIFY_VERIFIED)
            ->where('payment_date', '>=', $from->copy()->startOfMonth())
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, COALESCE(SUM(amount), 0) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $sold = Booking::where('status', Booking::STATUS_LUNAS)
            ->where('booking_date', '>=', $from->copy()->startOfMonth())
            ->selectRaw("DATE_FORMAT(booking_date, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $from->copy()->addMonths($i);
            $ym = $month->format('Y-m');
            $series[] = [
                'month'        => $ym,
                'label'        => $month->translatedFormat('M Y'),
                'revenue'      => round((float) $revenue->get($ym, 0), 2),
                'units_sold'   => (int) $sold->get($ym, 0),
            ];
        }

        return $series;
    }

    /**
     * Pemesanan terbaru untuk kartu "daftar booking terbaru" pada dashboard.
     */
    private function recentBookings(?int $salesId = null, int $limit = 5): array
    {
        $query = Booking::with(['customerLead', 'unit', 'salesProfile.user'])
            ->latest('booking_date')
            ->latest('id');

        if ($salesId) {
            $query->where('sales_id', $salesId);
        }

        return $query->limit($limit)->get()->map(fn (Booking $b) => [
            'id'           => $b->id,
            'booking_code' => $b->booking_code,
            'customer'     => optional($b->customerLead)->name,
            'unit'         => optional($b->unit)->unit_code,
            'unit_type'    => optional($b->unit)->unit_type,
            'sales'        => optional(optional($b->salesProfile)->user)->name,
            'booking_date' => $b->booking_date,
            'total_price'  => (float) $b->total_price,
            'remaining'    => (float) $b->remaining_amount,
            'status'       => $b->status,
        ])->all();
    }

    /**
     * Dashboard Super Admin: unit sold out, belum delivery, pendapatan, jumlah sales,
     * calon konsumen dan konsumen.
     */
    public function superAdminDashboard()
    {
        $soldOut = Unit::where('status', Unit::STATUS_SOLD_OUT)->count();
        $notDelivered = Booking::where('status', Booking::STATUS_LUNAS)
            ->where('delivery_status', '!=', 'selesai')
            ->count();

        $revenue = (float) Payment::where('verification_status', Payment::VERIFY_VERIFIED)->sum('amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_units'         => Unit::count(),
                'units_available'     => Unit::where('status', Unit::STATUS_AVAILABLE)->count(),
                'units_booking'       => Unit::where('status', Unit::STATUS_BOOKING)->count(),
                'units_sold_out'      => $soldOut,
                'units_not_delivered' => $notDelivered,
                'total_revenue'       => round($revenue, 2),
                'total_sales'         => User::where('role', User::ROLE_SALES)->count(),
                'total_prospects'     => CustomerLead::where('status', '!=', CustomerLead::STATUS_BOOKING)->count(),
                'total_customers'     => CustomerLead::where('status', CustomerLead::STATUS_BOOKING)->count(),
                'total_leads'         => CustomerLead::count(),
                'total_bookings'      => Booking::count(),
                'active_bookings'     => Booking::whereIn('status', [
                    Booking::STATUS_MENUNGGU_DP, Booking::STATUS_DP_LUNAS, Booking::STATUS_CICILAN,
                ])->count(),
                'payments_pending'    => Payment::where('verification_status', Payment::VERIFY_PENDING)->count(),
                'sales_by_month'      => $this->monthlySeries(),
                'recent_bookings'     => $this->recentBookings(),
            ],
        ], 200);
    }

    /**
     * Dashboard Admin Sales: rekapitulasi penjualan seluruh sales.
     */
    public function adminSalesDashboard(Request $request)
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        $perSales = SalesProfile::query()
            ->join('users', 'users.id', '=', 'sales_profiles.user_id')
            ->where('users.role', User::ROLE_SALES)
            ->whereNull('users.deleted_at')
            ->leftJoinSub(
                Booking::query()
                    ->select('sales_id', DB::raw('COUNT(*) as bookings_count'))
                    ->groupBy('sales_id'),
                'b',
                'b.sales_id',
                '=',
                'sales_profiles.id'
            )
            ->leftJoinSub(
                Booking::query()
                    ->select('sales_id', DB::raw('COALESCE(SUM(total_price), 0) as revenue'))
                    ->where('booking_date', 'between', [$start, $end])
                    ->groupBy('sales_id'),
                'r',
                'r.sales_id',
                '=',
                'sales_profiles.id'
            )
            ->leftJoinSub(
                CustomerLead::query()
                    ->select('sales_id', DB::raw('COUNT(*) as leads_count'))
                    ->groupBy('sales_id'),
                'l',
                'l.sales_id',
                '=',
                'sales_profiles.id'
            )
            ->orderBy('users.name')
            ->get([
                'sales_profiles.id',
                'sales_profiles.sales_code',
                'users.name',
                'users.email',
                'sales_profiles.status',
                DB::raw('COALESCE(b.bookings_count, 0) as bookings_count'),
                DB::raw('COALESCE(r.revenue, 0) as revenue'),
                DB::raw('COALESCE(l.leads_count, 0) as leads_count'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'period'     => ['start_date' => $start, 'end_date' => $end],
                'totals'     => [
                    'sales_count'     => $perSales->count(),
                    'total_bookings'  => (int) $perSales->sum('bookings_count'),
                    'total_revenue'   => round((float) $perSales->sum('revenue'), 2),
                    'total_leads'     => (int) $perSales->sum('leads_count'),
                ],
                'counters'   => [
                    'total_units'        => Unit::count(),
                    'units_available'    => Unit::where('status', Unit::STATUS_AVAILABLE)->count(),
                    'units_sold_out'     => Unit::where('status', Unit::STATUS_SOLD_OUT)->count(),
                    'total_customers'    => CustomerLead::where('status', CustomerLead::STATUS_BOOKING)->count(),
                    'active_bookings'    => Booking::whereIn('status', [
                        Booking::STATUS_MENUNGGU_DP, Booking::STATUS_DP_LUNAS, Booking::STATUS_CICILAN,
                    ])->count(),
                    'payments_pending'   => Payment::where('verification_status', Payment::VERIFY_PENDING)->count(),
                ],
                'sales_by_month'  => $this->monthlySeries(),
                'recent_bookings' => $this->recentBookings(),
                'per_sales'  => $perSales,
            ],
        ], 200);
    }

    /**
     * Dashboard Sales: calon konsumen yang ditangani, dikelompokkan per status.
     * sales_id diturunkan dari token, bukan dari query string.
     */
    public function salesDashboard(Request $request)
    {
        $profile = $request->user()->salesProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini belum memiliki profil sales.',
            ], 403);
        }

        $byStatus = CustomerLead::where('sales_id', $profile->id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $bookings = Booking::where('sales_id', $profile->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'sales_id'          => $profile->id,
                'sales_code'        => $profile->sales_code,
                'total_leads'       => (int) $byStatus->sum(),
                'leads_by_status'   => [
                    'cold'    => (int) $byStatus->get(CustomerLead::STATUS_COLD, 0),
                    'warm'    => (int) $byStatus->get(CustomerLead::STATUS_WARM, 0),
                    'hot'     => (int) $byStatus->get(CustomerLead::STATUS_HOT, 0),
                    'booking' => (int) $byStatus->get(CustomerLead::STATUS_BOOKING, 0),
                ],
                'total_bookings'    => (int) (clone $bookings)->count(),
                'bookings_by_status' => [
                    'menunggu dp' => (int) (clone $bookings)->where('status', Booking::STATUS_MENUNGGU_DP)->count(),
                    'dp lunas'    => (int) (clone $bookings)->where('status', Booking::STATUS_DP_LUNAS)->count(),
                    'cicilan'     => (int) (clone $bookings)->where('status', Booking::STATUS_CICILAN)->count(),
                    'lunas'       => (int) (clone $bookings)->where('status', Booking::STATUS_LUNAS)->count(),
                ],
                'total_closing'     => round((float) Booking::where('sales_id', $profile->id)->sum('total_price'), 2),
                'payments_total'    => Payment::whereHas('booking', fn ($q) => $q->where('sales_id', $profile->id))->count(),
                'payments_pending'  => Payment::where('verification_status', Payment::VERIFY_PENDING)
                    ->whereHas('booking', fn ($q) => $q->where('sales_id', $profile->id))->count(),
                'units_available'   => Unit::where('status', Unit::STATUS_AVAILABLE)->count(),
                'recent_bookings'   => $this->recentBookings($profile->id),
                'pending_follow_up' => CustomerLead::where('sales_id', $profile->id)
                    ->where('status', '!=', CustomerLead::STATUS_BOOKING)
                    ->where(fn ($q) => $q->whereNull('last_follow_up')->orWhereDate('last_follow_up', '<', now()->subDays(7)))
                    ->count(),
            ],
        ], 200);
    }
}
