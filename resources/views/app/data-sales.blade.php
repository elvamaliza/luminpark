@extends('layouts.app')

@section('title', 'Data Sales')
@section('page_title', 'Data Sales')
@section('page_desc', 'Daftar akun sales. Buka tombol Detail untuk mengubah data, melihat kinerja, menonaktifkan akun, atau mereset kata sandi.')

@section('page_actions')
    <button id="btn-add-sales" type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah Sales
    </button>
@endsection

@section('content')
    <!-- Kotak statistik dengan variasi warna lembut -->
    <div id="sales-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cari Sales</span>
                <input id="sales-search" type="search" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20" placeholder="nama, kode sales, atau email">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status Sales</span>
                <select id="sales-status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                    <option value="resign">Resign</option>
                </select>
            </label>
            <p id="sales-count" class="self-end text-sm text-slate-500">Memuat data sales...</p>
        </div>
    </section>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Peringkat Sales</h2>
            <div class="flex flex-wrap gap-2">
                <select id="ranking-month" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"></select>
                <select id="ranking-year" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"></select>
                <select id="ranking-project" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                    <option value="">Semua Proyek</option>
                </select>
            </div>
        </div>
        <div id="sales-ranking-table"></div>
    </section>

    <div id="sales-table"></div>

    <p class="mt-4 text-xs text-slate-500">
        Reset kata sandi akan menghapus seluruh sesi login sales tersebut sehingga yang bersangkutan harus masuk ulang.
        Sales yang masih memiliki riwayat booking tidak dapat dihapus, cukup dinonaktifkan.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_PENGELOLA = ['super_admin', 'admin_sales'];
  const SALES_STATUS_LIST = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
    { value: 'resign', label: 'Resign' },
  ];

  const salesSearch = document.getElementById('sales-search');
  const salesStatusFilter = document.getElementById('sales-status');

  let salesAll = [];
  let salesView = [];
  let salesBookingCount = {};
  let salesRanking = [];

  function salesProfile(row) {
    return row.sales_profile || row.salesProfile || null;
  }

  function salesCode(row) {
    const profile = salesProfile(row);
    return profile && profile.sales_code ? profile.sales_code : '-';
  }

  function salesProfileStatus(row) {
    const profile = salesProfile(row);
    return profile && profile.status ? profile.status : row.status;
  }

  function salesBookings(row) {
    const profile = salesProfile(row);
    if (!profile) return 0;
    return salesBookingCount[profile.id] || 0;
  }

  function salesFiltered() {
    const kata = (salesSearch.value || '').trim().toLowerCase();
    const status = salesStatusFilter.value;

    return salesAll.filter((row) => {
      const haystack = [row.name, salesCode(row), row.email, row.phone].join(' ').toLowerCase();
      if (kata && haystack.indexOf(kata) === -1) return false;
      if (status && salesProfileStatus(row) !== status) return false;
      return true;
    });
  }

  // KOTAK STATISTIK DENGAN VARIASI WARNA LEMBUT
  function salesRenderStats() {
    const host = document.getElementById('sales-stats');
    const aktif = salesAll.filter((row) => salesProfileStatus(row) === 'aktif').length;
    const totalBooking = Object.keys(salesBookingCount).reduce((akumulasi, key) => akumulasi + salesBookingCount[key], 0);

    const items = [
      { label: 'Total Sales', value: LP.number(salesAll.length), bg: 'linear-gradient(135deg, #ffffff 0%, #fafafa 100%)', border: '#e2e8f0' },
      { label: 'Sales Aktif', value: LP.number(aktif), hint: (salesAll.length - aktif) + ' nonaktif atau resign', bg: 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)', border: '#bbf7d0' },
      { label: 'Total Booking', value: LP.number(totalBooking), hint: 'Tercatat pada modul booking', bg: 'linear-gradient(135deg, #fefce8 0%, #fef9c3 100%)', border: '#fde047' },
      { label: 'Tanpa Profil', value: LP.number(salesAll.filter((row) => !salesProfile(row)).length), hint: 'Perlu dilengkapi kode sales', bg: 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)', border: '#bae6fd' },
    ];

    host.innerHTML = '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;" class="max-xl:grid-cols-2">'
      + items.map((item) => {
          return `<div style="background: ${item.bg}; border: 1px solid ${item.border}; padding: 12px 14px; border-radius: 10px; min-height: 65px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);" class="transition-all hover:shadow-md">
            <p style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #334155; margin-bottom: 1px;">${LP.esc(item.label)}</p>
            <p style="font-size: 15px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 0px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 9px; color: #475569; margin-top: 1px;">${LP.esc(item.hint)}</p>` : ''}
          </div>`;
      }).join('') + '</div>';
  }

  function salesRender() {
    salesView = salesFiltered();

    document.getElementById('sales-count').textContent =
      salesView.length + ' sales ditampilkan dari ' + salesAll.length + ' akun sales.';

    LP.table({
      mount: 'sales-table',
      rows: salesView,
      empty: 'Belum ada data sales yang cocok dengan pencarian atau filter status.',
      columns: [
        { label: 'Kode Sales', render: (row) => '<span class="font-semibold text-slate-900">' + LP.esc(salesCode(row)) + '</span>' },
        {
          label: 'Nama Sales',
          render: (row) => '<span class="font-medium text-slate-900">' + LP.esc(row.name) + '</span>'
            + '<span class="block text-xs text-slate-500">' + LP.esc(row.email) + '</span>',
        },
        { label: 'Telepon', render: (row) => LP.esc(row.phone || '-') },
        { label: 'WhatsApp', render: (row) => { const p = salesProfile(row); return LP.esc(p && p.whatsapp_number ? p.whatsapp_number : '-'); } },
        { label: 'Bergabung', render: (row) => { const p = salesProfile(row); return LP.esc(p && p.join_date ? LP.date(p.join_date) : '-'); } },
        { label: 'Booking', align: 'right', render: (row) => LP.number(salesBookings(row)) },
        {
          label: 'Status',
          render: (row) => LP.badge(salesProfileStatus(row))
            + (row.status === 'aktif' ? '' : '<span class="ml-1 block text-xs text-slate-500">Akun ' + LP.esc(row.status) + '</span>'),
        },
        {
          label: 'Aksi',
          render: (row, index) => '<div class="flex flex-wrap gap-1.5">'
            + '<a href="/data-sales/' + LP.esc(row.id) + '" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Detail</a>'
            + '<button type="button" data-sales-action="delete" data-index="' + index + '" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Hapus</button>'
            + '</div>',
        },
      ],
    });
  }

  function salesStatusOptions() {
    return SALES_STATUS_LIST.map((item) => ({ value: item.value, label: item.label }));
  }

  function salesCreateFields() {
    return [
      { name: 'name', label: 'Nama Lengkap', type: 'text', required: true, placeholder: 'Nama sales' },
      { name: 'sales_code', label: 'Kode Sales', type: 'text', required: true, placeholder: 'SL-004', hint: 'Kode unik, maksimal 30 karakter.' },
      { name: 'email', label: 'Email Login', type: 'email', required: true, placeholder: 'Gunakan email perusahaan yang unik', hint: 'Email ini dipakai sales untuk masuk ke sistem.' },
      { name: 'birth_date', label: 'Tanggal Lahir', type: 'date', hint: 'Bila kata sandi dikosongkan, sistem memakai tanggal lahir sebagai kata sandi awal (yyyymmdd).' },
      { name: 'password', label: 'Kata Sandi (opsional)', type: 'password', hint: 'Kosongkan agar kata sandi awal otomatis memakai tanggal lahir. Pada login pertama sales wajib menggantinya dengan kata sandi kuat: minimal 8 karakter, huruf besar, huruf kecil, dan angka.' },
      { name: 'phone', label: 'Nomor Telepon', type: 'text', placeholder: '08123456789' },
      { name: 'whatsapp_number', label: 'Nomor WhatsApp', type: 'text', placeholder: '6281234567890' },
      { name: 'join_date', label: 'Tanggal Bergabung', type: 'date' },
      { name: 'status', label: 'Status Sales', type: 'select', required: true, options: salesStatusOptions() },
      { name: 'address', label: 'Alamat', type: 'textarea', rows: 2, col: 2 },
      { name: 'target_units', label: 'Target Unit (opsional)', type: 'number', min: 0, step: 1 },
      { name: 'target_amount', label: 'Target Nilai (Rp, opsional)', type: 'number', min: 0, step: 'any' },
      { name: 'target_period_start', label: 'Awal Periode Target', type: 'date' },
      { name: 'target_period_end', label: 'Akhir Periode Target', type: 'date' },
    ];
  }

  function tampilkanSandiAwal(nama, kataSandi) {
    LP.confirm(
      'Kata sandi awal ' + nama + ' adalah ' + kataSandi + ' (tanggal lahir, format yyyymmdd). Sales wajib menggantinya pada login pertama dengan kata sandi kuat (minimal 8 karakter, huruf besar, huruf kecil, dan angka). Simpan baik-baik sebelum menutup kotak ini.',
      { title: 'Kata Sandi Awal Sales', okLabel: 'Sudah Saya Catat' }
    );
  }

  async function salesCreate(values, context) {
    const body = {
      name: values.name.trim(),
      email: values.email.trim(),
      sales_code: values.sales_code.trim(),
      status: values.status,
    };
    if (values.password) body.password = values.password;
    if (values.birth_date) body.birth_date = values.birth_date;
    if (values.phone) body.phone = values.phone;
    if (values.whatsapp_number) body.whatsapp_number = values.whatsapp_number;
    if (values.join_date) body.join_date = values.join_date;
    if (values.address) body.address = values.address;
    if (values.target_units) body.target_units = Number(values.target_units);
    if (values.target_amount) body.target_amount = Number(values.target_amount);
    if (values.target_period_start) body.target_period_start = values.target_period_start;
    if (values.target_period_end) body.target_period_end = values.target_period_end;

    const hasil = await LP.http.post('/sales', body);
    LP.toast((hasil.json && hasil.json.message) || 'Sales baru berhasil ditambahkan.');
    context.close();
    if (hasil.json && hasil.json.auth && hasil.json.auth.password_default) {
      tampilkanSandiAwal(body.name, hasil.json.auth.password_default);
    }
    await salesLoad();
  }

  function salesOpenCreate() {
    LP.formModal({
      title: 'Tambah Sales Baru',
      description: 'Sistem membuat akun peran sales beserta profil sales dalam satu langkah.',
      fields: salesCreateFields(),
      values: { status: 'aktif' },
      submitLabel: 'Simpan Sales',
      onSubmit: (values, context) => salesCreate(values, context),
    });
  }

  async function salesDelete(row) {
    const setuju = await LP.confirm(
      'Data sales ' + row.name + ' (' + salesCode(row) + ') akan dikeluarkan dari daftar sales beserta akun loginnya. Riwayatnya tetap tersimpan di basis data dan dapat dipulihkan administrator.',
      { title: 'Hapus Sales', okLabel: 'Ya, Hapus', danger: true },
    );
    if (!setuju) return;

    try {
      const hasil = await LP.http.del('/sales/' + row.id);
      LP.toast((hasil.json && hasil.json.message) || 'Data sales berhasil dihapus.');
    } catch (error) {
      LP.toast(error.message || 'Data sales gagal dihapus.', 'error');
      return;
    }
    await salesLoad();
  }

  function rankingPeriod() {
    const year = Number(document.getElementById('ranking-year').value);
    const month = Number(document.getElementById('ranking-month').value);
    const start = year + '-' + String(month).padStart(2, '0') + '-01';
    const end = new Date(year, month, 0);
    return { start_date: start, end_date: year + '-' + String(month).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') };
  }

  function rankingControls() {
    const now = new Date();
    const month = document.getElementById('ranking-month');
    const year = document.getElementById('ranking-year');
    month.innerHTML = Array.from({ length: 12 }, (_, index) => '<option value="' + (index + 1) + '">' + new Date(2000, index, 1).toLocaleString('id-ID', { month: 'long' }) + '</option>').join('');
    year.innerHTML = Array.from({ length: 5 }, (_, index) => '<option value="' + (now.getFullYear() - index) + '">' + (now.getFullYear() - index) + '</option>').join('');
    month.value = now.getMonth() + 1;
    year.value = now.getFullYear();
  }

  function salesRenderRanking() {
    LP.table({
      mount: 'sales-ranking-table',
      rows: salesRanking,
      empty: 'Belum ada data peringkat sales pada periode ini.',
      columns: [
        { label: 'Peringkat', render: (row) => '<span class="inline-flex min-w-8 justify-center rounded-full px-2 py-1 text-xs font-bold ' + (row.rank === 1 ? 'bg-amber-100 text-amber-800' : row.rank === 2 ? 'bg-slate-200 text-slate-700' : row.rank === 3 ? 'bg-orange-100 text-orange-800' : 'bg-slate-50 text-slate-600') + '">' + LP.number(row.rank) + '</span>' },
        { label: 'Nama Sales', render: (row) => LP.esc(row.name || '-') },
        { label: 'Jumlah Booking', align: 'right', render: (row) => LP.number(row.jumlah_booking) },
        { label: 'Total Penjualan', align: 'right', render: (row) => LP.money(row.total_nilai) },
        { label: 'Capaian Target', align: 'right', render: (row) => row.unit_progress == null ? '-' : LP.number(row.unit_progress) + '%' },
      ],
    });
  }

  async function rankingLoad() {
    const period = rankingPeriod();
    const params = new URLSearchParams(period);
    const project = document.getElementById('ranking-project').value;
    if (project) params.set('cluster_id', project);
    LP.loading('sales-ranking-table', 'Memuat peringkat sales...');

    try {
      const { json } = await LP.http.get('/sales/ranking?' + params.toString());
      salesRanking = json && Array.isArray(json.data) ? json.data : [];
      const projects = json && Array.isArray(json.projects) ? json.projects : [];
      const selected = project || String((json && json.project_filter) || '');
      document.getElementById('ranking-project').innerHTML = '<option value="">Semua Proyek</option>' + projects.map((item) => '<option value="' + LP.esc(item.id) + '">' + LP.esc(item.name) + '</option>').join('');
      document.getElementById('ranking-project').value = selected;
      salesRenderRanking();
    } catch (error) {
      LP.errorBox('sales-ranking-table', error.message || 'Peringkat sales gagal dimuat.');
    }
  }

  async function salesLoad() {
    LP.loading('sales-table', 'Memuat data sales...');
    document.getElementById('sales-count').textContent = 'Memuat data sales...';

    try {
      const [daftar, booking] = await Promise.all([LP.fetchJson('/sales'), LP.fetchJson('/bookings')]);
      salesAll = Array.isArray(daftar) ? daftar : [];

      salesBookingCount = {};
      (Array.isArray(booking) ? booking : []).forEach((item) => {
        const kunci = String(item.sales_id);
        salesBookingCount[kunci] = (salesBookingCount[kunci] || 0) + 1;
      });
    } catch (error) {
      LP.errorBox('sales-table', error.message || 'Data sales gagal dimuat.');
      document.getElementById('sales-count').textContent = 'Data gagal dimuat.';
      return;
    }

    salesRenderStats();
    salesRender();
    await rankingLoad();
  }

  LP.guard(ROLE_PENGELOLA, async () => {
    rankingControls();
    document.getElementById('ranking-month').addEventListener('change', rankingLoad);
    document.getElementById('ranking-year').addEventListener('change', rankingLoad);
    document.getElementById('ranking-project').addEventListener('change', rankingLoad);
    document.getElementById('btn-add-sales').addEventListener('click', salesOpenCreate);

    const renderDebounced = LP.debounce(salesRender);
    salesSearch.addEventListener('input', renderDebounced);
    salesStatusFilter.addEventListener('change', salesRender);

    document.getElementById('sales-table').addEventListener('click', (event) => {
      const tombol = event.target.closest('button[data-sales-action]');
      if (!tombol) return;
      const baris = salesView[Number(tombol.dataset.index)];
      if (!baris) return;

      if (tombol.dataset.salesAction === 'delete') salesDelete(baris);
    });

    await salesLoad();
  });
</script>
@endpush