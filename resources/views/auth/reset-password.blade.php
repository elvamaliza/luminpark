<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi &middot; Luminpark</title>
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

<!-- Container Latar Belakang Gambar Penuh Unsplash (Sama Persis Kodenya dengan Login) -->
<div class="relative flex min-h-screen items-center justify-center bg-cover bg-center px-4 py-8" 
     style="background-image: linear-gradient(rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.2)), url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?q=80&w=1600&auto=format&fit=crop');">

    {{-- Kotak Card Kompak di Tengah dengan Efek Kaca Transparan Blur --}}
    <main class="w-full max-w-[360px] rounded-2xl border border-white/60 bg-white/45 p-6 shadow-2xl backdrop-blur-md sm:p-7">
        
        {{-- Langkah 1 --}}
        <form id="step1" class="space-y-3.5">
            <div class="space-y-1 text-center">
                <h2 class="text-xl font-bold tracking-tight text-zinc-900">Atur Ulang Kata Sandi</h2>
                <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Verifikasi identitas akun Anda dengan nama pengguna dan kata sandi saat ini.</p>
            </div>

            <div class="text-left">
                <label for="email" class="mb-1 block text-xs font-semibold text-zinc-800">Nama Pengguna</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                       class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                       placeholder="email akun Anda">
                <span class="mt-1 block text-[10px] font-medium text-zinc-600">gunakan email akun Anda</span>
            </div>

            <div class="text-left">
                <label for="password_lama" class="mb-1 block text-xs font-semibold text-zinc-800">Kata Sandi Saat Ini</label>
                <div class="relative">
                    <input id="password_lama" name="password_lama" type="password" autocomplete="current-password" required
                           class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 pr-10 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                           placeholder="••••••••">
                    <button type="button" data-eye="password_lama" aria-label="Tampilkan kata sandi"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-600 hover:text-zinc-900 transition-colors">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button id="verify-btn" type="submit"
                    class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-white shadow-md transition-all duration-200 hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                Verifikasi Identitas
            </button>

            <div class="pt-1 text-center">
                <a href="/login" class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 hover:underline">Batal dan Kembali ke Halaman Masuk</a>
            </div>
        </form>

        {{-- Langkah 2 --}}
        <form id="step2" class="hidden space-y-3.5">
            <div class="space-y-1 text-center">
                <h2 class="text-xl font-bold tracking-tight text-zinc-900">Kata Sandi Baru</h2>
                <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Identitas terverifikasi. Tentukan kata sandi baru untuk akun <span id="shown-email" class="font-semibold text-zinc-900"></span>.</p>
            </div>

            <div class="text-left">
                <label for="password_baru" class="mb-1 block text-xs font-semibold text-zinc-800">Kata Sandi Baru</label>
                <div class="relative">
                    <input id="password_baru" name="password_baru" type="password" autocomplete="new-password" required
                           class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 pr-10 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                           placeholder="••••••••">
                    <button type="button" data-eye="password_baru" aria-label="Tampilkan kata sandi"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-600 hover:text-zinc-900 transition-colors">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="text-left">
                <label for="password_baru2" class="mb-1 block text-xs font-semibold text-zinc-800">Konfirmasi Kata Sandi Baru</label>
                <input id="password_baru2" name="password_baru2" type="password" autocomplete="new-password" required
                       class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                       placeholder="••••••••">
            </div>

            <button id="update-btn" type="submit"
                    class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-white shadow-md transition-all duration-200 hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                Update Password
            </button>

            <div class="pt-1 text-center">
                <a href="/login" class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 hover:underline">Batal dan Kembali ke Halaman Masuk</a>
            </div>
        </form>

        <div class="mt-5 border-t border-zinc-300/60 pt-3.5 text-center">
            <p class="text-[11px] font-medium text-zinc-600">&copy; {{ date('Y') }} Perumahan Luminpark</p>
        </div>
    </main>
</div>

<script src="/js/luminpark.js"></script>
<script>
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
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