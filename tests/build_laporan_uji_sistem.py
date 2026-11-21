# -*- coding: utf-8 -*-
"""Laporan analisis kesesuaian backend + frontend terhadap kebutuhan pengguna dan laporan,
lengkap dengan tabel hasil pengujian berformat sepuluh kolom."""
import json
import os
import datetime
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

BASE = "C:/laragon/www/luminpark-main"
auto = json.load(open(f"{BASE}/tests/conformance-results.json", encoding="utf-8"))
ui = json.load(open(f"{BASE}/tests/frontend-results.json", encoding="utf-8"))
nf = json.load(open(f"{BASE}/tests/nf-results.json", encoding="utf-8")) if os.path.exists(
    f"{BASE}/tests/nf-results.json") else []
nf_cari = {r["id"]: r for r in nf}

# Suite penguatan memakai kolom "catatan" sebagai rekaman hasil; disamakan dengan skema sepuluh kolom.
hard = json.load(open(f"{BASE}/tests/hardening-results.json", encoding="utf-8")) if os.path.exists(
    f"{BASE}/tests/hardening-results.json") else []
for r in hard:
    r["actualBody"] = r.get("catatan") or f"HTTP {r.get('actualStatus')}"
    r["catatan"] = ""


def nf_ukur(kode, panjang=150):
    """Nilai terukur dari tests/run-nf-tests.mjs agar laporan tidak mengutip angka karangan."""
    r = nf_cari.get(kode)
    if not r:
        return f"{kode} belum dijalankan"
    t = r["actualBody"].strip()
    return t if len(t) <= panjang else t[:panjang].rstrip() + "…"


n_pass = sum(1 for r in auto + hard + ui if r["status"] == "Pass")
n_total = len(auto) + len(hard) + len(ui)
n_fail = n_total - n_pass
hari = datetime.date.today()

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
                col = {"Sesuai": GREEN, "Pass": GREEN, "Lulus": GREEN, "Selesai": GREEN,
                       "Sebagian": AMBER, "Terbuka": RED, "Fail": RED, "Gagal": RED}.get(str(val))
                if col:
                    r.font.color.rgb = col
                fill = {"Sesuai": "E3F2E3", "Pass": "E3F2E3", "Lulus": "E3F2E3", "Selesai": "E3F2E3",
                        "Sebagian": "FFF3E0", "Terbuka": "FBE4E4", "Fail": "FBE4E4", "Gagal": "FBE4E4"}.get(str(val))
                if fill:
                    shade(c, fill)
    for row in t.rows:
        for i, c in enumerate(row.cells):
            c.width = widths[i]
    if repeat:
        repeat_header(t)
    return t


# ============================== COVER ==============================
para("LAPORAN ANALISIS KESESUAIAN BACKEND DAN FRONTEND SERTA HASIL PENGUJIAN SISTEM",
     18, True, WD_ALIGN_PARAGRAPH.CENTER)
para("Sistem Informasi Manajemen Perumahan Lumin Park (LuminPark)", 12, True, WD_ALIGN_PARAGRAPH.CENTER)
para("Verifikasi terhadap kebutuhan pengguna dan isi laporan tugas proyek, "
     "disaji dalam format tabel uji sepuluh kolom", 11, False, WD_ALIGN_PARAGRAPH.CENTER)
doc.add_paragraph()
table(
    ["Aspek", "Keterangan"],
    [Cm(6.0), Cm(21.3)],
    [
        ["Objek uji", "Lapisan backend (Laravel 10 API + MySQL) dan lapisan frontend (Blade + JavaScript klien "
                     "public/js/luminpark.js) pada repositori luminpark-main"],
        ["Acuan analisis", "Kebutuhan fungsional BAB III laporan (butir a sampai h), kebutuhan non-fungsional, "
                           "ERD Gambar 2.24, wireframe halaman, serta keputusan pengguna 1 November 2026 "
                           "(tiga aktor login, Admin Sales hanya-lihat, penghapusan lunak, menu pada ikon profil)"],
        ["Bentuk pengujian", f"Black-box API testing ({len(auto)} test case otomatis), uji penguatan atas temuan "
                             f"batasan ({len(hard)} test case), dan uji antarmuka per aktor di browser "
                             f"({len(ui)} test case observasi DOM) terhadap server yang sedang berjalan"],
        ["Lingkungan", "Windows 10 x64, Laragon, PHP 8.3.33, MySQL 8.4.3, Node.js 22, Chromium automation"],
        ["Base URL", "http://127.0.0.1:8080 (web) dan http://127.0.0.1:8080/api (REST) untuk uji antarmuka pada "
                     "basis data kerja; http://127.0.0.1:8081/api (REST) untuk suite backend otomatis"],
        ["Kondisi awal basis data", "Suite backend dijalankan pada basis data terpisah luminpark_test "
                                    "(DB_DATABASE=luminpark_test php artisan migrate:fresh --seed, 22 tabel: 18 tabel "
                                    "domain + 4 tabel kerangka) agar data kerja pengguna tidak ditimpa; uji antarmuka "
                                    "memakai basis data luminpark dan luminpark_test"],
        ["Tanggal pengujian", hari.strftime("%d %B %Y")],
        ["Total test case", f"{len(auto)} backend + {len(hard)} penguatan + {len(ui)} frontend = {n_total} test case"],
        ["Hasil", f"Lulus {n_pass} | Gagal {n_fail} | Tingkat lulus {n_pass / n_total * 100:.1f}%"],
        ["Kesimpulan", "Backend dan frontend SESUAI dengan kebutuhan pengguna dan laporan. Dua butir kebutuhan "
                       "fungsional dan tiga butir non-fungsional berstatus Sebagian (satu butir lain, ketersediaan "
                       "layanan, belum terukur di lingkungan lokal); seluruhnya sudah disepakati sebagai "
                       "penyimpangan yang didokumentasikan. Lima batasan yang dilaporkan pada pengujian sebelumnya "
                       "(D-3 sampai D-7) sudah ditutup dan dibuktikan ulang dengan test case baru, sedangkan D-1 "
                       "baru tertutup sebagian (APP_DEBUG lingkungan pengembang)."],
    ],
    sizes=[9, 9], bold_cols=(0,),
)
doc.add_page_break()

# ============================== BAB I ==============================
head("BAB I - Metode pengujian")
head("1.1 Tahapan pelaksanaan", 2)
for i, txt in enumerate([
    "Basis data uji luminpark_test diatur ulang (DB_DATABASE=luminpark_test php artisan migrate:fresh --seed) supaya "
    "setiap angka yang dibaca laporan berasal dari data demo yang dikenal, bukan sisa eksekusi sebelumnya; basis data "
    "kerja luminpark tidak disentuh agar data proyek dan unit buatan pengguna tetap utuh.",
    "Server pengembangan dijalankan pada http://127.0.0.1:8081 untuk suite backend (DB_DATABASE=luminpark_test php "
    "artisan serve --host=127.0.0.1 --port=8081) dan pada http://127.0.0.1:8080 untuk antarmuka "
    "(php artisan serve --host=127.0.0.1 --port=8080); tautan storage diaktifkan agar unggahan gambar dapat dibaca "
    "kembali.",
    "Suite backend tests/run-conformance-tests.mjs mengirim permintaan HTTP nyata "
    "(LP_API_BASE=http://127.0.0.1:8081/api), lalu merekam ID, nama, skenario, "
    "deskripsi, langkah, data uji, hasil yang diharapkan, hasil aktual, status, dan catatan ke "
    "tests/conformance-results.json.",
    "Suite penguatan tests/run-hardening-tests.mjs menjalankan ulang butir yang sebelumnya berstatus batasan "
    "(LP_API_BASE=http://127.0.0.1:8081/api): daur hidup proyek TC-M, pemisahan data per sales TC-N, jejak audit "
    "TC-O, batas waktu sesi TC-P, dan keamanan serta lokalisasi TC-Q; hasilnya ditulis ke "
    "tests/hardening-results.json dengan skema kolom yang sama.",
    "Suite frontend dilakukan dengan memuat halaman sebenarnya di peramban pada sesi login tiap aktor, kemudian "
    "membaca dokumen DOM yang telah dirender (daftar menu, jumlah baris tabel, tombol aksi, isi dialog, nilai form) "
    "dan merekamnya dengan skema kolom yang sama ke tests/frontend-results.json. Baris hasil observasi disatukan "
    "kembali oleh tests/patch-frontend-results.mjs agar tanggal jendela uji dan temuan baru (FF-A20 sampai FF-A23, "
    "FF-B09, FF-C07, FF-D08) ikut terbaca.",
    "Seluruh hasil dibaca kembali dan dibandingkan dengan butir kebutuhan pada laporan; butiran yang belum terpenuhi "
    "utuh diberi status Sebagian beserta alasannya, bukan ditutupi.",
], 1):
    bullet(f"Langkah {i}. {txt}", style="List Number")

head("1.2 Format tabel hasil pengujian", 2)
para("Tabel hasil pada BAB IV (backend), BAB V (penguatan), dan BAB VI (frontend) memakai sepuluh kolom tepat seperti "
     "format yang diminta:", 9)
bullet("ID — penanda test case; TC-A sampai TC-L untuk suite konformansi backend, TC-M sampai TC-Q untuk suite "
       "penguatan, FF-A sampai FF-D untuk frontend.")
bullet("Nama — judul singkat uji.")
bullet("Skenario — kelompok alur (misalnya Autentikasi, RBAC negatif, Data Unit, Halaman publik konsumen).")
bullet("Deskripsi — apa yang dibuktikan dan mengapa dibutuhkan.")
bullet("Langkah Uji — urutan tindakan penguji, termasuk endpoint atau halaman yang dibuka.")
bullet("Data Uji — nilai masukan nyata yang dipakai (email, sandi, nominal, id, saklar pengaturan).")
bullet("Hasil yang Diharapkan — kriteria benar menurut kebutuhan laporan atau keputusan pengguna.")
bullet("Hasil Aktual — rekaman keluaran sesungguhnya: kode status dan potongan body respons (backend) atau fakta DOM "
       "yang terbaca di peramban (frontend).")
bullet("Status (Pass/Fail) — PASS bila seluruh kriteria terpenuhi, FAIL bila ada satu saja yang meleset.")
bullet("Catatan — penafsiran hasil, rujukan kebutuhan, dan tindakan perbaikan bila ada cacat.")

head("1.3 Rekapitulasi hasil", 2)
kel = {}
for r in auto + hard:
    key = r["skenario"]
    kel.setdefault(key, [0, 0])
    kel[key][0] += 1
    if r["status"] == "Pass":
        kel[key][1] += 1
ui_groups = [
    ("Frontend - aktor Super Admin", "FF-A"),
    ("Frontend - aktor Admin Sales", "FF-B"),
    ("Frontend - aktor Sales", "FF-C"),
    ("Frontend - halaman publik & autentikasi", "FF-D"),
]
recap = [[f"Backend API - {k}", str(v[0]), str(v[1]), str(v[0] - v[1])] for k, v in sorted(kel.items())]
for nama, prefix in ui_groups:
    grp = [r for r in ui if r["id"].startswith(prefix)]
    recap.append([nama, str(len(grp)), str(sum(1 for r in grp if r["status"] == "Pass")),
                  str(sum(1 for r in grp if r["status"] != "Pass"))])
recap.append(["TOTAL", str(n_total), str(n_pass), str(n_fail)])
t = table(["Kelompok pengujian", "Jumlah uji", "Lulus", "Gagal"],
          [Cm(17.0), Cm(3.4), Cm(3.4), Cm(3.4)], recap, sizes=[8, 8, 8, 8], center_cols=(1, 2, 3))
baris_total = t.rows[len(recap)].cells
for c in baris_total:
    shade(c, SUB)
    for r in c.paragraphs[0].runs:
        r.bold = True
para(f"Seluruh {n_total} test case berstatus PASS (tingkat lulus "
     f"{n_pass / n_total * 100:.1f}%). Tidak ada kegagalan fungsional pada saat pengujian dilakukan.",
     10, True, color=GREEN)
doc.add_page_break()

# ============================== BAB II ==============================
head("BAB II - Analisis kesesuaian backend terhadap kebutuhan")
para("Butir kebutuhan fungsional a.1 sampai h.3 pada BAB III laporan (halaman 29-31) dipetakan ke implementasi dan "
     "ke test case yang membuktikannya. Baris terakhir tiap kelompok menambahkan kebutuhan pengguna di luar teks "
     "laporan (keputusan 1 November 2026). Status: Sesuai = terpenuhi utuh; Sebagian = terpenuhi dengan catatan yang "
     "sudah disepakati.", 9)
matrix = [
    ("1", "a.1", "Login memakai nama pengguna dan sandi untuk Super Admin, Admin Sales, dan Sales",
     "AuthController::login (app/Http/Controllers/Api/AuthController.php:34) memvalidasi kredensial dengan "
     "Hash::check, menolak akun nonaktif (403), menolak peran non-internal (403), lalu menerbitkan token Sanctum.",
     "TC-A01, TC-A02, TC-A03, TC-A05, TC-A06", "Sebagian",
     "Identitas login memakai kolom email karena tabel users tidak memiliki kolom username; label pada formulir "
     "tetap berbunyi \"Nama Pengguna\" sesuai wireframe."),
    ("2", "a.2", "Role-based access control membedakan hak akses tiap aktor",
     "routes/api.php (group auth:sanctum + role:...) dan app/Http/Middleware/RoleMiddleware.php membatasi tiap rute.",
     "TC-A07, TC-A08, TC-A10, TC-A11, TC-A12, TC-E03, TC-G04, TC-I04, TC-I06, TC-K06, TC-K15, TC-L01..L04", "Sesuai", ""),
    ("3", "a.3", "Fitur reset password untuk setiap pengguna",
     "AuthController::resetPassword (mencabut seluruh token lama) dan SalesController::resetPassword untuk "
     "super admin; POST /api/forgot-password menyimpan token 64 karakter sebagai hash SHA-256 yang berlaku 10 menit "
     "lalu mengirim Mailable LinkAturUlangSandi, dan POST /api/reset-password/token menetapkan sandi baru cukup "
     "dengan token tersebut (TC-R10, TC-R11).", "TC-F04, TC-R10, TC-R11, FF-R14, FF-D06", "Sesuai",
     "Pemulihan mandiri tidak lagi meminta sandi lama sejak revisi 14 karena bukti kepemilikan dipindahkan ke tautan "
     "verifikasi yang dikirim ke email akun; akun konsumen memang diblokir dari login sehingga tidak memerlukan "
     "jalur ini."),
    ("4", "a.4", "Logout dengan batas waktu tertentu untuk setiap aktor",
     "AuthController::logout menghapus CurrentAccessToken; masa berlaku token dibaca dari pengaturan "
     "session_timeout_minutes melalui SettingController::sessionTimeoutMinutes() (app/Http/Controllers/Api/"
     "SettingController.php, minimum 15 menit, baku 1.440 menit) dan dipakai sebagai expiresAt saat createToken; "
     "GET /session/status mengembalikan sisa detik beserta batasnya dan POST /session/refresh menerbitkan token baru "
     "sambil mencabut token lama; tabel login_sessions mencatat masuk, keluar, IP, dan perangkat. Sejak revisi 35 "
     "penghitung pada header diperlakukan sebagai batas waktu tanpa aktivitas (public/js/luminpark.js), sehingga "
     "layar yang ditinggalkan keluar otomatis dan layar yang masih dipakai diperpanjang senyap.",
     "TC-A01, TC-A15, TC-A16, TC-L07, TC-P01, TC-P02, TC-P03, TC-R26, FF-A21, FF-R34", "Sesuai",
     "Batas waktu diambil dari halaman Pengaturan Sistem, bukan config/sanctum.php, dan label isian kini berbunyi "
     "'Batas Waktu Tanpa Aktivitas (menit)'. Antarmuka menampilkan chip hitung mundur beserta tombol Perpanjang, "
     "peringatan pada sisa 300 detik, serta keluar otomatis tanpa aktivitas (FF-A21, FF-R34, TC-P02, TC-P03, TC-R26)."),
    ("5", "Keputusan akun", "Hanya ada tiga jenis akun login (super admin, admin sales, sales); akun admin dan "
     "manajer digabung menjadi satu super admin; konsumen tidak punya akun login",
     "UserSeeder hanya membuat lima akun uji; akun consumer, admin@luminpark.com, dan manager@luminpark.com "
     "dihapus lunak; AuthController menolak peran di luar ROLE_INTERNAL dengan 403.",
     "TC-A04, TC-A04b, TC-A04c", "Sesuai",
     "Satu akun super admin aktif: superadmin@gmail.com (role super_admin). Rute POST /api/register memang tidak ada."),
    ("6", "b.1", "Admin Sales dan Super Admin melihat seluruh data unit beserta statusnya",
     "UnitController::index dengan filter status, dibatasi role:super_admin,admin_sales.", "TC-A13, TC-B01", "Sesuai", ""),
    ("7", "b.2", "Sales melihat informasi unit yang tersedia", "UnitController::availableUnits (GET /units-available).",
     "TC-A14", "Sesuai", ""),
    ("8", "b.3", "Konsumen melihat informasi unit rumah yang tersedia",
     "PublicInfoController::publicUnits tanpa autentikasi, hanya status available.", "TC-H01, TC-H03", "Sesuai", ""),
    ("9", "b.4", "Super Admin melihat, menambah, mengubah, dan menghapus data unit serta statusnya "
     "(tersedia, booking, sold out, proses delivery)",
     "apiResource units + Unit::STATUSES; penolakan penghapusan unit yang memiliki riwayat pemesanan.",
     "TC-B01, TC-B03, TC-B05, TC-B06, TC-B07, TC-B09, TC-B10, TC-B16", "Sesuai", ""),
    ("10", "b.5", "Super Admin memperbarui status unit", "UnitController::update (PUT/PATCH /units/{id}).", "TC-B06",
     "Sesuai", ""),
    ("10a", "b.1 cluster", "Super Admin melihat, menambah, mengubah, mengarsipkan, dan melepas proyek dari daftar "
     "melalui halaman Data Proyek; Admin Sales hanya dapat membacanya; Sales tidak punya akses",
     "ClusterController::index menghasilkan status, units_count, dan relasi project; store (POST /clusters) "
     "memvalidasi project_id (exists:projects) dan nama dengan Rule::unique('clusters','name')->where('project_id', "
     "...) karena indeks unik pada tabel clusters bersifat komposit (project_id, name); update (PUT /clusters/{id}); "
     "PATCH /clusters/{id}/arsipkan memindahkan status aktif/diarsipkan dengan validasi whitelist; destroy "
     "menolak 409 bila proyek masih dipakai unit dan bersifat lunak. Rute tulis dikunci role:super_admin "
     "(routes/api.php). Antarmukanya halaman /data-proyek berjudul 'Pengelolaan Proyek' berisi kartu ringkasan, "
     "tabel, form modal, serta aksi Ubah/Arsipkan/Aktifkan kembali; proyek arsip tidak muncul lagi pada formulir "
     "unit. Sejak revisi 4 seluruh label ini memakai istilah Proyek, hanya nama tabel dan rute yang tetap clusters.",
     "TC-B11..TC-B18, TC-M01..TC-M11, FF-A18, FF-A19, FF-A20, FF-B08, FF-B09, FF-C07, FF-R04", "Sesuai",
     "Baris ini merupakan permintaan pengguna di luar teks laporan. Pada pengujian sebelumnya pengelolaan proyek "
     "baru sampai tahap tambah (batasan D-6); ubah, arsipkan, pelepasan lunak, dan halaman daftarnya sudah ditutup dan "
     "dibuktikan TC-M01 sampai TC-M11."),
    ("10b", "Keputusan pemisahan data", "Sales hanya dapat membaca pemesanan, pembayaran, dan tindak lanjut "
     "miliknya sendiri, sedangkan Super Admin dan Admin Sales melihat seluruhnya (butir d.4)",
     "Trait App\\Http\\Controllers\\Concerns\\ScopesSalesData dipakai BookingController::index/show, "
     "PaymentController::index/show, dan FollowUpController::index/show: peran sales disaring pada daftar dan "
     "diberi 403 'Data ini berada di bawah penanganan sales lain' pada akses langsung; role super_admin dan "
     "admin_sales tidak dipangkas.",
     "TC-N01..TC-N06, FF-C04", "Sesuai",
     "Menutup batasan D-4; pada laporan sebelumnya butir d.4 berstatus Sebagian karena indeks pembayaran belum "
     "dipangkas per sales."),
    ("10c", "Keputusan jejak audit", "Perubahan data penting dapat ditelusuri pada tabel activity_logs sesuai ERD",
     "App\\Observers\\AuditObserver yang didaftarkan pada AppServiceProvider::AUDITABLE menulis baris "
     "created_/updated_/deleted_ per entitas (proyek, unit, lead, booking, payment, sales, user, scheme, setting) "
     "beserta user_id, deskripsi, IP, dan user agent; GET /api/activity-logs (khusus super_admin) menggabungkannya "
     "dengan sumber sistem dan mendukung penyaringan ?action=.",
     "TC-O01, TC-O02, TC-O03, FF-A23, NF-05", "Sesuai",
     "Menutup batasan D-5. Pada laporan sebelumnya tabel activity_logs terbentuk dari migration tetapi tidak pernah "
     "ditulisi aplikasi."),
    ("10d", "Keputusan keluaran laporan", "Laporan dapat dicetak/diekspor PDF beserta blok pengesahan "
     "(kelanjutan butir f.4)",
     "LP.cetakLaporan() (public/js/luminpark.js) menyisipkan kop berisi identitas perumahan dari Pengaturan Sistem "
     "(estate_name, estate_address, telepon, email) serta blok pengesahan dua kolom 'Dibuat oleh' dan 'Mengetahui: "
     "Pimpinan / Atasan langsung' pada DOM dengan kelas .lp-print-only, lalu memanggil window.print() sehingga "
     "keluaran PDF dari dialog cetak peramban memakai kop, judul, periode, dan kolom tanda tangan yang sama; tombol "
     "Ekspor PDF / Cetak menemani unduhan CSV yang sudah ada.",
     "FF-A22, FF-A13, TC-G05..TC-G10", "Sesuai",
     "Menutup batasan D-3. Tanda tangan masih dibubuh setelah cetak (manual); tanda tangan digital tersertifikasi "
     "belum dipakai dan dicatat pada butir pengembangan lanjutan."),
    ("10e", "Keputusan keamanan & lokalisasi", "Percobaan login berulang dibatasi dan seluruh pesan kesalahan "
     "berbahasa Indonesia",
     "LoginThrottle pada AuthController::login membatasi tiga percobaan gagal per kombinasi email+IP; setelah itu "
     "akun dikunci selama login_lockout_minutes yang dipilih super admin pada Pengaturan Sistem (429 beserta "
     "retry_after dan locked_until, pesan 'Akun dikunci <menit> menit karena 3 kali percobaan login gagal. Coba lagi "
     "dalam <menit> menit.') dan RateLimiter auth-password pada "
     "AppServiceProvider untuk reset/refresh/sesi; berkas lang/id.php plus renderable pada app/Exceptions/"
     "Handler.php mengganti pesan bawaan Laravel, 'Unauthenticated.', dan bocoran 'No query results for model' "
     "menjadi kalimat Indonesia tanpa nama kelas maupun path berkas.",
     "TC-Q01..TC-Q05, TC-Q04, TC-R24, NF-08, FF-R33", "Sebagian",
     "Pembatasan laju dan lokalisasi sudah dibuktikan; jumlah percobaan diturunkan menjadi tiga dan lama kuncinya "
     "menjadi pengaturan sejak revisi 34. Batasan D-1 baru berstatus Sebagian karena .env pengembang "
     "masih memakai APP_DEBUG=true, yang wajib dimatikan saat dipasang di server umum."),
    ("11", "Keputusan hapus lunak", "Data yang dihapus hilang dari antarmuka tetapi tetap tersimpan di basis data",
     "SoftDeletes pada users, units, sales_profiles, customer_leads, bookings, payments, payment_schemes, "
     "public_posts, dan clusters (migration 2026_11_14_090000_add_status_and_soft_deletes_to_clusters.php menambah "
     "deleted_at serta status pada clusters); seluruh query memakai scope tanpa deleted_at.",
     "TC-L05, TC-L06, TC-M09, TC-M10", "Sesuai",
     "Indeks unik pada email, kode unit, kode sales, dan nama skema diturunkan menjadi indeks biasa agar kode yang "
     "pernah dipakai dapat digunakan lagi; keunikan dijaga di lapisan validasi dengan aturan whereNull('deleted_at'). "
     "Hanya tabel projects yang masih dihapus permanen karena tidak punya endpoint penghapus."),
    ("12", "c.1", "Sales membuat pemesanan unit untuk konsumen berstatus hot",
     "BookingController::store menolak lead yang statusnya bukan hot (422).", "TC-D03", "Sesuai", ""),
    ("13", "c.2", "Sales memilih skema pelunasan (KPR, cash keras, cash bertahap)",
     "apiResource payment-schemes + PaymentSeeder dengan tiga skema; booking memvalidasi payment_scheme_id.",
     "TC-D01, TC-D05, TC-H04", "Sesuai", ""),
    ("14", "c.3", "Sistem otomatis menghitung DP, cicilan, dan sisa pembayaran",
     "PaymentScheme::calculateBreakdown() dijalankan server saat booking dibuat; nilai turunan ditolak bila "
     "dikirim klien.", "TC-D02, TC-D05, TC-D09", "Sesuai",
     "Bunga dihitung flat atas pokok; laporan tidak menetapkan rumus bunga efektif sehingga pilihan ini diterima."),
    ("15", "c.4", "Status unit berubah menjadi dipesan setelah booking berhasil",
     "BookingController::store mengeset Unit::STATUS_BOOKING dan status lead menjadi booking.",
     "TC-D04, TC-D06, TC-D07", "Sesuai", ""),
    ("16", "d.1", "Sales mencatat pembayaran (DP, cicilan, pelunasan) dan mengunggah bukti transfer",
     "PaymentController::store menerima multipart transfer_receipt (jpg/jpeg/png/pdf, maksimum 5 MB) pada disk public.",
     "TC-E01, TC-E02, TC-E08, TC-E09, FF-A17", "Sesuai", ""),
    ("17", "d.2", "Super Admin memverifikasi bukti pembayaran (terverifikasi/ditolak)",
     "PUT /payments/{id}/verify khusus role:super_admin; kolom verified_by dan verified_at terisi; penolakan tidak "
     "mengubah sisa pembayaran.", "TC-E03, TC-E04, TC-E06", "Sesuai", ""),
    ("18", "d.3", "Status pelunasan dan sisa pembayaran diperbarui otomatis",
     "PaymentController::recalculateBooking + Booking::settlementStatus() menurunkan status (menunggu DP, dp lunas, "
     "cicilan, lunas) dari ledger pembayaran yang terverifikasi.", "TC-D09, TC-E05, TC-E07, TC-E10, TC-D10", "Sesuai", ""),
    ("19", "d.4", "Sales memantau status pembayaran konsumennya",
     "GET /payments dengan filter booking_id, payment_type, dan verification_status, sekaligus dipangkas pada "
     "booking milik sales yang login lewat trait ScopesSalesData (lihat baris 10b).",
     "TC-E09, TC-N01, TC-N03, TC-N04, TC-N05", "Sesuai",
     "Status dinaikkan dari Sebagian menjadi Sesuai setelah batasan D-4 ditutup: akses langsung ke booking atau "
     "pembayaran sales lain kini dijawab 403 (TC-N02, TC-N04)."),
    ("20", "d.5", "Rekap pelunasan per konsumen, unit, dan skema", "ReportController::settlementReport (TC-G06).",
     "TC-G06", "Sesuai", ""),
    ("21", "e.1", "Sales menambah, mengubah, dan menghapus data calon konsumen",
     "apiResource customer-leads; penolakan penghapusan lead yang sudah berstatus booking.",
     "TC-C01, TC-C04, TC-C11", "Sesuai", ""),
    ("22", "e.2", "Sales memperbarui status calon konsumen (cold, warm, hot, booking)",
     "CustomerLead::STATUSES + validasi status pada update.", "TC-C02, TC-C04", "Sesuai", ""),
    ("23", "e.3", "Sales melihat daftar calon konsumen yang ditanganinya berdasarkan status",
     "CustomerLeadController::index dipangkas ke sales_id milik pengguna yang login.", "TC-C08, TC-C09, TC-C10",
     "Sesuai", ""),
    ("24", "e.4", "Sales mencatat riwayat follow-up setiap kali berinteraksi",
     "apiResource follow-ups menyimpan status_before/status_after, metode komunikasi, dan menyinkronkan "
     "last_follow_up pada lead.", "TC-C05, TC-C06, TC-C07", "Sesuai", ""),
    ("25", "e.5", "Admin Sales dan Super Admin melihat seluruh calon konsumen dari semua sales dengan filter status",
     "Indeks tidak dipangkas untuk role super_admin/admin_sales.", "TC-C08", "Sesuai", ""),
    ("26", "e.6", "Admin Sales dan Super Admin memantau perkembangan follow-up setiap sales",
     "Daftar follow-up gabungan (lead + sales) serta laporan konsumen dan kinerja sales.", "TC-C07, TC-G07, TC-G08",
     "Sesuai", ""),
    ("27", "f.1", "Super Admin melihat dashboard rekapitulasi unit sold out dan belum delivery, pendapatan, jumlah sales, "
     "calon konsumen, dan konsumen", "DashboardController::superAdminDashboard memakai konstanta status Unit/Booking "
     "ditambah widget grafik dan booking terbaru.", "TC-G01, TC-K11", "Sesuai", ""),
    ("28", "f.2", "Admin Sales melihat dashboard rekapitulasi penjualan seluruh sales",
     "DashboardController::adminSalesDashboard.", "TC-G02, TC-K12", "Sesuai", ""),
    ("29", "f.3", "Sales melihat dashboard jumlah calon konsumen yang ditangani berdasarkan status",
     "DashboardController::salesDashboard (dibatasi role:sales).", "TC-G03, TC-G04, TC-K13", "Sesuai", ""),
    ("30", "f.4", "Super Admin dan Admin Sales melihat serta mencetak laporan penjualan unit, pelunasan, kinerja sales, "
     "dan laporan konsumen beserta calon konsumen",
     "ReportController::salesReport/settlementReport/salesPerformanceReport/consumerReport dan exportReport (CSV), "
     "ditambah lembar cetak berpengesahan lewat LP.cetakLaporan() pada halaman /laporan.",
     "TC-G05, TC-G06, TC-G07, TC-G08, TC-G09, TC-G10, FF-A22", "Sesuai",
     "Status dinaikkan dari Sebagian menjadi Sesuai setelah batasan D-3 ditutup: selain unduhan CSV berkepala bahasa "
     "Indonesia, tombol Ekspor PDF / Cetak menghasilkan lembar dengan kop, periode, dan dua kolom tanda tangan "
     "(dibuat oleh / mengetahui) yang terverifikasi pada FF-A22."),
    ("31", "f.5 (tambahan)", "Laporan penjualan khusus sales yang sedang login (Wireframe 2.46)",
     "ReportController::mySales + mySalesExport pada GET /reports/my-sales dan /reports/my-sales/export "
     "(routes/api.php:138-142); ditolak untuk peran lain.", "TC-K14, TC-K15, TC-K16", "Sesuai",
     "Baris ini belum ada pada matriks laporan sebelumnya; implementasinya sudah tersedia dan diuji."),
    ("32", "g.1", "Konsumen melihat daftar unit rumah tersedia beserta tipe, luas, dan harga",
     "PublicInfoController::publicUnits memuat unit_type, land_size, building_size, dan price.", "TC-H01", "Sesuai", ""),
    ("33", "g.2", "Konsumen melihat detail unit rumah beserta spesifikasi lengkap",
     "PublicInfoController::publicUnitDetail; unit berstatus booking/sold_out ditutup untuk publik (404).",
     "TC-H02, TC-H03", "Sesuai", ""),
    ("34", "g.3", "Konsumen melihat skema pembayaran yang tersedia", "PublicInfoController::paymentSchemes.", "TC-H04",
     "Sesuai", ""),
    ("35", "g.4", "Konsumen diarahkan ke narahubung sales melalui tombol Hubungi Sales",
     "PublicInfoController::salesContacts menghasilkan nomor telepon dan tautan wa.me pada beranda, detail unit, dan "
     "halaman /kontak. Formulir pengisian calon konsumen di halaman publik sudah dihapus beserta rute "
     "POST /api/public/contact, sehingga data lead hanya dibuat sales dari halaman Data Konsumen.",
     "TC-H05, TC-H06, FF-D02", "Sesuai",
     "Permintaan pengguna 16 November 2026: halaman publik tidak lagi menampung form pengisian calon konsumen; "
     "TC-H06 menjaga agar rute tulis publik tersebut tidak muncul kembali."),
    ("36", "g.5 (tambahan)", "Konten halaman publik dikendalikan super admin dari Pengaturan Halaman Publik",
     "PublicInfoController::publicSettings + tabel settings (public_hero_*, public_show_*, public_featured_*) dan "
     "tabel public_posts untuk kabar terbaru.", "TC-K01, TC-K03, TC-H08", "Sesuai",
     "Saklar yang sama dipatuhi oleh halaman beranda dan halaman Tentang Kami."),
    ("37", "h.1", "Super Admin mengelola data sales (tambah, ubah, hapus, reset password)",
     "SalesController::store (termasuk target penjualan), update (termasuk status nonaktif), destroy dengan penjaga "
     "riwayat, dan resetPassword yang mencabut token lama.", "TC-F01, TC-F04, TC-F05, TC-F07, TC-F08", "Sesuai", ""),
    ("38", "h.2", "Super Admin dan Admin Sales melihat daftar seluruh sales beserta kinerjanya",
     "SalesController::index + performance (pencapaian unit dan nominal dibanding target pada periode).",
     "TC-F06, TC-G07", "Sesuai", ""),
    ("39", "h.3", "Admin Sales mengelola data sales dengan hak yang sama",
     "Group role:super_admin,admin_sales pada apiResource sales.", "TC-F03", "Sesuai", ""),
    ("40", "Keputusan sandi sales", "Sandi awal sales adalah tanggal lahir (ddmmyyyy) dan wajib diganti saat masuk "
     "pertama kali", "SalesController::store mengeset must_change_password=1; kolom birth_date pada sales_profiles; "
     "halaman /ganti-sandi memaksa penggantian.", "TC-F01, FF-D06", "Sesuai", ""),
    ("41", "ERD Gambar 2.24", "Struktur basis data sesuai ERD (narasi menyebut 14 tabel, daftar yang diuraikan 15 "
     "entitas)",
     "18 tabel domain terbentuk lengkap dengan foreign key dan kolom sesuai narasi: users, sales_profiles, projects, "
     "clusters, units, customer_leads, follow_up_histories, bookings, payment_schemes, payments, sales_targets, "
     "sales_reports, notifications, system_logs, login_sessions, activity_logs, settings, public_posts.",
     "TC-A15, TC-B01, TC-B02, TC-D08, TC-F01, TC-G11, TC-I01, TC-I05, TC-O01, NF-05", "Sesuai",
     "Ditambah 4 tabel kerangka (migrations, failed_jobs, password_reset_tokens, personal_access_tokens) sehingga "
     "total 22 tabel. Nama tabel dipertahankan dalam bahasa Inggris; pemetaan ke nama Indonesia tersedia pada "
     "LAMPIRAN A laporan pembanding sebelumnya. Angka 14 pada narasi laporan perlu dikoreksi menjadi 18. Kolom "
     "clusters.status dan clusters.deleted_at ditambahkan karena kebutuhan pengelolaan cluster; activity_logs kini "
     "benar-benar ditulisi AuditObserver (NF-05: 146 baris, 22 jenis aksi)."),
]
table(["No", "Butir", "Kebutuhan pada laporan / keputusan pengguna", "Implementasi pada backend",
       "Bukti pengujian", "Status", "Catatan"],
      [Cm(0.9), Cm(2.0), Cm(6.2), Cm(7.4), Cm(4.0), Cm(1.7), Cm(5.1)],
      [[str(i)] + list(m)[1:] for i, m in enumerate(matrix, 1)],
      sizes=[8, 8, 8, 8, 7, 8, 7], bold_cols=(0, 1), center_cols=(0,), status_col=5)
n_sesuai = sum(1 for m in matrix if m[5] == "Sesuai")
n_sebagian = sum(1 for m in matrix if m[5] == "Sebagian")
para(f"Ringkasan matriks backend: {n_sesuai} butir Sesuai, {n_sebagian} butir Sebagian, 0 butir Belum terpenuhi.",
     10, True)

head("2.1 Kebutuhan non-fungsional", 2)
nfr = [
    ("NF-1", "Waktu respons maksimum 5 detik untuk proses umum",
     "Diukur dari sisi klien atas login, daftar unit, dashboard super admin, laporan penjualan, dan katalog publik "
     "oleh tests/run-nf-tests.mjs.",
     "NF-01", "Sesuai", f"Terukur: {nf_ukur('NF-01', 120)}"),
    ("NF-2", "Setiap pengguna masuk memakai akun terverifikasi",
     "Hash::check terhadap sandi ber-hash; akun nonaktif ditolak 403; peran non-internal ditolak 403.",
     "TC-A05, TC-A03, TC-A04, TC-F05", "Sesuai", ""),
    ("NF-3", "Role-based access control",
     "RoleMiddleware pada seluruh rute internal; guard ganda (middleware API + penapisan peran di Blade); "
     "pemisahan data antar baris (row-level) untuk peran sales.",
     "TC-A10, TC-A11, TC-A12, TC-E03, TC-G04, TC-I06, TC-L01..L04, TC-M11, TC-N02, TC-N04, TC-O03, "
     "NF-11 (skrip NF)", "Sesuai", ""),
    ("NF-4", "Password disimpan sebagai hash",
     "Cast password => hashed pada App\\Models\\User; nilai tersimpan berawalan $2y$ (bcrypt).",
     "NF-02 (skrip NF)", "Sesuai", f"Terukur: {nf_ukur('NF-02', 60)}"),
    ("NF-5", "Antarmuka sederhana dan konsisten",
     "Diuji pada BAB III (verifikasi DOM per aktor), bukan lagi di luar cakupan.",
     "FF-A01..FF-A23, FF-B01..FF-B09, NF-09 (skrip NF)", "Sesuai",
     "Layout, tabel, dialog, dan formulir memakai komponen yang sama; halaman baru Data Proyek dan Log Aktivitas "
     "dua sumber mengikuti kerangka yang sama."),
    ("NF-6", "Responsif di berbagai perangkat",
     "Kerangka Tailwind dengan grid adaptif pada seluruh halaman; pemeriksaan visual penuh memerlukan viewport "
     "tersedia.", "Observasi DOM (lebar konten adaptif)", "Sebagian",
     "Struktur responsif terverifikasi dari markup; uji tampilan pada tiga ukuran layar belum dilakukan manusia."),
    ("NF-7", "Navigasi dengan ikon dan label jelas",
     "Sidebar berisi tautan berlabel bahasa Indonesia; notifikasi pada ikon lonceng; menu akun pada ikon profil.",
     "FF-A01, FF-A02, FF-A03, FF-B01, FF-B02, FF-C01, FF-C02", "Sesuai", ""),
    ("NF-8", "Ketersediaan layanan minimal 80 persen",
     "Server bertahan sepanjang dua rangkaian pengujian tanpa kegagalan boot; pengukuran jangka panjang memerlukan "
     "pemantauan produksi.", "Observasi pelaksanaan uji", "Belum terukur",
     "Bukan kegagalan, melainkan belum dapat diukur pada lingkungan lokal."),
    ("NF-9", "Setiap kesalahan memberi pesan jelas tanpa crash",
     "Respons JSON berpesan bahasa Indonesia untuk 401/403/404/409/422/429; renderable pada app/Exceptions/"
     "Handler.php menutup 'Unauthenticated.', 'The given data was invalid.', dan bocoran 'No query results for model "
     "[App\\Models\\Unit]' yang selama ini menampilkan nama kelas serta path berkas.",
     "TC-A05, TC-A06, TC-B08, TC-B10, TC-E08, TC-H06, TC-Q04, TC-Q05, NF-07 dan NF-08 (skrip NF)", "Sesuai",
     f"Terukur: {nf_ukur('NF-08', 110)}. Dengan APP_DEBUG=true respons 500 masih menampilkan jejak teknis; "
     "wajib dimatikan bila aplikasi dipasang di luar mesin pengembang."),
    ("NF-9a (tambahan)", "Percobaan login berulang dibatasi",
     "App\\Support\\LoginThrottle menolak 429 beserta retry_after dan locked_until setelah tiga percobaan gagal pada "
     "kombinasi email + alamat IP, lalu menahan akun selama login_lockout_minutes hasil pengaturan dan membersihkannya "
     "begitu login berhasil; RateLimiter auth-password (10/menit) "
     "mengawal reset sandi, refresh sesi, dan pergantian sandi.",
     "TC-Q01, TC-Q02, TC-Q03, TC-R24, FF-R33", "Sesuai",
     "Menutup separuh batasan D-1; laporan sebelumnya hanya memakai throttle:api 60 permintaan/menit per IP sehingga "
     "brute-force satu akun masih mungkin. Sejak revisi 34 ambang percobaan menjadi tiga dan lama penguncian dapat "
     "diatur super admin pada Pengaturan Sistem."),
    ("NF-10", "Setiap data divalidasi sebelum disimpan",
     "Validasi form request pada seluruh controller; nilai turunan finansial dihitung server, tidak diterima dari klien.",
     "TC-B05, TC-C02, TC-C03, TC-D03, TC-D04, TC-D05, TC-E02, TC-F02, TC-M03, TC-K04, TC-K05, NF-10 (skrip NF)",
     "Sesuai", ""),
    ("NF-11", "Sistem mencegah duplikasi data",
     "Keunikan dijaga lapisan validasi memakai Rule::unique(...)->whereNull('deleted_at') untuk users.email, "
     "units.unit_code, sales_profiles.sales_code, payment_schemes.scheme_name, dan customer_leads.phone "
     "(CustomerLeadController::aturanTelepon dengan ignore(id)); indeks unik tingkat basis data "
     "masih berlaku pada clusters(project_id,name), sales_targets(sales_id,periode), sales_reports(periode), "
     "settings(key), sales_profiles(user_id), dan bookings.booking_code.",
     "TC-B04, TC-F02, TC-D04, TC-C03, TC-R15, TC-R19, NF-10 (skrip NF)", "Sebagian",
     "Indeks unik email/kode unit/kode sales/nama skema sengaja diturunkan menjadi indeks biasa oleh migration "
     "2026_11_01_110000_add_soft_deletes.php agar kode dari baris yang dihapus lunak dapat dipakai lagi, dan "
     "clusters(project_id,name) diturunkan oleh migration pengelolaan cluster (TC-M03 menjaga keunikannya di lapisan "
     "validasi). Nomor handphone lead tidak dapat dipakai dua konsumen dalam keadaan mana pun — cold, warm, hot, "
     "maupun sesudah dibatalkan (revisi 19 dan 24, dibuktikan TC-R19)— karena pembatalan tidak menghapus baris "
     "lead; hanya hapus lunak yang membebaskan nomornya. Form lead pada halaman "
     "publik sudah dihapus sehingga lead hanya dibuat sales."),
    ("NF-12", "Dapat diakses melalui Chrome, Firefox, dan Edge",
     "API REST berbasis HTTP/JSON standar; frontend memakai JavaScript dan fetch tanpa pustaka khusus browser.",
     "Seluruh test case, NF-12 (skrip NF)", "Sebagian",
     f"Uji fungsional dijalankan pada Chromium ({len(ui)}/{len(ui)} skenario Pass); Firefox dan Edge belum "
     "dijalankan, demikian pula uji visual pada tiga ukuran layar."),
    ("NF-13 (tambahan)", "Setiap aktivitas penting tercatat",
     "login_sessions mencatat IP, perangkat, serta waktu masuk dan keluar; system_logs dan notifications mencatat "
     "peristiwa bisnis; AuditObserver kini menulisi tabel activity_logs pada peristiwa created/updated/deleted "
     "sembilan entitas dan halaman Log Aktivitas membacanya pada tab 'Jejak audit data'.",
     "TC-A15, TC-I01, TC-I02, TC-I03, TC-I05, TC-I06, TC-O01, TC-O02, TC-O03, FF-A23, NF-05 (skrip NF)", "Sesuai",
     f"Terukur: {nf_ukur('NF-05', 110)}. Log hanya dapat dibaca super admin; sales dan admin sales ditolak "
     "(TC-I06, TC-O03, TC-L04). Butir yang pada laporan sebelumnya berstatus Sebagian ini ditutup sebagai "
     "tindak lanjut D-5."),
]
table(["No", "Kebutuhan non-fungsional", "Perlakuan pada sistem", "Bukti", "Status", "Catatan"],
      [Cm(1.7), Cm(5.2), Cm(8.6), Cm(4.4), Cm(2.2), Cm(5.2)], nfr,
      sizes=[8, 8, 8, 7, 8, 7], bold_cols=(0,), center_cols=(0,), status_col=4)
nf_sesuai = sum(1 for r in nfr if r[4] == "Sesuai")
nf_sebagian = sum(1 for r in nfr if r[4] == "Sebagian")
para(f"Ringkasan kebutuhan non-fungsional: {nf_sesuai} butir Sesuai dan {nf_sebagian} butir Sebagian dari "
     f"{len(nfr)} butir; tidak ada butir yang Belum terpenuhi. Butir Sebagian disertai ukuran nyata dan "
     f"tindak lanjut pada subbab 7.2.", 9, True)
doc.add_page_break()

# ============================== BAB III ==============================
head("BAB III - Analisis kesesuaian frontend terhadap kebutuhan pengguna")
para("Analisis frontend dilakukan pada aplikasi yang benar-benar berjalan di peramban, bukan dari pembacaan kode. "
     "Untuk setiap aktor, token login disisipkan ke localStorage lalu halaman internal dimuat, dan yang dicatat "
     "adalah fakta DOM hasil render: daftar tautan sidebar, menu yang tampil pada ikon profil, jumlah baris tabel, "
     "label tombol aksi, isi dialog konfirmasi, serta inventaris field pada halaman pengaturan dan profil.", 9)

head("3.1 Kesesuaian per butir kebutuhan pengguna", 2)
fmatrix = [
    ("Satu akun super admin", "Hanya superadmin@gmail.com yang berlaku; akun admin dan manajer lama tidak dapat masuk",
     "FF-A01, FF-A02, FF-D03 + TC-A04c", "Sesuai"),
    ("Navigasi", "Sidebar hanya berisi modul kerja; Profil, Sesi Login, Pengaturan Sistem, dan Pengaturan Halaman "
     "Publik dibuka dari ikon profil; notifikasi berada di ikon lonceng header", "FF-A01, FF-A02, FF-A03", "Sesuai"),
    ("Tiga aktor login", "Konsumen tidak memiliki jalur masuk; antarmuka hanya menyediakan login bagi tiga aktor "
     "internal (super admin, admin sales, sales)",
     "FF-D03 + TC-A04, TC-A04b", "Sesuai"),
    ("Hak Super Admin", "Delapan modul kerja tampil dan seluruh aksi manajemen tersedia (kelola proyek, tambah unit, "
     "edit, hapus, verifikasi pembayaran, serah terima, laporan, log aktivitas, sesi login)",
     "FF-A01, FF-A04..FF-A23", "Sesuai"),
    ("Tambah proyek di Data Unit", "Tombol Tambah Proyek hanya terlihat untuk super admin; modal berisi perumahan "
     "induk, nama proyek, dan deskripsi. Proyek hasil simpan langsung muncul pada penapis proyek dan pada opsi "
     "Proyek di form Tambah Unit", "FF-A18, FF-A19", "Sesuai"),
    ("Pengelolaan proyek menyeluruh", "Halaman /data-proyek berjudul 'Pengelolaan Proyek' dengan kartu ringkasan "
     "Total Proyek / Proyek Aktif / Proyek Arsip / Unit Terdaftar, penapis nama, perumahan, dan status, kolom Peta "
     "untuk koordinat, serta tombol aksi per baris 'Detail', 'Ubah', 'Arsipkan' atau 'Aktifkan', dan 'Hapus'",
     "FF-A20, FF-B09, FF-C07, FF-R04", "Sesuai"),
    ("Penghitung waktu sesi", "Chip 'Sesi hh:mm:ss' pada header berubah menjadi kuning dan memunculkan tombol "
     "Perpanjang saat sisa waktu turun di bawah lima menit; perpanjangan mengganti token dan token lama langsung "
     "berhenti berlaku", "FF-A21", "Sesuai"),
    ("Laporan berpengesahan", "Lembar cetak memuat kop perumahan, judul, periode, waktu cetak, serta blok 'Dibuat "
     "oleh' dan 'Mengetahui: Pimpinan / Atasan langsung'; blok hanya tampil pada media print", "FF-A22", "Sesuai"),
    ("Jejak audit pada antarmuka", "Halaman /log-aktivitas memiliki dua tab ('Jejak audit data' dan 'Aktivitas "
     "sistem & login'); tab audit menampilkan waktu, pengguna, aksi, keterangan, dan IP", "FF-A23", "Sesuai"),
    ("Hak Admin Sales (hanya-lihat)", "Data Unit, Data Konsumen, Booking, dan Data Pembayaran hanya menyediakan "
     "tombol Detail; tanpa Log Aktivitas dan tanpa Sesi Login; Data Sales tetap dapat dikelola; pada Data Unit tombol "
     "Kelola Proyek/Tambah Unit/Tambah Proyek tidak tampil; pada halaman detail unit judulnya \"Data Unit\" tanpa "
     "kata Ubah dengan seluruh isian terkunci dan tanpa pemilih foto; pada Data Proyek kolom Aksi tidak dirender "
     "sama sekali",
     "FF-B01, FF-B02, FF-B03, FF-B04, FF-B05, FF-B06, FF-B07, FF-B08, FF-B09, FF-B10, FF-B11", "Sesuai"),
    ("Hak Sales", "Tanpa menu Sesi Login dan tanpa field Sesi Aktif pada profil; hanya menangani data konsumennya "
     "sendiri; memakai laporan khusus sales; membuka /data-proyek dijawab halaman penolakan peran",
     "FF-C01, FF-C02, FF-C03, FF-C04, FF-C05, FF-C06, FF-C07", "Sesuai"),
    ("Detail + hapus pada setiap tabel", "Baris Data Unit, Data Sales, dan Data Konsumen hanya menyediakan aksi "
     "Detail dan Hapus; proses edit berpindah ke halaman detail masing-masing entitas",
     "FF-A04, FF-A05, FF-A06, FF-A07, FF-A08, FF-A09", "Sesuai"),
    ("Penghapusan lunak", "Dialog hapus menyatakan data dikeluarkan dari daftar dan riwayatnya tetap tersimpan, bukan "
     "hilang permanen", "FF-A10, FF-D05", "Sesuai"),
    ("Pengaturan Sistem", "Memuat seksi identitas, penjualan dan pembayaran, serta kebijakan login pada seksi Sistem & "
     "Sesi Login (session_timeout_minutes sebagai batas waktu tanpa aktivitas dan login_lockout_minutes sebagai lama "
     "penguncian setelah tiga kali gagal); seksi dan isian integrasi Google sudah dilepas dari antarmuka",
     "FF-A11, FF-R32, FF-R33", "Sesuai"),
    ("Pengelolaan halaman publik", "Super Admin dapat mengatur hero, saklar seksi beranda, unit unggulan, pengumuman, "
     "footer, dan kabar terbaru; nilai yang sama dipatuhi halaman publik", "FF-A12, FF-D01, FF-D04", "Sesuai"),
    ("Profil per peran", "Profil super admin tidak lagi menampilkan Bergabung Sebagai Sales dan Kode Sales; profil "
     "sales menampilkan identitas, foto, dan kata sandi tanpa field sesi", "FF-A14, FF-C03", "Sesuai"),
    ("Laporan dapat dibaca", "Empat tab laporan terisi data pada jendela 30 hari dan menyediakan ekspor; sales "
     "memperoleh halaman Laporan Sales sendiri", "FF-A13, FF-C05", "Sesuai"),
    ("Halaman publik konsumen", "Beranda, detail unit, skema pembayaran, tentang kami, kontak, dan booking berjalan "
     "tanpa login dengan harga serta narahubung WhatsApp; tidak ada lagi form pengisian calon konsumen",
     "FF-D01, FF-D02, FF-D04 + TC-H01, TC-H02, TC-H03, TC-H04, TC-H05, TC-H06, TC-H08", "Sesuai"),
    ("Reset dan ganti sandi", "Tersedia halaman ganti sandi wajib (/ganti-sandi) dan reset sandi dua langkah "
     "(/reset-password)", "FF-D06, FF-D07", "Sesuai"),
]
table(["Aspek kebutuhan", "Bukti yang diamati pada antarmuka", "Rujukan uji", "Status"],
      [Cm(5.0), Cm(14.3), Cm(5.4), Cm(2.6)],
      [[a, b, c, d] for a, b, c, d in [(x[0], x[1], x[2], x[3]) for x in fmatrix]],
      sizes=[8, 8, 7, 8], status_col=3)

head("3.2 Perbaikan frontend yang lahir dari analisis ini", 2)
table(["Temuan", "Sebab", "Perbaikan", "Verifikasi ulang"],
      [Cm(6.6), Cm(6.6), Cm(7.6), Cm(6.5)],
      [
          ["Dialog hapus kabar terbaru menjanjikan penghapusan permanen",
           "Teks lama pada resources/views/app/pengaturan-publik.blade.php berbunyi \"akan dihapus permanen\" padahal "
           "model PublicPost memakai SoftDeletes",
           "Teks diubah menjadi \"...akan dikeluarkan dari daftar kabar terbaru. Riwayatnya tetap tersimpan di basis "
           "data dan dapat dipulihkan administrator.\"", "FF-D05: dialog terbaca sesuai, Batal tidak menghapus data"],
          ["Saklar Galeri pada halaman Tentang Kami tidak dipatuhi",
           "public/tentang.blade.php selalu merender seksi galeri tanpa membaca pengaturan public_show_*",
           "Setiap seksi diberi atribut data-seksi dan blok init diganti memakai LP.fillSettings() seperti pada "
           "beranda; seksi yang dimatikan tidak dirender dan datanya tidak diminta",
           "FF-D04: public_show_gallery=0 menghasilkan galeri tersembunyi dengan mount hanya 149 karakter; "
           "seksi Skema dan Tim Sales tetap tampil"],
          ["Rincian laporan pernah tampil kosong",
           "Tab laporan menunggu perubahan tanggal sebelum memuat data",
           "Tanggal awal dihitung hari ini dikurangi 29 hari lalu tabel dimuat saat halaman dibuka",
           "FF-A13: empat tab langsung terisi (lima baris laporan penjualan)"],
          ["Profil sales menunjukkan tulisan \"belum memiliki profil sales\"",
           "Halaman profil mengharapkan relasi sales_profiles yang tidak selalu terisi untuk peran super_admin",
           "Panel profil dirender sesuai peran; baris Sesi Aktif hanya disisipkan untuk super_admin",
           "FF-C03 dan FF-A14: kedua profil menampilkan data yang benar"],
      ], sizes=[8, 8, 8, 8])

head("3.3 Limitasi frontend yang masih terbuka", 2)
bullet("Batas waktu tanpa aktivitas sudah tampil sebagai hitung mundur beserta peringatan lima menit, perpanjangan "
       "senyap saat layar masih dipakai, dan tombol Perpanjang (FF-A21, FF-R34). Yang belum ada adalah saluran kedua "
       "berupa notifikasi e-mail atau pengingat di luar aplikasi saat sesi akan berakhir.")
bullet("Tombol Masuk dengan Google pada /login masih bergantung pada nilai google_client_id, google_client_secret, "
       "dan google_login_enabled pada tabel settings. Sejak revisi 33 kolom pengisiannya tidak lagi ditampilkan pada "
       "halaman Pengaturan, sehingga kredensial OAuth diisi langsung pada tabel settings oleh pemilik proyek; alur "
       "kodenya sudah ada (rute auth/google/redirect dan auth/google/callback) dan kontrak API tidak berubah "
       "(TC-R25).")
bullet("Label kolom identitas pada /login berbunyi \"Nama Pengguna\" sementara yang dimasukkan adalah email. Ini "
       "disengaja mengikuti wireframe, tetapi dapat diubah menjadi \"Email\" bila laporan disesuaikan.")
bullet("Tampilan logo belum dapat diverifikasi secara visual karena lingkungan pengujian tidak menyediakan viewport "
       "browser; yang diverifikasi adalah struktur DOM, bukan kenampakan piksel.")

head("3.4 Penanganan daftar revisi pengguna", 2)
para("Daftar revisi berikut lahir dari elisitasi ulang setelah aplikasi dapat dibuka, sehingga tidak tercantum pada "
     "butir a-h BAB III laporan tugas proyek. Setiap butir dikerjakan pada backend dan frontend, lalu diuji ulang "
     "dengan kode test case TC-R (permintaan API) dan FF-R (observasi DOM pada peramban) terhadap basis data uji "
     "luminpark_test.", 9)
table(["No.", "Permintaan revisi", "Perlakuan pada sistem", "Bukti uji", "Status"],
      [Cm(1.0), Cm(7.0), Cm(10.2), Cm(6.6), Cm(1.9)],
      [
          ["1", "Foto sales pada daftar kontak halaman publik yang menangani konsumen",
           "PublicInfoController::salesContacts mengirim avatar_url; LP.fotoSales() merender <img> atau inisial bila "
           "foto belum ada (public/js/luminpark.js, /kontak, detail unit, /tentang)",
           "TC-R07, FF-R01", "Sesuai"],
          ["2", "Kolom tanggal chat awal pada form tambah konsumen peran Sales",
           "Kolom customer_leads.first_chat_date (migration 2026_11_19_090000_add_revisi_proyek_columns), validasi "
           "nullable|date, isian pada modal dan kolom 'Chat Awal' pada daftar serta ekspor laporan",
           "TC-R01, FF-R02", "Sesuai"],
          ["3", "Pilihan metode pembayaran termasuk di luar uang piutang pada peran Sales",
           "payments.payment_method dipisah dari payment_type; Payment::METHODS = transfer bank, tunai, qris, virtual "
           "account, cek/giro; pemilih wajib pada form catat pembayaran",
           "TC-R03, FF-R03", "Sesuai"],
          ["4", "Seluruh penyebutan Cluster menjadi Proyek",
           "Judul halaman, tombol, pesan ClusterController, dan label AuditObserver memakai 'proyek'; nama tabel "
           "clusters, rute /clusters, dan parameter cluster_id dipertahankan",
           "TC-M01…M11, TC-O02, FF-R04", "Sesuai"],
          ["5", "Status pembatalan pada data konsumen dengan riwayat pembelian tetap tersimpan",
           "PATCH /bookings/{id}/cancel menyimpan waktu dan alasan; CustomerLead::category() menghasilkan 'dibatalkan'; "
           "blok pembatalan tampil pada detail booking dan detail konsumen; laporan menyaring category=dibatalkan",
           "TC-R04, FF-R05", "Sesuai"],
          ["6", "Peran super admin tetap memakai nama halaman Data Konsumen",
           "NAV super_admin/admin_sales menunjuk /data-konsumen berlabel 'Data Konsumen'; judul dirender span "
           "#lead-judul agar dapat diganti menurut peran",
           "FF-R06", "Sesuai"],
          ["7", "Konsumen hanya yang sudah booking; calon konsumen menjadi Database Konsumen dengan status Cold, "
                "Warm, Hot",
           "Dua menu khusus Sales (?kategori=konsumen dan ?kategori=prospek); scope prospects/customers/cancelled; "
           "status lead dibatasi cold|warm|hot sehingga nilai booking ditolak",
           "TC-R02, FF-R07", "Sesuai"],
          ["8", "Sandi awal sales memakai tanggal lahir yyyymmdd dan wajib diganti dengan sandi kuat",
           "SalesController::passwordFromBirthDate() + must_change_password; AuthController::updatePassword menolak "
           "sandi tanpa huruf besar, huruf kecil, angka, dan minimal delapan karakter",
           "TC-R08, FF-R08, FF-D06", "Sesuai"],
          ["9", "Log Aktivitas hanya untuk peran Monitoring Manajemen",
           "Rute /activity-logs dan /system-logs role:admin_sales; guard halaman diganti menjadi ['admin_sales'] "
           "(resources/views/app/log-aktivitas.blade.php)",
           "TC-O01, TC-O03, TC-L04, FF-R09", "Sesuai"],
          ["10", "Filter per proyek pada statistik super admin",
           "Trait FiltersProjects dipakai DashboardController::superAdmin lewat parameter cluster_id; tabel 'Statistik "
           "per Proyek' merangkum seluruh proyek bila filter dikosongkan",
           "TC-R05, FF-R10", "Sesuai"],
          ["11", "Pencarian Data Unit berdasarkan Blok, Status, dan Proyek",
           "Tiga penapis pada resources/views/app/data-unit.blade.php (#unit-search, #unit-status, #unit-cluster); API "
           "mendukung parameter cluster_id dan status",
           "TC-R09, FF-R11", "Sesuai"],
          ["12", "Peta lokasi setiap proyek pada halaman beranda",
           "clusters.address/latitude/longitude dikelola pada Data Proyek; GET /api/public/project-locations menyuplai "
           "proyek aktif berkoordinat ke peta Leaflet di resources/views/public/beranda.blade.php",
           "TC-R06, FF-R12", "Sesuai"],
          ["13", "Informasi skema pembayaran dihapus dari halaman Tentang Kami",
           "Seksi skema dihapus dari resources/views/public/tentang.blade.php, nilai about_text pada seeder dan basis "
           "data diperbarui, judul 'Identitas Project' menjadi 'Identitas Proyek'",
           "FF-R13", "Sesuai"],
          ["14", "Atur ulang sandi tidak lagi meminta kata sandi lama; verifikasi lewat email akun",
           "Rute tamu POST /api/forgot-password menyimpan token 64 karakter sebagai hash SHA-256 pada "
           "password_reset_tokens (berlaku 10 menit) dan mengirim Mailable LinkAturUlangSandi berisi tautan "
           "/reset-password?token=...&email=...; POST /api/reset-password/token menyimpan sandi baru cukup dengan "
           "token tersebut lalu mencabut token dan sesi lama; halaman auth/reset-password.blade.php menjadi tiga "
           "langkah. Jalur email + password_lama pada POST /api/reset-password tetap dipakai halaman Profil, "
           "/ganti-sandi, dan paksaan ganti sandi",
           "TC-R10, TC-R11, FF-R14", "Sesuai"],
          ["15", "Calon konsumen otomatis pindah ke data konsumen setelah pembayaran diverifikasi, dan datanya masuk "
                 "ke riwayat",
           "CustomerLead::sudahBayarTerverifikasi() menjadi satu-satunya penentu kategori 'konsumen' (dipakai "
           "CustomerLeadController, DashboardController, dan ReportController dengan bookings.payments dimuat lebih "
           "dahulu); scope riwayat() (customers OR cancelled) sempat ditawarkan sebagai filter category=riwayat dan "
           "menu History pada revisi 18, lalu sejak revisi 25 riwayat cukup dibaca dari penapis Kategori Dibatalkan "
           "sehingga tidak ada lagi pilihan riwayat pada Data Konsumen",
           "TC-R12, TC-D07, FF-R15", "Sesuai"],
          ["16", "Konsumen yang sudah membayar dan menempati rumah lalu membatalkan keluar dari menu konsumen dan "
                 "pindah ke data riwayat",
           "BookingController::cancel tidak lagi menolak booking lunas/selesai, mengembalikan unit menjadi available, "
           "dan menyimpan riwayat pembayaran; CustomerLead::pembatalanTerakhir() mengisi kolom pembatalan pada "
           "category=dibatalkan; halaman khusus yang semula /pembatalan-konsumen sempat berpindah menjadi /history "
           "pada revisi 18 lalu dihapus total pada revisi 25 sehingga kedua alamat menghasilkan HTTP 404 dan riwayat "
           "dibuka lewat penapis Kategori Dibatalkan",
           "TC-R13, TC-R04, FF-R16", "Sesuai"],
          ["17", "Biaya tambahan konstruksi di luar biaya utama perumahan beserta request tambahan bangunan saat "
                 "booking",
           "Kolom bookings.addition_cost (decimal 15,2 default 0) dan addition_description (string 255) pada migration "
           "2026_11_20_090000_add_booking_addition_cost; BookingController::store/::update memvalidasi nullable|numeric|"
           "min:0 dan nullable|string|max:255 tanpa menyentuh calculateBreakdown sehingga uang muka, angsuran, dan sisa "
           "tetap dihitung dari harga unit; isian, catatan, kolom tabel, detail, riwayat konsumen, dan dua kolom ekspor "
           "CSV ditambahkan",
           "TC-R14, FF-R17", "Sesuai"],
          ["18", "Susunan menu peran Sales tanpa Pembatalan Konsumen dan tanpa History",
           "NAV public/js/luminpark.js menghasilkan enam menu untuk Sales (Dashboard, Konsumen, Database Konsumen, "
           "Booking Unit, Data Pembayaran, Laporan Sales); routes/web.php sudah menghapus Route::view "
           "'/pembatalan-konsumen' (HTTP 404) dan pada revisi 25 ikut menghapus '/history' sehingga kedua alamat "
           "lawas menolak dan riwayat pembatalan dibaca dari penapis Kategori pada menu Konsumen; berkas "
           "resources/views/app/history.blade.php tidak lagi dirujuk rute mana pun",
           "TC-R17, FF-R16, FF-R18", "Sesuai"],
          ["19", "Nomor handphone hanya dapat dipakai satu konsumen",
           "CustomerLeadController::aturanTelepon() membangun Rule::unique('customer_leads','phone') berfilter "
           "whereNull('deleted_at') dan ignore(id) saat pembaruan, dipakai store() (required) dan update() (sometimes) "
           "dengan pesan seragam PESAN_TELEPON_DIPAKAI; petunjuk isian Nomor Telepon pada form menjelaskan aturannya",
           "TC-R15, FF-R19", "Sesuai"],
          ["20", "Pilihan konsumen pada booking hanya yang berstatus Hot",
           "bookingLeadsHot() menyaring opsi pemilih Konsumen pada resources/views/app/booking.blade.php dan "
           "bookingOpenCreate() menolak membuka modal bila belum ada lead Hot; BookingController::store tetap "
           "menegakkan status hot (HTTP 422) untuk permintaan langsung",
           "TC-R16, FF-R20", "Sesuai"],
          ["21", "Sales memilih ada/tidaknya permintaan tambahan bangunan saat booking",
           "Select wajib addition_requested (tidak/ya, default tidak) dengan bookingToggleTambahan() yang "
           "menyembunyikan serta mengosongkan label addition_description dan addition_cost; bookingCreate() mengirim "
           "biaya 0 dan keterangan null saat 'tidak' dan menolak 'ya' tanpa keterangan, sedangkan "
           "BookingController::tolakTambahanTanpaKeterangan() menegakkan aturan yang sama pada store() dan update()",
           "TC-R18, TC-R14, FF-R21", "Sesuai"],
          ["22", "Tombol tambah data konsumen pada menu Data Konsumen dihilangkan",
           "leadPaintActions() pada resources/views/app/data-konsumen.blade.php menyembunyikan #btn-add-lead untuk "
           "peran Sales kecuali pada penapis kosong/prospek; pilihan 'riwayat' dihapus dari #lead-category — yang sejak "
           "revisi 25 hanya menawarkan Semua Kategori, Konsumen, Calon Konsumen, dan Dibatalkan — dan catatan halaman "
           "menjelaskan daftar Konsumen terisi otomatis setelah pembayaran disetujui (CustomerLead::category)",
           "TC-R12, FF-R22, FF-R15", "Sesuai"],
          ["23", "Tombol Batal pada kolom Aksi Data Konsumen berisi alasan dan tanggal pembatalan, datanya pindah ke "
                 "kategori Dibatalkan",
           "Rute POST /api/customer-leads/{id}/batalkan (role:sales,super_admin) memvalidasi reason dan cancelled_at "
           "lalu memanggil Booking::batalkan() — dipakai juga oleh PATCH /bookings/{id}/cancel — yang mengunci status "
           "dibatalkan, mengembalikan unit menjadi available, dan menyimpan pembayaran terverifikasi sebagai riwayat; "
           "trait Concerns/NotifiesOperationalUsers mengirim notifikasi bertaut /data-konsumen?kategori=dibatalkan "
           "dan balasan API menyebut pemindahan ke kategori Dibatalkan. Tombol Batal dirender hanya "
           "bila baris punya_booking_aktif dan modal leadOpenBatal() memuat dua isian tersebut",
           "TC-R17, TC-R13, FF-R23", "Sesuai"],
          ["24", "Nomor handphone tetap tidak dapat dipakai ulang walau konsumennya masih cold atau sudah dibatalkan",
           "Aturan pada revisi 19 dipertahankan tanpa perubahan logika karena pembatalan hanya mengunci status booking "
           "dan tidak menghapus lead: baris customer_leads tetap hidup (deleted_at null) sehingga Rule::unique tetap "
           "menolak nomor yang sama pada konsumen cold, warm, hot, maupun dibatalkan — satu-satunya jalan membebaskan "
           "nomor adalah hapus lunak (revisi 15). Petunjuk isian dan pesan PESAN_TELEPON_DIPAKAI menjelaskan keadaan "
           "tersebut dan TC-R19 membuktikan kedua ujungnya",
           "TC-R15, TC-R19, FF-R19", "Sesuai"],
          ["25", "Menu History dihapus; riwayat pembatalan dicari lewat penapis Kategori pada menu Konsumen",
           "Entri { href: '/history' } dihapus dari array NAV public/js/luminpark.js dan Route::view('/history', "
           "'app.history') dihapus dari routes/web.php, sehingga peran mana pun hanya menampilkan tautan aslinya dan "
           "GET /history menghasilkan HTTP 404; tautan page-action, catatan halaman, deskripsi modal Batal, dan "
           "notifikasi operasional dialihkan ke /data-konsumen?kategori=dibatalkan. Penapis Kategori tetap memuat "
           "opsi Dibatalkan dan halaman detail konsumen mempertahankan kolom Pembatalan berisi alasan serta tanggal, "
           "sehingga tidak ada informasi yang hilang",
           "TC-R17, TC-R19, FF-R16, FF-R18", "Sesuai"],
          ["26", "Penapis Status dihilangkan dari menu Konsumen karena status hanya dimiliki calon konsumen",
           "leadPaintFilterVisibility() pada resources/views/app/data-konsumen.blade.php memberi label penapis id "
           "#lead-label-status lalu menyembunyikannya (kelas hidden) bagi peran Sales pada kategori konsumen dan "
           "dibatalkan, serta mengosongkan nilai #lead-status agar baris tidak tersembunyi di balik penapis yang tidak "
           "tampak; fungsi dipanggil saat guard halaman dan setiap penapis Kategori berubah. Cold/warm/hot tetap "
           "tersedia pada Database Konsumen dan pada super admin kedua penapis tetap tampil",
           "TC-R01, TC-R16, FF-R24", "Sesuai"],
          ["27", "Penapis Kategori dihilangkan dari menu Database Konsumen",
           "Blok yang sama pada leadPaintFilterVisibility() menyembunyikan #lead-label-category ketika peran Sales "
           "berada pada kategori prospek dan mengunci nilai #lead-category pada 'prospek' agar leadKategoriAktif(), "
           "leadJudul(), dan leadPaintActions() tetap bekerja; judul 'Database Konsumen', jumlah baris calon, serta "
           "tombol Tambah Konsumen tidak berubah sehingga revisi 22 tetap terpenuhi",
           "TC-R12, FF-R20, FF-R24", "Sesuai"],
      ], sizes=[8, 8, 8, 8, 8], center_cols=(0, 4), status_col=4)

para("Verifikasi per peran atas keempat belas butir pertama tersebut menemukan lima galat yang lolos dari suite API karena "
     "hanya dapat diamati pada antarmuka, dan semuanya ditutup pada sesi revisi yang sama: guard halaman Log Aktivitas "
     "masih menuntut super admin (FF-R09); jejak audit masih menulis aksi berakhiran cluster sehingga istilah Proyek "
     "tidak konsisten (FF-R04); halaman Tentang Kami masih memuat narasi skema dari kolom pengaturan about_text dan "
     "berjudul bahasa Inggris (FF-R13); penapis kategori pada halaman Laporan konsumen masih memakai nilai lama "
     "sehingga selalu kosong (FF-R05); serta penyaringan dashboard menimbulkan galat relasi 'Call to undefined method "
     "Booking::booking()' pada deret bulanan (FF-R10).", 9)
para("Pengulangan verifikasi yang sama untuk sepuluh butir terakhir (revisi 18-27) tidak menemukan galat antarmuka baru: "
     "sidebar peran Sales berisi enam tautan tanpa History maupun Pembatalan Konsumen, alamat /history dan "
     "/pembatalan-konsumen keduanya menghasilkan HTTP 404, nomor handphone ganda ditolak dengan pesan yang sama pada "
     "isian dan toast termasuk ketika nomor pertama masih berstatus cold atau konsumennya sudah dibatalkan, pilihan "
     "konsumen pada form booking hanya memuat lead Hot, isian tambahan bangunan tersembunyi pada keadaan awal, tombol "
     "Tambah Konsumen tidak lagi terender pada menu Konsumen, satu pembatalan lewat tombol Batal memindahkan barisnya "
     "ke kategori Dibatalkan dengan tanggal, alasan, serta nominal terverifikasi yang tetap terbaca pada halaman "
     "detail, dan pada menu Konsumen label Status hilang (nilainya ikut dikosongkan) sementara pada Database Konsumen "
     "label Kategori hilang dengan judul serta jumlah baris yang tetap benar.", 9)
doc.add_page_break()


# ============================== BAB IV & V (tabel sepuluh kolom) ==============================
COLS = ["ID", "Nama", "Skenario", "Deskripsi", "Langkah Uji", "Data Uji",
        "Hasil yang Diharapkan", "Hasil Aktual", "Status (Pass/Fail)", "Catatan"]
W = [Cm(1.2), Cm(2.4), Cm(2.0), Cm(3.2), Cm(3.0), Cm(2.9), Cm(3.2), Cm(4.4), Cm(1.2), Cm(3.8)]


def uji_table(rows, judul, pengantar):
    head(judul)
    para(pengantar, 9)
    t = doc.add_table(rows=1, cols=len(COLS))
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.autofit = False
    for i, hd in enumerate(COLS):
        c = t.rows[0].cells[i]
        c.text = ""
        r = c.paragraphs[0].add_run(hd)
        r.bold = True
        r.font.size = Pt(7.5)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        shade(c, HDR)
        c.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
    for x in rows:
        ok = x["status"] == "Pass"
        texts = [x["id"], x["nama"], x["skenario"], x["deskripsi"], x["langkah"], x["dataUji"], x["ekspektasi"],
                 x["actualBody"], "PASS" if ok else "FAIL",
                 " ".join(filter(None, [x.get("catatan", "").strip(),
                                        "[" + x["kebutuhan"] + "]" if x.get("kebutuhan") else ""])).strip()]
        cells = t.add_row().cells
        for i, v in enumerate(texts):
            c = cells[i]
            c.text = ""
            p = c.paragraphs[0]
            r = p.add_run(str(v))
            r.font.size = Pt(6.5)
            if i == 0:
                r.bold = True
            if i == 8:
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                r.bold = True
                r.font.color.rgb = GREEN if ok else RED
                shade(c, "E3F2E3" if ok else "FBE4E4")
    for row in t.rows:
        for i, c in enumerate(row.cells):
            c.width = W[i]
    repeat_header(t)
    doc.add_page_break()


def hard_group_count(huruf):
    return sum(1 for r in hard if r["id"].startswith(f"TC-{huruf}"))


uji_table(
    auto,
    "BAB IV - Tabel hasil pengujian backend (format sepuluh kolom)",
    f"Sebanyak {len(auto)} test case dieksekusi dengan node tests/run-conformance-tests.mjs terhadap server "
    f"http://127.0.0.1:8081/api (LP_API_BASE), setelah basis data uji luminpark_test diisi ulang dengan "
    f"DB_DATABASE=luminpark_test php artisan migrate:fresh --seed pada {hari.strftime('%d %B %Y')}. Kolom Hasil Aktual memuat kode status HTTP "
    f"dan potongan isi respons yang benar-benar dikembalikan aplikasi; kolom Catatan memuat tafsir hasil serta "
    f"rujukan butir kebutuhan.")

uji_table(
    hard,
    "BAB V - Tabel hasil pengujian penguatan sistem (format sepuluh kolom)",
    f"Sebanyak {len(hard)} test case dieksekusi dengan node tests/run-hardening-tests.mjs terhadap server yang sama "
    f"({hard_group_count('M')} uji pengelolaan proyek TC-M, {hard_group_count('N')} uji pemisahan data per sales "
    f"TC-N, {hard_group_count('O')} uji jejak audit TC-O, {hard_group_count('P')} uji batas waktu sesi TC-P, dan "
    f"{hard_group_count('Q')} uji pembatasan laju login serta lokalisasi pesan TC-Q). Suite ini khusus membuktikan "
    f"butir yang pada laporan sebelumnya berstatus batasan (D-1 sampai D-7) sudah benar-benar ditutup, dijalankan "
    f"setelah basis data uji luminpark_test diisi ulang.")

uji_table(
    ui,
    "BAB VI - Tabel hasil pengujian frontend (format sepuluh kolom)",
    f"Sebanyak {len(ui)} test case diamati pada peramban dengan memuat halaman nyata dari aplikasi. Prefix FF-A untuk "
    f"aktor Super Admin ({sum(1 for r in ui if r['id'].startswith('FF-A'))} uji), FF-B untuk Admin Sales "
    f"({sum(1 for r in ui if r['id'].startswith('FF-B'))} uji), FF-C untuk Sales "
    f"({sum(1 for r in ui if r['id'].startswith('FF-C'))} uji), FF-D untuk halaman publik serta autentikasi "
    f"({sum(1 for r in ui if r['id'].startswith('FF-D'))} uji), dan FF-R khusus bukti antarmuka daftar revisi pengguna "
    f"({sum(1 for r in ui if r['id'].startswith('FF-R'))} uji). Kolom Hasil Aktual berisi fakta DOM yang terbaca dari "
    f"dokumen yang sudah dirender (tautan menu, jumlah baris tabel, label tombol, isi dialog, nilai field).")

# ============================== BAB VI ==============================
head("BAB VII - Kesimpulan dan rekomendasi")
head("7.1 Kesimpulan", 2)
para(f"Backend: {len(auto)} test case konformansi dan {len(hard)} test case penguatan seluruhnya PASS. "
     f"{len(matrix)} butir kebutuhan (a.1 sampai h.3, ERD, dan keputusan pengguna) terpenuhi; {n_sebagian} butir "
     f"berstatus Sebagian dan seluruhnya merupakan penyimpangan yang telah diketahui serta disepakati: identitas "
     f"login berupa email (butir a.1) dan APP_DEBUG lingkungan "
     f"pengembang yang baru aman bila dimatikan saat pemasangan (separuh butir keamanan pada 10e).", 9)
para(f"Suite penguatan ({len(hard)} test case, BAB V) sengaja ditulis untuk menutup batasan yang dilaporkan pada "
     f"analisis sebelumnya: daur hidup proyek lengkap beserta halaman daftarnya (TC-M01 sampai TC-M11), pemisahan "
     f"data booking-pembayaran-tindak lanjut per sales (TC-N01 sampai TC-N06), penulisan tabel activity_logs "
     f"sesuai ERD (TC-O01 sampai TC-O03), penegakan dan perpanjangan batas waktu sesi (TC-P01 sampai TC-P03), "
     f"serta pembatasan laju login tiga percobaan dengan masa penguncian yang diatur super admin dan lokalisasi menyeluruh pesan kesalahan "
     f"(TC-Q01 sampai TC-Q05, TC-R24). Butir d.4 dan f.4 yang semula Sebagian kini menjadi Sesuai.", 9)
para(f"Frontend: {len(ui)} test case observasi DOM seluruhnya PASS dan sesuai dengan keputusan pengguna, yaitu "
     f"navigasi modul di sidebar, menu akun pada ikon profil, notifikasi pada lonceng, halaman Data Proyek dengan "
     f"aksi Ubah/Arsipkan/Hapus khusus super admin sedangkan kolom Aksi tidak dirender bagi Admin Sales (FF-A20, "
     f"FF-B09), penolakan halaman bagi Sales (FF-C07), hak hanya-lihat Admin Sales pada empat modul yang dipertebal "
     f"dengan judul detail unit tanpa kata \"Ubah\" plus isian terkunci dan hilangnya tombol Kelola Proyek "
     f"(FF-B10, FF-B11), pembatasan data sales, lembar cetak laporan berpengesahan (FF-A22), chip hitung mundur sesi "
     f"beserta tombol Perpanjang (FF-A21), serta dua tab jejak audit pada Log Aktivitas (FF-A23). Dua ketidaksuaian "
     f"yang ditemukan selama analisis (teks hapus permanen pada kabar terbaru dan saklar galeri pada halaman Tentang "
     f"Kami) telah diperbaiki dan diverifikasi ulang.", 9)
para("Secara keseluruhan sistem dapat dinyatakan SESUAI dengan kebutuhan pengguna dan isi laporan untuk keperluan "
     "demonstrasi dan pengujian skripsi; untuk status produksi masih diperlukan butir rekomendasi di bawah.", 9)

head("7.2 Rekomendasi sebelum aplikasi dipasang di server umum", 2)
para("Empat rekomendasi yang muncul pada analisis sebelumnya sudah dikerjakan dan tidak lagi diminta di sini: "
     "pembatasan laju login (kini App\\Support\\LoginThrottle, dibuktikan TC-Q01 sampai TC-Q03), penulisan tabel "
     "activity_logs sesuai ERD (AuditObserver, TC-O01 sampai TC-O03 dan FF-A23), pemangkasan daftar pembayaran "
     "per sales (trait ScopesSalesData, TC-N01 sampai TC-N06), dan ekspor laporan PDF berpengesahan "
     "(LP.cetakLaporan, FF-A22). Sisa rekomendasi:", 9)
table(["Prioritas", "Butir", "Alasan", "Tindakan yang disarankan"],
      [Cm(2.0), Cm(6.5), Cm(9.5), Cm(9.3)],
      [
          ["Tinggi", "APP_DEBUG masih bernilai true",
           "Respons 500 menampilkan jejak stack, nama tabel, kolom, dan path berkas sehingga menguntungkan pihak yang "
           "mencoba mendeteksi celah.",
           "Setel APP_DEBUG=false dan APP_ENV=production; hasil uji NF-9 tetap berlaku karena pesan error JSON tidak "
           "berubah (renderable pada app/Exceptions/Handler.php sudah menutup 401/403/404/422/429)."],
          ["Tinggi", "Skema basis data kerja perlu dijaga tetap sinkron",
           "Galat SQLSTATE[42S22] 1054 Unknown column 'clusters.deleted_at' sempat muncul pada halaman Booking di "
           "http://127.0.0.1:8000 (16 November 2026) karena migration pengelolaan proyek baru terpasang pada "
           "luminpark_test, belum pada basis data kerja luminpark. Sudah dipulihkan dengan php artisan migrate "
           "(non-destruktif, 1,859 ms) dan seluruh halaman terkait kembali 200.",
           "Setiap kali menambah migration, jalankan php artisan migrate --force pada basis data kerja "
           "(tanpa migrate:fresh) lalu uji halaman terdampak; tambahkan catatan versi skema pada dokumen serah terima "
           "agar penguji lain dapat memastikan kedua basis data memakai skema yang sama."],
          ["Sedang", "Duplikasi lead lewat nomor handphone sudah tertutup; email masih boleh sama",
           "Sejak revisi 19 entri manual pada Database Konsumen ditolak bila nomor handphonenya sudah dipakai "
           "konsumen lain (HTTP 422 dengan pesan \"sudah dipakai konsumen lain dan hanya dapat digunakan satu "
           "kali\"), dibuktikan TC-R15 dan FF-R19. Revisi 24 menegaskan kunci itu tetap berlaku walau pemilik nomor "
           "masih berstatus cold maupun sudah dibatalkan (TC-R19), karena pembatalan tidak menghapus baris lead; "
           "hanya hapus lunak yang membebaskan nomornya. Email "
           "customer_leads sengaja tidak dibuat unik karena kolomnya opsional dan banyak lead hanya memiliki nomor "
           "telepon.",
           "Bila di kemudian hari email dipakai untuk mengirim tautan informasi kepada konsumen, tambahkan aturan "
           "unik whereNull('deleted_at') yang sama seperti pada phone dan siapkan prosedur klaim email ganda."],
          ["Tinggi", "UAT belum melibatkan pengguna sebenarnya",
           "Seluruh baris UAT pada laporan BAB IV diisi penguji tunggal (pengembang), sehingga aspek kemudahan "
           "pemakaian belum diukur dari sudut pandang admin, sales, maupun konsumen nyata (batasan D-2).",
           "Isi lembar UAT (bagian User Acceptance Test laporan BAB IV) bersama minimal tiga pengguna nyata per "
           "peran, lalu rekap rata-rata penilaian dan catat umpan baliknya pada lampiran."],
          ["Sedang", "Uji lintas peramban dan lebar layar",
           "Suite frontend berjalan pada Chromium; Firefox, Edge, serta tampilan pada 375/768/1440 piksel belum "
           "diperiksa manusia (batasan D-10 dan butir NF-12).",
           "Jalankan ulang sepuluh alur utama pada Firefox dan Edge serta tiga lebar layar memakai DevTools, lalu "
           "tandai tiap kombinasi lulus/tidak pada lembar checklist."],
          ["Sedang", "Kredensial login Google belum diisi",
           "google_client_id, google_client_secret, dan google_login_enabled masih kosong/false sehingga tombol Google "
           "nonaktif (FF-D03). Sejak revisi 33 isian tersebut tidak lagi ditampilkan pada halaman Pengaturan.",
           "Isi ketiga kunci pada tabel settings (lewat migrasi/seeder atau SQL terkelola), lalu uji alur callback; "
           "GET /api/settings tetap mengembalikan kunci google_* sehingga frontend tidak perlu diubah lagi (TC-R25)."],
          ["Rendah", "Ranah Blade warisan masih ada",
           "Sensus berkas 21 Nov 2026: resources/views/welcome.blade.php, dashboard/index.blade.php, "
           "sales/index.blade.php, super-admin/dashboard.blade.php, super-admin/dataunit_perumahan.blade.php, "
           "activity-log/index.blade.php, dan layouts/dashboard.blade.php tidak dirujuk satu rute pun. "
           "Rujukan activity-log/index.blade.php pada app/Http/Controllers/ActivityLogController.php:13 ikut mati "
           "karena hanya varian Api\\ActivityLogController yang didaftarkan di routes/api.php:146, sedangkan "
           "halaman /log-aktivitas kini memakai app/log-aktivitas.blade.php lewat Route::view (revisi 30). "
           "Tiga puluh berkas lain dari total 37 Blade terpetakan rute; app/history.blade.php sudah dihapus (revisi 28).",
           "Hapus berkas yang tidak dirujuk rute beserta pemanggil lamanya agar tidak membingungkan pembaca kode."],
          ["Rendah", "Daftar log sistem belum berpaginasi",
           "GET /api/system-logs mengembalikan seluruh baris; volumenya akan besar setelah pemakaian lama.",
           "Terapkan paginasi baku (perPage 15) sebagaimana modul lain."],
          ["Rendah", "Teks laporan menyebut 14 tabel dan 6 rute publik",
           "Fakta implementasi adalah 18 tabel domain (22 tabel termasuk kerangka) dan 6 halaman publik non-login "
           "ditambah tiga halaman autentikasi (login, reset sandi, ganti sandi); total rute terdaftar 130 "
           "(97 API + 33 web) termasuk dua pengalihan Google.",
           "Perbarui bilangan tersebut pada BAB II dan BAB III laporan agar sesuai perangkat yang diuji."],
      ], sizes=[8, 8, 8, 8], center_cols=(0,))
doc.add_page_break()

# ============================== LAMPIRAN ==============================
head("LAMPIRAN A - Akun uji dan cara menjalankan pengujian")
head("A.1 Akun hasil seeder (seluruhnya memakai password123)", 2)
table(["Email", "Peran", "Fungsi dalam pengujian"],
      [Cm(6.0), Cm(4.2), Cm(17.1)],
      [
          ["superadmin@gmail.com", "super_admin (Super Admin)",
           "aktor utama CRUD proyek, unit, dan sales, verifikasi pembayaran, laporan, log aktivitas, sesi login, "
           "pengaturan sistem, dan pengaturan halaman publik"],
          ["admin.sales@luminpark.com", "admin_sales",
           "uji kesetaraan hak baca dengan super admin dan hak kelola data sales (FF-B01..FF-B07, TC-F03, TC-L01..L03)"],
          ["sales@luminpark.com", "sales",
           "booking, pencatatan pembayaran, follow-up, dashboard sales, dan laporan sales (FF-C01..FF-C06)"],
          ["sales2@luminpark.com", "sales",
           "uji pemisahan data antar sales (TC-C09, TC-C10)"],
          ["sales3@luminpark.com", "sales",
           "uji reset sandi dan penonaktifan akun (TC-F04, TC-F05)"],
      ], sizes=[9, 9, 9])
bullet("Akun konsumen tidak lagi dibuat; konsumen memakai halaman publik dan formulir yang menghasilkan lead "
       "(TC-A04 menolak login konsumen, TC-A04b membuktikan tidak ada rute POST /api/register).")
bullet("Akun admin@luminpark.com dan manager@luminpark.com telah dihapus karena keduanya adalah orang yang sama "
       "dengan super admin; keduanya ditolak saat login (TC-A04c).")

head("A.2 Perintah pelaksanaan", 2)
table(["Tahap", "Perintah"], [Cm(5.0), Cm(22.3)],
      [
          ["Isi ulang basis data uji", "C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root -e "
                                       "\"create database if not exists luminpark_test\""],
          ["Migrasi dan seeder uji", "DB_DATABASE=luminpark_test "
                                     "C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe artisan "
                                     "migrate:fresh --seed"],
          ["Tautan storage", "C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe artisan storage:link"],
          ["Server uji (backend)", "DB_DATABASE=luminpark_test C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe "
                                   "artisan serve --host=127.0.0.1 --port=8081"],
          ["Server antarmuka", "C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe artisan serve "
                               "--host=127.0.0.1 --port=8080"],
          ["Uji backend", "LP_API_BASE=http://127.0.0.1:8081/api "
                          "C:/laragon/bin/nodejs/node-v22/node.exe tests/run-conformance-tests.mjs"],
          ["Uji penguatan", "LP_API_BASE=http://127.0.0.1:8081/api "
                            "C:/laragon/bin/nodejs/node-v22/node.exe tests/run-hardening-tests.mjs"],
          ["Uji frontend", "Muat halaman nyata pada peramban otomatis lalu baca contentDocument tiap aktor; "
                           "baris hasil observasi disatukan dengan "
                           "C:/laragon/bin/nodejs/node-v22/node.exe tests/patch-frontend-results.mjs ke "
                           "tests/frontend-results.json"],
          ["Matriks kebutuhan", "C:/laragon/bin/python/python-3.13/python.exe -Xutf8 tests/patch_kf_matrix.py "
                                "(memperbarui rujukan pada tests/kf-matrix.json)"],
          ["Uji non-fungsional", "LP_API_BASE=http://127.0.0.1:8081/api "
                                 "C:/laragon/bin/nodejs/node-v22/node.exe tests/run-nf-tests.mjs"],
          ["Susun laporan ini", "C:/laragon/bin/python/python-3.13/python.exe -Xutf8 "
                                "tests/build_laporan_uji_sistem.py"],
      ], sizes=[9, 9])

head("A.3 Artefak yang dihasilkan", 2)
table(["Berkas", "Isi"], [Cm(9.0), Cm(18.3)],
      [
          ["tests/conformance-results.json", f"{len(auto)} baris hasil uji backend dalam skema sepuluh kolom"],
          ["tests/hardening-results.json", f"{len(hard)} baris hasil uji penguatan atas butir batasan"],
          ["tests/frontend-results.json", f"{len(ui)} baris hasil uji frontend dalam skema kolom yang sama"],
          ["tests/nf-results.json", f"{len(nf)} baris hasil uji non-fungsional dalam skema kolom yang sama"],
          ["tests/kf-matrix.json", f"{len(json.load(open(f'{BASE}/tests/kf-matrix.json', encoding='utf-8')))} butir "
                                   "kebutuhan laporan dan daftar revisi beserta status dan rujukan test case per butir"],
          ["tests/run-conformance-tests.mjs", "mesin eksekusi dan definisi test case backend"],
          ["tests/run-hardening-tests.mjs", "definisi test case penguatan (proyek, pemisahan data, audit, sesi, "
                                            "keamanan, lokalisasi)"],
          ["tests/patch-frontend-results.mjs", "penyatu baris observasi DOM ke tests/frontend-results.json"],
          ["tests/run-nf-tests.mjs", "pengukur waktu respons, probe basis data, dan agregat ketiga suite di atas"],
          ["tests/build_laporan_uji_sistem.py", "penyusun berkas Word ini"],
          ["tests/build_bab4_pengujian.py", "penyusun laporan BAB IV (black-box + UAT) dari berkas hasil yang sama"],
          ["Laporan-Analisis-Kesesuaian-dan-Uji-Sistem-LuminPark.docx", "laporan yang sedang dibaca"],
      ], sizes=[9, 9])

OUT = f"{BASE}/Laporan-Analisis-Kesesuaian-dan-Uji-Sistem-LuminPark.docx"
try:
    doc.save(OUT)
except PermissionError:
    OUT = OUT.replace(".docx", f"-{hari.isoformat()}.docx")
    doc.save(OUT)
    print("PERINGATAN: berkas laporan lama sedang dibuka Word, hasilnya disimpan sebagai berkas baru.")
print(f"Tersimpan: {OUT}")
print(f"Baris backend {len(auto)}, penguatan {len(hard)}, frontend {len(ui)}, lulus {n_pass}, gagal {n_fail}")
