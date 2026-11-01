<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &middot; Luminpark</title>
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
<body class="min-h-full bg-white text-zinc-900 antialiased">
<div class="grid min-h-screen lg:grid-cols-2">

    {{-- Panel kiri: identitas visual (Wireframe 2.25) --}}
    <aside class="relative hidden flex-col justify-between overflow-hidden bg-brand-900 p-12 text-white lg:flex">
        <img src="/images/logo-wordmark-light.svg" alt="Logo Luminpark" class="h-14 w-auto self-start">

        <div class="max-w-md">
            <h1 class="text-4xl font-bold leading-tight">Selamat Datang</h1>
            <p class="mt-3 text-base text-brand-100/90">Sistem Manajemen Perumahan Lubuk Minturun Park</p>
            <ul class="mt-8 space-y-3 text-sm text-brand-100/90">
                <li class="flex items-start gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span> Kelola data unit, sales, dan konsumen dalam satu dashboard.</li>
                <li class="flex items-start gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span> Pantau booking, pembayaran, dan pelunasan secara real time.</li>
                <li class="flex items-start gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span> Laporan penjualan dan kinerja sales siap unduh.</li>
            </ul>
        </div>

        <p class="text-xs text-brand-100/70">&copy; {{ date('Y') }} Perumahan Luminpark. Seluruh hak cipta dilindungi.</p>
    </aside>

    {{-- Panel kanan: zona interaksi login --}}
    <main class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <img src="/images/logo-wordmark.svg" alt="Logo Luminpark" class="h-12 w-auto self-start lg:hidden">

            <h2 class="mt-6 text-2xl font-bold text-zinc-900 lg:mt-0">Masuk ke Sistem</h2>
            <p class="mt-1 text-sm text-zinc-500">Khusus Manajer, Admin Sales, dan Sales. Konsumen cukup memakai halaman publik.</p>

            <form id="login-form" class="mt-8 space-y-5" novalidate>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-zinc-700">Nama Pengguna</label>
                    <input id="email" name="email" type="email" autocomplete="username" required
                           class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                           placeholder="email akun Anda">
                    <span class="mt-1 block text-xs text-zinc-500">gunakan email akun Anda</span>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-zinc-700">Kata Sandi</label>
                        <a href="/reset-password" class="text-sm font-medium text-brand-600 hover:text-brand-900 hover:underline">Lupa Password?</a>
                    </div>
                    <div class="relative">
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 pr-12 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                               placeholder="••••••••">
                        <button id="toggle-password" type="button" aria-label="Tampilkan kata sandi"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 hover:text-zinc-800">
                            <svg id="eye-open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eye-off" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.4 4.2M6.1 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 3.3-.6"/></svg>
                        </button>
                    </div>
                </div>

                <button id="submit-btn" type="submit"
                        class="w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-bold uppercase tracking-wide text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                    Log In
                </button>
            </form>

            <div class="mt-5 hidden items-center gap-3 text-xs font-medium uppercase tracking-wide text-zinc-400" id="google-separator">
                <span class="h-px flex-1 bg-zinc-200"></span> atau <span class="h-px flex-1 bg-zinc-200"></span>
            </div>

            <div class="mt-4">
                <button id="google-btn" type="button" disabled
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-400 disabled:cursor-not-allowed disabled:opacity-70 enabled:border-zinc-300 enabled:bg-white enabled:text-zinc-800 enabled:hover:bg-zinc-50">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.3z"/><path d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 .9-3.4.9-2.6 0-4.8-1.7-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22z"/><path d="M6.4 13.9a6 6 0 0 1 0-3.8V7.5H3.1a10 10 0 0 0 0 9z"/><path d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.9A10 10 0 0 0 3.1 7.5l3.3 2.6C7.2 7.6 9.4 5.9 12 5.9z"/></svg>
                    <span id="google-btn-label">Masuk dengan Google</span>
                </button>
                <p id="google-note" class="mt-2 text-center text-xs text-zinc-400">Menunggu konfigurasi Manajer pada halaman Pengaturan.</p>
            </div>

            <p class="mt-8 text-center text-xs text-zinc-400">&copy; {{ date('Y') }} Perumahan Luminpark</p>
        </div>
    </main>
</div>

<script src="/js/luminpark.js"></script>
<script>
    const PESAN_GOOGLE = {
        'belum-diaktifkan': 'Masuk dengan Google belum diaktifkan. Gunakan email dan kata sandi Anda.',
        'ditolak-pengguna': 'Anda membatalkan login Google. Silakan coba lagi atau gunakan kata sandi.',
        'state-tidak-valid': 'Sesi login Google tidak valid. Silakan mulai ulang prosesnya.',
        'pertukaran-gagal': 'Kode Google tidak dapat ditukarkan. Silakan coba lagi.',
        'server-google-tidak-terjangkau': 'Layanan Google tidak dapat dihubungi. Periksa koneksi internet Anda.',
        'email-belum-diverifikasi': 'Email akun Google Anda belum terverifikasi sehingga tidak dapat dipakai masuk.',
        'bukan-akun-internal': 'Email Google itu tidak terdaftar sebagai akun staf internal. Hubungi Manajer atau Admin Sales.',
        'akun-nonaktif': 'Akun Anda dinonaktifkan. Hubungi Manajer atau Admin Sales.',
        'google-sudah-tertaut': 'Akun internal ini sudah tertaut dengan akun Google lain.',
    };

    // Token dari halaman balik Google diangkut lewat fragment agar tidak ikut terkirim ke server.
    const fragment = new URLSearchParams(location.hash.replace(/^#/, ''));
    const tokenDariGoogle = fragment.get('lp_token');

    if (tokenDariGoogle) {
        LP.token.set(tokenDariGoogle);
        history.replaceState(null, '', '/login');
        location.href = '/dashboard';
    } else if (LP.token.get()) {
        location.replace('/dashboard');
    }

    const errorKey = new URLSearchParams(location.search).get('google_error');
    if (errorKey) {
        history.replaceState(null, '', '/login');
        LP.toast(PESAN_GOOGLE[errorKey] || 'Login Google gagal. Silakan coba lagi.', 'error');
    }

    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('submit-btn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('toggle-password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeOff = document.getElementById('eye-off');
    const googleBtn = document.getElementById('google-btn');
    const googleNote = document.getElementById('google-note');

    toggleBtn.addEventListener('click', function () {
        const reveal = passwordInput.type === 'password';
        passwordInput.type = reveal ? 'text' : 'password';
        eyeOpen.classList.toggle('hidden', reveal);
        eyeOff.classList.toggle('hidden', !reveal);
        toggleBtn.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });

    LP.fetchJson('/public/auth-config', { anonymous: true, quiet: true })
        .then(function (cfg) {
            if (!cfg || !cfg.google_login_enabled) return;
            googleBtn.disabled = false;
            document.getElementById('google-separator').classList.remove('hidden');
            googleNote.textContent = 'Akun Google harus memakai email yang sama dengan akun staf internal Anda.';
        })
        .catch(function () { /* tombol tetap tidak aktif bila konfigurasi belum terbaca */ });

    googleBtn.addEventListener('click', function () {
        if (googleBtn.disabled) {
            LP.toast('Masuk dengan Google belum diaktifkan oleh Manajer.', 'info');
            return;
        }
        location.href = '/auth/google/redirect';
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        if (!email || !password) {
            LP.toast('Nama pengguna dan kata sandi wajib diisi.', 'error');
            return;
        }
        LP.withBusy(submitBtn, async function () {
            try {
                await LP.login(email, password);
                location.href = '/dashboard';
            } catch (err) {
                LP.toast(err.message || 'Login gagal. Periksa nama pengguna dan kata sandi.', 'error');
            }
        });
    });
</script>
</body>
</html>
