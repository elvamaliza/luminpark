@extends('layouts.app')

@section('title', 'Laporan Sales')

@section('page_title', 'Laporan Sales')

@section('page_desc', 'Laporan kegiatan penjualan untuk konsumen yang Anda tangani, per periode terpilih.')

@section('page_actions')
<div class="lp-no-print flex flex-wrap items-center gap-2">
    <button id="ls-export" type="button" class="rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ekspor Excel (CSV)</button>
    <button id="ls-print" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Ekspor PDF / Cetak</button>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <section class="lp-no-print rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <form id="ls-filter" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Dari Tanggal</span>
                <input id="ls-start" name="start_date" type="date" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Sampai Tanggal</span>
                <input id="ls-end" name="end_date" type="date" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm">
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
            </div>
            <p class="pb-2 text-xs text-zinc-500">Laporan hanya menampilkan data milik sales yang sedang login.</p>
        </form>
    </section>

    <div class="rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm">
        <p id="ls-meta" class="text-sm text-zinc-600">Memuat laporan...</p>
    </div>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Ringkasan</h2>
        <div id="ls-summary"></div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Rincian Laporan</h2>
        <div id="ls-table"></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
  function buildQuery() {
    return new URLSearchParams({
      start_date: document.getElementById('ls-start').value,
      end_date: document.getElementById('ls-end').value,
    });
  }

  async function loadReport() {
    LP.loading('ls-summary', 'Memuat ringkasan...');
    LP.loading('ls-table', 'Memuat rincian laporan...');
    document.getElementById('ls-meta').textContent = 'Memuat laporan penjualan Anda...';

    try {
      const { json } = await LP.http.get('/reports/my-sales?' + buildQuery().toString());
      if (!json) throw new Error('Respons laporan tidak dikenali.');

      const s = json.summary || {};
      const p = json.period || {};
      const sales = json.sales || {};

      document.getElementById('ls-meta').innerHTML = '<span class="font-semibold text-zinc-900">'
        + LP.esc(sales.name || 'Sales') + '</span>'
        + (sales.sales_code ? '<span class="text-zinc-500"> · ' + LP.esc(sales.sales_code) + '</span>' : '')
        + '<span class="block mt-1 text-xs text-zinc-500">Periode ' + LP.date(p.start_date) + ' sampai ' + LP.date(p.end_date)
        + ' · ' + LP.number((json.data || []).length) + ' baris rincian</span>';

      document.getElementById('ls-summary').innerHTML = '<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">'
        + [
          { label: 'Jumlah Pelanggan', value: LP.number(s.total_customers), tone: 'emerald' },
          { label: 'Calon Pembeli', value: LP.number(s.total_prospects), tone: 'sky' },
          { label: 'Pemesanan (Booking)', value: LP.number(s.total_bookings), tone: 'amber' },
          { label: 'Unit Terjual', value: LP.number(s.units_sold) },
          { label: 'Total Pendapatan', value: LP.shortMoney(s.total_revenue), hint: 'Pembayaran terverifikasi', tone: 'emerald' },
        ].map((item) => LP.statCard(item)).join('') + '</div>';

      LP.table({
        mount: 'ls-table',
        rows: json.data || [],
        empty: 'Belum ada aktivitas penjualan pada periode ini.',
        columns: [
          { label: 'No', align: 'right', render: (row, index) => LP.number(index + 1) },
          { label: 'Tanggal', render: (row) => LP.date(row.date) },
          { label: 'Pelanggan', render: (row) => LP.esc(row.customer || '-') },
          { label: 'Unit', render: (row) => LP.esc(row.unit || '-') },
          { label: 'Kode Booking', render: (row) => LP.esc(row.booking_code || '-') },
          { label: 'Skema', render: (row) => LP.esc(row.scheme || '-') },
          { label: 'Pembayaran', align: 'right', render: (row) => LP.money(row.paid_amount) },
          { label: 'Sisa', align: 'right', render: (row) => LP.money(row.remaining_amount) },
          { label: 'Status', render: (row) => LP.badge(row.status_label) },
        ],
      });
    } catch (error) {
      LP.errorBox('ls-summary', error.message || 'Laporan gagal dimuat.');
      LP.errorBox('ls-table', 'Rincian laporan tidak dapat dimuat.');
      document.getElementById('ls-meta').textContent = 'Laporan gagal dimuat.';
      LP.toast(error.message || 'Laporan gagal dimuat.', 'error');
    }
  }

  document.getElementById('ls-filter').addEventListener('submit', (event) => {
    event.preventDefault();
    loadReport();
  });

  document.getElementById('ls-export').addEventListener('click', async (event) => {
    await LP.withBusy(event.currentTarget, async () => {
      try {
        await LP.download('/reports/my-sales/export?' + buildQuery().toString(), 'laporan-sales.csv');
        LP.toast('Berkas CSV laporan sales sedang diunduh.');
      } catch (error) {
        LP.toast(error.message || 'Berkas laporan gagal diunduh.', 'error');
      }
    });
  });

  document.getElementById('ls-print').addEventListener('click', async (event) => {
    const tombol = event.currentTarget;
    tombol.disabled = true;
    tombol.textContent = 'Menyiapkan...';
    try {
      await LP.cetakLaporan({
        judul: 'Laporan Penjualan Sales',
        periode: LP.date(document.getElementById('ls-start').value) + ' s/d ' + LP.date(document.getElementById('ls-end').value),
        catatan: 'Laporan ini hanya memuat konsumen yang berada di bawah penanganan sales yang mencetak.',
      });
    } catch (error) {
      LP.toast(error.message || 'Lembar cetak gagal disiapkan.', 'error');
    } finally {
      tombol.disabled = false;
      tombol.textContent = 'Ekspor PDF / Cetak';
    }
  });

  document.getElementById('ls-start').value = LP.isoDate(Date.now() - 29 * 86400000);
  document.getElementById('ls-end').value = LP.today();

  LP.guard(['sales'], loadReport);
</script>
@endpush
