@extends('layouts.public')

@section('title', 'Skema Pembayaran · Perumahan Lumin Park')

@section('content')
<section class="border-b border-zinc-100 bg-brand-50">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-3xl font-bold tracking-tight text-brand-900">Skema Pembayaran</h1>
        <p class="mt-2 max-w-2xl text-sm text-brand-900/70">Beberapa metode pembiayaan resmi yang tersedia di Perumahan Lumin Park. Pilih yang paling sesuai dengan rencana Anda.</p>
    </div>
</section>

<main class="mx-auto max-w-6xl space-y-12 px-4 py-12">
    <section>
        <h2 class="text-xl font-bold text-zinc-900">Pilihan Skema</h2>
        <div id="skema-list" class="mt-6"></div>
    </section>

    <section>
        <h2 class="text-xl font-bold text-zinc-900">Perbandingan Ringkas</h2>
        <div id="skema-table" class="mt-6"></div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-6">
        <h2 class="text-lg font-bold text-zinc-900">Cara Membaca Skema</h2>
        <ul class="mt-3 space-y-2 text-sm text-zinc-600">
            <li><span class="font-semibold text-zinc-900">Uang muka (DP)</span> adalah persentase dari harga unit yang dibayarkan di awal.</li>
            <li><span class="font-semibold text-zinc-900">Tenor</span> adalah jangka waktu angsuran dalam bulan.</li>
            <li><span class="font-semibold text-zinc-900">Suku bunga</span> 0% berarti angsuran dihitung secara flat tanpa bunga bank.</li>
        </ul>
        <p class="mt-4 text-sm text-zinc-500">Ingin melihat angka untuk unit tertentu? Buka halaman detail unit untuk simulasi otomatis.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="/unit" class="rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">Lihat Daftar Unit</a>
            <a href="/kontak" class="rounded-xl border border-zinc-300 px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">Tanya Sales</a>
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

    function skemaCard(s) {
        return '<div class="flex flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">'
            + '<h3 class="text-lg font-bold text-zinc-900">' + LP.esc(s.scheme_name) + '</h3>'
            + '<p class="mt-1 text-xs font-semibold uppercase tracking-wide text-brand-600">' + LP.esc(s.scheme_type) + '</p>'
            + '<dl class="mt-4 grid grid-cols-3 gap-3 text-center">'
            + '<div class="rounded-xl bg-brand-50 p-3"><dt class="text-[11px] uppercase tracking-wide text-brand-900/60">DP</dt><dd class="mt-1 text-lg font-bold text-brand-900">' + pct(s.down_payment_percent) + '</dd></div>'
            + '<div class="rounded-xl bg-brand-50 p-3"><dt class="text-[11px] uppercase tracking-wide text-brand-900/60">Tenor</dt><dd class="mt-1 text-lg font-bold text-brand-900">' + LP.number(s.installment_months) + '</dd></div>'
            + '<div class="rounded-xl bg-brand-50 p-3"><dt class="text-[11px] uppercase tracking-wide text-brand-900/60">Bunga</dt><dd class="mt-1 text-lg font-bold text-brand-900">' + pct(s.interest_rate) + '</dd></div>'
            + '</dl>'
            + '<p class="mt-4 text-sm leading-relaxed text-zinc-600">' + (s.description ? LP.esc(s.description) : 'Hubungi sales untuk detail skema ini.') + '</p>'
            + '<a href="/unit" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-600">Pilih unit untuk simulasi <span aria-hidden="true">&rarr;</span></a>'
            + '</div>';
    }

    function skemaTable(rows) {
        const isi = rows.map(function (s) {
            return '<tr class="border-t border-zinc-100">'
                + '<td class="px-4 py-3 text-sm font-semibold text-zinc-900">' + LP.esc(s.scheme_name) + '</td>'
                + '<td class="px-4 py-3 text-sm text-zinc-600">' + LP.esc(s.scheme_type) + '</td>'
                + '<td class="px-4 py-3 text-right text-sm tabular-nums text-zinc-700">' + pct(s.down_payment_percent) + '</td>'
                + '<td class="px-4 py-3 text-right text-sm tabular-nums text-zinc-700">' + LP.number(s.installment_months) + ' bln</td>'
                + '<td class="px-4 py-3 text-right text-sm tabular-nums text-zinc-700">' + pct(s.interest_rate) + '</td>'
                + '</tr>';
        }).join('');
        return '<div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm">'
            + '<table class="min-w-full"><thead class="bg-zinc-50"><tr>'
            + '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Skema</th>'
            + '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Jenis</th>'
            + '<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Uang Muka</th>'
            + '<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Tenor</th>'
            + '<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">Bunga</th>'
            + '</tr></thead><tbody>' + isi + '</tbody></table></div>';
    }

    async function init() {
        const listMount = document.getElementById('skema-list');
        const tableMount = document.getElementById('skema-table');
        LP.loading(listMount, 'Memuat skema pembayaran...');
        LP.loading(tableMount, 'Menyiapkan tabel perbandingan...');
        try {
            const rows = await LP.fetchJson('/public/payment-schemes', ANON);
            const list = rows || [];
            if (!list.length) {
                listMount.innerHTML = '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Skema pembayaran belum tersedia.</p>';
                tableMount.innerHTML = '';
                return;
            }
            listMount.innerHTML = '<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' + list.map(skemaCard).join('') + '</div>';
            tableMount.innerHTML = skemaTable(list);
        } catch (e) {
            LP.errorBox(listMount, e.message || 'Gagal memuat skema pembayaran.');
            tableMount.innerHTML = '';
        }
    }

    init();
</script>
@endpush
