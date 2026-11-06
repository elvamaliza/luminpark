@extends('layouts.app')

@section('title', 'Laporan')

@section('page_title', 'Laporan')

@section('page_desc', 'Laporan penjualan, pelunasan, kinerja sales, dan konsumen untuk periode terpilih.')

@section('page_actions')
<div class="lp-no-print flex flex-wrap items-center gap-2">
    <button id="lap-export" type="button" class="rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Ekspor Excel (CSV)</button>
    <button id="lap-print" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Cetak</button>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <section class="lp-no-print rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <form id="lap-filter" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Jenis laporan">
                <button type="button" role="tab" data-type="sales" class="rounded-xl border border-zinc-900 bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">Penjualan</button>
                <button type="button" role="tab" data-type="settlement" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700">Pelunasan</button>
                <button type="button" role="tab" data-type="performance" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700">Kinerja Sales</button>
                <button type="button" role="tab" data-type="consumers" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700">Konsumen</button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Dari Tanggal</span>
                    <input id="lap-start" name="start_date" type="date" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Sampai Tanggal</span>
                    <input id="lap-end" name="end_date" type="date" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm">
                </label>
                <label id="lap-status-wrap" class="hidden">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status Konsumen</span>
                    <select id="lap-status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm">
                        <option value="">Semua status</option>
                        <option value="cold">Cold</option>
                        <option value="warm">Warm</option>
                        <option value="hot">Hot</option>
                        <option value="booking">Booking</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">Tampilkan Laporan</button>
                </div>
            </div>
        </form>
    </section>

    <p id="lap-period" class="text-sm text-zinc-600">Memuat laporan...</p>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Ringkasan Laporan</h2>
        <div id="lap-summary"></div>
    </section>

    <section id="lap-recap-wrap" class="hidden">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Rekapitulasi Pelunasan</h2>
        <div id="lap-recap" class="grid gap-4 lg:grid-cols-3"></div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Detail Laporan</h2>
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
        { label: 'Sales', render: (r) => LP.esc(r.sales_profile && r.sales_profile.user ? r.sales_profile.user.name : '-') },
        { label: 'Skema', render: (r) => LP.esc(r.payment_scheme ? r.payment_scheme.scheme_name : '-') },
        { label: 'Harga Total', align: 'right', render: (r) => LP.money(r.total_price) },
        { label: 'Uang Muka', align: 'right', render: (r) => LP.money(r.down_payment_amount) },
        { label: 'Angsuran', align: 'right', render: (r) => LP.money(r.installment_amount) },
        { label: 'Sisa', align: 'right', render: (r) => LP.money(r.remaining_amount) },
        { label: 'Status', render: (r) => LP.badge(r.status) },
        { label: 'Serah Terima', render: (r) => LP.badge(r.delivery_status) },
      ],
      summary: (payload) => [
        { label: 'Total Transaksi', value: LP.number(payload.total_transactions), tone: 'zinc' },
        { label: 'Nilai Penjualan', value: LP.shortMoney(payload.total_sales_value), tone: 'emerald' },
        { label: 'Total Sisa Pembayaran', value: LP.shortMoney(payload.total_remaining_value), tone: 'amber' },
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
        { label: 'Jenis Pembayaran', render: (r) => LP.esc(r.payment_type) },
        { label: 'Jumlah', align: 'right', render: (r) => LP.money(r.amount) },
        { label: 'Tanggal', render: (r) => LP.date(r.payment_date) },
        { label: 'Bank', render: (r) => LP.esc(r.bank_name || '-') },
        { label: 'Verifikasi', render: (r) => LP.badge(r.verification_status) },
        { label: 'Diverifikasi Oleh', render: (r) => LP.esc(r.verifier ? r.verifier.name : '-') },
        { label: 'Waktu Verifikasi', render: (r) => LP.datetime(r.verified_at) },
      ],
      summary: (payload) => [
        { label: 'Total Transaksi', value: LP.number(payload.total_transactions), tone: 'zinc' },
        { label: 'Total Dana Masuk', value: LP.shortMoney(payload.total_settled_amount), tone: 'emerald' },
      ],
    },
    performance: {
      name: 'Kinerja Sales',
      path: '/reports/performance',
      exportType: 'performance',
      columns: [
        { label: 'Sales', render: (r) => LP.esc(r.name) + '<span class="block text-xs text-zinc-500">' + LP.esc(r.sales_code) + '</span>' },
        { label: 'Status', render: (r) => LP.badge(r.status) },
        { label: 'Lead', align: 'right', render: (r) => LP.number(r.total_leads) },
        { label: 'Booking', align: 'right', render: (r) => LP.number(r.total_bookings) },
        { label: 'Nilai Closing', align: 'right', render: (r) => LP.money(r.total_closing) },
        { label: 'Unit Terjual', align: 'right', render: (r) => LP.number(r.units_sold) },
        { label: 'Pendapatan Lunas', align: 'right', render: (r) => LP.money(r.revenue_sold) },
        { label: 'Target Unit', render: (r) => progressBar(LP.number(r.achieved_units) + ' / ' + LP.number(r.target_units), r.unit_progress) },
        { label: 'Target Nilai', render: (r) => progressBar(LP.shortMoney(r.achieved_amount) + ' / ' + LP.shortMoney(r.target_amount), r.amount_progress) },
      ],
      summary: (payload) => {
        const t = payload.totals || {};
        return [
          { label: 'Total Lead', value: LP.number(t.total_leads), tone: 'violet' },
          { label: 'Total Booking', value: LP.number(t.total_bookings), tone: 'amber' },
          { label: 'Total Closing', value: LP.shortMoney(t.total_closing), tone: 'emerald' },
          { label: 'Unit Terjual', value: LP.number(t.units_sold), tone: 'zinc' },
        ];
      },
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
        { label: 'Sumber', render: (r) => LP.esc(r.source || '-') },
        { label: 'Sales', render: (r) => LP.esc(r.sales_profile && r.sales_profile.user ? r.sales_profile.user.name : '-') },
        { label: 'Unit', render: (r) => LP.esc(r.unit ? r.unit.unit_code : (r.interested_unit || '-')) },
        { label: 'Budget', align: 'right', render: (r) => LP.money(r.budget) },
        { label: 'Follow Up Terakhir', render: (r) => LP.date(r.last_follow_up) },
        { label: 'Dibuat', render: (r) => LP.date(r.created_at) },
      ],
      summary: (payload) => {
        const t = payload.totals || {};
        return [
          { label: 'Total Konsumen', value: LP.number(t.total), tone: 'zinc' },
          { label: 'Cold', value: LP.number(t.cold), tone: 'sky' },
          { label: 'Warm', value: LP.number(t.warm), tone: 'amber' },
          { label: 'Hot', value: LP.number(t.hot), tone: 'emerald' },
          { label: 'Sudah Booking', value: LP.number(t.customer), tone: 'violet' },
        ];
      },
    },
  };

  let currentType = 'sales';
  const hiddenForPrint = [];

  function progressBar(text, percent) {
    const pct = Math.max(0, Math.min(100, Number(percent || 0)));
    return '<div class="min-w-[140px]">'
      + '<span class="text-xs font-medium text-zinc-700">' + LP.esc(text) + ' (' + LP.number(pct) + '%)</span>'
      + '<span class="mt-1 block h-1.5 w-full rounded-full bg-zinc-200">'
      + '<span class="block h-1.5 rounded-full bg-emerald-500" style="width:' + pct + '%"></span></span></div>';
  }

  function recapTable(title, map) {
    const rows = Object.entries(map || {}).map(([name, value]) => ({ name: name, transactions: value.transactions, total_amount: value.total_amount }));
    return '<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm">'
      + '<p class="border-b border-zinc-100 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(title) + '</p>'
      + '<div class="p-1" id="recap-' + LP.esc(title.toLowerCase().replace(/[^a-z0-9]+/g, '-')) + '"></div></div>';
  }

  // KOTAK RINGKASAN DISESUAIKAN MENJADI 4 KOLOM KECIL, PADAT, DAN PERSEGI PENDEK
  function gridCard(items) {
    return '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;">'
      + items.map((item) => {
          let toneClass = 'border-slate-200 bg-white';
          if (item.tone === 'emerald') toneClass = 'border-emerald-200 bg-emerald-50/30';
          if (item.tone === 'amber') toneClass = 'border-amber-200 bg-amber-50/30';
          if (item.tone === 'violet') toneClass = 'border-violet-200 bg-violet-50/30';
          if (item.tone === 'sky') toneClass = 'border-sky-200 bg-sky-50/30';

          return `<div style="padding: 8px 12px; border-radius: 8px; min-height: 60px; display: flex; flex-direction: column; justify-content: center;" class="border ${toneClass} shadow-sm">
            <p style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 1px;">${LP.esc(item.label)}</p>
            <p style="font-size: 15px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 0px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 9px; color: #64748b; margin-top: 1px;">${LP.esc(item.hint)}</p>` : ''}
          </div>`;
      }).join('') + '</div>';
  }

  function buildQuery() {
    const params = new URLSearchParams({
      start_date: document.getElementById('lap-start').value,
      end_date: document.getElementById('lap-end').value,
    });
    const status = document.getElementById('lap-status').value;
    if (currentType === 'consumers' && status) params.set('status', status);
    return params;
  }

  function setTypeButtons() {
    document.querySelectorAll('[data-type]').forEach((button) => {
      const active = button.dataset.type === currentType;
      button.className = 'rounded-xl border px-4 py-2 text-sm '
        + (active ? 'border-zinc-900 bg-zinc-900 font-semibold text-white' : 'border-zinc-300 bg-white font-medium text-zinc-700');
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  async function loadReport() {
    const conf = TYPES[currentType];
    document.getElementById('lap-status-wrap').className = currentType === 'consumers' ? 'block' : 'hidden';
    setTypeButtons();

    LP.loading('lap-summary', 'Memuat ringkasan...');
    LP.loading('lap-table', 'Memuat detail laporan...');
    document.getElementById('lap-period').textContent = 'Memuat laporan ' + conf.name.toLowerCase() + '...';
    document.getElementById('lap-recap-wrap').classList.add('hidden');
    document.getElementById('lap-recap').innerHTML = '';

    try {
      const { json } = await LP.http.get(conf.path + '?' + buildQuery().toString());
      if (!json) throw new Error('Respons laporan tidak dikenali.');

      const period = json.period || {};
      document.getElementById('lap-period').textContent = 'Laporan ' + conf.name + ' periode '
        + LP.date(period.start_date) + ' sampai ' + LP.date(period.end_date)
        + ' — ' + LP.number((json.data || []).length) + ' baris data.';

      document.getElementById('lap-summary').innerHTML = gridCard(conf.summary(json));

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

  document.getElementById('lap-print').addEventListener('click', () => window.print());

  window.addEventListener('beforeprint', () => {
    document.querySelectorAll('.lp-no-print, header, #lp-aside, #lp-user-menu').forEach((node) => {
      if (node.classList.contains('hidden')) return;
      node.classList.add('hidden');
      hiddenForPrint.push(node);
    });
  });
  window.addEventListener('afterprint', () => {
    hiddenForPrint.forEach((node) => node.classList.remove('hidden'));
    hiddenForPrint.length = 0;
  });

  document.getElementById('lap-start').value = LP.isoDate(Date.now() - 2592000000);
  document.getElementById('lap-end').value = LP.today();

  LP.guard(['manager', 'admin_sales'], loadReport);
</script>
@endpush