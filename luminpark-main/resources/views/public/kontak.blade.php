@extends('layouts.public')

@section('title', 'Kontak · Perumahan Lumin Park')

@section('content')
<section class="border-b border-zinc-100 bg-brand-50">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-3xl font-bold tracking-tight text-brand-900">Hubungi Kami</h1>
        <p class="mt-2 max-w-2xl text-sm text-brand-900/70">Silakan hubungi tim marketing melalui telepon atau email di
            bawah ini, atau langsung menghubungi sales lewat WhatsApp.</p>
    </div>
</section>

<main class="mx-auto grid max-w-6xl gap-8 px-4 py-12 lg:grid-cols-2">

    {{-- Kolom informasi pemasaran --}}
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-xl font-bold text-zinc-900">Informasi Pemasaran</h2>
        <dl class="mt-6 space-y-4 text-sm">
            <div>
                <dt class="font-semibold text-zinc-500">Nama Perumahan</dt>
                <dd class="text-zinc-800" data-setting="estate_name">Perumahan Lumin Park</dd>
            </div>
            <div>
                <dt class="font-semibold text-zinc-500">Alamat</dt>
                <dd class="text-zinc-800" data-setting="estate_address">Jalan Lubuk Minturun, Koto Tangah, Kota Padang, Sumatera Barat</dd>
            </div>
            <div>
                <dt class="font-semibold text-zinc-500">Telepon</dt>
                <dd><a data-setting="estate_phone" data-link="tel" aria-label="Nomor telepon" class="font-medium text-brand-600 hover:text-brand-900"></a></dd>
            </div>
            <div>
                <dt class="font-semibold text-zinc-500">Email</dt>
                <dd><a data-setting="estate_email" data-link="mailto" aria-label="Email" class="font-medium text-brand-600 hover:text-brand-900"></a></dd>
            </div>
            <div>
                <dt class="font-semibold text-zinc-500">Jam Operasional</dt>
                <dd class="text-zinc-800" data-setting="estate_hours">Senin - Sabtu, 08.00 - 17.00 WIB</dd>
            </div>
        </dl>
    </section>

    {{-- Kolom narahubung sales --}}
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-xl font-bold text-zinc-900">Sales Kami</h2>
        <p class="mt-1 text-sm text-zinc-500">Pilih salah satu sales untuk melanjutkan pertanyaan lewat WhatsApp.</p>
        <div id="kontak-sales" class="mt-6 space-y-3"></div>
        <a href="/unit" class="mt-6 inline-flex rounded-xl border border-brand-500 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">Lihat Daftar Unit</a>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const ANON = { anonymous: true };

    async function isiSales() {
        const mount = document.getElementById('kontak-sales');
        LP.loading(mount, 'Memuat sales...');
        try {
            const rows = await LP.fetchJson('/public/sales-contacts', ANON);
            mount.innerHTML = (rows || []).length
                ? rows.map(function (s) {
                    // Revisi 36: foto sales tampil penuh pada kartu narahubung, bukan inisial saja.
                    return '<a href="' + LP.esc(s.whatsapp_link) + '" target="_blank" rel="noopener" class="flex items-center gap-4 rounded-2xl border border-zinc-200 px-4 py-4 text-sm transition hover:border-brand-500">'
                        + (s.avatar_url
                            ? LP.fotoSales(s.avatar_url, s.name, 'h-20 w-20')
                            : '<span class="grid h-20 w-20 shrink-0 place-items-center rounded-full bg-brand-50 text-2xl font-bold text-brand-700">' + LP.esc(String(s.name || '?').charAt(0)) + '</span>')
                        + '<span class="min-w-0 flex-1">'
                        + '<span class="block truncate font-bold text-zinc-900">' + LP.esc(s.name) + '</span>'
                        + '<span class="block text-xs text-zinc-500">' + LP.esc(s.sales_code) + '</span>'
                        + '<span class="mt-1 block text-xs font-medium text-zinc-600">' + LP.esc(s.whatsapp_number || '') + '</span>'
                        + '</span>'
                        + '<span class="shrink-0 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white">WhatsApp</span>'
                        + '</a>';
                }).join('')
                : '<p class="text-sm text-zinc-500">Kontak sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat kontak sales.');
        }
    }

    isiSales();
</script>
@endpush
