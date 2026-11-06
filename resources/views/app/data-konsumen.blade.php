@extends('layouts.app')

@section('title', 'Data Konsumen')
@section('page_title', 'Data Konsumen')
@section('page_desc', 'Daftar calon konsumen dan konsumen. Buka tombol Detail untuk mengubah data, menyesuaikan status, atau mencatat follow up.')

@section('page_actions')
    <a href="/booking" class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Booking Unit</a>
    <button id="btn-add-lead" type="button" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah Konsumen
    </button>
@endsection

@section('content')
    <!-- Kotak statistik disesuaikan menjadi 4 kolom kecil, padat, dan persegi pendek -->
    <div id="lead-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Cari Konsumen</span>
                <input id="lead-search" type="search" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10" placeholder="nama, nomor telepon, atau email">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status Konsumen</span>
                <select id="lead-status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Status</option>
                    <option value="cold">Cold</option>
                    <option value="warm">Warm</option>
                    <option value="hot">Hot</option>
                    <option value="booking">Booking</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Sales Penanganan</span>
                <select id="lead-sales" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Sales</option>
                </select>
            </label>
            <p id="lead-count" class="self-end text-sm text-zinc-500">Memuat data konsumen...</p>
        </div>
    </section>

    <div id="lead-table"></div>

    <p class="mt-4 text-xs text-zinc-500">
        Klik baris konsumen untuk membuka detail beserta riwayat interaksinya. Booking hanya dapat dibuat untuk konsumen berstatus Hot.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_INTERNAL = ['manager', 'admin_sales', 'sales'];
  const LEAD_STATUS_LIST = [
    { value: 'cold', label: 'Cold' },
    { value: 'warm', label: 'Warm' },
    { value: 'hot', label: 'Hot' },
    { value: 'booking', label: 'Booking' },
  ];

  const leadSearch = document.getElementById('lead-search');
  const leadStatusFilter = document.getElementById('lead-status');
  const leadSalesFilter = document.getElementById('lead-sales');

  let leadAll = [];
  let leadView = [];
  let leadUser = null;
  let leadSalesOptions = [];
  let leadUnitOptions = [];

  function leadProfile(row) {
    return row.sales_profile || row.salesProfile || null;
  }

  function leadSalesName(row) {
    const profile = leadProfile(row);
    if (profile && profile.user && profile.user.name) return profile.user.name;
    if (profile && profile.sales_code) return profile.sales_code;
    return 'Belum ditangani';
  }

  function leadUnitLabel(row) {
    if (row.interested_unit) return row.interested_unit;
    if (row.unit && row.unit.unit_code) return row.unit.unit_code;
    return '-';
  }

  function leadStale(row) {
    if (row.status === 'booking') return false;
    if (!row.last_follow_up) return true;
    const hari = new Date(row.last_follow_up);
    if (Number.isNaN(hari.getTime())) return true;
    return (Date.now() - hari.getTime()) > 7 * 24 * 60 * 60 * 1000;
  }

  function leadFiltered() {
    const kata = (leadSearch.value || '').trim().toLowerCase();
    const status = leadStatusFilter.value;
    const salesId = leadSalesFilter.value;

    return leadAll.filter((row) => {
      const haystack = [row.name, row.phone, row.email, leadUnitLabel(row)].join(' ').toLowerCase();
      if (kata && haystack.indexOf(kata) === -1) return false;
      if (status && row.status !== status) return false;
      if (salesId && String(row.sales_id || '') !== salesId) return false;
      return true;
    });
  }

  // KOTAK STATISTIK DIUBAH JADI 4 KOLOM KECIL & PERSEGI PENDEK
  function leadRenderStats() {
    const host = document.getElementById('lead-stats');
    const jumlah = (status) => leadAll.filter((row) => row.status === status).length;

    const items = [
      { label: 'Total Konsumen', value: LP.number(leadAll.length), tone: 'zinc' },
      { label: 'Hot', value: LP.number(jumlah('hot')), hint: 'Siap dibuat booking', tone: 'rose' },
      { label: 'Sudah Booking', value: LP.number(jumlah('booking')), tone: 'amber' },
      { label: 'Perlu Follow Up', value: LP.number(leadAll.filter(leadStale).length), hint: 'Belum ada kontak 7 hari terakhir', tone: 'sky' },
    ];

    host.innerHTML = '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;">'
      + items.map((item) => {
          let toneClass = 'border-slate-200 bg-white';
          if (item.tone === 'rose') toneClass = 'border-rose-200 bg-rose-50/30';
          if (item.tone === 'amber') toneClass = 'border-amber-200 bg-amber-50/30';
          if (item.tone === 'sky') toneClass = 'border-sky-200 bg-sky-50/30';

          return `<div style="padding: 8px 12px; border-radius: 8px; min-height: 60px; display: flex; flex-direction: column; justify-content: center;" class="border ${toneClass} shadow-sm">
            <p style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 1px;">${LP.esc(item.label)}</p>
            <p style="font-size: 15px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 0px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 9px; color: #64748b; margin-top: 1px;">${LP.esc(item.hint)}</p>` : ''}
          </div>`;
      }).join('') + '</div>';
  }

  function leadRender() {
    leadView = leadFiltered();

    document.getElementById('lead-count').textContent =
      leadView.length + ' konsumen ditampilkan dari ' + leadAll.length + ' data konsumen.';

    LP.table({
      mount: 'lead-table',
      rows: leadView,
      empty: 'Belum ada konsumen yang cocok. Tambahkan konsumen baru atau ubah kata kunci pencarian.',
      onRow: (row) => { window.location.href = '/data-konsumen/' + row.id; },
      columns: [
        {
          label: 'Konsumen',
          render: (row) => '<span class="font-semibold text-zinc-900">' + LP.esc(row.name) + '</span>'
            + '<span class="block text-xs text-zinc-500">' + LP.esc(row.email || 'Tanpa email') + '</span>',
        },
        { label: 'No. Telepon', render: (row) => LP.esc(row.phone) },
        { label: 'Status', render: (row) => LP.badge(row.status) },
        { label: 'Sales', render: (row) => LP.esc(leadSalesName(row)) },
        { label: 'Unit Diminati', render: (row) => LP.esc(leadUnitLabel(row)) },
        { label: 'Follow Up Terakhir', render: (row) => LP.date(row.last_follow_up) },
        {
          label: 'Aksi',
          render: (row, index) => '<div class="flex flex-wrap gap-1.5" data-stop>'
            + '<a href="/data-konsumen/' + LP.esc(row.id) + '" class="rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">Detail</a>'
            + (leadBolehKelola()
              ? '<button type="button" data-lead-action="delete" data-index="' + index + '" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Hapus</button>'
              : '')
            + '</div>',
        },
      ],
    });
  }

  function leadIsSales() {
    return leadUser && leadUser.role === 'sales';
  }

  function leadBolehKelola() {
    return LP.can(['manager', 'sales']);
  }

  function leadFields() {
    const fields = [
      { name: 'name', label: 'Nama Konsumen', type: 'text', required: true, placeholder: 'Nama lengkap konsumen' },
      { name: 'phone', label: 'Nomor Telepon', type: 'text', required: true, placeholder: '081234567890', hint: 'Maksimal 15 karakter.' },
      { name: 'email', label: 'Email', type: 'email', placeholder: 'Email konsumen (opsional)' },
      { name: 'status', label: 'Status Konsumen', type: 'select', required: true, options: LEAD_STATUS_LIST },
    ];

    if (!leadIsSales()) {
      fields.push({
        name: 'sales_id',
        label: 'Sales Penanganan',
        type: 'select',
        options: leadSalesOptions,
        placeholder: 'Belum ditangani sales',
      });
    }

    fields.push(
      { name: 'unit_id', label: 'Unit Diminati', type: 'select', options: leadUnitList(), placeholder: 'Belum memilih unit' },
      { name: 'interested_unit', label: 'Kode Tipe Unit (teks)', type: 'text', placeholder: 'Modern Tropical 60', hint: 'Opsional, misal catatan minat dari halaman kontak.' },
      { name: 'budget', label: 'Perkiraan Anggaran (Rp)', type: 'number', min: 0, step: 'any' },
      { name: 'last_follow_up', label: 'Follow Up Terakhir', type: 'date' },
      { name: 'source', label: 'Sumber Konsumen', type: 'text', placeholder: 'manual, website, telemarketing, atau event' },
      { name: 'address', label: 'Alamat', type: 'textarea', rows: 2, col: 2 },
      { name: 'note', label: 'Catatan', type: 'textarea', rows: 3, col: 2, placeholder: 'Preferensi, keberatan, atau kesepakatan dengan konsumen.' },
    );

    return fields;
  }

  function leadUnitList() {
    return leadUnitOptions.map((item) => ({ value: item.id, label: item.unit_code + ' — ' + item.unit_type }));
  }

  function leadValues() {
    return { status: 'cold', source: 'manual' };
  }

  function leadBody(values) {
    const body = {
      name: values.name.trim(),
      phone: values.phone.trim(),
      status: values.status,
      email: values.email,
      address: values.address,
      interested_unit: values.interested_unit,
      budget: values.budget,
      note: values.note,
      last_follow_up: values.last_follow_up,
      source: values.source,
      unit_id: values.unit_id,
    };
    if (!leadIsSales()) body.sales_id = values.sales_id;
    return body;
  }

  async function leadSave(values, context) {
    const hasil = await LP.http.post('/customer-leads', leadBody(values));

    LP.toast((hasil.json && hasil.json.message) || 'Data konsumen berhasil disimpan.');
    context.close();
    await leadLoad();
  }

  function leadOpenCreate() {
    LP.formModal({
      title: 'Tambah Konsumen Baru',
      description: 'Catat calon konsumen baru beserta sales yang menanganinya. Ubah data dan status dilakukan pada halaman detail konsumen.',
      fields: leadFields(),
      values: leadValues(),
      submitLabel: 'Simpan Konsumen',
      onSubmit: (values, context) => leadSave(values, context),
    });
  }

  async function leadDelete(row) {
    const setuju = await LP.confirm(
      'Data konsumen ' + row.name + ' beserta catatan follow up-nya akan dikeluarkan dari daftar. Riwayatnya tetap tersimpan di basis data dan dapat dipulihkan administrator.',
      { title: 'Hapus Konsumen', okLabel: 'Ya, Hapus', danger: true },
    );
    if (!setuju) return;

    try {
      const hasil = await LP.http.del('/customer-leads/' + row.id);
      LP.toast((hasil.json && hasil.json.message) || 'Data konsumen berhasil dihapus.');
    } catch (error) {
      LP.toast(error.message || 'Data konsumen gagal dihapus.', 'error');
      return;
    }
    await leadLoad();
  }

  function leadPaintFilters() {
    const selectedSales = leadSalesFilter.value;

    if (leadIsSales()) {
      leadSalesFilter.innerHTML = '<option value="">Konsumen Saya</option>';
      leadSalesFilter.disabled = true;
    } else {
      leadSalesFilter.innerHTML = '<option value="">Semua Sales</option>'
        + leadSalesOptions.map((item) => '<option value="' + LP.esc(item.value) + '">' + LP.esc(item.label) + '</option>').join('');
      leadSalesFilter.value = selectedSales;
    }
  }

  async function leadLoad() {
    LP.loading('lead-table', 'Memuat data konsumen...');
    document.getElementById('lead-count').textContent = 'Memuat data konsumen...';

    try {
      const daftar = await LP.fetchJson('/customer-leads');
      leadAll = Array.isArray(daftar) ? daftar : [];
    } catch (error) {
      LP.errorBox('lead-table', error.message || 'Data konsumen gagal dimuat.');
      document.getElementById('lead-count').textContent = 'Data gagal dimuat.';
      return;
    }

    leadRenderStats();
    leadRender();
  }

  LP.guard(ROLE_INTERNAL, async (user) => {
    leadUser = user;

    if (leadBolehKelola()) {
      const tugas = [LP.fetchJson('/units-available')];
      if (!leadIsSales()) tugas.push(LP.fetchJson('/sales'));

      try {
        const [unit, daftarSales] = await Promise.all(tugas);
        leadUnitOptions = Array.isArray(unit) ? unit : [];
        if (!leadIsSales()) {
          leadSalesOptions = (Array.isArray(daftarSales) ? daftarSales : [])
            .filter((item) => leadProfile(item))
            .map((item) => ({ value: item.sales_profile.id, label: item.name + ' (' + item.sales_profile.sales_code + ')' }));
        }
      } catch (error) {
        LP.toast('Sebagian data pilihan gagal dimuat: ' + error.message, 'error');
      }
    }

    leadPaintFilters();

    if (leadBolehKelola()) {
      document.getElementById('btn-add-lead').addEventListener('click', leadOpenCreate);
    } else {
      document.getElementById('btn-add-lead').classList.add('hidden');
    }

    const renderDebounced = LP.debounce(leadRender);
    leadSearch.addEventListener('input', renderDebounced);
    leadStatusFilter.addEventListener('change', leadRender);
    leadSalesFilter.addEventListener('change', leadRender);

    document.getElementById('lead-table').addEventListener('click', (event) => {
      const tombol = event.target.closest('button[data-lead-action]');
      if (!tombol) return;
      const baris = leadView[Number(tombol.dataset.index)];
      if (baris) leadDelete(baris);
    });

    await leadLoad();
  });
</script>
@endpush