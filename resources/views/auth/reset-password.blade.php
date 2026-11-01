<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi &middot; Luminpark</title>
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

    {{-- Panel kiri: identitas + tautan kembali (Wireframe 2.26) --}}
    <aside class="relative hidden flex-col justify-between overflow-hidden bg-brand-900 p-12 text-white lg:flex">
        <img src="/images/logo-wordmark-light.svg" alt="Logo Luminpark" class="h-12 w-auto self-start">

        <div class="max-w-md">
            <h1 class="text-4xl font-bold leading-tight">Atur Ulang Kata Sandi</h1>
            <p class="mt-3 text-base text-brand-100/90">Keamanan akun Anda adalah prioritas kami. Verifikasi identitas, lalu tentukan kata sandi baru.</p>
            <a href="/login" class="mt-8 inline-flex items-center gap-2 rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">
                <span aria-hidden="true">&lsaquo;</span> Kembali ke Halaman Masuk
            </a>
        </div>

        <p class="text-xs text-brand-100/70">&copy; {{ date('Y') }} Perumahan Luminpark. Seluruh hak cipta dilindungi.</p>
    </aside>

    {{-- Panel kanan: formulir dua langkah --}}
    <main class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <img src="/images/logo-wordmark.svg" alt="Logo Luminpark" class="mb-8 h-11 w-auto self-start lg:hidden">

            <ol class="mb-6 flex items-center gap-2 text-xs font-semibold text-zinc-400">
                <li id="badge-step1" class="rounded-full bg-brand-600 px-3 py-1 text-white">1. Verifikasi</li>
                <li aria-hidden="true">&rarr;</li>
                <li id="badge-step2" class="rounded-full bg-zinc-100 px-3 py-1">2. Kata Sandi Baru</li>
            </ol>

            {{-- Langkah 1 --}}
            <form id="step1" class="space-y-5">
                <h2 class="text-xl font-bold text-zinc-900">Verifikasi Identitas</h2>
                <p class="text-sm text-zinc-500">Masukkan nama pengguna (email) dan kata sandi saat ini untuk membuktikan kepemilikan akun.</p>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-zinc-700">Nama Pengguna</label>
                    <input id="email" name="email" type="email" autocomplete="username" required
                           class="w-full rounded-xl border border-zinc-300 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                           placeholder="email akun Anda">
                    <span class="mt-1 block text-xs text-zinc-500">gunakan email akun Anda</span>
                </div>

                <div>
                    <label for="password_lama" class="mb-1.5 block text-sm font-medium text-zinc-700">Password Saat Ini</label>
                    <div class="relative">
                        <input id="password_lama" name="password_lama" type="password" autocomplete="current-password" required
                               class="w-full rounded-xl border border-zinc-300 px-4 py-3 pr-12 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                               placeholder="••••••••">
                        <button type="button" data-eye="password_lama" aria-label="Tampilkan kata sandi"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 hover:text-zinc-800">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button id="verify-btn" type="submit"
                        class="w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-bold uppercase tracking-wide text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                    Verifikasi Identitas
                </button>

                <div class="text-center">
                    <a href="/login" class="text-sm font-medium text-zinc-500 hover:text-brand-600">Batal dan Kembali ke Halaman Login</a>
                </div>
            </form>

            {{-- Langkah 2 --}}
            <form id="step2" class="hidden space-y-5">
                <h2 class="text-xl font-bold text-zinc-900">Kata Sandi Baru</h2>
                <p class="text-sm text-zinc-500">Identitas terverifikasi. Tentukan kata sandi baru (minimal 6 karakter) untuk akun <span id="shown-email" class="font-semibold text-zinc-700"></span>.</p>

                <div>
                    <label for="password_baru" class="mb-1.5 block text-sm font-medium text-zinc-700">Password Baru</label>
                    <div class="relative">
                        <input id="password_baru" name="password_baru" type="password" autocomplete="new-password" required
                               class="w-full rounded-xl border border-zinc-300 px-4 py-3 pr-12 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                               placeholder="••••••••">
                        <button type="button" data-eye="password_baru" aria-label="Tampilkan kata sandi"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-500 hover:text-zinc-800">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="password_baru2" class="mb-1.5 block text-sm font-medium text-zinc-700">Konfirmasi Password Baru</label>
                    <input id="password_baru2" name="password_baru2" type="password" autocomplete="new-password" required
                           class="w-full rounded-xl border border-zinc-300 px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                           placeholder="••••••••">
                </div>

                <button id="update-btn" type="submit"
                        class="w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-bold uppercase tracking-wide text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                    Update Password
                </button>

                <div class="text-center">
                    <a href="/login" class="text-sm font-medium text-zinc-500 hover:text-brand-600">Batal dan Kembali ke Halaman Login</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="/js/luminpark.js"></script>
<script>
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const badge1 = document.getElementById('badge-step1');
    const badge2 = document.getElementById('badge-step2');
    const verifyBtn = document.getElementById('verify-btn');
    const updateBtn = document.getElementById('update-btn');

    const emailInput = document.getElementById('email');
    const lamaInput = document.getElementById('password_lama');
    const baruInput = document.getElementById('password_baru');
    const baru2Input = document.getElementById('password_baru2');

    const state = { email: '', password_lama: '' };

    document.querySelectorAll('[data-eye]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.eye);
            target.type = target.type === 'password' ? 'text' : 'password';
        });
    });

    function toStep2() {
        step1.classList.add('hidden');
        step2.classList.remove('hidden');
        badge1.classList.remove('bg-brand-600', 'text-white');
        badge1.classList.add('bg-emerald-100', 'text-emerald-700');
        badge1.textContent = '1. Terverifikasi';
        badge2.classList.remove('bg-zinc-100');
        badge2.classList.add('bg-brand-600', 'text-white');
        document.getElementById('shown-email').textContent = state.email;
        baruInput.focus();
    }

    step1.addEventListener('submit', function (event) {
        event.preventDefault();
        const email = emailInput.value.trim();
        const lama = lamaInput.value;
        if (!email || !lama) {
            LP.toast('Nama pengguna dan kata sandi saat ini wajib diisi.', 'error');
            return;
        }
        LP.withBusy(verifyBtn, async function () {
            try {
                await LP.login(email, lama);
                state.email = email;
                state.password_lama = lama;
                toStep2();
                LP.toast('Identitas terverifikasi. Silakan tentukan kata sandi baru.', 'success');
            } catch (err) {
                LP.toast(err.message || 'Verifikasi gagal. Periksa nama pengguna dan kata sandi.', 'error');
            }
        });
    });

    step2.addEventListener('submit', function (event) {
        event.preventDefault();
        const lama = state.password_lama;
        const baru = baruInput.value;
        const konfirmasi = baru2Input.value;

        if (baru.length < 6) {
            LP.toast('Password baru minimal 6 karakter.', 'error');
            return;
        }
        if (baru !== konfirmasi) {
            LP.toast('Konfirmasi password baru tidak sama.', 'error');
            return;
        }
        if (baru === lama) {
            LP.toast('Password baru harus berbeda dari password lama.', 'error');
            return;
        }

        LP.withBusy(updateBtn, async function () {
            try {
                await LP.http.post('/reset-password', {
                    email: state.email,
                    password_lama: lama,
                    password_baru: baru,
                });
                LP.token.clear();
                LP.toast('Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.', 'success');
                setTimeout(function () { location.href = '/login'; }, 1200);
            } catch (err) {
                if (err && err.errors) {
                    const pesan = Object.values(err.errors).flat().join('. ');
                    LP.toast(pesan || err.message || 'Gagal memperbarui kata sandi.', 'error');
                } else {
                    LP.toast(err.message || 'Gagal memperbarui kata sandi.', 'error');
                }
            }
        });
    });
</script>
</body>
</html>
