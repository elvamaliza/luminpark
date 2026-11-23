<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FiltersProjects;
use App\Http\Controllers\Concerns\PerProjectSummary;
use App\Models\Booking;
use App\Models\Cluster;
use App\Models\CustomerLead;
use App\Models\Payment;
use App\Models\SalesProfile;
use App\Models\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use FiltersProjects;
    use PerProjectSummary;

    /**
     * Daftar proyek untuk penapis pada halaman laporan (revisi 32).
     */
    private function daftarProyek()
    {
        return Cluster::query()->aktif()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Laporan penjualan unit.
     */
    public function salesReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $proyek = $this->clusterIdDari($request);

        $rows = $this->padaProyek(
            Booking::with(['customerLead', 'salesProfile.user', 'unit', 'paymentScheme']),
            $proyek,
            'unit'
        )
            ->whereBetween('booking_date', [$start, $end])
            ->orderBy('booking_date')
            ->get();

        $rows = $this->tandaiProyek($rows, fn (Booking $b) => optional($b->unit)->cluster_id);

        return response()->json([
            'success'               => true,
            'period'                => ['start_date' => $start, 'end_date' => $end],
            'project_filter'        => $proyek,
            'projects'              => $this->daftarProyek(),
            'per_project'           => $this->rangkumPerProyek(
                $rows,
                fn (Booking $b) => optional($b->unit)->cluster_id,
                fn (array $group) => [
                    'transactions'    => count($group),
                    'cancelled'       => collect($group)->where('status', Booking::STATUS_DIBATALKAN)->count(),
                    'sales_value'     => round((float) collect($group)->where('status', '!=', Booking::STATUS_DIBATALKAN)->sum('total_price'), 2),
                    'remaining_value' => round((float) collect($group)->where('status', '!=', Booking::STATUS_DIBATALKAN)->sum('remaining_amount'), 2),
                ]
            ),
            'total_transactions'    => $rows->count(),
            'total_cancelled'       => $rows->where('status', Booking::STATUS_DIBATALKAN)->count(),
            'total_sales_value'     => round((float) $rows->where('status', '!=', Booking::STATUS_DIBATALKAN)->sum('total_price'), 2),
            'total_remaining_value' => round((float) $rows->where('status', '!=', Booking::STATUS_DIBATALKAN)->sum('remaining_amount'), 2),
            'data'                  => $rows,
        ], 200);
    }

    /**
     * Laporan pelunasan (pembayaran terverifikasi).
     */
    public function settlementReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $proyek = $this->clusterIdDari($request);

        $rows = $this->padaProyek(
            Payment::with(['booking.customerLead', 'booking.unit', 'verifier']),
            $proyek
        )
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->whereBetween('payment_date', [$start, $end])
            ->orderBy('payment_date')
            ->get();

        $rows = $this->tandaiProyek($rows, fn (Payment $p) => optional(optional($p->booking)->unit)->cluster_id);

        $byConsumer = $rows->groupBy(fn (Payment $p) => $p->booking?->customerLead?->name ?? '(tanpa nama)')
            ->map(fn ($group) => [
                'transactions' => $group->count(),
                'total_amount' => round((float) $group->sum('amount'), 2),
            ]);

        $byUnit = $rows->groupBy(fn (Payment $p) => $p->booking?->unit?->unit_code ?? '(tanpa unit)')
            ->map(fn ($group) => [
                'transactions' => $group->count(),
                'total_amount' => round((float) $group->sum('amount'), 2),
            ]);

        $byScheme = $rows->groupBy(fn (Payment $p) => (string) $p->payment_type)
            ->map(fn ($group) => [
                'transactions' => $group->count(),
                'total_amount' => round((float) $group->sum('amount'), 2),
            ]);

        return response()->json([
            'success'              => true,
            'period'               => ['start_date' => $start, 'end_date' => $end],
            'project_filter'       => $proyek,
            'projects'             => $this->daftarProyek(),
            'per_project'          => $this->rangkumPerProyek(
                $rows,
                fn (Payment $p) => optional(optional($p->booking)->unit)->cluster_id,
                fn (array $group) => [
                    'transactions' => count($group),
                    'total_amount' => round((float) collect($group)->sum('amount'), 2),
                ]
            ),
            'total_transactions'   => $rows->count(),
            'total_settled_amount' => round((float) $rows->sum('amount'), 2),
            'recap'                => [
                'by_consumer' => $byConsumer,
                'by_unit'     => $byUnit,
                'by_scheme'   => $byScheme,
            ],
            'data'                 => $rows,
        ], 200);
    }

    /**
     * Laporan kinerja sales: jumlah leads, booking, closing, dan capaian target.
     * Revisi 32: angka per sales dapat dipersempit ke satu proyek dan dilengkapi
     * rincian per proyek.
     */
    public function salesPerformanceReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $proyek = $this->clusterIdDari($request);

        $rows = SalesProfile::with('user')
            ->withCount([
                'customerLeads' => fn ($q) => $this->leadProyek($q, $proyek),
                'bookings' => fn ($q) => $this->padaProyek($q, $proyek, 'unit'),
            ])
            ->with(['targets' => fn ($q) => $q->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start)])
            ->withSum(['bookings' => fn ($q) => $this->padaProyek($q, $proyek, 'unit')], 'total_price')
            ->get()
            ->map(function (SalesProfile $profile) use ($start, $end, $proyek) {
                $closed = $this->padaProyek(
                    Booking::where('sales_id', $profile->id)
                        ->where('status', Booking::STATUS_LUNAS)
                        ->whereBetween('booking_date', [$start, $end]),
                    $proyek,
                    'unit'
                );

                $target = $profile->targets->first();

                return [
                    'sales_id'        => $profile->id,
                    'sales_code'      => $profile->sales_code,
                    'name'            => $profile->user?->name,
                    'email'           => $profile->user?->email,
                    'status'          => $profile->status,
                    'total_leads'     => (int) $profile->customer_leads_count,
                    'total_bookings'  => (int) $profile->bookings_count,
                    'total_closing'   => round((float) $profile->bookings_sum_total_price, 2),
                    'units_sold'      => (int) (clone $closed)->count(),
                    'revenue_sold'    => round((float) (clone $closed)->sum('total_price'), 2),
                    'target_units'    => $target?->target_units,
                    'achieved_units'  => $target?->achieved_units,
                    'unit_progress'   => $target?->unit_progress,
                    'target_amount'   => $target?->target_amount,
                    'achieved_amount' => $target?->achieved_amount,
                    'amount_progress' => $target?->amount_progress,
                ];
            });

        $bookingProyek = $this->padaProyek(
            Booking::with('unit:id,cluster_id')->whereBetween('booking_date', [$start, $end]),
            $proyek,
            'unit'
        )->get();

        return response()->json([
            'success' => true,
            'period'  => ['start_date' => $start, 'end_date' => $end],
            'project_filter' => $proyek,
            'projects'       => $this->daftarProyek(),
            'per_project'    => $this->rangkumPerProyek(
                $bookingProyek,
                fn (Booking $b) => optional($b->unit)->cluster_id,
                fn (array $group) => [
                    'transactions'  => count($group),
                    'units_sold'    => collect($group)->where('status', Booking::STATUS_LUNAS)->count(),
                    'revenue_sold'  => round((float) collect($group)->where('status', Booking::STATUS_LUNAS)->sum('total_price'), 2),
                    'sales_count'   => collect($group)->pluck('sales_id')->filter()->unique()->count(),
                ]
            ),
            'totals'  => [
                'total_leads'    => (int) $rows->sum('total_leads'),
                'total_bookings' => (int) $rows->sum('total_bookings'),
                'total_closing'  => round((float) $rows->sum('total_closing'), 2),
                'units_sold'     => (int) $rows->sum('units_sold'),
            ],
            'data'    => $rows->values(),
        ], 200);
    }

    /**
     * Laporan konsumen dan calon konsumen.
     */
    public function consumerReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $proyek = $this->clusterIdDari($request);

        $query = CustomerLead::with([
            'salesProfile.user',
            'unit:id,cluster_id,unit_code',
            'bookings:id,lead_id,status,unit_id',
            'bookings.unit:id,cluster_id',
            'bookings.payments:id,booking_id,verification_status',
            'followUpHistories' => fn ($q) => $q->latest('follow_up_date')->limit(1),
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Revisi 5 & 7: pembatalan dilihat lewat kategori, bukan lagi status 'booking'.
        if ($request->filled('category')) {
            match ($request->category) {
                CustomerLead::CATEGORY_PROSPEK => $query->prospects(),
                CustomerLead::CATEGORY_KONSUMEN => $query->customers(),
                CustomerLead::CATEGORY_DIBATALKAN => $query->cancelled(),
                CustomerLead::CATEGORY_RIWAYAT => $query->history(),
                default => null,
            };
        }

        $rows = $this->tandaiProyek(
            $this->denganKategoriLead(
                $this->leadProyek($query, $proyek)
                    ->whereBetween('created_at', [$start, $end . ' 23:59:59'])
                    ->orderBy('name')
                    ->get()
            ),
            fn (CustomerLead $l) => $this->proyekDariLead($l)
        );

        return response()->json([
            'success' => true,
            'period'  => ['start_date' => $start, 'end_date' => $end],
            'project_filter' => $proyek,
            'projects'       => $this->daftarProyek(),
            'per_project'    => $this->rangkumPerProyek(
                $rows,
                fn (CustomerLead $l) => $this->proyekDariLead($l),
                fn (array $group) => [
                    'total'     => count($group),
                    'cold'      => collect($group)->where('category', CustomerLead::CATEGORY_PROSPEK)->where('status', CustomerLead::STATUS_COLD)->count(),
                    'warm'      => collect($group)->where('category', CustomerLead::CATEGORY_PROSPEK)->where('status', CustomerLead::STATUS_WARM)->count(),
                    'hot'       => collect($group)->where('category', CustomerLead::CATEGORY_PROSPEK)->where('status', CustomerLead::STATUS_HOT)->count(),
                    'prospek'   => collect($group)->where('category', CustomerLead::CATEGORY_PROSPEK)->count(),
                    'customer'  => collect($group)->where('category', CustomerLead::CATEGORY_KONSUMEN)->count(),
                    'cancelled' => collect($group)->where('category', CustomerLead::CATEGORY_DIBATALKAN)->count(),
                ]
            ),
            'totals'  => [
                'total'     => $rows->count(),
                'cold'      => $rows->where('category', CustomerLead::CATEGORY_PROSPEK)->where('status', CustomerLead::STATUS_COLD)->count(),
                'warm'      => $rows->where('category', CustomerLead::CATEGORY_PROSPEK)->where('status', CustomerLead::STATUS_WARM)->count(),
                'hot'       => $rows->where('category', CustomerLead::CATEGORY_PROSPEK)->where('status', CustomerLead::STATUS_HOT)->count(),
                'prospek'   => $rows->where('category', CustomerLead::CATEGORY_PROSPEK)->count(),
                'customer'  => $rows->where('category', CustomerLead::CATEGORY_KONSUMEN)->count(),
                'cancelled' => $rows->where('category', CustomerLead::CATEGORY_DIBATALKAN)->count(),
            ],
            'data'    => $rows,
        ], 200);
    }

    /**
     * Ringkasan penjualan per periode, disimpan ke tabel laporan_penjualan.
     */
    public function summarize(Request $request)
    {
        [$start, $end] = $this->period($request);

        $bookings = Booking::whereBetween('booking_date', [$start, $end]);

        $report = SalesReport::updateOrCreate(
            ['period_start' => $start, 'period_end' => $end],
            [
                'units_sold'    => (int) Booking::whereBetween('booking_date', [$start, $end])
                    ->where('status', Booking::STATUS_LUNAS)->count(),
                'total_revenue' => round((float) Booking::whereBetween('booking_date', [$start, $end])
                    ->whereIn('status', Booking::ACTIVE_STATUSES)->sum('total_price'), 2),
                'total_leads'   => (int) CustomerLead::whereBetween('created_at', [$start, $end . ' 23:59:59'])->count(),
                'total_closing' => round((float) Payment::verified()
                    ->whereBetween('payment_date', [$start, $end])->sum('amount'), 2),
                'generated_by'  => $request->user()?->id,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Ringkasan penjualan tersimpan.', 'data' => $report], 201);
    }

    /**
     * Ekspor laporan ke CSV (dapat dibuka di Excel/Microsoft Office).
     */
    public function exportReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $type = $request->query('type', 'sales');
        $proyek = $this->clusterIdDari($request);

        [$headings, $rows] = match ($type) {
            'settlement' => $this->settlementExport($start, $end, $proyek),
            'performance' => $this->performanceExport($start, $end, $proyek),
            'consumer' => $this->consumerExport($start, $end, $proyek),
            default => $this->salesExport($start, $end, $proyek),
        };

        $filename = sprintf('laporan-%s-%s-%s.csv', $type, $start, $end);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headings);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function salesExport(string $start, string $end, ?int $proyek = null): array
    {
        $headings = ['Kode Booking', 'Tanggal', 'Calon Konsumen', 'Sales', 'Unit', 'Proyek', 'Skema', 'Harga Total', 'Biaya Tambahan Konstruksi', 'Keterangan Tambahan', 'Uang Muka', 'Angsuran', 'Sisa', 'Status', 'Status Serah Terima'];

        $rows = $this->padaProyek(
            Booking::with(['customerLead', 'salesProfile.user', 'unit.cluster', 'paymentScheme']),
            $proyek,
            'unit'
        )
            ->whereBetween('booking_date', [$start, $end])
            ->orderBy('booking_date')
            ->get()
            ->map(fn (Booking $b) => [
                $b->booking_code,
                $b->booking_date?->toDateString(),
                $b->customerLead?->name,
                $b->salesProfile?->user?->name,
                $b->unit?->unit_code,
                $b->unit?->cluster?->name,
                $b->paymentScheme?->scheme_name,
                $b->total_price,
                $b->addition_cost,
                $b->addition_description,
                $b->down_payment_amount,
                $b->installment_amount,
                $b->remaining_amount,
                $b->status,
                $b->delivery_status,
            ])
            ->all();

        return [$headings, $rows];
    }

    private function settlementExport(string $start, string $end, ?int $proyek = null): array
    {
        $headings = ['Tanggal Bayar', 'Kode Booking', 'Calon Konsumen', 'Proyek', 'Unit', 'Jenis', 'Nominal', 'Bank', 'Rekening', 'Atas Nama', 'Verifikasi', 'Verifikator'];

        $rows = $this->padaProyek(
            Payment::with(['booking.customerLead', 'booking.unit.cluster']),
            $proyek
        )
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->whereBetween('payment_date', [$start, $end])
            ->orderBy('payment_date')
            ->get()
            ->map(fn (Payment $p) => [
                $p->payment_date?->toDateString(),
                $p->booking?->booking_code,
                $p->booking?->customerLead?->name,
                $p->booking?->unit?->cluster?->name,
                $p->booking?->unit?->unit_code,
                $p->payment_type,
                $p->amount,
                $p->bank_name,
                $p->account_number,
                $p->account_holder,
                $p->verification_status,
                $p->verifier?->name,
            ])
            ->all();

        return [$headings, $rows];
    }

    private function performanceExport(string $start, string $end, ?int $proyek = null): array
    {
        $headings = ['Kode Sales', 'Nama', 'Proyek', 'Leads', 'Booking', 'Closing (Rp)', 'Unit Terjual', 'Target Unit', 'Capaian Unit', 'Progress Unit (%)'];

        $namaProyek = $proyek
            ? (Cluster::withTrashed()->where('id', $proyek)->value('name') ?? '(proyek arsip)')
            : 'Semua Proyek';

        $rows = SalesProfile::with('user')
            ->withCount([
                'customerLeads' => fn ($q) => $this->leadProyek($q, $proyek),
                'bookings' => fn ($q) => $this->padaProyek($q, $proyek, 'unit'),
            ])
            ->get()
            ->map(function (SalesProfile $profile) use ($start, $end, $proyek, $namaProyek) {
                $target = $profile->targets()
                    ->whereDate('period_start', '<=', $end)
                    ->whereDate('period_end', '>=', $start)
                    ->first();

                return [
                    $profile->sales_code,
                    $profile->user?->name,
                    $namaProyek,
                    $profile->customer_leads_count,
                    $profile->bookings_count,
                    round((float) $this->padaProyek(
                        Booking::where('sales_id', $profile->id),
                        $proyek,
                        'unit'
                    )->sum('total_price'), 2),
                    (int) $this->padaProyek(
                        Booking::where('sales_id', $profile->id)
                            ->where('status', Booking::STATUS_LUNAS)
                            ->whereBetween('booking_date', [$start, $end]),
                        $proyek,
                        'unit'
                    )->count(),
                    $target?->target_units,
                    $target?->achieved_units,
                    $target?->unit_progress,
                ];
            })
            ->all();

        return [$headings, $rows];
    }

    private function consumerExport(string $start, string $end, ?int $proyek = null): array
    {
        $headings = ['Nama', 'Telepon', 'Email', 'Status', 'Kategori', 'Proyek', 'Sales', 'Unit Diminati', 'Anggaran', 'Tanggal Chat Awal', 'Follow Up Terakhir'];

        $namaProyek = Cluster::withTrashed()->pluck('name', 'id');

        $rows = $this->denganKategoriLead(
            $this->leadProyek(
                CustomerLead::with([
                    'salesProfile.user',
                    'unit:id,cluster_id,unit_code',
                    'bookings:id,lead_id,status,unit_id',
                    'bookings.unit:id,cluster_id',
                ]),
                $proyek
            )
                ->whereBetween('created_at', [$start, $end . ' 23:59:59'])
                ->orderBy('name')
                ->get()
        )
            ->map(function (CustomerLead $l) use ($namaProyek) {
                $clusterId = $this->proyekDariLead($l);

                return [
                    $l->name,
                    $l->phone,
                    $l->email,
                    $l->status,
                    $l->category(),
                    $clusterId ? ($namaProyek[$clusterId] ?? '(proyek arsip)') : '(belum ada proyek)',
                    $l->salesProfile?->user?->name,
                    $l->unit?->unit_code,
                    $l->budget,
                    $l->first_chat_date?->toDateString(),
                    $l->last_follow_up?->toDateString(),
                ];
            })
            ->all();

        return [$headings, $rows];
    }

    /**
     * Laporan kegiatan penjualan milik sales yang login (Wireframe 2.46).
     */
    public function mySalesReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $profile = $this->currentSalesProfile($request);

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini belum memiliki profil sales.',
            ], 403);
        }

        $rows = $this->mySalesRows($profile, $start, $end);

        return response()->json([
            'success' => true,
            'period'  => ['start_date' => $start, 'end_date' => $end],
            'sales'   => [
                'id'         => $profile->id,
                'sales_code' => $profile->sales_code,
                'name'       => $profile->user?->name,
            ],
            'summary' => [
                'total_customers' => $rows->where('status_label', '!=', 'Calon Pembeli')->count(),
                'total_prospects' => $rows->where('status_label', 'Calon Pembeli')->count(),
                'total_bookings'  => Booking::where('sales_id', $profile->id)
                    ->whereBetween('booking_date', [$start, $end])->count(),
                'units_sold'      => Booking::where('sales_id', $profile->id)
                    ->where('status', Booking::STATUS_LUNAS)
                    ->whereBetween('booking_date', [$start, $end])->count(),
                'total_revenue'   => round((float) Payment::where('verification_status', Payment::VERIFY_VERIFIED)
                    ->whereBetween('payment_date', [$start, $end])
                    ->whereHas('booking', fn ($q) => $q->where('sales_id', $profile->id))->sum('amount'), 2),
            ],
            'data'    => $rows->values(),
        ], 200);
    }

    /**
     * Ekspor CSV laporan penjualan milik sales yang login.
     */
    public function exportMySalesReport(Request $request)
    {
        [$start, $end] = $this->period($request);
        $profile = $this->currentSalesProfile($request);

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Akun ini belum memiliki profil sales.'], 403);
        }

        $headings = ['Tanggal', 'Pelanggan', 'Unit', 'Kode Booking', 'Pembayaran (Rp)', 'Sisa (Rp)', 'Status'];

        $rows = $this->mySalesRows($profile, $start, $end)
            ->map(fn (array $r) => [
                $r['date'],
                $r['customer'],
                $r['unit'],
                $r['booking_code'],
                $r['paid_amount'],
                $r['remaining_amount'],
                $r['status_label'],
            ])
            ->all();

        $filename = sprintf('laporan-sales-saya-%s-%s.csv', $start, $end);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headings);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Baris gabungan booking dan calon konsumen yang ditangani satu sales.
     */
    private function mySalesRows(SalesProfile $profile, string $start, string $end)
    {
        $bookings = Booking::with(['customerLead', 'unit', 'paymentScheme', 'payments'])
            ->where('sales_id', $profile->id)
            ->whereBetween('booking_date', [$start, $end])
            ->get()
            ->map(function (Booking $b) {
                $paid = (float) $b->payments
                    ->where('verification_status', Payment::VERIFY_VERIFIED)
                    ->sum('amount');

                return [
                    'date'             => $b->booking_date?->toDateString(),
                    'customer'         => $b->customerLead?->name,
                    'unit'             => $b->unit?->unit_code,
                    'booking_code'     => $b->booking_code,
                    'scheme'           => $b->paymentScheme?->scheme_name,
                    'paid_amount'      => round($paid, 2),
                    'remaining_amount' => round((float) $b->remaining_amount, 2),
                    'status_label'     => match ($b->status) {
                        Booking::STATUS_LUNAS => 'Terjual',
                        Booking::STATUS_DIBATALKAN => 'Dibatalkan',
                        default => 'Pemesanan',
                    },
                    'status'           => $b->status,
                ];
            });

        $prospects = CustomerLead::with('unit')
            ->where('sales_id', $profile->id)
            ->prospects()
            ->whereBetween('created_at', [$start, $end . ' 23:59:59'])
            ->get()
            ->map(fn (CustomerLead $l) => [
                'date'             => ($l->first_chat_date ?? $l->last_follow_up ?? $l->created_at)?->toDateString(),
                'customer'         => $l->name,
                'unit'             => $l->unit?->unit_code ?? $l->interested_unit,
                'booking_code'     => '-',
                'scheme'           => '-',
                'paid_amount'      => 0,
                'remaining_amount' => 0,
                'status_label'     => 'Calon Pembeli',
                'status'           => $l->status,
            ]);

        return $bookings->concat($prospects)->sortByDesc('date')->values();
    }

    private function currentSalesProfile(Request $request): ?SalesProfile
    {
        return $request->user()?->salesProfile;
    }

    private function period(Request $request): array
    {
        return [
            $request->input('start_date', now()->startOfMonth()->toDateString()),
            $request->input('end_date', now()->endOfMonth()->toDateString()),
        ];
    }
}
