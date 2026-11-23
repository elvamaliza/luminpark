@extends('layouts.public')

@section('title', 'Daftar Unit · Perumahan Lumin Park')

@section('content')
<section class="border-b border-zinc-100 bg-brand-50">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-3xl font-bold tracking-tight text-brand-900">Daftar Unit Rumah</h1>
        <p class="mt-2 max-w-2xl text-sm text-brand-900/70">Saring unit melalui pilihan proyek dan urutan harga untuk menemukan rumah yang sesuai.</p>
    </div>
</section>

<main class="mx-auto max-w-6xl px-4 py-12">
    {{-- Revisi 38: kotak pencarian kode/tipe unit dihapus, hanya penapis proyek dan harga yang tersisa. --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="cluster" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Proyek</label>
                <select id="cluster" class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    <option value="">Semua proyek</option>
                </select>
            </div>
            <div>
                <label for="sort" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Urutkan harga</label>
                <select id="sort" class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    <option value="">Tanpa urutan</option>
                    <option value="asc">Termurah dulu</option>
                    <option value="desc">Termahal dulu</option>
                </select>
            </div>
        </div>
        <p id="unit-count" class="mt-4 text-sm text-zinc-500"></p>
    </div>

    <div id="unit-grid" class="mt-8"></div>
</main>
@endsection

@push('scripts')
<script>
    const ANON = { anonymous: true };
    let SEMUA_UNIT = [];

    const gridMount = document.getElementById('unit-grid');
    const clusterSelect = document.getElementById('cluster');
    const sortSelect = document.getElementById('sort');
    const countLabel = document.getElementById('unit-count');

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

    function isiCluster() {
        const nama = [];
        SEMUA_UNIT.forEach(function (u) {
            if (u.cluster && nama.indexOf(u.cluster) === -1) nama.push(u.cluster);
        });
        nama.sort();
        nama.forEach(function (c) {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            clusterSelect.appendChild(opt);
        });
    }

    function render() {
        const cluster = clusterSelect.value;
        const sort = sortSelect.value;

        let hasil = SEMUA_UNIT.filter(function (u) {
            return !cluster || u.cluster === cluster;
        });

        if (sort === 'asc') hasil.sort(function (a, b) { return Number(a.price) - Number(b.price); });
        if (sort === 'desc') hasil.sort(function (a, b) { return Number(b.price) - Number(a.price); });

        countLabel.textContent = hasil.length + ' unit ditemukan.';

        if (!hasil.length) {
            gridMount.innerHTML = '<div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center"><p class="text-sm text-zinc-500">Tidak ada unit pada proyek ini. Coba pilih proyek lain atau tukar ke "Semua proyek".</p></div>';
            return;
        }
        gridMount.innerHTML = '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' + hasil.map(unitCard).join('') + '</div>';
    }

    async function init() {
        const params = new URLSearchParams(window.location.search);

        LP.loading(gridMount, 'Memuat daftar unit...');
        try {
            SEMUA_UNIT = (await LP.fetchJson('/public/units', ANON)) || [];
            isiCluster();

            // Penanda pada peta beranda menautkan /unit?proyek=<nama proyek>.
            const proyek = params.get('proyek');
            if (proyek && Array.from(clusterSelect.options).some((o) => o.value === proyek)) {
                clusterSelect.value = proyek;
            }

            render();
        } catch (e) {
            LP.errorBox(gridMount, e.message || 'Gagal memuat daftar unit.');
        }
    }

    clusterSelect.addEventListener('change', render);
    sortSelect.addEventListener('change', render);

    init();
</script>
@endpush
