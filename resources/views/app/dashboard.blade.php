@extends('layouts.app')

@section('title', 'Dashboard')

@section('page_title', 'Dashboard')

@section('page_desc', 'Rincian unit, kinerja penjualan, dan aktivitas pemesanan terbaru per proyek sesuai peran Anda.')

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

    {{-- Revisi 10 & 32: super admin dan monitoring manajemen dapat mempersempit
         angka ke satu proyek; rincian selalu ditampilkan per proyek. --}}
    <section id="dash-proyek-block" class="hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <form id="dash-proyek-form" class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Filter Proyek</span>
                <select id="dash-proyek" class="w-64 rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm"></select>
            </label>
            <p id="dash-proyek-note" class="pb-2 text-xs text-zinc-500">Pilih proyek untuk mempersempit rincian, grafik, dan booking terbaru.</p>
        </form>
    </section>

    <section>
        <h2 id="dash-stats-title" class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Ringkasan</h2>
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
        <p class="mt-2 text-xs text-zinc-500">Enam bulan terakhir, dihitung dari pembayaran terverifikasi dan booking berstatus lunas pada proyek terpilih.</p>
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
  const LEAD_ORDER = ['cold', 'warm', 'hot'];
  const BOOKING_ORDER = ['menunggu dp', 'dp lunas', 'cicilan', 'lunas', 'dibatalkan'];

  let chartRows = [];
  let chartKey = 'revenue';
  let dashUser = null;
  let dashProyek = '';

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
        { label: 'Proyek', render: (r) => LP.esc(r.project || '-') },
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

  // Revisi 10 & 32: penapis proyek beserta rincian angka tiap proyek. Tabel ini
  // menggantikan kartu jumlah gabungan karena angka yang diminta adalah per proyek.
  function renderProyek(data) {
    document.getElementById('dash-proyek-block').classList.remove('hidden');
    document.getElementById('dash-stats-title').textContent = 'Ringkasan per Proyek';

    const pilihan = Array.isArray(data.projects) ? data.projects : [];
    const aktif = data.project_filter ? String(data.project_filter) : '';
    const namaAktif = (pilihan.find((item) => String(item.id) === aktif) || {}).name || '';

    document.getElementById('dash-proyek').innerHTML = '<option value="">Semua Proyek</option>'
      + pilihan.map((item) => '<option value="' + LP.esc(item.id) + '"'
        + (String(item.id) === aktif ? ' selected' : '') + '>' + LP.esc(item.name) + '</option>').join('');

    document.getElementById('dash-proyek-note').textContent = aktif
      ? 'Rincian, grafik, dan booking terbaru dibatasi ke proyek ' + namaAktif + '.'
      : 'Setiap baris adalah satu proyek. Klik baris proyek untuk mempersempit grafik dan booking terbaru; baris (belum ada proyek) berisi konsumen yang belum menautkan unit.';

    LP.table({
      mount: 'dash-stats',
      rows: Array.isArray(data.per_project) ? data.per_project : [],
      empty: 'Belum ada proyek dengan data.',
      onRow: (row) => {
        if (!row.cluster_id) return;
        dashProyek = String(row.cluster_id);
        load(dashUser);
      },
      columns: [
        { label: 'Proyek', render: (r) => '<span class="font-semibold">' + LP.esc(r.name || '-') + '</span>' },
        { label: 'Total Unit', align: 'right', render: (r) => LP.number(r.units_total) },
        { label: 'Tersedia', align: 'right', render: (r) => LP.number(r.units_available) },
        { label: 'Unit di Booking', align: 'right', render: (r) => LP.number(r.units_booking) },
        { label: 'Unit Terjual', align: 'right', render: (r) => LP.number(r.units_sold_out) },
        { label: 'Booking', align: 'right', render: (r) => LP.number(r.bookings_total) },
        { label: 'Booking Aktif', align: 'right', render: (r) => LP.number(r.bookings_active) },
        { label: 'Booking Dibatalkan', align: 'right', render: (r) => LP.number(r.bookings_cancelled) },
        { label: 'Belum Serah Terima', align: 'right', render: (r) => LP.number(r.units_not_delivered) },
        { label: 'Bayar Pending', align: 'right', render: (r) => LP.number(r.payments_pending) },
        { label: 'Sales', align: 'right', render: (r) => LP.number(r.sales_count) },
        { label: 'Prospek', align: 'right', render: (r) => LP.number(r.prospects) },
        { label: 'Konsumen', align: 'right', render: (r) => LP.number(r.customers) },
        { label: 'Konsumen Dibatalkan', align: 'right', render: (r) => LP.number(r.cancelled_customers) },
        { label: 'Pendapatan', align: 'right', render: (r) => LP.money(r.revenue) },
      ],
    });
  }

  async function load(user) {
    dashUser = user;
    const isAdmin = user.role === 'admin_sales';
    const params = new URLSearchParams();
    if (dashProyek) params.set('cluster_id', dashProyek);
    if (isAdmin) {
      params.set('start_date', document.getElementById('dash-start').value || firstOfMonth());
      params.set('end_date', document.getElementById('dash-end').value || LP.today());
    }

    const endpoint = user.role === 'sales'
      ? '/dashboard/sales'
      : (isAdmin ? '/dashboard/admin-sales' : '/dashboard/super-admin') + '?' + params.toString();

    LP.loading('dash-stats', user.role === 'sales'
      ? 'Memuat ringkasan dashboard...'
      : 'Memuat rincian angka setiap proyek...');
    LP.loading('dash-chart', 'Memuat grafik penjualan...');
    LP.loading('dash-bookings', 'Memuat booking terbaru...');
    if (isAdmin) LP.loading('dash-persales');

    try {
      const data = await LP.fetchJson(endpoint);

      if (user.role === 'sales') {
        document.getElementById('dash-stats-title').textContent = 'Ringkasan';
        document.getElementById('dash-stats').innerHTML = salesStats(data);
        document.getElementById('dash-mine').classList.remove('hidden');
        pillList('dash-lead-status', data.leads_by_status || {}, LEAD_ORDER);
        pillList('dash-booking-status', data.bookings_by_status || {}, BOOKING_ORDER);
      } else {
        renderProyek(data);
        if (isAdmin) {
          document.getElementById('dash-period').classList.remove('hidden');
          renderPersales(data.per_sales);
        }
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

  document.getElementById('dash-proyek').addEventListener('change', (event) => {
    dashProyek = event.target.value;
    if (dashUser) load(dashUser);
  });

  document.getElementById('dash-refresh').addEventListener('click', (event) => {
    LP.withBusy(event.currentTarget, () => LP.guard(['super_admin', 'admin_sales', 'sales'], load));
  });

  document.getElementById('dash-start').value = firstOfMonth();
  document.getElementById('dash-end').value = LP.today();

  LP.guard(['super_admin', 'admin_sales', 'sales'], load);
</script>
@endpush
