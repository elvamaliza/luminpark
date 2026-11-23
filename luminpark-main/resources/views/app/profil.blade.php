@extends('layouts.app')

@section('title', 'Profil Saya')

@section('page_title', 'Profil Saya')

@section('page_desc', 'Kelola identitas akun, foto profil, dan kata sandi Anda.')

@section('page_actions')
<div class="flex flex-wrap items-center gap-2">
    <button id="pf-photo" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Ganti Foto</button>
    <button id="pf-edit" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Edit Profil</button>
    <button id="pf-password" type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Ganti Kata Sandi</button>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <section id="pf-identity"></section>
    <section id="pf-target-wrap" class="hidden">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Target Penjualan</h2>
        <div id="pf-targets" class="grid gap-4 lg:grid-cols-2"></div>
    </section>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Keamanan Akun</h2>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-zinc-900">Ubah Kata Sandi</p>
                <p class="text-xs text-zinc-500">Perbarui kata sandi akun Anda secara berkala untuk menjaga keamanan.</p>
            </div>
            <button id="pf-password-2" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Ganti Kata Sandi</button>
        </div>
        <div class="mt-3 rounded-xl border border-zinc-200 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-zinc-900">Login dengan Google</p>
                    <p class="text-xs text-zinc-500">Hubungkan akun Google Anda untuk masuk tanpa mengetik kata sandi.</p>
                </div>
                <div id="pf-google-state" class="flex items-center gap-3"></div>
            </div>
            <p id="pf-google-note" class="mt-2 hidden rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900"></p>
        </div>
        <ul class="mt-4 space-y-1.5 text-sm text-zinc-600">
            <li>Email, peran, dan status akun diatur oleh Super Admin.</li>
            <li>Mengganti kata sandi akan mengakhiri seluruh sesi login yang sedang aktif.</li>
            <li>Batas waktu sesi login diatur Super Admin pada halaman Pengaturan Sistem.</li>
        </ul>
    </section>
</div>
@endsection

@push('scripts')
<script>
  let profile = null;

  function infoRow(label, value) {
    return `<div class="border-t border-zinc-100 py-2.5 sm:grid sm:grid-cols-3 sm:gap-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">${LP.esc(label)}</dt>
      <dd class="mt-0.5 text-sm text-zinc-800 sm:col-span-2 sm:mt-0">${value}</dd>
    </div>`;
  }

  function progressBar(text, percent, tone) {
    const pct = Math.max(0, Math.min(100, Number(percent || 0)));
    const color = tone === 'amount' ? 'bg-sky-500' : 'bg-emerald-500';
    return '<div class="mt-2">'
      + '<div class="flex items-center justify-between text-xs text-zinc-600"><span>' + LP.esc(text) + '</span>'
      + '<span class="font-semibold tabular-nums">' + LP.number(pct) + '%</span></div>'
      + '<div class="mt-1 h-2 w-full rounded-full bg-zinc-200">'
      + '<div class="h-2 rounded-full ' + color + '" style="width:' + pct + '%"></div></div></div>';
  }

  function ratioPercent(achieved, target) {
    const t = Number(target || 0);
    if (!t) return 0;
    return Math.round((Number(achieved || 0) / t) * 10000) / 100;
  }

  function renderIdentity(p) {
    const initial = (p.name || '?').trim().charAt(0).toUpperCase();
    const avatar = p.avatar_url
      ? '<img src="' + LP.esc(p.avatar_url) + '" alt="Foto profil ' + LP.esc(p.name || '') + '" class="h-20 w-20 rounded-full border-2 border-white object-cover shadow">'
      : '<span class="grid h-20 w-20 place-items-center rounded-full bg-zinc-900 text-2xl font-bold text-white">' + LP.esc(initial) + '</span>';

    const rows = [
      ['Nama', LP.esc(p.name || '-')],
      ['Peran', LP.esc(LP.roleLabel(p.role))],
      ['Username', LP.esc(p.username || '-')],
      ['Nomor HP', LP.esc(p.phone || '-')],
      ['Nomor WhatsApp', LP.esc(p.whatsapp_number || '-')],
      ['Alamat', LP.esc(p.address || '-')],
      ['Status Akun', LP.badge(p.status)],
      ['Akun Dibuat', LP.date(p.created_at)],
    ];

    if (p.role === 'super_admin') {
      rows.splice(7, 0, ['Sesi Aktif', '<a href="/sesi" class="font-medium text-brand-600 underline">' + LP.number(p.active_sessions) + ' sesi</a>']);
    }

    document.getElementById('pf-identity').innerHTML = `
      <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-4 border-b border-zinc-100 bg-gradient-to-br from-zinc-50 to-white p-5">
          ${avatar}
          <div>
            <h2 class="text-lg font-bold text-zinc-900">${LP.esc(p.name || '-')}</h2>
            <p class="text-sm text-zinc-500">${LP.esc(p.username || '-')}</p>
            <p class="mt-2 flex flex-wrap items-center gap-2">
              ${LP.badge(p.status)}
              <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">${LP.esc(LP.roleLabel(p.role))}</span>
              ${p.sales_code ? `<span class="inline-flex items-center rounded-full border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600">${LP.esc(p.sales_code)}</span>` : ''}
            </p>
          </div>
        </div>
        <dl class="grid grid-cols-1 gap-x-8 px-5 py-3 sm:grid-cols-2">
          ${rows.map((row) => infoRow(row[0], row[1])).join('')}
        </dl>
      </div>`;
  }

  function renderTargets(p) {
    const targets = p.targets || [];
    if (!targets.length) {
      document.getElementById('pf-target-wrap').classList.add('hidden');
      return;
    }
    document.getElementById('pf-target-wrap').classList.remove('hidden');
    document.getElementById('pf-targets').innerHTML = targets.map((t) => `
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-zinc-900">Periode ${LP.date(t.period_start)} - ${LP.date(t.period_end)}</p>
        ${progressBar('Unit: ' + LP.number(t.achieved_units) + ' dari ' + LP.number(t.target_units) + ' unit', ratioPercent(t.achieved_units, t.target_units), 'unit')}
        ${progressBar('Nilai: ' + LP.shortMoney(t.achieved_amount) + ' dari ' + LP.shortMoney(t.target_amount), ratioPercent(t.achieved_amount, t.target_amount), 'amount')}
      </div>`).join('');
  }

  const GOOGLE_PESAN = {
    'sudah-dipakai-akun-lain': 'Akun Google itu sudah tertaut dengan akun lain.',
    'akun-tidak-diketahui': 'Akun Anda tidak lagi terdaftar, silakan masuk ulang.',
  };

  function googleNote() {
    const note = document.getElementById('pf-google-note');
    const params = new URLSearchParams(location.search);
    const pesan = params.get('google') === 'terhubung'
      ? 'Akun Google berhasil ditautkan ke akun Anda.'
      : (GOOGLE_PESAN[params.get('google_error')] || (params.get('google_error') ? 'Penautan Google gagal: ' + params.get('google_error') : ''));

    if (!pesan) return;
    note.textContent = pesan;
    note.classList.remove('hidden');
    history.replaceState({}, '', '/profil');
  }

  function renderGoogle(p) {
    const host = document.getElementById('pf-google-state');
    const chip = (teks, cls) => '<span class="rounded-full px-2.5 py-1 text-xs font-semibold ' + cls + '">' + teks + '</span>';

    if (!p.google_ready) {
      host.innerHTML = chip('Belum tersedia', 'bg-zinc-100 text-zinc-600 ring-1 ring-inset ring-zinc-500/20');
      return;
    }

    if (p.google_linked) {
      host.innerHTML = chip('Terhubung', 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20')
        + '<button id="pf-google-unlink" type="button" class="rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-xs font-medium text-zinc-700 hover:bg-zinc-50">Putuskan Tautan</button>';
    } else {
      host.innerHTML = chip('Belum terhubung', 'bg-zinc-100 text-zinc-600 ring-1 ring-inset ring-zinc-500/20')
        + '<button id="pf-google-link" type="button" class="rounded-xl border border-zinc-900 bg-white px-3.5 py-2 text-xs font-semibold text-zinc-900 hover:bg-zinc-50">Hubungkan Google</button>';
    }

    const link = document.getElementById('pf-google-link');
    if (link) {
      link.onclick = () => LP.withBusy(link, async () => {
        const data = await LP.http.post('/auth/google/link', {});
        const url = data.json && data.json.data ? data.json.data.url : null;
        if (!url) throw new Error('URL penautan tidak diterima.');
        location.assign(url);
      }).catch((error) => LP.toast(error.message || 'Gagal menyiapkan penautan Google.', 'error'));
    }

    const unlink = document.getElementById('pf-google-unlink');
    if (unlink) {
      unlink.onclick = () => LP.withBusy(unlink, async () => {
        await LP.http.del('/auth/google/link');
        LP.toast('Tautan akun Google dilepas.');
        await refresh();
      }).catch((error) => LP.toast(error.message || 'Gagal melepas tautan Google.', 'error'));
    }
  }

  async function refresh() {
    const data = await LP.fetchJson('/profile');
    profile = data;
    renderIdentity(data);
    renderTargets(data);
    renderGoogle(data);
    LP.paintIdentity(data);
    return data;
  }

  function editProfile() {
    LP.formModal({
      title: 'Edit Profil',
      description: 'Perbarui informasi pribadi akun Anda.',
      values: {
        name: profile.name || '',
        phone: profile.phone || '',
        address: profile.address || '',
        whatsapp_number: profile.whatsapp_number || '',
      },
      fields: [
        { name: 'name', label: 'Nama Lengkap', type: 'text', required: true, col: 2 },
        { name: 'phone', label: 'Nomor HP', type: 'tel', hint: 'Maksimal 20 karakter.' },
        { name: 'whatsapp_number', label: 'Nomor WhatsApp', type: 'text', hint: 'Format internasional, contoh 6281234567890.' },
        { name: 'address', label: 'Alamat', type: 'textarea', rows: 3, col: 2 },
      ],
      submitLabel: 'Simpan Perubahan',
      onSubmit: async (values) => {
        const payload = {
          name: values.name,
          phone: values.phone,
          address: values.address,
          whatsapp_number: values.whatsapp_number,
        };
        await LP.http.put('/profile', payload);
        LP.toast('Profil berhasil diperbarui.');
        await refresh();
      },
    });
  }

  function changePhoto() {
    LP.formModal({
      title: 'Ganti Foto Profil',
      description: 'Foto digunakan pada daftar pengguna dan menu akun.',
      width: 'max-w-lg',
      fields: [
        { name: 'avatar', label: 'Berkas Foto', type: 'file', required: true, col: 2, accept: 'image/*', hint: 'JPG/PNG maksimal 2MB' },
      ],
      submitLabel: 'Unggah Foto',
      onSubmit: async (values) => {
        const file = values.__files ? values.__files.avatar : null;
        if (!file) throw new Error('Pilih berkas foto terlebih dahulu.');
        if (file.size > 2 * 1024 * 1024) throw new Error('Ukuran foto melebihi 2MB.');

        const body = new FormData();
        body.append('avatar', file);
        body.append('_method', 'PUT');
        await LP.http.post('/profile', body);
        LP.toast('Foto profil berhasil diperbarui.');
        await refresh();
      },
    });
  }

  function changePassword() {
    LP.formModal({
      title: 'Ganti Kata Sandi',
      description: 'Setelah berhasil, semua sesi login akan keluar otomatis.',
      width: 'max-w-lg',
      values: { email: profile.username || profile.email || '' },
      fields: [
        { name: 'email', label: 'Email Akun', type: 'email', required: true, col: 2, hint: 'Harus sesuai dengan email akun yang sedang login.' },
        { name: 'password_lama', label: 'Kata Sandi Saat Ini', type: 'password', required: true },
        { name: 'password_baru', label: 'Kata Sandi Baru', type: 'password', required: true, hint: 'Minimal 8 karakter, memuat huruf besar, huruf kecil, dan angka; berbeda dari kata sandi lama.' },
      ],
      submitLabel: 'Ganti Kata Sandi',
      onSubmit: async (values) => {
        if (!LP.sandiKuat(values.password_baru)) throw new Error(LP.PESAN_SANDI);
        const ok = await LP.confirm('Kata sandi akan diganti dan seluruh sesi login diakhiri. Anda akan diarahkan ke halaman masuk.', {
          title: 'Ganti Kata Sandi',
          okLabel: 'Ya, Ganti',
          danger: true,
        });
        if (!ok) throw new Error('Penggantian kata sandi dibatalkan.');

        await LP.http.post('/reset-password', {
          email: values.email,
          password_lama: values.password_lama,
          password_baru: values.password_baru,
        });
        LP.toast('Kata sandi berhasil diganti. Silakan masuk kembali.');
        LP.token.clear();
        setTimeout(() => location.replace('/login'), 1200);
      },
    });
  }

  document.getElementById('pf-edit').addEventListener('click', () => {
    if (profile) editProfile();
  });
  document.getElementById('pf-photo').addEventListener('click', () => {
    if (profile) changePhoto();
  });
  document.getElementById('pf-password').addEventListener('click', () => {
    if (profile) changePassword();
  });
  document.getElementById('pf-password-2').addEventListener('click', () => {
    if (profile) changePassword();
  });

  LP.guard(['super_admin', 'admin_sales', 'sales'], async () => {
    LP.loading('pf-identity', 'Memuat profil...');
    googleNote();
    try {
      await refresh();
    } catch (error) {
      LP.errorBox('pf-identity', error.message || 'Profil gagal dimuat.');
      LP.toast(error.message || 'Profil gagal dimuat.', 'error');
    }
  });
</script>
@endpush
