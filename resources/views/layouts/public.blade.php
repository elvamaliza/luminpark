<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Perumahan Luminpark')</title>
    <meta name="description" content="Sistem informasi dan manajemen penjualan unit perumahan Luminpark.">
    <link rel="icon" type="image/svg+xml" href="/images/logo-mark.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 50: '#f2f7f4', 100: '#dcebe2', 500: '#2f7d5b', 600: '#256349', 900: '#123024' } },
                    fontFamily: { sans: ['ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'] },
                },
            },
        };
    </script>
</head>
<body class="bg-white text-zinc-900 antialiased">
<div id="lp-announcement" class="hidden bg-brand-600 px-4 py-2.5 text-center text-sm font-medium text-white"></div>
<header class="sticky top-0 z-40 border-b border-zinc-100 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <a href="/" class="flex items-center gap-2">
            <img src="/images/logo-mark.svg" alt="Logo Luminpark" class="h-10 w-10 shrink-0">
            <span class="text-sm font-bold sm:text-base" data-setting="estate_name">Perumahan Luminpark</span>
        </a>

        <nav class="ml-auto hidden items-center gap-1 text-sm font-medium text-zinc-600 md:flex">
            <a href="/" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-900">Beranda</a>
            <a href="/unit" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-900">Daftar Unit</a>
            <a href="/skema-pembayaran" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-900">Skema Pembayaran</a>
            <a href="/tentang" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-900">Tentang Kami</a>
            <a href="/kontak" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-900">Kontak</a>
        </nav>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <button id="lp-public-menu" class="rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 md:hidden" aria-label="Buka menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div id="lp-public-nav" class="hidden border-t border-zinc-100 bg-white px-4 py-2 text-sm font-medium md:hidden">
        <a href="/" class="block rounded-lg px-3 py-2 hover:bg-brand-50">Beranda</a>
        <a href="/unit" class="block rounded-lg px-3 py-2 hover:bg-brand-50">Daftar Unit</a>
        <a href="/skema-pembayaran" class="block rounded-lg px-3 py-2 hover:bg-brand-50">Skema Pembayaran</a>
        <a href="/tentang" class="block rounded-lg px-3 py-2 hover:bg-brand-50">Tentang Kami</a>
        <a href="/kontak" class="block rounded-lg px-3 py-2 hover:bg-brand-50">Kontak</a>
    </div>
</header>

@yield('content')

<footer class="mt-20 bg-brand-900 text-brand-100">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="flex items-center gap-2">
                <img src="/images/logo-mark.svg" alt="Logo Luminpark" class="h-10 w-10 shrink-0">
                <span class="text-base font-bold text-white" data-setting="estate_name">Perumahan Luminpark</span>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-brand-100/80" data-setting="about_text">Hunian minimalis, asri, dan islami.</p>
        </div>
        <div>
            <h4 class="text-sm font-semibold uppercase tracking-wide text-white">Kontak</h4>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a data-setting="estate_address" data-link="maps" class="hover:text-white" href="/kontak">Alamat pemasaran</a></li>
                <li><a data-setting="estate_phone" data-link="tel" class="hover:text-white" href="/kontak">Nomor telepon</a></li>
                <li><a data-setting="estate_email" data-link="mailto" class="hover:text-white" href="/kontak">Email</a></li>
                <li><span data-setting="estate_hours">Jam operasional</span></li>
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-semibold uppercase tracking-wide text-white">Navigasi</h4>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="/unit" class="hover:text-white">Daftar unit rumah</a></li>
                <li><a href="/skema-pembayaran" class="hover:text-white">Skema pembayaran</a></li>
                <li><a href="/tentang" class="hover:text-white">Tentang kami</a></li>
                <li><a href="/kontak" class="hover:text-white">Kontak &amp; narahubung sales</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-sm font-semibold uppercase tracking-wide text-white">Tim Sales</h4>
            <ul id="footer-sales" class="mt-3 space-y-2 text-sm"><li class="text-brand-100/60">Memuat...</li></ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-brand-100/70">
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
            host.innerHTML = (rows || []).slice(0, 4).map((s) => `<li><a class="hover:text-white" href="${LP.esc(s.whatsapp_link)}" target="_blank" rel="noopener">${LP.esc(s.name)} &middot; ${LP.esc(s.sales_code)}</a></li>`).join('')
                || '<li class="text-brand-100/60">Belum ada data sales.</li>';
        })
        .catch(() => {});
</script>
@stack('scripts')
</body>
</html>
