@extends('layouts.app')

@section('title', 'Laporan')

@section('page_title', 'Laporan')

@section('page_desc', 'Laporan penjualan, pelunasan, kinerja sales, dan konsumen untuk periode terpilih — dirinci per proyek.')

@section('page_actions')
<div class="lp-no-print flex flex-wrap items-center gap-2">
    <button id="lap-export" type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">Ekspor Excel (CSV)</button>
    <button id="lap-print" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all shadow-sm">Ekspor PDF / Cetak</button>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <section class="lp-no-print rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form id="lap-filter" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Jenis laporan">
                <button type="button" role="tab" data-type="sales" class="rounded-xl border border-[#1b3225] bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white transition-all shadow-sm">Penjualan</button>
                <button type="button" role="tab" data-type="settlement" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all">Pelunasan</button>
                <button type="button" role="tab" data-type="performance" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all">Kinerja Sales</button>
                <button type="button" role="tab" data-type="consumers" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all">Konsumen</button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Dari Tanggal</span>
                    <input id="lap-start" name="start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Sampai Tanggal</span>
                    <input id="lap-end" name="end_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Proyek</span>
                    <select id="lap-proyek" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                        <option value="">Semua Proyek</option>
                    </select>
                </label>
                <label id="lap-status-wrap" class="hidden">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status Konsumen</span>
                    <select id="lap-status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                        <option value="">Semua status</option>
                        <option value="cold">Cold</option>
                        <option value="warm">Warm</option>
                        <option value="hot">Hot</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">Tampilkan Laporan</button>
                </div>
            </div>
        </form>
    </section>

    <p id="lap-period" class="text-sm text-slate-600">Memuat laporan...</p>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Ringkasan per Proyek</h2>
        <div id="lap-summary"></div>
    </section>

    <section id="lap-recap-wrap" class="hidden">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Rekapitulasi Pelunasan</h2>
        <div id="lap-recap" class="grid gap-4 lg:grid-cols-3"></div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Detail Laporan</h2>
        <div id="lap-table"></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
  const TYPES = {
    sales: {
      name: 'Penjualan',
      path: '/reports/sales',
      exportType: 'sales',
      columns: [
        { label: 'Kode Booking', render: (r) => LP.esc(r.booking_code) },
        { label: 'Tanggal', render: (r) => LP.date(r.booking_date) },
        { label: 'Konsumen', render: (r) => LP.esc(r.customer_lead ? r.customer_lead.name : '-') },
        { label: 'Unit', render: (r) => LP.esc(r.unit ? r.unit.unit_code : '-') },
        { label: 'Tipe Unit', render: (r) => LP.esc(r.unit ? r.unit.unit_type : '-') },
        { label: 'Proyek', render: (r) => LP.esc(r.project || '-') },
        { label: 'Sales', render: (r) => LP.esc(r.sales_profile && r.sales_profile.user ? r.sales_profile.user.name : '-') },
        { label: 'Skema', render: (r) => LP.esc(r.payment_scheme ? r.payment_scheme.scheme_name : '-') },
        { label: 'Harga Total', align: 'right', render: (r) => LP.money(r.total_price) },
        { label: 'Uang Muka', align: 'right', render: (r) => LP.money(r.down_payment_amount) },
        { label: 'Angsuran', align: 'right', render: (r) => LP.money(r.installment_amount) },
        { label: 'Sisa', align: 'right', render: (r) => LP.money(r.remaining_amount) },
        { label: 'Status', render: (r) => LP.badge(r.status) },
        { label: 'Serah Terima', render: (r) => LP.badge(r.delivery_status) },
      ],
      proyekColumns: [
        { label: 'Proyek', render: (r) => '<span class="font-semibold">' + LP.esc(r.name) + '</span>' },
        { label: 'Transaksi', align: 'right', render: (r) => LP.number(r.transactions) },
        { label: 'Dibatalkan', align: 'right', render: (r) => LP.number(r.cancelled) },
        { label: 'Nilai Penjualan', align: 'right', render: (r) => LP.money(r.sales_value) },
        { label: 'Sisa Pembayaran', align: 'right', render: (r) => LP.money(r.remaining_value) },
      ],
    },
    settlement: {
      name: 'Pelunasan',
      path: '/reports/settlement',
      exportType: 'settlement',
      columns: [
        { label: 'Kode Booking', render: (r) => LP.esc(r.booking ? r.booking.booking_code : '-') },
        { label: 'Konsumen', render: (r) => LP.esc(r.booking && r.booking.customer_lead ? r.booking.customer_lead.name : '-') },
        { label: 'Unit', render: (r) => LP.esc(r.booking && r.booking.unit ? r.booking.unit.unit_code : '-') },
        { label: 'Proyek', render: (r) => LP.esc(r.project || '-') },
        { label: 'Jenis Pembayaran', render: (r) => LP.esc(r.payment_type) },
        { label: 'Jumlah', align: 'right', render: (r) => LP.money(r.amount) },
        { label: 'Tanggal', render: (r) => LP.date(r.payment_date) },
        { label: 'Bank', render: (r) => LP.esc(r.bank_name || '-') },
        { label: 'Verifikasi', render: (r) => LP.badge(r.verification_status) },
        { label: 'Diverifikasi Oleh', render: (r) => LP.esc(r.verifier ? r.verifier.name : '-') },
        { label: 'Waktu Verifikasi', render: (r) => LP.datetime(r.verified_at) },
      ],
      proyekColumns: [
        { label: 'Proyek', render: (r) => '<span class="font-semibold">' + LP.esc(r.name) + '</span>' },
        { label: 'Transaksi', align: 'right', render: (r) => LP.number(r.transactions) },
        { label: 'Dana Masuk', align: 'right', render: (r) => LP.money(r.total_amount) },
      ],
    },
    performance: {
      name: 'Kinerja Sales',
      path: '/reports/performance',
      exportType: 'performance',
      columns: [
        { label: 'Sales', render: (r) => LP.esc(r.name) + '<span class="block text-xs text-slate-500">' + LP.esc(r.sales_code) + '</span>' },
        { label: 'Status', render: (r) => LP.badge(r.status) },
        { label: 'Lead', align: 'right', render: (r) => LP.number(r.total_leads) },
        { label: 'Booking', align: 'right', render: (r) => LP.number(r.total_bookings) },
        { label: 'Nilai Closing', align: 'right', render: (r) => LP.money(r.total_closing) },
        { label: 'Unit Terjual', align: 'right', render: (r) => LP.number(r.units_sold) },
        { label: 'Pendapatan Lunas', align: 'right', render: (r) => LP.money(r.revenue_sold) },
        { label: 'Target Unit', render: (r) => progressBar(LP.number(r.achieved_units) + ' / ' + LP.number(r.target_units), r.unit_progress) },
        { label: 'Target Nilai', render: (r) => progressBar(LP.shortMoney(r.achieved_amount) + ' / ' + LP.shortMoney(r.target_amount), r.amount_progress) },
      ],
      proyekColumns: [
        { label: 'Proyek', render: (r) => '<span class="font-semibold">' + LP.esc(r.name) + '</span>' },
        { label: 'Booking Periode', align: 'right', render: (r) => LP.number(r.transactions) },
        { label: 'Unit Terjual', align: 'right', render: (r) => LP.number(r.units_sold) },
        { label: 'Pendapatan Lunas', align: 'right', render: (r) => LP.money(r.revenue_sold) },
        { label: 'Sales Bekerja', align: 'right', render: (r) => LP.number(r.sales_count) },
      ],
    },
    consumers: {
      name: 'Konsumen',
      path: '/reports/consumers',
      exportType: 'consumer',
      columns: [
        { label: 'Nama Konsumen', render: (r) => LP.esc(r.name) },
        { label: 'Telepon', render: (r) => LP.esc(r.phone || '-') },
        { label: 'Email', render: (r) => LP.esc(r.email || '-') },
        { label: 'Status', render: (r) => LP.badge(r.status) },
        { label: 'Kategori', render: (r) => LP.badge(r.category || 'prospek') },
        { label: 'Sumber', render: (r) => LP.esc(r.source || '-') },
        { label: 'Sales', render: (r) => LP.esc(r.sales_profile && r.sales_profile.user ? r.sales_profile.user.name : '-') },
        { label: 'Unit', render: (r) => LP.esc(r.unit ? r.unit.unit_code : (r.interested_unit || '-')) },
        { label: 'Proyek', render: (r) => LP.esc(r.project || '-') },
        { label: 'Budget', align: 'right', render: (r) => LP.money(r.budget) },
        { label: 'Follow Up Terakhir', render: (r) => LP.date(r.last_follow_up) },
        { label: 'Dibuat', render: (r) => LP.date(r.created_at) },
      ],
      proyekColumns: [
        { label: 'Proyek', render: (r) => '<span class="font-semibold">' + LP.esc(r.name) + '</span>' },
        { label: 'Total', align: 'right', render: (r) => LP.number(r.total) },
        { label: 'Cold', align: 'right', render: (r) => LP.number(r.cold) },
        { label: 'Warm', align: 'right', render: (r) => LP.number(r.warm) },
        { label: 'Hot', align: 'right', render: (r) => LP.number(r.hot) },
        { label: 'Prospek', align: 'right', render: (r) => LP.number(r.prospek) },
        { label: 'Konsumen', align: 'right', render: (r) => LP.number(r.customer) },
        { label: 'Dibatalkan', align: 'right', render: (r) => LP.number(r.cancelled) },
      ],
    },
  };

  let currentType = 'sales';

  function progressBar(text, percent) {
    const pct = Math.max(0, Math.min(100, Number(percent || 0)));
    return '<div class="min-w-[140px]">'
      + '<span class="text-xs font-medium text-slate-700">' + LP.esc(text) + ' (' + LP.number(pct) + '%)</span>'
      + '<span class="mt-1 block h-1.5 w-full rounded-full bg-slate-200">'
      + '<span class="block h-1.5 rounded-full bg-[#5c7361]" style="width:' + pct + '%"></span></span></div>';
  }

  function recapTable(title, map) {
    const rows = Object.entries(map || {}).map(([name, value]) => ({ name: name, transactions: value.transactions, total_amount: value.total_amount }));
    return '<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">'
      + '<p class="border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">' + LP.esc(title) + '</p>'
      + '<div class="p-1" id="recap-' + LP.esc(title.toLowerCase().replace(/[^a-z0-9]+/g, '-')) + '"></div></div>';
  }

  // Revisi 32: ringkasan laporan disajikan per proyek, bukan jumlah gabungan.
  function paintProyek(json) {
    const pilihan = Array.isArray(json.projects) ? json.projects : [];
    const aktif = json.project_filter ? String(json.project_filter) : '';
    const select = document.getElementById('lap-proyek');
    const sebelumnya = select.value;
    const dipakai = aktif || sebelumnya;

    select.innerHTML = '<option value="">Semua Proyek</option>'
      + pilihan.map((item) => '<option value="' + LP.esc(item.id) + '"'
        + (String(item.id) === dipakai ? ' selected' : '') + '>' + LP.esc(item.name) + '</option>').join('');

    if (pilihan.some((item) => String(item.id) === dipakai)) select.value = dipakai;
  }

  function renderSummary(conf, json) {
    LP.table({
      mount: 'lap-summary',
      rows: Array.isArray(json.per_project) ? json.per_project : [],
      columns: conf.proyekColumns,
      empty: 'Belum ada proyek dengan data pada periode ini.',
    });
  }

  function buildQuery() {
    const params = new URLSearchParams({
      start_date: document.getElementById('lap-start').value,
      end_date: document.getElementById('lap-end').value,
    });
    const proyek = document.getElementById('lap-proyek').value;
    if (proyek) params.set('cluster_id', proyek);
    const status = document.getElementById('lap-status').value;
    if (currentType === 'consumers' && status) {
      // Revisi 5: pembatalan adalah kategori, bukan status cold/warm/hot.
      params.set(status === 'dibatalkan' ? 'category' : 'status', status);
    }
    return params;
  }

  function setTypeButtons() {
    document.querySelectorAll('[data-type]').forEach((button) => {
      const active = button.dataset.type === currentType;
      button.className = 'rounded-xl border px-4 py-2 text-sm transition-all '
        + (active ? 'border-[#1b3225] bg-[#1b3225] font-semibold text-white shadow-sm' : 'border-slate-200 bg-white font-medium text-slate-700 hover:bg-slate-50');
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  async function loadReport() {
    const conf = TYPES[currentType];
    document.getElementById('lap-status-wrap').className = currentType === 'consumers' ? 'block' : 'hidden';
    setTypeButtons();

    LP.loading('lap-summary', 'Memuat rincian per proyek...');
    LP.loading('lap-table', 'Memuat detail laporan...');
    document.getElementById('lap-period').textContent = 'Memuat laporan ' + conf.name.toLowerCase() + '...';
    document.getElementById('lap-recap-wrap').classList.add('hidden');
    document.getElementById('lap-recap').innerHTML = '';

    try {
      const { json } = await LP.http.get(conf.path + '?' + buildQuery().toString());
      if (!json) throw new Error('Respons laporan tidak dikenali.');

      const period = json.period || {};
      const namaProyek = (document.getElementById('lap-proyek').selectedOptions[0] || {}).textContent || 'Semua Proyek';
      document.getElementById('lap-period').textContent = 'Laporan ' + conf.name + ' periode '
        + LP.date(period.start_date) + ' sampai ' + LP.date(period.end_date)
        + ' pada proyek ' + namaProyek
        + ' — ' + LP.number((json.data || []).length) + ' baris data.';

      paintProyek(json);
      renderSummary(conf, json);

      LP.table({ mount: 'lap-table', rows: json.data, columns: conf.columns, empty: 'Tidak ada data transaksi pada periode ini.' });

      if (currentType === 'settlement' && json.recap) {
        document.getElementById('lap-recap-wrap').classList.remove('hidden');
        const groups = [
          ['Per Konsumen', json.recap.by_consumer],
          ['Per Unit', json.recap.by_unit],
          ['Per Skema Pembayaran', json.recap.by_scheme],
        ];
        document.getElementById('lap-recap').innerHTML = groups.map((group) => recapTable(group[0], group[1])).join('');
        groups.forEach((group) => {
          LP.table({
            mount: 'recap-' + group[0].toLowerCase().replace(/[^a-z0-9]+/g, '-'),
            rows: Object.entries(group[1] || {}).map(([name, value]) => ({ name, value })),
            empty: 'Belum ada data.',
            columns: [
              { label: 'Nama', render: (r) => LP.esc(r.name) },
              { label: 'Transaksi', align: 'right', render: (r) => LP.number(r.value.transactions) },
              { label: 'Total', align: 'right', render: (r) => LP.money(r.value.total_amount) },
            ],
          });
        });
      }
    } catch (error) {
      LP.errorBox('lap-summary', error.message || 'Laporan gagal dimuat.');
      LP.errorBox('lap-table', 'Detail laporan tidak dapat dimuat.');
      document.getElementById('lap-period').textContent = 'Laporan gagal dimuat.';
      LP.toast(error.message || 'Laporan gagal dimuat.', 'error');
    }
  }

  document.querySelectorAll('[data-type]').forEach((button) => {
    button.addEventListener('click', () => {
      currentType = button.dataset.type;
      loadReport();
    });
  });

  document.getElementById('lap-filter').addEventListener('submit', (event) => {
    event.preventDefault();
    loadReport();
  });

  document.getElementById('lap-proyek').addEventListener('change', loadReport);

  document.getElementById('lap-export').addEventListener('click', async (event) => {
    const conf = TYPES[currentType];
    await LP.withBusy(event.currentTarget, async () => {
      try {
        await LP.download('/reports/export?type=' + conf.exportType + '&' + buildQuery().toString(), 'laporan-' + currentType + '.csv');
        LP.toast('Berkas CSV laporan ' + conf.name + ' sedang diunduh.');
      } catch (error) {
        LP.toast(error.message || 'Berkas laporan gagal diunduh.', 'error');
      }
    });
  });

  document.getElementById('lap-print').addEventListener('click', async (event) => {
    const tombol = event.currentTarget;
    tombol.disabled = true;
    tombol.textContent = 'Menyiapkan...';
    try {
      const catatanCetak = ['Proyek: ' + (document.getElementById('lap-proyek').selectedOptions[0]?.textContent || 'Semua Proyek')];
      if (!document.getElementById('lap-status-wrap').classList.contains('hidden')) {
        catatanCetak.push('Penapis status konsumen: ' + (document.getElementById('lap-status').selectedOptions[0]?.textContent || ''));
      }

      await LP.cetakLaporan({
        judul: 'Laporan ' + TYPES[currentType].name,
        periode: LP.date(document.getElementById('lap-start').value) + ' s/d ' + LP.date(document.getElementById('lap-end').value),
        catatan: catatanCetak.join('  ·  '),
      });
    } catch (error) {
      LP.toast(error.message || 'Lembar cetak gagal disiapkan.', 'error');
    } finally {
      tombol.disabled = false;
      tombol.textContent = 'Ekspor PDF / Cetak';
    }
  });

  document.getElementById('lap-start').value = LP.isoDate(Date.now() - 2592000000);
  document.getElementById('lap-end').value = LP.today();

  LP.guard(['super_admin', 'admin_sales'], loadReport);
</script>
@endpush