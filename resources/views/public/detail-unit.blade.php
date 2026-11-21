@extends('layouts.public')

@section('title', 'Detail Unit · Perumahan Lumin Park')

@section('content')
<main class="mx-auto max-w-5xl px-4 py-8">
    <nav class="mb-6 text-sm text-zinc-500" aria-label="Breadcrumb">
        <a href="/" class="hover:text-brand-600">Beranda</a>
        <span aria-hidden="true">&nbsp;/&nbsp;</span>
        <a href="/unit" class="hover:text-brand-600">Daftar Unit</a>
        <span aria-hidden="true">&nbsp;/&nbsp;</span>
        <span id="crumb-unit" class="font-medium text-zinc-700">Detail unit</span>
    </nav>

    <div id="unit-detail"></div>

    <section class="mt-10 rounded-2xl border border-brand-100 bg-brand-50 p-6 sm:p-8">
        <h2 class="text-lg font-bold text-brand-900">Hubungi sales untuk penawaran unit ini</h2>
        <p class="mt-1 text-sm text-brand-900/70">Terhubung langsung dengan tim sales kami melalui WhatsApp.</p>
        <div id="detail-sales" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
        <div class="mt-6">
            <a href="/kontak" class="inline-flex rounded-xl border border-brand-500 px-5 py-3 text-sm font-semibold text-brand-700 transition hover:bg-white">Lihat Semua Narahubung</a>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const ANON = { anonymous: true };
    let SKEMA = [];
    let HARGA = 0;

    function pct(value) {
        const n = Number(value || 0);
        return (n % 1 === 0 ? n : n.toFixed(1)) + '%';
    }

    function specRow(label, valueHtml) {
        return '<div class="flex items-center justify-between gap-4 border-b border-zinc-100 py-3 text-sm last:border-b-0">'
            + '<dt class="text-zinc-500">' + LP.esc(label) + '</dt>'
            + '<dd class="text-right font-semibold text-zinc-900">' + valueHtml + '</dd>'
            + '</div>';
    }

    function unitVisual(u) {
        if (u.image_url) {
            return '<img src="' + LP.esc(u.image_url) + '" alt="' + LP.esc(u.unit_type) + '" class="h-72 w-full rounded-2xl border border-zinc-200 object-cover shadow-sm">';
        }
        return '<div class="flex h-72 w-full items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-brand-50 text-sm font-semibold text-brand-600">Foto unit belum tersedia</div>';
    }

    function renderDetail(u) {
        document.getElementById('crumb-unit').textContent = u.unit_code || 'Detail unit';

        const spec = '<dl class="mt-6 rounded-2xl border border-zinc-200 bg-white px-5 shadow-sm">'
            + specRow('Blok / Kode Unit', LP.esc(u.unit_code))
            + specRow('Tipe', LP.esc(u.unit_type))
            + specRow('Luas Tanah', LP.number(u.land_size) + ' m&sup2;')
            + specRow('Luas Bangunan', LP.number(u.building_size) + ' m&sup2;')
            + specRow('Proyek', LP.esc(u.cluster))
            + specRow('Perumahan', LP.esc(u.project))
            + specRow('Harga', LP.money(u.price))
            + '</dl>';

        const deskripsi = u.description
            ? '<div class="mt-6"><h2 class="text-lg font-bold text-zinc-900">Deskripsi Unit</h2><p class="mt-2 text-sm leading-relaxed text-zinc-600">' + LP.esc(u.description) + '</p></div>'
            : '';

        const opsiSkema = SKEMA.map(function (s) {
            return '<option value="' + LP.esc(s.id) + '">' + LP.esc(s.scheme_name) + '</option>';
        }).join('');

        const simulasi = '<div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">'
            + '<h2 class="text-lg font-bold text-zinc-900">Simulasi Pembayaran</h2>'
            + '<p class="mt-1 text-sm text-zinc-500">Angka di bawah adalah perkiraan berdasarkan skema yang dipilih. Nilai final mengikuti kesepakatan dengan developer.</p>'
            + (SKEMA.length
                ? '<div class="mt-4"><label for="pilih-skema" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Pilih skema</label>'
                    + '<select id="pilih-skema" class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:max-w-xs">' + opsiSkema + '</select></div>'
                    + '<div id="hasil-simulasi" class="mt-5"></div>'
                : '<p class="mt-4 text-sm text-zinc-500">Skema pembayaran belum tersedia untuk disimulasikan.</p>')
            + '</div>';

        const detail = document.getElementById('unit-detail');
        detail.innerHTML = '<div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-8">'
            + unitVisual(u)
            + '<div class="mt-6 flex flex-wrap items-end justify-between gap-3">'
            + '<div><span class="text-xs font-bold uppercase tracking-wide text-brand-600">' + LP.esc(u.unit_code) + '</span>'
            + '<h1 class="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">' + LP.esc(u.unit_type) + '</h1>'
            + '<p class="mt-1 text-sm text-zinc-500">' + LP.esc(u.cluster) + '</p></div>'
            + '<div class="text-right"><p class="text-xs uppercase tracking-wide text-zinc-400">Harga</p>'
            + '<p class="text-2xl font-bold text-brand-700">' + LP.money(u.price) + '</p></div>'
            + '</div>'
            + spec + deskripsi + simulasi + '</div>';

        if (SKEMA.length) {
            const select = document.getElementById('pilih-skema');
            select.addEventListener('change', jalankanSimulasi);
            jalankanSimulasi();
        }
    }

    function jalankanSimulasi() {
        const select = document.getElementById('pilih-skema');
        const mount = document.getElementById('hasil-simulasi');
        if (!select || !mount) return;
        const scheme = SKEMA.filter(function (s) { return String(s.id) === String(select.value); })[0];
        if (!scheme) { mount.innerHTML = ''; return; }

        const dpPersen = Number(scheme.down_payment_percent || 0);
        const uangMuka = HARGA * dpPersen / 100;
        const sisa = HARGA - uangMuka;
        const bulan = Number(scheme.installment_months || 0);

        let baris = '<div class="space-y-2 rounded-xl bg-brand-50 p-5 text-sm">'
            + '<div class="flex justify-between"><span class="text-zinc-600">Uang muka (' + pct(dpPersen) + ')</span><span class="font-bold text-zinc-900">' + LP.money(Math.round(uangMuka)) + '</span></div>'
            + '<div class="flex justify-between"><span class="text-zinc-600">Sisa pembiayaan</span><span class="font-semibold text-zinc-800">' + LP.money(Math.round(sisa)) + '</span></div>'
            + '<div class="flex justify-between"><span class="text-zinc-600">Suku bunga</span><span class="font-semibold text-zinc-800">' + pct(scheme.interest_rate) + '</span></div>';

        if (bulan > 0) {
            // Bunga flat yang sama dengan PaymentScheme::calculateBreakdown() di backend.
            const bunga = sisa * Number(scheme.interest_rate || 0) / 100 * (bulan / 12);
            const angsuran = (sisa + bunga) / bulan;
            baris += '<div class="flex justify-between"><span class="text-zinc-600">Total bunga (' + LP.number(bulan) + ' bln)</span><span class="font-semibold text-zinc-800">' + LP.money(Math.round(bunga)) + '</span></div>';
            baris += '<div class="mt-2 flex justify-between border-t border-brand-100 pt-3"><span class="text-zinc-600">Perkiraan angsuran / bulan</span>'
                + '<span class="font-bold text-brand-700">' + LP.money(Math.round(angsuran)) + ' &times; ' + LP.number(bulan) + ' bln</span></div>';
        } else {
            baris += '<p class="mt-2 border-t border-brand-100 pt-3 text-zinc-600">Skema ini dilunasi sekaligus tanpa angsuran bulanan.</p>';
        }

        baris += '<p class="pt-2 text-xs text-zinc-400">Semua nilai merupakan perkiraan (flat), belum termasuk biaya proses, pajak, dan pungutan lainnya.</p></div>';
        mount.innerHTML = baris;
    }

    async function loadSales(u) {
        const mount = document.getElementById('detail-sales');
        LP.loading(mount, 'Memuat kontak sales...');
        try {
            const rows = await LP.fetchJson('/public/sales-contacts', ANON);
            const pesan = encodeURIComponent('Halo, saya tertarik dengan unit ' + (u.unit_code || '') + ' (' + (u.unit_type || '') + '). Boleh dibantu?');
            mount.innerHTML = (rows || []).length
                ? rows.map(function (s) {
                    const link = (s.whatsapp_link || '') + '?text=' + pesan;
                    return '<a href="' + LP.esc(link) + '" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-brand-100 bg-white p-3 text-sm shadow-sm transition hover:border-brand-500">'
                        + (s.avatar_url
                            ? LP.fotoSales(s.avatar_url, s.name, 'h-9 w-9')
                            : '<span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .2-3.2-.7-2.7-1.1-4.4-3.8-4.6-4-.1-.2-1-1.4-1-2.6s.6-1.8.8-2c.2-.2.4-.3.6-.3h.5c.2 0 .4 0 .5.4l.8 2c.1.2 0 .3-.1.5l-.4.6c-.2.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.4 0 .6-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.2.1.7-.1 1z"/></svg></span>')
                        + '<span class="min-w-0"><span class="block truncate font-semibold text-zinc-900">' + LP.esc(s.name) + '</span><span class="block text-xs text-zinc-500">' + LP.esc(s.sales_code) + '</span></span>'
                        + '</a>';
                }).join('')
                : '<p class="text-sm text-zinc-500">Kontak sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat kontak sales.');
        }
    }

    async function init() {
        const detailMount = document.getElementById('unit-detail');
        const cocok = window.location.pathname.match(/\/unit\/(\d+)/);
        if (!cocok) {
            LP.errorBox(detailMount, 'Tautan unit tidak valid. Silakan pilih unit dari daftar unit.');
            return;
        }
        const id = cocok[1];

        LP.loading(detailMount, 'Memuat detail unit...');
        try {
            const hasil = await Promise.all([
                LP.fetchJson('/public/units/' + id, ANON),
                LP.fetchJson('/public/payment-schemes', ANON).catch(function () { return []; }),
            ]);
            const u = hasil[0];
            SKEMA = hasil[1] || [];
            HARGA = Number(u.price || 0);
            renderDetail(u);
            loadSales(u);
        } catch (e) {
            if (e && e.status === 404) {
                detailMount.innerHTML = '<div class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center"><h2 class="text-lg font-semibold text-amber-900">Unit tidak ditemukan</h2><p class="mt-2 text-sm text-amber-800">Unit yang Anda cari sudah tidak tersedia atau tautannya salah.</p><a href="/unit" class="mt-4 inline-flex rounded-xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white">Kembali ke Daftar Unit</a></div>';
            } else {
                LP.errorBox(detailMount, e.message || 'Gagal memuat detail unit.');
            }
        }
    }

    init();
</script>
@endpush
