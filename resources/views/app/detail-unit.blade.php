@extends('layouts.app')

@section('title', 'Detail Unit')
@section('page_title', 'Detail Unit Perumahan')
@section('page_desc', 'Informasi lengkap satu unit rumah beserta riwayat pemesanannya. Perubahan data dilakukan langsung pada halaman ini.')

@section('page_actions')
    <a href="/data-unit" class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        Kembali ke Data Unit
    </a>
@endsection

@section('content')
    <div id="unit-detail" class="space-y-4"></div>
@endsection

@push('scripts')
<script>
  const UNIT_STATUS_LIST = [
    { value: 'available', label: 'Tersedia' },
    { value: 'booking', label: 'Booking' },
    { value: 'sold_out', label: 'Terjual' },
    { value: 'delivery_process', label: 'Proses Serah Terima' },
  ];

  const detailId = (location.pathname.split('/').filter(Boolean).pop() || '').replace(/[^0-9]/g, '');

  let detailUnit = null;
  let detailClusters = [];
  let detailBookings = [];

  function bolehUbah() {
    return LP.can(['manager']);
  }

  function controlClass() {
    return 'w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10 disabled:bg-zinc-100 disabled:text-zinc-500';
  }

  function fieldHtml(name, label, value, meta) {
    const mati = meta.locked ? ' disabled' : '';
    let kontrol;

    if (meta.type === 'select') {
      kontrol = '<select id="du-' + name + '" class="' + controlClass() + '"' + mati + '>'
        + meta.options.map((item) => '<option value="' + LP.esc(item.value) + '"'
          + (String(item.value) === String(value ?? '') ? ' selected' : '') + '>' + LP.esc(item.label) + '</option>').join('')
        + '</select>';
    } else if (meta.type === 'textarea') {
      kontrol = '<textarea id="du-' + name + '" rows="' + (meta.rows || 3) + '" class="' + controlClass() + '"' + mati
        + ' placeholder="' + LP.esc(meta.placeholder || '') + '">' + LP.esc(value ?? '') + '</textarea>';
    } else {
      kontrol = '<input id="du-' + name + '" type="' + meta.type + '" class="' + controlClass() + '" value="' + LP.esc(value ?? '') + '"'
        + (meta.min !== undefined ? ' min="' + meta.min + '"' : '')
        + (meta.step !== undefined ? ' step="' + meta.step + '"' : '')
        + mati + '>';
    }

    return '<label class="block ' + (meta.col === 2 ? 'sm:col-span-2' : '') + '">'
      + '<span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + '</span>'
      + kontrol
      + (meta.hint ? '<span class="mt-1 block text-xs text-zinc-500">' + LP.esc(meta.hint) + '</span>' : '')
      + '<span data-err="' + LP.esc(name) + '" class="mt-1 hidden text-xs font-medium text-rose-600"></span>'
      + '</label>';
  }

  function detailSummary(row) {
    const cluster = row.cluster || {};
    const proyek = cluster.project || {};

    return '<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-start justify-between gap-3">'
      + '<div><h2 class="text-xl font-semibold text-zinc-900">' + LP.esc(row.unit_code) + '</h2>'
      + '<p class="mt-0.5 text-sm text-zinc-500">' + LP.esc(row.unit_type) + ' &middot; ' + LP.esc(cluster.name || 'Tanpa cluster') + '</p></div>'
      + '<div>' + LP.badge(row.status) + '</div></div>'
      + '<p class="mt-4 text-2xl font-semibold text-zinc-900">' + LP.money(row.price) + '</p>'
      + '<dl class="mt-4 grid gap-3 sm:grid-cols-2">'
      + '<div class="rounded-xl bg-zinc-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Luas Tanah</dt>'
      + '<dd class="mt-1 text-sm font-medium text-zinc-800">' + LP.number(row.land_size) + ' m2</dd></div>'
      + '<div class="rounded-xl bg-zinc-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Luas Bangunan</dt>'
      + '<dd class="mt-1 text-sm font-medium text-zinc-800">' + LP.number(row.building_size) + ' m2</dd></div>'
      + '<div class="rounded-xl bg-zinc-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Cluster</dt>'
      + '<dd class="mt-1 text-sm font-medium text-zinc-800">' + LP.esc(cluster.name || '-') + '</dd></div>'
      + '<div class="rounded-xl bg-zinc-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Proyek</dt>'
      + '<dd class="mt-1 text-sm font-medium text-zinc-800">' + LP.esc(proyek.name || '-') + '</dd></div>'
      + '</dl>'
      + '<p class="mt-4 text-sm text-zinc-700">' + (row.description
        ? LP.esc(row.description)
        : '<span class="text-zinc-500">Belum ada deskripsi unit.</span>') + '</p>'
      + '<p class="mt-4 text-xs text-zinc-500">Unit ditambahkan pada ' + LP.esc(LP.datetime(row.created_at)) + '.</p>'
      + '</div>';
  }

  function detailPhoto(row) {
    const gambar = row.image_url
      ? '<img src="' + LP.esc(row.image_url) + '" alt="Foto unit ' + LP.esc(row.unit_code) + '" class="h-56 w-full rounded-xl object-cover">'
      : '<div class="flex h-56 w-full items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-zinc-50 text-sm text-zinc-500">Unit ini belum punya foto.</div>';

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Foto Unit</h2>'
      + '<div class="mt-3">' + gambar + '</div>'
      + '<label class="mt-4 block">'
      + '<span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Ganti Foto</span>'
      + '<input id="du-image" type="file" accept="image/jpeg,image/png"'
      + (bolehUbah() ? '' : ' disabled')
      + ' class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-zinc-700 disabled:bg-zinc-100 disabled:text-zinc-500">'
      + '<span class="mt-1 block text-xs text-zinc-500">Format JPG, JPEG, atau PNG. Ukuran maksimal 2 MB.</span>'
      + '</label>'
      + (bolehUbah()
        ? ''
        : '<p class="mt-3 rounded-xl bg-zinc-50 p-3 text-xs text-zinc-600">Halaman ini hanya dibaca. Hanya Manajer yang dapat mengubah data unit.</p>')
      + '</section>';
  }

  function detailForm(row) {
    const locked = !bolehUbah();
    const meta = (extra) => Object.assign({ locked: locked }, extra || {});
    const clusterOptions = detailClusters.length
      ? detailClusters.map((item) => ({ value: item.id, label: item.name }))
      : [{ value: row.cluster_id, label: (row.cluster && row.cluster.name) || 'Cluster unit ini' }];

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-center justify-between gap-3">'
      + '<div><h2 class="text-base font-semibold text-zinc-900">Ubah Data Unit</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">Perubahan langsung tersimpan ke data master dan tampil pada halaman publik daftar unit.</p></div>'
      + (locked ? '' : '<button id="du-save" type="button" class="rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60">Simpan Perubahan</button>')
      + '</div>'
      + '<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">'
      + fieldHtml('cluster_id', 'Cluster', row.cluster_id, meta({ type: 'select', options: clusterOptions, col: 2 }))
      + fieldHtml('unit_code', 'Kode Unit / Blok', row.unit_code, meta({ type: 'text' }))
      + fieldHtml('unit_type', 'Tipe Unit', row.unit_type, meta({ type: 'text' }))
      + fieldHtml('land_size', 'Luas Tanah (m2)', row.land_size, meta({ type: 'number', min: 1, step: '1' }))
      + fieldHtml('building_size', 'Luas Bangunan (m2)', row.building_size, meta({ type: 'number', min: 1, step: '1' }))
      + fieldHtml('price', 'Harga Unit (Rp)', Math.round(Number(row.price || 0)), meta({ type: 'number', min: 0, step: '1' }))
      + fieldHtml('status', 'Status Unit', row.status, meta({ type: 'select', options: UNIT_STATUS_LIST, hint: 'Status berubah otomatis saat booking dibuat dan pelunasan terverifikasi.' }))
      + fieldHtml('description', 'Deskripsi Unit', row.description || '', meta({ type: 'textarea', col: 2 }))
      + '</div></section>';
  }

  function detailBookingSection() {
    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-base font-semibold text-zinc-900">Riwayat Pemesanan</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">'
      + (detailBookings.length
        ? detailBookings.length + ' pemesanan tercatat untuk unit ini.'
        : 'Belum ada pemesanan untuk unit ini. Unit masih siap dibooking.')
      + '</p><div id="du-booking-table" class="mt-4"></div></section>';
  }

  function detailRenderBookings() {
    if (!detailBookings.length) {
      document.getElementById('du-booking-table').innerHTML = '';
      return;
    }

    LP.table({
      mount: 'du-booking-table',
      rows: detailBookings,
      empty: 'Belum ada pemesanan.',
      columns: [
        { label: 'Kode Booking', render: (row) => '<a class="font-medium text-brand-600 hover:underline" href="/booking">' + LP.esc(row.booking_code) + '</a>' },
        { label: 'Tanggal', render: (row) => LP.date(row.booking_date) },
        { label: 'Konsumen', render: (row) => LP.esc((row.customer_lead && row.customer_lead.name) || '-') },
        { label: 'Sales', render: (row) => LP.esc((row.sales_profile && row.sales_profile.user && row.sales_profile.user.name) || '-') },
        { label: 'Skema', render: (row) => LP.esc((row.payment_scheme && row.payment_scheme.scheme_name) || '-') },
        { label: 'Total Harga', align: 'right', render: (row) => LP.money(row.total_price) },
        { label: 'Status', render: (row) => LP.badge(row.status) },
        { label: 'Serah Terima', render: (row) => LP.badge(row.delivery_status) },
      ],
    });
  }

  function detailCollect() {
    const nilai = {};
    ['cluster_id', 'unit_code', 'unit_type', 'land_size', 'building_size', 'price', 'status', 'description']
      .forEach((key) => {
        const input = document.getElementById('du-' + key);
        nilai[key] = input ? input.value : '';
      });
    return nilai;
  }

  function detailShowErrors(errors) {
    document.querySelectorAll('#unit-detail [data-err]').forEach((slot) => {
      slot.classList.add('hidden');
      slot.textContent = '';
    });
    Object.entries(errors || {}).forEach(([key, messages]) => {
      const slot = document.querySelector('#unit-detail [data-err="' + key + '"]');
      if (slot) {
        slot.textContent = messages.join(', ');
        slot.classList.remove('hidden');
      }
    });
  }

  async function detailSave() {
    const nilai = detailCollect();
    const berkas = document.getElementById('du-image');
    const foto = berkas && berkas.files && berkas.files[0] ? berkas.files[0] : null;

    const payload = {
      cluster_id: Number(nilai.cluster_id),
      unit_code: String(nilai.unit_code || '').trim(),
      unit_type: String(nilai.unit_type || '').trim(),
      land_size: Number(nilai.land_size),
      building_size: Number(nilai.building_size),
      price: Number(nilai.price),
      status: nilai.status,
      description: nilai.description,
    };

    if (!payload.unit_code || !payload.unit_type) throw new Error('Kode unit dan tipe unit wajib diisi.');
    if (!(payload.land_size > 0) || !(payload.building_size > 0)) throw new Error('Luas tanah dan luas bangunan harus lebih dari nol.');
    if (!Number.isFinite(payload.price) || payload.price < 0) throw new Error('Harga unit tidak valid.');

    detailShowErrors({});

    let hasil;
    if (foto) {
      const body = new FormData();
      body.append('_method', 'PUT');
      Object.keys(payload).forEach((key) => body.append(key, payload[key]));
      body.append('image', foto);
      hasil = await LP.http.post('/units/' + detailUnit.id, body);
    } else {
      hasil = await LP.http.put('/units/' + detailUnit.id, payload);
    }

    LP.toast((hasil.json && hasil.json.message) || 'Data unit berhasil diperbarui.');
    await detailLoad();
  }

  async function detailReloadBookings() {
    try {
      const data = await LP.fetchJson('/bookings?unit_id=' + detailId);
      detailBookings = Array.isArray(data) ? data : (data && Array.isArray(data.data) ? data.data : []);
    } catch (error) {
      detailBookings = [];
      LP.toast('Riwayat pemesanan unit gagal dimuat: ' + (error.message || 'periksa koneksi Anda.'), 'error');
    }
  }

  function detailRender(row) {
    detailUnit = row;

    const judul = document.querySelector('main h1');
    if (judul) judul.textContent = 'Detail Unit ' + row.unit_code;
    document.title = 'Detail Unit ' + row.unit_code + ' · Lumin Park';

    const form = detailForm(row);
    const sidebar = detailSummary(row) + detailPhoto(row);

    document.getElementById('unit-detail').innerHTML =
      '<div class="grid gap-4 lg:grid-cols-3">'
      + '<div class="lg:col-span-2">' + form + '</div>'
      + '<div>' + sidebar + '</div></div>'
      + detailBookingSection();
    detailRenderBookings();

    const simpan = document.getElementById('du-save');
    if (simpan) {
      simpan.addEventListener('click', (event) => {
        LP.withBusy(event.currentTarget, async () => {
          try {
            await detailSave();
          } catch (error) {
            if (error && error.errors) detailShowErrors(error.errors);
            LP.toast(error.message || 'Data unit gagal disimpan.', 'error');
          }
        });
      });
    }
  }

  async function detailLoad() {
    LP.loading('unit-detail', 'Memuat detail unit...');

    let unit;
    try {
      unit = await LP.fetchJson('/units/' + detailId);
    } catch (error) {
      LP.errorBox('unit-detail', error.status === 404
        ? 'Unit tidak ditemukan. Bisa jadi sudah dihapus dari daftar unit.'
        : (error.message || 'Detail unit gagal dimuat.'));
      return;
    }

    if (!unit || !unit.id) {
      LP.errorBox('unit-detail', 'Unit tidak ditemukan pada sistem.');
      return;
    }

    await detailReloadBookings();
    detailRender(unit);
  }

  LP.guard(['manager', 'admin_sales'], async () => {
    if (!detailId) {
      LP.errorBox('unit-detail', 'Alamat halaman tidak menyertakan nomor id unit.');
      return;
    }

    if (bolehUbah()) {
      try {
        const clusters = await LP.fetchJson('/units/clusters');
        detailClusters = Array.isArray(clusters) ? clusters : [];
      } catch (error) {
        LP.toast('Daftar cluster gagal dimuat: ' + (error.message || 'periksa koneksi Anda.'), 'error');
      }
    }

    await detailLoad();
  });
</script>
@endpush
