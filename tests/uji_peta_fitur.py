# -*- coding: utf-8 -*-
"""Peta fitur aplikasi dan skenario UAT LuminPark.

Satu sumber untuk seluruh penyusun laporan Word (BAB III, BAB IV, dan analisis kesesuaian)
sehingga jumlah baris uji dan rujukan test case tidak pernah berbeda antar dokumen.
Struktur FITUR: (kode fitur, judul, pengantar, [test case id]).
Struktur UAT: (fitur, aktor, skenario, hasil diharapkan, [test case id]).
"""
import json

BASE = "C:/laragon/www/luminpark-main"
nf = json.load(open(f"{BASE}/tests/nf-results.json", encoding="utf-8"))

FITUR = [
    ("LOG", "Autentikasi dan Login",
     "Uji mencakup login tiga aktor, penolakan akun non-internal dan akun lama hasil pelebaran peran, kata sandi "
     "salah, token kedaluwarsa/palsu, logout, pembatasan laju percobaan masuk, serta halaman ganti dan reset sandi. "
     "Revisi 14: halaman atur ulang sandi tidak lagi meminta kata sandi lama karena bukti kepemilikan dipindahkan "
     "ke tautan verifikasi yang dikirim ke email akun. Revisi 34: tiga kali percobaan gagal pada satu email "
     "mengunci akun selama login_lockout_minutes yang dipilih super admin, dan percobaan keempat berbalas 429 "
     "beserta sisa waktu tunggu.",
     ["TC-A01", "TC-A02", "TC-A03", "TC-A04", "TC-A04b", "TC-A04c", "TC-A05", "TC-A06", "TC-A07", "TC-A08",
      "TC-A09", "TC-A16", "TC-Q01", "TC-Q02", "TC-Q03", "TC-R10", "TC-R11", "TC-R24", "FF-D03", "FF-D06", "FF-D07",
      "FF-R14", "FF-R33"]),
    ("SES", "Sesi Login, Log Aktivitas, dan Notifikasi",
     "Membuktikan setiap masuk/keluar tercatat beserta IP dan perangkat, lonceng notifikasi membaca data milik "
     "pengguna yang login, jejak audit data tersimpan pada activity_logs, serta sisa waktu sesi terlihat dan dapat "
     "diperpanjang. Revisi 30 memindahkan halaman Log Aktivitas dari Monitoring Manajemen (revisi 9) menjadi khusus "
     "Super Admin, dan revisi 29 melepas Admin Sales dari siaran booking, pembatalan, pembayaran, serta hasil "
     "verifikasi sehingga notifikasi alur sales hanya diterima Super Admin dan sales pemilik transaksi. Revisi 35 "
     "mengubah penghitung header menjadi batas waktu tanpa aktivitas: layar yang ditinggalkan keluar otomatis "
     "sedangkan layar yang masih dipakai diperpanjang senyap.",
     ["TC-A15", "TC-I01", "TC-I02", "TC-I03", "TC-I04", "TC-I05", "TC-I06", "TC-L04",
      "TC-O01", "TC-O02", "TC-O03", "TC-P01", "TC-P02", "TC-P03", "TC-R20", "TC-R21", "TC-R26",
      "FF-A15", "FF-A03", "FF-B01", "FF-A21", "FF-A23", "FF-R09", "FF-R26", "FF-R27", "FF-R34"]),
    ("AKS", "Role-Based Access Control",
     "Kelompok uji negatif dan positif untuk middleware peran: permintaan di luar hak menghasilkan 401/403 dan "
     "kendali tulis tidak dirender pada antarmuka. Revisi 18 menutup kelompok ini dengan susunan menu per peran, "
     "termasuk hilangnya menu Pembatalan Konsumen, revisi 25 menghapus menu History sehingga riwayat pembatalan "
     "dibaca lewat penapis Kategori Dibatalkan, dan revisi 28 menghapus berkas history.blade.php berikut sensus "
     "berkas Blade yang tidak lagi dirujuk rute.",
     ["TC-A10", "TC-A11", "TC-A12", "TC-A13", "TC-A14", "TC-K17", "TC-L01", "TC-L02", "TC-L03",
      "FF-A01", "FF-A02", "FF-B02", "FF-B07", "FF-C01", "FF-C02", "FF-C07", "FF-R18", "FF-R25"]),
    ("PRY", "Manajemen Data Proyek",
     "Siklus penuh proyek (dulu disebut cluster): daftar dan rincian, pembuatan, keunikan nama dalam satu perumahan, "
     "perubahan, pengarsipan, penolakan hapus pada proyek berisi unit, penghapusan lunak, serta pembatasan hak tulis "
     "dan halaman daftar khusus. Revisi 4 mengganti seluruh penyebutan Cluster menjadi Proyek pada antarmuka, "
     "pesan sistem, dan jejak audit, sementara revisi 31 melepas kolom Peta serta penapis Perumahan dari tabel "
     "Data Proyek.",
     ["TC-B02", "TC-B11", "TC-B12", "TC-B13", "TC-B14", "TC-B15", "TC-B17", "TC-B18",
      "TC-M01", "TC-M02", "TC-M03", "TC-M04", "TC-M05", "TC-M06", "TC-M07", "TC-M08", "TC-M09", "TC-M10",
      "TC-M11", "FF-A18", "FF-A19", "FF-B08", "FF-A20", "FF-B09", "FF-B11", "FF-R04", "FF-R28"]),
    ("PIL", "Pemisahan Data per Sales",
     "Butir d.4 dan penutup batasan D-4: daftar dan rincian booking, pembayaran, serta riwayat follow-up hanya "
     "memuat data yang berada di bawah penanganan sales yang login, sementara super admin dan Admin Sales tetap "
     "melihat seluruhnya.",
     ["TC-N01", "TC-N02", "TC-N03", "TC-N04", "TC-N05", "TC-N06"]),
    ("UNI", "Manajemen Data Unit Perumahan",
     "CRUD unit oleh super admin (tambah, ubah, hapus lunak), validasi kode dan spesifikasi, penolakan penghapusan "
     "unit berriwayat pemesanan, peralihan aksi edit ke halaman detail, serta penapis Blok, Status, dan Proyek "
     "sesuai revisi 11.",
     ["TC-B01", "TC-B03", "TC-B04", "TC-B05", "TC-B06", "TC-B07", "TC-B08", "TC-B09", "TC-B10", "TC-B16", "TC-L05",
      "TC-R09", "FF-A04", "FF-A05", "FF-A10", "FF-B03", "FF-B10", "FF-R11"]),
    ("KON", "Manajemen Calon Konsumen dan Follow Up",
     "Sales membuat dan memutakhirkan lead, mencatat riwayat follow-up, dan hanya melihat data miliknya; Admin "
     "Sales dan super admin membaca seluruh lead tanpa dapat menulis. Revisi 2 menambah tanggal chat awal, revisi 5 "
     "status pembatalan beserta riwayat pembeliannya, revisi 6-7 memisahkan menu Konsumen dari Database Konsumen "
     "(cold, warm, hot), dan revisi 15 menghitung konsumen hanya dari pembayaran yang terverifikasi. Revisi 19 dan "
     "24 mengunci satu nomor handphone untuk satu konsumen sehingga nomor tetap tidak dapat dipakai ulang walau "
     "lead masih cold atau sudah dibatalkan, revisi 22 menghapus tombol tambah data dari menu Konsumen karena "
     "daftarnya terisi otomatis, revisi 23 menambahkan tombol Batal beralasan beserta tanggal pada kolom Aksi, "
     "revisi 25 menghapus menu History dan memindahkan riwayat pembatalan ke penapis Kategori Dibatalkan, serta "
     "revisi 26-27 menyusun ulang penapis: Status hanya muncul pada Database Konsumen dan Kategori hanya pada menu "
     "Konsumen. Revisi 39 melepas kolom Status, pilihan Calon Konsumen, dan penapis Sales Penanganan dari daftar "
     "versi sales; revisi 40 mengubah penapis Sales Penanganan menjadi kotak pencarian yang berisi seluruh nama "
     "sales untuk Admin Sales dan Super Admin; revisi 41 mengganti label Unit Diminati menjadi Unit Ditempati serta "
     "menambah kolom Pesanan Tambahan pada kategori selain calon konsumen.",
     ["TC-C01", "TC-C02", "TC-C03", "TC-C04", "TC-C05", "TC-C06", "TC-C07", "TC-C08", "TC-C09", "TC-C10", "TC-C11",
      "TC-L06", "TC-R01", "TC-R02", "TC-R12", "TC-R15", "TC-R17", "TC-R19", "TC-R30", "TC-R31", "TC-R32",
      "FF-A08", "FF-A09", "FF-B04", "FF-C04",
      "FF-R02", "FF-R05", "FF-R06", "FF-R07", "FF-R15", "FF-R16", "FF-R19", "FF-R22", "FF-R23", "FF-R24",
      "FF-R38", "FF-R39", "FF-R40"]),
    ("BKG", "Skema Pembayaran dan Pemesanan Unit",
     "Tiga skema pelunasan, perhitungan otomatis uang muka/angsuran/sisa oleh server, penolakan lead yang belum "
     "berstatus hot, perubahan status unit setelah booking berhasil, pembatalan pemesanan yang menyisakan riwayat "
     "transaksi sesuai revisi 5 — termasuk pembatalan setelah lunas dan serah terima sesuai revisi 16 — serta "
     "pencatatan biaya tambahan konstruksi di luar harga utama unit sesuai revisi 17 yang kini dipilih melalui "
     "saklar ada/tidaknya permintaan tambahan (revisi 21) dan hanya dapat dibuat untuk konsumen hot pada antarmuka "
     "(revisi 20). Revisi 42 membuat pilihan unit berjenjang Proyek lalu Unit/blok dan mengeluarkan rumah yang sudah "
     "memegang booking aktif dari pilihan, baik pada GET /units-available maupun pada POST /bookings; revisi 43 "
     "menambahkan saklar 'Catat Pembayaran Sekarang' beserta kategori DP/Cicilan/Biaya Tambahan sehingga pembayaran "
     "pertama dapat dicatat sekaligus saat booking dibuat.",
     ["TC-D01", "TC-D02", "TC-D03", "TC-D04", "TC-D05", "TC-D06", "TC-D07", "TC-D08", "TC-D09", "TC-D10",
      "TC-R04", "TC-R13", "TC-R14", "TC-R16", "TC-R18", "TC-R33", "TC-R35",
      "FF-A16", "FF-B05", "FF-C06", "FF-R17", "FF-R20", "FF-R21", "FF-R41", "FF-R42"]),
    ("BYR", "Pencatatan dan Verifikasi Pembayaran",
     "Sales mengunggah bukti transfer, super admin memverifikasi atau menolak, status pelunasan diperbarui otomatis, "
     "koreksi pembayaran dapat dihapus lunak, dan pilihan metode pembayaran selain uang piutang tersedia bagi sales "
     "sesuai revisi 3. Sejak revisi 41 tabel Data Pembayaran juga menampilkan kolom Pesanan Tambahan dan Biaya "
     "Tambahan yang dibaca dari booking induknya, sehingga permintaan konstruksi ikut terlihat sebelum verifikasi "
     "(bukti pada klaster KON: TC-R30 dan FF-R40). Revisi 43 menambah kolom Kategori serta pilihan kategori "
     "DP/Cicilan/Biaya Tambahan pada modal Catat Pembayaran dengan nominal yang terisi otomatis sesuai kategori; "
     "hanya DP dan cicilan yang mengurangi sisa pembayaran dan memicu pelunasan, sedangkan pembayaran biaya "
     "tambahan berdiri di luar harga unit dan tetap dapat dicatat walau unitnya sudah lunas.",
     ["TC-E01", "TC-E02", "TC-E03", "TC-E04", "TC-E05", "TC-E06", "TC-E07", "TC-E08", "TC-E09", "TC-E10",
      "TC-R03", "TC-R34", "FF-A17", "FF-R03", "FF-R43"]),
    ("SLS", "Manajemen Data Sales dan Kinerja",
     "Super admin dan Admin Sales mengelola akun sales (tambah, ubah, nonaktifkan, reset sandi, hapus) serta "
     "memantau pencapaian target per periode. Revisi 8: akun sales baru otomatis memakai tanggal lahir "
     "(yyyymmdd) sebagai sandi awal dan wajib diganti setelah login. Revisi 36: halaman Detail Sales menambah seksi "
     "Foto Sales untuk mengunggah avatar yang kemudian dipakai kartu narahubung pada situs publik.",
     ["TC-F01", "TC-F02", "TC-F03", "TC-F04", "TC-F05", "TC-F06", "TC-F07", "TC-F08",
      "TC-R08", "FF-A06", "FF-A07", "FF-B06", "FF-R08"]),
    ("DSH", "Dashboard Tiap Aktor",
     "Rekapitulasi unit terjual/belum serah terima, pendapatan, jumlah sales, calon konsumen, dan konsumen sesuai "
     "peran yang login; revisi 10 menambahkan penapis proyek yang mengubah seluruh angka statistik, dan revisi 32 "
     "menggantikan kartu angka gabungan dengan tabel \"Ringkasan per Proyek\" — satu baris untuk tiap proyek "
     "termasuk ember \"(belum ada proyek)\" bagi lead yang belum menautkan unit.",
     ["TC-G01", "TC-G02", "TC-G03", "TC-G04", "TC-K11", "TC-K12", "TC-K13", "TC-R05", "TC-R23",
      "FF-R10", "FF-R29", "FF-R31"]),
    ("LPO", "Laporan, Ekspor, dan Laporan Khusus Sales",
     "Empat jenis laporan berjangka waktu, ekspor berkas CSV, lembar cetak berpengesahan, tabel laporan_penjualan "
     "hasil rekap, serta halaman Laporan Sales milik sales yang login. Revisi 32 menambahkan tabel ringkasan per "
     "proyek pada keempat tab laporan dan kolom Proyek pada berkas ekspor.",
     ["TC-G05", "TC-G06", "TC-G07", "TC-G08", "TC-G09", "TC-G10", "TC-G11", "TC-K14", "TC-K15", "TC-K16",
      "TC-R22", "FF-A13", "FF-C05", "FF-A22", "FF-R30"]),
    ("PUB", "Halaman Publik Konsumen",
     "Katalog unit, rincian unit beserta harga dan skema, narahubung sales lewat WhatsApp yang kini menampilkan foto "
     "sales (revisi 1), peta lokasi proyek pada beranda (revisi 12), halaman Tentang Kami tanpa informasi skema "
     "pembayaran (revisi 13), kabar terbaru, dan pengaturan tampilan yang dipatuhi halaman publik. Formulir "
     "pengisian calon konsumen sudah dihapus dari halaman publik sehingga tidak ada jalur tulis tanpa login. "
     "Revisi 36 memperbesar foto narahubung menjadi 80×80 px dan menambah unggahannya dari halaman Detail Sales; "
     "revisi 37 membuat penanda peta memperbesar sampai zoom jalan (z17) serta menambah tombol rute OSRM dan tautan "
     "Google Maps per proyek; revisi 38 menghapus kotak pencarian kode unit/tipe rumah dari halaman Daftar Unit "
     "beserta form pencariannya pada hero beranda, sehingga penelusuran konsumen memakai penapis proyek dan urutan "
     "harga.",
     ["TC-H01", "TC-H02", "TC-H03", "TC-H04", "TC-H05", "TC-H06", "TC-H08", "TC-K01", "TC-R06", "TC-R07",
      "TC-R27", "TC-R28", "TC-R29", "FF-D01", "FF-D02", "FF-D04", "FF-D08", "FF-R01", "FF-R12", "FF-R13",
      "FF-R35", "FF-R36", "FF-R37"]),
    ("SET", "Pengaturan Sistem dan Halaman Publik",
     "Pengaturan kunci (batas waktu tanpa aktivitas, durasi penguncian login, hero, saklar seksi, unit unggulan) "
     "hanya dapat diubah super admin dan divalidasi sebelum disimpan. Revisi 33 melepas seksi status serta grup "
     "integrasi login Google dari antarmuka Pengaturan tanpa mengubah penyimpanan maupun kontrak API, sehingga "
     "tombol Google pada halaman masuk tetap berfungsi.",
     ["TC-K02", "TC-K03", "TC-K04", "TC-K05", "TC-K06", "TC-L07", "TC-L08", "TC-L09", "TC-R25",
      "FF-A11", "FF-A12", "FF-D05", "FF-R32"]),
    ("PRF", "Profil Akun",
     "Profil mandiri per peran: unggah avatar, ubah identitas, tanpa field sales untuk super admin, dan tanpa field "
     "Sesi Aktif untuk sales.",
     ["TC-K07", "TC-K08", "TC-K09", "TC-K10", "FF-A14", "FF-C03"]),
    ("NFP", "Kebutuhan Non-Fungsional",
     "Waktu respons, keamanan kata sandi, penegakan batas sesi, jejak audit, penghapusan lunak, validasi, "
     "penanganan galat, lokalisasi pesan kesalahan, dan kompatibilitas peramban.",
     ["TC-Q04", "TC-Q05"] + [f"NF-{i:02d}" for i in range(1, len(nf) + 1)]),
]
UAT = [
    ("Autentikasi", "Super Admin, Admin Sales, Sales",
     "Pengguna login sesuai peran dan keluar dengan aman",
     "Login super admin, Admin Sales, dan Sales berhasil; akun konsumen dan akun lama ditolak",
     ["TC-A01", "TC-A02", "TC-A03", "TC-A04", "TC-A15", "FF-D03"]),
    ("Data Proyek", "Super Admin",
     "Super admin menambahkan proyek baru sebelum unit dibuat",
     "Proyek baru terbentuk, nama duplikat dalam satu perumahan ditolak, langsung tersedia pada form unit",
     ["TC-B12", "TC-B13", "TC-B16", "FF-A18", "FF-A19", "FF-R04"]),
    ("Data Unit", "Super Admin, Admin Sales",
     "Super admin menambah, mengubah, dan menghapus unit; Admin Sales hanya melihat",
     "Seluruh aksi berjalan; unit berriwayat pemesanan tidak dapat dihapus",
     ["TC-B03", "TC-B06", "TC-B09", "TC-B10", "TC-L05", "FF-A04", "FF-B03"]),
    ("Calon Konsumen", "Sales, Admin Sales, Super Admin",
     "Sales membuat lead, mencatat follow-up, dan menaikkan status sampai hot",
     "Riwayat follow-up tersimpan dan hanya lead milik sales yang tampil pada daftar miliknya",
     ["TC-C01", "TC-C05", "TC-C09", "FF-A08", "FF-C04"]),
    ("Pemesanan", "Sales",
     "Sales membuat booking dari lead berstatus hot dan memilih skema pelunasan",
     "Uang muka, angsuran, dan sisa dihitung otomatis; status unit berubah menjadi dipesan",
     ["TC-D01", "TC-D04", "TC-D05", "FF-C06"]),
    ("Pembayaran", "Sales, Super Admin",
     "Sales mengunggah bukti transfer dan super admin memverifikasi",
     "Verifikasi mengisi verified_by/verified_at, penolakan tidak mengubah sisa, pelunasan terdeteksi otomatis",
     ["TC-E01", "TC-E04", "TC-E07", "FF-A17"]),
    ("Data Sales", "Super Admin, Admin Sales",
     "Super admin dan Admin Sales mengelola akun sales beserta target",
     "Tambah, ubah, nonaktifkan, reset sandi, dan hapus berjalan; sales dengan riwayat ditolak dihapus",
     ["TC-F01", "TC-F04", "TC-F05", "TC-F08", "FF-A06", "FF-B06"]),
    ("Dashboard", "Semua aktor",
     "Tiap aktor melihat rekapitulasi sesuai perannya",
     "Dashboard super admin, Admin Sales, dan Sales menampilkan angka yang konsisten dengan basis data",
     ["TC-G01", "TC-G02", "TC-G03"]),
    ("Laporan", "Super Admin, Admin Sales, Sales",
     "Menyaring laporan penjualan, pelunasan, kinerja, dan konsumen lalu mengekspor",
     "Empat laporan terisi pada jendela 30 hari dan ekspor CSV menghasilkan berkas yang dapat dibuka",
     ["TC-G05", "TC-G06", "TC-G07", "TC-G08", "TC-G09", "TC-K14", "FF-A13", "FF-C05"]),
    ("Halaman Publik", "Konsumen tanpa login",
     "Konsumen melihat unit, skema pembayaran, dan menghubungi sales tanpa login",
     "Katalog hanya menampilkan unit tersedia; konsumen menghubungi sales lewat WhatsApp dan tidak ada form lead publik",
     ["TC-H01", "TC-H02", "TC-H05", "FF-D01", "FF-D02"]),
    ("Pengaturan", "Super Admin",
     "Super admin mengatur batas sesi dan tampilan halaman publik",
     "Perubahan tersimpan, nilai tidak valid ditolak, dan halaman publik mematuhi saklar",
     ["TC-K02", "TC-K03", "TC-K05", "FF-A11", "FF-D05"]),
    ("Profil dan Notifikasi", "Semua aktor",
     "Pengguna memutakhirkan profil dan membaca notifikasi pada lonceng",
     "Profil per peran tampil benar; jumlah notifikasi belum dibaca sesuai pengguna yang login",
     ["TC-A09", "TC-I02", "TC-K08", "FF-A03", "FF-C03"]),
    ("Hak Akses", "Semua aktor",
     "Setiap peran mencoba membuka modul di luar haknya",
     "Ditolak 401/403 pada API dan kendali tulis tidak dirender pada antarmuka",
     ["TC-A10", "TC-A11", "TC-A12", "TC-L01", "TC-L04", "FF-B07"]),
    ("Audit dan Sesi", "Monitoring Manajemen",
     "Admin Sales menelusuri sesi login dan log aktivitas sesuai revisi 9",
     "login_sessions merekam IP, perangkat, waktu masuk/keluar; halaman Log Aktivitas hanya terbuka bagi peran "
     "Monitoring Manajemen dan ditolak 403 bagi super admin maupun Sales",
     ["TC-A15", "TC-I01", "TC-I04", "TC-L04", "TC-O03", "FF-A15", "FF-R09"]),
    ("Penghapusan Data", "Super Admin",
     "Data yang dihapus hilang dari daftar tetapi tidak hilang permanen",
     "Baris tetap ada dengan deleted_at dan tidak muncul lagi pada daftar, laporan, maupun halaman publik",
     ["TC-L05", "TC-L06", "FF-A10"]),
    ("Konsumen, Prospek, dan Pembatalan", "Sales, Super Admin",
     "Sales mencatat tanggal chat awal, memisahkan Konsumen dari Database Konsumen, lalu membatalkan pemesanan "
     "miliknya (revisi 2, 5, 6, 7)",
     "Form lead menyimpan tanggal chat awal; menu Konsumen hanya menampilkan yang pembayarannya sudah diverifikasi "
     "dan Database Konsumen hanya cold/warm/hot; pembatalan memberi status Dibatalkan dan riwayat pembeliannya tetap "
     "terbaca",
     ["TC-R01", "TC-R02", "TC-R04", "FF-R02", "FF-R05", "FF-R06", "FF-R07"]),
    ("Metode Pembayaran", "Sales, Super Admin",
     "Sales memilih metode pembayaran selain uang piutang saat mencatat pembayaran (revisi 3)",
     "Pilihan metode tersimpan pada baris pembayaran, dapat dibaca kembali oleh super admin, dan tetap terbawa "
     "pada laporan serta ekspor",
     ["TC-R03", "FF-R03", "FF-A17"]),
    ("Statistik dan Penapis per Proyek", "Super Admin",
     "Super admin memilih satu proyek pada dashboard lalu menapis daftar unit berdasarkan Blok, Status, dan "
     "Proyek (revisi 10 dan 11)",
     "Seluruh kartu statistik berubah mengikuti proyek terpilih dan hasil penapis daftar unit cocok dengan "
     "jumlah pada basis data",
     ["TC-R05", "TC-R09", "FF-R10", "FF-R11"]),
    ("Halaman Publik Hasil Revisi", "Konsumen tanpa login",
     "Konsumen membuka peta proyek pada beranda, melihat foto sales pada daftar narahubung, dan membaca Tentang "
     "Kami yang tidak lagi memuat skema pembayaran (revisi 1, 12, 13)",
     "Peta menampilkan marker tiap proyek yang memiliki koordinat, kartu narahubung menampilkan foto dan nama "
     "sales, dan Tentang Kami bersih dari narasi KPR/cash",
     ["TC-R06", "TC-R07", "FF-R01", "FF-R12", "FF-R13", "FF-D01"]),
    ("Proyek pada Antarmuka dan Jejak Audit", "Super Admin",
     "Super admin membuat lalu melepas proyek sementara seluruh layar menyebutnya Proyek, bukan Cluster (revisi 4)",
     "Menu, judul halaman, pesan sistem, dan aksi pada activity_logs memakai istilah proyek; tabel basis data "
     "tetap clusters sehingga riwayat lama tidak berubah",
     ["TC-M02", "TC-M09", "TC-O02", "FF-A18", "FF-R04"]),
    ("Akun Sales Baru", "Super Admin, Sales",
     "Super admin menambahkan sales baru dan akun tersebut masuk memakai tanggal lahir sebagai sandi awal "
     "(revisi 8)",
     "Sandi awal yyyymmdd dapat dipakai login satu kali, sistem mewajibkan penggantian sandi, dan sandi baru "
     "harus memuat huruf besar, huruf kecil, angka, serta minimal delapan karakter",
     ["TC-R08", "TC-F01", "FF-R08", "FF-D06"]),
    ("Atur Ulang Sandi Lewat Email", "Super Admin, Admin Sales, Sales",
     "Pengguna yang lupa sandi mengisi email pada /reset-password, membuka tautan verifikasi di kotak masuk "
     "emailnya, lalu menentukan dan mengonfirmasi sandi baru tanpa pernah diminta sandi lama (revisi 14)",
     "Tautan hanya dikirim ke akun internal, balasan sistem seragam untuk email yang tidak terdaftar, token hanya "
     "sekali pakai, dan sandi baru langsung berlaku sementara sandi lama ditolak",
     ["TC-R10", "TC-R11"]),
    ("Riwayat Konsumen, Pembatalan Pascabayar, dan Biaya Tambahan", "Sales, Super Admin",
     "Sales menunggu verifikasi pembayaran konsumennya, membatalkan pemesanan konsumen yang sudah menempati rumah, "
     "dan mencatat request tambahan bangunan saat booking (revisi 15, 16, 17)",
     "Konsumen baru muncul di Data Konsumen setelah pembayaran diverifikasi, seluruh data yang sudah berpindah "
     "dibaca dari penapis Kategori Dibatalkan, pembatalan pascapelunasan mengeluarkan konsumen dari daftar "
     "Konsumen dengan unit kembali tersedia, dan biaya tambahan konstruksi tersimpan tanpa mengubah uang muka "
     "maupun angsuran",
     ["TC-R12", "TC-R13", "TC-R14", "FF-R15", "FF-R16", "FF-R17"]),
    ("Alur Sales dari Calon Konsumen sampai Kategori Dibatalkan", "Sales, Super Admin",
     "Sales menambahkan calon konsumen bernomor handphone unik, melakukan follow up cold/warm/hot, membuat booking "
     "hanya untuk lead hot dengan permintaan tambahan opsional, lalu membatalkan transaksi konsumen yang sudah "
     "membayar melalui tombol Batal pada Data Konsumen dan membacanya kembali pada penapis Kategori (revisi 18-27)",
     "Nomor handphone ganda ditolak dengan pesan yang jelas dan tetap terkunci walau lead masih cold atau sudah "
     "dibatalkan, pilihan konsumen pada form booking hanya berstatus Hot, isian keterangan dan biaya tambahan hanya "
     "muncul saat sales memilih 'ada', tombol Tambah Konsumen tidak lagi tersedia pada menu Konsumen karena daftar "
     "terisi otomatis setelah verifikasi, menu History tidak lagi muncul pada navigasi, konsumen yang dibatalkan "
     "berpindah ke kategori Dibatalkan beserta alasan, tanggal, serta riwayat pembayaran yang tetap tersimpan, dan "
     "penapis Status hanya tampil pada Database Konsumen sedangkan penapis Kategori hanya tampil pada menu Konsumen",
     ["TC-R15", "TC-R16", "TC-R17", "TC-R18", "TC-R19", "FF-R18", "FF-R19", "FF-R20", "FF-R21", "FF-R22",
      "FF-R23", "FF-R24"]),
]
