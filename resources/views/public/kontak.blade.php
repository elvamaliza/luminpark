@extends('layouts.public')

@section('title', 'Kontak · Perumahan Lumin Park')

@section('content')
<section class="border-b border-zinc-100 bg-brand-50">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-3xl font-bold tracking-tight text-brand-900">Hubungi Kami</h1>
        <p class="mt-2 max-w-2xl text-sm text-brand-900/70">Kirim pertanyaan atau permintaan penawaran. Tim kami akan menghubungi Anda secepatnya.</p>
    </div>
</section>

<main class="mx-auto grid max-w-6xl gap-8 px-4 py-12 lg:grid-cols-5">

    {{-- Kolom formulir --}}
    <section class="lg:col-span-3">
        <form id="contact-form" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8" novalidate>
            <h2 class="text-xl font-bold text-zinc-900">Formulir Kontak</h2>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="mb-1.5 block text-sm font-medium text-zinc-700">Nama <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" required autocomplete="name"
                           class="w-full rounded-xl border border-zinc-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    <span data-error="name" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
                </div>

                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-medium text-zinc-700">No. HP <span class="text-rose-600">*</span></label>
                    <input id="phone" name="phone" type="tel" required autocomplete="tel"
                           class="w-full rounded-xl border border-zinc-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    <span data-error="phone" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-zinc-700">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email"
                           class="w-full rounded-xl border border-zinc-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    <span data-error="email" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
                </div>

                <div class="sm:col-span-2">
                    <label for="address" class="mb-1.5 block text-sm font-medium text-zinc-700">Alamat</label>
                    <input id="address" name="address" type="text" autocomplete="street-address"
                           class="w-full rounded-xl border border-zinc-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    <span data-error="address" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
                </div>

                <div class="sm:col-span-2">
                    <label for="unit_id" class="mb-1.5 block text-sm font-medium text-zinc-700">Unit Diminati</label>
                    <select id="unit_id" name="unit_id" class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        <option value="">Belum menentukan unit</option>
                    </select>
                    <span data-error="unit_id" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
                </div>

                <div class="sm:col-span-2">
                    <label for="note" class="mb-1.5 block text-sm font-medium text-zinc-700">Pesan</label>
                    <textarea id="note" name="note" rows="4"
                              class="w-full rounded-xl border border-zinc-300 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"></textarea>
                    <span data-error="note" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
                </div>
            </div>

            <div class="mt-6">
                <button id="kirim-btn" type="submit"
                        class="w-full rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    Kirim Pesan
                </button>
            </div>
        </form>
    </section>

    {{-- Kolom informasi --}}
    <aside class="space-y-6 lg:col-span-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-zinc-900">Informasi Pemasaran</h2>
            <dl class="mt-4 space-y-4 text-sm">
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
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-zinc-900">Sales Kami</h2>
            <p class="mt-1 text-sm text-zinc-500">Atau hubungi sales langsung lewat WhatsApp.</p>
            <div id="kontak-sales" class="mt-4 space-y-3"></div>
        </div>
    </aside>
</main>
@endsection

@push('scripts')
<script>
    const ANON = { anonymous: true };
    const form = document.getElementById('contact-form');
    const kirimBtn = document.getElementById('kirim-btn');
    const unitSelect = document.getElementById('unit_id');

    function tampilkanError(errors) {
        form.querySelectorAll('[data-error]').forEach(function (node) {
            node.classList.add('hidden');
            node.textContent = '';
        });
        if (!errors) return;
        Object.keys(errors).forEach(function (key) {
            const slot = form.querySelector('[data-error="' + key + '"]');
            if (slot) {
                slot.textContent = (errors[key] || []).join(', ');
                slot.classList.remove('hidden');
            }
        });
    }

    async function isiUnit() {
        try {
            const rows = await LP.fetchJson('/public/units', ANON);
            (rows || []).forEach(function (u) {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.unit_code + ' - ' + u.unit_type + ' (' + LP.money(u.price) + ')';
                unitSelect.appendChild(opt);
            });
            const target = new URLSearchParams(window.location.search).get('unit_id');
            if (target) unitSelect.value = target;
        } catch (e) {
            LP.toast('Unit tidak dapat dimuat, silakan isi pesan tanpa memilih unit.', 'info');
        }
    }

    async function isiSales() {
        const mount = document.getElementById('kontak-sales');
        LP.loading(mount, 'Memuat sales...');
        try {
            const rows = await LP.fetchJson('/public/sales-contacts', ANON);
            mount.innerHTML = (rows || []).length
                ? rows.map(function (s) {
                    return '<a href="' + LP.esc(s.whatsapp_link) + '" target="_blank" rel="noopener" class="flex items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 text-sm transition hover:border-brand-500">'
                        + '<span><span class="block font-semibold text-zinc-900">' + LP.esc(s.name) + '</span><span class="block text-xs text-zinc-500">' + LP.esc(s.sales_code) + '</span></span>'
                        + '<span class="font-semibold text-brand-600">WhatsApp</span>'
                        + '</a>';
                }).join('')
                : '<p class="text-sm text-zinc-500">Kontak sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat kontak sales.');
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        tampilkanError(null);

        const data = {
            name: document.getElementById('name').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            email: document.getElementById('email').value.trim(),
            address: document.getElementById('address').value.trim(),
            note: document.getElementById('note').value.trim(),
            unit_id: unitSelect.value,
        };

        if (!data.name || !data.phone) {
            LP.toast('Nama dan No. HP wajib diisi.', 'error');
            return;
        }

        LP.withBusy(kirimBtn, async function () {
            try {
                await LP.http.post('/public/contact', data, ANON);
                LP.toast('Terima kasih! Pesan Anda sudah terkirim, tim kami akan segera menghubungi.', 'success');
                form.reset();
            } catch (e) {
                if (e && e.errors) {
                    tampilkanError(e.errors);
                    const pesan = Object.values(e.errors).flat().join('. ');
                    LP.toast(pesan || e.message || 'Pesan belum terkirim.', 'error');
                } else {
                    LP.toast(e.message || 'Pesan belum terkirim. Coba lagi sebentar lagi.', 'error');
                }
            }
        });
    });

    isiUnit();
    isiSales();
</script>
@endpush
