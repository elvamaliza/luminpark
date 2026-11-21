@extends('layouts.app')

@section('title', 'Pengaturan Halaman Publik')

@section('page_title', 'Pengaturan Halaman Publik')

@section('page_desc', 'Kelola teks beranda, gambar hero, seksi yang tampil, unit unggulan, dan kabar terbaru yang dilihat pengunjung.')

@section('page_actions')
<div class="flex flex-wrap items-center gap-2">
    <a href="/" target="_blank" rel="noopener" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Pratinjau</a>
    <button id="pub-reset" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Batalkan Perubahan</button>
    <button id="pub-save" type="button" class="rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Simpan Perubahan</button>
</div>
@endsection

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        Nilai di halaman ini mengatur tampilan situs publik. Pengunjung melihat perubahannya segera setelah disimpan.
    </div>

    <p id="pub-dirty" class="hidden text-sm font-medium text-amber-700">Ada perubahan yang belum disimpan.</p>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Bagian Atas Beranda (Hero)</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Label Kecil di Atas Judul</span>
                <input data-pubkey="public_hero_eyebrow" type="text" class="pub-input w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm" placeholder="Hunian Minimalis, Asri, dan Islami">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Judul Utama</span>
                <input data-pubkey="public_hero_title" type="text" class="pub-input w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm" placeholder="Kosong = memakai nama perumahan">
                <span class="mt-1 block text-xs text-zinc-500">Bila dikosongkan, judul mengikuti nama perumahan pada Pengaturan Sistem.</span>
            </label>
            <label class="block sm:col-span-2">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Kalimat Pengantar</span>
                <textarea data-pubkey="public_hero_tagline" rows="3" class="pub-input w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm"></textarea>
            </label>
        </div>

        <div class="mt-4 border-t border-zinc-100 pt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Gambar Latar Hero</p>
            <div id="pub-hero-preview" class="mt-2"></div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input id="pub-hero-file" type="file" accept="image/jpeg,image/png" class="max-w-xs text-sm">
                <button id="pub-hero-upload" type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Unggah Gambar</button>
                <button id="pub-hero-remove" type="button" class="hidden rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Hapus Gambar</button>
            </div>
            <p class="mt-2 text-xs text-zinc-500">JPG/PNG maksimal 2MB, disarankan rasio lebar 16:9. Tanpa gambar, hero memakai latar hijau merek.</p>
        </div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Seksi yang Tampil di Beranda</h2>
        <div id="pub-sections" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
        <label class="mt-4 block max-w-xs">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Jumlah Unit Unggulan</span>
            <input data-pubkey="public_featured_count" type="number" min="1" max="12" step="1" class="pub-input w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm">
        </label>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Unit Unggulan Pilihan</h2>
        <p class="mt-1 text-sm text-zinc-500">Centang unit yang dipin paling depan. Bila tidak ada yang dicentang, beranda memakai urutan otomatis berdasarkan kode unit.</p>
        <div id="pub-units" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Pengumuman &amp; Catatan Footer</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Banner Pengumuman</span>
                <input data-pubkey="public_announcement" type="text" class="pub-input w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm" placeholder="Contoh: Promo uang muka ringan sampai 31 Desember 2026">
                <span class="mt-1 block text-xs text-zinc-500">Kosongkan untuk menghilangkan banner pada seluruh halaman publik.</span>
            </label>
            <label class="block sm:col-span-2">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Catatan Kaki Footer</span>
                <input data-pubkey="public_footer_note" type="text" class="pub-input w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm" placeholder="Kosong = memakai kalimat hak cipta bawaan">
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Kabar Terbaru</h2>
                <p class="mt-1 text-sm text-zinc-500">Maksimal lima kabar terbaru yang tayang tampil pada seksi Kabar Terbaru di beranda.</p>
            </div>
            <button id="pub-news-add" type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Tambah Kabar</button>
        </div>
        <div id="pub-news" class="mt-4 space-y-3"></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
  const SEKSINYANG = [
    { key: 'public_show_unggulan', label: 'Unit Unggulan' },
    { key: 'public_show_skema', label: 'Skema Pembayaran' },
    { key: 'public_show_peta', label: 'Peta Lokasi Proyek (Beranda)' },
    { key: 'public_show_news', label: 'Kabar Terbaru' },
    { key: 'public_show_gallery', label: 'Galeri Perumahan' },
    { key: 'public_show_sales', label: 'Hubungi Sales' },
  ];

  const TEKS_KUNCI = ['public_hero_eyebrow', 'public_hero_title', 'public_hero_tagline', 'public_featured_count', 'public_announcement', 'public_footer_note'];
  const SAKLAR_KUNCI = SEKSINYANG.map((item) => item.key);
  const SEMUA_KUNCI = TEKS_KUNCI.concat(SAKLAR_KUNCI);

  let awal = {};
  let kini = {};
  let savedIds = [];
  let savedIdsAwal = [];
  let heroUrl = null;
  let unitList = [];
  let posts = [];

  const inputOf = (key) => document.querySelector('[data-pubkey="' + key + '"]');

  function markDirty() {
    TEKS_KUNCI.forEach((key) => {
      const node = inputOf(key);
      if (node) kini[key] = node.value;
    });
    const ada = SEMUA_KUNCI.some((key) => String(awal[key] ?? '') !== String(kini[key] ?? ''))
      || savedIds.join(',') !== savedIdsAwal.join(',');
    document.getElementById('pub-dirty').classList.toggle('hidden', !ada);
  }

  function renderSeksi() {
    document.getElementById('pub-sections').innerHTML = SEKSINYANG.map((item) => {
      const aktif = String(kini[item.key] ?? '1') === '1';
      return '<label class="flex items-start gap-3 rounded-xl border ' + (aktif ? 'border-brand-500 bg-brand-50' : 'border-zinc-200 bg-white') + ' px-4 py-3">'
        + '<input type="checkbox" data-seksi="' + item.key + '" class="mt-1 h-4 w-4" ' + (aktif ? 'checked' : '') + '>'
        + '<span><span class="block text-sm font-semibold text-zinc-900">' + LP.esc(item.label) + '</span>'
        + '<span class="block text-xs text-zinc-500">' + (aktif ? 'Tampil di beranda' : 'Sembunyikan dari beranda') + '</span></span>'
        + '</label>';
    }).join('');

    document.querySelectorAll('[data-seksi]').forEach((box) => {
      box.addEventListener('change', () => {
        kini[box.dataset.seksi] = box.checked ? '1' : '0';
        renderSeksi();
        markDirty();
      });
    });
  }

  function renderHero() {
    document.getElementById('pub-hero-preview').innerHTML = heroUrl
      ? '<img src="' + LP.esc(heroUrl) + '" alt="Pratinjau gambar hero" class="h-32 w-full max-w-lg rounded-xl border border-zinc-200 object-cover">'
      : '<div class="flex h-24 w-full max-w-lg items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-brand-900 text-sm font-medium text-white">Latar hijau merek (tanpa gambar)</div>';
    document.getElementById('pub-hero-remove').classList.toggle('hidden', !heroUrl);
  }

  function renderUnits() {
    const host = document.getElementById('pub-units');
    if (!unitList.length) {
      host.innerHTML = '<p class="text-sm text-zinc-500">Belum ada unit berstatus tersedia.</p>';
      return;
    }

    host.innerHTML = unitList.map((u) => {
      const dipilih = savedIds.includes(u.id);
      return '<label class="flex items-center gap-3 rounded-xl border ' + (dipilih ? 'border-brand-500 bg-brand-50' : 'border-zinc-200') + ' px-3 py-2">'
        + '<input type="checkbox" data-unit="' + LP.esc(u.id) + '" class="h-4 w-4" ' + (dipilih ? 'checked' : '') + '>'
        + '<span class="min-w-0"><span class="block truncate text-sm font-semibold text-zinc-900">' + LP.esc(u.unit_code) + ' &middot; ' + LP.esc(u.unit_type) + '</span>'
        + '<span class="block truncate text-xs text-zinc-500">' + LP.esc(u.cluster ? u.cluster.name : '-') + ' &middot; ' + LP.money(u.price) + '</span></span>'
        + '</label>';
    }).join('');

    document.querySelectorAll('[data-unit]').forEach((box) => {
      box.addEventListener('change', () => {
        const id = Number(box.dataset.unit);
        savedIds = box.checked ? savedIds.concat([id]) : savedIds.filter((x) => x !== id);
        renderUnits();
        markDirty();
      });
    });
  }

  function renderPosts() {
    const host = document.getElementById('pub-news');
    if (!posts.length) {
      host.innerHTML = '<p class="text-sm text-zinc-500">Belum ada kabar terbaru. Tambahkan agar seksi ini terisi.</p>';
      return;
    }

    host.innerHTML = posts.map((p) => {
      const gambar = p.image_url
        ? '<img src="' + LP.esc(p.image_url) + '" alt="" class="h-16 w-24 shrink-0 rounded-lg object-cover">'
        : '';
      return '<article class="flex flex-wrap items-start gap-3 rounded-xl border border-zinc-200 p-3">'
        + gambar
        + '<div class="min-w-0 flex-1">'
        + '<p class="text-sm font-semibold text-zinc-900">' + LP.esc(p.title) + '</p>'
        + '<p class="mt-1 max-h-10 overflow-hidden text-xs text-zinc-500">' + LP.esc(p.content) + '</p>'
        + '<p class="mt-2 flex flex-wrap items-center gap-2 text-xs">'
        + '<span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700">' + LP.date(p.posted_on) + '</span>'
        + (p.is_published
          ? '<span class="rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700">Tayang</span>'
          : '<span class="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800">Sembunyi</span>')
        + '</p></div>'
        + '<div class="flex shrink-0 flex-wrap gap-2">'
        + '<button type="button" data-news-toggle="' + LP.esc(p.id) + '" class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">' + (p.is_published ? 'Sembunyikan' : 'Tayangkan') + '</button>'
        + '<button type="button" data-news-edit="' + LP.esc(p.id) + '" class="rounded-lg border border-zinc-300 px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">Edit</button>'
        + '<button type="button" data-news-del="' + LP.esc(p.id) + '" class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Hapus</button>'
        + '</div></article>';
    }).join('');
  }

  function openPost(original) {
    LP.formModal({
      title: original ? 'Edit Kabar Terbaru' : 'Tambah Kabar Terbaru',
      description: 'Judul dan isi ini yang dibaca pengunjung pada seksi Kabar Terbaru.',
      fields: [
        { name: 'title', label: 'Judul Kabar', type: 'text', required: true, col: 2, hint: 'Maksimal 150 karakter.' },
        { name: 'content', label: 'Isi Kabar', type: 'textarea', rows: 4, required: true, col: 2, hint: 'Maksimal 2000 karakter.' },
        { name: 'posted_on', label: 'Tanggal Tayang', type: 'date', required: true },
        {
          name: 'is_published', label: 'Status', type: 'select', required: true,
          options: [{ value: '1', label: 'Tampil di halaman publik' }, { value: '0', label: 'Sembunyikan' }],
        },
        { name: 'image', label: 'Gambar (opsional)', type: 'file', accept: 'image/*', col: 2, hint: 'JPG/PNG maksimal 2MB. Biarkan kosong untuk mempertahankan gambar saat ini.' },
      ],
      values: original
        ? { title: original.title, content: original.content, posted_on: LP.isoDate(original.posted_on), is_published: original.is_published ? '1' : '0' }
        : { posted_on: LP.today(), is_published: '1' },
      submitLabel: 'Simpan Kabar',
      onSubmit: async (values, context) => {
        const file = values.__files ? values.__files.image : null;
        const payload = {
          title: values.title.trim(),
          content: values.content.trim(),
          posted_on: values.posted_on,
          is_published: values.is_published,
        };

        let hasil;
        if (original) {
          if (file) {
            const body = new FormData();
            body.append('_method', 'PUT');
            Object.keys(payload).forEach((key) => body.append(key, payload[key]));
            body.append('image', file);
            hasil = await LP.http.post('/public-posts/' + original.id, body);
          } else {
            hasil = await LP.http.put('/public-posts/' + original.id, payload);
          }
        } else {
          const body = new FormData();
          Object.keys(payload).forEach((key) => body.append(key, payload[key]));
          if (file) body.append('image', file);
          hasil = await LP.http.post('/public-posts', body);
        }

        LP.toast((hasil.json && hasil.json.message) || 'Kabar terbaru tersimpan.');
        context.close();
        await loadPosts();
      },
    });
  }

  async function loadPosts() {
    const data = await LP.fetchJson('/public-posts');
    posts = Array.isArray(data) ? data : [];
    renderPosts();
  }

  function newsActions() {
    document.getElementById('pub-news').addEventListener('click', async (event) => {
      const edit = event.target.closest('[data-news-edit]');
      const alih = event.target.closest('[data-news-toggle]');
      const hapus = event.target.closest('[data-news-del]');

      if (edit) {
        const row = posts.find((p) => String(p.id) === edit.dataset.newsEdit);
        if (row) openPost(row);
        return;
      }

      if (alih) {
        const row = posts.find((p) => String(p.id) === alih.dataset.newsToggle);
        if (!row) return;
        await LP.withBusy(alih, async () => {
          try {
            await LP.http.put('/public-posts/' + row.id, { is_published: row.is_published ? 0 : 1 });
            await loadPosts();
            LP.toast('Status tayang kabar diperbarui.');
          } catch (error) {
            LP.toast(error.message || 'Status kabar gagal diubah.', 'error');
          }
        });
        return;
      }

      if (hapus) {
        const row = posts.find((p) => String(p.id) === hapus.dataset.newsDel);
        if (!row) return;
        const setuju = await LP.confirm('Kabar "' + row.title + '" akan dikeluarkan dari daftar kabar terbaru. Riwayatnya tetap tersimpan di basis data dan dapat dipulihkan administrator.', {
          title: 'Hapus Kabar Terbaru', okLabel: 'Ya, Hapus', danger: true,
        });
        if (!setuju) return;
        await LP.withBusy(hapus, async () => {
          try {
            await LP.http.del('/public-posts/' + row.id);
            LP.toast('Kabar terbaru berhasil dihapus.');
            await loadPosts();
          } catch (error) {
            LP.toast(error.message || 'Kabar gagal dihapus.', 'error');
          }
        });
      }
    });
  }

  function collectChanges() {
    const changes = {};
    SEMUA_KUNCI.forEach((key) => {
      if (String(awal[key] ?? '') !== String(kini[key] ?? '')) changes[key] = kini[key] ?? '';
    });
    if (savedIds.join(',') !== savedIdsAwal.join(',')) {
      changes.public_featured_unit_ids = savedIds.join(',');
    }
    return changes;
  }

  async function save() {
    const changes = collectChanges();
    if (!Object.keys(changes).length) {
      LP.toast('Tidak ada perubahan tampilan publik yang perlu disimpan.', 'info');
      return;
    }
    await LP.http.put('/settings', { settings: changes });
    LP.toast('Tampilan halaman publik berhasil disimpan.');
    await refresh();
  }

  async function refresh() {
    const data = await LP.fetchJson('/settings');
    const values = data.values || {};

    awal = {};
    kini = {};
    SEMUA_KUNCI.forEach((key) => {
      const baku = key === 'public_featured_count' ? '3' : (SAKLAR_KUNCI.includes(key) ? '1' : '');
      awal[key] = values[key] === null || values[key] === undefined ? baku : String(values[key]);
      kini[key] = awal[key];
      const node = inputOf(key);
      if (node) node.value = awal[key];
    });

    savedIds = (data.public && data.public.featured_unit_ids) || [];
    savedIdsAwal = savedIds.slice();
    heroUrl = data.public ? data.public.hero_image_url : null;

    renderSeksi();
    renderHero();
    markDirty();
  }

  async function init() {
    await refresh();

    const units = await LP.fetchJson('/units?status=available');
    unitList = Array.isArray(units) ? units : [];
    renderUnits();
    await loadPosts();

    document.querySelectorAll('.pub-input').forEach((node) => {
      node.addEventListener('input', markDirty);
      node.addEventListener('change', markDirty);
    });

    document.getElementById('pub-save').addEventListener('click', (event) => {
      LP.withBusy(event.currentTarget, async () => {
        try {
          await save();
        } catch (error) {
          LP.toast(error.message || 'Pengaturan halaman publik gagal disimpan.', 'error');
        }
      });
    });

    document.getElementById('pub-reset').addEventListener('click', (event) => {
      LP.withBusy(event.currentTarget, async () => {
        try {
          await refresh();
          renderUnits();
          LP.toast('Perubahan dibatalkan.', 'info');
        } catch (error) {
          LP.toast(error.message || 'Pengaturan gagal dimuat ulang.', 'error');
        }
      });
    });

    document.getElementById('pub-news-add').addEventListener('click', () => openPost(null));
    newsActions();

    document.getElementById('pub-hero-upload').addEventListener('click', async (event) => {
      const file = document.getElementById('pub-hero-file').files[0];
      if (!file) {
        LP.toast('Pilih berkas gambar terlebih dahulu.', 'error');
        return;
      }
      await LP.withBusy(event.currentTarget, async () => {
        try {
          const body = new FormData();
          body.append('image', file);
          const hasil = await LP.http.post('/settings/public-image', body);
          heroUrl = hasil.json && hasil.json.data ? hasil.json.data.hero_image_url : null;
          document.getElementById('pub-hero-file').value = '';
          renderHero();
          LP.toast((hasil.json && hasil.json.message) || 'Gambar hero tersimpan.');
        } catch (error) {
          LP.toast(error.message || 'Gambar hero gagal diunggah.', 'error');
        }
      });
    });

    document.getElementById('pub-hero-remove').addEventListener('click', async (event) => {
      await LP.withBusy(event.currentTarget, async () => {
        try {
          const body = new FormData();
          body.append('hapus', '1');
          await LP.http.post('/settings/public-image', body);
          heroUrl = null;
          renderHero();
          LP.toast('Gambar hero dihapus.');
        } catch (error) {
          LP.toast(error.message || 'Gambar hero gagal dihapus.', 'error');
        }
      });
    });
  }

  LP.guard(['super_admin'], async () => {
    try {
      await init();
    } catch (error) {
      LP.toast(error.message || 'Pengaturan halaman publik gagal dimuat.', 'error');
    }
  });
</script>
@endpush
