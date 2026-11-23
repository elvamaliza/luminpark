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

        {{-- Revisi 38: kotak pencarian kode/tipe unit dihapus; tautan langsung ke daftar unit dan sales. --}}
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="/unit" class="rounded-xl bg-white px-6 py-3 text-sm font-bold text-brand-900 hover:bg-brand-50">Lihat Daftar Unit</a>
            <a href="/kontak" class="rounded-xl border border-white/40 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">Hubungi Sales</a>
        </div>
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

    {{-- Revisi 12: peta lokasi setiap proyek. Revisi 37: penanda memperbesar ke detail jalan dan memberi tautan rute. --}}
    <section data-seksi="peta">
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Lokasi Proyek</h2>
        <p class="mt-1 text-sm text-zinc-500">Klik penanda pada peta untuk melihat detail jalan di sekitar proyek, lalu gunakan tombol rute untuk mengetahui cara menuju lokasinya.</p>
        <div id="beranda-map" class="mt-6 h-[420px] overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm"></div>
        <div id="beranda-rute" class="mt-4 flex flex-wrap items-center gap-3"></div>
        <div id="beranda-map-list" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"></div>
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
            <a href="/kontak" class="rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-800">Hubungi Kami</a>
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
                        + (s.avatar_url
                            ? LP.fotoSales(s.avatar_url, s.name, 'h-11 w-11')
                            : '<span class="grid h-11 w-11 place-items-center rounded-full bg-brand-50 text-brand-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .2-3.2-.7-2.7-1.1-4.4-3.8-4.6-4-.1-.2-1-1.4-1-2.6s.6-1.8.8-2c.2-.2.4-.3.6-.3h.5c.2 0 .4 0 .5.4l.8 2c.1.2 0 .3-.1.5l-.4.6c-.2.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.4 0 .6-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.2.1.7-.1 1z"/></svg></span>')
                        + '<span><span class="block text-sm font-bold text-zinc-900">' + LP.esc(s.name) + '</span><span class="block text-xs text-zinc-500">' + LP.esc(s.sales_code) + '</span></span>'
                        + '</a>';
                }).join('')
                : '<p class="rounded-2xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">Kontak sales belum tersedia.</p>';
        } catch (e) {
            LP.errorBox(mount, e.message || 'Gagal memuat kontak sales.');
        }
    }

    function muatLeaflet() {
        if (window.L) return Promise.resolve();

        return new Promise(function (resolve, reject) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(css);

            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            script.onerror = function () { reject(new Error('Peta tidak dapat dimuat.')); };
            document.head.appendChild(script);
        });
    }

    // Revisi 37: peta bisa diperbesar sampai detail jalan dan menyediakan rute menuju proyek.
    const ZOOM_JALAN = 17;
    const LAYANAN_RUTE = 'https://router.project-osrm.org/route/v1/driving/';

    let petaBeranda = null;
    let lapisanRute = null;
    let titikSaya = null;
    let proyekTerpilih = null;

    const ruteGoogle = (r) => 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination='
        + r.latitude + ',' + r.longitude;

    function popupProyek(r) {
        return '<strong>' + LP.esc(r.name) + '</strong><br>'
            + LP.esc(r.address || 'Alamat belum diisi') + '<br>'
            + LP.number(r.units_available) + ' unit tersedia<br>'
            + '<a href="/unit?proyek=' + encodeURIComponent(r.name) + '">Lihat unit proyek ini</a><br>'
            + '<a href="' + LP.esc(ruteGoogle(r)) + '" target="_blank" rel="noopener">Rute menuju lokasi</a>';
    }

    function petaInfo(rows) {
        document.getElementById('beranda-map-list').innerHTML = rows.map(function (r) {
            return '<div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">'
                + '<p class="text-sm font-bold text-zinc-900">' + LP.esc(r.name) + '</p>'
                + '<p class="mt-1 text-xs text-zinc-500">' + LP.esc(r.address || 'Alamat belum diisi') + '</p>'
                + '<p class="mt-2 text-xs font-semibold text-brand-700">' + LP.number(r.units_available) + ' unit tersedia</p>'
                + '<div class="mt-3 flex flex-wrap gap-2">'
                + '<a href="/unit?proyek=' + encodeURIComponent(r.name) + '" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-brand-500 hover:text-brand-700">Lihat unit</a>'
                + '<a href="' + LP.esc(ruteGoogle(r)) + '" target="_blank" rel="noopener" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">Rute menuju lokasi</a>'
                + '</div></div>';
        }).join('');
    }

    function pilihProyekPeta(r) {
        proyekTerpilih = r;
        const tombol = document.getElementById('beranda-rute-saya');
        const buka = document.getElementById('beranda-rute-buka');
        if (tombol) tombol.textContent = 'Gambar rute dari lokasi saya ke ' + r.name;
        if (buka) buka.href = ruteGoogle(r);
    }

    function ruteInfo(pesan) {
        const el = document.getElementById('beranda-rute-info');
        if (el) el.textContent = pesan;
    }

    function posisiPengguna() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Peramban ini tidak mendukung pengambilan lokasi.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }); },
                function () { reject(new Error('Izin lokasi tidak diberikan, sehingga rute dari posisi Anda tidak dapat dibuat.')); },
                { enableHighAccuracy: true, timeout: 12000 });
        });
    }

    async function gambarRute() {
        if (!petaBeranda || !proyekTerpilih) return;
        ruteInfo('Mengambil lokasi Anda...');

        try {
            const asal = await posisiPengguna();
            const tautan = LAYANAN_RUTE + asal.longitude + ',' + asal.latitude + ';'
                + proyekTerpilih.longitude + ',' + proyekTerpilih.latitude + '?overview=full&geometries=geojson';
            const jawaban = await fetch(tautan);
            const data = await jawaban.json();
            const rute = data && data.routes ? data.routes[0] : null;
            if (!rute) throw new Error('Rute berkendara tidak ditemukan pada layanan peta.');

            if (lapisanRute) petaBeranda.removeLayer(lapisanRute);
            lapisanRute = L.geoJSON(rute.geometry, { style: { color: '#1d4ed8', weight: 5, opacity: 0.75 } }).addTo(petaBeranda);

            if (titikSaya) petaBeranda.removeLayer(titikSaya);
            titikSaya = L.circleMarker([asal.latitude, asal.longitude], {
                radius: 6, color: '#0f766e', fillColor: '#14b8a6', fillOpacity: 0.9,
            }).addTo(petaBeranda).bindPopup('Posisi Anda');

            petaBeranda.fitBounds(lapisanRute.getBounds().pad(0.15), { maxZoom: ZOOM_JALAN });
            ruteInfo('Rute ke ' + proyekTerpilih.name + ': ' + (rute.distance / 1000).toFixed(1)
                + ' km, estimasi ' + Math.max(1, Math.round(rute.duration / 60)) + ' menit berkendara melalui jalan umum.');
        } catch (e) {
            ruteInfo((e && e.message ? e.message : 'Rute gagal dibuat.')
                + ' Tetap bisa dibuka lewat tombol Google Maps di samping.');
        }
    }

    async function loadMap() {
        const mount = document.getElementById('beranda-map');
        LP.loading(mount, 'Memuat peta lokasi proyek...');
        try {
            const rows = (await LP.fetchJson('/public/project-locations', ANON)) || [];
            if (!rows.length) {
                mount.innerHTML = '<div class="flex h-full items-center justify-center p-8 text-center text-sm text-zinc-500">'
                    + 'Belum ada proyek dengan koordinat lokasi. Isi Latitude dan Longitude proyek pada menu Data Proyek.</div>';
                document.getElementById('beranda-map-list').innerHTML = '';
                document.getElementById('beranda-rute').innerHTML = '';
                return;
            }

            await muatLeaflet();
            mount.innerHTML = '';

            petaBeranda = L.map(mount, { scrollWheelZoom: false, maxZoom: 19 });
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; kontributor OpenStreetMap',
            }).addTo(petaBeranda);

            const penanda = rows.map(function (r) {
                const marker = L.marker([r.latitude, r.longitude]).addTo(petaBeranda).bindPopup(popupProyek(r));
                marker.on('click', function () {
                    pilihProyekPeta(r);
                    petaBeranda.flyTo([r.latitude, r.longitude], ZOOM_JALAN);
                });
                return marker;
            });

            if (penanda.length === 1) {
                petaBeranda.setView([rows[0].latitude, rows[0].longitude], 15);
            } else {
                petaBeranda.fitBounds(L.featureGroup(penanda).getBounds().pad(0.3), { maxZoom: ZOOM_JALAN });
            }

            document.getElementById('beranda-rute').innerHTML =
                '<button id="beranda-rute-saya" type="button" class="rounded-xl border border-brand-500 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">Gambar rute dari lokasi saya</button>'
                + '<a id="beranda-rute-buka" href="#" target="_blank" rel="noopener" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800">Buka rute di Google Maps</a>'
                + '<p id="beranda-rute-info" class="text-sm text-zinc-500">Klik penanda pada peta untuk memperbesar sampai detail jalan.</p>';
            document.getElementById('beranda-rute-saya').addEventListener('click', function (event) {
                LP.withBusy(event.currentTarget, gambarRute);
            });
            pilihProyekPeta(rows[0]);

            petaInfo(rows);
        } catch (e) {
            LP.errorBox(mount, e.message || 'Peta lokasi gagal dimuat.');
        }
    }

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
        ['unggulan', 'skema', 'peta', 'news', 'gallery', 'sales'].forEach(function (nama) {
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
        if (aktif.peta) loadMap();
        if (aktif.news || aktif.gallery) loadNewsAndGallery(aktif);
        if (aktif.sales) loadSales();
    }).catch(function () {
        loadUnits();
        loadSchemes();
        loadMap();
        loadNewsAndGallery({ news: true, gallery: true });
        loadSales();
    });
</script>
@endpush
