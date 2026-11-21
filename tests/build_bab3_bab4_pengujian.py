# -*- coding: utf-8 -*-
"""Dokumen gabungan BAB III + BAB IV pengujian sistem LuminPark.

BAB III (Implementasi dan Pengujian) ditulis di sini: tujuan implementasi, metode,
lingkungan, format pencatatan sepuluh kolom, lalu skenario pengujian awal yang disusun
berdasarkan fungsi utama sistem. Sumber datanya tests/uji_skenario_bab3.py (baris
skenario) dan tests/uji_peta_fitur.py (pengantar tiap fitur) sehingga jumlah skenario,
kode fitur, dan rujukan test case tidak pernah berbeda dari dokumen uji lain.

BAB IV tidak ditulis ulang: tests/build_bab4_pengujian.py dipanggil lebih dulu, lalu isi
dokumennya ditempel setelah BAB III. Dengan begitu setiap angka hasil eksekusi tetap
berasal dari satu sumber (tests/*-results.json).

Perintah:
  C:/laragon/bin/python/python-3.13/python.exe -Xutf8 tests/build_bab3_bab4_pengujian.py
"""
import datetime
import json
import re
import subprocess
import sys
from copy import deepcopy

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

sys.path.insert(0, "C:/laragon/www/luminpark-main/tests")
from uji_peta_fitur import FITUR  # noqa: E402
from uji_skenario_bab3 import LANGKAH, SKENARIO  # noqa: E402

BASE = "C:/laragon/www/luminpark-main"
HARI = datetime.date.today()
HARI_ID = {1: "Januari", 2: "Februari", 3: "Maret", 4: "April", 5: "Mei", 6: "Juni", 7: "Juli",
           8: "Agustus", 9: "September", 10: "Oktober", 11: "November", 12: "Desember"}
tanggal = f"{HARI.day} {HARI_ID[HARI.month]} {HARI.year}"

hasil_uji = {}
for berkas in ("conformance", "hardening", "frontend", "nf"):
    for r in json.load(open(f"{BASE}/tests/{berkas}-results.json", encoding="utf-8")):
        if berkas == "hardening":
            # suite penguatan menyimpan rekamannya pada kolom catatan
            r["actualBody"] = r.get("actualBody") or r.get("catatan") or ""
        hasil_uji[r["id"]] = r

GREEN = RGBColor(0x1B, 0x7F, 0x3B)
RED = RGBColor(0xB0, 0x1F, 0x1F)
AMBER = RGBColor(0xB0, 0x6A, 0x00)
HDR = "2E5496"

b3 = Document()
b3.styles["Normal"].font.name = "Calibri"
b3.styles["Normal"].font.size = Pt(10)
sec = b3.sections[0]
sec.orientation = WD_ORIENT.LANDSCAPE
sec.page_width, sec.page_height = Cm(29.7), Cm(21.0)
for m in ("top_margin", "bottom_margin", "left_margin", "right_margin"):
    setattr(sec, m, Cm(1.2))


def shade(cell, hexc):
    el = OxmlElement("w:shd")
    el.set(qn("w:val"), "clear")
    el.set(qn("w:fill"), hexc)
    cell._tc.get_or_add_tcPr().append(el)


def para(text, size=10, bold=False, align=None):
    p = b3.add_paragraph()
    r = p.add_run(text)
    r.font.size = Pt(size)
    r.bold = bold
    if align:
        p.alignment = align
    return p


def bullet(text):
    b3.add_paragraph(text, style="List Bullet")


def head(text, lvl=1):
    b3.add_heading(text, level=lvl)


def repeat_header(t):
    pr = t.rows[0]._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    pr.append(el)


def table(headers, widths, data, sizes=None, bold_cols=(), center_cols=(), status_col=None, repeat=True):
    t = b3.add_table(rows=1, cols=len(headers))
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


def potong(teks, n=150):
    teks = (teks or "-").strip()
    return teks if len(teks) <= n else teks[:n].rstrip() + "…"


def samarkan(teks):
    """Token sesi hasil uji tidak boleh tercetak pada laporan."""
    return re.sub(r'(access_token"\s*:\s*")[^"]+', r"\1••••••••", teks or "")


# ============================== validasi sumber skenario ==============================
nomor_fitur = {}
for idx, (kode, judul, _, daftar) in enumerate(FITUR, 1):
    nomor_fitur[kode] = (idx, judul, set(daftar))

for i, baris in enumerate(SKENARIO):
    kode, _, _, _, langkah, _, _, rujukan = baris
    assert kode in nomor_fitur, f"skenario {i}: fitur {kode} tidak dikenal"
    assert langkah and all(isinstance(s, str) for s in langkah), f"skenario {i}: langkah uji kosong"
    assert rujukan, f"skenario {i} ({kode}): tidak merujuk test case"
    asing = [t for t in rujukan if t not in nomor_fitur[kode][2]]
    assert not asing, f"skenario {i} ({kode}): merujuk test case fitur lain: {asing}"
    hilang = [t for t in rujukan if t not in hasil_uji]
    assert not hilang, f"skenario {i} ({kode}): test case tidak tercatat pada suite: {hilang}"

assert len(set((b[0], b[1]) for b in SKENARIO)) == len(SKENARIO), "ada nama skenario ganda dalam satu fitur"

# ============================== BAB III ==============================
para("BAB III", 20, True, WD_ALIGN_PARAGRAPH.CENTER)
para("IMPLEMENTASI DAN PENGUJIAN", 16, True, WD_ALIGN_PARAGRAPH.CENTER)
para("SISTEM INFORMASI MANAJEMEN PERUMAHAN LUMIN PARK BERBASIS WEB", 12, True, WD_ALIGN_PARAGRAPH.CENTER)
b3.add_paragraph()

head("A. Tujuan Implementasi", 1)
para("Tahap implementasi merupakan pelaksanaan hasil analisis kebutuhan dan perancangan sistem pada BAB II menjadi "
     "sistem informasi manajemen perumahan Lumin Park yang benar-benar berjalan. Tujuan yang ingin dicapai pada "
     "tahap ini adalah:")
for t in [
    "mewujudkan seluruh fungsi utama sistem — autentikasi tiga peran, manajemen data proyek dan unit, pencatatan "
    "calon konsumen beserta tindak lanjutnya, pemesanan unit, pelunasan pembayaran, data sales, dashboard, laporan, "
    "dan halaman publik konsumen;",
    "menerapkan hak akses berbasis peran (super admin, Admin Sales, dan Sales) baik pada lapisan API maupun pada "
    "lapisan antarmuka, termasuk pembatasan hanya-lihat bagi Admin Sales;",
    "menegakkan aturan bisnis yang disepakati, yaitu konsumen hanya dihitung dari pembayaran terverifikasi, nomor "
    "handphone hanya sekali pakai, pemesanan hanya untuk prospek berstatus hot, dan biaya tambahan konstruksi "
    "dicatat di luar harga utama unit;",
    "menyediakan jejak audit dan sesi login sebagai bahan pengawasan pengelolaan data;",
    "menyajikan konten halaman publik yang dapat dikendalikan super admin tanpa membuka jalur penulisan data bagi "
    "konsumen; serta",
    "memastikan sistem memenuhi kebutuhan non-fungsional berupa waktu respons, keamanan kata sandi, validasi "
    "berlapis, pesan kesalahan berbahasa Indonesia, dan penghapusan data bersifat lunak.",
]:
    bullet(t)
para(f"Seluruh tujuan tersebut telah direalisasikan pada basis kode Laravel 10 dengan 22 tabel basis data, satu "
     f"aplikasi Blade untuk internal, dan enam halaman publik bagi konsumen. Realisasi tiap butir kebutuhan "
     f"dibuktikan pada BAB II dan diukur kembali melalui pengujian yang dipaparkan pada bagian B bab ini.")

head("B. Pengujian Sistem", 1)
para("Pengujian sistem dilakukan untuk memastikan setiap fungsi yang dirancang bekerja sebagaimana diharapkan "
     "sebelum sistem diserahkan kepada pengguna. Dua metode dipakai secara berpasangan: black box testing untuk "
     "memeriksa perilaku fungsional sistem dari sisi masukan dan keluaran tanpa melihat kode internal, serta user "
     "acceptance test (UAT) untuk memastikan alur kerja pengguna benar-benar dapat dijalankan sampai tuntas. "
     "Skenario pengujian awal disusun berdasarkan fungsi utama sistem dan digunakan sebagai acuan dalam proses "
     "pengujian; hasil pelaksanaan tiap barisnya dilaporkan pada BAB IV.")

head("B.1 Metode Pengujian", 2)
para("Black box testing dipilih karena kebutuhan sistem dinyatakan dalam bentuk fungsi yang dapat diamati pengguna: "
     "sebuah permintaan dibuat, lalu keluaran dan perubahan datanya diperiksa. Skenario disusun menjadi tiga "
     "kelompok, yaitu pengujian positif (fungsi berjalan sesuai kebutuhan), pengujian negatif (sistem menolak "
     "masukan salah atau permintaan di luar hak), dan pengujian batas (aturan bisnis pada nilai tepi seperti nominal "
     "nol, nomor ganda, dan tiga kali gagal login). UAT dilakukan dengan menjalankan alur kerja nyata tiap peran "
     "mulai dari login sampai laporan diperoleh.")
table(["Aspek", "Black Box Testing", "User Acceptance Test (UAT)"], [Cm(4.0), Cm(11.2), Cm(11.2)],
      [
          ["Sudut pandang", "Fungsi sistem diuji dari masukan dan keluaran tanpa memeriksa kode internal.",
           "Kelengkapan alur kerja dinilai dari sudut pengguna akhir pada tiap peran."],
          ["Objek uji", "Endpoint API REST, antarmuka halaman, validasi, hak akses, dan kondisi basis data.",
           "Skenario bisnis end-to-end: calon konsumen sampai pelunasan dan laporan."],
          ["Cara penyusunan", "Diturunkan dari butir kebutuhan fungsional dan non-fungsional pada BAB II serta "
                              "daftar revisi sistem.",
           "Diturunkan dari alur kerja pengguna dan hasil wawancara kebutuhan."],
          ["Data uji", "Data demo hasil seeder pada basis data uji beserta masukan tepi yang disiapkan skrip.",
           "Akun tiap aktor dan transaksi yang dijalankan seperti pemakaian sebenarnya."],
          ["Pelaksana", "Tim pengembang dengan suite otomatis.",
           "Tim pengembang berperan sebagai pengguna, ditambah penguji eksternal sebagai konsumen."],
          ["Kriteria hasil", "Status Pass bila keluaran sama dengan hasil yang diharapkan.",
           "Status Diterima bila alur dapat diselesaikan dari awal sampai akhir tanpa galat."],
          ["Luaran", "Daftar test case beserta hasil aktual pada BAB IV bagian A.",
           "Daftar skenario penerimaan beserta rekapitulasi pada BAB IV bagian B."],
      ], sizes=[9, 9, 9], bold_cols=(0,))

head("B.2 Lingkungan, Alat, dan Data Pengujian", 2)
table(["Komponen", "Keterangan"], [Cm(5.5), Cm(21.8)],
      [
          ["Perangkat lunak inti", "Laravel 10 pada PHP 8.3.33, MySQL 8.4.3, dan Apache Laragon; antarmuka "
                                   "menggunakan Blade, Tailwind CSS, dan JavaScript klien tanpa framework tambahan."],
          ["Perangkat keras", "Laptop/workstation Windows 10 x64, prosesor empat inti, RAM 16 GB, penyimpanan SSD; "
                              "seluruh layanan berjalan pada satu mesin melalui 127.0.0.1."],
          ["Alat uji fungsional", "Suite Node.js 22: tests/run-conformance-tests.mjs (permintaan API nyata), "
                                  "tests/run-hardening-tests.mjs (jejak audit, sesi, laju login, proyek, dan "
                                  "pemisahan data sales), serta observasi DOM pada peramban Chromium untuk "
                                  "pengujian antarmuka."],
          ["Alat uji non-fungsional", "tests/run-nf-tests.mjs untuk pengukuran waktu respons dari sisi klien, probe "
                                      "basis data MySQL, dan agregasi seluruh hasil uji; php artisan tinker dipakai "
                                      "untuk pemeriksaan langsung."],
          ["Lingkungan uji", "http://127.0.0.1:8081/api dengan basis data luminpark_test, disiapkan ulang melalui "
                             "migrate:fresh --seed agar setiap angka berasal dari data demo yang dikenal."],
          ["Lingkungan tampilan", "http://127.0.0.1:8000 dengan basis data kerja luminpark, hanya dipakai untuk "
                                  "memperhatikan antarmuka dan bukan sebagai objek pengujian angka."],
          ["Akun uji", "superadmin@gmail.com (Super Admin), admin.sales@luminpark.com (Admin Sales), serta sales@, "
                       "sales2@, dan sales3@luminpark.com (Sales) dengan sandi password123; satu akun konsumen "
                       "dipakai untuk membuktikan penolakan login."],
          ["Data uji dasar", "Data demo berupa perumahan, proyek, unit dengan harga dan status, calon konsumen "
                             "cold/warm/hot, booking dengan tiga skema pelunasan, pembayaran beserta bukti transfer, "
                             "kategori DP/Cicilan/Biaya Tambahan, dan kabar terbaru halaman publik."],
          ["Tanggal pengujian", tanggal],
      ], sizes=[9, 9], bold_cols=(0,))

head("B.3 Format Pencatatan dan Kriteria Hasil", 2)
para("Setiap skenario dan test case dicatat pada tabel dengan sepuluh kolom berikut.")
table(["Kolom", "Isi yang dicatat"], [Cm(5.5), Cm(21.8)],
      [
          ["ID", "Kode baris. Skenario awal pada BAB III memakai pola SP-<kode fitur> <nomor>; test case hasil "
                 "eksekusi pada BAB IV memakai pola <kode fitur> <nomor> dan mencantumkan identitas test case "
                 "skrip (TC-*, FF-*, NF-*) pada kolom Catatan."],
          ["Nama", "Nama fungsi atau pengujian yang dijalankan."],
          ["Skenario", "Tujuan satu kalimat dari pengujian tersebut."],
          ["Deskripsi", "Konteks fungsi, modul, dan aturan bisnis yang dijaga."],
          ["Langkah Uji", "Urutan langkah pelaksanaan, dimulai dari pembukaan halaman atau pengiriman permintaan."],
          ["Data Uji", "Masukan, kredensial, nilai batas, atau berkas yang dipakai pada pengujian."],
          ["Hasil yang Diharapkan", "Keluaran yang diwajibkan kebutuhan, termasuk kode status dan perubahan data."],
          ["Hasil Aktual", "Rekaman keluaran sesungguhnya: kode status beserta potongan respons untuk uji API, "
                           "fakta DOM untuk uji antarmuka, dan angka terukur untuk uji non-fungsional."],
          ["Status (Pass/Fail)", "Pass bila Hasil Aktual memenuhi Hasil yang Diharapkan; Fail bila tidak."],
          ["Catatan", "Identitas test case pada skrip otomatis, penjelasan selisih, atau rujukan butir kebutuhan."],
      ], sizes=[9, 9], bold_cols=(0,))
para("Kriteria penilaian yang dipakai pada kedua metode:")
table(["Status", "Kriteria", "Tindak lanjut"], [Cm(3.0), Cm(11.7), Cm(11.7)],
      [
          ["Pass", "Keluaran aktual sama dengan hasil yang diharapkan tanpa galat sisa.",
           "Baris dinyatakan lulus dan menjadi bukti pada matriks kebutuhan."],
          ["Fail", "Keluaran aktual berbeda dari yang diharapkan, terjadi galat server, atau fungsi tidak muncul.",
           "Diperbaiki pada iterasi berikutnya lalu seluruh suite dijalankan ulang."],
          ["Diterima", "Seluruh alur skenario UAT dapat diselesaikan pengguna dari awal sampai akhir.",
           "Dicatat pada rekapitulasi UAT dan ditandatangani pada lembar pengesahan."],
          ["Sebagian", "Kebutuhan terpenuhi tetapi cara pencapaiannya berbeda dari rancangan awal, misalnya kolom "
                       "basis data tetap memakai nama cluster.",
           "Dokumentasi perbedaan disertai alasan pada catatan kesesuaian."],
      ], sizes=[9, 9, 9], bold_cols=(0,), status_col=0)

head("B.4 Skenario Pengujian Awal", 2)
para(f"Skenario pengujian awal disusun berdasarkan fungsi utama sistem dan digunakan sebagai acuan dalam proses "
     f"pengujian. Terdapat {len(SKENARIO)} skenario yang dikelompokkan menurut {len(FITUR)} fitur utama; setiap "
     f"barisnya memuat rencana pelaksanaan beserta test case yang mewujudkannya. Kolom Hasil Aktual dan Status "
     f"diisi dari rekaman eksekusi test case yang dirujuk baris tersebut, sehingga satu skenario dapat ditelusuri "
     f"ke bukti nyatanya pada BAB IV bagian A.4 tanpa mengulang paparan respons secara penuh.")
para("Legenda kode fitur: " + ", ".join(f"{kode} = {judul}" for kode, judul, _, _ in FITUR) + ".", 9, True)

COLS = ["ID", "Nama", "Skenario", "Deskripsi", "Langkah Uji", "Data Uji", "Hasil yang Diharapkan",
        "Hasil Aktual", "Status\n(Pass/Fail)", "Catatan"]
W = [Cm(1.5), Cm(2.4), Cm(1.9), Cm(3.2), Cm(3.0), Cm(2.7), Cm(3.1), Cm(3.6), Cm(1.2), Cm(3.7)]
UKURAN = [6.5] * 10

# satu tabel per fitur; penomoran SP-<kode> <nomor> mengikuti urutan baris dalam fiturnya
berkas_per_fitur = []
for baris in SKENARIO:
    if berkas_per_fitur and berkas_per_fitur[-1][0] == baris[0]:
        berkas_per_fitur[-1][1].append(baris)
    else:
        berkas_per_fitur.append((baris[0], [baris]))
assert [k for k, _ in berkas_per_fitur] == [f[0] for f in FITUR], \
    "urutan kelompok skenario tidak sama dengan peta fitur BAB IV"

gagal_total = 0
for kode, isi in berkas_per_fitur:
    idx, judul, _ = nomor_fitur[kode]
    head(f"B.4.{idx} {judul}", 2)
    para(FITUR[idx - 1][2], 9)
    data = []
    for n, (_, nama, sken, desk, lang, bahan, harap, rujukan) in enumerate(isi, 1):
        belum_lulus = [t for t in rujukan if hasil_uji[t]["status"] != "Pass"]
        bukti = samarkan(potong(hasil_uji[rujukan[0]].get("actualBody") or hasil_uji[rujukan[0]]["skenario"]))
        gagal_total += 1 if belum_lulus else 0
        data.append([f"SP-{kode} {n:02d}", nama, sken, desk, LANGKAH(lang), bahan, harap, bukti,
                     "Fail" if belum_lulus else "Pass",
                     f"Diwujudkan {len(rujukan)} test case: {', '.join(rujukan)}"
                     + (f"; belum lulus: {', '.join(belum_lulus)}." if belum_lulus
                        else f". Hasil pelaksanaan pada BAB IV A.4.{idx}.")])
    table(COLS, W, data, sizes=UKURAN, status_col=8)
para(f"Sebanyak {len(SKENARIO)} skenario awal di atas dilaksanakan pada {len(berkas_per_fitur)} fitur utama; "
     f"{len(SKENARIO) - gagal_total} baris dinyatakan Pass dan {gagal_total} baris Fail pada tanggal pengujian "
     f"{tanggal}. Rincian eksekusi setiap barisnya disajikan pada BAB IV bagian A.4.", 9, True)

b3.add_page_break()

head("C. Dokumentasi Implementasi", 1)
para("Bagian berikut mendokumentasikan layar yang dihasilkan implementasi beserta peran yang dapat membukanya. "
     "Tangkapan layar tiap halaman dimasukkan pada penyusunan akhir laporan, sementara fungsi setiap layar telah "
     "dibuktikan melalui pengujian black box antarmuka yang dilaporkan pada BAB IV.")
LAYAR = [
    ["1", "Login dan atur sandi", "/login, /ganti-sandi, /reset-password",
     "Masuk tiga aktor, penggantian sandi wajib akun baru, dan pemulihan sandi lewat tautan email.",
     "Pengguna internal"],
    ["2", "Dashboard", "/dashboard",
     "Rekapitulasi unit terjual, pendapatan, prospek, konsumen, grafik tren, dan booking terbaru per peran; "
     "penapis per proyek bagi super admin.", "Semua peran"],
    ["3", "Data Unit Perumahan", "/data-unit dan /data-unit/{id}",
     "Daftar unit dengan penapis Blok, Status, dan Proyek; detail unit berisi formulir edit dan unggah foto.",
     "Super Admin (tulis), Admin Sales (baca)"],
    ["4", "Data Proyek", "/data-proyek",
     "Daftar proyek beserta aksi tambah, ubah, arsipkan, aktifkan, dan hapus.",
     "Super Admin (tulis), Admin Sales (baca)"],
    ["5", "Data Sales", "/data-sales dan /data-sales/{id}",
     "Kelola akun sales, target periode, reset sandi, nonaktifkan, serta seksi foto sales.",
     "Super Admin dan Admin Sales"],
    ["6", "Data Konsumen", "/data-konsumen (kategori konsumen/prospek) dan detailnya",
     "Calon konsumen, riwayat tindak lanjut, kolom Unit Ditempati dan Pesanan Tambahan, serta tombol Batal.",
     "Semua peran dengan tampilan berbeda"],
    ["7", "Booking Unit", "/booking",
     "Pemesanan unit dengan pilihan berjenjang Proyek lalu Unit, tiga skema pelunasan, permintaan tambahan, dan "
     "pencatatan pembayaran awal.", "Semua peran (tulis oleh sales)"],
    ["8", "Data Pembayaran", "/pembayaran",
     "Pencatatan pembayaran beserta bukti transfer, kategori DP/Cicilan/Biaya Tambahan, verifikasi, dan "
     "penolakan.", "Semua peran; verifikasi khusus Super Admin"],
    ["9", "Laporan", "/laporan",
     "Empat jenis laporan dengan ringkasan per proyek, ekspor CSV, dan lembar cetak berpengesahan.",
     "Super Admin dan Admin Sales"],
    ["10", "Laporan Sales", "/laporan-sales", "Laporan penjualan dan pelunasan milik sales yang login.", "Sales"],
    ["11", "Log Aktivitas", "/log-aktivitas", "Jejak audit data pada activity_logs berdampingan dengan log sistem.",
     "Super Admin"],
    ["12", "Sesi Login", "/sesi",
     "Daftar sesi aktif beserta alamat IP, perangkat, dan waktu masuk/keluar.", "Super Admin"],
    ["13", "Notifikasi", "/notifikasi dan lonceng header",
     "Kabar booking, pembayaran, dan hasil verifikasi milik pengguna yang login.", "Semua peran"],
    ["14", "Profil", "/profil", "Pemutakhiran data diri dan unggah foto profil.", "Semua peran"],
    ["15", "Pengaturan Sistem", "/pengaturan",
     "Batas waktu sesi, kebijakan laju dan penguncian login, serta nilai skema pembayaran.", "Super Admin"],
    ["16", "Pengaturan Halaman Publik", "/pengaturan-publik",
     "Saklar dan teks bagian halaman publik beserta kabar terbaru.", "Super Admin"],
    ["17", "Beranda publik", "/",
     "Pengenalan perumahan, peta lokasi proyek beserta rute, unit unggulan, dan kabar terbaru.", "Umum tanpa login"],
    ["18", "Katalog dan detail unit publik", "/unit dan /unit/{id}",
     "Unit tersedia beserta harga, spesifikasi, dan tombol WhatsApp narahubung.", "Umum tanpa login"],
    ["19", "Skema pembayaran publik", "/skema-pembayaran",
     "Perbandingan tiga skema pelunasan bagi calon konsumen.", "Umum tanpa login"],
    ["20", "Narahubung dan kontak", "/kontak",
     "Daftar sales dengan foto dan tautan WhatsApp; tanpa formulir isian konsumen.", "Umum tanpa login"],
    ["21", "Tentang Kami", "/tentang", "Profil pengembang, visi misi, dan berita; tidak memuat skema pembayaran.",
     "Umum tanpa login"],
]
table(["No", "Halaman", "Rute", "Fungsi utama", "Peran yang dapat membuka"],
      [Cm(1.0), Cm(4.4), Cm(5.6), Cm(10.2), Cm(5.8)], LAYAR, sizes=[8] * 5, bold_cols=(1,), center_cols=(0,))

# ============================== tempel BAB IV ==============================
proses = subprocess.run([sys.executable, "-Xutf8", "tests/build_bab4_pengujian.py"], cwd=BASE,
                       capture_output=True, text=True)
if proses.returncode != 0:
    raise SystemExit(f"penyusun BAB IV gagal:\n{proses.stdout}\n{proses.stderr}")
bab4 = next((b.split("Tersimpan: ", 1)[1].strip() for b in proses.stdout.splitlines()
             if b.startswith("Tersimpan: ")), None)
assert bab4, f"tidak menemukan jalur hasil pada keluaran:\n{proses.stdout}"

host = Document(bab4.replace("\\", "/"))
body = host.element.body
untuk = [el for el in b3.element.body if el.tag != qn("w:sectPr")]
for pos, el in enumerate(untuk):
    body.insert(pos, deepcopy(el))

OUT = f"{BASE}/Laporan-BAB-III-IV-Pengujian-dan-Evaluasi-Sistem-LuminPark.docx"
try:
    host.save(OUT)
except PermissionError:
    OUT = OUT.replace(".docx", f"-{HARI.isoformat()}.docx")
    host.save(OUT)
    print("PERINGATAN: berkas lama sedang dibuka Word, hasilnya disimpan sebagai berkas baru.")
print(f"Tersimpan: {OUT}")
print(f"Skenario {len(SKENARIO)} pada {len(berkas_per_fitur)} fitur | Pass {len(SKENARIO) - gagal_total} | "
      f"Fail {gagal_total} | tabel BAB III {len(b3.tables)} | elemen dipindah {len(untuk)}")
