<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerProjectSummary;
use App\Http\Controllers\Concerns\FiltersProjects;
use App\Models\Booking;
use App\Models\Cluster;
use App\Models\CustomerLead;
use App\Models\Payment;
use App\Models\SalesProfile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use FiltersProjects;
    use PerProjectSummary;

    /**
     * Deretan penjualan enam bulan terakhir dari pembayaran terverifikasi.
     * Bila $clusterId diisi, angka hanya dihitung untuk proyek tersebut.
     */
    private function monthlySeries(int $months = 6, ?int $clusterId = null): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);

        $revenue = $this->padaProyek(Payment::query(), $clusterId)
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->where('payment_date', '>=', $from->copy()->startOfMonth())
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, COALESCE(SUM(amount), 0) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $sold = $this->padaProyek(Booking::query(), $clusterId, 'unit')
            ->where('status', Booking::STATUS_LUNAS)
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
     * Konsumen, prospek, dan pembatalan per proyek. Kategori dibaca dari
     * relasi pemesanan + pembayaran memakai logika scope yang sama dengan
     * kartu statistik, jadi angka tabel proyek tidak menyimpang dari totalnya.
     */
    private function metrikLeadPerProyek(?int $clusterId = null): array
    {
        $leads = $this->leadProyek(CustomerLead::query(), $clusterId)
            ->with([
                'unit:id,cluster_id',
                'bookings:id,lead_id,status,unit_id',
                'bookings.unit:id,cluster_id',
                'bookings.payments:id,booking_id,verification_status',
            ])
            ->get();

        $hasil = [];

        foreach ($leads as $lead) {
            // Lead yang belum memilih unit dan belum memesan tidak dapat ditautkan ke
            // proyek mana pun; tetap dihitung agar tabelnya tidak kehilangan angka.
            $id = $this->proyekDariLead($lead) ?? 0;

            $hasil[$id] ??= [
                'leads_total'         => 0,
                'prospects'           => 0,
                'customers'           => 0,
                'cancelled_customers' => 0,
                'cold'                => 0,
                'warm'                => 0,
                'hot'                 => 0,
            ];

            $kategori = $lead->category();
            $hasil[$id]['leads_total']++;

            match ($kategori) {
                CustomerLead::CATEGORY_KONSUMEN => $hasil[$id]['customers']++,
                CustomerLead::CATEGORY_DIBATALKAN => $hasil[$id]['cancelled_customers']++,
                default => $hasil[$id]['prospects']++,
            };

            if ($kategori === CustomerLead::CATEGORY_PROSPEK && in_array($lead->status, CustomerLead::STATUSES, true)) {
                $hasil[$id][$lead->status]++;
            }
        }

        return $hasil;
    }

    /**
     * Rekap unit, pemesanan, pembayaran, dan konsumen per proyek (revisi 10,
     * diperluas revisi 32). Empat query agregat ditambah satu set lead, bukan
     * per baris proyek. Bila $clusterId diisi, hanya proyek itu yang dirangkum.
     */
    private function rekapPerProyek(?int $clusterId = null): array
    {
        $unit = DB::table('units')
            ->whereNull('deleted_at')
            ->when($clusterId, fn ($q) => $q->where('cluster_id', $clusterId))
            ->selectRaw("COALESCE(cluster_id, 0) as cluster_id,
                COUNT(*) as units_total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as units_available,
                SUM(CASE WHEN status = 'booking' THEN 1 ELSE 0 END) as units_booking,
                SUM(CASE WHEN status = 'sold_out' THEN 1 ELSE 0 END) as units_sold_out")
            ->groupByRaw('COALESCE(cluster_id, 0)')
            ->get()
            ->keyBy('cluster_id');

        $booking = DB::table('bookings')
            ->join('units', 'units.id', '=', 'bookings.unit_id')
            ->whereNull('bookings.deleted_at')
            ->when($clusterId, fn ($q) => $q->where('units.cluster_id', $clusterId))
            ->selectRaw('COALESCE(units.cluster_id, 0) as cluster_id,
                COUNT(*) as bookings_total,
                COUNT(DISTINCT bookings.sales_id) as sales_count,
                SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as bookings_cancelled,
                SUM(CASE WHEN bookings.status = ? AND bookings.delivery_status <> ? THEN 1 ELSE 0 END) as units_not_delivered',
                [Booking::STATUS_DIBATALKAN, Booking::STATUS_LUNAS, 'selesai'])
            ->groupByRaw('COALESCE(units.cluster_id, 0)')
            ->get()
            ->keyBy('cluster_id');

        $pembayaran = DB::table('payments')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->join('units', 'units.id', '=', 'bookings.unit_id')
            ->whereNull('payments.deleted_at')
            ->whereNull('bookings.deleted_at')
            ->when($clusterId, fn ($q) => $q->where('units.cluster_id', $clusterId))
            ->selectRaw('COALESCE(units.cluster_id, 0) as cluster_id,
                COALESCE(SUM(CASE WHEN payments.verification_status = ? THEN payments.amount ELSE 0 END), 0) as revenue,
                SUM(CASE WHEN payments.verification_status = ? THEN 1 ELSE 0 END) as payments_verified,
                SUM(CASE WHEN payments.verification_status = ? AND bookings.status <> ? THEN 1 ELSE 0 END) as payments_pending',
                [Payment::VERIFY_VERIFIED, Payment::VERIFY_VERIFIED, Payment::VERIFY_PENDING, Booking::STATUS_DIBATALKAN])
            ->groupByRaw('COALESCE(units.cluster_id, 0)')
            ->get()
            ->keyBy('cluster_id');

        $bookingAktif = DB::table('bookings')
            ->join('units', 'units.id', '=', 'bookings.unit_id')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', Booking::ACTIVE_STATUSES)
            ->when($clusterId, fn ($q) => $q->where('units.cluster_id', $clusterId))
            ->selectRaw('COALESCE(units.cluster_id, 0) as cluster_id, COUNT(*) as bookings_active')
            ->groupByRaw('COALESCE(units.cluster_id, 0)')
            ->get()
            ->keyBy('cluster_id');

        $lead = $this->metrikLeadPerProyek($clusterId);
        $nama = $this->namaProyek();

        // Baris = proyek aktif + proyek arsip yang masih punya data + ember
        // '(belum ada proyek)'. Tanpa filter, semua ember ikut tampil agar angka
        // tabel dapat direkonsiliasi dengan laporan konsumen.
        $ids = collect($unit->keys())
            ->concat($booking->keys())
            ->concat($pembayaran->keys())
            ->concat($bookingAktif->keys())
            ->concat(array_keys($lead))
            ->map(fn ($id) => (int) $id)
            ->when(
                $clusterId,
                fn ($k) => $k->filter(fn (int $id) => $id === $clusterId)
            )
            ->concat($clusterId ? collect() : Cluster::query()->aktif()->pluck('id')->map(fn ($id) => (int) $id))
            ->unique()
            ->values();

        $rows = [];

        foreach ($ids as $id) {
            $u = $unit->get($id);
            $b = $booking->get($id);
            $a = $bookingAktif->get($id);
            $p = $pembayaran->get($id);
            $l = $lead[$id] ?? [];

            $rows[] = [
                'cluster_id'          => $id === 0 ? null : $id,
                'name'                => $this->labelProyek($id === 0 ? null : $id, $nama),
                'units_total'         => (int) optional($u)->units_total,
                'units_available'     => (int) optional($u)->units_available,
                'units_booking'       => (int) optional($u)->units_booking,
                'units_sold_out'      => (int) optional($u)->units_sold_out,
                'bookings_total'      => (int) optional($b)->bookings_total,
                'bookings_active'     => (int) optional($a)->bookings_active,
                'bookings_cancelled'  => (int) optional($b)->bookings_cancelled,
                'units_not_delivered' => (int) optional($b)->units_not_delivered,
                'sales_count'         => (int) optional($b)->sales_count,
                'payments_pending'    => (int) optional($p)->payments_pending,
                'revenue'             => round((float) optional($p)->revenue, 2),
                'leads_total'         => (int) ($l['leads_total'] ?? 0),
                'prospects'           => (int) ($l['prospects'] ?? 0),
                'customers'           => (int) ($l['customers'] ?? 0),
                'cancelled_customers' => (int) ($l['cancelled_customers'] ?? 0),
            ];
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /** Nama baris tabel proyek: 0 berarti belum tertaut proyek mana pun. */
    private function labelDashboardProyek(int $id, array $nama): string
    {
        return $id === 0 ? '(belum ada proyek)' : ($nama[$id] ?? '(proyek arsip)');
    }

    /**
     * Pemesanan terbaru untuk kartu "daftar booking terbaru" pada dashboard.
     */
    private function recentBookings(?int $salesId = null, int $limit = 5, ?int $clusterId = null): array
    {
        $query = $this->padaProyek(Booking::with(['customerLead', 'unit', 'salesProfile.user']), $clusterId, 'unit')
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
            'project'      => optional(optional($b->unit)->cluster)->name,
            'sales'        => optional(optional($b->salesProfile)->user)->name,
            'booking_date' => $b->booking_date,
            'total_price'  => (float) $b->total_price,
            'remaining'    => (float) $b->remaining_amount,
            'status'       => $b->status,
        ])->all();
    }

    /**
     * Dashboard Super Admin: unit sold out, belum delivery, pendapatan, jumlah sales,
     * calon konsumen dan konsumen. Seluruh angka dapat dipersempit ke satu proyek
     * lewat parameter cluster_id (revisi 10).
     */
    public function superAdminDashboard(Request $request)
    {
        $proyek = $request->filled('cluster_id') ? (int) $request->cluster_id : null;

        $soldOut = $this->unitProyek(Unit::query(), $proyek)->where('status', Unit::STATUS_SOLD_OUT)->count();
        $notDelivered = $this->padaProyek(Booking::query(), $proyek, 'unit')
            ->where('status', Booking::STATUS_LUNAS)
            ->where('delivery_status', '!=', 'selesai')
            ->count();

        $revenue = (float) $this->padaProyek(Payment::query(), $proyek)
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->sum('amount');

        $totalSales = $proyek
            ? $this->padaProyek(Booking::query(), $proyek, 'unit')->distinct()->count('sales_id')
            : User::where('role', User::ROLE_SALES)->count();

        $cancelledPending = $this->padaProyek(Payment::query(), $proyek)
            ->where('verification_status', Payment::VERIFY_PENDING)
            ->whereDoesntHave('booking', fn ($b) => $b->where('status', Booking::STATUS_DIBATALKAN))
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'project_filter'        => $proyek,
                'projects'              => Cluster::query()->aktif()->orderBy('name')->get(['id', 'name']),
                'per_project'           => $this->rekapPerProyek($proyek),
                'total_units'           => $this->unitProyek(Unit::query(), $proyek)->count(),
                'units_available'       => $this->unitProyek(Unit::query(), $proyek)->where('status', Unit::STATUS_AVAILABLE)->count(),
                'units_booking'         => $this->unitProyek(Unit::query(), $proyek)->where('status', Unit::STATUS_BOOKING)->count(),
                'units_sold_out'        => $soldOut,
                'units_not_delivered'   => $notDelivered,
                'total_revenue'         => round($revenue, 2),
                'total_sales'           => $totalSales,
                'total_prospects'       => $this->leadProyek(CustomerLead::query(), $proyek)->prospects()->count(),
                'total_customers'       => $this->leadProyek(CustomerLead::query(), $proyek)->customers()->count(),
                'total_cancelled'       => $this->leadProyek(CustomerLead::query(), $proyek)->cancelled()->count(),
                'total_leads'           => $this->leadProyek(CustomerLead::query(), $proyek)->count(),
                'total_bookings'        => $this->padaProyek(Booking::query(), $proyek, 'unit')->count(),
                'active_bookings'       => $this->padaProyek(Booking::query(), $proyek, 'unit')
                    ->whereIn('status', Booking::ACTIVE_STATUSES)->count(),
                'cancelled_bookings'    => $this->padaProyek(Booking::query(), $proyek, 'unit')
                    ->where('status', Booking::STATUS_DIBATALKAN)->count(),
                'payments_pending'      => $cancelledPending,
                'sales_by_month'        => $this->monthlySeries(6, $proyek),
                'recent_bookings'       => $this->recentBookings(null, 5, $proyek),
            ],
        ], 200);
    }

    /**
     * Dashboard Monitoring Manajemen: rekapitulasi penjualan seluruh sales.
     * Revisi 32 menambahkan penapis proyek dan rincian per proyek supaya angka
     * yang disajikan berasal dari masing-masing proyek, bukan gabungan.
     */
    public function adminSalesDashboard(Request $request)
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        $proyek = $this->clusterIdDari($request);

        $perSales = SalesProfile::query()
            ->join('users', 'users.id', '=', 'sales_profiles.user_id')
            ->where('users.role', User::ROLE_SALES)
            ->whereNull('users.deleted_at')
            ->leftJoinSub(
                $this->padaProyek(Booking::query(), $proyek, 'unit')
                    ->select('sales_id', DB::raw('COUNT(*) as bookings_count'))
                    ->groupBy('sales_id'),
                'b',
                'b.sales_id',
                '=',
                'sales_profiles.id'
            )
            ->leftJoinSub(
                $this->padaProyek(Booking::query(), $proyek, 'unit')
                    ->select('sales_id', DB::raw('COALESCE(SUM(total_price), 0) as revenue'))
                    ->whereBetween('booking_date', [$start, $end])
                    ->groupBy('sales_id'),
                'r',
                'r.sales_id',
                '=',
                'sales_profiles.id'
            )
            ->leftJoinSub(
                $this->leadProyek(CustomerLead::query(), $proyek)
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
                'project_filter' => $proyek,
                'projects'       => Cluster::query()->aktif()->orderBy('name')->get(['id', 'name']),
                'per_project'    => $this->rekapPerProyek($proyek),
                'period'     => ['start_date' => $start, 'end_date' => $end],
                'totals'     => [
                    'sales_count'     => $perSales->count(),
                    'total_bookings'  => (int) $perSales->sum('bookings_count'),
                    'total_revenue'   => round((float) $perSales->sum('revenue'), 2),
                    'total_leads'     => (int) $perSales->sum('leads_count'),
                ],
                'counters'   => [
                    'total_units'        => $this->unitProyek(Unit::query(), $proyek)->count(),
                    'units_available'    => $this->unitProyek(Unit::query(), $proyek)->where('status', Unit::STATUS_AVAILABLE)->count(),
                    'units_sold_out'     => $this->unitProyek(Unit::query(), $proyek)->where('status', Unit::STATUS_SOLD_OUT)->count(),
                    'total_customers'    => $this->leadProyek(CustomerLead::query(), $proyek)->customers()->count(),
                    'total_prospects'    => $this->leadProyek(CustomerLead::query(), $proyek)->prospects()->count(),
                    'total_cancelled'    => $this->leadProyek(CustomerLead::query(), $proyek)->cancelled()->count(),
                    'active_bookings'    => $this->padaProyek(Booking::query(), $proyek, 'unit')
                        ->whereIn('status', Booking::ACTIVE_STATUSES)->count(),
                    'payments_pending'   => $this->padaProyek(Payment::query(), $proyek)
                        ->where('verification_status', Payment::VERIFY_PENDING)
                        ->whereDoesntHave('booking', fn ($b) => $b->where('status', Booking::STATUS_DIBATALKAN))
                        ->count(),
                ],
                'sales_by_month'  => $this->monthlySeries(6, $proyek),
                'recent_bookings' => $this->recentBookings(null, 5, $proyek),
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

        // Prospek hanya cold/warm/hot; yang sudah memesan dihitung terpisah sebagai konsumen.
        $prospek = CustomerLead::where('sales_id', $profile->id)->prospects();

        $byStatus = (clone $prospek)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $bookings = Booking::where('sales_id', $profile->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'sales_id'          => $profile->id,
                'sales_code'        => $profile->sales_code,
                'total_leads'       => CustomerLead::where('sales_id', $profile->id)->count(),
                'total_prospects'   => (int) $byStatus->sum(),
                'total_customers'   => CustomerLead::where('sales_id', $profile->id)->customers()->count(),
                'total_cancelled'   => CustomerLead::where('sales_id', $profile->id)->cancelled()->count(),
                'leads_by_status'   => [
                    'cold'    => (int) $byStatus->get(CustomerLead::STATUS_COLD, 0),
                    'warm'    => (int) $byStatus->get(CustomerLead::STATUS_WARM, 0),
                    'hot'     => (int) $byStatus->get(CustomerLead::STATUS_HOT, 0),
                ],
                'total_bookings'    => (int) (clone $bookings)->whereIn('status', Booking::ACTIVE_STATUSES)->count(),
                'bookings_by_status' => [
                    'menunggu dp' => (int) (clone $bookings)->where('status', Booking::STATUS_MENUNGGU_DP)->count(),
                    'dp lunas'    => (int) (clone $bookings)->where('status', Booking::STATUS_DP_LUNAS)->count(),
                    'cicilan'     => (int) (clone $bookings)->where('status', Booking::STATUS_CICILAN)->count(),
                    'lunas'       => (int) (clone $bookings)->where('status', Booking::STATUS_LUNAS)->count(),
                    'dibatalkan'  => (int) (clone $bookings)->where('status', Booking::STATUS_DIBATALKAN)->count(),
                ],
                'total_closing'     => round((float) Booking::where('sales_id', $profile->id)
                    ->whereIn('status', Booking::ACTIVE_STATUSES)->sum('total_price'), 2),
                'payments_total'    => Payment::whereHas('booking', fn ($q) => $q->where('sales_id', $profile->id))->count(),
                'payments_pending'  => Payment::where('verification_status', Payment::VERIFY_PENDING)
                    ->whereHas('booking', fn ($q) => $q->where('sales_id', $profile->id)
                        ->whereIn('status', Booking::ACTIVE_STATUSES))->count(),
                'units_available'   => Unit::where('status', Unit::STATUS_AVAILABLE)->count(),
                'recent_bookings'   => $this->recentBookings($profile->id),
                'pending_follow_up' => (clone $prospek)
                    ->where(fn ($q) => $q->whereNull('last_follow_up')->orWhereDate('last_follow_up', '<', now()->subDays(7)))
                    ->count(),
            ],
        ], 200);
    }
}
