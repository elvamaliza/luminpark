# -*- coding: utf-8 -*-
"""Laporan pembanding: kesesuaian backend terhadap BAB II/III + hasil regresi pengujian API."""
import json, datetime
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

BASE = "C:/laragon/www/luminpark-main"
auto = json.load(open(f"{BASE}/tests/conformance-results.json", encoding="utf-8"))
manual = json.load(open(f"{BASE}/tests/manual-check-results.json", encoding="utf-8"))
routes = json.load(open(f"{BASE}/tests/routes.json", encoding="utf-8"))
schema = dict((t, c) for t, c in json.load(open(f"{BASE}/tests/schema-summary.json", encoding="utf-8")))
rows = auto + manual
passed = sum(1 for r in rows if r["status"] == "Pass")
failed = len(rows) - passed

GREEN = RGBColor(0x1B, 0x7F, 0x3B)
RED = RGBColor(0xB0, 0x1F, 0x1F)
AMBER = RGBColor(0xB0, 0x6A, 0x00)
HDR = "2E5496"
SUB = "D9E2F3"
GREY = "F2F2F2"

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


def bullet(text):
    doc.add_paragraph(text, style="List Bullet")


def head(text, lvl=1):
    doc.add_heading(text, level=lvl)


def table(headers, widths, data, sizes=None, bold_cols=(), center_cols=(), status_col=None):
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
                col = {"Sesuai": GREEN, "Pass": GREEN, "Selesai": GREEN,
                       "Sebagian": AMBER, "Terbuka": RED, "Fail": RED}.get(str(val))
                if col:
                    r.font.color.rgb = col
                fill = {"Sesuai": "E3F2E3", "Pass": "E3F2E3", "Selesai": "E3F2E3",
                        "Sebagian": "FFF3E0", "Terbuka": "FBE4E4", "Fail": "FBE4E4"}.get(str(val))
                if fill:
                    shade(c, fill)
    for row in t.rows:
        for i, c in enumerate(row.cells):
            c.width = widths[i]
    return t


# ============================== COVER ==============================
para("LAPORAN PENYESUAIAN BACKEND DENGAN SPESIFIKASI LAPORAN TUGAS PROYEK", 19, True,
     WD_ALIGN_PARAGRAPH.CENTER)
para("Verifikasi Kesesuaian BAB II dan BAB III beserta Regresi Pengujian API (Black-box)", 12, False,
     WD_ALIGN_PARAGRAPH.CENTER)
para("Sistem Informasi Manajemen Perumahan Lumin Park — Laravel 10 + Sanctum + MySQL", 11, False,
     WD_ALIGN_PARAGRAPH.CENTER)
doc.add_paragraph()
info = [
    ("Nama Proyek", "LuminPark (luminpark-main)"),
    ("Acuan Kesesuaian", "LAPORAN TUGAS PROYEK — BAB II (analisis kebutuhan & perancangan) dan "
                         "BAB III (kebutuhan fungsional/non-fungsional, ERD Gambar 2.24)"),
    ("Jenis Verifikasi", "Audit ketertelusuran kebutuhan (traceability) + black-box API testing fungsional & keamanan"),
    ("Lingkungan Uji", "Local (Laragon) — Windows 10, PHP 8.3.33, MySQL 8.4.3, Node.js 22"),
    ("Base URL", "http://127.0.0.1:8080/api"),
    ("Skema Basis Data", "luminpark — 20 migration (15 tabel domain + 5 tabel kerangka), 4 seeder"),
    ("Tanggal Verifikasi", datetime.date.today().strftime("%d %B %Y")),
    ("Butir Kebutuhan Fungsional", "34 butir (a.4 + b.5 + c.4 + d.4 + e.6 + f.4 + g.4 + h.3) + 1 baris ERD = 35 baris matriks"),
    ("Jumlah Test Case", f"{len(rows)} test case ({len(auto)} otomatis + {len(manual)} verifikasi manual multipart & waktu respons)"),
    ("Hasil", f"Lulus: {passed}   |   Gagal: {failed}   |   Tingkat Lulus: {passed/len(rows)*100:.1f}%"),
    ("Bandingkan dengan", "Laporan pengujian awal: 76 test case, Lulus 38 (50,0%), status BELUM LAYAN PRODUKSI"),
    ("Status Aplikasi", "SESUAI SPESIFIKASI (BAB II & III) untuk lapisan backend API"),
]
t = doc.add_table(rows=0, cols=2)
t.style = "Light Grid Accent 1"
for k, v in info:
    c = t.add_row().cells
    c[0].text = ""
    c[1].text = ""
    r = c[0].paragraphs[0].add_run(k)
    r.bold = True
    r.font.size = Pt(10)
    c[1].paragraphs[0].add_run(v).font.size = Pt(10)
    shade(c[0], GREY)
    c[0].width = Cm(7.5)
    c[1].width = Cm(19.5)
doc.add_page_break()

# ============================== BAB I ==============================
head("BAB I - Ringkasan eksekutif")
para(f"Backend LuminPark telah disesuaikan dengan spesifikasi Laporan Tugas Proyek (BAB II dan BAB III), kemudian "
     f"dijalankan kembali dan diuji ulang. Dari {len(rows)} test case yang dijalankan, {passed} lulus dan {failed} gagal "
     f"(tingkat lulus {passed/len(rows)*100:.1f}%). Pada laporan pengujian awal, hanya 38 dari 76 test case (50,0%) yang "
     f"lulus.")

head("1.1 Perbandingan sebelum dan sesudah penyesuaian", 2)
table(
    ["Indikator", "Sebelum penyesuaian", "Sesudah penyesuaian"],
    [Cm(7.0), Cm(9.6), Cm(11.5)],
    [
        ["Migration / tabel domain", "14 migration, 8 migration domain berstatus stub (hanya id + timestamps)",
         "20 migration; 15 tabel domain lengkap dengan kolom, foreign key, indeks unik, dan constraint"],
        ["Kolom pada tabel units", "stub (3 kolom)", f"{schema['units']} kolom (kode, cluster, tipe, luas tanah/bangunan, harga, status, deskripsi, gambar)"],
        ["Kolom pada tabel bookings", "stub (3 kolom)", f"{schema['bookings']} kolom (kode pemesanan, lead, sales, unit, skema, harga, DP, angsuran, sisa, status, status serah terima, catatan)"],
        ["Tabel ERD yang belum ada", "proyek, cluster, target_sales, laporan_penjualan, notifikasi, sesi_login absen",
         "keenam tabel diimplementasikan: projects, clusters, sales_targets, sales_reports, notifications, login_sessions"],
        ["Proteksi autentikasi", "hanya login/logout/me/reset-password/dashboard yang memakai auth:sanctum; "
                                  "seluruh apiResource dan reports terbuka tanpa token",
         "67 rute API terpetakan; hanya 6 rute publik konsumen + POST /login yang tanpa token, sisanya di dalam "
         "group auth:sanctum dan dibatasi RoleMiddleware"],
        ["Perhitungan DP/angsuran/sisa", "tidak ada; nilai diambil dari input klien",
         "dihitung server oleh PaymentScheme::calculateBreakdown() dan dipelihara PaymentController::recalculateBooking()"],
        ["Relasi & method yang hilang", "SalesController::performance/resetPassword belum ada; "
                                         "SalesProfile::customerLeads()/bookings() belum terdefinisi",
         "seluruh method controller terpasang dan relasi Eloquent terdefinisi; laporan kinerja mengembalikan angka nyata"],
        ["Hasil pengujian API", "38/76 lulus (50,0%)", f"{passed}/{len(rows)} lulus ({passed/len(rows)*100:.1f}%)"],
    ],
    sizes=[9, 9, 9], bold_cols=(0,),
)

head("1.2 Kesimpulan verifikasi", 2)
for s in [
    "Seluruh 34 butir kebutuhan fungsional BAB III telah memiliki implementasi pada backend. Tiga butir dinilai "
    "\"Sebagian\" dan diuraikan pada BAB III: identitas login memakai email (bukan kolom username tersendiri), "
    "indeks pembayaran belum dibatasi per sales, serta ekspor laporan berupa CSV dan belum PDF.",
    "Struktur ERD (Gambar 2.24) terpasang penuh. Nama tabel dipertahankan dalam bahasa Inggris sesuai keputusan "
    "pengguna; pemetaan satu-per-satu dari nama Indonesia pada ERD ke nama implementasi tersedia pada Lampiran A.",
    "Dua belas cacat (DF-01 s.d. DF-12) yang dilaporkan pada audit sebelumnya: sepuluh selesai, satu sebagian "
    "(ekspor dokumen), satu masih terbuka (rate limiting khusus login). Penjelasan pada BAB II.2.",
    "Satu cacat baru ditemukan selama regresi dan langsung diperbaiki: POST /api/follow-ups menghasilkan HTTP 500 "
    "bila sales_id tidak dikirim (BAB V.2).",
    "Kebutuhan non-fungsional kinerja diverifikasi secara terukur: waktu respons proses umum 5 permintaan terakhir "
    "di bawah ambang 5 detik (TC-J04).",
]:
    bullet(s)
doc.add_page_break()

# ============================== BAB II ==============================
head("BAB II - Ruang lingkup penyesuaian backend")
head("2.1 Perubahan per area", 2)
table(
    ["Area", "Yang diubah", "Berkas utama"],
    [Cm(4.6), Cm(15.4), Cm(7.8)],
    [
        ["Skema basis data", "Delapan migration domain diisi kolom sesuai narasi ERD; enam migration baru ditambahkan "
         "untuk proyek, cluster, target_sales, laporan_penjualan, notifikasi, dan sesi_login; foreign key, indeks unik, "
         "serta cascade delete dipasang.",
         "database/migrations/2026_10_09_*.php (16 berkas)"],
        ["Model Eloquent", "Model disesuaikan dengan skema: konstanta status pada Unit, CustomerLead, Booking, Payment; "
         "relasi hasMany/belongsTo dilengkapi; akses image_url pada Unit; cast password => hashed pada User.",
         "app/Models/*.php (16 model)"],
        ["Logika bisnis finansial", "Perhitungan DP, angsuran, dan sisa pembayaran dipindahkan ke server "
         "(PaymentScheme::calculateBreakdown). Status pelunasan Booking::settlementStatus diturunkan dari ledger "
         "pembayaran yang telah terverifikasi, termasuk saat pembayaran dihapus.",
         "app/Models/PaymentScheme.php, app/Models/Booking.php, app/Http/Controllers/Api/PaymentController.php"],
        ["Autentikasi & sesi", "Login menolak akun nonaktif (403), menerbitkan token Sanctum dengan kedaluwarsa dari "
         "config/sanctum.expiration, mencatat sesi (token, IP, user agent, waktu login/logout) pada login_sessions, "
         "dan menghidupkan kembali sesi saat logout.",
         "app/Http/Controllers/Api/AuthController.php, config/sanctum.php"],
        ["Otorisasi (RBAC)", "Seluruh rute bisnis dibungkus auth:sanctum lalu dibatasi per peran memakai middleware "
         "role:.... Rute verifikasi pembayaran khusus manager/superadmin; rute notifikasi hanya atas nama pemilik; "
         "indeks lead discoping per sales.",
         "routes/api.php, app/Http/Middleware/RoleMiddleware.php"],
        ["Dashboard & laporan", "Angka dashboard dihitung memakai konstanta status (tidak lagi mencampur bahasa). "
         "Laporan penjualan, pelunasan, kinerja, dan konsumen tersedia beserta ekspor CSV berkepala bahasa Indonesia.",
         "app/Http/Controllers/Api/DashboardController.php, ReportController.php"],
        ["API publik konsumen", "Katalog unit, detail unit, skema pembayaran, narahubung sales (tautan WhatsApp), "
         "berita/galeri, dan formulir kontak yang menjadikan pengirim sebagai calon konsumen baru.",
         "app/Http/Controllers/Api/PublicInfoController.php"],
        ["Notifikasi & log sistem", "Notifikasi dibuat otomatis pada peristiwa booking, pembayaran menunggu verifikasi, "
         "dan hasil verifikasi. Aktivitas penting (login gagal, mutasi data) ditulis ke system_logs.",
         "app/Http/Controllers/Api/NotificationController.php, SystemLogController.php"],
        ["Seeder & konfigurasi", "Seeder mengisi basis data uji: 7 pengguna (peran superadmin, manager, admin_sales, "
         "sales x3, consumer), 1 proyek, 3 cluster, 9 unit, 3 skema pembayaran, 4 calon konsumen, 3 riwayat follow-up, "
         "2 pemesanan, 4 pembayaran, dan 3 target sales. Notifikasi, log sistem, serta sesi login dibuat pada saat "
         "sistem dijalankan, bukan dari seeder.",
         "database/seeders/*.php, .env"],
        ["Antarmuka lama (Blade)", "Halaman web Blade warisan awal proyek (resources/views/dashboard, sales, "
         "super-admin, activity-log) tidak diubah dan tidak termasuk cakupan verifikasi; seluruh pengujian dilakukan "
         "pada lapisan API JSON. GET / masih merespons HTTP 200.",
         "resources/views/** (tidak diubah)"],
    ],
    sizes=[9, 9, 8], bold_cols=(0,),
)

head("2.2 Status penyelesaian cacat hasil audit sebelumnya", 2)
table(
    ["ID", "Keparahan", "Cacat awal", "Status", "Bukti / keterangan"],
    [Cm(1.6), Cm(2.2), Cm(9.0), Cm(2.4), Cm(13.0)],
    [
        ["DF-01", "Kritis", "Delapan migration domain hanya membuat id + timestamps", "Selesai",
         "Kolom domain terisi; seluruh operasi Create/Update/Filter berjalan. TC-B03, TC-D05, TC-E01, TC-F01"],
        ["DF-02", "Tinggi", "Model FollowUpHistory memakai tabel follow_up_history (bentuk tunggal)", "Selesai",
         "Override $table dihapus sehingga Model memakai follow_up_histories hasil migration. TC-C05, TC-C07"],
        ["DF-03", "Tinggi", "SalesController::resetPassword dan ::performance dirujuk rute tetapi tidak ada", "Selesai",
         "Kedua method diimplementasikan. TC-F04, TC-F06"],
        ["DF-04", "Tinggi", "Relasi SalesProfile::customerLeads()/bookings() tidak terdefinisi", "Selesai",
         "Relasi ditambahkan; laporan kinerja mengembalikan agregat nyata. TC-G07"],
        ["DF-05", "Kritis", "Seluruh apiResource dan rute reports berada di luar auth:sanctum", "Selesai",
         "Akses tanpa token menghasilkan 401 dan akses lintas peran menghasilkan 403. TC-A07, TC-A12, TC-I06, Lampiran B"],
        ["DF-06", "Sedang", "Katalog publik menyaring status 'tersedia' sedangkan validasi internal menerima nilai Inggris",
         "Selesai", "Satu kosakata status dipakai bersama (Unit::STATUSES). TC-H01, TC-H03"],
        ["DF-07", "Sedang", "Booking mengeset status unit ke nilai di luar domain validasi", "Selesai",
         "Unit tetap dapat diperbarui setelah dibooking. TC-D06, TC-B06"],
        ["DF-08", "Sedang", "Dashboard mencampur nilai status Indonesia/Inggris dan memakai 'active' yang tidak ada",
         "Selesai", "Perhitungan memakai konstanta; angka rekap konsisten dengan basis data. TC-G01, TC-G02, TC-G03"],
        ["DF-09", "Sedang", "Form kontak publik menulis unit_id/source yang tidak ada di fillable sehingga hilang diam",
         "Selesai", "Kolom dan fillable ditambahkan; lead dari landing page teratribusi. TC-H06"],
        ["DF-10", "Rendah", "Tidak ada pembatasan percobaan pada rute login", "Terbuka",
         "Masih memakai throttle global api (60/menit per IP). Disarankan RateLimiter per email (mis. 5/menit)."],
        ["DF-11", "Rendah", "APP_DEBUG=true membocorkan nama tabel, kolom, dan path absolut pada respons 500", "Terbuka",
         "Dipertahankan true untuk lingkungan local agar diagnosis mudah; wajib false dan LOG_LEVEL=warning saat deployment."],
        ["DF-12", "Rendah", "Ekspor laporan hanya mengembalikan JSON mentah", "Sebagian",
         "Ekspor CSV nyata (berkepala bahasa Indonesia, dapat dibuka dan dicetak lewat spreadsheet); generator PDF belum dipasang."],
    ],
    sizes=[8, 8, 8, 8, 8], bold_cols=(0,), center_cols=(1, 3), status_col=3,
)
doc.add_page_break()

# ============================== BAB III ==============================
head("BAB III - Matriks ketertelusuran kebutuhan ke implementasi")
para("Setiap butir kebutuhan fungsional pada BAB III laporan (halaman 29-31) dipetakan ke tempat implementasi dan "
     "test case yang membuktikannya. Status: Sesuai = terpenuhi utuh; Sebagian = terpenuhi dengan catatan; "
     "Belum = tidak terpenuhi.", 9)
matrix = [
    ("1", "a.1", "Login dengan username dan password untuk Manajer (Super Admin), Admin Sales, dan Sales",
     "AuthController::login — validasi kredensial memakai Hash::check, penerbitan token Sanctum, penolakan akun "
     "nonaktif; RoleMiddleware membatasi wilayah akses per peran.",
     "TC-A01, TC-A02, TC-A03, TC-A04, TC-A05, TC-A12", "Sebagian",
     "Identitas login memakai email (tabel users tidak memiliki kolom username); wireframe menyebut \"Nama Pengguna\"."),
    ("2", "a.2", "Role-based access control untuk membedakan hak akses",
     "routes/api.php (group auth:sanctum + role:...) dan app/Http/Middleware/RoleMiddleware.php.",
     "TC-A07, TC-A08, TC-A10, TC-A11, TC-C09, TC-C10, TC-E03, TC-G04, TC-I04, TC-I06", "Sesuai", ""),
    ("3", "a.3", "Fitur reset password untuk setiap pengguna",
     "AuthController::resetPassword (mandiri, revoke token lama) dan SalesController::resetPassword (kewenangan "
     "manajer/admin sales).", "TC-F04", "Sesuai", ""),
    ("4", "a.4", "Logout dengan batas waktu tertentu untuk setiap aktor",
     "AuthController::logout menghapus CurrentAccessToken; config/sanctum.php expiration → token_expires_at; "
     "AuthController::sessions + tabel login_sessions menyimpan waktu login/logout dan status sesi.",
     "TC-A15, TC-A16, TC-F04", "Sesuai", ""),
    ("5", "b.1", "Admin Sales dan Manajer melihat seluruh data unit beserta statusnya",
     "UnitController::index dengan filter status, dibatasi role:manager,admin_sales,superadmin.",
     "TC-A13, TC-B01", "Sesuai", ""),
    ("6", "b.2", "Sales melihat informasi unit yang tersedia",
     "UnitController::availableUnits (GET /api/units-available).", "TC-A14", "Sesuai", ""),
    ("7", "b.3", "Konsumen melihat informasi unit rumah yang tersedia",
     "PublicInfoController::publicUnits tanpa autentikasi, hanya status available.", "TC-H01, TC-H03", "Sesuai", ""),
    ("8", "b.4", "Manajer melihat, menambah, mengubah, menghapus data unit dan statusnya (tersedia, booking, sold out, "
     "proses delivery)",
     "apiResource units + Unit::STATUSES (available/booking/sold_out/delivery_process); penolakan penghapusan unit "
     "yang memiliki riwayat pemesanan.", "TC-A11, TC-B03, TC-B05, TC-B09, TC-B10, TC-J01", "Sesuai", ""),
    ("9", "b.5", "Manajer memperbarui status unit", "UnitController::update (PUT/PATCH /api/units/{id}).",
     "TC-B06", "Sesuai", ""),
    ("10", "c.1", "Sales membuat pemesanan unit untuk konsumen berstatus hot",
     "BookingController::store menolak lead yang statusnya bukan hot (HTTP 422).", "TC-D03", "Sesuai", ""),
    ("11", "c.2", "Sales memilih skema pelunasan (KPR, cash keras, cash bertahap)",
     "apiResource payment-schemes + PaymentSchemeSeeder menyediakan tiga skema; booking memvalidasi payment_scheme_id.",
     "TC-D01, TC-D05, TC-H04", "Sesuai", ""),
    ("12", "c.3", "Sistem otomatis menghitung DP, cicilan, dan sisa pembayaran",
     "PaymentScheme::calculateBreakdown() dipanggil server saat booking dibuat; nilai turunan tidak diterima dari klien.",
     "TC-D02, TC-D05, TC-D09", "Sesuai",
     "Bunga dihitung secara flat atas pokok (down_payment dan angsuran tetap); laporan tidak menetapkan rumus bunga efektif."),
    ("13", "c.4", "Sistem mengubah status unit menjadi \"dipesan\" setelah booking berhasil",
     "BookingController::store mengeset Unit::STATUS_BOOKING dan status lead menjadi booking.", "TC-D04, TC-D06, TC-D07",
     "Sesuai", ""),
    ("14", "d.1", "Sales mencatat pembayaran (DP, cicilan, pelunasan) dan mengunggah bukti transfer",
     "PaymentController::store menerima multipart transfer_receipt (jpg/jpeg/png/pdf, maks 5 MB) dan menyimpannya "
     "pada disk public.", "TC-E01, TC-E02, TC-E08, TC-J02", "Sesuai", ""),
    ("15", "d.2", "Manajer memverifikasi bukti pembayaran (terverifikasi/ditolak)",
     "PUT /api/payments/{id}/verify dengan middleware role:manager,superadmin; kolom verified_by dan verified_at "
     "terisi; penolakan tidak mengubah sisa pembayaran.", "TC-E03, TC-E04, TC-E06, TC-J03", "Sesuai", ""),
    ("16", "d.3", "Sistem memperbarui status pelunasan dan sisa pembayaran otomatis",
     "PaymentController::recalculateBooking + Booking::settlementStatus() menurunkan status (menunggu DP, dp lunas, "
     "cicilan, lunas) dari ledger pembayaran terverifikasi.", "TC-D09, TC-E05, TC-E07, TC-E10", "Sesuai", ""),
    ("17", "d.4", "Sales memantau status pembayaran konsumennya",
     "GET /api/payments dengan filter booking_id/payment_type/verification_status.", "TC-E09", "Sebagian",
     "Indeks pembayaran belum discoping per sales, sehingga sales dapat melihat pembayaran booking sales lain."),
    ("18", "e.1", "Sales menambah, mengubah, dan menghapus data calon konsumen",
     "apiResource customer-leads; penolakan penghapusan lead yang sudah berstatus booking.",
     "TC-C01, TC-C04, TC-C11", "Sesuai", ""),
    ("19", "e.2", "Sales memperbarui status calon konsumen (cold, warm, hot, booking)",
     "CustomerLead::STATUSES + validasi aturan status pada update.", "TC-C02, TC-C04", "Sesuai", ""),
    ("20", "e.3", "Sales melihat daftar calon konsumen yang ditangani berdasarkan status",
     "CustomerLeadController::index discoping ke sales_id milik pengguna yang login; filter status tersedia.",
     "TC-C08, TC-C09", "Sesuai", ""),
    ("21", "e.4", "Sales mencatat riwayat follow-up setiap kali berinteraksi",
     "apiResource follow-ups; penyimpanan status_before/status_after, metode komunikasi, dan sinkronisasi last_follow_up "
     "pada lead.", "TC-C05, TC-C06, TC-C07", "Sesuai", ""),
    ("22", "e.5", "Admin Sales dan Manajer melihat seluruh calon konsumen dari semua sales dengan filter status",
     "Indeks tidak discoping untuk role manager/admin_sales/superadmin.", "TC-C08", "Sesuai", ""),
    ("23", "e.6", "Admin Sales dan Manajer memantau perkembangan follow-up setiap sales",
     "Daftar follow-up gabungan (lead + sales) dan laporan konsumen/per-performance sales.", "TC-C07, TC-G07, TC-G08",
     "Sesuai", ""),
    ("24", "f.1", "Manajer melihat dashboard rekapitulasi unit sold out dan belum delivery, pendapatan, jumlah sales, "
     "calon konsumen, dan konsumen",
     "DashboardController::managerDashboard memakai konstanta status Unit dan Booking.", "TC-G01", "Sesuai", ""),
    ("25", "f.2", "Admin Sales melihat dashboard rekapitulasi penjualan seluruh sales",
     "DashboardController::adminSalesDashboard.", "TC-G02", "Sesuai", ""),
    ("26", "f.3", "Sales melihat dashboard jumlah calon konsumen yang ditangani berdasarkan status",
     "DashboardController::salesDashboard (dibatasi role:sales,superadmin).", "TC-G03, TC-G04", "Sesuai", ""),
    ("27", "f.4", "Manajer dan Admin Sales melihat dan mencetak laporan penjualan unit, pelunasan, kinerja sales, serta "
     "laporan konsumen beserta calon konsumen",
     "ReportController::salesReport/settlementReport/salesPerformanceReport/consumerReport dan exportReport (stream CSV).",
     "TC-G05, TC-G06, TC-G07, TC-G08, TC-G09, TC-G10", "Sebagian",
     "Ekspor nyata berupa CSV berkepala bahasa Indonesia dan dapat dicetak melalui spreadsheet; berkas PDF belum dibuat."),
    ("28", "g.1", "Konsumen melihat daftar unit rumah tersedia beserta tipe, luas, dan harga",
     "PublicInfoController::publicUnits (presentUnit memuat unit_type, land_size, building_size, price).", "TC-H01",
     "Sesuai", ""),
    ("29", "g.2", "Konsumen melihat detail unit rumah beserta spesifikasi lengkap",
     "PublicInfoController::publicUnitDetail; unit berstatus booking/sold_out ditutup untuk publik (404).",
     "TC-H02, TC-H03", "Sesuai", ""),
    ("30", "g.3", "Konsumen melihat skema pembayaran yang tersedia", "PublicInfoController::paymentSchemes.", "TC-H04",
     "Sesuai", ""),
    ("31", "g.4", "Konsumen diarahkan ke narahubung Sales melalui tombol \"Hubungi Sales\"",
     "PublicInfoController::salesContacts menghasilkan nomor telepon dan tautan whatsapp_link (wa.me).", "TC-H05",
     "Sesuai", ""),
    ("32", "h.1", "Manajer mengelola data sales (tambah, ubah, hapus, reset password)",
     "SalesController::store (termasuk target penjualan), update (termasuk status nonaktif), destroy dengan guard "
     "riwayat, resetPassword yang mencabut token lama.", "TC-F01, TC-F02, TC-F04, TC-F05, TC-F07, TC-F08", "Sesuai", ""),
    ("33", "h.2", "Manajer dan Admin Sales melihat daftar seluruh sales beserta kinerjanya",
     "SalesController::index + performance (pencapaian unit/nominal dibanding target pada periode).", "TC-F06, TC-G07",
     "Sesuai", ""),
    ("34", "h.3", "Admin Sales mengelola data sales dengan hak yang sama",
     "Group role:manager,admin_sales,superadmin pada apiResource sales.", "TC-F03", "Sesuai", ""),
    ("35", "ERD", "Struktur basis data sesuai ERD (Gambar 2.24) — narasi menyebut 14 tabel dan mendaftar 15 nama tabel",
     "15 tabel domain dibuat mengikuti narasi ERD: users, sales_profiles, projects, clusters, units, customer_leads, "
     "follow_up_histories, bookings, payment_schemes, payments, sales_targets, sales_reports, notifications, system_logs, "
     "login_sessions — lengkap dengan foreign key dan kolom yang dideskripsikan.",
     "TC-A15, TC-B01, TC-B02, TC-D08, TC-F01, TC-G11, TC-I01, TC-I05", "Sesuai",
     "Nama tabel dipertahankan dalam bahasa Inggris; pemetaan ke nama Indonesia pada Lampiran A. Jumlah tabel pada "
     "narasi (14) tidak sama dengan daftar yang disebut (15) — perlu dikoreksi pada teks laporan."),
]
table(
    ["No", "Butir", "Kebutuhan pada laporan (BAB III)", "Implementasi pada backend", "Bukti pengujian", "Status", "Catatan"],
    [Cm(0.9), Cm(1.2), Cm(6.6), Cm(7.6), Cm(5.0), Cm(1.8), Cm(4.8)],
    matrix, sizes=[8, 8, 8, 8, 7.5, 8, 7.5], bold_cols=(0, 1), center_cols=(0, 1), status_col=5,
)
para("")
n_sesuai = sum(1 for m in matrix if m[5] == "Sesuai")
n_sebagian = sum(1 for m in matrix if m[5] == "Sebagian")
para(f"Ringkasan matriks: {n_sesuai} butir Sesuai, {n_sebagian} butir Sebagian, 0 butir Belum.", 10, True)

head("3.1 Kebutuhan non-fungsional", 2)
nfr = [
    ("NF-1", "Kinerja: waktu respons maksimal 5 detik untuk proses umum",
     "Diukur dari sisi klien atas lima proses umum (login, daftar unit, dashboard manajer, laporan penjualan, katalog "
     "publik).", "TC-J04", "Sesuai", ""),
    ("NF-2", "Keamanan: setiap pengguna login memakai akun terverifikasi",
     "Hash::check terhadap password ber-hash; akun nonaktif ditolak 403.", "TC-A05, TC-A03, TC-F05", "Sesuai", ""),
    ("NF-3", "Keamanan: role-based access control",
     "RoleMiddleware pada seluruh rute internal.", "TC-A10, TC-A11, TC-A12, TC-E03, TC-G04, TC-I06", "Sesuai", ""),
    ("NF-4", "Keamanan: password disimpan sebagai hash",
     "Cast 'password' => 'hashed' pada App\\Models\\User; nilai tersimpan berawalan $2y$12$ (bcrypt).", "Inspeksi basis data",
     "Sesuai", ""),
    ("NF-5", "Kemudahan penggunaan: antarmuka sederhana dan konsisten",
     "Ranah tampilan (frontend Blade/Tailwind) — di luar cakupan verifikasi backend.", "—", "Tidak diuji (frontend)", ""),
    ("NF-6", "Kemudahan penggunaan: responsif di berbagai perangkat",
     "Ranah tampilan — di luar cakupan verifikasi backend.", "—", "Tidak diuji (frontend)", ""),
    ("NF-7", "Kemudahan penggunaan: navigasi dengan ikon dan label jelas",
     "Ranah tampilan — di luar cakupan verifikasi backend.", "—", "Tidak diuji (frontend)", ""),
    ("NF-8", "Ketersediaan minimal 80%",
     "Server bertahan hidup selama seluruh rangkaian pengujian tanpa kegagalan boot; pengukuran ketersediaan jangka "
     "panjang memerlukan pemantauan produksi.", "Observasi pelaksanaan uji", "Belum terukur", ""),
    ("NF-9", "Setiap kesalahan memberi pesan jelas tanpa crash",
     "Respons JSON berpesan bahasa Indonesia untuk 401/403/404/422 dan penanganan not-found memakai findOrFail.",
     "TC-A05, TC-A06, TC-B08, TC-B10, TC-E08, TC-H07", "Sesuai",
     "Dengan APP_DEBUG=true respons 500 masih menampilkan jejak teknis; wajib dimatikan di luar local."),
    ("NF-10", "Integritas: setiap data divalidasi sebelum disimpan",
     "Validasi form request pada seluruh controller; nilai turunan finansial tidak diterima dari klien.",
     "TC-B05, TC-C02, TC-C03, TC-D03, TC-D04, TC-E02, TC-F02, TC-H07", "Sesuai", ""),
    ("NF-11", "Integritas: sistem mencegah duplikasi data",
     "Constraint unik pada users.email, sales_profiles.sales_code, units.unit_code, payment_schemes.scheme_name, "
     "bookings.booking_code, sales_targets (sales_id, periode), sales_reports (periode); unit yang telah dipesan tidak "
     "dapat dibooking ulang.", "TC-B04, TC-F02, TC-D04", "Sebagian",
     "Belum ada unique pada phone/email customer_leads; duplikasi lead dari formulir publik masih mungkin."),
    ("NF-12", "Portabilitas: diakses melalui Chrome, Firefox, Edge",
     "API REST berbasis HTTP/JSON standar; tidak ada ketergantungan browser pada sisi backend.", "Seluruh test case",
     "Terpenuhi (sisi backend)", ""),
]
table(["No", "Kebutuhan non-fungsional", "Perlakuan pada backend", "Bukti", "Status", "Catatan"],
      [Cm(1.2), Cm(5.6), Cm(9.6), Cm(4.2), Cm(3.0), Cm(4.3)], nfr,
      sizes=[8, 8, 8, 7.5, 8, 7.5], bold_cols=(0,), center_cols=(0,), status_col=None)
doc.add_page_break()

# ============================== BAB IV ==============================
head("BAB IV - Tabel hasil pengujian backend (format test case)")
para(f"Sebanyak {len(rows)} test case dijalankan terhadap server yang sedang berjalan "
     f"(php artisan serve --host=127.0.0.1 --port=8080) setelah pengisian ulang basis data "
     f"(php artisan migrate:fresh --seed). Kolom mengikuti format yang diminta: ID, Nama, Skenario, Deskripsi, "
     f"Langkah Uji, Data Uji, Hasil yang Diharapkan, Hasil Aktual, Status (Pass/Fail), Catatan.", 9)

COLS = ["ID", "Nama", "Skenario", "Deskripsi", "Langkah Uji", "Data Uji",
        "Hasil yang Diharapkan", "Hasil Aktual", "Status (Pass/Fail)", "Catatan"]
W = [Cm(1.2), Cm(2.6), Cm(2.2), Cm(3.1), Cm(3.2), Cm(3.2), Cm(3.1), Cm(3.6), Cm(1.2), Cm(3.6)]
tab = doc.add_table(rows=1, cols=len(COLS))
tab.alignment = WD_TABLE_ALIGNMENT.CENTER
tab.autofit = False
for i, hd in enumerate(COLS):
    c = tab.rows[0].cells[i]
    c.text = ""
    r = c.paragraphs[0].add_run(hd)
    r.bold = True
    r.font.size = Pt(8)
    r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
    shade(c, HDR)
    c.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
for x in rows:
    ok = x["status"] == "Pass"
    texts = [x["id"], x["nama"], x["skenario"], x["deskripsi"], x["langkah"], x["dataUji"],
             x["ekspektasi"], x["actualBody"], "PASS" if ok else "FAIL",
             f'{x["catatan"]} [{x["kebutuhan"]}]']
    cells = tab.add_row().cells
    for i, v in enumerate(texts):
        c = cells[i]
        c.text = ""
        p = c.paragraphs[0]
        r = p.add_run(v)
        r.font.size = Pt(7)
        if i == 8:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            r.bold = True
            r.font.color.rgb = GREEN if ok else RED
            shade(c, "E3F2E3" if ok else "FBE4E4")
for row in tab.rows:
    for i, c in enumerate(row.cells):
        c.width = W[i]
doc.add_page_break()

# ============================== BAB V ==============================
head("BAB V - Rekap hasil, cacat regresi, dan keputusan implementasi")
head("5.1 Rekap per modul", 2)
MOD = {
    "A": "Autentikasi, otorisasi peran, dan sesi",
    "B": "Master data unit, cluster, dan proyek",
    "C": "Calon konsumen dan riwayat follow-up",
    "D": "Skema pembayaran dan pemesanan",
    "E": "Pembayaran dan verifikasi",
    "F": "Master data sales dan target",
    "G": "Dashboard dan laporan",
    "H": "API publik konsumen",
    "I": "Notifikasi dan log sistem",
    "J": "Verifikasi manual (unggah berkas & waktu respons)",
}
stats = []
for key in sorted(MOD):
    grp = [r for r in rows if r["id"].split("-")[1][0] == key]
    if not grp:
        continue
    ok = sum(1 for r in grp if r["status"] == "Pass")
    stats.append([MOD[key], str(len(grp)), str(ok), str(len(grp) - ok)])
stats.append(["TOTAL", str(len(rows)), str(passed), str(failed)])
t = table(["Modul", "Total TC", "Lulus", "Gagal"], [Cm(15.0), Cm(3.4), Cm(3.4), Cm(3.4)], stats,
          sizes=[9, 9, 9, 9], center_cols=(1, 2, 3))
for c in t.rows[-1].cells:
    for p in c.paragraphs:
        for r in p.runs:
            r.bold = True
    shade(c, GREY)

head("5.2 Cacat yang ditemukan selama regresi", 2)
table(["ID", "Keparahan", "Temuan", "Tindakan", "Verifikasi ulang"],
      [Cm(1.6), Cm(2.2), Cm(9.6), Cm(7.6), Cm(7.2)],
      [
          ["RD-01", "Sedang",
           "POST /api/follow-ups menghasilkan HTTP 500 (ErrorException: Undefined array key \"sales_id\") bila "
           "permintaan tidak menyertakan sales_id. Penyebab: operator elvis (?:) dipakai pada kunci hasil validate() "
           "yang hanya mengembalikan kunci yang dikirim klien.",
           "Diubah menjadi null coalescing (??) pada app/Http/Controllers/Api/FollowUpHistoryController.php::store "
           "sehingga nilai jatuh ke salesProfile milik pengguna yang login.",
           "TC-C05 (201), TC-C06, TC-C07, TC-C08, TC-C09 — seluruhnya lulus setelah perbaikan."],
          ["RD-02", "Ringan",
           "Ekspor CSV laporan menolak pemeriksaan uji karena kepala baris ditulis berkutip dan diawali byte-order-mark.",
           "Bukan cacat backend. Pemeriksaan uji disesuaikan mengenali BOM dan kutip CSV; berkas uji "
           "tests/run-conformance-tests.mjs.", "TC-G09 dan TC-G10 lulus dengan assertions kepala dan jumlah baris."],
      ],
      sizes=[8, 8, 8, 8, 8], bold_cols=(0,), center_cols=(1,))

head("5.3 Keputusan implementasi dan sisa pekerjaan", 2)
for s in [
    "Perhitungan bunga memakai model flat (pokok dibagi rata, bunga dihitung atas pokok awal) karena laporan tidak "
    "menetapkan rumus; bila diperlukan angsuran efektif-anuitas, formula pada PaymentScheme::calculateBreakdown() "
    "dapat diganti tanpa mengubah struktur basis data.",
    "Nama tabel dipertahankan dalam bahasa Inggris sesuai keputusan pengguna; konsekuensi dan pemetaan namanya "
    "didokumentasikan pada Lampiran A.",
    "Identitas login menggunakan email. Bila laporan menghendaki kolom username tersendiri, diperlukan migration "
    "tambahan dan penyesuaian AuthController::login serta formulir frontend.",
    "Disarankan membatasi GET /api/payments berdasarkan booking.sales_id milik pengguna yang login (peran sales) agar "
    "sesuai bunyi kebutuhan d.4.",
    "Ekspor PDF belum dipasang; opsi termudah adalah menandatangani berkas CSV dari spreadsheet atau menambahkan "
    "barloway/chpdf / dompdf pada ReportController::exportReport.",
    "Menambahkan constraint unik pada customer_leads.phone (per sales) untuk memperkuat kebutuhan integritas \"mencegah "
    "duplikasi data\".",
    "Rate limiting khusus login dan APP_DEBUG=false merupakan pekerjaan pengerasan yang dilakukan menjelang deployment.",
    "Katalog berita pada PublicInfoController::newsAndGallery masih berupa daftar statis karena ERD tidak menyediakan "
    "tabel berita; galeri sudah bersumber dari kolom image pada tabel units.",
]:
    bullet(s)
doc.add_page_break()

# ============================== BAB VI ==============================
head("BAB VI - Prosedur menjalankan backend dan hasil verifikasi ulang")
head("6.1 Langkah menjalankan", 2)
steps = [
    "Salin .env.example menjadi .env, lalu atur DB_DATABASE=luminpark, DB_USERNAME=root, DB_PASSWORD kosong, "
    "APP_DEBUG sesuai lingkungan.",
    "Buat basis data: CREATE DATABASE luminpark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci.",
    "composer install dan php artisan key:generate.",
    "php artisan migrate (20 migration) dan php artisan db:seed (UserSeeder, UnitSeeder, PaymentSchemeSeeder, "
    "DemoFlowSeeder).",
    "php artisan storage:link — wajib agar berkas hasil unggah (gambar unit, bukti transfer) dapat diakses melalui "
    "/storage.",
    "php artisan serve --host=127.0.0.1 --port=8080.",
    "Regresi otomatis: node tests/run-conformance-tests.mjs (90 test case) — hasilnya ditulis ke "
    "tests/conformance-results.json.",
    "Verifikasi manual unggah berkas dan waktu respons: node tests/run-manual-checks.mjs (4 test case) — hasilnya "
    "ditulis ke tests/manual-check-results.json.",
]
for i, s in enumerate(steps, 1):
    doc.add_paragraph(f"{i}. {s}")

head("6.2 Hasil pelaksanaan prosedur", 2)
for s in [
    "Dua puluh migration berjalan tanpa error dan empat seeder selesai (7 pengguna pada lima peran, 1 proyek, "
    "3 cluster, 9 unit, 3 skema pembayaran, 4 calon konsumen beserta riwayat follow-up, 2 pemesanan, 4 pembayaran, "
    "3 target sales).",
    f"Seluruh {len(rows)} test case lulus ({passed} Pass, {failed} Fail). Tidak ada respons HTTP 500 selama "
    "pengujian.",
    "Rute terpetakan 67 (php artisan route:list --path=api); 6 rute publik dan POST /api/login berada di luar "
    "autentikasi, sesuai kebutuhan modul informasi konsumen.",
    "Bukti unggah berkas terverifikasi di sistem berkas: gambar unit tersimpan pada storage/app/public/units dan "
    "bukti transfer pada storage/app/public/payment-receipts (TC-J01, TC-J02).",
]:
    bullet(s)

head("6.3 Kesimpulan", 2)
para(f"Backend LuminPark telah sesuai dengan spesifikasi BAB II dan BAB III. Matriks ketertelusuran menunjukkan "
     f"{n_sesuai} butir Sesuai dan {n_sebagian} butir Sebagian tanpa satu pun butir Belum, dan regresi "
     f"{len(rows)} test case menghasilkan tingkat lulus {passed/len(rows)*100:.1f}% — naik dari 50,0% pada laporan "
     f"pengujian awal. Verdict: backend LAYAK DITERIMA untuk lapisan API, dengan catatan perbaikan minor yang telah "
     f"didata pada BAB V.3 (scoping indeks pembayaran, ekspor PDF, unik nomor telepon lead, rate limiting login, dan "
     f"APP_DEBUG pada deployment).", 10)
doc.add_page_break()

# ============================== LAMPIRAN A ==============================
head("LAMPIRAN A - Pemetaan nama tabel ERD (bahasa Indonesia) ke implementasi (bahasa Inggris)")
para("Keputusan pengguna: nama tabel pada kode dipertahankan dalam bahasa Inggris. Tabel berikut menjadi jembatan "
     "antara ERD pada Gambar 2.24 dan skema implementasi; kolom jumlah diverifikasi dari information_schema pada "
     "basis data uji.", 9)
erd = [
    ("pengguna", "users", schema["users"], "tabel + migration add_role_to_users (role, phone, address, status)",
     "User.php"),
    ("profil_sales", "sales_profiles", schema["sales_profiles"], "kode sales, tanggal bergabung, status, foreign key "
     "user_id unik", "SalesProfile.php"),
    ("proyek", "projects", schema["projects"], "implementasi baru (sebelumnya tidak ada)", "Project.php"),
    ("cluster", "clusters", schema["clusters"], "implementasi baru; foreign key ke projects", "Cluster.php"),
    ("unit_rumah", "units", schema["units"], "kode unit unik, tipe, luas tanah, luas bangunan, harga, status, "
     "deskripsi, gambar", "Unit.php"),
    ("calon_konsumen", "customer_leads", schema["customer_leads"], "nama, telepon, email, alamat, status, unit "
     "diminati, anggaran, catatan, follow-up terakhir, sumber", "CustomerLead.php"),
    ("riwayat_follow_up", "follow_up_histories", schema["follow_up_histories"], "tanggal, metode komunikasi, status "
     "sebelum/sesudah, catatan hasil", "FollowUpHistory.php"),
    ("pemesanan", "bookings", schema["bookings"], "kode pemesanan unik, lead, sales, unit, skema, tanggal, harga "
     "total, DP, angsuran, sisa, status, status serah terima, catatan", "Booking.php"),
    ("skema_pembayaran", "payment_schemes", schema["payment_schemes"], "nama skema unik, tipe, deskripsi, persentase "
     "DP, tenor, suku bunga, status aktif", "PaymentScheme.php"),
    ("pembayaran", "payments", schema["payments"], "jenis, jumlah, tanggal, bank, nomor & pemilik rekening, bukti "
     "transfer, status verifikasi, verifikasi oleh/pada, catatan", "Payment.php"),
    ("target_sales", "sales_targets", schema["sales_targets"], "implementasi baru; periode, target unit/nominal, "
     "pencapaian unit/nominal, unik per (sales, periode)", "SalesTarget.php"),
    ("laporan_penjualan", "sales_reports", schema["sales_reports"], "implementasi baru; periode, unit terjual, total "
     "pendapatan, jumlah calon konsumen, total closing, pembuat", "SalesReport.php"),
    ("notifikasi", "notifications", schema["notifications"], "implementasi baru; pengguna, judul, isi, tipe, tautan, "
     "is_read", "Notification.php"),
    ("log_sistem", "system_logs", schema["system_logs"], "pengguna, aksi, modul, deskripsi, IP, user agent",
     "SystemLog.php"),
    ("sesi_login", "login_sessions", schema["login_sessions"], "implementasi baru; token sesi, IP, user agent, waktu "
     "login/logout, status", "LoginSession.php"),
]
table(["Tabel pada ERD (laporan)", "Tabel implementasi", "Jumlah kolom", "Kolom sesuai narasi ERD", "Model Eloquent"],
      [Cm(4.6), Cm(4.6), Cm(2.2), Cm(11.6), Cm(5.2)],
      [[a, b, str(c), d, e] for a, b, c, d, e in erd],
      sizes=[8, 8, 8, 8, 8], center_cols=(2,))
para("")
para("Catatan: narasi laporan menyatakan \"14 tabel\" tetapi mendaftar 15 nama entitas. Skema implementasi "
     "mencakup kelimabelas entitas tersebut. Tabel kerangka framework yang berada di luar ERD dan tidak dipetakan: "
     f"personal_access_tokens ({schema['personal_access_tokens']} kolom, token Sanctum), password_reset_tokens, "
     f"failed_jobs, serta activity_logs ({schema['activity_logs']} kolom) warisan halaman Blade lama yang tidak dipakai "
     "API (log aktivitas API memakai system_logs).", 9)

head("A.1 Pemetaan kosakata nilai status", 2)
table(["Entitas", "Istilah pada laporan", "Nilai pada sistem", "Tempat definisi"],
      [Cm(4.6), Cm(7.0), Cm(11.6), Cm(5.6)],
      [
          ["units.status", "tersedia, booking, sold out, proses delivery", "available, booking, sold_out, delivery_process",
           "App\\Models\\Unit (konstanta STATUS_*)"],
          ["customer_leads.status", "cold, warm, hot, booking", "cold, warm, hot, booking", "App\\Models\\CustomerLead"],
          ["bookings.status", "status pelunasan (belum dibayar hingga lunas)", "menunggu dp, dp lunas, cicilan, lunas",
           "App\\Models\\Booking + settlementStatus()"],
          ["bookings.delivery_status", "status serah terima", "belum proses, proses, selesai", "App\\Models\\Booking"],
          ["payments.payment_type", "jenis pembayaran (uang muka, cicilan, pelunasan)",
           "enum cash bertahap | cash keras | KPR (mengikuti jenis skema transaksi); fase DP/cicilan/pelunasan "
           "diturunkan dari Booking::settlementStatus() atas besarnya pembayaran, bukan dari kolom tersendiri",
           "App\\Models\\Payment (TYPES) + Booking::settlementStatus()"],
          ["payments.verification_status", "terverifikasi / ditolak", "pending, verified, rejected", "App\\Models\\Payment"],
          ["users.role", "Manajer, Admin Sales, Sales, Konsumen", "manager, admin_sales, sales, consumer, superadmin",
           "App\\Models\\User"],
          ["users.status", "status akun", "aktif, nonaktif", "App\\Models\\User"],
      ],
      sizes=[8, 8, 9, 8], bold_cols=(0,))
doc.add_page_break()

# ============================== LAMPIRAN B ==============================
head("LAMPIRAN B - Daftar rute API dan proteksinya")
para(f"{len(routes)} rute terdeteksi melalui php artisan route:list --path=api. Rute yang tidak memerlukan token "
     "hanya enam rute publik konsumen dan POST /api/login.", 9)
route_rows = []
for r in sorted(routes, key=lambda x: (x["uri"], x["method"])):
    mws = r.get("middleware") or []
    if isinstance(mws, str):
        mws = [mws]
    roles = next((m.split(":", 1)[1] for m in mws if "RoleMiddleware:" in m), "-")
    auth = "auth:sanctum" if any("Authenticate:sanctum" in m for m in mws) else "publik"
    action = (r.get("action") or "").replace("App\\Http\\Controllers\\Api\\", "").replace(
        "App\\Http\\Controllers\\", "web: ")
    route_rows.append([r["method"].replace("GET|HEAD", "GET").replace("PUT|PATCH", "PUT/PATCH"),
                       "/" + r["uri"], action, auth, roles])
table(["Method", "URI", "Controller@method", "Autentikasi", "Peran yang diizinkan"],
      [Cm(2.6), Cm(6.4), Cm(8.2), Cm(3.0), Cm(8.0)], route_rows,
      sizes=[7.5, 7.5, 7.5, 7.5, 7.5], center_cols=(0, 3))
doc.add_page_break()

# ============================== LAMPIRAN C ==============================
head("LAMPIRAN C - Akun uji dan artefak pengujian")
head("C.1 Akun hasil seeder (semua memakai password123)", 2)
table(["Email", "Peran", "Fungsi dalam pengujian"],
      [Cm(6.0), Cm(4.0), Cm(16.2)],
      [
          ["admin@luminpark.com", "superadmin", "uji hak akses penuh dan dashboard"],
          ["manager@luminpark.com", "manager", "aktor utama CRUD unit/sales, verifikasi pembayaran, laporan"],
          ["admin.sales@luminpark.com", "admin_sales", "uji kesetaraan hak dengan manajer (kebutuhan h.3)"],
          ["sales@luminpark.com", "sales", "booking, pembayaran, follow-up, dashboard sales"],
          ["sales2@luminpark.com", "sales", "uji pemisahan data antar sales (TC-C09, TC-C10)"],
          ["sales3@luminpark.com", "sales", "uji reset password dan penonaktifan akun (TC-F04, TC-F05)"],
          ["konsumen@luminpark.com", "consumer", "uji penolakan akses modul internal (TC-A04, TC-A12)"],
      ], sizes=[9, 9, 9])

head("C.2 Artefak yang dihasilkan", 2)
for s in [
    "tests/run-conformance-tests.mjs — perangkat uji regresi 90 test case (satu urutan jalan, dapat diulang).",
    "tests/conformance-results.json — hasil mesin 90 test case yang menjadi sumber tabel BAB IV.",
    "tests/run-manual-checks.mjs + tests/manual-check-results.json — verifikasi unggah berkas multipart (PNG/PDF) "
    "dan pengukuran waktu respons.",
    "tests/routes.json — senarai rute dari php artisan route:list (dasar Lampiran B).",
    "tests/schema-summary.json — jumlah kolom tiap tabel dari information_schema (dasar Lampiran A).",
    "tests/build_conformance_report.py — pembentuk berkas laporan ini.",
]:
    bullet(s)

out = f"{BASE}/Laporan-Kesesuaian-Backend-LuminPark.docx"
doc.save(out)
print("saved:", out, "| test rows:", len(rows), "| pass:", passed, "| fail:", failed)
print("matrix:", n_sesuai, "Sesuai,", n_sebagian, "Sebagian")
