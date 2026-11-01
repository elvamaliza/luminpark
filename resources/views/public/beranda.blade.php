@extends('layouts.public')

@section('title', 'Beranda · Perumahan Lumin Park')

@section('content')
<section class="relative overflow-hidden bg-brand-900 text-white">
    <img id="hero-bg" alt="" class="absolute inset-0 hidden h-full w-full object-cover opacity-35">
    <div class="relative mx-auto max-w-6xl px-4 py-16 sm:py-24">
        <p id="hero-eyebrow" class="text-sm font-semibold uppercase tracking-widest text-brand-100/80">Hunian Minimalis, Asri, dan Islami</p>
        <h1 class="mt-3 max-w-3xl text-4xl font-bold leading-tight sm:text-5xl">
            <span id="hero-title" data-setting="estate_name">Perumahan Lumin Park</span>
        </h1>
        <p id="hero-tagline" class="mt-4 max-w-2xl text-base leading-relaxed text-brand-100/90">
            Jelajahi unit rumah yang tersedia, bandingkan skema pembayaran, dan hubungi tim sales kami tanpa perlu membuat akun.
        </p>

        <form id="hero-search" class="mt-8 flex max-w-xl flex-col gap-3 sm:flex-row">
            <label for="hero-search-input" class="sr-only">Cari unit rumah</label>
            <input id="hero-search-input" type="search" placeholder="Cari tipe atau kode unit, misalnya Modern Tropical"
                   class="w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm text-white outline-none placeholder:text-brand-100/60 focus:border-white/50 focus:bg-white/15">
            <button type="submit" class="shrink-0 rounded-xl bg-white px-6 py-3 text-sm font-bold text-brand-900 hover:bg-brand-50">
                Cari Unit
            </button>
        </form>
    </div>
</section>

<main class="mx-auto max-w-6xl space-y-20 px-4 py-16">

    <section data-seksi="unggulan">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Unit Unggulan</h2>
                <p class="mt-1 text-sm text-zinc-500">Beberapa rumah yang sedang kami tawarkan.</p>
            </div>
            <a href="/unit" class="text-sm font-semibold text-brand-600 hover:text-brand-900">Lihat semua unit &rarr;</a>
        </div>
        <div id="beranda-units" class="mt-6"></div>
    </section>

    <section data-seksi="skema">
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Skema Pembayaran</h2>
        <p class="mt-1 text-sm text-zinc-500">Pilih metode pembiayaan yang paling sesuai dengan kebutuhan Anda.</p>
        <div id="beranda-schemes" class="mt-6"></div>
    </section>

    <section class="grid gap-10 lg:grid-cols-2">
        <div data-seksi="news">
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Kabar Terbaru</h2>
            <div id="beranda-news" class="mt-6 space-y-4"></div>
        </div>
        <div data-seksi="gallery">
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Galeri Perumahan</h2>
            <div id="beranda-gallery" class="mt-6"></div>
        </div>
    </section>

    <section class="rounded-3xl bg-brand-50 p-8 sm:p-10">
        <div data-seksi="sales">
            <h2 class="text-2xl font-bold tracking-tight text-brand-900">Hubungi Sales</h2>
            <p class="mt-1 text-sm text-brand-900/70">Kontak WhatsApp sales siap membantu Anda memilih unit yang tepat.</p>
            <div id="beranda-sales" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></div>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="/kontak" class="rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-800">Kirim Pertanyaan</a>
            <a href="/unit" class="rounded-xl border border-brand-500 px-6 py-3 text-sm font-semibold text-brand-700 hover:bg-white">Jelajahi Unit</a>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const ANON = { anonymous: true };

    function unitCard(u) {
        const image = u.image_url
            ? '<img src="' + LP.esc(u.image_url) + '" alt="' + LP.esc(u.unit_type) + '" loading="lazy" class="h-44 w-full object-cover">'
            : '<div class="flex h-44 w-full items-center justify-center bg-brand-50 text-sm font-semibold text-brand-600">Foto unit segera hadir</div>';
        return '<a href="/unit/' + LP.esc(u.id) + '" class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">'
            + image
            + '<div class="flex flex-1 flex-col gap-2 p-5">'
            + '<div class="flex items-center justify-between gap-2"><span class="text-xs font-bold uppercase tracking-wide text-brand-600">' + LP.esc(u.unit_code) + '</span><span class="text-xs text-zinc-500">' + LP.esc(u.cluster) + '</span></div>'
            + '<h3 class="text-base font-bold text-zinc-900">' + LP.esc(u.unit_type) + '</h3>'
            + '<p class="text-sm text-zinc-500">LT ' + LP.number(u.land_size) + ' m&sup2; &middot; LB ' + LP.number(u.building_size) + ' m&sup2;</p>'
            + '<p class="mt-1 text-lg font-bold text-zinc-900">' + LP.money(u.price) + '</p>'
            + '<span class="mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold text-brand-600">Lihat detail <span aria-hidden="true">&rarr;</span></span>'
            + '</div></a>';
    }

    function pct(value) {
        const n = Number(value || 0);
        return (n % 1 === 0 ? n : n.toFixed(1)) + '%';
    }

    function schemeCard(s) {
        return '<div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">'
            + '<h3 class="text-lg font-bold text-zinc-900">' + LP.esc(s.scheme_name) + '</h3>'
            + '<p class="mt-1 text-xs font-medium uppercase tracking-wide text-brand-600">' + LP.esc(s.scheme_type) + '</p>'
            + '<dl class="mt-4 space-y-2 text-sm">'
            + '<div class="flex justify-between"><dt class="text-zinc-500">Uang muka</dt><dd class="font-semibold text-zinc-900">' + pct(s.down_payment_percent) + '</dd></div>'
            + '<div class="flex justify-between"><dt class="text-zinc-500">Tenor</dt><dd class="font-semibold text-zinc-900">' + LP.number(s.installment_months) + ' bulan</dd></div>'
            + '<div class="flex justify-between"><dt class="text-zinc-500">Suku bunga</dt><dd class="font-semibold text-zinc-900">' + pct(s.interest_rate) + '</dd></div>'
            + '</dl>'
            + (s.description ? '<p class="mt-4 text-sm leading-relaxed text-zinc-500">' + LP.esc(s.description) + '</p>' : '')
            + '</div>';
    }

    async function loadUnits() {
        const mount = document.getElementById('beranda-units');
        LP.loading(mount, 'Memuat unit unggulan...');
        try {
            const rows = await LP.fetchJson('/public/featured-units', ANON);
            const list = rows || [];
            mount.innerHTML = list.length
                ? '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' + list.map(unitCard).join('') + '</div>'
                : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Belum ada unit yang tersedia saat ini.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat daftar unit.');
        }
    }

    async function loadSchemes() {
        const mount = document.getElementById('beranda-schemes');
        LP.loading(mount, 'Memuat skema pembayaran...');
        try {
            const rows = await LP.fetchJson('/public/payment-schemes', ANON);
            const list = (rows || []).slice(0, 4);
            mount.innerHTML = list.length
                ? '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">' + list.map(schemeCard).join('') + '</div>'
                : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Skema pembayaran belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat skema pembayaran.');
        }
    }

    function renderNews(news, mount) {
        mount.innerHTML = news.length
            ? news.map(function (n) {
                return '<article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
                    + '<p class="text-xs font-semibold uppercase tracking-wide text-brand-600">' + LP.date(n.date) + '</p>'
                    + '<h3 class="mt-1 text-base font-bold text-zinc-900">' + LP.esc(n.title) + '</h3>'
                    + (n.image_url ? '<img src="' + LP.esc(n.image_url) + '" alt="' + LP.esc(n.title) + '" loading="lazy" class="mt-3 h-36 w-full rounded-xl object-cover">' : '')
                    + '<p class="mt-2 text-sm leading-relaxed text-zinc-600">' + LP.esc(n.content) + '</p>'
                    + '</article>';
            }).join('')
            : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Belum ada kabar terbaru.</p>';
    }

    function renderGallery(gallery, mount) {
        mount.innerHTML = gallery.length
            ? '<div class="grid gap-4 sm:grid-cols-2">' + gallery.map(function (g) {
                return '<figure class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">'
                    + '<img src="' + LP.esc(g.image_url) + '" alt="' + LP.esc(g.title) + '" loading="lazy" class="h-40 w-full object-cover">'
                    + '<figcaption class="px-4 py-3 text-sm font-medium text-zinc-700">' + LP.esc(g.title) + '</figcaption>'
                    + '</figure>';
            }).join('') + '</div>'
            : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Galeri belum tersedia.</p>';
    }

    async function loadNewsAndGallery(aktif) {
        const newsMount = document.getElementById('beranda-news');
        const galleryMount = document.getElementById('beranda-gallery');
        if (aktif.news) LP.loading(newsMount, 'Memuat kabar terbaru...');
        if (aktif.gallery) LP.loading(galleryMount, 'Memuat galeri...');
        try {
            const data = await LP.fetchJson('/public/news-gallery', ANON);
            const news = (data && data.news) || [];
            const gallery = (data && data.gallery) || [];
            if (aktif.news) renderNews(news, newsMount);
            if (aktif.gallery) renderGallery(gallery, galleryMount);
        } catch (e) {
            if (aktif.news) LP.errorBox(newsMount, e.message || 'Gagal memuat kabar terbaru.');
            if (aktif.gallery) LP.errorBox(galleryMount, 'Galeri gagal dimuat.');
        }
    }

    async function loadSales() {
        const mount = document.getElementById('beranda-sales');
        LP.loading(mount, 'Memuat kontak sales...');
        try {
            const rows = await LP.fetchJson('/public/sales-contacts', ANON);
            mount.innerHTML = (rows || []).length
                ? rows.map(function (s) {
                    return '<a href="' + LP.esc(s.whatsapp_link) + '" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm transition hover:border-brand-500">'
                        + '<span class="grid h-11 w-11 place-items-center rounded-full bg-brand-50 text-brand-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .2-3.2-.7-2.7-1.1-4.4-3.8-4.6-4-.1-.2-1-1.4-1-2.6s.6-1.8.8-2c.2-.2.4-.3.6-.3h.5c.2 0 .4 0 .5.4l.8 2c.1.2 0 .3-.1.5l-.4.6c-.2.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.4 0 .6-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.2.1.7-.1 1z"/></svg></span>'
                        + '<span><span class="block text-sm font-bold text-zinc-900">' + LP.esc(s.name) + '</span><span class="block text-xs text-zinc-500">' + LP.esc(s.sales_code) + '</span></span>'
                        + '</a>';
                }).join('')
                : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Kontak sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat kontak sales.');
        }
    }

    document.getElementById('hero-search').addEventListener('submit', function (event) {
        event.preventDefault();
        const q = document.getElementById('hero-search-input').value.trim();
        location.href = '/unit' + (q ? '?cari=' + encodeURIComponent(q) : '');
    });

    function terapkanTampilan(nilai) {
        if (nilai.public_hero_eyebrow) document.getElementById('hero-eyebrow').textContent = nilai.public_hero_eyebrow;
        if (nilai.public_hero_title) document.getElementById('hero-title').textContent = nilai.public_hero_title;
        if (nilai.public_hero_tagline) document.getElementById('hero-tagline').textContent = nilai.public_hero_tagline;

        if (nilai.public_hero_image_url) {
            const bg = document.getElementById('hero-bg');
            bg.src = nilai.public_hero_image_url;
            bg.classList.remove('hidden');
        }

        const aktif = {};
        ['unggulan', 'skema', 'news', 'gallery', 'sales'].forEach(function (nama) {
            const node = document.querySelector('[data-seksi="' + nama + '"]');
            if (!node) return;
            const nyala = String(nilai['public_show_' + nama] === '0' ? '0' : '1') === '1';
            node.classList.toggle('hidden', !nyala);
            aktif[nama] = nyala;
        });
        return aktif;
    }

    LP.fillSettings().then(function (nilai) {
        const aktif = terapkanTampilan(nilai || {});
        if (aktif.unggulan) loadUnits();
        if (aktif.skema) loadSchemes();
        if (aktif.news || aktif.gallery) loadNewsAndGallery(aktif);
        if (aktif.sales) loadSales();
    }).catch(function () {
        loadUnits();
        loadSchemes();
        loadNewsAndGallery({ news: true, gallery: true });
        loadSales();
    });
</script>
@endpush
