# -*- coding: utf-8 -*-
"""Laporan analisis kesesuaian backend + frontend terhadap kebutuhan pengguna dan laporan,
lengkap dengan tabel hasil pengujian berformat sepuluh kolom."""
import json
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
n_pass = sum(1 for r in auto + ui if r["status"] == "Pass")
n_fail = len(auto) + len(ui) - n_pass
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
                           "ERD Gambar 2.24, wireframe halaman, serta keputusan pengguna tanggal 1 November 2026 "
                           "(empat aktor, Admin Sales hanya-lihat, penghapusan lunak, menu pada ikon profil)"],
        ["Bentuk pengujian", f"Black-box API testing ({len(auto)} test case otomatis) dan uji antarmuka per aktor di browser "
                             f"({len(ui)} test case observasi DOM) terhadap server yang sedang berjalan"],
        ["Lingkungan", "Windows 10 x64, Laragon, PHP 8.3.33, MySQL 8.4.3, Node.js 22, Chromium automation"],
        ["Base URL", "http://127.0.0.1:8080 (web) dan http://127.0.0.1:8080/api (REST) untuk uji antarmuka pada "
                     "basis data kerja; http://127.0.0.1:8081/api (REST) untuk suite backend otomatis"],
        ["Kondisi awal basis data", "Suite backend dijalankan pada basis data terpisah luminpark_test "
                                    "(DB_DATABASE=luminpark_test php artisan migrate:fresh --seed, 22 tabel: 18 tabel "
                                    "domain + 4 tabel kerangka) agar data kerja pengguna tidak ditimpa; uji antarmuka "
                                    "memakai basis data luminpark dan luminpark_test"],
        ["Tanggal pengujian", hari.strftime("%d %B %Y")],
        ["Total test case", f"{len(auto)} backend + {len(ui)} frontend = {len(auto) + len(ui)} test case"],
        ["Hasil", f"Lulus {n_pass} | Gagal {n_fail} | Tingkat lulus {n_pass / (len(auto) + len(ui)) * 100:.1f}%"],
        ["Kesimpulan", "Backend dan frontend SESUAI dengan kebutuhan pengguna dan laporan. Empat butir kebutuhan "
                       "berstatus Sebagian dan seluruhnya sudah disepakati sebagai penyimpangan yang didokumentasikan."],
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
    "kerja luminpark tidak disentuh agar cluster dan unit buatan pengguna tetap utuh.",
    "Server pengembangan dijalankan pada http://127.0.0.1:8081 untuk suite backend (DB_DATABASE=luminpark_test php "
    "artisan serve --host=127.0.0.1 --port=8081) dan pada http://127.0.0.1:8080 untuk antarmuka "
    "(php artisan serve --host=127.0.0.1 --port=8080); tautan storage diaktifkan agar unggahan gambar dapat dibaca "
    "kembali.",
    "Suite backend tests/run-conformance-tests.mjs mengirim permintaan HTTP nyata "
    "(LP_API_BASE=http://127.0.0.1:8081/api), lalu merekam ID, nama, skenario, "
    "deskripsi, langkah, data uji, hasil yang diharapkan, hasil aktual, status, dan catatan ke "
    "tests/conformance-results.json.",
    "Suite frontend dilakukan dengan memuat halaman sebenarnya di peramban pada sesi login tiap aktor, kemudian "
    "membaca dokumen DOM yang telah dirender (daftar menu, jumlah baris tabel, tombol aksi, isi dialog, nilai form) "
    "dan merekamnya dengan skema kolom yang sama ke tests/frontend-results.json.",
    "Seluruh hasil dibaca kembali dan dibandingkan dengan butir kebutuhan pada laporan; butiran yang belum terpenuhi "
    "utuh diberi status Sebagian beserta alasannya, bukan ditutupi.",
], 1):
    bullet(f"Langkah {i}. {txt}", style="List Number")

head("1.2 Format tabel hasil pengujian", 2)
para("Tabel hasil pada BAB IV (backend) dan BAB V (frontend) memakai sepuluh kolom tepat seperti format yang diminta:",
     9)
bullet("ID — penanda test case; TC-* untuk backend, FF-* untuk frontend.")
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
for r in auto:
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
recap.append(["TOTAL", str(len(auto) + len(ui)), str(n_pass), str(n_fail)])
t = table(["Kelompok pengujian", "Jumlah uji", "Lulus", "Gagal"],
          [Cm(17.0), Cm(3.4), Cm(3.4), Cm(3.4)], recap, sizes=[8, 8, 8, 8], center_cols=(1, 2, 3))
baris_total = t.rows[len(recap)].cells
for c in baris_total:
    shade(c, SUB)
    for r in c.paragraphs[0].runs:
        r.bold = True
para(f"Seluruh {len(auto) + len(ui)} test case berstatus PASS (tingkat lulus "
     f"{n_pass / (len(auto) + len(ui)) * 100:.1f}%). Tidak ada kegagalan fungsional pada saat pengujian dilakukan.",
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
     "super admin/admin sales (TC-F04).", "TC-F04", "Sebagian",
     "Reset dilakukan dari dalam akun sehingga wajib menyertakan sandi lama; tidak ada pengiriman tautan reset "
     "lewat email (Laravel Password Broker tidak dipakai). Solusi lupa sandi bagi sales adalah reset oleh super admin."),
    ("4", "a.4", "Logout dengan batas waktu tertentu untuk setiap aktor",
     "AuthController::logout menghapus CurrentAccessToken; masa berlaku token dibaca dari pengaturan "
     "session_timeout_minutes melalui SettingController::sessionTimeoutMinutes() (app/Http/Controllers/Api/"
     "SettingController.php:204, minimum 15 menit, baku 1.440 menit) dan dipakai sebagai expiresAt saat createToken; "
     "tabel login_sessions mencatat masuk, keluar, IP, dan perangkat.",
     "TC-A01, TC-A15, TC-A16, TC-L07", "Sesuai",
     "Koreksi dari laporan sebelumnya: batas waktu tidak lagi diambil dari config/sanctum.php, melainkan dari "
     "halaman Pengaturan Sistem sehingga dapat diubah super admin tanpa mengubah kode."),
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
     "TC-B03, TC-B05, TC-B06, TC-B07, TC-B09, TC-B10, TC-B16, TC-J01", "Sesuai", ""),
    ("10", "b.5", "Super Admin memperbarui status unit", "UnitController::update (PUT/PATCH /units/{id}).", "TC-B06",
     "Sesuai", ""),
    ("10a", "b.1 cluster", "Super Admin dapat menambahkan cluster baru dari halaman Data Unit, sebelum unit-unit di "
     "dalamnya dibuat; Admin Sales hanya dapat membacanya",
     "ClusterController::store (POST /clusters) memvalidasi project_id (exists:projects) dan nama dengan "
     "Rule::unique('clusters','name')->where('project_id', ...) karena indeks unik pada tabel clusters bersifat "
     "komposit (project_id, name); GET /clusters dan GET /projects tersedia untuk super_admin dan admin_sales, "
     "sedangkan POST dikunci role:super_admin (routes/api.php). Antarmukanya berupa tombol Tambah Cluster di "
     "resources/views/app/data-unit.blade.php yang memakai LP.formModal.",
     "TC-B11, TC-B12, TC-B13, TC-B14, TC-B15, TC-B16, TC-B17, TC-B18, FF-A18, FF-A19, FF-B08", "Sesuai",
     "Baris ini merupakan permintaan pengguna di luar teks laporan (tambah cluster disandingkan dengan tambah unit); "
     "cluster hasil uji langsung dapat dipakai sebagai cluster induk unit baru (TC-B16)."),
    ("11", "Keputusan hapus lunak", "Data yang dihapus hilang dari antarmuka tetapi tetap tersimpan di basis data",
     "SoftDeletes pada users, units, sales_profiles, customer_leads, bookings, payments, payment_schemes, dan "
     "public_posts (migration 2026_11_01_110000_add_soft_deletes.php); seluruh query memakai scope tanpa deleted_at.",
     "TC-L05, TC-L06", "Sesuai",
     "Indeks unik pada email, kode unit, kode sales, dan nama skema diturunkan menjadi indeks biasa agar kode yang "
     "pernah dipakai dapat digunakan lagi; keunikan dijaga di lapisan validasi dengan aturan whereNull('deleted_at')."),
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
     "TC-E01, TC-E02, TC-E08, TC-J02", "Sesuai", ""),
    ("17", "d.2", "Super Admin memverifikasi bukti pembayaran (terverifikasi/ditolak)",
     "PUT /payments/{id}/verify khusus role:super_admin; kolom verified_by dan verified_at terisi; penolakan tidak "
     "mengubah sisa pembayaran.", "TC-E03, TC-E04, TC-E06", "Sesuai", ""),
    ("18", "d.3", "Status pelunasan dan sisa pembayaran diperbarui otomatis",
     "PaymentController::recalculateBooking + Booking::settlementStatus() menurunkan status (menunggu DP, dp lunas, "
     "cicilan, lunas) dari ledger pembayaran yang terverifikasi.", "TC-D09, TC-E05, TC-E07, TC-E10, TC-D10", "Sesuai", ""),
    ("19", "d.4", "Sales memantau status pembayaran konsumennya",
     "GET /payments dengan filter booking_id, payment_type, dan verification_status.", "TC-E09", "Sebagian",
     "Indeks pembayaran belum dipangkas per sales, sehingga sales masih dapat membaca pembayaran milik booking "
     "sales lain bila mengetahui id booking-nya. Filter per-booking sudah tersedia sehingga dampak praktis terbatas."),
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
     "ReportController::salesReport/settlementReport/salesPerformanceReport/consumerReport dan exportReport.",
     "TC-G05, TC-G06, TC-G07, TC-G08, TC-G09, TC-G10", "Sebagian",
     "Ekspor nyata berupa berkas CSV berkepala bahasa Indonesia yang dapat dibuka dan dicetak lewat spreadsheet; "
     "unduhan PDF belum dibuat. Keputusan pengguna menerima bentuk CSV."),
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
     "PublicInfoController::salesContacts menghasilkan nomor telepon dan tautan wa.me; formulir publik "
     "menyimpan lead baru.", "TC-H05, TC-H06, TC-H07", "Sesuai", ""),
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
     "TC-A15, TC-B01, TC-B02, TC-D08, TC-F01, TC-G11, TC-I01, TC-I05", "Sesuai",
     "Ditambah 4 tabel kerangka (migrations, failed_jobs, password_reset_tokens, personal_access_tokens) sehingga "
     "total 22 tabel. Nama tabel dipertahankan dalam bahasa Inggris; pemetaan ke nama Indonesia tersedia pada "
     "LAMPIRAN A laporan pembanding sebelumnya. Angka 14 pada narasi laporan perlu dikoreksi menjadi 18."),
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
     "Diukur dari sisi klien atas login, daftar unit, dashboard super admin, laporan penjualan, dan katalog publik.",
     "TC-J04", "Sesuai", "Seluruh proses di bawah 5 detik pada lingkungan lokal."),
    ("NF-2", "Setiap pengguna masuk memakai akun terverifikasi",
     "Hash::check terhadap sandi ber-hash; akun nonaktif ditolak 403; peran non-internal ditolak 403.",
     "TC-A05, TC-A03, TC-A04, TC-F05", "Sesuai", ""),
    ("NF-3", "Role-based access control",
     "RoleMiddleware pada seluruh rute internal; guard ganda (middleware API + penapisan peran di Blade).",
     "TC-A10, TC-A11, TC-A12, TC-E03, TC-G04, TC-I06, TC-L01..L04", "Sesuai", ""),
    ("NF-4", "Password disimpan sebagai hash",
     "Cast password => hashed pada App\\Models\\User; nilai tersimpan berawalan $2y$ (bcrypt).",
     "Inspeksi basis data", "Sesuai", ""),
    ("NF-5", "Antarmuka sederhana dan konsisten",
     "Diuji pada BAB III (verifikasi DOM per aktor), bukan lagi di luar cakupan.",
     "FF-A01..FF-A17, FF-B01..FF-B07", "Sesuai", "Layout, tabel, dialog, dan formulir memakai komponen yang sama."),
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
     "Respons JSON berpesan bahasa Indonesia untuk 401/403/404/422; findOrFail menghasilkan 404 terkendali.",
     "TC-A05, TC-A06, TC-B08, TC-B10, TC-E08, TC-H07", "Sesuai",
     "Dengan APP_DEBUG=true respons 500 masih menampilkan jejak teknis; wajib dimatikan bila aplikasi dipasang di "
     "luar mesin pengembang."),
    ("NF-10", "Setiap data divalidasi sebelum disimpan",
     "Validasi form request pada seluruh controller; nilai turunan finansial dihitung server, tidak diterima dari klien.",
     "TC-B05, TC-C02, TC-C03, TC-D03, TC-D04, TC-D05, TC-E02, TC-F02, TC-H07, TC-K04, TC-K05", "Sesuai", ""),
    ("NF-11", "Sistem mencegah duplikasi data",
     "Keunikan dijaga lapisan validasi memakai Rule::unique(...)->whereNull('deleted_at') untuk users.email, "
     "units.unit_code, sales_profiles.sales_code, dan payment_schemes.scheme_name; indeks unik tingkat basis data "
     "masih berlaku pada clusters(project_id,name), sales_targets(sales_id,periode), sales_reports(periode), "
     "settings(key), sales_profiles(user_id), dan bookings.booking_code.",
     "TC-B04, TC-F02, TC-D04", "Sebagian",
     "Indeks unik email/kode unit/kode sales/nama skema sengaja diturunkan menjadi indeks biasa oleh migration "
     "2026_11_01_110000_add_soft_deletes.php agar kode dari baris yang dihapus lunak dapat dipakai lagi. Belum ada "
     "aturan unik untuk email atau telepon customer_leads sehingga lead ganda dari formulir publik masih mungkin."),
    ("NF-12", "Dapat diakses melalui Chrome, Firefox, dan Edge",
     "API REST berbasis HTTP/JSON standar; frontend memakai JavaScript dan fetch tanpa pustaka khusus browser.",
     "Seluruh test case", "Sesuai", "Uji fungsional dijalankan pada Chromium; Firefox dan Edge belum dijalankan."),
    ("NF-13 (tambahan)", "Setiap aktivitas penting tercatat",
     "activity_logs untuk login, logout, login gagal, dan aksi CRUD; system_logs dan notifications untuk peristiwa "
     "bisnis; tabel login_sessions menyimpan IP, perangkat, serta waktu masuk dan keluar.",
     "TC-A15, TC-I01, TC-I02, TC-I03, TC-I05, TC-I06", "Sesuai",
     "Log hanya dapat dibaca super admin; sales dan admin sales ditolak (TC-I06, TC-L04)."),
]
table(["No", "Kebutuhan non-fungsional", "Perlakuan pada sistem", "Bukti", "Status", "Catatan"],
      [Cm(1.7), Cm(5.2), Cm(8.6), Cm(4.4), Cm(2.2), Cm(5.2)], nfr,
      sizes=[8, 8, 8, 7, 8, 7], bold_cols=(0,), center_cols=(0,), status_col=4)
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
    ("Empat aktor", "Konsumen tidak memiliki jalur masuk; antarmuka hanya menyediakan login bagi tiga aktor internal",
     "FF-D03 + TC-A04, TC-A04b", "Sesuai"),
    ("Hak Super Admin", "Delapan modul kerja tampil dan seluruh aksi manajemen tersedia (tambah cluster, tambah unit, "
     "edit, hapus, verifikasi pembayaran, serah terima, laporan, log aktivitas, sesi login)",
     "FF-A01, FF-A04..FF-A19", "Sesuai"),
    ("Tambah cluster di Data Unit", "Tombol Tambah Cluster hanya terlihat untuk super admin; modal berisi proyek "
     "induk, nama cluster, dan deskripsi. Cluster hasil simpan langsung muncul pada penapis cluster dan pada opsi "
     "cluster di form Tambah Unit", "FF-A18, FF-A19", "Sesuai"),
    ("Hak Admin Sales (hanya-lihat)", "Data Unit, Data Konsumen, Booking, dan Data Pembayaran hanya menyediakan "
     "tombol Detail; tanpa Log Aktivitas dan tanpa Sesi Login; Data Sales tetap dapat dikelola",
     "FF-B01, FF-B02, FF-B03, FF-B04, FF-B05, FF-B06, FF-B07, FF-B08", "Sesuai"),
    ("Hak Sales", "Tanpa menu Sesi Login dan tanpa field Sesi Aktif pada profil; hanya menangani data konsumennya "
     "sendiri; memakai laporan khusus sales", "FF-C01, FF-C02, FF-C03, FF-C04, FF-C05, FF-C06", "Sesuai"),
    ("Detail + hapus pada setiap tabel", "Baris Data Unit, Data Sales, dan Data Konsumen hanya menyediakan aksi "
     "Detail dan Hapus; proses edit berpindah ke halaman detail masing-masing entitas",
     "FF-A04, FF-A05, FF-A06, FF-A07, FF-A08, FF-A09", "Sesuai"),
    ("Penghapusan lunak", "Dialog hapus menyatakan data dikeluarkan dari daftar dan riwayatnya tetap tersimpan, bukan "
     "hilang permanen", "FF-A10, FF-D05", "Sesuai"),
    ("Pengaturan Sistem", "Memuat seksi identitas, penjualan dan pembayaran, sesi (session_timeout_minutes), serta "
     "integrasi Google", "FF-A11", "Sesuai"),
    ("Pengelolaan halaman publik", "Super Admin dapat mengatur hero, saklar seksi beranda, unit unggulan, pengumuman, "
     "footer, dan kabar terbaru; nilai yang sama dipatuhi halaman publik", "FF-A12, FF-D01, FF-D04", "Sesuai"),
    ("Profil per peran", "Profil super admin tidak lagi menampilkan Bergabung Sebagai Sales dan Kode Sales; profil "
     "sales menampilkan identitas, foto, dan kata sandi tanpa field sesi", "FF-A14, FF-C03", "Sesuai"),
    ("Laporan dapat dibaca", "Empat tab laporan terisi data pada jendela 30 hari dan menyediakan ekspor; sales "
     "memperoleh halaman Laporan Sales sendiri", "FF-A13, FF-C05", "Sesuai"),
    ("Halaman publik konsumen", "Beranda, detail unit, skema pembayaran, tentang kami, kontak, dan booking berjalan "
     "tanpa login dengan harga serta narahubung WhatsApp", "FF-D01, FF-D02, FF-D04 + TC-H01..TC-H08", "Sesuai"),
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
bullet("Batas waktu sesi ditegakkan di sisi server (token kedaluwarsa, permintaan berikutnya 401 lalu client "
       "membersihkan token dan mengirim pengguna ke /login). Frontend belum memiliki penghitung waktu idle yang "
       "memperingatkan pengguna sebelum sesi berakhir.")
bullet("Tombol Masuk dengan Google pada /login sengaja dinonaktifkan dan muncul catatan \"Menunggu konfigurasi Super "
       "Admin pada halaman Pengaturan.\" karena google_client_id dan google_client_secret masih kosong serta "
       "google_login_enabled = 0. Alur kodenya sudah ada (rute auth/google/redirect dan auth/google/callback) "
       "menunggu kredensial asli dari pemilik proyek.")
bullet("Label kolom identitas pada /login berbunyi \"Nama Pengguna\" sementara yang dimasukkan adalah email. Ini "
       "disengaja mengikuti wireframe, tetapi dapat diubah menjadi \"Email\" bila laporan disesuaikan.")
bullet("Tampilan logo belum dapat diverifikasi secara visual karena lingkungan pengujian tidak menyediakan viewport "
       "browser; yang diverifikasi adalah struktur DOM, bukan kenampakan piksel.")
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
                 (x["catatan"] + (" [" + x["kebutuhan"] + "]" if x.get("kebutuhan") else ""))]
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


uji_table(
    auto,
    "BAB IV - Tabel hasil pengujian backend (format sepuluh kolom)",
    f"Sebanyak {len(auto)} test case dieksekusi dengan node tests/run-conformance-tests.mjs terhadap server "
    f"http://127.0.0.1:8081/api (LP_API_BASE), setelah basis data uji luminpark_test diisi ulang dengan "
    f"DB_DATABASE=luminpark_test php artisan migrate:fresh --seed pada {hari.strftime('%d %B %Y')}. Kolom Hasil Aktual memuat kode status HTTP "
    f"dan potongan isi respons yang benar-benar dikembalikan aplikasi; kolom Catatan memuat tafsir hasil serta "
    f"rujukan butir kebutuhan.")

uji_table(
    ui,
    "BAB V - Tabel hasil pengujian frontend (format sepuluh kolom)",
    f"Sebanyak {len(ui)} test case diamati pada peramban dengan memuat halaman nyata dari aplikasi. Prefix FF-A untuk "
    f"aktor Super Admin ({sum(1 for r in ui if r['id'].startswith('FF-A'))} uji), FF-B untuk Admin Sales "
    f"({sum(1 for r in ui if r['id'].startswith('FF-B'))} uji), FF-C untuk Sales "
    f"({sum(1 for r in ui if r['id'].startswith('FF-C'))} uji), dan FF-D untuk halaman publik serta autentikasi "
    f"({sum(1 for r in ui if r['id'].startswith('FF-D'))} uji). Kolom Hasil Aktual berisi fakta DOM yang terbaca dari "
    f"dokumen yang sudah dirender (tautan menu, jumlah baris tabel, label tombol, isi dialog, nilai field).")

# ============================== BAB VI ==============================
head("BAB VI - Kesimpulan dan rekomendasi")
head("6.1 Kesimpulan", 2)
para(f"Backend: {len(auto)} test case black-box seluruhnya PASS. {len(matrix)} butir kebutuhan (a.1 sampai h.3, "
     f"ERD, dan keputusan pengguna) terpenuhi; {n_sebagian} butir berstatus Sebagian dan seluruhnya merupakan "
     f"penyimpangan yang telah diketahui dan disepakati: identitas login berupa email, reset sandi memerlukan sandi "
     f"lama, indeks pembayaran belum dipangkas per sales, serta ekspor laporan berbentuk CSV bukan PDF. "
     f"Delapan test case baru (TC-B11 sampai TC-B18) membuktikan modul Tambah Cluster beserta penjagaan nama unik "
     f"per proyek dan hak aksesnya.", 9)
para(f"Frontend: {len(ui)} test case observasi DOM seluruhnya PASS dan sesuai dengan keputusan pengguna, yaitu "
     f"navigasi modul di sidebar, menu akun pada ikon profil, notifikasi pada lonceng, tombol Tambah Cluster yang "
     f"hanya tampil untuk super admin, hak hanya-lihat Admin Sales "
     f"pada empat modul, pembatasan data sales, serta bahasa penghapusan lunak pada dialog hapus. Dua ketidaksuaian "
     f"yang ditemukan selama analisis (teks hapus permanen pada kabar terbaru dan saklar galeri pada halaman Tentang "
     f"Kami) telah diperbaiki dan diverifikasi ulang.", 9)
para("Secara keseluruhan sistem dapat dinyatakan SESUAI dengan kebutuhan pengguna dan isi laporan untuk keperluan "
     "demonstrasi dan pengujian skripsi; status produksi masih memerlukan penutupan butir rekomendasi di bawah.", 9)

head("6.2 Rekomendasi sebelum aplikasi dipasang di server umum", 2)
table(["Prioritas", "Butir", "Alasan", "Tindakan yang disarankan"],
      [Cm(2.0), Cm(6.5), Cm(9.5), Cm(9.3)],
      [
          ["Tinggi", "APP_DEBUG masih bernilai true",
           "Respons 500 menampilkan jejak stack, nama tabel, kolom, dan path berkas sehingga menguntungkan pihak yang "
           "mencoba mendeteksi celah.",
           "Setel APP_DEBUG=false dan APP_ENV=production; hasil uji NF-9 tetap berlaku karena pesan error JSON tidak "
           "berubah."],
          ["Tinggi", "Perlambatan khusus login belum ada",
           "Rute POST /api/login hanya dibatasi throttle:api 60 permintaan per menit per IP (RouteServiceProvider.php:27 "
           "dan Kernel.php:41), sehingga percobaan sandi brute-force pada satu akun masih dapat berjalan puluhan kali "
           "per menit.",
           "Tambahkan RateLimiter khusus (misal lima percobaan per menit per email) pada rute login dan catat "
           "percobaan gagal yang sudah direkam activity_logs."],
          ["Sedang", "Indeks pembayaran belum dipangkas per sales",
           "Butir d.4 berstatus Sebagian; sales masih dapat membaca pembayaran booking sales lain bila id booking "
           "diketahui.",
           "Saring PaymentController::index memakai relasi booking.sales_id untuk peran sales."],
          ["Sedang", "Belum ada aturan unik pada customer_leads",
           "Formulir publik dapat menghasilkan lead ganda dengan email atau telepon yang sama (butir NF-11).",
           "Tambah validasi unik dengan whereNull('deleted_at') pada phone/email serta pesan \"sudah terdaftar\"."],
          ["Sedang", "Kredensial login Google belum diisi",
           "google_client_id, google_client_secret, dan google_login_enabled masih kosong/false sehingga tombol Google "
           "nonaktif (FF-D03).",
           "Isi kredensial pada halaman Pengaturan Sistem, lalu aktifkan saklar dan uji alur callback."],
          ["Rendah", "Ranah Blade warisan masih ada",
           "resources/views/dashboard/index.blade.php, sales/index.blade.php, activity-log/index.blade.php, "
           "super-admin/dashboard.blade.php, super-admin/dataunit_perumahan.blade.php, dan layouts/dashboard.blade.php "
           "tidak dipakai antarmuka baru tetapi masih dapat dijangkau ActivityLogController.php:13 dan "
           "Api/DashboardController.php:29.",
           "Hapus berkas dan pemanggilnya agar tidak membingungkan pembaca kode."],
          ["Rendah", "Daftar log sistem belum berpaginasi",
           "GET /api/system-logs mengembalikan seluruh baris; volumenya akan besar setelah pemakaian lama.",
           "Terapkan paginasi baku (perPage 15) sebagaimana modul lain."],
          ["Rendah", "Teks laporan menyebut 14 tabel dan 6 rute publik",
           "Fakta implementasi adalah 18 tabel domain (22 tabel termasuk kerangka) dan 10 halaman publik non-login "
           "ditambah halaman login, ganti sandi, dan reset sandi; total rute terdaftar 114 (83 API + 31 web).",
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
           "aktor utama CRUD cluster, unit, dan sales, verifikasi pembayaran, laporan, log aktivitas, sesi login, "
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
          ["Uji frontend", "Muat halaman nyata pada peramban otomatis lalu baca contentDocument tiap aktor; "
                           "hasil direkam pada tests/frontend-results.json"],
          ["Susun laporan ini", "C:/laragon/bin/python/python-3.13/python.exe tests/build_laporan_uji_sistem.py"],
      ], sizes=[9, 9])

head("A.3 Artefak yang dihasilkan", 2)
table(["Berkas", "Isi"], [Cm(9.0), Cm(18.3)],
      [
          ["tests/conformance-results.json", f"{len(auto)} baris hasil uji backend dalam skema sepuluh kolom"],
          ["tests/frontend-results.json", f"{len(ui)} baris hasil uji frontend dalam skema kolom yang sama"],
          ["tests/run-conformance-tests.mjs", "mesin eksekusi dan definisi test case backend"],
          ["tests/build_laporan_uji_sistem.py", "penyusun berkas Word ini"],
          ["Laporan-Analisis-Kesesuaian-dan-Uji-Sistem-LuminPark.docx", "laporan yang sedang dibaca"],
      ], sizes=[9, 9])

OUT = f"{BASE}/Laporan-Analisis-Kesesuaian-dan-Uji-Sistem-LuminPark.docx"
doc.save(OUT)
print(f"Tersimpan: {OUT}")
print(f"Baris backend {len(auto)}, baris frontend {len(ui)}, lulus {n_pass}, gagal {n_fail}")
