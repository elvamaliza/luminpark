@extends('layouts.app')

@section('title', 'Data Unit Perumahan')
@section('page_title', 'Data Unit Perumahan')
@section('page_desc', 'Telusuri dan saring unit; buka Detail untuk mengubah informasi unit. Tambah dan hapus unit hanya milik Manajer.')

@section('page_actions')
    <button id="btn-add-unit" type="button" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 disabled:opacity-60">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah Unit
    </button>
@endsection

@section('content')
    <!-- Kotak Statistik diperbarui agar persis seperti gambar referensi -->
    <div id="unit-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Cari Unit</span>
                <input id="unit-search" type="search" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10" placeholder="kode unit, tipe, atau cluster">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status Unit</span>
                <select id="unit-status" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Status</option>
                    <option value="available">Tersedia</option>
                    <option value="booking">Booking</option>
                    <option value="sold_out">Terjual</option>
                    <option value="delivery_process">Proses Serah Terima</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Cluster</span>
                <select id="unit-cluster" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Cluster</option>
                </select>
            </label>
            <p id="unit-count" class="self-end text-sm text-zinc-500">Memuat data unit...</p>
        </div>
    </section>

    <div id="unit-table"></div>

    <p class="mt-4 text-xs text-zinc-500">
        Status unit berubah otomatis oleh sistem: menjadi Booking saat pemesanan dibuat dan Terjual saat pelunasan terverifikasi.
        Foto unit digunakan pada halaman publik daftar unit.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_PENGELOLA = ['manager', 'admin_sales'];

  const UNIT_STATUS_LIST = [
    { value: 'available', label: 'Tersedia' },
    { value: 'booking', label: 'Booking' },
    { value: 'sold_out', label: 'Terjual' },
    { value: 'delivery_process', label: 'Proses Serah Terima' },
  ];

  const unitSearch = document.getElementById('unit-search');
  const unitStatusFilter = document.getElementById('unit-status');
  const unitClusterFilter = document.getElementById('unit-cluster');

  let unitAll = [];
  let unitClusters = [];
  let unitView = [];

  function unitClusterName(row) {
    return row && row.cluster ? row.cluster.name : '-';
  }

  function unitFiltered() {
    const kata = (unitSearch.value || '').trim().toLowerCase();
    const status = unitStatusFilter.value;
    const clusterId = unitClusterFilter.value;

    return unitAll.filter((row) => {
      const haystack = [row.unit_code, row.unit_type, unitClusterName(row)].join(' ').toLowerCase();
      if (kata && haystack.indexOf(kata) === -1) return false;
      if (status && row.status !== status) return false;
      if (clusterId && String(row.cluster_id) !== clusterId) return false;
      return true;
    });
  }

  // FUNGSI INI YANG MENGATUR KOTAK STATISTIK AGAR PERSIS SEPERTI GAMBAR REFERENSI ANDA
  function unitStats() {
    const host = document.getElementById('unit-stats');
    const hitung = (status) => unitAll.filter((row) => row.status === status).length;

    const items = [
      { label: 'Total Unit', value: LP.number(unitAll.length), hint: 'Seluruh unit terdaftar', tone: 'zinc' },
      { label: 'Tersedia', value: LP.number(hitung('available')), hint: 'Siap dibooking', tone: 'emerald' },
      { label: 'Sedang Booking', value: LP.number(hitung('booking')), hint: 'Menunggu pembayaran', tone: 'amber' },
      { label: 'Terjual', value: LP.number(hitung('sold_out') + hitung('delivery_process')), hint: 'Termasuk proses serah terima', tone: 'sky' },
    ];

    host.innerHTML = '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px;">'
      + items.map((item) => {
          let toneClass = 'border-slate-200 bg-white';
          if (item.tone === 'emerald') toneClass = 'border-emerald-200 bg-emerald-50/30';
          if (item.tone === 'sky') toneClass = 'border-sky-200 bg-sky-50/30';
          if (item.tone === 'amber') toneClass = 'border-amber-200 bg-amber-50/30';

          return `<div style="padding: 14px 18px; border-radius: 12px; min-height: 85px; display: flex; flex-direction: column; justify-content: space-between;" class="border ${toneClass} shadow-sm">
            <p style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin-bottom: 2px;">${LP.esc(item.label)}</p>
            <p style="font-size: 22px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 2px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 11px; color: #64748b; margin-top: 4px;">${LP.esc(item.hint)}</p>` : ''}
          </div>`;
      }).join('') + '</div>';
  }

  function unitRender() {
    unitView = unitFiltered();

    document.getElementById('unit-count').textContent =
      unitView.length + ' unit ditampilkan dari ' + unitAll.length + ' unit keseluruhan.';

    LP.table({
      mount: 'unit-table',
      rows: unitView,
      empty: 'Tidak ada unit yang cocok dengan kata kunci atau filter status.',
      columns: [
        { label: 'No.', render: (row, index) => String(index + 1) },
        {
          label: 'Blok / Kode Unit',
          render: (row) => '<span class="font-semibold text-zinc-900">' + LP.esc(row.unit_code) + '</span>',
        },
        { label: 'Cluster', render: (row) => LP.esc(unitClusterName(row)) },
        { label: 'Tipe Unit', render: (row) => LP.esc(row.unit_type) },
        { label: 'Luas Tanah', align: 'right', render: (row) => LP.number(row.land_size) + ' m2' },
        { label: 'Luas Bangunan', align: 'right', render: (row) => LP.number(row.building_size) + ' m2' },
        { label: 'Harga', align: 'right', render: (row) => LP.money(row.price) },
        { label: 'Status', render: (row) => LP.badge(row.status) },
        {
          label: 'Aksi',
          render: (row, index) => '<div class="flex flex-wrap gap-2">'
            + '<a href="/data-unit/' + LP.esc(row.id) + '" class="rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">Detail</a>'
            + (LP.can(['manager'])
              ? '<button type="button" data-unit-action="delete" data-index="' + index + '"'
                + ' class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Hapus</button>'
              : '')
            + '</div>',
        },
      ],
    });
  }

  function unitClusterOptions() {
    return unitClusters.map((item) => ({ value: item.id, label: item.name }));
  }

  function unitFields() {
    return [
      { name: 'cluster_id', label: 'Cluster', type: 'select', required: true, options: unitClusterOptions(), placeholder: 'Pilih cluster', col: 2 },
      { name: 'unit_code', label: 'Kode Unit / Blok', type: 'text', required: true, placeholder: 'LT-011', hint: 'Kode unik, maksimal 30 karakter.' },
      { name: 'unit_type', label: 'Tipe Unit', type: 'text', required: true, placeholder: 'Modern Tropical 60' },
      { name: 'land_size', label: 'Luas Tanah (m2)', type: 'number', required: true, min: 1, step: 'any' },
      { name: 'building_size', label: 'Luas Bangunan (m2)', type: 'number', required: true, min: 1, step: 'any' },
      { name: 'price', label: 'Harga Unit (Rp)', type: 'number', required: true, min: 0, step: 'any' },
      { name: 'status', label: 'Status Unit', type: 'select', required: true, options: UNIT_STATUS_LIST },
      { name: 'description', label: 'Deskripsi Unit', type: 'textarea', rows: 3, col: 2, placeholder: 'Contoh: 3 kamar tidur, hadap taman cluster.' },
      { name: 'image', label: 'Foto Unit', type: 'file', accept: 'image/*', col: 2, hint: 'Format JPG, JPEG, atau PNG. Ukuran maksimal 2 MB.' },
    ];
  }

  function unitValues(row) {
    if (!row) return { status: 'available' };
    return {
      cluster_id: row.cluster_id,
      unit_code: row.unit_code,
      unit_type: row.unit_type,
      land_size: row.land_size,
      building_size: row.building_size,
      price: Math.round(Number(row.price || 0)),
      status: row.status,
      description: row.description || '',
    };
  }

  function unitPayload(values) {
    return {
      cluster_id: Number(values.cluster_id),
      unit_code: (values.unit_code || '').trim(),
      unit_type: (values.unit_type || '').trim(),
      land_size: Number(values.land_size),
      building_size: Number(values.building_size),
      price: Number(values.price),
      status: values.status,
      description: values.description,
    };
  }

  async function unitSave(values, context, original) {
    const payload = unitPayload(values);
    const foto = values.__files ? values.__files.image : null;

    if (original && payload.unit_code !== original.unit_code) {
      const bentrok = unitAll.some((row) => row.unit_code === payload.unit_code);
      if (bentrok) throw new Error('Kode unit sudah dipakai unit lain.');
    }

    let hasil;
    if (original) {
      if (foto) {
        const body = new FormData();
        body.append('_method', 'PUT');
        Object.keys(payload).forEach((key) => body.append(key, payload[key]));
        body.append('image', foto);
        hasil = await LP.http.post('/units/' + original.id, body);
      } else {
        hasil = await LP.http.put('/units/' + original.id, payload);
      }
    } else {
      const body = new FormData();
      Object.keys(payload).forEach((key) => body.append(key, payload[key]));
      if (foto) body.append('image', foto);
      hasil = await LP.http.post('/units', body);
    }

    LP.toast((hasil.json && hasil.json.message) || 'Data unit berhasil disimpan.');
    context.close();
    await unitLoad();
  }

  function unitOpenForm(original) {
    if (!unitClusters.length) {
      LP.toast('Daftar cluster belum tersedia. Tambahkan cluster lewat Manajer.', 'error');
      return;
    }

    LP.formModal({
      title: original ? 'Edit Unit ' + original.unit_code : 'Tambah Unit Baru',
      description: original
        ? 'Perbarui informasi unit. Foto hanya diganti bila Anda memilih berkas baru.'
        : 'Isi data unit rumah yang akan dipasarkan.',
      fields: unitFields(),
      values: unitValues(original),
      submitLabel: original ? 'Simpan Perubahan' : 'Simpan Unit',
      width: 'max-w-2xl',
      onSubmit: (values, context) => unitSave(values, context, original),
    });
  }

  async function unitDelete(row) {
    const setuju = await LP.confirm(
      'Unit ' + row.unit_code + ' (' + row.unit_type + ') akan dikeluarkan dari daftar unit. Riwayatnya tetap tersimpan di sistem dan dapat dipulihkan administrator basis data.',
      { title: 'Hapus Unit', okLabel: 'Ya, Hapus', danger: true },
    );
    if (!setuju) return;

    try {
      const hasil = await LP.http.del('/units/' + row.id);
      LP.toast((hasil.json && hasil.json.message) || 'Data unit berhasil dihapus.');
    } catch (error) {
      LP.toast(error.message || 'Unit gagal dihapus.', 'error');
      return;
    }
    await unitLoad();
  }

  function unitPaintClusterFilter() {
    const selected = unitClusterFilter.value;
    unitClusterFilter.innerHTML = '<option value="">Semua Cluster</option>'
      + unitClusters.map((item) => '<option value="' + LP.esc(item.id) + '">' + LP.esc(item.name) + '</option>').join('');
    unitClusterFilter.value = selected;
  }

  async function unitLoad() {
    LP.loading('unit-table', 'Memuat data unit...');
    document.getElementById('unit-count').textContent = 'Memuat data unit...';

    try {
      const [daftar, clusters] = await Promise.all([
        LP.fetchJson('/units'),
        LP.fetchJson('/units/clusters'),
      ]);
      unitAll = Array.isArray(daftar) ? daftar : [];
      unitClusters = Array.isArray(clusters) ? clusters : [];
    } catch (error) {
      LP.errorBox('unit-table', error.message || 'Data unit gagal dimuat periksa koneksi atau hak akses Anda.');
      document.getElementById('unit-count').textContent = 'Data gagal dimuat.';
      return;
    }

    unitPaintClusterFilter();
    unitStats();
    unitRender();
  }

  LP.guard(ROLE_PENGELOLA, async () => {
    if (!LP.can(['manager'])) {
      document.getElementById('btn-add-unit').classList.add('hidden');
    } else {
      document.getElementById('btn-add-unit').addEventListener('click', () => unitOpenForm(null));
    }

    const renderDebounced = LP.debounce(unitRender);
    unitSearch.addEventListener('input', renderDebounced);
    unitStatusFilter.addEventListener('change', unitRender);
    unitClusterFilter.addEventListener('change', unitRender);

    document.getElementById('unit-table').addEventListener('click', (event) => {
      const tombol = event.target.closest('button[data-unit-action]');
      if (!tombol) return;
      const baris = unitView[Number(tombol.dataset.index)];
      if (!baris) return;
      unitDelete(baris);
    });

    await unitLoad();
  });
</script>
@endpush