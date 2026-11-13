@extends('layouts.app')

@section('title', 'Dashboard')

@section('page_title', 'Dashboard')

@section('page_desc', 'Ringkasan unit, kinerja penjualan, dan aktivitas pemesanan terbaru sesuai peran Anda.')

@section('page_actions')
<button id="dash-refresh" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Muat Ulang</button>
@endsection

@section('content')
<div class="space-y-6">
    <section id="dash-period" class="hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <form id="dash-period-form" class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Dari Tanggal</span>
                <input id="dash-start" name="start_date" type="date" class="rounded-xl border border-zinc-300 px-3 py-2 text-sm" >
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Sampai Tanggal</span>
                <input id="dash-end" name="end_date" type="date" class="rounded-xl border border-zinc-300 px-3 py-2 text-sm" >
            </label>
            <button type="submit" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
            <span class="pb-2 text-xs text-zinc-500">Periode mempengaruhi rekapitulasi penjualan seluruh sales.</span>
        </form>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Ringkasan</h2>
        <div id="dash-stats"></div>
    </section>

    <section id="dash-mine" class="hidden">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Status Konsumen &amp; Booking Saya</h2>
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Calon Konsumen per Status</p>
                <div id="dash-lead-status" class="mt-3 grid gap-2 sm:grid-cols-2"></div>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Booking per Status</p>
                <div id="dash-booking-status" class="mt-3 grid gap-2 sm:grid-cols-2"></div>
            </div>
        </div>
    </section>

    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Grafik Penjualan per Bulan</h2>
            <div id="dash-chart-toggle" class="flex gap-2" role="group" aria-label="Pilih nilai grafik">
                <button type="button" data-chart="revenue" class="rounded-lg border border-zinc-300 bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white">Pendapatan</button>
                <button type="button" data-chart="units_sold" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700">Unit Terjual</button>
            </div>
        </div>
        <div id="dash-chart"></div>
        <p class="mt-2 text-xs text-zinc-500">Enam bulan terakhir, dihitung dari pembayaran terverifikasi dan booking berstatus lunas.</p>
    </section>

    <section id="dash-totals-block" class="hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Counter Operasional</h2>
        <div id="dash-counters" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
    </section>

    <section id="dash-persales-block" class="hidden">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Rekapitulasi per Sales</h2>
        <div id="dash-persales"></div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Booking Terbaru</h2>
        <div id="dash-bookings"></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
  const LEAD_ORDER = ['cold', 'warm', 'hot', 'booking'];
  const BOOKING_ORDER = ['menunggu dp', 'dp lunas', 'cicilan', 'lunas'];

  let chartRows = [];
  let chartKey = 'revenue';

  const firstOfMonth = () => LP.today().slice(0, 8) + '01';

  function statsGrid(items) {
    return '<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">'
      + items.map((item) => LP.statCard(item)).join('') + '</div>';
  }

  function pillList(host, source, order) {
    document.getElementById(host).innerHTML = order.map((key) => `
      <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2">
        ${LP.badge(key)}
        <span class="text-sm font-bold tabular-nums text-zinc-800">${LP.number(source[key] || 0)}</span>
      </div>`).join('');
  }

  function counterList(host, source) {
    const entries = Object.entries(source);
    if (!entries.length) {
      document.getElementById(host).innerHTML = '<p class="text-sm text-zinc-500">Tidak ada counter.</p>';
      return;
    }
    document.getElementById(host).innerHTML = entries.map(([key, value]) => `
      <div class="rounded-xl border border-zinc-200 px-3 py-2">
        <p class="text-xs uppercase tracking-wide text-zinc-500">${LP.esc(key.replace(/_/g, ' '))}</p>
        <p class="mt-1 text-lg font-bold tabular-nums">${LP.number(value)}</p>
      </div>`).join('');
  }

  function managerStats(data) {
    return statsGrid([
      { label: 'Total Unit', value: LP.number(data.total_units), hint: 'Booking ' + LP.number(data.units_booking) + ' · Terjual ' + LP.number(data.units_sold_out) },
      { label: 'Unit Tersedia', value: LP.number(data.units_available), tone: 'emerald' },
      { label: 'Jumlah Sales', value: LP.number(data.total_sales), tone: 'sky' },
      { label: 'Jumlah Konsumen', value: LP.number(data.total_customers), hint: 'Calon konsumen ' + LP.number(data.total_prospects) + ' · Total lead ' + LP.number(data.total_leads), tone: 'violet' },
      { label: 'Booking Aktif', value: LP.number(data.active_bookings), hint: 'Total booking ' + LP.number(data.total_bookings), tone: 'amber' },
      { label: 'Pembayaran Pending', value: LP.number(data.payments_pending), hint: 'Menunggu verifikasi super admin', tone: 'amber' },
      { label: 'Total Pendapatan', value: LP.shortMoney(data.total_revenue), hint: 'Pembayaran terverifikasi', tone: 'emerald' },
      { label: 'Belum Serah Terima', value: LP.number(data.units_not_delivered), hint: 'Booking lunas menunggu serah terima' },
    ]);
  }

  function adminStats(data) {
    const c = data.counters || {};
    const t = data.totals || {};
    const p = data.period || {};
    const periodHint = p.start_date ? (LP.date(p.start_date) + ' - ' + LP.date(p.end_date)) : 'Periode berjalan';
    return statsGrid([
      { label: 'Total Unit', value: LP.number(c.total_units), hint: 'Terjual ' + LP.number(c.units_sold_out) },
      { label: 'Unit Tersedia', value: LP.number(c.units_available), tone: 'emerald' },
      { label: 'Jumlah Sales', value: LP.number(t.sales_count), tone: 'sky' },
      { label: 'Jumlah Konsumen', value: LP.number(c.total_customers), tone: 'violet' },
      { label: 'Booking Aktif', value: LP.number(c.active_bookings), tone: 'amber' },
      { label: 'Pembayaran Pending', value: LP.number(c.payments_pending), hint: 'Perlu diverifikasi super admin', tone: 'amber' },
      { label: 'Total Pendapatan', value: LP.shortMoney(t.total_revenue), hint: 'Periode ' + periodHint, tone: 'emerald' },
      { label: 'Total Booking', value: LP.number(t.total_bookings), hint: 'Lead seluruh sales ' + LP.number(t.total_leads) },
    ]);
  }

  function salesStats(data) {
    return statsGrid([
      { label: 'Jumlah Konsumen', value: LP.number(data.total_leads), hint: 'Calon konsumen yang saya tangani', tone: 'violet' },
      { label: 'Booking Saya', value: LP.number(data.total_bookings), tone: 'amber' },
      { label: 'Total Penjualan', value: LP.shortMoney(data.total_closing), hint: 'Nilai booking ' + LP.esc(data.sales_code || ''), tone: 'emerald' },
      { label: 'Unit Tersedia', value: LP.number(data.units_available), tone: 'emerald' },
      { label: 'Total Pembayaran', value: LP.number(data.payments_total), tone: 'sky' },
      { label: 'Pembayaran Pending', value: LP.number(data.payments_pending), tone: 'amber' },
      { label: 'Perlu Tindak Lanjut', value: LP.number(data.pending_follow_up), hint: 'Lead tanpa follow up 7 hari' },
      { label: 'Kode Sales', value: LP.esc(data.sales_code || '-'), hint: 'Kode unik akun sales Anda' },
    ]);
  }

  function drawChart() {
    const toggle = document.getElementById('dash-chart-toggle');
    if (!chartRows.length) {
      toggle.classList.add('hidden');
      document.getElementById('dash-chart').innerHTML =
        '<div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 shadow-sm">Grafik penjualan per bulan tidak tersedia pada ringkasan peran ini.</div>';
      return;
    }
    toggle.classList.remove('hidden');
    LP.barChart({
      mount: 'dash-chart',
      rows: chartRows,
      valueKey: chartKey,
      labelKey: 'label',
      format: chartKey === 'revenue' ? LP.shortMoney : LP.number,
    });
  }

  function renderBookings(rows) {
    LP.table({
      mount: 'dash-bookings',
      rows: rows || [],
      empty: 'Belum ada booking pada data saat ini.',
      columns: [
        { label: 'Kode Booking', key: 'booking_code' },
        { label: 'Konsumen', render: (r) => LP.esc(r.customer || '-') },
        { label: 'Unit', render: (r) => LP.esc(r.unit || '-') + (r.unit_type ? '<span class="block text-xs text-zinc-500">' + LP.esc(r.unit_type) + '</span>' : '') },
        { label: 'Sales', render: (r) => LP.esc(r.sales || '-') },
        { label: 'Tanggal Booking', render: (r) => LP.date(r.booking_date) },
        { label: 'Harga', align: 'right', render: (r) => LP.money(r.total_price) },
        { label: 'Sisa', align: 'right', render: (r) => LP.money(r.remaining) },
        { label: 'Status', render: (r) => LP.badge(r.status) },
      ],
    });
  }

  function renderPersales(rows) {
    document.getElementById('dash-persales-block').classList.remove('hidden');
    LP.table({
      mount: 'dash-persales',
      rows: rows || [],
      empty: 'Belum ada data sales.',
      columns: [
        { label: 'Nama Sales', render: (r) => LP.esc(r.name || '-') },
        { label: 'Kode', key: 'sales_code' },
        { label: 'Lead', align: 'right', render: (r) => LP.number(r.leads_count) },
        { label: 'Booking', align: 'right', render: (r) => LP.number(r.bookings_count) },
        { label: 'Nilai Penjualan', align: 'right', render: (r) => LP.money(r.revenue) },
        { label: 'Status', render: (r) => LP.badge(r.status) },
      ],
    });
  }

  async function load(user) {
    const isAdmin = user.role === 'admin_sales';
    const endpoint = user.role === 'sales'
      ? '/dashboard/sales'
      : isAdmin
        ? '/dashboard/admin-sales' + '?' + new URLSearchParams({
          start_date: document.getElementById('dash-start').value || firstOfMonth(),
          end_date: document.getElementById('dash-end').value || LP.today(),
        })
        : '/dashboard/super-admin';

    LP.loading('dash-stats', 'Memuat ringkasan dashboard...');
    LP.loading('dash-chart', 'Memuat grafik penjualan...');
    LP.loading('dash-bookings', 'Memuat booking terbaru...');
    if (isAdmin) LP.loading('dash-persales');

    try {
      const data = await LP.fetchJson(endpoint);

      if (user.role === 'sales') {
        document.getElementById('dash-stats').innerHTML = salesStats(data);
        document.getElementById('dash-mine').classList.remove('hidden');
        pillList('dash-lead-status', data.leads_by_status || {}, LEAD_ORDER);
        pillList('dash-booking-status', data.bookings_by_status || {}, BOOKING_ORDER);
      } else if (isAdmin) {
        document.getElementById('dash-stats').innerHTML = adminStats(data);
        document.getElementById('dash-period').classList.remove('hidden');
        document.getElementById('dash-totals-block').classList.remove('hidden');
        counterList('dash-counters', Object.assign({}, data.counters, {
          sales_count: (data.totals || {}).sales_count,
          total_bookings: (data.totals || {}).total_bookings,
          total_leads: (data.totals || {}).total_leads,
        }));
        renderPersales(data.per_sales);
      } else {
        document.getElementById('dash-stats').innerHTML = managerStats(data);
      }

      chartRows = (data.sales_by_month || []).map((row) => ({
        month: row.month,
        label: LP.monthLabel(row.month),
        revenue: Number(row.revenue || 0),
        units_sold: Number(row.units_sold || 0),
      }));
      drawChart();
      renderBookings(data.recent_bookings);
    } catch (error) {
      LP.errorBox('dash-stats', error.message || 'Gagal memuat dashboard.');
      LP.errorBox('dash-chart', 'Grafik tidak dapat dimuat.');
      LP.errorBox('dash-bookings', 'Daftar booking terbaru tidak dapat dimuat.');
      if (isAdmin) LP.errorBox('dash-persales', 'Rekapitulasi per sales tidak dapat dimuat.');
      LP.toast(error.message || 'Gagal memuat dashboard.', 'error');
    }
  }

  document.querySelectorAll('[data-chart]').forEach((button) => {
    button.addEventListener('click', () => {
      chartKey = button.dataset.chart;
      document.querySelectorAll('[data-chart]').forEach((other) => {
        const active = other === button;
        other.className = 'rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-semibold '
          + (active ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700');
      });
      drawChart();
    });
  });

  document.getElementById('dash-period-form').addEventListener('submit', (event) => {
    event.preventDefault();
    LP.guard(['super_admin', 'admin_sales', 'sales'], load);
  });

  document.getElementById('dash-refresh').addEventListener('click', (event) => {
    LP.withBusy(event.currentTarget, () => LP.guard(['super_admin', 'admin_sales', 'sales'], load));
  });

  document.getElementById('dash-start').value = firstOfMonth();
  document.getElementById('dash-end').value = LP.today();

  LP.guard(['super_admin', 'admin_sales', 'sales'], load);
</script>
@endpush
