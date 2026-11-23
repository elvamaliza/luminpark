<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganti Kata Sandi Awal &middot; Luminpark</title>
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
<body class="min-h-full bg-zinc-50 text-zinc-900 antialiased">
<main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-6 py-12">
    <img src="/images/logo-wordmark.svg" alt="Logo Luminpark" class="h-12 w-auto self-start">

    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-xl font-bold text-zinc-900">Ganti Kata Sandi Awal</h1>
        <p class="mt-2 text-sm text-zinc-500">
            Anda masuk memakai kata sandi awal yang dibuatkan pengelola — tanggal lahir Anda dalam format
            <span class="font-semibold">yyyymmdd</span> (contoh 19950314). Ganti terlebih dahulu sebelum menggunakan
            sistem. Kata sandi baru harus minimal 8 karakter serta memuat huruf besar, huruf kecil, dan angka.
            Setelah berhasil, Anda akan diminta masuk kembali dengan kata sandi baru.
        </p>

        <p id="identitas" class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">Memuat data akun...</p>

        <form id="ganti-form" class="mt-6 space-y-4" novalidate>
            <div>
                <label for="password_lama" class="mb-1.5 block text-sm font-medium text-zinc-700">Kata Sandi Saat Ini</label>
                <input id="password_lama" type="password" autocomplete="current-password" required
                       class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                       placeholder="kata sandi awal Anda">
            </div>
            <div>
                <label for="password_baru" class="mb-1.5 block text-sm font-medium text-zinc-700">Kata Sandi Baru</label>
                <input id="password_baru" type="password" autocomplete="new-password" required minlength="8"
                       class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                       placeholder="minimal 8 karakter, huruf besar, kecil, dan angka">
                <span class="mt-1 block text-xs text-zinc-500">Contoh yang sah: Sales2026. Jangan gunakan tanggal lahir Anda.</span>
            </div>
            <div>
                <label for="password_baru2" class="mb-1.5 block text-sm font-medium text-zinc-700">Ulangi Kata Sandi Baru</label>
                <input id="password_baru2" type="password" autocomplete="new-password" required minlength="8"
                       class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                       placeholder="ketik ulang kata sandi baru">
            </div>

            <button id="ganti-submit" type="submit"
                    class="w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-bold uppercase tracking-wide text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                Simpan Kata Sandi Baru
            </button>
        </form>

        <button id="keluar" type="button" class="mt-3 w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
            Keluar dan masuk dengan akun lain
        </button>
    </div>
</main>

<script src="/js/luminpark.js?v={{ filemtime(public_path('js/luminpark.js')) }}"></script>
<script>
    const identitas = document.getElementById('identitas');
    const form = document.getElementById('ganti-form');
    const submitBtn = document.getElementById('ganti-submit');
    let emailAktif = '';

    if (!LP.token.get()) {
        location.replace('/login');
    }

    LP.me()
        .then(function (user) {
            if (!user) return;
            if (!user.must_change_password) {
                location.replace('/dashboard');
                return;
            }
            emailAktif = user.email;
            identitas.textContent = 'Akun: ' + user.name + ' (' + user.email + ') — peran ' + LP.roleLabel(user.role) + '.';
        })
        .catch(function (err) {
            identitas.textContent = err && err.message ? err.message : 'Akun tidak dapat dimuat. Silakan masuk kembali.';
        });

    document.getElementById('keluar').addEventListener('click', function () {
        LP.logout();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const lama = document.getElementById('password_lama').value;
        const baru = document.getElementById('password_baru').value;
        const ulang = document.getElementById('password_baru2').value;

        if (!lama || !baru || !ulang) {
            LP.toast('Semua kata sandi wajib diisi.', 'error');
            return;
        }
        if (baru.length < 8 || !LP.sandiKuat(baru)) {
            LP.toast(LP.PESAN_SANDI, 'error');
            return;
        }
        if (baru !== ulang) {
            LP.toast('Penulisan kata sandi baru belum sama.', 'error');
            return;
        }
        if (baru === lama) {
            LP.toast('Kata sandi baru harus berbeda dari kata sandi saat ini.', 'error');
            return;
        }

        LP.withBusy(submitBtn, async function () {
            try {
                const { json } = await LP.http.post('/reset-password', {
                    email: emailAktif,
                    password_lama: lama,
                    password_baru: baru,
                });
                LP.token.clear();
                LP.toast(json && json.message ? json.message : 'Kata sandi berhasil diubah.', 'success');
                setTimeout(function () { location.href = '/login'; }, 1400);
            } catch (err) {
                LP.toast(err.message || 'Kata sandi gagal diubah.', 'error');
            }
        });
    });
</script>
</body>
</html>
