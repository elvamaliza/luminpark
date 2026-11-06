# -*- coding: utf-8 -*-
import json, re, datetime, collections
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

BASE = "C:/laragon/www/luminpark-main"
rows = []
for f in ["tests/api-test-results.json", "tests/api-test-results-extra.json"]:
    rows += json.load(open(f"{BASE}/{f}", encoding="utf-8"))
by_id = {r["id"]: r for r in rows}
data = [by_id[k] for k in sorted(by_id)]

# ---------- classify ----------
def clean_message(b):
    m = re.search(r'"message"\s*:\s*"((?:[^"\\]|\\.)*)"', b)
    txt = m.group(1) if m else b
    txt = txt.replace('\\`', '`').replace('\\\\', '\\')
    return txt

def sqlstate(txt):
    m = re.search(r"(SQLSTATE\[[0-9A-Z]+\][^()]*)", txt)
    return m.group(1) if m else txt

PASS = RGBColor(0x1B, 0x7F, 0x3B)
FAIL = RGBColor(0xB0, 0x1F, 0x1F)

def classify(r):
    return str(r["actualStatus"]), clean_message(r["actualBody"])

def root_cause(r):
    st, txt = classify(r)
    if r["id"] in ("TC-120", "TC-121", "TC-122"):
        return "SEC", txt[:150]
    if "Unknown column" in txt:
        c = re.search(r"Unknown column '([^']+)'", txt)
        return "SKEMA", f"Kolom `{c.group(1)}` tidak ada di tabel (migration masih stub). Query: {sqlstate(txt)[:110]}"
    if "doesn't exist" in txt and "Table" in txt:
        t = re.search(r"Table '[^']+'\.(\w+)", txt)
        return "SKEMA", f"Tabel `{t.group(1) if t else '?'}` tidak ditemukan: nama tabel pada Model tidak sama dengan hasil migration."
    if "does not exist." in txt and "Method" in txt:
        m = re.search(r"Method [\\A-Za-z]+::(\w+) does not exist", txt)
        return "KODE", f"Method controller `{m.group(1) if m else '?'}` belum diimplementasikan tetapi dirujuk oleh routes/api.php."
    if "Call to undefined method" in txt:
        m = re.search(r"undefined method ([\\A-Za-z]+)::(\w+)", txt)
        return "KODE", f"Relasi Eloquent `{m.group(2) if m else '?'}` tidak didefinisikan pada Model {m.group(1).split(chr(92))[-1] if m else '?'}."
    if "No query results for model" in txt:
        return "DATA", "Terhalang berantai: data uji tidak dapat dibuat karena endpoint Create gagal, sehingga Find/Update/Delete mengembalikan 404."
    if st == "404" and "tidak ditemukan" in txt or (st == "404" and "Not found" in txt):
        return "DATA", "Terhalang berantai: record acuan belum berhasil dibuat (endpoint Create gagal)."
    if st == "422" and "selected" in txt:
        return "DATA", "Aturan `exists:` menolak data uji karena tabel referensi kosong (efek berantai dari gagalnya Create)."
    if st == "500":
        return "KODE", sqlstate(txt)[:150]
    return "OK", txt[:150]


def pass_note(r, txt):
    if r["id"] in CURATED:
        return CURATED[r["id"]][1]
    msg = txt.strip(". ").split(".")[0][:90]
    return f"Sesuai ekspektasi (HTTP {r['actualStatus']}); respons: {msg}."

# curated notes
CURATED = {
 "TC-120": ("SEC", "KRITIS: respons yang muncul adalah error database, bukan 401 - bukti request menembus sampai controller. "
                    "Rute /api/sales berada di luar group auth:sanctum sehingga dapat dipanggil tanpa login."),
 "TC-121": ("SEC", "KRITIS: laporan finansial /api/reports/sales tidak dilindungi autentikasi apa pun."),
 "TC-122": ("SEC", "KRITIS: operasi destruktif DELETE /api/units/{id} tidak dilindungi autentikasi; 404 yang muncul adalah karena data kosong, bukan karena akses ditolak."),
 "TC-013": ("OK", "Validasi manual berjenjang berfungsi benar (400 saat sales_id absen)."),
 "TC-011": ("OK", "RoleMiddleware bekerja benar: role sales ditolak pada rute role:manager,superadmin."),
 "TC-006": ("OK", "Sanctum token valid dan profil user dikembalikan dengan benar."),
 "TC-140": ("OK", "CurrentAccessToken dihapus saat logout sehingga token lama benar-benar dicabut."),
 "TC-112": ("OK", "Data berita/galeri masih hardcode di controller, belum bersumber dari database."),
 "TC-030": ("OK", "HTTP 200 tetapi body array kosong karena tabel units belum memiliki kolom data."),
 "TC-040": ("OK", "HTTP 200 dengan data kosong; kolom domain lead tidak tersedia."),
 "TC-060": ("OK", "HTTP 200 dengan data kosong; kolom skema pembayaran tidak tersedia."),
 "TC-070": ("OK", "HTTP 200 dengan data kosong; relasi booking tidak dapat dimuat."),
 "TC-080": ("OK", "HTTP 200 dengan data kosong; kolom pembayaran tidak tersedia."),
 "TC-090": ("OK", "HTTP 200 dengan data kosong; kolom system_logs tidak tersedia."),
 "TC-103": ("OK", "HTTP 200; export hanya mengembalikan JSON mentah, belum menghasilkan berkas PDF/Excel."),
 "TC-111": ("OK", "Perlu dicatat: PublicInfoController menyaring status='tersedia' sedangkan UnitController hanya membolehkan available/booked/sold - nilai tidak pernah cocok."),
}

records = []
for r in data:
    st, txt = classify(r)
    exp_code = re.search(r"\d{3}", r["ekspektasi"])
    exp = int(exp_code.group()) if exp_code else 0
    ok = int(st) == exp if str(st).isdigit() else False
    if r["id"] in ("TC-120", "TC-121", "TC-122"):
        ok = False
    rc, dnote = root_cause(r)
    if r["id"] in CURATED:
        note = CURATED[r["id"]][1]
    else:
        note = dnote if not ok else pass_note(r, txt)
    actual = f"HTTP {st} - {txt[:230]}"
    records.append({**r, "passed": ok, "root": rc, "actual_clean": actual, "note": note})

total = len(records)
passed = sum(1 for x in records if x["passed"])
failed = total - passed

MODULE = [("TC-00", "Autentikasi (Login, Logout, Profil)"), ("TC-01", "Otorisasi Role & Dashboard"),
          ("TC-02", "Master Sales"), ("TC-03", "Master Unit"), ("TC-04", "Customer Lead"),
          ("TC-05", "Follow-Up History"), ("TC-06", "Skema Pembayaran"), ("TC-07", "Booking"),
          ("TC-08", "Pembayaran & Verifikasi"), ("TC-09", "System Log"), ("TC-10", "Laporan / Report"),
          ("TC-11", "API Publik Konsumen"), ("TC-12", "Keamanan / Autorisasi"), ("TC-13", "Logout"), ("TC-14", "Sesi Token")]
def module_of(i):
    n = int(i.split("-")[1])
    if n <= 9: return "Autentikasi"
    if n <= 19: return "Dashboard & Otorisasi Role"
    if n <= 29: return "Master Sales"
    if n <= 39: return "Master Unit"
    if n <= 49: return "Customer Lead"
    if n <= 59: return "Follow-Up History"
    if n <= 69: return "Skema Pembayaran"
    if n <= 79: return "Booking"
    if n <= 89: return "Pembayaran & Verifikasi"
    if n <= 99: return "System Log"
    if n <= 109: return "Laporan (Report)"
    if n <= 119: return "API Publik Konsumen"
    if n <= 129: return "Keamanan / Autorisasi"
    return "Sesi & Token"

mod_stats = collections.OrderedDict()
for x in records:
    m = module_of(x["id"])
    mod_stats.setdefault(m, [0, 0])
    mod_stats[m][0] += 1
    if x["passed"]:
        mod_stats[m][1] += 1

FAILCAT = collections.Counter(x["root"] for x in records if not x["passed"])
N_SCHEMA = FAILCAT.get("SKEMA", 0)
N_CHAIN = FAILCAT.get("DATA", 0)
N_CODE = FAILCAT.get("KODE", 0)
N_SEC = FAILCAT.get("SEC", 0)
AUTH_IDS = {1, 2, 3, 4, 5, 6, 7, 8, 9, 130, 140}
auth_ids = [x for x in records if int(x["id"].split("-")[1]) in AUTH_IDS]
AUTH_TOT = len(auth_ids)
AUTH_PASS = sum(1 for x in auth_ids if x["passed"])

# ---------- document ----------
doc = Document()
st = doc.styles["Normal"]
st.font.name = "Calibri"
st.font.size = Pt(10)

sec = doc.sections[0]
sec.orientation = WD_ORIENT.LANDSCAPE
sec.page_width, sec.page_height = Cm(29.7), Cm(21.0)
for m in ("top_margin", "bottom_margin", "left_margin", "right_margin"):
    setattr(sec, m, Cm(1.2))

def shade(cell, hexc):
    el = OxmlElement("w:shd")
    el.set(qn("w:val"), "clear"); el.set(qn("w:fill"), hexc)
    cell._tc.get_or_add_tcPr().append(el)

def h(text, lvl=1):
    p = doc.add_heading(text, level=lvl)
    return p

def para(text, size=10, bold=False, align=None):
    p = doc.add_paragraph()
    r = p.add_run(text)
    r.font.size = Pt(size); r.bold = bold
    if align: p.alignment = align
    return p

# COVER
para("LAPORAN PENGUJIAN BACKEND (API TESTING)", 20, True, WD_ALIGN_PARAGRAPH.CENTER)
para("Aplikasi LuminPark - CRM Pemasaran Perumahan", 13, False, WD_ALIGN_PARAGRAPH.CENTER)
para("Laravel 10 + Laravel Sanctum", 11, False, WD_ALIGN_PARAGRAPH.CENTER)
doc.add_paragraph()
tbl = doc.add_table(rows=0, cols=2)
tbl.style = "Light Grid Accent 1"
for k, v in [("Nama Proyek", "LuminPark (luminpark-main)"),
             ("Jenis Pengujian", "Black-box API Testing (Functional & Security)"),
             ("Lingkungan Uji", "Local (Laragon) - Windows 10, PHP 8.3.33, MySQL 8.4.3"),
             ("Base URL", "http://127.0.0.1:8080/api"),
             ("Alat Uji", "php artisan serve, Node.js 22 (fetch), cURL, MySQL CLI"),
             ("Database Uji", "luminpark (schema hasil artisan migrate + db:seed)"),
             ("Tanggal Pengujian", datetime.date.today().strftime("%d %B %Y")),
             ("Jumlah Test Case", f"{total} test case"),
             ("Hasil", f"Lulus: {passed}   |   Gagal: {failed}   |   Tingkat Lulus: {passed/total*100:.1f}%"),
             ("Status Aplikasi", "DAPAT DIJALANKAN, namun BELUM LAYAN PRODUKSI")]:
    c = tbl.add_row().cells
    c[0].text = ""; c[1].text = ""
    rr = c[0].paragraphs[0].add_run(k); rr.bold = True; rr.font.size = Pt(10)
    c[1].paragraphs[0].add_run(v).font.size = Pt(10)
    shade(c[0], "F2F2F2")

doc.add_page_break()

# BAB I
h("BAB I - ringkasan eksekutif", 1)
para(f"Pengujian dilakukan terhadap {total} test case yang mencakup {len(mod_stats)} modul fungsional backend LuminPark. "
     f"Dari jumlah tersebut, {passed} test case ({passed/total*100:.1f}%) berjalan sesuai harapan dan {failed} test case "
     f"({failed/total*100:.1f}%) menyimpang.")
para("Secara umum backend berhasil di-boot: server Laravel hidup, routing terpetakan (57 rute API teridentifikasi), "
     "autentikasi berbasis Sanctum berfungsi penuh, dan middleware otorisasi berbasis role bekerja dengan benar.")
p = doc.add_paragraph(
    f"Namun demikian, {failed} test case menyimpang. Pengujian mengidentifikasi lima hambatan pokok pada backend:\n")
for t in ["Skema database tidak lengkap. Delapan migration domain (sales_profiles, customer_leads, follow_up_histories, "
          "units, payment_schemes, bookings, payments, system_logs) masih berupa stub yang hanya membuat kolom id, "
          "created_at, updated_at, sementara Model dan controller menuntut puluhan kolom. Akibatnya setiap operasi "
          "Write/Filter/Aggregate menghasilkan SQLSTATE[42S22] Column not found (HTTP 500).",
          "Ketidakkonsistenan nama tabel. Model FollowUpHistory memakai $table = 'follow_up_history' (bentuk tunggal), "
          "sedangkan migration membuat tabel follow_up_histories.",
          "Method yang belum diimplementasikan. Route /api/sales/{id}/performance dan /api/sales/{id}/reset-password "
          "mengarah ke method yang tidak ada di SalesController.",
          "Relasi Eloquent yang belum didefinisikan. ReportController memanggil withCount(['customerLeads', 'bookings']) "
          "namun SalesProfile::customerLeads() tidak ada.",
          "Lubang keamanan autorisasi. Seluruh rute resource (sales, units, customer-leads, bookings, payments, "
          "payment-schemes, follow-ups, system-logs) dan seluruh rute reports berada di luar group auth:sanctum, "
          "sehingga dapat dipanggil tanpa login."]:
    doc.add_paragraph(t, style="List Bullet")

h("BAB II - lingkungan dan prosedur menjalankan backend", 1)
h("2.1 Tahapan menjalankan", 2)
steps = [
    "Copy file konfigurasi lingkungan: salin .env.example menjadi .env (file .env tidak ada pada source repository).",
    "Ubah parameter database pada .env: DB_DATABASE=luminpark (default laravel), DB_USERNAME=root, DB_PASSWORD kosong.",
    "Buat database MySQL: CREATE DATABASE luminpark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci.",
    "Pasang dependensi: composer install (folder vendor sudah tersedia pada source yang diuji).",
    "Bangkitkan kunci aplikasi: php artisan key:generate.",
    "Membuat struktur tabel: php artisan migrate (14 migration berhasil dijalankan tanpa error).",
    "Mengisi data awal: php artisan db:seed (UserSeeder membuat 3 akun: superadmin, manager, sales).",
    "Menjalankan server: php artisan serve --host=127.0.0.1 --port=8080.",
    "Verifikasi awal: GET / merespons HTTP 200 dan POST /api/login mengembalikan token.",
]
for i, s in enumerate(steps, 1):
    doc.add_paragraph(f"{i}. {s}")
para("Catatan pelaksanaan: pada mesin pengujian perintah php tidak tersedia di PATH, sehingga dieksekusi memakai path "
     "lengkap C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe. Port 8000 sudah dipakai proses lain sehingga "
     "dipilih port 8080.")

h("2.2 Akun uji hasil seeder", 2)
t = doc.add_table(rows=1, cols=4); t.style = "Light List Accent 1"
for i, hd in enumerate(["Email", "Password", "Role", "Ketersediaan Login"]):
    r = t.rows[0].cells[i].paragraphs[0].add_run(hd); r.bold = True
    shade(t.rows[0].cells[i], "D9E2F3")
for e, p_, rl, ok in [("superadmin@gmail.com", "password123", "manager (super admin)", "Berhasil (HTTP 200)"),
                      ("admin.sales@luminpark.com", "password123", "admin_sales", "Berhasil (HTTP 200)"),
                      ("sales@luminpark.com", "password123", "sales", "Berhasil (HTTP 200)")]:
    c = t.add_row().cells
    for i, v in enumerate([e, p_, rl, ok]):
        c[i].paragraphs[0].add_run(v).font.size = Pt(9)

h("2.3 Cakupan rute yang diuji", 2)
para("Sebanyak 57 rute API terdetifikasi melalui php artisan route:list. Pengujianfunctional mencakup seluruh kelompok "
     "rute utama: autentikasi (login/logout/me/reset-password), dashboard per role, CRUD master sales, CRUD unit, CRUD "
     "customer lead, follow-up history, skema pembayaran, booking, pembayaran beserta verifikasi, system log, laporan "
     "(sales/settlement/performance/export), API publik konsumen, serta uji keamanan akses tanpa token.")

doc.add_page_break()

# BAB III matrix
h("BAB III - rekap hasil per modul", 1)
t = doc.add_table(rows=1, cols=4); t.style = "Light List Accent 1"
for i, hd in enumerate(["Modul", "Total TC", "Lulus", "Gagal"]):
    r = t.rows[0].cells[i].paragraphs[0].add_run(hd); r.bold = True
    shade(t.rows[0].cells[i], "D9E2F3")
for m, (a, b) in mod_stats.items():
    c = t.add_row().cells
    for i, v in enumerate([m, str(a), str(b), str(a - b)]):
        c[i].paragraphs[0].add_run(v).font.size = Pt(9)
c = t.add_row().cells
for i, v in enumerate(["TOTAL", str(total), str(passed), str(failed)]):
    rr = c[i].paragraphs[0].add_run(v); rr.bold = True; rr.font.size = Pt(9)
    shade(c[i], "F2F2F2")

para("")
h("3.1 Klasifikasi penyebab kegagalan", 2)
cnt = collections.Counter(x["root"] for x in records if not x["passed"])
LABEL = {"SKEMA": "Skema database tidak lengkap: migration stub / tabel tidak ditemukan (HTTP 500)",
         "KODE": "Kode belum diimplementasikan: method controller atau relasi Eloquent hilang (HTTP 500)",
         "DATA": "Efek berantai: data acuan gagal dibuat sehingga read/update/delete 404 atau validasi exists 422",
         "SEC": "Rute sensitif lolos tanpa autentikasi (seharusnya HTTP 401)",
         "OK": "Lainnya"}
t = doc.add_table(rows=1, cols=2); t.style = "Light List Accent 1"
for i, hd in enumerate(["Kategori Penyebab", "Jumlah Test Case Gagal"]):
    r = t.rows[0].cells[i].paragraphs[0].add_run(hd); r.bold = True
    shade(t.rows[0].cells[i], "D9E2F3")
for k, v in cnt.most_common():
    c = t.add_row().cells
    c[0].paragraphs[0].add_run(LABEL.get(k, k)).font.size = Pt(9)
    c[1].paragraphs[0].add_run(str(v)).font.size = Pt(9)

doc.add_page_break()

# BAB IV main table
h("BAB IV - tabel hasil pengujian (format testcase)", 1)
para("Kolom sesuai format yang diminta: ID, Nama, Skenario, Deskripsi, Langkah Uji, Data Uji, Hasil yang Diharapkan, "
     "Hasil Aktual, Status (Pass/Fail), Catatan.", 9)

COLS = ["ID", "Nama", "Skenario", "Deskripsi", "Langkah Uji", "Data Uji",
        "Hasil yang Diharapkan", "Hasil Aktual", "Status (Pass/Fail)", "Catatan"]
W = [Cm(1.2), Cm(2.7), Cm(2.3), Cm(3.1), Cm(3.1), Cm(3.3), Cm(3.3), Cm(3.6), Cm(1.2), Cm(3.2)]

tab = doc.add_table(rows=1, cols=len(COLS))
tab.alignment = WD_TABLE_ALIGNMENT.CENTER
tab.autofit = False
for i, hd in enumerate(COLS):
    cell = tab.rows[0].cells[i]
    cell.text = ""
    rr = cell.paragraphs[0].add_run(hd)
    rr.bold = True; rr.font.size = Pt(8); rr.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
    shade(cell, "2E5496")
    cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER

def addrow(cells, texts, passed):
    for i, v in enumerate(texts):
        cell = cells[i]
        cell.text = ""
        p = cell.paragraphs[0]
        rr = p.add_run(v)
        rr.font.size = Pt(7)
        if i == 8:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            rr.bold = True
            rr.font.color.rgb = PASS if passed else FAIL
            shade(cell, "E3F2E3" if passed else "FBE4E4")

for x in records:
    texts = [x["id"], x["nama"], x["skenario"], x["deskripsi"], x["langkah"], x["dataUji"],
             x["ekspektasi"], x["actual_clean"], "PASS" if x["passed"] else "FAIL", x["note"]]
    addrow(tab.add_row().cells, texts, x["passed"])

for row in tab.rows:
    for i, c in enumerate(row.cells):
        c.width = W[i]
for i, w in enumerate(W):
    for row in tab.rows:
        row.cells[i].width = w

doc.add_page_break()

# BAB V detailed defect
h("BAB V - daftar cacat (defect register)", 1)
DEFECTS = [
 ("DF-01", "Kritis", "Skema database",
  "Migration untuk 8 tabel domain (sales_profiles, customer_leads, follow_up_histories, units, payment_schemes, "
  "bookings, payments, system_logs) hanya berisi $table->id() dan $table->timestamps().",
  f"Seluruh endpoint Create/Update/Filter/Aggregate menghasilkan HTTP 500 SQLSTATE[42S22]. Rantai penyebab ini "
  f"menjangkiti {N_SCHEMA + N_CHAIN} dari {total} test case ({N_SCHEMA} gagal langsung, {N_CHAIN} efek berantai).",
  "Lengkapi isi setiap migration dengan kolom sesuai $fillable pada Model dan aturan validasi pada controller, lalu "
  "jalankan php artisan migrate:fresh --seed."),
 ("DF-02", "Tinggi", "Konsistensi nama tabel",
  "Model App\\Models\\FollowUpHistory menetapkan $table = 'follow_up_history', sedangkan migration membuat "
  "follow_up_histories.",
  "GET/POST /api/follow-ups menghasilkan HTTP 500 SQLSTATE[42S02] Base table not found meski migration sudah dijalankan.",
  "Samakan nama: hapus $table pada Model ( Laravel akan menurunkan follow_up_histories ) atau ubah migration."),
 ("DF-03", "Tinggi", "Method controller hilang",
  "routes/api.php:26-27 mendaftarkan SalesController::resetPassword dan SalesController::performance, tetapi kedua "
  "method tersebut tidak ada pada SalesController (hanya index/store/show/update/destroy).",
  "POST /api/sales/{id}/reset-password dan GET /api/sales/{id}/performance menghasilkan HTTP 500 BadMethodCallException.",
  "Implementasikan kedua method atau hapus rutenya."),
 ("DF-04", "Tinggi", "Relasi Eloquent hilang",
  "ReportController::salesPerformanceReport memakai withCount(['customerLeads','bookings']) dan "
  "withSum('bookings','total_price'), namun SalesProfile hanya mendefinisikan relasi user().",
  "GET /api/reports/performance menghasilkan HTTP 500 Call to undefined method SalesProfile::customerLeads().",
  "Tambahkan relasi hasMany customerLeads() dan hasMany bookings() pada SalesProfile."),
 ("DF-05", "Kritis", "Keamanan / autorisasi",
  "Hanya group /api/login + auth:sanctum (logout, me, reset-password, dashboard/*) yang dilindungi. Seluruh "
  "apiResource (sales, units, customer-leads, bookings, payments, payment-schemes, follow-ups, system-logs) serta "
  "prefix reports dan public berada di luar proteksi.",
  "Data pribadi pelanggan, data finansial booking/payment, dan operasi penghapusan dapat dipanggil oleh siapa pun "
  "tanpa token. Terlihat pada TC-120/TC-121/TC-122: request menembus sampai controller (HTTP 500/404) alih-alih HTTP 401.",
  "Pindahkan rute bisnis ke dalam Route::middleware('auth:sanctum')->group(...), tambahkan permission checks "
  "berdasarkan role, dan berikan rate limiting tambahan."),
 ("DF-06", "Sedang", "Inkonsistensi nilai status unit",
  "PublicInfoController::publicUnits dan publicUnitDetail menyaring status = 'tersedia' (bahasa Indonesia), sedangkan "
  "UnitController::store memvalidasi status dalam available|booked|sold.",
  "Tidak mungkin ada unit yang lolos filter katalog publik: konsumen tidak akan pernah melihat unit.",
  "Bakukan satu vocabulary status (disarankan enumerated string bahasa Inggris) di seluruh controller dan Model."),
 ("DF-07", "Sedang", "Inkonsistensi status booking -> unit",
  "BookingController::store memperbarui status unit menjadi 'booking', padahal aturan validasi UnitController hanya "
  "menerima available|booked|sold.",
  "Setelah booking, unit tidak dapat lagi di-update melalui PUT /api/units/{id} karena nilai statusnya keluar dari "
  "domain validasi.",
  "Samakan konstanta status dan pindahkan nilai ke class constant/enum."),
 ("DF-08", "Sedang", "Domain status dashboard tidak konsisten",
  "DashboardController menghitung Unit::whereIn('status', ['booking','terjual']) dan Booking::whereIn('status', "
  "['dp lunas','cicilan','active']), menggabungkan bahasa Indonesia dan Inggris serta nilai 'active' yang tidak ada di "
  "enum booking.",
  "Angka units_sold, occupancy_rate, dan bookingAktif pada dashboard manager akan selalu kuranghitung.",
  "Gunakan enum yang sama dengan domain validasi."),
 ("DF-09", "Sedang", "Mass assignment field tidak terdaftar",
  "PublicInfoController::submitContactForm menulis key 'unit_id' dan 'source' ke CustomerLead, tetapi fillable model "
  "hanya memuat sales_id, name, phone, email, address, status, interested_unit, budget, note, last_follow_up. "
  "CustomerLead juga divalidasi memakai sales_id wajib.",
  "Sumber lead ('website') dan unit acuan hilang diam-diam; lead dari landing page tidak teratribusi.",
  "Tambahkan unit_id dan source ke fillable serta kolomnya pada migration, atau hapus penulisan key tersebut."),
 ("DF-10", "Rendah", "Tidak ada proteksi rate limit pada login",
  "Rute POST /api/login berada di group api dengan throttle 60 menit per IP tanpa pembatasan khusus login.",
  "Endpoint login rawan brute-force kredensial.",
  "Tambahkan RateLimiter khusus (mis. 5 attempt / menit per email) pada rute login."),
 ("DF-11", "Rendah", "Informasi teknis bocor ke klien",
  "Dengan APP_DEBUG=true, respons 500 mengungkap nama tabel, kolom, potongan SQL, dan absolut path filesystem.",
  "Membantu penyerang memetakan skema database (terlihat pada seluruh kolom Hasil Aktual di BAB IV).",
  "Selalu set APP_DEBUG=false dan LOG_LEVEL=warning pada lingkungan selain local."),
 ("DF-12", "Rendah", "Export laporan belum menghasilkan dokumen",
  "ReportController::exportReport hanya mengembalikan JSON dengan key report_type/exported_at.",
  "Klaim fitur 'Export PDF/Excel' belum terpenuhi secara fungsional.",
  "Integrasikan library dompdf/livewire-excel atau kembalikan stream berkas."),
]
t = doc.add_table(rows=1, cols=6); t.style = "Light List Accent 1"
DW = [Cm(1.3), Cm(1.9), Cm(3.6), Cm(7.4), Cm(6.2), Cm(6.4)]
for i, hd in enumerate(["ID", "Keparahan", "Area", "Uraian Cacat", "Dampak", "Rekomendasi"]):
    r = t.rows[0].cells[i].paragraphs[0].add_run(hd); r.bold = True
    shade(t.rows[0].cells[i], "D9E2F3")
for d in DEFECTS:
    c = t.add_row().cells
    for i in range(6):
        c[i].text = ""
        rr = c[i].paragraphs[0].add_run(str(d[i])); rr.font.size = Pt(7.5)
        if i in (0, 1): rr.bold = True
        if i == 1 and d[1] == "Kritis": rr.font.color.rgb = FAIL
        c[i].width = DW[i]
for row in t.rows:
    for i, c in enumerate(row.cells):
        c.width = DW[i]

doc.add_page_break()

# BAB VI
h("BAB VI - kesimpulan dan rekomendasi", 1)
h("6.1 Kesimpulan", 2)
for s in [
  "Backend LuminPark berhasil disiapkan dan dijalankan tanpa kesalahan boot: 14 migration lolos, seeder menghasilkan 3 "
  f"akun role, dan 57 rute API terpetakan.",
  f"Lapisan autentikasi & sesi merupakan bagian paling matang: {AUTH_PASS} dari {AUTH_TOT} test case LULUS, mencakup "
  "penolakan password salah (401), validasi format email dan field kosong (422), penolakan token palsu (401), "
  "pencabutan token setelah logout (401), serta penolakan lintas role oleh RoleMiddleware (403).",
  "Validasi input pada controller berjalan benar secara umum: aturan required, email, numeric min:0, in:enum, unique, dan "
  "exists seluruhnya menghasilkan HTTP 422 dengan pesan yang tepat.",
  f"Sebaliknya, lapisan persistensi belum layak dipakai. {failed} dari {total} test case ({failed/total*100:.1f}%) gagal: "
  f"{N_SCHEMA} gagal langsung karena migration domain belum berisi kolom, {N_CHAIN} merupakan efek berantai dari gagalnya "
  f"pembuatan data acuan, {N_CODE} karena method/relasi yang belum ditulis, dan {N_SEC} karena rute sensitif tidak dilindungi.",
  "Terdapat satu cacat keamanan kritis (DF-05): hampir seluruh endpoint CRUD dan laporan dapat diakses tanpa autentikasi apa pun.",
  "Verdict pengujian: Backend FAILED FOR ACCEPTANCE. Perlu perbaikan DF-01, DF-02, DF-03, DF-04, DF-05 sebelum pengujian ulang."
]:
    doc.add_paragraph(s, style="List Bullet")

h("6.2 Prioritas perbaikan", 2)
t = doc.add_table(rows=1, cols=4); t.style = "Light List Accent 1"
for i, hd in enumerate(["Prioritas", "Item", "Usaha", "Verifikasi"]):
    r = t.rows[0].cells[i].paragraphs[0].add_run(hd); r.bold = True
    shade(t.rows[0].cells[i], "D9E2F3")
for p, item, effort, ver in [
    ("P1", "DF-05 Bungkus seluruh rute bisnis dalam auth:sanctum + checks role", "Kecil",
     "Ulangi TC-120/121/122; seluruhnya harus HTTP 401"),
    ("P1", "DF-01 Lengkapi 8 migration kolom domain", "Besar",
     "Ulangi TC-021, TC-031, TC-061, TC-071, TC-081, TC-113; harus HTTP 201"),
    ("P1", "DF-02 Samakan nama tabel follow_up_history(s)", "Kecil", "Ulangi TC-050/TC-051"),
    ("P2", "DF-03 Implementasikan SalesController::performance dan resetPassword", "Sedang", "Ulangi TC-026/TC-028"),
    ("P2", "DF-04 Tambahkan relasi customerLeads() dan bookings() pada SalesProfile", "Kecil", "Ulangi TC-102"),
    ("P3", "DF-06/07/08 Bakukan enum status unit, booking, dan dashboard", "Sedang",
     "Ulangi TC-110/TC-111/TC-010 dan alur booking-unit"),
    ("P3", "DF-09/10/11/12 Perbaikan atribusi lead, rate limit login, hardening debug, export nyata", "Sedang",
     "Regression test menyeluruh")]:
    c = t.add_row().cells
    for i, v in enumerate([p, item, effort, ver]):
        rr = c[i].paragraphs[0].add_run(v); rr.font.size = Pt(8)
        if i == 0: rr.bold = True

para("")
para("Laporan ini dihasilkan dari eksekusi nyata terhadap server backend yang sedang berjalan. Seluruh nilai pada kolom "
     "\"Hasil Aktual\" merupakan respons HTTP yang benar-benar diterima selama pengujian, bukan hasil simulasi.", 9)

doc.add_page_break()
h("Lampiran A - bukti ketidakcocokan skema database", 1)
para("Kolom aktual di database luminpark dibandingkan dengan kolom yang demanded oleh property fillable pada Model. "
     "Seluruh selisih berikut menjadi penyebab langsung error SQLSTATE[42S22].", 9)
EVIDENCE = [
 ("units", "id, created_at, updated_at",
  "block_number, unit_type, land_size, building_size, price, status, description", "7 kolom"),
 ("sales_profiles", "id, created_at, updated_at",
  "user_id, sales_code, target_sales, total_sales_achieved, commission_rate, join_date, status", "7 kolom"),
 ("customer_leads", "id, created_at, updated_at",
  "sales_id, name, phone, email, address, status, interested_unit, budget, note, last_follow_up", "10 kolom"),
 ("follow_up_histories / follow_up_history", "id, created_at, updated_at",
  "lead_id, sales_id, follow_up_date, status_before, status_after, method, note + selisih nama tabel", "7 kolom + 1 nama"),
 ("payment_schemes", "id, created_at, updated_at",
  "scheme_name, description, down_payment_percent, installment_months, interest_rate, is_active", "6 kolom"),
 ("bookings", "id, created_at, updated_at",
  "lead_id, sales_id, unit_id, payment_scheme_id, booking_date, booking_code, total_price, down_payment_amount, "
  "installment_amount, remaining_amount, status, delivery_status, notes", "13 kolom"),
 ("payments", "id, created_at, updated_at",
  "booking_id, payment_type, amount, payment_date, bank_name, account_number, account_holder, transfer_receipt, "
  "verification_status, verified_by, verified_at, notes", "12 kolom"),
 ("system_logs", "id, created_at, updated_at",
  "user_id, activity, description, ip_address, user_agent", "5 kolom"),
]
t = doc.add_table(rows=1, cols=4); t.style = "Light List Accent 1"
EW = [Cm(4.6), Cm(4.2), Cm(14.0), Cm(3.5)]
for i, hd in enumerate(["Tabel", "Kolom Aktual di DB", "Kolom yang Dituntut Model/Controller", "Selisih"]):
    r = t.rows[0].cells[i].paragraphs[0].add_run(hd); r.bold = True
    shade(t.rows[0].cells[i], "D9E2F3")
for e in EVIDENCE:
    c = t.add_row().cells
    for i, v in enumerate(e):
        c[i].text = ""
        c[i].paragraphs[0].add_run(v).font.size = Pt(7.5)
        c[i].width = EW[i]
for row in t.rows:
    for i, c in enumerate(row.cells):
        c.width = EW[i]
para("")
para("Perintah pembuktian: SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE "
     "TABLE_SCHEMA='luminpark' ORDER BY TABLE_NAME, ORDINAL_POSITION;", 8.5)

h("Lampiran B - berkas artefak pengujian", 1)
for s in ["tests/api-test-harness.mjs - helper HTTP dan pencatat hasil.",
          "tests/run-api-tests.mjs - 65 test case utama (eksekusi nyata terhadap http://127.0.0.1:8080/api).",
          "tests/run-api-tests-extra.mjs - 11 test case pelengkap.",
          "tests/api-test-results.json dan tests/api-test-results-extra.json - respons mentah tiap test case.",
          "tests/build_report.py - generator dokumen laporan ini."]:
    doc.add_paragraph(s, style="List Bullet")

out = f"{BASE}/Laporan-Testing-Backend-LuminPark.docx"
doc.save(out)
print("SAVED:", out)
print(f"total={total} pass={passed} fail={failed}")
