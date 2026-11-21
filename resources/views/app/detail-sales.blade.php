@extends('layouts.app')

@section('title', 'Detail Sales')
@section('page_title', 'Detail Data Sales')
@section('page_desc', 'Data diri sales, status akun login, capaian kinerja, riwayat pemesanan, serta reset kata sandi.')

@section('page_actions')
    <a href="/data-sales" class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        Kembali ke Data Sales
    </a>
@endsection

@section('content')
    <div id="sales-detail" class="space-y-4"></div>
@endsection

@push('scripts')
<script>
  const ROLE_PENGELOLA = ['super_admin', 'admin_sales'];
  const SALES_STATUS_LIST = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
    { value: 'resign', label: 'Resign' },
  ];

  const detailId = (location.pathname.split('/').filter(Boolean).pop() || '').replace(/[^0-9]/g, '');

  let detailSales = null;

  function profil() {
    return detailSales && (detailSales.sales_profile || detailSales.salesProfile) ? (detailSales.sales_profile || detailSales.salesProfile) : null;
  }

  function kode() {
    const p = profil();
    return p && p.sales_code ? p.sales_code : '-';
  }

  function tanggalAwal() {
    const hari = new Date();
    return hari.getFullYear() + '-' + String(hari.getMonth() + 1).padStart(2, '0') + '-01';
  }

  function tanggalAkhir() {
    const hari = new Date();
    return hari.getFullYear() + '-' + String(hari.getMonth() + 1).padStart(2, '0') + '-' + String(hari.getDate()).padStart(2, '0');
  }

  function controlClass() {
    return 'w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10';
  }

  function fieldHtml(name, label, value, meta) {
    const opsi = meta.type || 'text';
    let kontrol;

    if (opsi === 'select') {
      kontrol = '<select id="ds-' + name + '" class="' + controlClass() + '">'
        + meta.options.map((item) => '<option value="' + LP.esc(item.value) + '"'
          + (String(item.value) === String(value ?? '') ? ' selected' : '') + '>' + LP.esc(item.label) + '</option>').join('')
        + '</select>';
    } else if (opsi === 'textarea') {
      kontrol = '<textarea id="ds-' + name + '" rows="' + (meta.rows || 2) + '" class="' + controlClass() + '">' + LP.esc(value ?? '') + '</textarea>';
    } else {
      kontrol = '<input id="ds-' + name + '" type="' + opsi + '" class="' + controlClass() + '" value="' + LP.esc(value ?? '') + '"'
        + (meta.min !== undefined ? ' min="' + meta.min + '"' : '') + '>';
    }

    return '<label class="block ' + (meta.col === 2 ? 'sm:col-span-2' : '') + '">'
      + '<span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + '</span>'
      + kontrol
      + (meta.hint ? '<span class="mt-1 block text-xs text-zinc-500">' + LP.esc(meta.hint) + '</span>' : '')
      + '<span data-err="' + LP.esc(name) + '" class="mt-1 hidden text-xs font-medium text-rose-600"></span>'
      + '</label>';
  }

  function detailRow(label, value) {
    return '<div class="border-t border-zinc-100 py-2.5 first:border-t-0 sm:grid sm:grid-cols-3 sm:gap-4">'
      + '<dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + '</dt>'
      + '<dd class="mt-1 text-sm text-zinc-800 sm:col-span-2 sm:mt-0">' + value + '</dd></div>';
  }

  function detailIdentity(row) {
    const p = profil();
    const kosong = '<span class="text-zinc-500">Belum terisi</span>';

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-start justify-between gap-3">'
      + '<div><h2 class="text-lg font-semibold text-zinc-900">' + LP.esc(row.name) + '</h2>'
      + '<p class="mt-0.5 text-sm text-zinc-500">' + LP.esc(kode()) + ' &middot; ' + LP.esc(row.email) + '</p></div>'
      + '<div class="flex flex-wrap gap-2">' + LP.badge(p && p.status ? p.status : row.status)
      + (row.status === 'aktif' ? '' : '<span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">Akun ' + LP.esc(row.status) + '</span>')
      + '</div></div>'
      + '<dl class="mt-4">'
      + detailRow('Nomor Telepon', row.phone ? LP.esc(row.phone) : kosong)
      + detailRow('Nomor WhatsApp', p && p.whatsapp_number ? LP.esc(p.whatsapp_number) : kosong)
      + detailRow('Tanggal Lahir', p && p.birth_date ? LP.esc(LP.date(p.birth_date)) : kosong)
      + detailRow('Tanggal Bergabung', p && p.join_date ? LP.esc(LP.date(p.join_date)) : kosong)
      + detailRow('Alamat', row.address ? LP.esc(row.address) : kosong)
      + detailRow('Wajib Ganti Sandi', row.must_change_password
        ? '<span class="font-medium text-amber-700">Ya, kata sandi awal masih dipakai</span>'
        : '<span class="text-zinc-500">Tidak, sales sudah mengganti sandi sendiri</span>')
      + '</dl></section>';
  }

  function detailPhotoCard(row) {
    const gambar = row.avatar_url
      ? '<img src="' + LP.esc(row.avatar_url) + '" alt="Foto sales ' + LP.esc(row.name) + '" class="h-32 w-32 rounded-2xl object-cover">'
      : '<div class="grid h-32 w-32 place-items-center rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 text-center text-xs text-zinc-500">Belum ada foto sales</div>';

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Foto Sales</h2>'
      + '<div class="mt-3 flex items-start gap-4">'
      + gambar
      + '<div class="min-w-0">'
      + '<label class="block">'
      + '<span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Ganti Foto</span>'
      + '<input id="ds-avatar" type="file" accept="image/jpeg,image/png"'
      + ' class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-zinc-700">'
      + '<span class="mt-1 block text-xs text-zinc-500">Format JPG, JPEG, atau PNG. Ukuran maksimal 2 MB.</span>'
      + '</label>'
      + '<p class="mt-2 text-xs text-zinc-500">Foto yang disimpan di sini langsung tampil pada kartu narahubung sales di halaman publik, termasuk halaman Kontak dan detail unit.</p>'
      + '</div></div></section>';
  }

  function detailForm(row) {
    const p = profil() || {};

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-center justify-between gap-3">'
      + '<div><h2 class="text-base font-semibold text-zinc-900">Ubah Data Sales</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">Email login tidak diubah dari halaman ini. Gunakan Reset Kata Sandi untuk mengganti sandi.</p></div>'
      + '<button id="ds-save" type="button" class="rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60">Simpan Perubahan</button>'
      + '</div>'
      + '<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">'
      + fieldHtml('name', 'Nama Lengkap', row.name, { type: 'text' })
      + fieldHtml('sales_code', 'Kode Sales', p.sales_code || '', { type: 'text', hint: 'Kode unik maksimal 30 karakter.' })
      + fieldHtml('phone', 'Nomor Telepon', row.phone || '', { type: 'text' })
      + fieldHtml('whatsapp_number', 'Nomor WhatsApp', p.whatsapp_number || '', { type: 'text' })
      + fieldHtml('birth_date', 'Tanggal Lahir', p.birth_date ? LP.isoDate(p.birth_date) : '', { type: 'date', hint: 'Dipakai saat kata sandi dikembalikan ke tanggal lahir.' })
      + fieldHtml('join_date', 'Tanggal Bergabung', p.join_date ? LP.isoDate(p.join_date) : '', { type: 'date' })
      + fieldHtml('status', 'Status Sales', p.status || 'aktif', { type: 'select', options: SALES_STATUS_LIST, hint: 'Status kepegawaian, berbeda dengan status akun login.' })
      + fieldHtml('address', 'Alamat', row.address || '', { type: 'textarea', rows: 2, col: 2 })
      + '</div></section>';
  }

  function detailAccountCard(row) {
    const nonaktif = row.status !== 'aktif';

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Akses Akun</h2>'
      + '<p class="mt-2 text-sm text-zinc-600">Menonaktifkan akun membuat sales ini tidak dapat login, tetapi riwayat booking dan konsumennya tetap tersimpan.</p>'
      + '<div class="mt-4 grid gap-2">'
      + '<button id="ds-toggle" type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold '
      + (nonaktif
        ? 'bg-emerald-600 text-white hover:bg-emerald-500'
        : 'border border-amber-300 bg-white text-amber-800 hover:bg-amber-50') + '">'
      + (nonaktif ? 'Aktifkan Akun Login' : 'Nonaktifkan Akun Login') + '</button>'
      + '<button id="ds-password" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Reset Kata Sandi</button>'
      + '</div>'
      + '<p class="mt-3 text-xs text-zinc-500">Reset kata sandi mencabut seluruh sesi login sales ini. Bila dikembalikan ke tanggal lahir, format sandinya yyyymmdd dan wajib diganti saat login berikutnya dengan minimal 8 karakter serta huruf besar, huruf kecil, dan angka.</p>'
      + '</section>';
  }

  function detailPerformanceCard() {
    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-center justify-between gap-3">'
      + '<div><h2 class="text-base font-semibold text-zinc-900">Kinerja Sales</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">Capaian metrik dan target pada rentang tanggal terpilih.</p></div>'
      + '</div>'
      + '<div class="mt-4 grid gap-3 sm:grid-cols-3">'
      + '<label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal Mulai</span>'
      + '<input id="ds-perf-start" type="date" class="' + controlClass() + '" value="' + tanggalAwal() + '"></label>'
      + '<label class="block"><span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal Akhir</span>'
      + '<input id="ds-perf-end" type="date" class="' + controlClass() + '" value="' + tanggalAkhir() + '"></label>'
      + '<button id="ds-perf-muat" type="button" class="self-end rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60">Hitung Kinerja</button>'
      + '</div>'
      + '<div id="ds-perf-isi" class="mt-4"></div></section>';
  }

  function progressHtml(label, capaian, target, progress, format) {
    const pct = Math.max(0, Math.min(100, Number(progress || 0)));
    return '<div class="rounded-xl border border-zinc-200 bg-white p-4">'
      + '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + '</p>'
      + '<p class="mt-1 text-sm text-zinc-700">' + LP.esc(format(capaian)) + ' dari ' + LP.esc(format(target)) + '</p>'
      + '<div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-100">'
      + '<div class="h-full rounded-full bg-emerald-500" style="width:' + pct + '%"></div></div>'
      + '<p class="mt-1 text-xs font-medium text-zinc-500">' + LP.number(pct) + ' % tercapai</p></div>';
  }

  async function detailLoadPerformance() {
    const isi = document.getElementById('ds-perf-isi');
    const mulai = document.getElementById('ds-perf-start').value || tanggalAwal();
    const akhir = document.getElementById('ds-perf-end').value || tanggalAkhir();

    LP.loading(isi, 'Memuat data kinerja...');

    try {
      const data = await LP.fetchJson('/sales/' + detailId + '/performance?start_date=' + mulai + '&end_date=' + akhir);
      const metrik = data.metrics || {};
      const target = data.target;
      const periode = data.period || {};

      const kartu = [
        LP.statCard({ label: 'Total Konsumen', value: LP.number(metrik.total_leads), hint: 'Semua lead yang ditangani', tone: 'zinc' }),
        LP.statCard({ label: 'Konsumen Baru', value: LP.number(metrik.new_leads), hint: 'Terbuat pada periode', tone: 'sky' }),
        LP.statCard({ label: 'Follow Up', value: LP.number(metrik.total_follow_ups), hint: 'Catatan riwayat interaksi', tone: 'zinc' }),
        LP.statCard({ label: 'Booking', value: LP.number(metrik.bookings), hint: 'Pemesanan pada periode', tone: 'amber' }),
        LP.statCard({ label: 'Unit Terjual', value: LP.number(metrik.units_sold), hint: 'Booking berstatus lunas', tone: 'emerald' }),
        LP.statCard({ label: 'Omzet Booking', value: LP.money(metrik.booked_revenue), hint: 'Booking dengan pembayaran terverifikasi', tone: 'emerald' }),
        LP.statCard({ label: 'Pembayaran Terverifikasi', value: LP.money(metrik.verified_payments), hint: 'Nominal masuk pada periode', tone: 'sky' }),
      ].join('');

      const targetHtml = target
        ? '<div class="grid gap-3 sm:grid-cols-2">'
          + progressHtml('Progress Target Unit', target.achieved_units, target.target_units, target.unit_progress, (nilai) => LP.number(nilai) + ' unit')
          + progressHtml('Progress Target Nilai', target.achieved_amount, target.target_amount, target.amount_progress, (nilai) => LP.money(nilai))
          + '</div>'
        : '<p class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">Belum ada target sales pada periode ini. Tambahkan target saat membuat sales baru.</p>';

      isi.innerHTML = '<div class="space-y-4">'
        + '<p class="text-xs text-zinc-500">Periode analisis: ' + LP.esc(LP.date(periode.start_date)) + ' sampai ' + LP.esc(LP.date(periode.end_date)) + '</p>'
        + '<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">' + kartu + '</div>'
        + targetHtml + '</div>';
    } catch (error) {
      LP.errorBox(isi, error.message || 'Data kinerja sales gagal dimuat. Pastikan sales ini sudah memiliki profil sales.');
    }
  }

  function detailBookings(row) {
    const p = profil();
    const daftar = p && Array.isArray(p.bookings) ? p.bookings : [];

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-base font-semibold text-zinc-900">Riwayat Pemesanan</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">' + daftar.length + ' booking tercatat atas nama sales ini.</p>'
      + (daftar.length
        ? '<div id="ds-booking-table" class="mt-4"></div>'
        : '<p class="mt-4 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-6 text-center text-sm text-zinc-500">Belum ada pemesanan yang dicatat sales ini.</p>')
      + '</section>';
  }

  function detailRenderBookings() {
    const p = profil();
    const daftar = p && Array.isArray(p.bookings) ? p.bookings : [];
    if (!daftar.length) return;

    LP.table({
      mount: 'ds-booking-table',
      rows: daftar,
      empty: 'Belum ada pemesanan.',
      columns: [
        { label: 'Kode Booking', render: (item) => '<a class="font-medium text-brand-600 hover:underline" href="/booking">' + LP.esc(item.booking_code) + '</a>' },
        { label: 'Tanggal', render: (item) => LP.date(item.booking_date) },
        { label: 'Unit', render: (item) => (item.unit ? LP.esc(item.unit.unit_code + ' — ' + item.unit.unit_type) : '-') },
        { label: 'Total Harga', align: 'right', render: (item) => LP.money(item.total_price) },
        { label: 'Status', render: (item) => LP.badge(item.status) },
        { label: 'Serah Terima', render: (item) => LP.badge(item.delivery_status) },
      ],
    });
  }

  function detailShowErrors(errors) {
    document.querySelectorAll('#sales-detail [data-err]').forEach((slot) => {
      slot.classList.add('hidden');
      slot.textContent = '';
    });
    Object.entries(errors || {}).forEach(([key, messages]) => {
      const slot = document.querySelector('#sales-detail [data-err="' + key + '"]');
      if (slot) {
        slot.textContent = messages.join(', ');
        slot.classList.remove('hidden');
      }
    });
  }

  async function detailSave() {
    const nilai = (key) => {
      const input = document.getElementById('ds-' + key);
      return input ? String(input.value).trim() : '';
    };

    const body = {
      name: nilai('name'),
      phone: nilai('phone'),
      address: nilai('address'),
      status: nilai('status'),
      sales_code: nilai('sales_code'),
    };
    if (nilai('whatsapp_number')) body.whatsapp_number = nilai('whatsapp_number');
    if (nilai('join_date')) body.join_date = nilai('join_date');
    if (nilai('birth_date')) body.birth_date = nilai('birth_date');

    if (!body.name) throw new Error('Nama sales wajib diisi.');
    if (!body.sales_code) throw new Error('Kode sales wajib diisi.');

    detailShowErrors({});

    const berkas = document.getElementById('ds-avatar');
    const foto = berkas && berkas.files && berkas.files[0] ? berkas.files[0] : null;

    let hasil;
    if (foto) {
      const muatan = new FormData();
      muatan.append('_method', 'PUT');
      Object.keys(body).forEach((key) => muatan.append(key, body[key]));
      muatan.append('avatar', foto);
      hasil = await LP.http.post('/sales/' + detailId, muatan);
    } else {
      hasil = await LP.http.put('/sales/' + detailId, body);
    }

    LP.toast((hasil.json && hasil.json.message) || 'Data sales berhasil diperbarui.');
    await detailLoad();
  }

  async function detailToggle() {
    const akanNonaktif = detailSales.status === 'aktif';
    const setuju = await LP.confirm(
      akanNonaktif
        ? 'Akun login ' + detailSales.name + ' akan dinonaktifkan dan tidak dapat masuk ke sistem lagi.'
        : 'Akun login ' + detailSales.name + ' akan diaktifkan kembali sehingga yang bersangkutan bisa masuk seperti semula.',
      {
        title: akanNonaktif ? 'Nonaktifkan Akun' : 'Aktifkan Akun',
        okLabel: akanNonaktif ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
        danger: akanNonaktif,
      },
    );
    if (!setuju) return;

    try {
      const hasil = await LP.http.put('/sales/' + detailId, { account_status: akanNonaktif ? 'nonaktif' : 'aktif' });
      LP.toast((hasil.json && hasil.json.message) || 'Status akun sales diperbarui.');
    } catch (error) {
      LP.toast(error.message || 'Status akun sales gagal diperbarui.', 'error');
      return;
    }
    await detailLoad();
  }

  function detailOpenPassword() {
    const p = profil();
    const tanggalLahir = p && p.birth_date ? LP.isoDate(p.birth_date) : '';
    const sandiLahir = tanggalLahir ? tanggalLahir.replace(/-/g, '') : '';

    LP.formModal({
      title: 'Manajemen Kata Sandi — ' + detailSales.name,
      description: 'Opsi 1 mengembalikan sandi ke tanggal lahir (yyyymmdd). Opsi 2 mengisi kata sandi baru secara manual.',
      fields: [
        {
          name: 'metode', label: 'Cara Reset', type: 'select', required: true, col: 2,
          options: [
            { value: 'tanggal_lahir', label: sandiLahir ? 'Opsi 1 — Kembalikan ke tanggal lahir (' + sandiLahir + ')' : 'Opsi 1 — Kembalikan ke tanggal lahir (belum bisa dipakai)' },
            { value: 'manual', label: 'Opsi 2 — Isi kata sandi baru' },
          ],
        },
        { name: 'password', label: 'Kata Sandi Baru', type: 'password', col: 2, hint: 'Minimal 6 karakter. Sales tetap wajib menggantinya pada login pertama. Kosongkan bila memakai Opsi 1.' },
        { name: 'konfirmasi', label: 'Konfirmasi Kata Sandi', type: 'password', col: 2 },
      ],
      values: { metode: sandiLahir ? 'tanggal_lahir' : 'manual' },
      submitLabel: 'Reset Kata Sandi',
      width: 'max-w-lg',
      onSubmit: async (values, context) => {
        const body = {};

        if (values.metode === 'manual' || !values.metode) {
          if (!values.password) throw new Error('Isi kata sandi baru untuk opsi manual.');
          if (values.password !== values.konfirmasi) throw new Error('Konfirmasi kata sandi tidak sama.');
          body.password = values.password;
        } else if (!tanggalLahir) {
          throw new Error('Tanggal lahir sales belum terisi. Simpan tanggal lahir lebih dulu, atau pakai opsi manual.');
        }

        const hasil = await LP.http.post('/sales/' + detailId + '/reset-password', body);
        context.close();

        const kataSandiAwal = hasil.json && hasil.json.auth ? hasil.json.auth.password_default : null;
        if (kataSandiAwal) {
          await LP.confirm(
            'Kata sandi ' + detailSales.name + ' dikembalikan menjadi ' + kataSandiAwal + ' (format ddmmyyyy) dan wajib diganti saat login berikutnya.',
            { title: 'Kata Sandi Disetel Ulang', okLabel: 'Sudah Saya Catat' },
          );
        } else {
          LP.toast((hasil.json && hasil.json.message) || 'Kata sandi sales berhasil direset.');
        }
        await detailLoad();
      },
    });
  }

  function detailRender(row) {
    detailSales = row;

    const judul = document.querySelector('main h1');
    if (judul) judul.textContent = 'Detail Sales — ' + row.name;
    document.title = 'Detail Sales ' + row.name + ' · Lumin Park';

    document.getElementById('sales-detail').innerHTML =
      '<div class="grid gap-4 lg:grid-cols-3">'
      + '<div class="space-y-4 lg:col-span-2">' + detailForm(row) + detailPerformanceCard() + '</div>'
      + '<div class="space-y-4">' + detailPhotoCard(row) + detailIdentity(row) + detailAccountCard(row) + '</div></div>'
      + detailBookings(row);

    detailRenderBookings();

    document.getElementById('ds-save').addEventListener('click', (event) => {
      LP.withBusy(event.currentTarget, async () => {
        try {
          await detailSave();
        } catch (error) {
          if (error && error.errors) detailShowErrors(error.errors);
          LP.toast(error.message || 'Data sales gagal disimpan.', 'error');
        }
      });
    });

    document.getElementById('ds-toggle').addEventListener('click', (event) => {
      LP.withBusy(event.currentTarget, detailToggle);
    });
    document.getElementById('ds-password').addEventListener('click', detailOpenPassword);
    document.getElementById('ds-perf-muat').addEventListener('click', (event) => {
      LP.withBusy(event.currentTarget, detailLoadPerformance);
    });

    detailLoadPerformance();
  }

  async function detailLoad() {
    LP.loading('sales-detail', 'Memuat detail sales...');

    try {
      const data = await LP.fetchJson('/sales/' + detailId);
      if (!data || !data.id) {
        LP.errorBox('sales-detail', 'Data sales tidak ditemukan pada sistem.');
        return;
      }
      detailRender(data);
    } catch (error) {
      LP.errorBox('sales-detail', error.status === 404
        ? 'Sales tidak ditemukan. Bisa jadi sudah dihapus dari daftar sales.'
        : (error.message || 'Detail sales gagal dimuat.'));
    }
  }

  LP.guard(ROLE_PENGELOLA, async () => {
    if (!detailId) {
      LP.errorBox('sales-detail', 'Alamat halaman tidak menyertakan nomor id akun sales.');
      return;
    }
    await detailLoad();
  });
</script>
@endpush
