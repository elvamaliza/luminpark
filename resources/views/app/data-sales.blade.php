@extends('layouts.app')

@section('title', 'Data Sales')
@section('page_title', 'Data Sales')
@section('page_desc', 'Daftar akun sales. Buka tombol Detail untuk mengubah data, melihat kinerja, menonaktifkan akun, atau mereset kata sandi.')

@section('page_actions')
    <button id="btn-add-sales" type="button" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah Sales
    </button>
@endsection

@section('content')
    <!-- Kotak statistik disesuaikan agar ukurannya kecil, padat, dan 4 kolom persegi -->
    <div id="sales-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Cari Sales</span>
                <input id="sales-search" type="search" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10" placeholder="nama, kode sales, atau email">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status Sales</span>
                <select id="sales-status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                    <option value="resign">Resign</option>
                </select>
            </label>
            <p id="sales-count" class="self-end text-sm text-zinc-500">Memuat data sales...</p>
        </div>
    </section>

    <div id="sales-table"></div>

    <p class="mt-4 text-xs text-zinc-500">
        Reset kata sandi akan menghapus seluruh sesi login sales tersebut sehingga yang bersangkutan harus masuk ulang.
        Sales yang masih memiliki riwayat booking tidak dapat dihapus, cukup dinonaktifkan.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_PENGELOLA = ['manager', 'admin_sales'];
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

  // KOTAK STATISTIK DIUBAH JADI 4 KOLOM KECIL & PERSEGI PENDEK
  function salesRenderStats() {
    const host = document.getElementById('sales-stats');
    const aktif = salesAll.filter((row) => salesProfileStatus(row) === 'aktif').length;
    const totalBooking = Object.keys(salesBookingCount).reduce((akumulasi, key) => akumulasi + salesBookingCount[key], 0);

    const items = [
      { label: 'Total Sales', value: LP.number(salesAll.length), tone: 'zinc' },
      { label: 'Sales Aktif', value: LP.number(aktif), hint: (salesAll.length - aktif) + ' nonaktif atau resign', tone: 'emerald' },
      { label: 'Total Booking', value: LP.number(totalBooking), hint: 'Tercatat pada modul booking', tone: 'amber' },
      { label: 'Tanpa Profil', value: LP.number(salesAll.filter((row) => !salesProfile(row)).length), hint: 'Perlu dilengkapi kode sales', tone: 'sky' },
    ];

    host.innerHTML = '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;">'
      + items.map((item) => {
          let toneClass = 'border-slate-200 bg-white';
          if (item.tone === 'emerald') toneClass = 'border-emerald-200 bg-emerald-50/30';
          if (item.tone === 'sky') toneClass = 'border-sky-200 bg-sky-50/30';
          if (item.tone === 'amber') toneClass = 'border-amber-200 bg-amber-50/30';

          return `<div style="padding: 8px 12px; border-radius: 8px; min-height: 60px; display: flex; flex-direction: column; justify-content: center;" class="border ${toneClass} shadow-sm">
            <p style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 1px;">${LP.esc(item.label)}</p>
            <p style="font-size: 15px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 0px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 9px; color: #64748b; margin-top: 1px;">${LP.esc(item.hint)}</p>` : ''}
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
        { label: 'Kode Sales', render: (row) => '<span class="font-semibold text-zinc-900">' + LP.esc(salesCode(row)) + '</span>' },
        {
          label: 'Nama Sales',
          render: (row) => '<span class="font-medium text-zinc-900">' + LP.esc(row.name) + '</span>'
            + '<span class="block text-xs text-zinc-500">' + LP.esc(row.email) + '</span>',
        },
        { label: 'Telepon', render: (row) => LP.esc(row.phone || '-') },
        { label: 'WhatsApp', render: (row) => { const p = salesProfile(row); return LP.esc(p && p.whatsapp_number ? p.whatsapp_number : '-'); } },
        { label: 'Bergabung', render: (row) => { const p = salesProfile(row); return LP.esc(p && p.join_date ? LP.date(p.join_date) : '-'); } },
        { label: 'Booking', align: 'right', render: (row) => LP.number(salesBookings(row)) },
        {
          label: 'Status',
          render: (row) => LP.badge(salesProfileStatus(row))
            + (row.status === 'aktif' ? '' : '<span class="ml-1 block text-xs text-zinc-500">Akun ' + LP.esc(row.status) + '</span>'),
        },
        {
          label: 'Aksi',
          render: (row, index) => '<div class="flex flex-wrap gap-1.5">'
            + '<a href="/data-sales/' + LP.esc(row.id) + '" class="rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">Detail</a>'
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
      { name: 'birth_date', label: 'Tanggal Lahir', type: 'date', hint: 'Bila kata sandi dikosongkan, sistem memakai tanggal lahir sebagai kata sandi awal (ddmmyyyy).' },
      { name: 'password', label: 'Kata Sandi (opsional)', type: 'password', hint: 'Kosongkan agar kata sandi awal otomatis memakai tanggal lahir. Sales wajib menggantinya saat pertama masuk.' },
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
      'Kata sandi awal ' + nama + ' adalah ' + kataSandi + ' (tanggal lahir, format ddmmyyyy). Sales wajib menggantinya pada login pertama. Simpan baik-baik sebelum menutup kotak ini.',
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
  }

  LP.guard(ROLE_PENGELOLA, async () => {
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