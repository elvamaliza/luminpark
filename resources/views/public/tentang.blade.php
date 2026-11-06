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

    <section data-seksi="skema">
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Skema Pembayaran</h2>
        <p class="mt-1 text-sm text-zinc-500">Tiga metode pembiayaan yang kami sediakan untuk memudahkan kepemilikan rumah.</p>
        <div id="tentang-schemes" class="mt-6"></div>
    </section>

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

    function pct(value) {
        const n = Number(value || 0);
        return (n % 1 === 0 ? n : n.toFixed(1)) + '%';
    }

    async function loadProject() {
        const mount = document.getElementById('tentang-project');
        LP.loading(mount, 'Memuat identitas project...');
        try {
            const rows = await LP.fetchJson('/public/units', ANON);
            const list = rows || [];
            if (!list.length) {
                mount.innerHTML = '<p class="text-sm text-zinc-500">Belum ada data project yang dapat ditampilkan.</p>';
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
            mount.innerHTML = '<h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Identitas Project</h3>'
                + '<p class="mt-2 text-lg font-bold text-zinc-900">' + LP.esc(list[0].project) + '</p>'
                + '<dl class="mt-4 space-y-3 text-sm">'
                + '<div class="flex justify-between"><dt class="text-zinc-500">Jumlah unit</dt><dd class="font-semibold text-zinc-900">' + LP.number(list.length) + '</dd></div>'
                + '<div><dt class="text-zinc-500">Tersedia di cluster</dt><dd class="mt-1 flex flex-wrap gap-1">'
                + clusters.map(function (c) { return '<span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">' + LP.esc(c) + '</span>'; }).join('')
                + '</dd></div>'
                + '<div><dt class="text-zinc-500">Tipe rumah</dt><dd class="mt-1 flex flex-wrap gap-1">'
                + tipe.map(function (t) { return '<span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">' + LP.esc(t) + '</span>'; }).join('')
                + '</dd></div>'
                + '</dl>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat identitas project.');
        }
    }

    async function loadSchemes() {
        const mount = document.getElementById('tentang-schemes');
        LP.loading(mount, 'Memuat skema pembayaran...');
        try {
            const rows = await LP.fetchJson('/public/payment-schemes', ANON);
            const list = (rows || []).slice(0, 3);
            if (!list.length) {
                mount.innerHTML = '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Skema pembayaran belum tersedia.</p>';
                return;
            }
            const isi = list.map(function (s) {
                return '<tr class="border-t border-zinc-100">'
                    + '<td class="px-4 py-3 text-sm font-semibold text-zinc-900">' + LP.esc(s.scheme_name) + '</td>'
                    + '<td class="px-4 py-3 text-right text-sm tabular-nums text-zinc-700">' + pct(s.down_payment_percent) + '</td>'
                    + '<td class="px-4 py-3 text-right text-sm tabular-nums text-zinc-700">' + LP.number(s.installment_months) + ' bln</td>'
                    + '<td class="px-4 py-3 text-right text-sm tabular-nums text-zinc-700">' + pct(s.interest_rate) + '</td>'
                    + '<td class="px-4 py-3 text-sm text-zinc-600">' + (s.description ? LP.esc(s.description) : '-') + '</td>'
                    + '</tr>';
            }).join('');
            mount.innerHTML = '<div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm"><table class="min-w-full"><thead class="bg-zinc-50"><tr>'
                + '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Skema</th>'
                + '<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Uang Muka</th>'
                + '<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Tenor</th>'
                + '<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Bunga</th>'
                + '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Keterangan</th>'
                + '</tr></thead><tbody>' + isi + '</tbody></table></div>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat skema pembayaran.');
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
                        + '<span class="grid h-11 w-11 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">' + LP.esc(String(s.name || '?').charAt(0)) + '</span>'
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
        ['skema', 'gallery', 'sales'].forEach(function (nama) {
            const node = document.querySelector('[data-seksi="' + nama + '"]');
            if (!node) return;
            const nyala = String(nilai && nilai['public_show_' + nama] === '0' ? '0' : '1') === '1';
            node.classList.toggle('hidden', !nyala);
            aktif[nama] = nyala;
        });
        if (aktif.skema) loadSchemes();
        if (aktif.gallery) loadGallery();
        if (aktif.sales) loadSales();
    }).catch(function () {
        loadSchemes();
        loadGallery();
        loadSales();
    });
</script>
@endpush
