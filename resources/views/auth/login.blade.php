<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &middot; Luminpark</title>
    <link rel="icon" type="image/svg+xml" href="/images/logo-mark.svg">
    <!-- Memuat Google Font Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 50: '#f2f7f4', 100: '#dcebe2', 500: '#2f7d5b', 600: '#256349', 900: '#123024' } },
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                },
            },
        };
    </script>
</head>
<body class="min-h-full font-sans antialiased bg-zinc-100">

<!-- Container Latar Belakang Gambar Penuh yang Terang & Jernih -->
<div class="relative flex min-h-screen items-center justify-center bg-cover bg-center px-4 py-8" 
     style="background-image: linear-gradient(rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.2)), url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?q=80&w=1600&auto=format&fit=crop');">

    {{-- Kotak Login Kompak (Ukuran Sedang) dengan Efek Kaca Transparan Blur --}}
    <main class="w-full max-w-[360px] rounded-2xl border border-white/60 bg-white/45 p-6 shadow-2xl backdrop-blur-md sm:p-7">
        
        <div class="space-y-1 text-center">
            <h2 class="text-xl font-bold tracking-tight text-zinc-900">Masuk ke Sistem</h2>
            <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Khusus Super Admin, Admin Sales, dan Sales. Konsumen cukup memakai halaman publik.</p>
        </div>

        <form id="login-form" class="mt-5 space-y-3.5" novalidate>
            <div class="text-left">
                <label for="email" class="mb-1 block text-xs font-semibold text-zinc-800">Nama Pengguna</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                       class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                       placeholder="email akun Anda">
                <span class="mt-1 block text-[10px] font-medium text-zinc-600">gunakan email akun Anda</span>
            </div>

            <div class="text-left">
                <div class="mb-1 flex items-center justify-between">
                    <label for="password" class="block text-xs font-semibold text-zinc-800">Kata Sandi</label>
                    <a href="/reset-password" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 hover:underline">Lupa Password?</a>
                </div>
                <div class="relative">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 pr-10 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                           placeholder="••••••••">
                    <button id="toggle-password" type="button" aria-label="Tampilkan kata sandi"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-600 hover:text-zinc-900 transition-colors">
                        <svg id="eye-open" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="eye-off" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.4 4.2M6.1 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 3.3-.6"/></svg>
                    </button>
                </div>
            </div>

            <button id="submit-btn" type="submit"
                    class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-white shadow-md transition-all duration-200 hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                Log In
            </button>
        </form>

        <div class="mt-4 hidden items-center gap-3 text-[11px] font-medium uppercase tracking-wider text-zinc-500" id="google-separator">
            <span class="h-px flex-1 bg-zinc-300/80"></span> atau <span class="h-px flex-1 bg-zinc-300/80"></span>
        </div>

        <div class="mt-3.5">
            <button id="google-btn" type="button" disabled
                    class="flex w-full items-center justify-center gap-2.5 rounded-xl border border-zinc-300/80 bg-white/70 px-4 py-2.5 text-xs font-medium text-zinc-700 shadow-sm transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-60 enabled:hover:bg-white/90">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.3z"/><path d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 .9-3.4.9-2.6 0-4.8-1.7-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22z"/><path d="M6.4 13.9a6 6 0 0 1 0-3.8V7.5H3.1a10 10 0 0 0 0 9z"/><path d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.9A10 10 0 0 0 3.1 7.5l3.3 2.6C7.2 7.6 9.4 5.9 12 5.9z"/></svg>
                <span id="google-btn-label">Masuk dengan Google</span>
            </button>
            <p id="google-note" class="mt-1.5 text-center text-[10px] font-medium text-zinc-600">Menunggu konfigurasi Super Admin pada halaman Pengaturan.</p>
        </div>

        <div class="mt-5 border-t border-zinc-300/60 pt-3.5 text-center">
            <p class="text-[11px] font-medium text-zinc-600">&copy; {{ date('Y') }} Perumahan Luminpark</p>
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
        .catch(function () {});

    googleBtn.addEventListener('click', function () {
        if (googleBtn.disabled) {
            LP.toast('Masuk dengan Google belum diaktifkan oleh Super Admin.', 'info');
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