# -*- coding: utf-8 -*-
"""BAB IV Pengujian dan Evaluasi Sistem LuminPark (seluruh lapisan, bukan hanya API).

Struktur mengikuti pola laporan referensi Kelompok 7SIB-4:
A. Test Case & Test Result (per fitur, tabel sepuluh kolom)
B. User Acceptance Test (UAT)
C. Evaluasi Sistem
D. Rencana Pengembangan Lanjutan

Sumber data: tests/conformance-results.json (API), tests/frontend-results.json (antarmuka),
tests/nf-results.json (non-fungsional) sehingga setiap baris berisi hasil eksekusi nyata.
"""
import json
import re
import datetime
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

BASE = "C:/laragon/www/luminpark-main"
hari = datetime.date.today()
hari_id = {1: "Januari", 2: "Februari", 3: "Maret", 4: "April", 5: "Mei", 6: "Juni", 7: "Juli",
           8: "Agustus", 9: "September", 10: "Oktober", 11: "November", 12: "Desember"}
tanggal = f"{hari.day} {hari_id[hari.month]} {hari.year}"

api = json.load(open(f"{BASE}/tests/conformance-results.json", encoding="utf-8"))
hard = json.load(open(f"{BASE}/tests/hardening-results.json", encoding="utf-8"))
fe = json.load(open(f"{BASE}/tests/frontend-results.json", encoding="utf-8"))
nf = json.load(open(f"{BASE}/tests/nf-results.json", encoding="utf-8"))

# Suite penguatan mencatat hasil aktual pada kolom catatan; dipindahkan ke kolom
# hasil aktual agar paparan laporannya seragam dengan suite lain.
for r in hard:
    r["actualBody"] = r.get("catatan") or ""
    r["catatan"] = ""

sumber = {r["id"]: r for r in api + hard + fe + nf}

# token sesi hasil uji tidak perlu ikut tercetak pada laporan
for r in api + hard + fe + nf:
    r["actualBody"] = re.sub(r'(access_token"\s*:\s*")[^"]+', r"\1••••••••", r["actualBody"])


def nf_angka(kode, pola, ke=1, bawaan="?"):
    """Ambil satu angka langsung dari baris hasil tests/run-nf-tests.mjs."""
    ketemu = re.search(pola, sumber[kode]["actualBody"])
    return ketemu.group(ke) if ketemu else bawaan


lambat = max(int(x) for x in re.findall(r"(\d+)ms\(", sumber["NF-01"]["actualBody"]))
rata_rata = nf_angka("NF-01", r"rata-rata (\d+)ms")
n500 = nf_angka("NF-07", r"respons 5xx=(\d+)")
permintaan = nf_angka("NF-07", r"dari (\d+) permintaan")
pesan4xx = nf_angka("NF-08", r"(\d+)/\d+ respons 4xx")
total4xx = nf_angka("NF-08", r"\d+/(\d+) respons 4xx")
validasi_n = nf_angka("NF-10", r"(\d+)/\d+ kasus invalid")
skenario_ui = nf_angka("NF-12", r"Chromium: (\d+)/\d+")
audit = {k: nf_angka("NF-05", rf"{k}=(\d+) baris") for k in ("system_logs", "login_sessions", "activity_logs")}

kf = json.load(open(f"{BASE}/tests/kf-matrix.json", encoding="utf-8"))
knf_id = [f"NF-{i:02d}" for i in range(1, len(nf) + 1)]
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

GREEN = RGBColor(0x1B, 0x7F, 0x3B)
RED = RGBColor(0xB0, 0x1F, 0x1F)
AMBER = RGBColor(0xB0, 0x6A, 0x00)
HDR = "2E5496"
SUB = "D9E2F3"

doc = Document()
doc.styles["Normal"].font.name = "Calibri"
doc.styles["Normal"].font.size = Pt(10)
sec = doc.sections[0]
sec.orientation = WD_ORIENT.LANDSCAPE
sec.page_width, sec.page_height = Cm(29.7), Cm(21.0)
for m in ("top_margin", "bottom_margin", "left_margin", "right_margin"):
    setattr(sec, m, Cm(1.2))


def shade(cell, hexc):
    el = OxmlElement("w:shd")
    el.set(qn("w:val"), "clear")
    el.set(qn("w:fill"), hexc)
    cell._tc.get_or_add_tcPr().append(el)


def para(text, size=10, bold=False, align=None, color=None):
    p = doc.add_paragraph()
    r = p.add_run(text)
    r.font.size = Pt(size)
    r.bold = bold
    if color:
        r.font.color.rgb = color
    if align:
        p.alignment = align
    return p


def bullet(text, style="List Bullet"):
    doc.add_paragraph(text, style=style)


def huruf(text, style="List Number"):
    return doc.add_paragraph(text, style=style)


def head(text, lvl=1):
    doc.add_heading(text, level=lvl)


def repeat_header(t):
    pr = t.rows[0]._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    pr.append(el)


def table(headers, widths, data, sizes=None, bold_cols=(), center_cols=(), status_col=None, repeat=True):
    t = doc.add_table(rows=1, cols=len(headers))
    t.style = "Light Grid Accent 1"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.autofit = False
    for i, hd in enumerate(headers):
        c = t.rows[0].cells[i]
        c.text = ""
        r = c.paragraphs[0].add_run(hd)
        r.bold = True
        r.font.size = Pt(8)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        shade(c, HDR)
        c.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
    for row in data:
        cells = t.add_row().cells
        for i, val in enumerate(row):
            c = cells[i]
            c.text = ""
            p = c.paragraphs[0]
            r = p.add_run(str(val))
            r.font.size = Pt((sizes or [9] * len(headers))[i])
            if i in bold_cols:
                r.bold = True
            if i in center_cols:
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            if status_col is not None and i == status_col:
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                r.bold = True
                warna = {"Pass": GREEN, "Diterima": GREEN, "Sesuai": GREEN, "Sebagian": AMBER,
                         "Fail": RED, "Belum": RED}.get(str(val))
                if warna:
                    r.font.color.rgb = warna
                isi = {"Pass": "E3F2E3", "Diterima": "E3F2E3", "Sesuai": "E3F2E3",
                       "Sebagian": "FFF3E0", "Fail": "FBE4E4", "Belum": "FBE4E4"}.get(str(val))
                if isi:
                    shade(c, isi)
    for row in t.rows:
        for i, c in enumerate(row.cells):
            c.width = widths[i]
    if repeat:
        repeat_header(t)
    return t


def potong(teks, n=300):
    teks = (teks or "-").strip()
    return teks if len(teks) <= n else teks[:n].rstrip() + "…"


# ============================== COVER ==============================
para("BAB IV", 20, True, WD_ALIGN_PARAGRAPH.CENTER)
para("PENGUJIAN DAN EVALUASI SISTEM", 16, True, WD_ALIGN_PARAGRAPH.CENTER)
para("SISTEM INFORMASI MANAJEMEN PERUMAHAN LUMIN PARK BERBASIS WEB", 12, True, WD_ALIGN_PARAGRAPH.CENTER)
doc.add_paragraph()
table(
    ["Aspek", "Keterangan"],
    [Cm(6.0), Cm(21.3)],
    [
        ["Objek pengujian", "Seluruh lapisan sistem: API REST Laravel 10 (backend), antarmuka Blade + JavaScript "
                            "klien (frontend), halaman publik konsumen, serta kebutuhan non-fungsional"],
        ["Metode", "Black box testing berbasis permintaan HTTP nyata, observasi DOM pada peramban, pengukuran waktu "
                   "respons, dan pembacaan langsung kondisi basis data MySQL"],
        ["Jumlah pengujian", f"{len(api)} uji API + {len(hard)} uji penguatan + {len(fe)} uji antarmuka + "
                             f"{len(nf)} uji non-fungsional "
                             f"= {len(api) + len(hard) + len(fe) + len(nf)} test case eksekusi nyata. Test case yang "
                             f"sama dipaparkan tiga sudut: {len(kf)} butir kebutuhan fungsional (A.2), {len(nf)} "
                             f"butir kebutuhan non-fungsional (A.3), dan {len(FITUR)} fitur aplikasi (A.4); ditambah "
                             f"{len(UAT)} skenario UAT (B). Penomoran tidak dihitung ulang sehingga tidak ada "
                             "pengujian ganda."],
        ["Alat", "Node.js 22 (tests/run-conformance-tests.mjs, tests/run-hardening-tests.mjs, dan "
                 "tests/run-nf-tests.mjs), Chromium automation untuk observasi DOM, klien MySQL 8.4.3, PHP 8.3.33 "
                 "(artisan tinker untuk probe basis data)"],
        ["Lingkungan", "Windows 10 x64, Laragon; http://127.0.0.1:8081/api (basis data uji luminpark_test) untuk "
                       "suite otomatis dan observasi antarmuka, sedangkan http://127.0.0.1:8080 memakai basis data "
                       "kerja luminpark dan hanya dipakai untuk melihat tampilan"],
        ["Kondisi awal", "DB_DATABASE=luminpark_test php artisan migrate:fresh --seed (22 tabel) agar setiap angka "
                         "berasal dari data demo yang dikenal"],
        ["Tanggal pengujian", tanggal],
        ["Hasil keseluruhan", f"Lulus {sum(1 for r in api + hard + fe + nf if r['status'] == 'Pass')} "
                              f"| Gagal {sum(1 for r in api + hard + fe + nf if r['status'] != 'Pass')}"],
    ],
    sizes=[9, 9], bold_cols=(0,),
)
doc.add_page_break()

# ============================== A. TEST CASE & TEST RESULT ==============================
head("A. Test Case & Test Result", 1)
para("Bagian A disusun empat sudut: A.1 rekapitulasi jumlah test case, A.2 satu baris untuk setiap butir kebutuhan "
     "fungsional, A.3 satu baris untuk setiap butir kebutuhan non-fungsional, dan A.4 daftar test case eksekusi "
     "per fitur aplikasi. Kolom dipakai sesuai format pengujian: ID, Nama, Skenario, Deskripsi, Langkah Uji, Data "
     "Uji, Hasil yang Diharapkan, Hasil Aktual, Status (Pass/Fail), dan Catatan. Hasil Aktual berisi rekaman keluaran "
     "sesungguhnya — kode status beserta potongan respons untuk uji API, fakta DOM untuk uji antarmuka, dan nilai "
     "terukur untuk uji non-fungsional. Kolom Catatan mencantumkan identitas test case pada skrip otomatis agar "
     "setiap baris dapat diulang.", 9)
para("Legenda kode: TC-* = permintaan HTTP nyata (tests/run-conformance-tests.mjs, dan tests/run-hardening-tests.mjs "
     "untuk kelompok TC-M proyek, TC-N pemisahan data sales, TC-O jejak audit, TC-P manajemen sesi, TC-Q keamanan "
     "serta lokalisasi pesan), FF-* = observasi DOM pada peramban (tests/frontend-results.json), NF-* = pengukuran "
     "non-fungsional dan probe basis data (tests/run-nf-tests.mjs). Kode KF, KNF, dan UAT pada A.2, A.3, serta "
     "bagian B adalah baris rangkuman yang menunjuk kembali test case tersebut, bukan pengujian tambahan, sehingga "
     f"jumlah test case executed tetap {len(api) + len(hard) + len(fe) + len(nf)}. Nilai access_token pada Hasil "
     "Aktual disamarkan karena bersifat rahasia meskipun berasal dari basis data uji.", 9, True)

SKRIP_KLASTER = {"M", "N", "O", "P", "Q"}
BUATAN = {
    "TC": "tests/run-conformance-tests.mjs",
    "FF": "tests/frontend-results.json",
    "NF": "tests/run-nf-tests.mjs",
}


def asal_skrip(kode):
    """Berkas/skrip tempat satu test case dicatat."""
    if kode.startswith("TC-") and kode[3:4] in SKRIP_KLASTER:
        return "tests/run-hardening-tests.mjs"
    return BUATAN[kode[:2]]

# daftar FITUR dipindahkan ke bagian data di awal berkas

# --- validasi cakupan: setiap test case wajib masuk tepat satu fitur ---
pakai = [sid for _, _, _, daftar in FITUR for sid in daftar]
bila_ganda = [s for s in set(pakai) if pakai.count(s) > 1]
hilang = [r["id"] for r in api + hard + fe + nf if r["id"] not in sumber]
belum = sorted(set(sumber) - set(pakai))
assert not hilang, f"test case hilang: {hilang}"
assert not bila_ganda, f"test case masuk lebih dari satu fitur: {bila_ganda}"
assert not belum, f"test case belum dipetakan ke fitur mana pun: {belum}"

COLS = ["ID", "Nama", "Skenario", "Deskripsi", "Langkah Uji", "Data Uji", "Hasil yang Diharapkan",
        "Hasil Aktual", "Status\n(Pass/Fail)", "Catatan"]
W = [Cm(1.2), Cm(2.5), Cm(1.9), Cm(3.3), Cm(3.1), Cm(2.8), Cm(3.2), Cm(4.3), Cm(1.2), Cm(3.5)]
UKURAN = [6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5, 6.5]


def baris(uji, kode):
    rujukan = f"Rujukan skrip: {uji['id']} ({asal_skrip(uji['id'])}). "
    metodenya = f"{uji['method']} {uji['path']}".strip()
    asal = f"Mode: {metodenya}. " if metodenya not in ("UI", "NF") else ""
    return [
        kode,
        uji["nama"],
        uji["skenario"],
        uji["deskripsi"],
        uji["langkah"],
        uji["dataUji"],
        uji["ekspektasi"],
        potong(uji["actualBody"], 320),
        "Pass" if uji["status"] == "Pass" else "Fail",
        asal + rujukan + (uji["catatan"] or "Sesuai harapan."),
    ]


MODUL = {
    "a": "Manajemen Autentikasi", "b": "Manajemen Data Unit", "c": "Manajemen Pemesanan",
    "d": "Manajemen Pembayaran dan Pelunasan", "e": "Manajemen Calon Konsumen",
    "f": "Dashboard dan Laporan", "g": "Informasi untuk Konsumen", "h": "Manajemen Data Sales",
}


PETA_MODUL = {
    "Keputusan akun": "Keputusan jumlah akun login",
    "Keputusan hapus lunak": "Keputusan penghapusan lunak",
    "Keputusan sandi sales": "Keputusan sandi awal sales",
    "Keputusan hak hanya-lihat": "Keputusan hak Admin Sales",
    "ERD Gambar 2.24": "Perancangan basis data (ERD)",
    "Tambahan: Profil akun": "Profil akun mandiri",
    "Tambahan: Notifikasi": "Notifikasi",
    "Tambahan: Pengaturan sistem": "Pengaturan sistem",
    "Tambahan: Halaman publik": "Pengaturan halaman publik",
    "Keamanan akses menyeluruh": "Keamanan akses lintas modul",
    "Umpan balik sistem": "Kualitas pesan dan konsistensi antarmuka",
    "Integritas masukan": "Integritas data dan pencegahan duplikasi",
    "Revisi 1": "Halaman publik — foto narahubung",
    "Revisi 2": "Calon konsumen — tanggal chat awal",
    "Revisi 3": "Pembayaran — metode aktual",
    "Revisi 4": "Data proyek — penamaan istilah",
    "Revisi 5": "Pemesanan — pembatalan berriwayat",
    "Revisi 6": "Data konsumen — judul super admin",
    "Revisi 7": "Konsumen vs Database Konsumen",
    "Revisi 8": "Data sales — sandi awal",
    "Revisi 9": "Log aktivitas — hak akses",
    "Revisi 10": "Dashboard — filter proyek",
    "Revisi 11": "Data unit — penapis blok/status/proyek",
    "Revisi 12": "Beranda publik — peta proyek",
    "Revisi 13": "Tentang Kami — tanpa skema pembayaran",
    "Revisi 14": "Atur ulang sandi — verifikasi email",
    "Revisi 15": "Konsumen — terisi setelah verifikasi",
    "Revisi 16": "Pembatalan pascapelunasan",
    "Revisi 17": "Pemesanan — biaya tambahan konstruksi",
    "Revisi 18": "Navigasi — menu per peran",
    "Revisi 19": "Calon konsumen — nomor handphone unik",
    "Revisi 20": "Pemesanan — konsumen berstatus Hot",
    "Revisi 21": "Pemesanan — saklar permintaan tambahan",
    "Revisi 22": "Data konsumen — tanpa tombol tambah",
    "Revisi 23": "Data konsumen — tombol Batal beralasan",
    "Revisi 24": "Calon konsumen — nomor terkunci permanen",
    "Revisi 25": "Navigasi — History digabung ke Kategori",
    "Revisi 26": "Data konsumen — penapis Status",
    "Revisi 27": "Database Konsumen — penapis Kategori",
}


def modul_dari(kode):
    if kode in PETA_MODUL:
        return PETA_MODUL[kode]
    return MODUL.get(kode[:1], "Kebutuhan tambahan hasil elisitasi")


def label(kalimat, batas=68):
    kata = kalimat.rstrip(".;").split()
    hasil = ""
    for w in kata:
        if len(hasil) + len(w) + 1 > batas:
            break
        hasil = f"{hasil} {w}".strip()
    return hasil if hasil else kalimat[:batas]


def gabung(ids, kolom, maksimum=3, lebar=110):
    potongan = []
    for i, t in enumerate(ids[:maksimum], 1):
        nilai = re.sub(r"\s+", " ", str(sumber[t].get(kolom) or "-")).strip()
        nilai = re.sub(r"^1\.\s*", "", nilai)
        if len(nilai) > lebar:
            nilai = nilai[:lebar].rstrip() + "…"
        potongan.append(f"{i}. [{t}] {nilai}")
    if len(ids) > maksimum:
        potongan.append(f"({len(ids) - maksimum} test case lainnya dilihat pada tabel A.4)")
    return "\n".join(potongan)


def rujukan_fitur(ids):
    bagian = [f"A.4.{i}" for i, (_, _, _, daftar) in enumerate(FITUR, 1) if set(daftar) & set(ids)]
    return ", ".join(bagian)


for r in kf:
    assert all(t in sumber for t in r["rujukan"]), f"rujukan tidak ada pada berkas hasil: {r['kode']}"


def baris_kebutuhan(r, kode):
    ids = r["rujukan"]
    n_api = sum(1 for t in ids if t.startswith("TC"))
    n_ui = sum(1 for t in ids if t.startswith("FF"))
    n_nf = sum(1 for t in ids if t.startswith("NF"))
    campuran = f"{len(ids)} test case ({n_api} permintaan API"
    if n_ui:
        campuran += f", {n_ui} observasi antarmuka"
    if n_nf:
        campuran += f", {n_nf} pengukuran non-fungsional"
    campuran += ")"
    lulus = all(sumber[t]["status"] == "Pass" for t in ids)
    butiran = r["kode"][:1].lower() in MODUL
    awal = f"Butir {r['kode']} pada modul" if butiran else "Kebutuhan tambahan ini berada pada modul"
    sebutan = (f"Kebutuhan fungsional butir {r['kode']}" if butiran
               else "Kebutuhan tambahan hasil elisitasi (di luar daftar a-h laporan)")
    catatan = (f"{awal} {modul_dari(r['kode'])}; dibuktikan {campuran} — rincian pada "
               f"{rujukan_fitur(ids)}. Kesesuaian perancangan: {r['status']}. ")
    if r["status"] != "Sesuai":
        catatan += f"Catatan penyelia: {(r['catatan'] or '').strip()} "
    catatan += "Seluruh kolom dihitung ulang dari berkas hasil tests/conformance-results.json, " \
               "tests/frontend-results.json, dan tests/nf-results.json."
    perlakuan = potong(re.sub(r"\s+", " ", r["impl"]).strip(), 190)
    return [
        kode,
        label(r["kebutuhan"]),
        modul_dari(r["kode"]),
        f"{sebutan}: {r['kebutuhan'].rstrip('.')} | Perlakuan sistem: {perlakuan}",
        gabung(ids, "langkah"),
        gabung(ids, "dataUji", maksimum=2, lebar=95),
        f"{r['kebutuhan'].rstrip('.')} dapat diamati pada respons API dan/atau antarmuka. | "
        + gabung(ids, "ekspektasi", maksimum=2, lebar=95),
        gabung(ids, "actualBody", maksimum=2, lebar=150),
        "Pass" if lulus else "Fail",
        catatan,
    ]


head("A.1 Rekapitulasi pengujian per fitur", 2)
rekap = []
for idx, (pref, judul, _, daftar) in enumerate(FITUR, 1):
    baris_fitur = [sumber[s] for s in daftar]
    rekap.append([
        f"A.4.{idx}",
        judul,
        str(sum(1 for r in baris_fitur if r["id"].startswith("TC"))),
        str(sum(1 for r in baris_fitur if r["id"].startswith("FF"))),
        str(sum(1 for r in baris_fitur if r["id"].startswith("NF"))),
        str(len(baris_fitur)),
        str(sum(1 for r in baris_fitur if r["status"] == "Pass")),
        str(sum(1 for r in baris_fitur if r["status"] != "Pass")),
        f"{pref} 01…{len(baris_fitur):02d}",
    ])
rekap.append(["TOTAL", f"{len(FITUR)} fitur", str(len(api) + len(hard)), str(len(fe)), str(len(nf)),
              str(len(api) + len(hard) + len(fe) + len(nf)),
              str(sum(1 for r in api + hard + fe + nf if r["status"] == "Pass")),
              str(sum(1 for r in api + hard + fe + nf if r["status"] != "Pass")), "-"])
t = table(["Bagian", "Fitur yang diuji", "Uji API", "Uji UI", "Uji NF", "Total", "Lulus", "Gagal", "Kode ID"],
          [Cm(2.0), Cm(7.1), Cm(2.0), Cm(1.8), Cm(1.8), Cm(1.8), Cm(1.8), Cm(1.8), Cm(7.1)],
          rekap, sizes=[8] * 9, center_cols=(0, 2, 3, 4, 5, 6, 7), bold_cols=(0,))
for c in t.rows[len(rekap)].cells:
    shade(c, SUB)
    for r in c.paragraphs[0].runs:
        r.bold = True
doc.add_page_break()

# --- A.2 black box per butir kebutuhan fungsional ---
head("A.2 Pengujian black box berdasarkan kebutuhan fungsional", 2)
para(f"Tabel ini memotret {len(api) + len(hard) + len(fe) + len(nf)} test case eksekusi nyata dari sudut kebutuhan "
     "fungsional BAB III dan daftar revisi, bukan dari sudut fitur "
     "aplikasi. Kolom Langkah Uji, Data Uji, Hasil yang Diharapkan, dan Hasil Aktual disusun otomatis dari baris "
     "test case yang dirujuk, sehingga setiap butir dapat ditelusuri kembali ke perintah HTTP atau observasi DOM "
     "yang sungguh dijalankan. Status Pass berarti seluruh test case perujuk butir tersebut lulus; kolom Catatan "
     "menyatakan kesesuaian perancangan bila butir dinilai Sebagian.", 9)
kf_pass = sum(1 for r in kf if all(sumber[t]["status"] == "Pass" for t in r["rujukan"]))
para(f"Banyak butir kebutuhan fungsional: {len(kf)} butir; {kf_pass} butir dinyatakan Pass dan "
     f"{len(kf) - kf_pass} butir Fail.", 9, True)
data = [baris_kebutuhan(r, f"KF {i:02d}") for i, r in enumerate(kf, 1)]
table(COLS, W, data, sizes=UKURAN, bold_cols=(0, 8), center_cols=(0, 8), status_col=8)
doc.add_page_break()

# --- A.3 black box per butir kebutuhan non-fungsional ---
head("A.3 Pengujian black box berdasarkan kebutuhan non-fungsional", 2)
para("Kebutuhan non-fungsional diuji dengan pengukuran langsung pada sistem yang berjalan: waktu respons diukur dari "
     "sisi klien, keamanan dan jejak audit dibaca dari basis data, sedangkan kepatuhan validasi, penegakan peran, "
     "dan kualitas pesan agregat dihitung dari seluruh test case suite API dan antarmuka.", 9)
data = []
for i, kode in enumerate(knf_id, 1):
    r = sumber[kode]
    data.append([
        f"KNF {i:02d}", r["nama"], r["skenario"], r["deskripsi"], r["langkah"], r["dataUji"], r["ekspektasi"],
        potong(r["actualBody"], 320), "Pass" if r["status"] == "Pass" else "Fail",
        f"Mode: pengukuran terukur (bukan permintaan fungsional). Rujukan skrip: {kode} "
        f"({BUATAN['NF']}). " + (r["catatan"] or "Sesuai harapan."),
    ])
table(COLS, W, data, sizes=UKURAN, bold_cols=(0, 8), center_cols=(0, 8), status_col=8)
doc.add_page_break()

# --- A.4 detail eksekusi per fitur ---
head("A.4 Detail eksekusi uji per fitur aplikasi", 2)
para("Bagian ini menampilkan setiap test case apa adanya per fitur aplikasi, termasuk identitas test case pada skrip "
     "otomatis di kolom Catatan. Bagi uji API, kolom Hasil Aktual memuat kode status HTTP disertai potongan respons "
     "server yang sebenarnya; bagi uji antarmuka memuat fakta DOM hasil render halaman; bagi uji non-fungsional "
     "memuat angka hasil pengukuran.", 9)
for idx, (pref, judul, pengantar, daftar) in enumerate(FITUR, 1):
    head(f"A.4.{idx} {judul}", 2)
    para(pengantar, 9)
    data = [baris(sumber[s], f"{pref} {i:02d}") for i, s in enumerate(daftar, 1)]
    table(COLS, W, data, sizes=UKURAN, bold_cols=(0, 8), center_cols=(0, 8), status_col=8)
    doc.add_page_break()

# ============================== B. UAT ==============================
head("B. User Acceptance Test (UAT)", 1)
para("User Acceptance Testing (UAT) merupakan tahap pengujian yang dilakukan dari sudut pandang pengguna akhir "
     "untuk memastikan Sistem Informasi Manajemen Perumahan Lumin Park telah berjalan sesuai kebutuhan operasional "
     "penjualan perumahan, standar kualitas, dan alur bisnis yang berlaku pada BAB III. UAT dilaksanakan dengan "
     "memakai akun tiap aktor (super admin, Admin Sales, dan Sales) serta jalur konsumen tanpa login, lalu setiap "
     "skenario dinilai Diterima apabila alurnya dapat diselesaikan dari awal sampai akhir tanpa galat.", 9)
para("UAT bertujuan menilai apakah sistem telah:", 9)
for t in ["memenuhi kebutuhan pengguna pada butir a sampai h BAB III,",
          "mendukung alur kerja penjualan unit dari calon konsumen sampai pelunasan,",
          "menyediakan fungsi sesuai spesifikasi dan wireframe,",
          "layak diimplementasikan pada lingkungan operasional."]:
    bullet(t)
para("Peserta pengujian adalah tim pengembang yang berperan sebagai super admin, Admin Sales, dan Sales, ditambah "
     "satu penguji eksternal sebagai konsumen pada halaman publik. Isian nama penguji, tanggal, dan tanda tangan "
     "disesuaikan dengan pelaksanaan UAT yang sebenarnya.", 9, True)

# daftar skenario UAT didefinisikan pada bagian data di awal berkas
for _, _, _, _, refs in UAT:
    assert all(t in sumber for t in refs), f"skenario UAT merujuk test case yang tidak ada: {refs}"


def baris_uat(r, kode):
    fitur, aktor, skenario, hasil, refs = r
    bagian = rujukan_fitur(refs)
    n_api = sum(1 for t in refs if t.startswith("TC"))
    n_ui = sum(1 for t in refs if t.startswith("FF"))
    lulus = all(sumber[t]["status"] == "Pass" for t in refs)
    return [
        kode, fitur, f"UAT aktor: {aktor}",
        f"Skenario: {skenario}. | Dinyatakan diterima apabila {hasil.lower()}.",
        gabung(refs, "langkah", maksimum=2, lebar=100),
        f"Akun {aktor.lower()} (password123). " + gabung(refs, "dataUji", maksimum=2, lebar=90),
        f"{skenario} — {hasil}." + " | " + gabung(refs, "ekspektasi", maksimum=2, lebar=90),
        f"{hasil}. " + gabung(refs, "actualBody", maksimum=2, lebar=140),
        "Pass" if lulus else "Fail",
        f"Diterima dalam UAT karena {len(refs)} test case ({n_api} API, {n_ui} antarmuka) seluruhnya lulus; "
        f"rincian pada {bagian}. Isian nama dan tanggal tanda tangan mengikuti pelaksanaan UAT yang sebenarnya.",
    ]


para(f"Format tabel UAT mengikuti format pengujian sepuluh kolom; kolom Hasil Aktual dirangkum dari test case yang "
     f"menutup skenario tersebut sehingga penilaian pengguna tetap dapat ditelusuri ke bukti teknis.", 9)
data = [baris_uat(r, f"UAT {i:02d}") for i, r in enumerate(UAT, 1)]
table(COLS, W, data, sizes=UKURAN, bold_cols=(0, 8), center_cols=(0, 8), status_col=8)
doc.add_paragraph()
head("B.1 Lembar pengesahan hasil UAT", 2)
para("Lembar berikut diisi pada saat pelaksanaan UAT bersama pengguna; kolom nama, tanggal, dan tanda tangan tidak "
     "dapat dihasilkan dari berkas hasil pengujian otomatis.", 9)
table(["Nama Penguji", "Peran", "Tanggal", "Tanda Tangan"],
      [Cm(8.0), Cm(6.5), Cm(5.0), Cm(7.8)],
      [["", "", "", ""] for _ in range(4)], sizes=[9] * 4)
doc.add_page_break()

# ============================== C. EVALUASI SISTEM ==============================
head("C. Evaluasi Sistem", 1)

def rentang(pref, awal=1):
    """Kode baris A.4 satu fitur, mis. PRY 01…PRY 25, dihitung dari FITUR."""
    banyak = next((len(daftar) for p, _, _, daftar in FITUR if p == pref), 0)
    return f"{pref} {awal:02d}…{pref} {banyak:02d}"


head("C.1 Kesesuaian sistem dengan kebutuhan pengguna", 2)
para("Hasil pengujian pada bagian A menunjukkan bahwa sistem (butir kebutuhan per modul tersedia pada tabel A.2, "
     f"kode KF 01…KF {len(kf):02d}):", 9)
for t in [
    "menyediakan autentikasi berbasiskan peran untuk tiga akun login (super admin, Admin Sales, dan Sales), "
    "menutup jalur masuk bagi konsumen, dan membatati laju percobaan masuk "
    f"({rentang('LOG', 2)}, {rentang('AKS')});",
    f"mengelola data proyek (revisi 4: istilah cluster diganti Proyek) sebagai induk unit sampai siklus arsip dan "
    f"hapus, termasuk pencegahan nama ganda dalam satu perumahan dan halaman daftar khusus ({rentang('PRY')});",
    f"mengelola data unit perumahan lengkap dengan status, validasi, dan penjaga integritas data pemesanan "
    f"({rentang('UNI')});",
    f"mencatat calon konsumen, riwayat follow-up, dan pemisahan data antar sales ({rentang('KON')}, {rentang('PIL')});",
    f"memproses pemesanan beserta perhitungan uang muka, angsuran, dan sisa pembayaran secara otomatis di server "
    f"({rentang('BKG')});",
    f"mencatat pembayaran, memverifikasi bukti transfer, dan menurunkan status pelunasan otomatis ({rentang('BYR')});",
    f"mengelola akun sales, target, dan kinerjanya ({rentang('SLS')});",
    f"menyajikan dashboard, empat jenis laporan, ekspor CSV, lembar cetak berpengesahan, dan laporan khusus sales "
    f"(DSH, {rentang('LPO')});",
    "menyajikan halaman publik konsumen yang isinya dapat diatur super admin (PUB, SET);",
    f"menyediakan jejak audit data, sesi, dan notifikasi ({rentang('SES')}).",
]:
    bullet(t)

head("C.2 Kesesuaian sistem dengan alur kerja penjualan perumahan", 2)
para("Sistem telah mendukung alur kerja berikut secara berantai dan terbukti pada satu rangkaian data uji yang sama:", 9)
for i, t in enumerate([
    "Sales menambahkan calon konsumen beserta sumber dan kebutuhannya (halaman publik tidak lagi menampung form "
    "pengisian konsumen);",
    "Sales mencatat interaksi follow-up sampai status lead naik menjadi cold, warm, lalu hot;",
    "Lead berstatus hot dipesankan menjadi booking dengan memilih skema pelunasan (KPR, cash keras, cash bertahap);",
    "Status unit otomatis berubah menjadi dipesan dan tidak lagi tampil pada katalog publik;",
    "Sales mengunggah bukti pembayaran (uang muka, angsuran, atau pelunasan);",
    "Super admin memverifikasi atau menolak bukti; sistem menghitung ulang sisa dan status pelunasan;",
    "Admin Sales dan super admin memantau kinerja seluruh sales melalui dashboard dan laporan;",
    "Data yang berhenti diproses dikeluarkan dari daftar melalui penghapusan lunak sehingga riwayat transaksi utuh.",
], 1):
    huruf(f"Langkah {i}. {t}", style="List Number")

head("C.3 Standar kualitas sistem", 2)
KUALITAS = [
    ("Kemudahan penggunaan", "Seluruh halaman memakai satu kerangka, tabel, dialog, dan formulir komponen LP; "
     "label berbahasa Indonesia dan aksi utama tampil di kanan atas halaman.", "NF-09, FF-A01, FF-A04", "Sesuai"),
    ("Struktur navigasi jelas", "Sidebar berisi modul kerja saja; Profil, Sesi Login, Pengaturan Sistem, dan "
     "Pengaturan Halaman Publik berada pada menu ikon profil; notifikasi pada ikon lonceng.", "FF-A01, FF-A02, FF-A03, FF-B01, FF-B02, FF-C01, FF-C02", "Sesuai"),
    ("Validasi masukan", f"{validasi_n} test case validasi menghasilkan 422 disertai pesan; nilai turunan finansial "
     "selalu dihitung ulang server dan ditolak bila dikirim klien.", "NF-10, TC-D05, TC-B04, TC-M03", "Sesuai"),
    ("Pembatasan akses sesuai peran", "Middleware peran pada API dan penapisan peran pada antarmuka (pengamanan "
     "berlapis dua).", "NF-11, TC-A10…TC-A14, TC-M11, FF-B03", "Sesuai"),
    ("Penanganan galat", f"{n500} respons 5xx dari {permintaan} permintaan; {pesan4xx} dari {total4xx} respons 4xx "
     "membawa pesan dan seluruhnya berbahasa Indonesia, termasuk pesan bawaan kerangka yang sebelumnya masih "
     "Inggris (Unauthenticated., No query results for model, The given data was invalid.).",
     "NF-07, NF-08, TC-Q04, TC-Q05", "Sesuai"),
    ("Keamanan kata sandi, sesi, dan laju login", "Bcrypt ($2y$) untuk seluruh akun; masa berlaku token mengikuti "
     "pengaturan session_timeout_minutes yang pada antarmuka berarti batas waktu tanpa aktivitas; tiga percobaan "
     "masuk gagal pada satu email mengunci akun selama login_lockout_minutes dan direset saat berhasil; sisa waktu "
     "sesi tampil pada header beserta tombol perpanjang.",
     "NF-02, NF-03, TC-K03, TC-Q01…TC-Q03, TC-P01…TC-P03, TC-R24, TC-R26, FF-A21, FF-R33, FF-R34", "Sesuai"),
    ("Pemisahan data antar sales", "Daftar dan rincian booking, pembayaran, dan follow-up dipangkas memakai "
     "relasi booking.sales_id sehingga sales tidak membaca pekerjaan sales lain, baik lewat daftar maupun id "
     "langsung.", "TC-N01…TC-N06, FF-C04", "Sesuai"),
    ("Keutuhan data", "Penghapusan lunak mempertahankan baris beserta riwayatnya; unit, lead, dan sales "
     "berriwayat transaksi ditolak saat dihapus; proyek kosong dilepas dari daftar dan proyek berisi unit ditolak "
     "409 dengan arahan memakai Arsipkan.", "NF-04, TC-L05, TC-L06, TC-B10, TC-M08, TC-M09", "Sesuai"),
    ("Jejak audit", f"activity_logs sesuai ERD terisi {audit['activity_logs']} baris melalui AuditObserver "
     f"(aksi created_/updated_/deleted_ untuk enam modul), login_sessions {audit['login_sessions']} baris "
     f"(IP, perangkat, waktu masuk/keluar), dan system_logs {audit['system_logs']} baris; keduanya tampil pada dua "
     "tab halaman Log Aktivitas.", "NF-05, TC-O01…TC-O03, FF-A23", "Sesuai"),
    ("Kelengkapan keluaran laporan", "Empat jenis laporan dapat diunduh sebagai CSV dan dicetak sebagai lembar A4 "
     "berkop perumahan dengan blok pengesahan dua pihak; lembar cetak hanya tampil pada media print.",
     "TC-G05…TC-G11, TC-K14…TC-K16, FF-A13, FF-A22", "Sesuai"),
    ("Waktu respons", f"Rata-rata {rata_rata} ms dengan proses terlambat {lambat} ms, seluruhnya di bawah batas "
     "5 detik.", "NF-01", "Sesuai"),
    ("Kompatibilitas peramban", f"Seluruh {skenario_ui} skenario diuji pada Chromium; Firefox, Edge, dan uji visual "
     "multi-ukuran belum dijalankan manual.", "NF-12", "Sebagian"),
]
table(["Aspek kualitas", "Kondisi hasil pengujian", "Bukti uji", "Status"],
      [Cm(5.4), Cm(14.0), Cm(5.6), Cm(2.3)], KUALITAS, sizes=[8, 8, 7, 8], status_col=3)

head("C.4 Fitur yang telah berfungsi dengan baik", 2)
for t in [
    "Autentikasi tiga peran dengan token Sanctum, batas waktu sesi dari pengaturan, logout, ganti sandi wajib, dan "
    "reset sandi dua langkah;",
    "Manajemen proyek satu siklus penuh (daftar terpisah, tambah, ubah, arsipkan/aktifkan, lepas dari daftar) "
    "beserta hubungannya ke formulir unit;",
    "Lembar cetak laporan A4 berkops perumahan dengan blok pengesahan dua pihak (Simpan sebagai PDF);",
    "Penghitung waktu sesi pada header yang berbasis aktivitas — peringatan lima menit, perpanjangan senyap selama "
    "layar dipakai, dan keluar otomatis bila ditinggalkan; tiga percobaan login gagal per email mengunci akun "
    "selama durasi yang diatur super admin;",
    "Jejak audit data pada tabel activity_logs sesuai ERD yang tampil berdampingan dengan log sistem;",
    "Manajemen unit perumahan, profil unit lengkap, dan halaman detail unit;",
    "Manajemen calon konsumen, riwayat follow-up, dan pemisahan data antar sales;",
    "Pemesanan unit dengan tiga skema pelunasan dan perhitungan finansial otomatis;",
    "Pencatatan pembayaran, unggah bukti transfer, verifikasi/penolakan, dan status pelunasan otomatis;",
    "Manajemen akun sales, target periode, reset sandi, dan laporan kinerja;",
    "Dashboard tiga aktor serta laporan penjualan, pelunasan, kinerja, dan konsumen beserta ekspor;",
    "Halaman publik konsumen (beranda, katalog, detail unit, skema, narahubung, kontak, kabar terbaru) yang "
    "kontennya dikendalikan super admin;",
    "Penghapusan lunak pada data transaksi dan akun, jejak sesi, log aktivitas, dan notifikasi;",
    "Pengaturan sistem dan pengaturan halaman publik dengan validasi nilainya.",
]:
    bullet(t)

head("C.5 Kendala selama pengembangan dan pengujian", 2)
table(["Kendala", "Dampak", "Penanganan", "Status"],
      [Cm(6.6), Cm(7.6), Cm(11.3), Cm(1.8)],
      [
          ["Peran manajer disatukan dengan super admin",
           "Nilai enum role, middleware, label, dan seeder harus diubah serentak; satu dashboard kehilangan rutenya.",
           "Migrasi rename manager menjadi super_admin, pembaruan seluruh role:super_admin, dan penghapusan teks "
           "manajer pada antarmuka; akun lama admin@ dan manager@ ditolak login (TC-A04, TC-A04c).", "Selesai"],
          ["Indeks unik proyek (tabel clusters) bersifat komposit (project_id, name)",
           "Validasi unique sederhana menghasilkan error 500 ketika nama sama dipakai proyek berbeda.",
           "Aturan validasi dipersempit dengan where('project_id', …) sehingga menjadi 422 (TC-B13, TC-M03).", "Selesai"],
          ["Tabel activity_logs pada ERD tidak pernah ditulis aplikasi",
           "Kolom pada tabel tetap kosong sehingga jejak audit per data tidak dapat ditelusuri auditor.",
           "Ditutup: App\\Observers\\AuditObserver dipasang pada model dalam AppServiceProvider::AUDITABLE "
           "sehingga setiap create/update/delete tercatat beserta pelaku dan IP; halaman Log Aktivitas kini punya "
           "tab khusus jejak audit (TC-O01…TC-O03, FF-A23, NF-05).", "Selesai"],
          ["Data kerja pengguna berisiko tertimpa pengujian",
           "Suite otomatis membuat, mengubah, dan menghapus data.",
           "Seluruh pengujian dijalankan pada basis data terpisah luminpark_test (port 8081); basis data luminpark "
           "hanya dipakai observasi antarmuka.", "Selesai"],
          ["Ekspor laporan hanya berbentuk CSV tanpa lembar pengesahan",
           "Butir f.4 pada laporan menyebut mencetak laporan beserta tanda tangan pimpinan; CSV tidak dapat "
           "dipakai sebagai dokumen arsip.",
           "Ditutup: LP.cetakLaporan menyusun lembar A4 (kop dari pengaturan estate_*, judul, periode, waktu "
           "cetak, kolom 'Dibuat oleh' dan 'Mengetahui, Pimpinan / Atasan langsung') lalu membuka dialog cetak "
           "peramban untuk disimpan sebagai PDF (FF-A22). CSV tetap tersedia untuk pengolahan angka.", "Selesai"],
          ["Indeks pembayaran, booking, dan follow-up belum dipisah per sales",
           "Butir d.4: sales dapat membuka pekerjaan sales lain bila id-nya diketahui.",
           "Ditutup: trait ScopesSalesData mempersempit daftar dan menolak show() di luar wilayah sales dengan 403 "
           "(TC-N01…TC-N06); super admin dan Admin Sales tidak terpengaruh (TC-N06).", "Selesai"],
          ["Pengelolaan proyek berhenti pada aksi tambah",
           "Proyek usang tidak dapat diubah, ditutup, maupun dihapus sehingga menumpuk pada formulir unit.",
           "Ditutup: PATCH arsipkan, PUT ubah, penghapusan lunak dengan penjagaan 409, serta halaman Data Proyek "
           "khusus super admin dan daftar tanpa kolom Aksi bagi Admin Sales (TC-M01…TC-M11, FF-A20, FF-B09, FF-B11, "
           "FF-C07).",
           "Selesai"],
          ["Verifikasi revisi menemukan lima galat yang lolos dari suite API",
           "Guard halaman Log Aktivitas masih menuntut super admin sehingga menu Monitoring Manajemen sendiri "
           "ditolak; jejak audit masih menulis created_cluster/deleted_cluster; halaman Tentang Kami masih memuat "
           "narasi skema KPR/cash dari kolom about_text dan memakai judul berbahasa Inggris; penapis laporan "
           "konsumen masih memakai nilai booking sehingga selalu kosong setelah pemisahan kategori; relasi "
           "statistik dashboard belum menyaring proyek.",
           "Ditutup pada sesi revisi: guard menjadi ['admin_sales'] (resources/views/app/log-aktivitas.blade.php), "
           "AuditObserver melabeli ulang Cluster menjadi 'proyek' dan Project menjadi 'perumahan', about_text "
           "bawaan seeder dan dua baris basis data diperbarui tanpa skema, penapis laporan ditambah jalur "
           "category=dibatalkan pada ReportController::consumerReport, dan FiltersProjects dipakai dashboard "
           "(FF-R04, FF-R09, FF-R13, TC-R02, TC-R05).", "Selesai"],
          ["Sesi berakhir tanpa peringatan dan pesan bawaan kerangka masih berbahasa Inggris",
           "Pekerjaan pengguna hilang di tengah sesi dan pesan 'Unauthenticated.' serta "
           "'No query results for model' terbaca teknis.",
           "Ditutup: chip penghitung waktu pada header membaca batas waktu tanpa aktivitas, memperingatkan lima "
           "menit sebelum habis, memperpanjang sendiri selama layar masih dipakai, dan keluar otomatis bila "
           "ditinggalkan (FF-A21, FF-R34, TC-P01…TC-P03, TC-R26); APP_LOCALE=id plus renderable pada "
           "app/Exceptions/Handler.php melokalisasi 401/404/422 (TC-Q04, TC-Q05, NF-08).", "Selesai"],

          ["Kredensial login Google belum diisi",
           "Tombol Masuk dengan Google pada halaman login dinonaktifkan.",
           "Alur dan halamannya sudah tersedia dan membaca nilai pada tabel settings; sejak revisi 33 kolom "
           "Client ID/Secret tidak lagi ditampilkan di halaman Pengaturan, sehingga pengisian kredensial OAuth "
           "dilakukan pada tabel settings oleh pemilik proyek.", "Terbuka"],
          ["APP_DEBUG masih true pada lingkungan lokal",
           "Respons 500 menampilkan jejak stack, nama tabel, dan path berkas.",
           "Dipertahankan hanya untuk pengembangan; wajib false pada server produksi. Lapisan pengaman sudah "
           "dipasang lebih dulu (RateLimiter login, pesan 401/404/422 berbahasa Indonesia, dan badan galat 404 "
           "yang tidak lagi membocorkan nama model).", "Terbuka"],
          ["Skema basis data kerja belum dimigrasi",
           "Tabel clusters pada basis data luminpark belum memiliki kolom status dan deleted_at sehingga halaman "
           "Booking dan Data Cluster (sekarang Data Proyek) pada http://127.0.0.1:8000 memunculkan galat SQLSTATE[42S22] 1054 "
           "Unknown column 'clusters.deleted_at' dengan pesan \"Data gagal dimuat.\"",
           "Ditutup 16 November 2026: migration 2026_11_14_090000_add_status_and_soft_deletes_to_clusters.php "
           "dipasang pada basis data kerja memakai php artisan migrate --force (non-destruktif, 1,859 ms; tanpa "
           "migrate:fresh sehingga cluster dan akun buatan pengguna tetap utuh). GET /bookings, /clusters, /units, "
           "/customer-leads, /payments, dan /dashboard/* kembali 200, dan halaman /booking (Admin Sales) "
           "merender 2 baris booking tanpa galat.", "Selesai"],
          ["Halaman publik menampung form pengisian calon konsumen",
           "Pengunjung dapat menulis data tanpa login sehingga lead masuk otomatis berstatus cold dan berisiko "
           "ganda.",
           "Atas permintaan pengguna 16 November 2026 formulir tersebut dihapus berikut rute POST /api/public/contact, "
           "PublicInfoController::submitContactForm(), dan pembatasan laju public-form. Halaman /kontak dan tombol "
           "Hubungi Sales pada detail unit kini mengarah ke WhatsApp narahubung; calon konsumen dicatat sales dari "
           "/data-konsumen. Rute lama dijaga tetap 404 (TC-H06) dan diamati pada peramban (FF-D08).", "Selesai"],
      ], sizes=[8, 8, 8, 8], status_col=3)
doc.add_page_break()

head("C.6 Tindak lanjut batasan pengujian", 2)
para("Tabel berikut menutup Bab IV dengan memetakan setiap batasan yang dilaporkan pada pelaksanaan pengujian "
     "semula ke perbaikan yang sudah dikerjakan, bukti uji yang menyertainya, dan sisa pekerjaan. Batasan yang "
     "bersifat kegiatan manusia (UAT) atau bergantung kredensial pihak ketiga (Google) sengaja tidak dinyatakan "
     "selesai.", 9)
table(["Kode", "Batasan semula", "Perbaikan yang dilakukan", "Bukti uji", "Status"],
      [Cm(1.4), Cm(6.4), Cm(11.3), Cm(5.6), Cm(2.6)],
      [
          ["D-1", "APP_DEBUG true, tidak ada pembatasan laju login, sebagian pesan bawaan kerangka masih bahasa "
           "Inggris",
           "RateLimiter tiga percobaan gagal per email (kunci email+IP) dengan masa penguncian selama "
           "login_lockout_minutes hasil pengaturan, disetel ulang saat berhasil; "
           "APP_LOCALE=id, app/Lang/id/validation.php, dan renderable 401/403/404/422/429 pada Handler. Nilai "
           "APP_DEBUG diserahkan pada pemasangan produksi.",
           "TC-Q01…TC-Q03, TC-Q04, TC-Q05, TC-R24, NF-08", "Sebagian"],
          ["D-2", "UAT belum melibatkan pengguna sebenarnya",
           "Lembar UAT (bagian B) disiapkan beserta kolom nama penguji, tanggal, dan paraf; pelaksanaannya "
           "memerlukan aktivitas manusia sehingga tidak dapat dinyatakan lulus oleh tim pengembang.",
           "UAT 01…UAT " + f"{len(UAT):02d}", "Terbuka"],
          ["D-3", "Laporan hanya dapat diekspor CSV tanpa blok tanda tangan",
           "Tombol Ekspor PDF / Cetak pada halaman Laporan dan Laporan Sales menghasilkan lembar A4 berkop "
           "perumahan, periode, identitas pencetak, dan kolom pengesahan dua pihak.",
           "FF-A22, FF-A13", "Selesai"],
          ["D-4", "Daftar pembayaran (juga booking dan follow-up) belum dipisah per sales",
           "Trait ScopesSalesData menyaring index() dan menolak show() lintas sales pada tiga modul.",
           "TC-N01…TC-N06, FF-C04", "Selesai"],
          ["D-5", "activity_logs pada ERD tidak pernah ditulis",
           "AuditObserver mencatat create/update/delete enam model beserta pelaku dan IP; Log Aktivitas menampilkan "
           "tab Jejak audit data.",
           "TC-O01…TC-O03, FF-A23, NF-05", "Selesai"],
          ["D-6", "Pengelolaan proyek (dulunya cluster) baru sampai tahap tambah",
           "Ubah, arsipkan/aktifkan, pelepasan lunak dengan penjagaan 409, halaman Data Proyek terpisah, serta kolom "
           "Aksi yang tidak lagi dirender bagi pembaca (Admin Sales) dan tombol Kelola Proyek yang disembunyikan "
           "pada halaman Data Unit.",
           "TC-M01…TC-M11, FF-A20, FF-B09, FF-B11, FF-C07", "Selesai"],
          ["D-7", "Tidak ada peringatan sebelum sesi berakhir",
           "Chip hitung mundur berbasis aktivitas pada header: peringatan kuning lima menit sebelum habis, tombol "
           "Perpanjang, perpanjangan senyap selama layar dipakai, dan keluar otomatis bila tidak ada aktivitas "
           "(revisi 35).",
           "TC-P01…TC-P03, TC-R26, FF-A21, FF-R34", "Selesai"],
          ["D-8", "Login Google menunggu kredensial OAuth",
           "Belum berubah: alur, halaman, dan pengamanannya tersedia sejak pengujian sebelumnya; kolom kredensial "
           "masih kosong sehingga tombol dinonaktifkan. Sejak revisi 33 isian kredensial tidak lagi ditampilkan pada "
           "halaman Pengaturan, sedangkan isi tabel settings dan kontrak API tidak berubah.",
           "TC-A17…TC-A21 (suite konformansi), TC-R25, FF-D03, FF-R32", "Terbuka"],
          ["D-9", "Analitik tingkat manajerial belum ada",
           "Belum berubah; direncanakan pada bagian D karena bukan permintaan wajib laporan.", "-", "Terbuka"],
          ["D-10", "Uji lintas peramban dan lebar layar belum manual",
           "Belum berubah; kerangka responsif Tailwind dan komponen LP sudah seragam, bukti formal masih Chromium "
           "saja.", "NF-12", "Terbuka"],
      ], sizes=[8, 8, 8, 7, 8], center_cols=(0, 4), bold_cols=(0,), status_col=4)
doc.add_page_break()

# ============================== D. RENCANA PENGEMBANGAN LANJUTAN ==============================
head("D. Rencana Pengembangan Lanjutan", 1)
para("Sistem pada tahap ini telah memenuhi seluruh test case fungsional maupun non-fungsional yang dijalankan. "
     "Beberapa pengembangan berikut direncanakan agar sistem siap dipakai pada lingkungan operasional yang "
     "sesungguhnya.", 9)
RENCANA = [
    ("Tinggi", "Pemasangan pada lingkungan produksi",
     "Menetapkan APP_DEBUG=false, mengaktifkan konfigurasi dan rute yang di-cache, menjalankan php artisan migrate "
     "pada basis data kerja luminpark (kolom status dan deleted_at pada clusters belum ada di sana), memasang "
     "HTTPS, serta menjadwalkan pencadangan basis data."),
    ("Tinggi", "UAT bersama pengguna sebenarnya",
     "Pelaksanaan UAT oleh bagian pemasaran perumahan dengan daftar hadir, skenario terukur, dan berita acara "
     "sehingga kolom nama penguji dan tanggal terisi dari pengguna, bukan tim pengembang."),
    ("Sedang", "Tanda tangan digital pada lembar cetak",
     "Mengganti kolom garis dengan tanda tangan tersimpan (gambar) atau kode verifikasi berkas agar dokumen hasil "
     "cetak dapat dibuktikan keasliannya tanpa penempelan manual."),
    ("Sedang", "Hak unduh dan penomoran dokumen laporan",
     "Memberi nomor berkas, watermark draf, dan pembatasan siapa boleh mencetak versi final pada arsip laporan."),
    ("Rendah", "Integrasi Google Sign-In",
     "Mengisi kredensial OAuth langsung pada tabel settings (halaman Pengaturan tidak lagi menampilkannya sejak "
     "revisi 33) lalu menguji alur taut akun dan login satu klik."),
    ("Rendah", "Analitik tingkat manajerial",
     "Grafik tren penjualan per proyek, rasio konversi lead, dan usia stok unit untuk membantu keputusan pimpinan."),
    ("Rendah", "Uji lintas peramban dan perangkat",
     "Menjalankan kembali uji antarmuka pada Firefox dan Edge serta pada lebar layar 360, 768, dan 1.366 piksel "
     "sebagai penutup butir kompatibilitas."),
]
table(["Prioritas", "Butir pengembangan", "Uraian rencana"],
      [Cm(2.2), Cm(6.6), Cm(18.5)], RENCANA, sizes=[8, 8, 8], bold_cols=(0, 1), center_cols=(0,))

doc.add_page_break()

# ============================== LAMPIRAN ==============================
head("LAMPIRAN - Jejak penelusuran dan cara menjalankan pengujian", 1)
head("L.1 Jejak penelusuran kode uji", 2)
para("Setiap baris tabel pada bagian A.4 memakai kode fitur (mis. CLR 03) sekaligus menyimpan kode test case pada "
     "skrip otomatis di kolom Catatan. Baris KF pada A.2 dan KNF pada A.3 tidak menambah test case baru; keduanya "
     "hanya merangkum test case yang sama menurut butir kebutuhan BAB III, sehingga satu butir dapat merujuk beberapa "
     "test case dan satu test case dapat membuktikan beberapa butir. Tabel pertama memetakan tiap butir kebutuhan "
     "fungsional ke baris A.2; tabel kedua merangkum test case per fitur aplikasi.", 9)
table(["Kode baris A.2", "Kode butir laporan", "Modul", "Test case perujuk", "Banyaknya"],
      [Cm(2.6), Cm(3.0), Cm(6.2), Cm(13.0), Cm(2.5)],
      [[f"KF {i:02d}", r["kode"], modul_dari(r["kode"]), ", ".join(r["rujukan"]), str(len(r["rujukan"]))]
       for i, r in enumerate(kf, 1)],
      sizes=[8, 8, 8, 7, 8], center_cols=(0, 4), bold_cols=(0,))
doc.add_paragraph()
table(["Fitur", "Kode laporan", "Test case skrip (API)", "Test case skrip (antarmuka)", "Non-fungsional"],
      [Cm(6.0), Cm(3.4), Cm(7.6), Cm(7.6), Cm(2.7)],
      [[judul, f"{pref} 01…{pref} {len(daftar):02d}",
        ", ".join(s for s in daftar if s.startswith("TC")) or "-",
        ", ".join(s for s in daftar if s.startswith("FF")) or "-",
        ", ".join(s for s in daftar if s.startswith("NF")) or "-"]
       for pref, judul, _, daftar in FITUR],
      sizes=[8, 8, 7, 7, 8])

head("L.2 Akun uji (seluruhnya memakai password123)", 2)
table(["Email", "Peran", "Fungsi dalam pengujian"], [Cm(6.0), Cm(4.0), Cm(17.3)],
      [
          ["superadmin@gmail.com", "super_admin",
           "proyek, unit, verifikasi pembayaran, laporan, log aktivitas, sesi login, pengaturan sistem dan publik"],
          ["admin.sales@luminpark.com", "admin_sales", "uji hak hanya-lihat dan kelola data sales"],
          ["sales@luminpark.com", "sales", "lead, follow-up, booking, pembayaran, dashboard dan laporan sales"],
          ["sales2@luminpark.com", "sales", "uji pemisahan data antar sales"],
          ["sales3@luminpark.com", "sales", "uji reset sandi dan penonaktifan akun"],
      ], sizes=[9, 9, 9])

head("L.3 Perintah pelaksanaan", 2)
table(["Tahap", "Perintah"], [Cm(5.4), Cm(21.9)],
      [
          ["Siapkan basis data uji", "C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root -e "
                                     "\"create database if not exists luminpark_test\""],
          ["Migrasi dan seeder", "DB_DATABASE=luminpark_test C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe "
                                 "artisan migrate:fresh --seed --force"],
          ["Server uji (8081)", "DB_DATABASE=luminpark_test C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe "
                                "artisan serve --host=127.0.0.1 --port=8081"],
          ["Server antarmuka (8080)", "C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe artisan serve "
                                      "--host=127.0.0.1 --port=8080"],
          ["Uji API", "LP_API_BASE=http://127.0.0.1:8081/api "
                      "C:/laragon/bin/nodejs/node-v22/node.exe tests/run-conformance-tests.mjs"],
          ["Uji penguatan (penutup batasan)", "LP_API_BASE=http://127.0.0.1:8081/api "
                                              "C:/laragon/bin/nodejs/node-v22/node.exe "
                                              "tests/run-hardening-tests.mjs"],
          ["Uji non-fungsional", "LP_API_BASE=http://127.0.0.1:8081/api "
                                 "C:/laragon/bin/nodejs/node-v22/node.exe tests/run-nf-tests.mjs"],
          ["Uji antarmuka", "Observasi DOM tiap halaman per peran pada peramban otomatis; hasil direkam pada "
                            "tests/frontend-results.json"],
          ["Susun dokumen ini", "C:/laragon/bin/python/python-3.13/python.exe -Xutf8 tests/build_bab4_pengujian.py"],
          ["Susun dokumen BAB III + BAB IV", "C:/laragon/bin/python/python-3.13/python.exe -Xutf8 "
                                             "tests/build_bab3_bab4_pengujian.py"],
      ], sizes=[9, 9])

head("L.4 Berkas hasil pengujian", 2)
table(["Berkas", "Isi"], [Cm(9.0), Cm(18.3)],
      [
          ["tests/conformance-results.json", f"{len(api)} baris uji API (skema sepuluh kolom)"],
          ["tests/hardening-results.json", f"{len(hard)} baris uji penguatan: TC-M proyek, TC-N pemisahan sales, "
                                           "TC-O jejak audit, TC-P sesi, TC-Q laju login dan lokalisasi"],
          ["tests/frontend-results.json", f"{len(fe)} baris uji antarmuka"],
          ["tests/nf-results.json", f"{len(nf)} baris uji non-fungsional hasil pengukuran"],
          ["tests/kf-matrix.json", f"daftar {len(kf)} butir kebutuhan fungsional beserta test case perujuknya "
                                   "(sumber tabel A.2)"],
          ["tests/run-conformance-tests.mjs", "mesin dan definisi test case API"],
          ["tests/run-hardening-tests.mjs", "suite penutup batasan D-1, D-3 s.d. D-7 (dapat dijalankan berulang)"],
          ["tests/patch-frontend-results.mjs", "penyuntik hasil observasi DOM terbaru ke tests/frontend-results.json"],
          ["tests/patch_kf_matrix.py", "penyelaras matriks kebutuhan fungsional dengan bukti suite penguatan"],
          ["tests/run-nf-tests.mjs", "pengukur waktu respons, probe basis data, dan agregat hasil uji"],
          ["tests/build_bab4_pengujian.py", "penyusun dokumen BAB IV ini"],
          ["tests/build_bab3_bab4_pengujian.py", "penyusun dokumen gabungan BAB III dan BAB IV; BAB IV-nya diambil "
                                                 "dari berkas hasil skrip ini"],
          ["tests/uji_skenario_bab3.py", "daftar skenario pengujian awal BAB III per fitur beserta rujukan "
                                         "test case tiap barisnya"],
      ], sizes=[9, 9])

OUT = f"{BASE}/Laporan-BAB-IV-Pengujian-dan-Evaluasi-Sistem-LuminPark.docx"
try:
    doc.save(OUT)
except PermissionError:
    # Berkas sedang dibuka Word; simpan versi baru agar hasil uji tidak hilang.
    OUT = OUT.replace(".docx", f"-{hari.strftime('%Y-%m-%d')}.docx")
    doc.save(OUT)
    print("PERINGATAN: berkas laporan lama terkunci Word, hasilnya disimpan sebagai berkas baru.")
total = len(api) + len(hard) + len(fe) + len(nf)
lulus = sum(1 for r in api + hard + fe + nf if r["status"] == "Pass")
print(f"Tersimpan: {OUT}")
print(f"Fitur {len(FITUR)} | baris uji {total} (API {len(api)}, penguatan {len(hard)}, UI {len(fe)}, "
      f"NF {len(nf)}) | lulus {lulus} "
      f"| gagal {total - lulus}")
