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

        {{-- Langkah 1: isi email --}}
        <form id="step1" class="space-y-3.5">
            <div class="space-y-1 text-center">
                <h2 class="text-xl font-bold tracking-tight text-zinc-900">Lupa Password</h2>
                <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Masukkan email akun Anda. Kami kirim tautan verifikasi ke kotak masuk email tersebut.</p>
            </div>

            <div class="text-left">
                <label for="email" class="mb-1 block text-xs font-semibold text-zinc-800">Email Akun</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                       placeholder="email akun Anda">
                <span class="mt-1 block text-[10px] font-medium text-zinc-600">hanya akun super admin, admin sales, dan sales</span>
            </div>

            <button id="send-btn" type="submit"
                    class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-white shadow-md transition-all duration-200 hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                Kirim Verifikasi
            </button>

            <div class="pt-1 text-center">
                <a href="/login" class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 hover:underline">Batal dan Kembali ke Halaman Masuk</a>
            </div>
        </form>

        {{-- Langkah 2: cek email --}}
        <div id="step2" class="hidden space-y-3.5">
            <div class="space-y-1 text-center">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-white/70 text-zinc-900 shadow-sm">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                </span>
                <h2 class="text-xl font-bold tracking-tight text-zinc-900">Cek Email Anda</h2>
                <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Tautan verifikasi kami kirim ke <span id="shown-email" class="font-semibold text-zinc-900"></span>. Buka email tersebut lalu klik <span class="font-semibold">Atur Ulang Kata Sandi</span>.</p>
            </div>

            <div class="rounded-xl border border-zinc-300/70 bg-white/55 px-3 py-2.5 text-left">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-700">Catatan</p>
                <ul class="mt-1 space-y-1 text-[11px] leading-relaxed text-zinc-700">
                    <li>&bull; Tautan berlaku 10 menit dan hanya sekali pakai.</li>
                    <li>&bull; Tidak menemukan emailnya? Periksa kotak spam atau promotions.</li>
                    <li>&bull; Kata sandi lama Anda tetap berlaku sampai tautan dipakai.</li>
                </ul>
            </div>

            <button id="resend-btn" type="button"
                    class="w-full rounded-xl border border-zinc-900/20 bg-white/70 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-900 transition-all duration-200 hover:bg-white active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-55">
                Kirim Ulang Verifikasi
            </button>

            <div class="flex items-center justify-between gap-2 pt-1 text-center">
                <button id="back-to-email" type="button" class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 hover:underline">Ganti email</button>
                <a href="/login" class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 hover:underline">Kembali ke Halaman Masuk</a>
            </div>
        </div>

        {{-- Langkah 3: kata sandi baru (dituju lewat tautan verifikasi) --}}
        <form id="step3" class="hidden space-y-3.5">
            <div class="space-y-1 text-center">
                <h2 class="text-xl font-bold tracking-tight text-zinc-900">Kata Sandi Baru</h2>
                <p class="text-[11px] font-normal leading-relaxed text-zinc-700">Tautan verifikasi valid untuk <span id="shown-email-3" class="font-semibold text-zinc-900"></span>. Tentukan kata sandi baru, minimal 8 karakter dan memuat huruf besar, huruf kecil, serta angka.</p>
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
                <label for="password_baru2" class="mb-1 block text-xs font-semibold text-zinc-800">Verifikasi Kata Sandi Baru</label>
                <div class="relative">
                    <input id="password_baru2" name="password_baru2" type="password" autocomplete="new-password" required
                           class="w-full rounded-xl border border-zinc-300/80 bg-white/70 px-3 py-2.5 pr-10 text-xs font-normal text-zinc-900 outline-none transition-all duration-200 focus:border-zinc-900 focus:bg-white focus:ring-2 focus:ring-zinc-900/10"
                           placeholder="••••••••">
                    <button type="button" data-eye="password_baru2" aria-label="Tampilkan konfirmasi kata sandi"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-600 hover:text-zinc-900 transition-colors">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button id="save-btn" type="submit"
                    class="w-full rounded-xl bg-zinc-900 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-white shadow-md transition-all duration-200 hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60">
                Simpan
            </button>

            <div class="pt-1 text-center">
                <a href="/reset-password" id="minta-tautan-baru" class="text-[11px] font-semibold text-zinc-700 hover:text-zinc-900 hover:underline">Tautan tidak valid? Kirim verifikasi ulang</a>
            </div>
        </form>

        <div class="mt-5 border-t border-zinc-300/60 pt-3.5 text-center">
            <p class="text-[11px] font-medium text-zinc-600">&copy; {{ date('Y') }} Perumahan Luminpark</p>
        </div>
    </main>
</div>

<script src="/js/luminpark.js?v={{ filemtime(public_path('js/luminpark.js')) }}"></script>
<script>
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    const sendBtn = document.getElementById('send-btn');
    const resendBtn = document.getElementById('resend-btn');
    const saveBtn = document.getElementById('save-btn');

    const emailInput = document.getElementById('email');
    const baruInput = document.getElementById('password_baru');
    const baru2Input = document.getElementById('password_baru2');

    const query = new URLSearchParams(location.search);
    const state = { email: '', token: query.get('token') || '' };

    let jeda = 0;
    let timerJeda = null;

    document.querySelectorAll('[data-eye]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.eye);
            target.type = target.type === 'password' ? 'text' : 'password';
        });
    });

    // Alamat email disamarkan pada langkah "cek email" supaya tidak terbaca penuh di layar.
    function samarkan(email) {
        const [nama, domain] = String(email || '').split('@');
        if (!domain) return email || '-';
        const pendek = nama.slice(0, 2) + '***';
        return pendek + '@' + domain;
    }

    function tampil(form) {
        [step1, step2, step3].forEach(function (node) { node.classList.toggle('hidden', node !== form); });
    }

    function keLangkah2(email) {
        state.email = email;
        emailInput.value = email;
        document.getElementById('shown-email').textContent = samarkan(email);
        tampil(step2);
        mulaiJeda();
    }

    function mulaiJeda() {
        jeda = 60;
        resendBtn.disabled = true;
        clearInterval(timerJeda);
        timerJeda = setInterval(function () {
            jeda -= 1;
            if (jeda > 0) {
                resendBtn.textContent = 'Kirim Ulang dalam ' + jeda + ' detik';
                return;
            }
            clearInterval(timerJeda);
            resendBtn.disabled = false;
            resendBtn.textContent = 'Kirim Ulang Verifikasi';
        }, 1000);
        resendBtn.textContent = 'Kirim Ulang dalam ' + jeda + ' detik';
    }

    async function kirimVerifikasi(btn, email) {
        await LP.withBusy(btn, async function () {
            try {
                await LP.http.post('/forgot-password', { email }, { anonymous: true });
                keLangkah2(email);
                LP.toast('Tautan verifikasi sudah dikirim ke email Anda.', 'success');
            } catch (err) {
                LP.toast(err.message || 'Gagal mengirim tautan verifikasi.', 'error');
            }
        });
    }

    step1.addEventListener('submit', function (event) {
        event.preventDefault();
        const email = emailInput.value.trim();
        if (!email) {
            LP.toast('Email wajib diisi.', 'error');
            return;
        }
        kirimVerifikasi(sendBtn, email);
    });

    resendBtn.addEventListener('click', function () {
        if (resendBtn.disabled) return;
        kirimVerifikasi(resendBtn, state.email || emailInput.value.trim());
    });

    document.getElementById('back-to-email').addEventListener('click', function () {
        clearInterval(timerJeda);
        resendBtn.disabled = false;
        resendBtn.textContent = 'Kirim Ulang Verifikasi';
        tampil(step1);
        emailInput.focus();
    });

    /* Tautan email membuka halaman ini dengan ?token=...&email=... : langsung ke langkah 3. */
    if (state.token && query.get('email')) {
        state.email = query.get('email');
        document.getElementById('shown-email-3').textContent = samarkan(state.email);
        tampil(step3);
        // Alamat email pada URL tidak boleh tertinggal di riwayat peramban.
        history.replaceState(null, '', '/reset-password');
        baruInput.focus();
    } else if (query.get('email')) {
        emailInput.value = query.get('email');
    }

    step3.addEventListener('submit', function (event) {
        event.preventDefault();
        const baru = baruInput.value;
        const konfirmasi = baru2Input.value;

        if (!LP.sandiKuat(baru)) {
            LP.toast(LP.PESAN_SANDI, 'error');
            return;
        }
        if (baru !== konfirmasi) {
            LP.toast('Verifikasi password baru tidak sama.', 'error');
            return;
        }

        LP.withBusy(saveBtn, async function () {
            try {
                const res = await LP.http.post('/reset-password/token', {
                    email: state.email,
                    token: state.token,
                    password_baru: baru,
                }, { anonymous: true });
                LP.token.clear();
                state.token = '';
                LP.toast((res && res.message) || 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.', 'success');
                tampil(step1);
                setTimeout(function () { location.href = '/login'; }, 1400);
            } catch (err) {
                if (err && err.errors) {
                    const pesan = Object.values(err.errors).flat().join('. ');
                    LP.toast(pesan || err.message || 'Gagal memperbarui kata sandi.', 'error');
                } else {
                    LP.toast(err.message || 'Gagal memperbarui kata sandi.', 'error');
                }
                // Token yang ditolak tidak bisa dipakai lagi: minta tautan baru dari langkah 1.
                if (err.status === 400) {
                    state.token = '';
                    tampil(step1);
                }
            }
        });
    });
</script>
</body>
</html>
