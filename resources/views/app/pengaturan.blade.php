@extends('layouts.app')

@section('title', 'Pengaturan')

@section('page_title', 'Pengaturan')

@section('page_desc', 'Informasi dasar sistem, identitas perumahan, parameter penjualan, dan integrasi login Google.')

@section('page_actions')
<div class="flex flex-wrap items-center gap-2">
    <a href="/pengaturan-publik" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Pengaturan Halaman Publik</a>
    <button id="set-reset" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Batalkan Perubahan</button>
    <button id="set-save" type="button" class="rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Simpan Perubahan</button>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        Nilai yang disimpan di sini dipakai sistem sebagai parameter uang muka minimum dan masa berlaku booking, serta menjadi identitas perumahan pada halaman publik.
    </div>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Status Masuk dengan Google</h2>
        <div id="google-status" class="mt-3 text-sm text-zinc-600">Memuat status...</div>
        <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-zinc-600">
            <li>Buka <span class="font-medium text-zinc-800">Google Cloud Console &rsaquo; APIs and Services &rsaquo; Credentials</span> lalu buat <em>OAuth client ID</em> bertipe <em>Web application</em>.</li>
            <li>Pada <span class="font-medium text-zinc-800">Authorized redirect URIs</span>, isikan persis URI pengalihan balik di bawah ini.</li>
            <li>Salin <em>Client ID</em> dan <em>Client Secret</em> ke kolom Integrasi Login Google di bawah.</li>
            <li>Pilih <span class="font-medium text-zinc-800">Aktif</span> pada saklar lalu klik Simpan Perubahan. Tombol Google pada halaman masuk akan aktif otomatis.</li>
        </ol>
    </section>
    <p id="set-dirty" class="hidden text-sm font-medium text-amber-700">Ada perubahan yang belum disimpan.</p>
    <div id="set-form"></div>
</div>
@endsection

@push('scripts')
<script>
  const GROUP_LABEL = {
    identitas: 'Identitas Perumahan',
    penjualan: 'Penjualan & Pembayaran',
    sistem: 'Sistem & Sesi Login',
    integrasi: 'Integrasi Login Google',
  };

  const META = {
    estate_name: { label: 'Nama Perumahan', type: 'text', required: true, col: 2 },
    estate_address: { label: 'Alamat Perumahan', type: 'textarea', rows: 3, col: 2, hint: 'Alamat lengkap lokasi perumahan.' },
    estate_phone: { label: 'Nomor Telepon', type: 'tel', required: true, hint: 'Contoh: (0751) 550100' },
    estate_email: { label: 'Email', type: 'email', required: true },
    estate_hours: { label: 'Jam Operasional', type: 'text', hint: 'Contoh: Senin - Sabtu, 08.00 - 17.00 WIB' },
    about_text: { label: 'Tentang Perumahan', type: 'textarea', rows: 5, col: 2, hint: 'Ditampilkan pada halaman Tentang Kami.' },
    min_dp_percent: { label: 'Minimal Uang Muka (%)', type: 'number', min: 0, max: 100, step: 0.01 },
    booking_validity_days: { label: 'Batas Waktu Booking (hari)', type: 'number', min: 1, max: 365, step: 1 },
    session_timeout_minutes: {
      label: 'Batas Sesi Login (menit)', type: 'number', min: 15, max: 10080, step: 1,
      hint: 'Token login kedaluwarsa otomatis setelah tidak dipakai. Minimum 15 menit, maksimum 10080 menit (7 hari).',
    },
    google_login_enabled: {
      label: 'Saklar Masuk dengan Google', type: 'select', col: 2,
      options: [{ value: '0', label: 'Nonaktif' }, { value: '1', label: 'Aktif' }],
      hint: 'Aktif hanya bila Client ID dan Client Secret sudah terisi.',
    },
    google_client_id: {
      label: 'Google Client ID', type: 'text', col: 2,
      placeholder: '1234567890-abcd1234.apps.googleusercontent.com',
      hint: 'Berlaku sebagai identitas publik, bukan rahasia.',
    },
    google_client_secret: {
      label: 'Google Client Secret', type: 'text', col: 2,
      placeholder: 'GOCSPX-xxxxxxxxxxxxxxxx',
      hint: 'Disimpan di server dan tidak pernah ditampilkan kembali. Kosongkan atau biarkan penanda tersimpan agar nilai lama tidak berubah.',
    },
  };

  let loaded = {};
  let editable = [];

  const controlId = (key) => 'set-' + key;

  function renderGoogleStatus(info) {
    const host = document.getElementById('google-status');
    if (!host) return;
    const g = info || {};
    const aktif = g.active
      ? '<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Aktif</span>'
      : '<span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 ring-1 ring-inset ring-zinc-500/20">Belum aktif</span>';
    const centang = (label, ok) => '<li>' + label + ': ' + (ok
      ? '<span class="font-semibold text-emerald-700">terisi</span>'
      : '<span class="font-semibold text-amber-700">belum diisi</span>') + '</li>';

    host.innerHTML = '<div class="flex flex-wrap items-center gap-3">' + aktif
      + '<span class="text-xs text-zinc-500">Tombol Google pada halaman masuk ikut menyala hanya saat status Aktif.</span></div>'
      + '<ul class="mt-3 space-y-1 text-sm text-zinc-600">'
      + centang('Client ID', g.client_configured)
      + centang('Client Secret', g.secret_configured)
      + '<li class="pt-1">Daftarkan URI ini pada <em>Authorized redirect URIs</em>: '
      + '<code class="rounded bg-zinc-100 px-2 py-1 text-xs font-mono text-zinc-800 select-all">' + LP.esc(g.redirect_uri || '-') + '</code></li>'
      + '</ul>';
  }

  function fieldHtml(key, value) {
    const meta = META[key] || { label: key.replace(/_/g, ' '), type: 'text' };
    const id = controlId(key);
    const locked = editable.length > 0 && !editable.includes(key);
    const cls = 'w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10 disabled:bg-zinc-100 disabled:text-zinc-500';
    let control;
    if (meta.type === 'textarea') {
      control = '<textarea id="' + id + '" rows="' + (meta.rows || 4) + '" class="' + cls + '" data-key="' + LP.esc(key) + '" '
        + (locked ? 'disabled ' : '') + 'placeholder="' + LP.esc(meta.hint || '') + '">' + LP.esc(value ?? '') + '</textarea>';
    } else if (meta.type === 'select') {
      const opts = (meta.options || []).map((o) => '<option value="' + LP.esc(o.value) + '"'
        + (String(o.value) === String(value ?? '') ? ' selected' : '') + '>' + LP.esc(o.label) + '</option>').join('');
      control = '<select id="' + id + '" class="' + cls + '" data-key="' + LP.esc(key) + '" ' + (locked ? 'disabled ' : '') + '>' + opts + '</select>';
    } else {
      control = '<input id="' + id + '" type="' + meta.type + '" class="' + cls + '" data-key="' + LP.esc(key) + '" '
        + (locked ? 'disabled ' : '')
        + (meta.min !== undefined ? 'min="' + meta.min + '" ' : '')
        + (meta.max !== undefined ? 'max="' + meta.max + '" ' : '')
        + (meta.step !== undefined ? 'step="' + meta.step + '" ' : '')
        + 'value="' + LP.esc(value ?? '') + '" placeholder="' + LP.esc(meta.placeholder || '') + '">';
    }
    return '<label class="block ' + (meta.col === 2 ? 'sm:col-span-2' : '') + '">'
      + '<span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">'
      + LP.esc(meta.label) + (meta.required ? ' *' : '') + '</span>'
      + control
      + (locked ? '<span class="mt-1 block text-xs text-zinc-500">Kunci ini tidak dapat diubah dari halaman pengaturan.</span>'
        : (meta.hint ? '<span class="mt-1 block text-xs text-zinc-500">' + LP.esc(meta.hint) + '</span>' : ''))
      + '<span data-err="' + LP.esc(key) + '" class="mt-1 hidden text-xs font-medium text-rose-600"></span>'
      + '</label>';
  }

  function renderForm(semuaItems, values) {
    // Kunci public_* dikelola di halaman Pengaturan Halaman Publik, tidak di sini.
    const items = (semuaItems || []).filter((item) => !String(item.key).startsWith('public_'));
    const data = values || {};
    const groups = [];
    const groupedKeys = [];
    items.forEach((item) => {
      if (!groups.includes(item.group)) groups.push(item.group);
      groupedKeys.push(item.key);
    });

    const extraKeys = Object.keys(data).filter((key) => !groupedKeys.includes(key) && !String(key).startsWith('public_'));
    if (extraKeys.length) groups.push('lainnya');
    if (!groups.length) {
      document.getElementById('set-form').innerHTML =
        '<div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 shadow-sm">Belum ada pengaturan tersimpan di sistem.</div>';
      return;
    }

    const keysOf = (group) => (group === 'lainnya'
      ? extraKeys
      : (items || []).filter((item) => item.group === group).map((item) => item.key));

    document.getElementById('set-form').innerHTML = groups.map((group) => `
      <section class="mb-6 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">${LP.esc(GROUP_LABEL[group] || String(group).replace(/_/g, ' '))}</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          ${keysOf(group).map((key) => fieldHtml(key, data[key])).join('')}
        </div>
      </section>`).join('');

    document.querySelectorAll('#set-form [data-key]').forEach((input) => {
      input.addEventListener('input', markDirty);
      input.addEventListener('change', markDirty);
    });
  }

  function markDirty() {
    document.getElementById('set-dirty').classList.remove('hidden');
  }

  function clearDirty() {
    document.getElementById('set-dirty').classList.add('hidden');
    document.querySelectorAll('#set-form [data-err]').forEach((node) => {
      node.classList.add('hidden');
      node.textContent = '';
    });
  }

  function collectChanges() {
    const changes = {};
    document.querySelectorAll('#set-form [data-key]').forEach((input) => {
      const key = input.dataset.key;
      const current = input.value;
      if (String(loaded[key] ?? '') !== String(current ?? '')) changes[key] = current;
    });
    return changes;
  }

  async function refresh() {
    const data = await LP.fetchJson('/settings');
    loaded = data.values || {};
    editable = data.editable || [];
    renderGoogleStatus(data.google);
    renderForm(data.items || [], loaded);
    clearDirty();
  }

  async function save() {
    const changes = collectChanges();
    if (!Object.keys(changes).length) {
      LP.toast('Tidak ada perubahan yang perlu disimpan.', 'info');
      return;
    }
    try {
      await LP.http.put('/settings', { settings: changes });
      LP.toast('Pengaturan berhasil disimpan.');
      await refresh();
    } catch (error) {
      const errors = error.errors || {};
      Object.entries(errors).forEach(([key, messages]) => {
        const slot = document.querySelector('[data-err="' + key + '"]');
        if (slot) {
          slot.textContent = messages.join(', ');
          slot.classList.remove('hidden');
        }
      });
      LP.toast(error.message || 'Pengaturan gagal disimpan.', 'error');
    }
  }

  document.getElementById('set-save').addEventListener('click', (event) => {
    LP.withBusy(event.currentTarget, save);
  });

  document.getElementById('set-reset').addEventListener('click', (event) => {
    LP.withBusy(event.currentTarget, refresh);
  });

  LP.guard(['super_admin'], async () => {
    LP.loading('set-form', 'Memuat pengaturan...');
    try {
      await refresh();
    } catch (error) {
      LP.errorBox('set-form', error.message || 'Pengaturan gagal dimuat.');
      LP.toast(error.message || 'Pengaturan gagal dimuat.', 'error');
    }
  });
</script>
@endpush
