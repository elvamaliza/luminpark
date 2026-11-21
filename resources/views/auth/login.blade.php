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

<!-- Container Latar Belakang Gambar Penuh -->
<div class="relative flex min-h-screen items-center justify-center bg-cover bg-center px-4 py-8" 
     style="background-image: linear-gradient(rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.2)), url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?q=80&w=1600&auto=format&fit=crop');">

    <main class="w-full max-w-[360px] rounded-2xl border border-white/60 bg-white/45 p-6 shadow-2xl backdrop-blur-md sm:p-7">
        
        <div class="space-y-1 text-center">
            <h2 class="text-xl font-bold tracking-tight text-zinc-900">Masuk ke Sistem</h2>
            <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Khusus Super Admin, Monitoring Manajemen, dan Sales. Konsumen cukup memakai halaman publik.</p>
        </div>

        <form id="login-form" class="mt-5 space-y-3.5" novalidate>
            <div class="text-left">
                <label for="email" class="mb-1 block text-xs font-semibold text-zinc-800">Nama Pengguna</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                       class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                       placeholder="email akun Anda">
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

        <!-- Pemisah ATAU yang diselaraskan tepat di tengah dengan flex -->
        <div class="mt-4 flex items-center gap-3 text-[11px] font-medium uppercase tracking-wider text-zinc-500" id="google-separator">
            <span class="h-px flex-1 bg-zinc-300/80"></span> 
            <span class="px-1 text-zinc-500">ATAU</span> 
            <span class="h-px flex-1 bg-zinc-300/80"></span>
        </div>

        <div class="mt-3.5">
            <!-- Tombol Google yang didesain lebih berwarna, elegan, dan interaktif -->
            <button id="google-btn" type="button"
                    class="flex w-full items-center justify-center gap-2.5 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-xs font-semibold text-zinc-800 shadow-sm transition-all duration-200 hover:border-zinc-400 hover:bg-zinc-50 hover:shadow active:scale-[0.99]">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                </svg>
                <span id="google-btn-label" class="text-zinc-800">Masuk dengan Google</span>
            </button>
            <p id="google-note" class="mt-1.5 text-center text-[10px] font-medium text-zinc-600">Akun Google harus memakai email yang sama dengan akun staf internal Anda.</p>
        </div>

        <div class="mt-5 border-t border-zinc-300/60 pt-3.5 text-center">
            <p class="text-[11px] font-medium text-zinc-600">&copy; {{ date('Y') }} Perumahan Luminpark</p>
        </div>
    </main>
</div>

<script src="/js/luminpark.js?v={{ filemtime(public_path('js/luminpark.js')) }}"></script>
<script>
    const PESAN_GOOGLE = {
        'belum-diaktifkan': 'Masuk dengan Google belum diaktifkan. Gunakan email dan kata sandi Anda.',
        'ditolak-pengguna': 'Anda membatalkan login Google. Silakan coba lagi atau gunakan kata sandi.',
        'state-tidak-valid': 'Sesi login Google tidak valid. Silakan mulai ulang prosesnya.',
        'pertukaran-gagal': 'Kode Google tidak dapat ditukarkan. Silakan coba lagi.',
        'server-google-tidak-terjangkau': 'Layanan Google tidak dapat dihubungi. Periksa koneksi internet Anda.',
        'email-belum-diverifikasi': 'Email akun Google Anda belum terverifikasi sehingga tidak dapat dipakai masuk.',
        'bukan-akun-internal': 'Email Google itu tidak terdaftar sebagai akun staf internal. Hubungi Super Admin atau Monitoring Manajemen.',
        'akun-nonaktif': 'Akun Anda dinonaktifkan. Hubungi Super Admin atau Monitoring Manajemen.',
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

    if (new URLSearchParams(location.search).get('sesi') === 'berakhir') {
        history.replaceState(null, '', '/login');
        LP.toast('Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.', 'error');
    }

    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('submit-btn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('toggle-password');
    const eyeOpen = document.getElementById('eye-open');
    const eyeOff = document.getElementById('eye-off');
    const googleBtn = document.getElementById('google-btn');

    toggleBtn.addEventListener('click', function () {
        const reveal = passwordInput.type === 'password';
        passwordInput.type = reveal ? 'text' : 'password';
        eyeOpen.classList.toggle('hidden', reveal);
        eyeOff.classList.toggle('hidden', !reveal);
        toggleBtn.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });

    googleBtn.addEventListener('click', function () {
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