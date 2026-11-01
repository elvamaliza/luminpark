<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CustomerLead;
use App\Models\Payment;
use App\Models\SalesProfile;
use App\Models\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Laporan penjualan unit.
     */
    public function salesReport(Request $request)
    {
        [$start, $end] = $this->period($request);

        $rows = Booking::with(['customerLead', 'salesProfile.user', 'unit', 'paymentScheme'])
            ->whereBetween('booking_date', [$start, $end])
            ->orderBy('booking_date')
            ->get();

        return response()->json([
            'success'               => true,
            'period'                => ['start_date' => $start, 'end_date' => $end],
            'total_transactions'    => $rows->count(),
            'total_sales_value'     => round((float) $rows->sum('total_price'), 2),
            'total_remaining_value' => round((float) $rows->sum('remaining_amount'), 2),
            'data'                  => $rows,
        ], 200);
    }

    /**
     * Laporan pelunasan (pembayaran terverifikasi).
     */
    public function settlementReport(Request $request)
    {
        [$start, $end] = $this->period($request);

        $rows = Payment::with(['booking.customerLead', 'booking.unit', 'verifier'])
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->whereBetween('payment_date', [$start, $end])
            ->orderBy('payment_date')
            ->get();

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
     */
    public function salesPerformanceReport(Request $request)
    {
        [$start, $end] = $this->period($request);

        $rows = SalesProfile::with('user')
            ->withCount(['customerLeads', 'bookings'])
            ->withSum('bookings', 'total_price')
            ->with(['targets' => fn ($q) => $q->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start)])
            ->get()
            ->map(function (SalesProfile $profile) use ($start, $end) {
                $closed = Booking::where('sales_id', $profile->id)
                    ->where('status', Booking::STATUS_LUNAS)
                    ->whereBetween('booking_date', [$start, $end]);

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

        return response()->json([
            'success' => true,
            'period'  => ['start_date' => $start, 'end_date' => $end],
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

        $query = CustomerLead::with([
            'salesProfile.user',
            'unit',
            'followUpHistories' => fn ($q) => $q->latest('follow_up_date')->limit(1),
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->whereBetween('created_at', [$start, $end . ' 23:59:59'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'period'  => ['start_date' => $start, 'end_date' => $end],
            'totals'  => [
                'total'    => $rows->count(),
                'cold'     => $rows->where('status', CustomerLead::STATUS_COLD)->count(),
                'warm'     => $rows->where('status', CustomerLead::STATUS_WARM)->count(),
                'hot'      => $rows->where('status', CustomerLead::STATUS_HOT)->count(),
                'customer' => $rows->where('status', CustomerLead::STATUS_BOOKING)->count(),
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
                'total_revenue' => round((float) Booking::whereBetween('booking_date', [$start, $end])->sum('total_price'), 2),
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

        [$headings, $rows] = match ($type) {
            'settlement' => $this->settlementExport($start, $end),
            'performance' => $this->performanceExport($start, $end),
            'consumer' => $this->consumerExport($start, $end),
            default => $this->salesExport($start, $end),
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

    private function salesExport(string $start, string $end): array
    {
        $headings = ['Kode Booking', 'Tanggal', 'Calon Konsumen', 'Sales', 'Unit', 'Cluster', 'Skema', 'Harga Total', 'Uang Muka', 'Angsuran', 'Sisa', 'Status', 'Status Serah Terima'];

        $rows = Booking::with(['customerLead', 'salesProfile.user', 'unit.cluster', 'paymentScheme'])
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
                $b->down_payment_amount,
                $b->installment_amount,
                $b->remaining_amount,
                $b->status,
                $b->delivery_status,
            ])
            ->all();

        return [$headings, $rows];
    }

    private function settlementExport(string $start, string $end): array
    {
        $headings = ['Tanggal Bayar', 'Kode Booking', 'Calon Konsumen', 'Jenis', 'Nominal', 'Bank', 'Rekening', 'Atas Nama', 'Verifikasi', 'Verifikator'];

        $rows = Payment::with(['booking.customerLead', 'verifier'])
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->whereBetween('payment_date', [$start, $end])
            ->orderBy('payment_date')
            ->get()
            ->map(fn (Payment $p) => [
                $p->payment_date?->toDateString(),
                $p->booking?->booking_code,
                $p->booking?->customerLead?->name,
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

    private function performanceExport(string $start, string $end): array
    {
        $headings = ['Kode Sales', 'Nama', 'Leads', 'Booking', 'Closing (Rp)', 'Unit Terjual', 'Target Unit', 'Capaian Unit', 'Progress Unit (%)'];

        $rows = SalesProfile::with('user')->withCount(['customerLeads', 'bookings'])->get()
            ->map(function (SalesProfile $profile) use ($start, $end) {
                $target = $profile->targets()
                    ->whereDate('period_start', '<=', $end)
                    ->whereDate('period_end', '>=', $start)
                    ->first();

                return [
                    $profile->sales_code,
                    $profile->user?->name,
                    $profile->customer_leads_count,
                    $profile->bookings_count,
                    round((float) Booking::where('sales_id', $profile->id)->sum('total_price'), 2),
                    (int) Booking::where('sales_id', $profile->id)
                        ->where('status', Booking::STATUS_LUNAS)
                        ->whereBetween('booking_date', [$start, $end])->count(),
                    $target?->target_units,
                    $target?->achieved_units,
                    $target?->unit_progress,
                ];
            })
            ->all();

        return [$headings, $rows];
    }

    private function consumerExport(string $start, string $end): array
    {
        $headings = ['Nama', 'Telepon', 'Email', 'Status', 'Sales', 'Unit Diminati', 'Anggaran', 'Follow Up Terakhir'];

        $rows = CustomerLead::with(['salesProfile.user', 'unit'])
            ->whereBetween('created_at', [$start, $end . ' 23:59:59'])
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerLead $l) => [
                $l->name,
                $l->phone,
                $l->email,
                $l->status,
                $l->salesProfile?->user?->name,
                $l->unit?->unit_code,
                $l->budget,
                $l->last_follow_up?->toDateString(),
            ])
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
                    'status_label'     => $b->status === Booking::STATUS_LUNAS ? 'Terjual' : 'Pemesanan',
                    'status'           => $b->status,
                ];
            });

        $prospects = CustomerLead::with('unit')
            ->where('sales_id', $profile->id)
            ->where('status', '!=', CustomerLead::STATUS_BOOKING)
            ->whereBetween('created_at', [$start, $end . ' 23:59:59'])
            ->get()
            ->map(fn (CustomerLead $l) => [
                'date'             => ($l->last_follow_up ?? $l->created_at)?->toDateString(),
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
