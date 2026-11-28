<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Perumahan Luminpark')</title>
    <meta name="description" content="Sistem informasi dan manajemen penjualan unit perumahan Luminpark.">
    <link rel="icon" type="image/svg+xml" href="/images/logo-mark.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Menu transparan di atas hero (aktif hanya di halaman yang memakai @section('header-overlay')) */
        .lp-header-overlay [data-setting="estate_name"] { color: #fff !important; }
        .lp-header-overlay nav a { color: rgba(255, 255, 255, .92) !important; }
        .lp-header-overlay nav a:hover { color: #fff !important; text-decoration: underline; text-underline-offset: 6px; }
        .lp-header-overlay #lp-public-menu { color: #fff !important; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        /* leaves: hijau daun yang lebih pekat, warna utama (nama "brand" dipertahankan agar kode lain tetap jalan) */
                        brand: { 50: '#eef5e6', 100: '#d9e9c6', 200: '#b8d796', 300: '#92c068', 400: '#6ea646', 500: '#4f8a2e', 600: '#3b7024', 700: '#2e591d', 800: '#234516', 900: '#19330f' },
                        /* bark: cokelat kulit kayu yang lebih hangat */
                        bark: { 50: '#f7f0e8', 100: '#ebdac6', 300: '#c19a6b', 500: '#8c5e34', 700: '#6b4423', 800: '#4e3119', 900: '#35210f' },
                        /* marble: krem marmer hangat untuk latar */
                        marble: { 50: '#fbf8f1', 100: '#f3eddf', 200: '#e8dfca', 300: '#d8ccb0' },
                        /* brownstone: cokelat kemerahan untuk tombol dan aksen */
                        brownstone: { 400: '#c0714f', 500: '#a85a3a', 600: '#8f4a2e', 700: '#723a24' },
                    },
                                        fontFamily: { sans: ['ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'], serif: ['"Cormorant Garamond"', 'Georgia', 'serif'] },
                },
            },
        };
    </script>
</head>
<body class="bg-marble-50 text-stone-900 antialiased">
<div id="lp-announcement" class="hidden bg-brand-700 px-4 py-2.5 text-center text-sm font-medium text-brand-50"></div>
<header class="@hasSection('header-overlay') lp-header-overlay absolute inset-x-0 top-0 z-40 @else sticky top-0 z-40 border-b border-marble-200 bg-marble-50/85 backdrop-blur @endif">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <a href="/" class="flex items-center gap-2.5">
            <img src="/images/logo-mark.svg" alt="Logo Luminpark" class="h-9 w-9 shrink-0">
            <span class="text-sm font-semibold tracking-tight text-brand-800 sm:text-base" data-setting="estate_name">Perumahan Luminpark</span>
        </a>

        <nav class="ml-auto hidden items-center gap-7 text-sm font-medium text-stone-600 md:flex">
            <a href="/" class="transition hover:text-brand-700">Beranda</a>
            <a href="/unit" class="transition hover:text-brand-700">Daftar Unit</a>
            <a href="/skema-pembayaran" class="transition hover:text-brand-700">Skema Pembayaran</a>
            <a href="/tentang" class="transition hover:text-brand-700">Tentang Kami</a>
            <a href="/kontak" class="transition hover:text-brand-700">Kontak</a>
        </nav>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <button id="lp-public-menu" class="rounded-lg p-2 text-stone-600 hover:bg-marble-100 md:hidden" aria-label="Buka menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div id="lp-public-nav" class="hidden border-t border-marble-200 bg-marble-50 px-4 py-2 text-sm font-medium text-stone-700 md:hidden">
        <a href="/" class="block rounded-lg px-3 py-2 hover:bg-marble-100">Beranda</a>
        <a href="/unit" class="block rounded-lg px-3 py-2 hover:bg-marble-100">Daftar Unit</a>
        <a href="/skema-pembayaran" class="block rounded-lg px-3 py-2 hover:bg-marble-100">Skema Pembayaran</a>
        <a href="/tentang" class="block rounded-lg px-3 py-2 hover:bg-marble-100">Tentang Kami</a>
        <a href="/kontak" class="block rounded-lg px-3 py-2 hover:bg-marble-100">Kontak</a>
    </div>
</header>

@yield('content')

<footer class="@yield('footer-margin', 'mt-20') bg-bark-900 text-marble-300">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="flex items-center gap-2.5">
                <img src="/images/logo-mark.svg" alt="Logo Luminpark" class="h-9 w-9 shrink-0">
                <span class="text-base font-semibold tracking-tight text-marble-50" data-setting="estate_name">Perumahan Luminpark</span>
            </div>
            <p class="mt-4 text-sm leading-relaxed text-marble-300/80" data-setting="about_text">Hunian minimalis, asri, dan islami.</p>
        </div>
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-[0.15em] text-marble-50">Kontak</h4>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a data-setting="estate_address" data-link="maps" class="transition hover:text-white" href="/kontak">Alamat pemasaran</a></li>
                <li><a data-setting="estate_phone" data-link="tel" class="transition hover:text-white" href="/kontak">Nomor telepon</a></li>
                <li><a data-setting="estate_email" data-link="mailto" class="transition hover:text-white" href="/kontak">Email</a></li>
                <li><span data-setting="estate_hours">Jam operasional</span></li>
            </ul>
        </div>
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-[0.15em] text-marble-50">Navigasi</h4>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="/unit" class="transition hover:text-white">Daftar unit rumah</a></li>
                <li><a href="/skema-pembayaran" class="transition hover:text-white">Skema pembayaran</a></li>
                <li><a href="/tentang" class="transition hover:text-white">Tentang kami</a></li>
                <li><a href="/kontak" class="transition hover:text-white">Kontak &amp; narahubung sales</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-[0.15em] text-marble-50">Tim Sales</h4>
            <ul id="footer-sales" class="mt-4 space-y-2.5 text-sm"><li class="text-marble-300/60">Memuat...</li></ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-marble-300/70">
        &copy; {{ date('Y') }} <span data-setting="estate_name">Perumahan Luminpark</span>. <span data-setting="public_footer_note">Seluruh hak cipta dilindungi.</span>
    </div>
</footer>

<script src="/js/luminpark.js?v={{ filemtime(public_path('js/luminpark.js')) }}"></script>
<script>
    document.getElementById('lp-public-menu')?.addEventListener('click', () => {
        document.getElementById('lp-public-nav')?.classList.toggle('hidden');
    });

    LP.fillSettings().then((nilai) => {
        const banner = document.getElementById('lp-announcement');
        const teks = nilai && nilai.public_announcement;
        if (banner && teks) {
            banner.textContent = teks;
            banner.classList.remove('hidden');
        }
    }).catch(() => {});
    LP.fetchJson('/public/sales-contacts', { anonymous: true })
        .then((rows) => {
            const host = document.getElementById('footer-sales');
            if (!host) return;
            host.innerHTML = (rows || []).slice(0, 4).map((s) => `<li><a class="transition hover:text-white" href="${LP.esc(s.whatsapp_link)}" target="_blank" rel="noopener">${LP.esc(s.name)} &middot; ${LP.esc(s.sales_code)}</a></li>`).join('')
                || '<li class="text-marble-300/60">Belum ada data sales.</li>';
        })
        .catch(() => {});
</script>
@stack('scripts')
</body>
</html>
