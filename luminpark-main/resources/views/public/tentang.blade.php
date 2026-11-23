@extends('layouts.public')

@section('title', 'Tentang Kami · Perumahan Lumin Park')

@section('content')
<section class="bg-brand-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-16">
        <p class="text-sm font-semibold uppercase tracking-widest text-brand-100/80">Tentang Kami</p>
        <h1 class="mt-3 text-4xl font-bold leading-tight"><span data-setting="estate_name">Perumahan Lumin Park</span></h1>
    </div>
</section>

<main class="mx-auto max-w-6xl space-y-16 px-4 py-16">

    <section class="grid gap-10 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Gambaran Umum</h2>
            <p class="mt-4 text-base leading-relaxed text-zinc-600" data-setting="about_text">
                Lumin Park (Lubuk Minturun Park) adalah pengembang perumahan yang mengusung konsep hunian minimalis, asri, dan islami.
            </p>
        </div>
        <div id="tentang-project" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm"></div>
    </section>

    {{-- Revisi: informasi skema pembayaran hanya tampil pada halaman Beranda dan Skema Pembayaran. --}}
    <section data-seksi="gallery">
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Galeri Perumahan</h2>
        <div id="tentang-gallery" class="mt-6"></div>
    </section>

    <section data-seksi="sales">
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Tim Sales Kami</h2>
        <p class="mt-1 text-sm text-zinc-500">Siap membantu Anda menemukan unit yang tepat.</p>
        <div id="tentang-sales" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></div>
        <div class="mt-8">
            <a href="/unit" class="inline-flex rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-800">Mulai Jelajahi Unit</a>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const ANON = { anonymous: true };

    async function loadProject() {
        const mount = document.getElementById('tentang-project');
        LP.loading(mount, 'Memuat identitas proyek...');
        try {
            const rows = await LP.fetchJson('/public/units', ANON);
            const list = rows || [];
            if (!list.length) {
                mount.innerHTML = '<p class="text-sm text-zinc-500">Belum ada data proyek yang dapat ditampilkan.</p>';
                return;
            }
            const clusters = [];
            list.forEach(function (u) {
                if (u.cluster && clusters.indexOf(u.cluster) === -1) clusters.push(u.cluster);
            });
            const tipe = [];
            list.forEach(function (u) {
                if (u.unit_type && tipe.indexOf(u.unit_type) === -1) tipe.push(u.unit_type);
            });
            mount.innerHTML = '<h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Identitas Proyek</h3>'
                + '<p class="mt-2 text-lg font-bold text-zinc-900">' + LP.esc(list[0].project) + '</p>'
                + '<dl class="mt-4 space-y-3 text-sm">'
                + '<div class="flex justify-between"><dt class="text-zinc-500">Jumlah unit</dt><dd class="font-semibold text-zinc-900">' + LP.number(list.length) + '</dd></div>'
                + '<div><dt class="text-zinc-500">Tersedia di proyek</dt><dd class="mt-1 flex flex-wrap gap-1">'
                + clusters.map(function (c) { return '<span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">' + LP.esc(c) + '</span>'; }).join('')
                + '</dd></div>'
                + '<div><dt class="text-zinc-500">Tipe rumah</dt><dd class="mt-1 flex flex-wrap gap-1">'
                + tipe.map(function (t) { return '<span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">' + LP.esc(t) + '</span>'; }).join('')
                + '</dd></div>'
                + '</dl>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat identitas proyek.');
        }
    }

    async function loadGallery() {
        const mount = document.getElementById('tentang-gallery');
        LP.loading(mount, 'Memuat galeri...');
        try {
            const data = await LP.fetchJson('/public/news-gallery', ANON);
            const gallery = (data && data.gallery) || [];
            mount.innerHTML = gallery.length
                ? '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">' + gallery.map(function (g) {
                    return '<figure class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">'
                        + '<img src="' + LP.esc(g.image_url) + '" alt="' + LP.esc(g.title) + '" loading="lazy" class="h-48 w-full object-cover">'
                        + '<figcaption class="px-4 py-3 text-sm font-medium text-zinc-700">' + LP.esc(g.title) + '</figcaption>'
                        + '</figure>';
                }).join('') + '</div>'
                : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Galeri belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat galeri.');
        }
    }

    async function loadSales() {
        const mount = document.getElementById('tentang-sales');
        LP.loading(mount, 'Memuat tim sales...');
        try {
            const rows = await LP.fetchJson('/public/sales-contacts', ANON);
            mount.innerHTML = (rows || []).length
                ? rows.map(function (s) {
                    return '<a href="' + LP.esc(s.whatsapp_link) + '" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-brand-500">'
                        + (s.avatar_url
                            ? LP.fotoSales(s.avatar_url, s.name, 'h-11 w-11')
                            : '<span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">' + LP.esc(String(s.name || '?').charAt(0)) + '</span>')
                        + '<span class="min-w-0"><span class="block truncate font-semibold text-zinc-900">' + LP.esc(s.name) + '</span><span class="block text-xs text-zinc-500">' + LP.esc(s.sales_code) + '</span></span>'
                        + '</a>';
                }).join('')
                : '<p class="text-sm text-zinc-500">Tim sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat tim sales.');
        }
    }

    loadProject();

    LP.fillSettings().then(function (nilai) {
        const aktif = {};
        ['gallery', 'sales'].forEach(function (nama) {
            const node = document.querySelector('[data-seksi="' + nama + '"]');
            if (!node) return;
            const nyala = String(nilai && nilai['public_show_' + nama] === '0' ? '0' : '1') === '1';
            node.classList.toggle('hidden', !nyala);
            aktif[nama] = nyala;
        });
        if (aktif.gallery) loadGallery();
        if (aktif.sales) loadSales();
    }).catch(function () {
        loadGallery();
        loadSales();
    });
</script>
@endpush
