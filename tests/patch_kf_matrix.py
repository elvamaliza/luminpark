# -*- coding: utf-8 -*-
"""Memperbarui matriks kebutuhan fungsional (tests/kf-matrix.json) dengan bukti
suite penguatan (tests/hardening-results.json) setelah batasan D-1, D-3, D-4,
D-5, D-6, dan D-7 ditutup.
"""
import json

PATH = "C:/laragon/www/luminpark-main/tests/kf-matrix.json"
kf = json.load(open(PATH, encoding="utf-8"))
indeks = {r["kode"]: r for r in kf}


def ubah(kode, *, impl=None, tambah=None, status=None, catatan=None):
    baris = indeks[kode]
    if impl:
        baris["impl"] = impl
    if tambah:
        baris["rujukan"] = list(dict.fromkeys(baris["rujukan"] + tambah))
    baris["bukti"] = ", ".join(baris["rujukan"])
    if status:
        baris["status"] = status
    if catatan:
        baris["catatan"] = catatan
    return baris


ubah(
    "a.4",
    tambah=["TC-P01", "TC-P02", "TC-P03", "FF-A21", "TC-R26", "FF-R34"],
    impl=("AuthController::logout menghapus CurrentAccessToken; masa berlaku token dibaca dari pengaturan "
          "session_timeout_minutes melalui SettingController::sessionTimeoutMinutes() "
          "(app/Http/Controllers/Api/SettingController.php, minimum 15 menit, baku 1.440 menit) dan dipakai sebagai "
          "expiresAt saat createToken; tabel login_sessions mencatat masuk, keluar, IP, dan perangkat. "
          "GET /session/status + POST /session/refresh (AuthController::sessionStatus/refreshSession) menjadi sumber "
          "penghitung waktu mundur pada header (public/js/luminpark.js). Sejak revisi 35 angka tersebut dibaca "
          "sebagai batas waktu tanpa aktivitas: pointerdown/keydown/wheel/touchstart diperdengarkan, layar yang "
          "ditinggalkan keluar otomatis ke /login begitu melewati batas, dan layar yang masih dipakai diperpanjang "
          "senyap sebelum token habis."),
    catatan=("Koreksi dari laporan sebelumnya: batas waktu tidak lagi diambil dari config/sanctum.php, melainkan dari "
             "halaman Pengaturan Sistem, dan label isian berubah menjadi 'Batas Waktu Tanpa Aktivitas (menit)' "
             "(revisi 35). Penutup batasan D-7: sisa waktu sesi terlihat pada chip header (FF-A21), dapat "
             "diperpanjang satu klik, dan token lama dicabut saat perpanjangan (TC-P03). Penguncian tiga kali gagal "
             "login diatur pada kunci terpisah login_lockout_minutes (revisi 34)."),
)

ubah(
    "b.1 cluster",
    tambah=["TC-M01", "TC-M02", "TC-M03", "TC-M04", "TC-M05", "TC-M06", "TC-M07", "TC-M08", "TC-M09", "TC-M10",
            "TC-M11", "FF-A20", "FF-B09", "FF-C07"],
    impl=("ClusterController (app/Http/Controllers/Api/ClusterController.php) menyediakan index/show/store/update/"
          "destroy + PATCH /clusters/{id}/arsipkan. Nama divalidasi Rule::unique('clusters','name') per proyek dan "
          "status hanya aktif|diarsipkan; penghapusan menolak 409 bila cluster masih menaungi unit dan bersifat "
          "lunak (kolom deleted_at). Rute baca untuk super_admin dan admin_sales, rute tulis khusus super_admin "
          "(routes/api.php). Antarmuka: tombol Tambah Cluster pada halaman Data Unit dan halaman khusus "
          "resources/views/app/data-cluster.blade.php berisi ringkasan, penapis, serta aksi Ubah/Arsipkan/Aktifkan/"
          "Hapus. Kolom Aksi hanya dirender untuk super_admin (bisaKelola), sehingga Admin Sales melihat lima kolom "
          "tanpa aksi tulis apa pun (FF-B09)."),
    catatan=("Penutup batasan D-6: pengelolaan cluster tidak lagi berhenti pada tambah. Cluster diarsipkan hilang "
             "dari formulir unit (TC-M05) namun riwayat unitnya tetap ada, dan cluster kosong boleh dihapus tanpa "
             "menghilangkan barisnya (TC-M09, NF-04). Bug GET /clusters/{id} yang mengembalikan 500 "
             "(Call to undefined method Cluster::merge()) ikut diperbaiki dan diuji lewat TC-M04."),
)

ubah(
    "d.4",
    tambah=["TC-N01", "TC-N02", "TC-N03", "TC-N04", "TC-N05", "TC-N06", "FF-C04"],
    status="Sesuai",
    impl=("PaymentController::index, BookingController::index, dan FollowUpHistoryController::index mempersempit "
          "kuantum memakai trait ScopesSalesData (app/Http/Traits/ScopesSalesData.php): peran sales hanya melihat "
          "baris yang booking/lead-nya berada di bawah sales_profile milik akunnya, dan show() melempar 403 "
          "'Data ini berada di bawah penanganan sales lain' untuk id di luar wilayahnya. Admin Sales dan super "
          "admin tidak dipangkas."),
    catatan=("Penutup batasan D-4: sebelumnya hanya daftar lead yang dipisah per sales. TC-N01/N03 membuktikan "
             "pemangkasan pada level daftar dan TC-N02/N04 pada level akses langsung lewat id; TC-N06 memastikan "
             "super admin tetap melihat seluruh data."),
)

ubah(
    "f.4",
    tambah=["FF-A22"],
    status="Sesuai",
    impl=("ReportController::salesReport/settlementReport/salesPerformanceReport/consumerReport dan exportReport "
          "(CSV) serta laporan khusus sales pada /laporan-sales. Tombol 'Ekspor PDF / Cetak' pada "
          "resources/views/app/laporan.blade.php dan laporan-sales.blade.php memanggil LP.cetakLaporan "
          "(public/js/luminpark.js) yang menyusun lembar A4: kop perumahan dari pengaturan estate_*, judul dan "
          "periode laporan, waktu cetak, lalu blok pengesahan 'Dibuat oleh' (nama dan peran pengguna yang login) "
          "dan 'Mengetahui, Pimpinan / Atasan langsung'."),
    catatan=("Penutup batasan D-3: PDF dibuat melalui dialog cetak peramban (Simpan sebagai PDF) dengan @page A4 "
             "portrait; header, sidebar, dan lonceng notifikasi disembunyikan lewat media query print sehingga "
             "lembar yang dihasilkan bersih. Ekspor CSV tetap dipertahankan untuk kebutuhan pengolahan angka."),
)

ubah(
    "ERD Gambar 2.24",
    tambah=["TC-O01", "TC-O02", "TC-O03", "FF-A23", "NF-05"],
    impl=("18 tabel domain terbentuk lengkap dengan foreign key dan kolom sesuai narasi: users, sales_profiles, "
          "projects, clusters, units, customer_leads, follow_up_histories, bookings, payment_schemes, payments, "
          "sales_targets, sales_reports, notifications, system_logs, login_sessions, activity_logs, settings, "
          "public_posts. Tabel activity_logs kini benar-benar ditulis lewat App\\Observers\\AuditObserver yang "
          "dipasang pada model dalam AppServiceProvider::AUDITABLE dan dibaca GET /activity-logs."),
    catatan=("Ditambah 4 tabel kerangka (migrations, failed_jobs, password_reset_tokens, personal_access_tokens) "
             "sehingga total 22 tabel. Nama tabel dipertahankan dalam bahasa Inggris; pemetaan ke nama Indonesia "
             "tersedia pada LAMPIRAN A laporan pembanding. Penutup batasan D-5: narasi ERD tidak lagi perlu "
             "direvisi karena activity_logs sudah terisi (NF-05) dan tampil pada tab 'Jejak audit data' halaman "
             "Log Aktivitas (FF-A23). Angka 14 pada narasi laporan tetap perlu dikoreksi menjadi 18."),
)

ubah(
    "Keamanan akses menyeluruh",
    tambah=["TC-M11", "TC-N02", "TC-N04", "TC-O03", "TC-Q01", "TC-Q02", "TC-Q03", "FF-C07", "TC-R24", "FF-R33"],
    impl=("Middleware auth:sanctum + RoleMiddleware pada grup rute internal; guard peran juga menyaring tombol pada "
          "antarmuka. Percobaan login dibatasi LoginThrottle — tiga kegagalan pada kunci email+IP mengunci akun "
          "selama login_lockout_minutes yang dipilih super admin, jendela hitung dimulai ulang tiap kegagalan, dan "
          "direset begitu berhasil; respons 401/403/404/422/429 seluruhnya membawa pesan berbahasa Indonesia lewat "
          "renderable pada app/Exceptions/Handler.php. Revisi 33 melepas konfigurasi Google dari antarmuka "
          "Pengaturan tanpa mengubah penyimpanan maupun pemakaiannya."),
    catatan=("Penutup batasan D-1 sebagian: RateLimiter login dan lokalisasi pesan sudah aktif di kode. Sisa "
             "pekerjaan adalah penetapan APP_DEBUG=false pada lingkungan produksi."),
)

# Keputusan pengguna 16 November 2026: form pengisian calon konsumen di halaman publik dihapus,
# sehingga rujukan TC-H07 (uji validasi form tersebut) tidak berlaku lagi.
indeks["g.4"]["rujukan"] = [k for k in indeks["g.4"]["rujukan"] if k != "TC-H07"]
ubah(
    "g.4",
    tambah=["FF-D08"],
    impl=("PublicInfoController::salesContacts mengembalikan nama, nomor telepon, dan tautan wa.me dari "
          "sales_profiles aktif; halaman /kontak serta tombol Hubungi Sales pada detail unit hanya menampilkan "
          "narahubung tersebut. Formulir pengisian calon konsumen beserta rute POST /api/public/contact, method "
          "submitContactForm(), dan pembatasan laju 'public-form' dihapus."),
    catatan=("Halaman publik tidak lagi memiliki jalur tulis tanpa login, sehingga tidak ada lead yang masuk "
             "otomatis dari situs. Konsumen menghubungi sales lewat WhatsApp, lalu sales mencatat calon konsumen "
             "dari halaman Data Konsumen (TC-H05, FF-D02). TC-H06 menjaga agar POST /api/public/contact tetap "
             "mengembalikan 404."),
)

# Keputusan pengguna 16 November 2026: hak hanya-lihat Admin Sales dipertebal pada antarmuka.
ubah(
    "Keputusan hak hanya-lihat",
    tambah=["FF-B10", "FF-B11"],
    impl=("Group role:super_admin untuk endpoint tulis unit/lead/booking/pembayaran dan role:super_admin untuk tulis "
          "cluster; RoleMiddleware menolak 403. Pada antarmuka Admin Sales tidak lagi melihat kendali tulis: judul "
          "detail unit menjadi \"Data Unit\" (tanpa kata Ubah) dengan seluruh isian terkunci dan tanpa pemilih "
          "\"Ganti Foto\", tombol Kelola Cluster/Tambah Unit/Tambah Cluster disembunyikan, kolom Aksi pada Data "
          "Cluster tidak dirender, dan baris unit hanya menyediakan tombol Detail."),
    catatan=("Keputusan pengguna hasil elisitasi ulang; tidak terdapat pada daftar a-h laporan awal. Dipertebal "
             "16 November 2026 atas permintaan \"admin sales hanya bisa melihat detail dari unit bukan mengubah\", "
             "\"hapus kata ubah dari Ubah Data Unit\", \"hapus tombol kelola cluster\", dan \"kolom aksi pada menu "
             "data cluster dihapus saja\"."),
)

# Butir baru: lokalisasi sistem (penutup D-1 sisi pesan).
for r in kf:
    if r["kode"] == "Tambahan: lokalisasi pesan":
        r["no"] = "42i"

if "Tambahan: lokalisasi pesan" not in indeks:
    kf.append({
        "no": "42i",
        "kode": "Tambahan: lokalisasi pesan",
        "kebutuhan": "Seluruh pesan kesalahan sistem berbahasa Indonesia, termasuk pesan bawaan kerangka",
        "impl": ("APP_LOCALE=id dengan fallback en pada config/app.php, terjemahan aturan pada "
                 "app/Lang/id/validation.php (termasuk pesan custom unique/exists/numeric), dan renderable untuk "
                 "ValidationException, ModelNotFoundException, NotFoundHttpException, serta AuthenticationException "
                 "pada app/Exceptions/Handler.php."),
        "bukti": "TC-Q04, TC-Q05, NF-08",
        "status": "Sesuai",
        "catatan": ("Penutup batasan D-1 sisi pesan: 'The given data was invalid.', 'Unauthenticated.', dan "
                    "'No query results for model ...' sudah tidak muncul lagi; 57 dari 57 respons 4xx membawa "
                    "pesan Indonesia (NF-08)."),
        "rujukan": ["TC-Q04", "TC-Q05", "NF-08"],
    })

# --- Daftar revisi 1-13 hasil elisitasi ulang (November 2026) -------------------------------
# Setiap butir diberi baris sendiri supaya dapat ditelusuri pada tabel A.2, dengan rujukan ke
# test case API (TC-R..) dan observasi antarmuka (FF-R..) yang sungguh dijalankan.
REVISI = [
    (
        "Revisi 1", "Foto sales pada daftar narahubung halaman publik",
        "Menambahkan foto sales di daftar kontak pada halaman publik yang menangani atau bertanggung jawab atas "
        "konsumen tersebut.",
        "PublicInfoController::salesContacts menyertakan avatar_url dari relasi user setiap sales_profiles aktif "
        "bernomor WhatsApp; /kontak, seksi narahubung pada detail unit, dan seksi Tim Sales Kami pada /tentang "
        "memanggil LP.fotoSales() (public/js/luminpark.js) yang merender <img alt=\"Foto <nama>\"> dan jatuh ke "
        "lingkaran inisial bila avatar belum diunggah. Sejak revisi 36 kartu narahubung memakai foto 80×80 px "
        "(h-20 w-20) dan avatarnya diunggah pengelola dari seksi 'Foto Sales' pada halaman Detail Sales.",
        ["TC-R07", "TC-R27", "FF-R01", "FF-R35", "FF-D02"],
        "Tiga kartu narahubung terukur di peramban (basis data uji, port 8081): dua kartu memakai <img> 80×80 px "
        "dengan berkas avatar yang membalas HTTP 200 dan satu kartu memakai inisial 'A'; unggah foto dari "
        "/data-sales/4 berbalas toast 'Data sales berhasil diperbarui' lalu avatar_url sales yang sama muncul "
        "kembali pada GET /public/sales-contacts (TC-R27, FF-R35).",
    ),
    (
        "Revisi 2", "Tanggal chat awal pada form tambah konsumen peran Sales",
        "Pada form tambah konsumen di role Sales, tambahkan kolom tanggal chat awal untuk mencatat kapan pertama "
        "kali konsumen menghubungi sales.",
        "Migration 2026_11_19_090000_add_revisi_proyek_columns menambah customer_leads.first_chat_date (date, "
        "nullable); CustomerLeadController::store dan ::update memvalidasi first_chat_date sebagai nullable|date "
        "dan field 'Tanggal Chat Awal' muncul pada modal konsumen peran Sales dengan nilai baku hari ini, tampil "
        "sebagai kolom 'Chat Awal' pada daftar, dibaca kembali di halaman detail, serta ikut pada ekspor CSV "
        "laporan konsumen (ReportController::exportReport).",
        ["TC-R01", "TC-C01", "FF-R02"],
        "Isi yang ditolak server (nilai bukan tanggal) dijawab 422 (TC-R01); label dan isian terverifikasi pada "
        "DOM modal peran Sales.",
    ),
    (
        "Revisi 3", "Opsi metode pembayaran selain uang piutang pada peran Sales",
        "Pada role Sales, tambahkan pilihan metode pembayaran, termasuk pembayaran selain melalui uang piutang.",
        "Kolom payments.payment_method (string 30) dipisah dari payment_type yang tetap menyimpan skema pembiayaan; "
        "Payment::METHODS = transfer bank, tunai, qris, virtual account, cek/giro. PaymentController::store "
        "memvalidasi 'nullable|in:' . implode(',', Payment::METHODS) dan ::index menyaring lewat parameter "
        "payment_method; form catat pembayaran peran Sales menampilkan pemilih wajib 'Metode Pembayaran' dan "
        "metodenya muncul pada tabel Riwayat Pembayaran detail booking.",
        ["TC-R03", "FF-R03", "FF-A17"],
        "Nilai di luar daftar ditolak 422 dan metode non-transfer (qris) terbukti tersimpan serta terbaca kembali "
        "pada antarmuka.",
    ),
    (
        "Revisi 4", "Seluruh penyebutan Cluster diubah menjadi Proyek",
        "Semua penyebutan nama Cluster pada sistem diubah menjadi Proyek.",
        "resources/views/app/data-proyek.blade.php memakai @section('title', 'Data Proyek') dan page_title "
        "'Pengelolaan Proyek' dengan tombol Tambah Proyek; label navigasi 'Data Proyek' pada "
        "public/js/luminpark.js; tombol Kelola Proyek dan Tambah Proyek pada Data Unit; pesan sistem "
        "ClusterController ('Proyek baru berhasil ditambahkan.', 'Proyek masih dipakai N unit.'); dan "
        "App\\Observers\\AuditObserver melabeli model Cluster sebagai 'proyek' serta Project sebagai 'perumahan' "
        "sehingga jejak audit membaca 'Menghapus proyek …'. Nama tabel clusters, rute /clusters, dan parameter "
        "cluster_id sengaja dipertahankan (keputusan nama tabel berbahasa Inggris + lampiran pemetaan ERD).",
        ["TC-M01", "TC-M02", "TC-M04", "TC-M08", "TC-M09", "TC-O01", "TC-O02", "FF-A18", "FF-A19", "FF-B08",
         "FF-R04"],
        "Yang masih terbaca 'Cluster Asena' dan semacamnya adalah nama data bikinan pengguna, bukan label sistem. "
        "Perbaikan accompanying: AuditObserver sebelumnya menulis created_cluster/deleted_cluster.",
    ),
    (
        "Revisi 5", "Status pembatalan konsumen dengan riwayat pembelian tetap tersimpan",
        "Menambahkan status Pembatalan pada data konsumen; riwayat pembelian sebelumnya tetap tersimpan dan dapat "
        "dilihat.",
        "BookingController::cancel (PATCH /bookings/{id}/cancel) mengunci status booking menjadi dibatalkan "
        "beserta waktu pembatalan dan alasan minimal lima karakter; CustomerLead::category() menggolongkan lead yang "
        "seluruh pemesanannya dibatalkan sebagai 'dibatalkan'; pembayaran tambahan pada booking yang dibatalkan "
        "ditolak; halaman detail booking dan detail konsumen menampilkan blok Waktu dan Alasan Pembatalan di atas "
        "tabel riwayat pemesanan dan pembayaran; ReportController::consumerReport menyaring kategori lewat parameter "
        "category=dibatalkan.",
        ["TC-R04", "FF-R05", "TC-D09"],
        "Butir ini menggantikan penapis lama bernilai booking yang selalu mengembalikan nol baris setelah pemisahan "
        "kategori pada revisi 7.",
    ),
    (
        "Revisi 6", "Halaman super admin tetap bernama Data Konsumen",
        "Super Admin tetap bernama Data Konsumen.",
        "NAV pada public/js/luminpark.js mengarahkan super_admin dan admin_sales ke /data-konsumen dengan label "
        "'Data Konsumen'; khusus peran Sales tautannya dipecah menjadi Konsumen dan Database Konsumen. Judul "
        "halaman dirender lewat span #lead-judul sehingga ikut berubah tanpa memuat ulang halaman, dan ringkasan "
        "super admin memuat kartu Total Data Konsumen, Calon Konsumen, Konsumen, dan Pembatalan.",
        ["FF-R06", "TC-C01"],
        "Terverifikasi pada DOM: judul 'Data Konsumen' dengan 25 baris (6 konsumen, 16 calon, 3 pembatalan).",
    ),
    (
        "Revisi 7", "Pemisahan Konsumen dan Database Konsumen dengan status Cold, Warm, Hot",
        "Konsumen di role sales hanya untuk konsumen yang sudah booking dan sudah sah menjadi pembeli, dan untuk "
        "calon konsumen namanya database konsumen; database ini hanya memiliki tiga status: Cold, Warm, Hot.",
        "CustomerLead::CATEGORY_PROSPEK/KONSUMEN/DIBATALKAN dihitung dari relasi bookings (aktif berarti konsumen, "
        "pernah tetapi seluruhnya dibatalkan berarti dibatalkan, selain itu prospek); scopeProspects, scopeCustomers, "
        "dan scopeCancelled menjadi dasar penyaringan; status lead dibatasi cold|warm|hot pada lapisan validasi "
        "sehingga nilai booking ditolak; sidebar Sales menampilkan dua menu terpisah "
        "(/data-konsumen?kategori=konsumen dan ?kategori=prospek) dengan judul dan penafsirannya masing-masing.",
        ["TC-R02", "FF-R07", "FF-C04"],
        "Bug yang ikut ditutup pada butir ini: penapis laporan konsumen masih memakai nilai status 'booking' "
        "sehingga selalu kosong; kini memakai kategori.",
    ),
    (
        "Revisi 8", "Sandi awal sales dari tanggal lahir dan wajib diganti",
        "Setiap admin menambahkan sales baru, akun sales otomatis terbuat dan password defaultnya menggunakan "
        "tanggal lahir dengan format yyyymmdd; setelah sales login barulah sales mengubah password tersebut sesuai "
        "syarat keamanan huruf besar, huruf kecil, angka, dan minimal 8 karakter.",
        "SalesController::passwordFromBirthDate() membentuk sandi awal yyyymmdd (contoh 14 Maret 1995 menjadi "
        "19950314) bila kolom sandi dikosongkan, menyetel auth.password_source=birth_date serta "
        "must_change_password=true; AuthController::updatePassword menolak sandi baru yang tidak memenuhi "
        "regex /^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).{8,}$/ dan reset sandi pada SalesController::resetPassword memakai "
        "mekanisme yang sama. Modal tambah sales memberi petunjuk aturan tersebut pada kolom Tanggal Lahir.",
        ["TC-R08", "TC-F01", "FF-R08", "FF-D06"],
        "Alur utuh teruji: login dengan 19950314 berhasil, sandi lemah ditolak 422, sandi kuat diterima dan sandi "
        "lama hangus.",
    ),
    (
        "Revisi 9", "Log Aktivitas hanya tersedia pada satu peran pemantau",
        "Fitur Log Aktivitas hanya tersedia pada satu peran: dimulai sebagai Monitoring Manajemen (revisi 9) lalu "
        "dipindahkan menjadi hak Super Admin saja karena menu tersebut dihapus dari Admin Sales (revisi 30).",
        "Rute GET /activity-logs dan /system-logs dikunci role:super_admin pada routes/api.php dan satu entri "
        "{ href: '/log-aktivitas', label: 'Log Aktivitas', roles: ['super_admin'] } pada array NAV "
        "public/js/luminpark.js, sehingga peran lain tidak melihat menunya; guard halaman "
        "resources/views/app/log-aktivitas.blade.php LP.guard(['super_admin'], load) menolak peran lain dengan panel "
        "'Halaman belum tersedia untuk peran Anda'. Halaman app/log-aktivitas.blade.php tetap dua tab: 'Jejak audit "
        "data' (activity_logs) dan 'Aktivitas sistem & login' (system_logs).",
        ["TC-O01", "TC-O03", "TC-L04", "TC-R21", "TC-I05", "FF-R09", "FF-R27", "FF-A23", "FF-B01", "FF-B07"],
        "Revisi 30 menghapus Log Aktivitas dari Admin Sales: GET /api/activity-logs dan /api/system-logs dengan token "
        "admin_sales keduanya berbalas 403, Super Admin memperoleh 200 berisi baris jejak, dan permintaan tanpa token "
        "401 (TC-R21, TC-L04, TC-O03).",
    ),
    (
        "Revisi 10", "Filter dan statistik super admin berdasarkan proyek",
        "Pada role super admin harus ada filter per proyek, jadi statistik berdasarkan proyek cluster.",
        "Trait FiltersProjects (app/Http/Controllers/Concerns/FiltersProjects.php) menyediakan unitProyek, "
        "padaProyek, dan leadProyek; DashboardController::superAdmin membaca parameter cluster_id untuk "
        "mempersempit seluruh angka, dan tanpa parameter setiap proyek disajikan pada satu baris tabel "
        "'Ringkasan per Proyek' (kolom unit, booking, sales, prospek, konsumen, pembatalan, pendapatan) hasil "
        "Concerns/PerProjectSummary. Sejak revisi 32 tabel per-proyek itulah yang tampil di antarmuka, bukan angka "
        "gabungan seluruh proyek.",
        ["TC-R05", "TC-G01", "TC-R22", "TC-R23", "FF-R10", "FF-R29"],
        "Penyaringan terukur pada satu proyek: tabel dashboard dan keempat laporan hanya menampilkan satu baris "
        "proyek tersebut dan nilainya sama persis dengan angka gabungan di respons (TC-R22, TC-R23).",
    ),
    (
        "Revisi 11", "Pencarian Data Unit berdasarkan Blok, Status, dan Proyek",
        "Pada bagian Data Unit, ubah tampilannya menjadi fitur pencarian berdasarkan Blok, Status, dan Proyek.",
        "resources/views/app/data-unit.blade.php menyediakan tiga penapis sekaligus — input #unit-search untuk blok "
        "(pencocokan kode unit tidak peka huruf), select #unit-status, dan select #unit-cluster yang menyembunyikan "
        "proyek diarsipkan — dan menyaring baris hasil GET /units di sisi klien; API yang sama mendukung parameter "
        "cluster_id dan status sehingga penapisan dapat dilakukan di server.",
        ["TC-R09", "TC-B01", "FF-R11"],
        "Kombinasi ketiga penapis terukur memangkas 14 baris awal menjadi satu baris; pilihan proyek menampilkan "
        "delapan opsi.",
    ),
    (
        "Revisi 12", "Peta lokasi proyek pada halaman beranda",
        "Menambahkan Maps pada halaman Beranda yang menampilkan lokasi masing-masing proyek.",
        "Migration revisi menambah clusters.address, latitude, dan longitude (decimal 10,7) yang dikelola lewat "
        "ClusterController::update dengan validasi numeric between:-90,90 dan between:-180,180 serta isian "
        "latitude/longitude pada formulir Ubah proyek (kolom Peta pada tabelnya dihapus pada revisi 31); "
        "GET /api/public/project-locations (PublicInfoController::projectLocations) mengirim hanya proyek aktif "
        "berkoordinat lengkap beserta jumlah unit tersedia, dan resources/views/public/beranda.blade.php memetakan "
        "hasilnya dengan Leaflet 1.9.4 dan tile OpenStreetMap pada seksi 'Lokasi Proyek' beserta daftarnya. Sejak "
        "revisi 37 penanda memperbesar ke ZOOM_JALAN = 17 agar grid dan nama jalan terbaca, popup maupun kartu "
        "lokasi menautkan rute Google Maps, dan tombol 'Gambar rute dari lokasi saya' menggambar polyline jalan "
        "dari geometri GeoJSON layanan OSRM lengkap dengan jarak dan estimasi waktu berkendara.",
        ["TC-R06", "TC-R28", "FF-R12", "FF-R36"],
        "Proyek tanpa koordinat dilewati dari daftar agar penanda tidak menumpuk di titik nol. Di peramban, klik "
        "penanda menaikkan zoom dari 11 ke 17 (maxZoom peta 19) dan rute OSRM ke Proyek Asena terukur '18.2 km, "
        "estimasi 18 menit berkendara' dengan dua path SVG pada lapisan vektor; tanpa izin lokasi muncul pesan "
        "fallback berbahasa Indonesia (TC-R28, FF-R36).",
    ),
    (
        "Revisi 13", "Skema pembayaran dihapus dari halaman Tentang Kami",
        "Menghapus skema pembayaran pada halaman Tentang Kami; hilangkan informasi skema pembayaran dari halaman "
        "Tentang Kami.",
        "Seksi skema pembayaran dihapus dari resources/views/public/tentang.blade.php, kalimat tentang 'skema "
        "pembayaran KPR dan cash bertahap' dikeluarkan dari nilai baku about_text pada SettingSeeder sekaligus pada "
        "baris settings di basis data kerja dan basis data uji, dan judul 'Identitas Project' menjadi 'Identitas "
        "Proyek'. Informasi skema tetap disajikan pada seksi Skema Pembayaran beranda dan halaman /skema-pembayaran.",
        ["FF-R13", "TC-H03", "FF-D04"],
        "Nol kecocokan kata skema/KPR/cash di dalam tag <main> halaman Tentang Kami saat diukur di peramban.",
    ),
    (
        "Revisi 14", "Atur ulang sandi diverifikasi lewat email",
        "Menghapus isian kata sandi lama pada halaman atur ulang sandi; alurnya menjadi lupa password - isi email "
        "- kirim verifikasi ke email akun - isi kata sandi baru - verifikasi kata sandi baru - simpan.",
        "Rute tamu POST /api/forgot-password (AuthController::forgotPassword) membuat token acak 64 karakter yang "
        "disimpan sebagai hash SHA-256 pada tabel password_reset_tokens dengan masa berlaku 10 menit, lalu mengirim "
        "Mailable LinkAturUlangSandi berisi tautan /reset-password?token=...&email=...; balasan untuk email yang "
        "tidak terdaftar dan email konsumen dibuat identik agar halaman ini tidak dipakai memeriksa keberadaan "
        "akun, dan permintaan dibatasi enam kali per menit per alamat IP. POST /api/reset-password/token "
        "(AuthController::resetPasswordWithToken) menyimpan kata sandi baru cukup dengan token tersebut tanpa kata "
        "sandi lama, lalu menghapus baris token sehingga hanya sekali pakai, mencabut seluruh token Sanctum, dan "
        "menutup sesi login yang berjalan. resources/views/auth/reset-password.blade.php disusun ulang menjadi tiga "
        "langkah (isi email - cek email dengan kirim ulang berjeda 60 detik - kata sandi baru beserta verifikasinya) "
        "dan langsung membuka langkah ketiga bila diakses lewat tautan email. Jalur lama email + password_lama pada "
        "POST /api/reset-password tetap dipertahankan untuk halaman Profil, /ganti-sandi, dan paksaan ganti sandi.",
        ["TC-R10", "TC-R11", "FF-R14"],
        "Isian kata sandi saat ini tidak ada lagi pada halaman publik tersebut; pengiriman email memakai driver log "
        "di mesin lokal karena tidak ada server SMTP, dan blok konfigurasi Gmail didokumentasikan pada .env.example.",
    ),
    (
        "Revisi 15", "Calon konsumen berpindah ke data konsumen setelah pembayaran diverifikasi",
        "Pada database konsumen, ketika calon konsumen sudah diverifikasi membayar maka datanya otomatis pindah ke "
        "data konsumen, dan data tersebut juga masuk ke pilihan history.",
        "CustomerLead::sudahBayarTerverifikasi() membaca pembayaran berverification_status=verified pada booking "
        "yang tidak dibatalkan (relasi bookings.payments dimuat lebih dahulu di CustomerLeadController::index, "
        "ReportController, dan DashboardController sehingga tanpa kueri tambahan per baris); category() "
        "mengembalikan 'konsumen' hanya bila metode ini terpenuhi sehingga calon dengan booking namun pembayaran "
        "belum terverifikasi tetap 'prospek'. Scope customers(), prospek(), cancelled() dan riwayat() memakai aturan "
        "yang sama, dan kategori filter CATEGORY_RIWAYAT='riwayat' (customers() OR cancelled()) tetap tersedia "
        "sebagai penapis API. Sejak revisi 18 pilihan riwayat tidak lagi menempel pada penapis Data Konsumen, dan "
        "sejak revisi 25 penapis Kategori pada menu Konsumen hanya menawarkan Semua Kategori, Konsumen, Calon "
        "Konsumen, dan Dibatalkan.",
        ["TC-R12", "TC-R02", "TC-R04", "FF-R15"],
        "Penapis kategori tidak mengubah label kategori per baris; pada pengukuran terakhir peran Sales membaca "
        "16 baris konsumen, 91 calon, dan 9 baris dibatalkan dari 116 lead.",
    ),
    (
        "Revisi 16", "Konsumen yang membatalkan setelah menempati rumah keluar dari menu Konsumen",
        "Jika konsumen telah melakukan pembayaran dan sudah menempati rumah namun melakukan pembatalan, datanya "
        "tidak lagi ada di menu konsumen tetapi berpindah ke data riwayat.",
        "BookingController::cancel tidak lagi menolak booking berstatus lunas atau delivery_status selesai; unit "
        "dikembalikan menjadi available dan status booking dikunci dibatalkan sementara baris pembayaran "
        "riwayat pembelian tetap tersimpan. CustomerLead::pembatalanTerakhir() menyusun kolom pembatalan (kode "
        "booking, tanggal, alasan, unit, nominal terverifikasi, dan status serah terima) yang disertakan pada "
        "GET /api/customer-leads?category=dibatalkan. Halaman khusus yang semula berada di "
        "resources/views/app/pembatalan-konsumen.blade.php sempat berpindah menjadi app/history.blade.php pada "
        "revisi 18, lalu dihapus pada revisi 25 sehingga pembacaannya kembali lewat penapis Kategori = Dibatalkan "
        "dan kolom Pembatalan pada halaman detail konsumen; tombol batalkan pada "
        "/booking dibuka untuk seluruh booking yang belum dibatalkan.",
        ["TC-R13", "TC-R04", "FF-R16"],
        "Lead hasil pembatalan pascapelunasan hilang dari penapis konsumen, tetap muncul pada kategori dibatalkan, "
        "dan nominal terverifikasinya sama dengan harga total unit.",
    ),
    (
        "Revisi 17", "Biaya tambahan konstruksi dan request tambahan bangunan pada booking",
        "Ketika booking tersedia isian tambahan biaya konstruksi di luar biaya utama perumahan apabila konsumen "
        "mengajukan permintaan tambahan pada bangunan unit rumah.",
        "Migration 2026_11_20_090000_add_booking_addition_cost menambah bookings.addition_cost (decimal 15,2 "
        "default 0) dan addition_description (string 255, nullable) dengan penjaga Schema::hasColumn sehingga "
        "idempoten; BookingController::store/::update memvalidasi keduanya dan menyimpannya di luar total_price "
        "sehingga hitungan PaymentScheme::calculateBreakdown, down_payment_amount, installment_amount, dan "
        "remaining_amount tidak berubah. Formulir Buat Booking menampilkan 'Biaya Tambahan Konstruksi (Rp)' "
        "beserta catatan bookingAdditionNote(), kolom Biaya Tambahan pada tabel dan detail booking, tabel riwayat "
        "pada /data-konsumen/{id}, serta dua kolom ekstra pada ekspor CSV laporan penjualan.",
        ["TC-R14", "FF-R17"],
        "Biaya negatif ditolak 422 dengan pesan attribute Indonesia 'biaya tambahan konstruksi'; simulasi uang "
        "muka dan angsuran tetap identik saat biaya tambahan diisi.",
    ),
    (
        "Revisi 18", "Menu peran Sales dipisah dari halaman Pembatalan Konsumen",
        "Menu pada role sales yaitu dashboard, data konsumen, database konsumen (calon konsumen), data pembayaran, "
        "booking unit, laporan sales, dan history — butir history kemudian dicabut pada revisi 25.",
        "Array NAV pada public/js/luminpark.js menghapus butir /pembatalan-konsumen sehingga Sales membaca enam menu "
        "berurutan Dashboard, Konsumen, Database Konsumen, Booking Unit, Data Pembayaran, dan Laporan Sales; menu "
        "History yang sempat ditambahkan pada revisi 18 dihapus kembali pada revisi 25. routes/web.php tidak lagi "
        "mendaftarkan /pembatalan-konsumen maupun /history sehingga kedua alamat lama menghasilkan HTTP 404. "
        "CustomerLead::bookingAktif(), bookingPembatalanTerakhir(), dan "
        "riwayatRingkas() menyediakan objek riwayat (kode dan tanggal booking, status, unit, harga, biaya tambahan, "
        "nominal terverifikasi, serah terima, tanggal batal, alasan) sementara CustomerLeadController::index "
        "menyertakan flag punya_booking_aktif pada setiap baris.",
        ["TC-R17", "TC-R12", "FF-R16", "FF-R18"],
        "Navigasi Sales berisi enam tautan tanpa History maupun Pembatalan Konsumen; riwayat pembatalan dibaca dari "
        "penapis Kategori = Dibatalkan (9 baris dari 116 lead pada pengukuran terakhir).",
    ),
    (
        "Revisi 19", "Nomor handphone hanya dapat dipakai satu kali",
        "Sales menambahkan calon konsumen pada menu database konsumen dengan mengisi beberapa data salah satunya "
        "menggunakan nomor handphone yang hanya bisa sekali pakai pada satu konsumen, jadi ketika ada konsumen yang "
        "menggunakan nomor handphone yang sama maka tidak bisa.",
        "CustomerLeadController::aturanTelepon() membangun Rule::unique('customer_leads','phone') dengan "
        "whereNull('deleted_at') dan ignore(id) saat pembaruan, dipakai pada store() (required) dan update() "
        "(sometimes), dengan pesan seragam self::PESAN_TELEPON_DIPAKAI sehingga respons 422 dan span kesalahan "
        "pada form antarmuka menampilkan kalimat yang sama. Petunjuk isian Nomor Telepon pada resources/views/app/"
        "data-konsumen.blade.php ikut menjelaskan aturan tersebut. Aturan diterapkan di lapisan aplikasi (bukan "
        "indeks unik database) karena basis data kerja masih memuat nomor ganda dan lead yang dihapus lembut harus "
        "membebaskan nomornya.",
        ["TC-R15", "FF-R19"],
        "Nomor milik lead lain ditolak 422 tanpa menambah baris; menyimpan ulang nomor milik sendiri menghasilkan "
        "200; nomor lead yang sudah dihapus lembut dapat dipakai lead baru (201).",
    ),
    (
        "Revisi 20", "Pilihan konsumen pada booking dibatasi berstatus Hot",
        "Ketika ada calon konsumen yang ingin lanjut booking maka sales menambahkan booking pada menu booking unit "
        "yang pilihan konsumennya hanya yang berstatus hot.",
        "resources/views/app/booking.blade.php memfilter daftar lead melalui bookingLeadsHot() sebelum "
        "bookingLeadOptions() membangun opsi, sehingga pemilih Konsumen hanya berisi placeholder 'Pilih konsumen "
        "berstatus Hot' dan lead hot; label status tidak lagi ditempelkan pada opsi. bookingOpenCreate() mengunci "
        "modal dengan toast 'Belum ada calon konsumen berstatus Hot…' bila belum ada lead hot. Penolakan tetap "
        "dijaga di sisi server oleh BookingController::store yang memeriksa status hot (pesan 'Pemesanan hanya dapat dibuat "
        "untuk calon konsumen berstatus hot.') sehingga permintaan manual dari klien tidak dapat memintas.",
        ["TC-R16", "FF-R20"],
        "Booking untuk lead warm ditolak 422 dan lead yang sama berhasil dibooking setelah statusnya diubah menjadi "
        "hot (201); opsi modal berisi 49 lead hot tanpa satu pun cold/warm.",
    ),
    (
        "Revisi 21", "Pemilihan ada/tidaknya permintaan tambahan bangunan",
        "Sales memilih apakah calon konsumen yang booking ada permintaan tambahan atau tidak; jika ada maka ada "
        "field tambahan unit (keterangan apa saja yang ditambah) dan biaya tambahan, dan biaya tambahan tersebut di "
        "luar biaya unit utama.",
        "Formulir Buat Booking memperoleh select wajib addition_requested ('Tidak ada permintaan tambahan' / 'Ada "
        "permintaan tambahan bangunan') dengan nilai awal tidak; bookingToggleTambahan(form) menyembunyikan "
        "label addition_description dan addition_cost memakai closest('label').classList.toggle('hidden') serta "
        "mengosongkan nilainya, dan menyegarkan catatan bookingAdditionNote(). bookingCreate() menolak kombinasi "
        "'ya' tanpa keterangan lebih dahulu di sisi klien, lalu mengirim addition_cost = 0 dan "
        "addition_description = null ketika 'tidak' dipilih sehingga tidak ada biaya bayangan. Rantai yang sama "
        "dijaga BookingController::tolakTambahanTanpaKeterangan() pada store() dan update() (HTTP 422) dan biaya "
        "tambahan tetap disimpan di luar total_price sehingga simulasi uang muka serta angsuran tidak berubah.",
        ["TC-R18", "TC-R14", "FF-R21", "FF-R17"],
        "Isian tambahan hanya tampil saat 'ada' dipilih; menyimpan tanpa keterangan memunculkan toast permintaan "
        "keterangan dan jumlah booking tidak bertambah; API menolak biaya tambahan tanpa keterangan dengan 422.",
    ),
    (
        "Revisi 22", "Tombol tambah data konsumen dihilangkan dari menu Data Konsumen",
        "Calon konsumen yang sudah booking dan diverifikasi pembayarannya otomatis ditambahkan pada data konsumen, "
        "sehingga sales tidak lagi melakukan penambahan data konsumen; tombol tambah data konsumen pada menu data "
        "konsumen dihilangkan karena daftar akan terisi otomatis.",
        "leadPaintActions() pada resources/views/app/data-konsumen.blade.php menyembunyikan #btn-add-lead untuk "
        "peran Sales kecuali pada penapis kategori kosong atau prospek (Database Konsumen), dan menyembunyikannya "
        "penuh bagi peran yang tidak boleh menulis; fungsi ini dipanggil saat guard dan setiap kali penapis kategori "
        "berubah. Pilihan 'riwayat' dihapus dari #lead-category karena LEAD_KATEGORI_PENAPIS kini mengikuti "
        "LEAD_CATEGORY_LABEL (konsumen, prospek, dibatalkan) dan riwayat pembatalan dibaca lewat kategori Dibatalkan. Catatan halaman "
        "menjelaskan bahwa daftar Konsumen terisi otomatis setelah pembayaran disetujui super admin, mengikuti "
        "CustomerLead::category() yang menilai kategori dari sudahBayarTerverifikasi().",
        ["TC-R12", "TC-D07", "FF-R22", "FF-R15"],
        "Menu Konsumen tidak menampilkan tombol Tambah Konsumen sedangkan Database Konsumen masih "
        "menampilkannya; kategori konsumen bertambah satu baris otomatis ketika pembayaran diverifikasi.",
    ),
    (
        "Revisi 23", "Tombol Batal pada kolom Aksi Data Konsumen dengan alasan dan tanggal pembatalan",
        "Konsumen yang sudah membayar atau sudah menempati unit dapat membatalkan transaksi; sales menekan tombol "
        "batal pada kolom aksi menu data konsumen yang berisi alasan pembatalan dan tanggal pembatalan, lalu data "
        "konsumen keluar dari daftar konsumen dan dapat dibaca kembali pada kategori Dibatalkan.",
        "Rute POST /api/customer-leads/{id}/batalkan (grup role:sales,super_admin) ditangani "
        "CustomerLeadController::batalkan() yang memvalidasi reason (required, min 5) dan cancelled_at (nullable|date), "
        "menolak 404/403/422 ('Konsumen ini tidak memiliki pemesanan aktif yang dapat dibatalkan.'), lalu "
        "memanggil Booking::batalkan() untuk setiap pemesanan aktif. Booking::batalkan() — dipakai juga oleh "
        "PATCH /api/bookings/{id}/cancel — mengunci status dibatalkan, mengisi cancelled_at dan cancellation_reason, "
        "mengembalikan unit menjadi available, dan membiarkan baris pembayaran terverifikasi sebagai riwayat; trait "
        "Concerns/NotifiesOperationalUsers.php mengirim notifikasi pembatalan ke Super Admin (Admin Sales "
        "dilepas pada revisi 29) dengan tautan /data-konsumen?kategori=dibatalkan. Antarmuka merender tombol Batal (data-lead-action='batal') hanya bila baris "
        "punya_booking_aktif dan leadOpenBatal() membuka modal dua isian (alasan wajib, tanggal wajib default "
        "LP.today()) kemudian memuat ulang daftar.",
        ["TC-R17", "TC-R13", "FF-R23", "FF-R16"],
        "Setelah pembatalan, lead hilang dari category=konsumen dan muncul pada category=dibatalkan dengan tanggal "
        "2026-11-10/2026-11-19, alasan tersimpan, unit kembali available, dan pembatalan kedua ditolak 422.",
    ),
    (
        "Revisi 24", "Nomor handphone tetap terkunci walau cold maupun sudah dibatalkan",
        "Nomor handphone hanya bisa sekali pakai pada satu konsumen; ketika ada konsumen yang menggunakan nomor "
        "handphone yang sama maka tidak bisa, walaupun konsumennya berstatus cold ataupun sudah dibatalkan.",
        "CustomerLeadController::aturanTelepon() memakai Rule::unique('customer_leads','phone') dengan "
        "whereNull('deleted_at') dan ignore(id) saat pembaruan, sehingga status follow up (cold/warm/hot) maupun "
        "kategori dibatalkan tidak ikut membebaskan nomor karena baris lead tetap hidup — pembatalan hanya "
        "menutup pemesanan lewat Booking::batalkan() dan tidak menyentuh kolom phone maupun deleted_at lead. "
        "Pesan galat tunggal CustomerLeadController::PESAN_TELEPON_DIPAKAI dipakai oleh store() dan update(), "
        "sedangkan petunjuk isian pada leadFields() menegaskan 'termasuk konsumen yang sudah dibatalkan'.",
        ["TC-R15", "TC-R19", "FF-R19"],
        "TC-R19 membuktikan nomor yang sama ditolak 422 ketika lead masih cold dan ditolak 422 lagi setelah "
        "pemesanannya dibatalkan (kategori menjadi dibatalkan); satu-satunya jalur pembebasan nomor adalah "
        "penghapusan lunak lead (TC-R15).",
    ),
    (
        "Revisi 25", "Menu History dihapus, riwayat pembatalan dibaca dari penapis Kategori",
        "Hapus menu history; jika sales ingin melihat riwayat konsumen yang sudah membatalkan pesanan, sales bisa "
        "mencari melalui pencarian konsumen dengan pilihan kategori batalkan, dan jika ingin melihat konsumen tetap "
        "bisa memilih kategori konsumen.",
        "Butir { href: '/history', label: 'History' } dihapus dari array NAV public/js/luminpark.js, "
        "Route::view('/history') dihapus dari routes/web.php, dan resources/views/app/history.blade.php tidak lagi "
        "dirujuk sehingga GET /history maupun /pembatalan-konsumen menghasilkan HTTP 404. Tautan History pada "
        "page_actions Data Konsumen ikut dihapus, kartu ringkasan 'History Konsumen' menjadi 'Konsumen Dibatalkan' "
        "dengan petunjuk 'Dibuka lewat penapis Kategori', dan seluruh teks yang menyebut menu History pada halaman "
        "Konsumen, modal Batal, serta modal pembatalan /booking diganti menjadi kategori Dibatalkan. Notifikasi "
        "pembatalan kini menuju /data-konsumen?kategori=dibatalkan, sedangkan penapis API category=riwayat tetap "
        "tersedia walau tidak dipakai antarmuka.",
        ["TC-R17", "TC-R19", "FF-R16", "FF-R18", "FF-R23"],
        "Sidebar Sales berisi enam tautan tanpa History; kategori Dibatalkan menampilkan 9 baris dari 116 lead dan "
        "halaman detail konsumen tetap memuat kolom Pembatalan berisi alasan serta tanggal (19 Nov 2026, 07.00).",
    ),
    (
        "Revisi 26", "Penapis Status dihilangkan dari daftar Konsumen",
        "Pencarian berdasarkan status dihilangkan pada menu Konsumen karena status cold, warm, dan hot hanya "
        "dimiliki oleh calon konsumen.",
        "Label penapis Status diberi id lead-label-status dan leadPaintFilterVisibility() pada "
        "resources/views/app/data-konsumen.blade.php menyembunyikannya bagi peran Sales setiap kali kategori aktif "
        "bukan prospek (Konsumen maupun Dibatalkan); nilai #lead-status ikut dikosongkan agar tidak ada baris yang "
        "tersembunyi di balik penapis yang tidak terlihat. Fungsi ini dipanggil saat guard dan pada setiap perubahan "
        "penapis Kategori, sedangkan super admin dan Admin Sales pada halaman gabungan tetap melihat kedua penapis.",
        ["TC-R01", "FF-R24"],
        "Pada /data-konsumen?kategori=konsumen #lead-label-status berkelas hidden dengan value kosong dan "
        "16 baris konsumen tampil; saat penapis dipindah ke Calon Konsumen label Status muncul kembali.",
    ),
    (
        "Revisi 27", "Penapis Kategori dihilangkan dari Database Konsumen",
        "Pada menu database konsumen, penapis kategori juga dihapus karena halaman tersebut sudah khusus memuat "
        "calon konsumen.",
        "leadPaintFilterVisibility() menyembunyikan label lead-label-category bagi peran Sales ketika kategori "
        "aktif adalah prospek, sehingga Database Konsumen hanya menampilkan pencarian, Status, dan Sales Penanganan; "
        "nilai #lead-category tetap 'prospek' supaya leadKategoriAktif(), leadJudul(), dan leadPaintActions() "
        "berperilaku seperti sebelumnya. Penapis Kategori tetap ada pada daftar Konsumen karena di sanalah riwayat "
        "pembatalan dibuka (revisi 25).",
        ["TC-R12", "FF-R20", "FF-R24"],
        "Pada /data-konsumen?kategori=prospek #lead-label-category berkelas hidden (value 'prospek'), judul halaman "
        "'Database Konsumen', tombol Tambah Konsumen terlihat, dan 91 baris calon konsumen tampil dari 116 lead.",
    ),
    (
        "Revisi 28", "Berkas history.blade.php dihapus dan daftar berkas tak berujuk rute diperbarui",
        "Hapus history.blade.php dan perbarui daftar file yang tidak dirujuk rute.",
        "resources/views/app/history.blade.php benar-benar dihapus dari pohon berkas, dan penelusuran pada routes/, "
        "app/, public/js, serta tests/ tidak lagi menemukan kata 'app.history' maupun '/history' sehingga "
        "GET /history dan /pembatalan-konsumen tetap berbalas HTTP 404. Sensus 21 November 2026 atas 37 berkas Blade "
        "dipakai untuk menulis ulang daftar berkas warisan pada baris risiko BAB V laporan uji: "
        "welcome.blade.php, dashboard/index.blade.php, sales/index.blade.php, super-admin/dashboard.blade.php, "
        "super-admin/dataunit_perumahan.blade.php, activity-log/index.blade.php, dan layouts/dashboard.blade.php "
        "tidak dirujuk satu rute pun; rujukan activity-log/index pada app/Http/Controllers/ActivityLogController.php:13 "
        "ikut mati karena yang terdaftar hanyalah App\\Http\\Controllers\\Api\\ActivityLogController "
        "(routes/api.php:146), sedangkan halaman /log-aktivitas dirender lewat Route::view('app.log-aktivitas') "
        "sejak revisi 30. Tiga puluh berkas lainnya terpetakan rute, tiga di antaranya hanya dipakai @extends "
        "(layouts/app, layouts/public, layouts/dashboard).",
        ["TC-R17", "TC-R19", "FF-R16", "FF-R25"],
        "GET /history = 404 dan GET /pembatalan-konsumen = 404 pada basis data uji; berkas history.blade.php tidak "
        "ada lagi di resources/views/app dan sidebar Sales berisi enam tautan tanpa History.",
    ),
    (
        "Revisi 29", "Notifikasi pembayaran dan verifikasi super admin tidak muncul di Admin Sales",
        "Pada role admin sales, notifikasi pembayaran dan verifikasi super admin ke sales tidak perlu muncul juga ke "
        "admin sales.",
        "Concerns/NotifiesOperationalUsers.php menyempitkan kueri penerimanya menjadi hanya pengguna berrole "
        "super_admin, sehingga siaran 'Booking baru dibuat', 'Pemesanan konsumen dibatalkan', dan pembayaran tidak "
        "lagi dicetak ke akun Admin Sales; PaymentController::notifyVerificationUsers() mengirim hasil verifikasi ke "
        "Super Admin ditambah satu penerima terpilih — sales pemilik pemesanan — lewat Notification::createForUser(). "
        "NotificationController::milikPengguna() turut mengecualikan type booking dan payment pada pembacaan bagi "
        "peran admin_sales agar baris lama hasil siaran sebelum revisi tidak muncul lagi di lonceng maupun halaman "
        "/notifikasi, tanpa menghapus data apa pun. Notifikasi yang memang dialamatkan kepada Admin Sales tetap "
        "diterima karena penyaringan hanya berlaku pada dua jenis operasional tersebut.",
        ["TC-R20", "TC-I01", "TC-I02", "FF-R26"],
        "TC-R20 pada transaksi uji (lead hot + booking + pembayaran uang muka diverifikasi): Admin Sales 0 baris, "
        "Super Admin 2 baris, sales pemilik 1 baris; daftar Admin Sales berisi 0 baris booking/payment padahal "
        "tabel notifications masih menyimpan 343 baris warisan milik peran tersebut.",
    ),
    (
        "Revisi 30", "Menu Log Aktivitas dihapus dari Admin Sales dan menjadi hak Super Admin",
        "Log aktivitas dihapus — dijelaskan pengguna sebagai penghapusan menu dari Admin Sales, dan Super Admin "
        "tetap memperoleh halamannya.",
        "Entri { href: '/log-aktivitas', label: 'Log Aktivitas', roles: ['super_admin'] } pada array NAV "
        "public/js/luminpark.js membuat menu hanya dirender untuk Super Admin, guard halaman "
        "resources/views/app/log-aktivitas.blade.php berubah menjadi LP.guard(['super_admin'], load), dan kedua "
        "rute API — GET /api/activity-logs serta GET /api/system-logs pada routes/api.php — dikunci "
        "role:super_admin. Tab 'Jejak audit data' (activity_logs) dan 'Aktivitas sistem & login' (system_logs) "
        "beserta penelusuran, pemilih pengguna, dan penjelas aksi berbahasa Indonesia tidak berubah; modul "
        "Sesi Login tetap khusus Super Admin. Berkas resources/views/activity-log/index.blade.php berikut "
        "App\\Http\\Controllers\\ActivityLogController varian web masuk daftar berkas tak berujuk rute (revisi 28).",
        ["TC-R21", "TC-I05", "TC-L04", "TC-O01", "TC-O03", "FF-R09", "FF-R27", "FF-A23", "FF-B01", "FF-B07"],
        "Sidebar Admin Sales berisi delapan tautan tanpa Log Aktivitas dan membuka /log-aktivitas menampilkan panel "
        "'Halaman belum tersedia untuk peran Anda'; sidebar Super Admin berisi sembilan tautan termasuk Log Aktivitas "
        "dan halaman itu memuat kedua tab. GET /api/activity-logs dan /api/system-logs dengan token admin_sales "
        "berbalas 403, dengan token super_admin 200 berisi baris, dan tanpa token 401.",
    ),
    (
        "Revisi 31", "Kolom Peta dan penapis Perumahan dihapus dari tabel Data Proyek",
        "Pada tabel menu data proyek, hapus kolom peta, dan hapus search bar perumahan.",
        "resources/views/app/data-proyek.blade.php tidak lagi mendefinisikan kolom 'Peta' (sebelumnya selnya berisi "
        "tautan 'Lihat' menuju lokasi proyek) sehingga header tabel menjadi [Proyek, Perumahan, Alamat, "
        "Jumlah Unit, Status, Deskripsi, Aksi]; label 'Cari Perumahan' beserta pemilih #proyek-project (penapis "
        "perumahan) dihapus dari baris penapis sehingga tersisa pencarian 'Cari Proyek' (#proyek-search) dan "
        "'Status' (#proyek-status). Kolom Perumahan pada baris tetap ditampilkan karena ia data proyek, bukan "
        "penapis, dan isian latitude/longitude pada formulir Ubah/Tambah Proyek dipertahankan sebab koordinat itu "
        "masih dipakai penanda peta pada halaman beranda publik (revisi 12).",
        ["TC-R06", "TC-R09", "FF-A20", "FF-R28"],
        "Pada /data-proyek (Super Admin, 23 proyek aktif+arsip) header tabel tidak memuat 'Peta' dan satu-satunya "
        "kendali penapis adalah #proyek-search berlabel 'Cari Proyek' serta #proyek-status berlabel 'Status'; "
        "pemilih perumahan tidak lagi ada di DOM. Rute /public/project-locations tetap 200 untuk peta beranda.",
    ),
    (
        "Revisi 32", "Laporan dan dashboard merinci angka setiap proyek, bukan angka gabungan",
        "Pada laporan dan dashboard, tampilkan data pada masing-masing proyek bukan secara keseluruhan proyek — "
        "dipilih pengguna: rincian per proyek selalu tampil, satu baris per proyek, angka gabungan dihapus dari "
        "antarmuka, dan penapis proyek tetap tersedia.",
        "Concerns/PerProjectSummary.php (namaProyek, rangkumPerProyek, tandaiProyek, labelProyek, proyekDariLead) "
        "menjadi tulang punggung rincian: ReportController menambahkan blok per_project pada laporan penjualan, "
        "pelunasan, kinerja sales, dan konsumen, sedangkan DashboardController::rekapPerProyek() menggabungkan "
        "kueri unit, booking, pembayaran, dan metrikLeadPerProyek() dengan pengelompokan "
        "COALESCE(cluster_id, 0) sehingga lead yang belum menautkan unit tetap terbaca pada baris "
        "'(belum ada proyek)'. Antarmuka dashboard (/dashboard untuk Super Admin dan Admin Sales) menampilkan tabel "
        "'Ringkasan per Proyek' lima belas kolom dan menghapus kartu angka gabungan, sementara halaman laporan "
        "menaruh tabel ringkasan per proyek di atas tabel rincian pada keempat tab; kunci JSON lama (counters, "
        "totals, total_*) tetap dikirim agar konsumen lama tidak rusak. Ekspor CSV menambahkan kolom Proyek dan "
        "penapis #dash-proyek/#lap-cluster tetap berfungsi: satu proyek terfilter menghasilkan satu baris yang "
        "sama dengan angka totalnya. Perbaikan ikutannya: where('booking_date','between',...) pada subqueries "
        "pendapatan adminSalesDashboard() diganti whereBetween karena operator tersebut menghasilkan galat SQL "
        "1525 Incorrect DATE value.",
        ["TC-R05", "TC-R22", "TC-R23", "FF-R10", "FF-R29", "FF-R30", "FF-R31"],
        "TC-R22: jumlah baris per_project sama persis dengan total laporan pada keempat laporan (penjualan 27 "
        "transaksi Rp 15,22 M; pelunasan 73 Rp 26,16 M), berkas CSV berkepaskan kolom Proyek, dan penyaringan "
        "cluster_id menghasilkan satu baris. TC-R23: penjumlahan tabel per proyek pada dashboard Super Admin sama "
        "dengan counters total_units/total_bookings/total_prospects/total_customers/total_cancelled dan baris "
        "(belum ada proyek) tetap hadir; dashboard Admin Sales pun memakai tabel yang sama (24 baris teramati di "
        "peramban).",
    ),
    (
        "Revisi 33", "Blok konfigurasi Google dilepas dari halaman Pengaturan Super Admin",
        "Hapus tampilan blok status masuk pada Google dan blok integrasi login Google pada role super admin.",
        "resources/views/app/pengaturan.blade.php tidak lagi merender seksi 'Status Masuk dengan Google' "
        "(beserta #google-status dan empat langkah pendaftaran OAuth) maupun grup 'Integrasi Login Google'; "
        "fungsi renderGoogleStatus() dihapus dan entri integrasi dikeluarkan dari GROUP_LABEL. Kunci google_* "
        "disaring bersama public_* oleh helper terselubung = /^(public_|google_)/ sebelum fieldHtml() dipanggil, "
        "baik pada items hasil GET /api/settings maupun pada extraKeys. Yang dihapus hanya tampilan: baris "
        "google_login_enabled, google_client_id, dan google_client_secret tetap tersimpan pada tabel settings, "
        "tetap ada pada RULES serta editable SettingController, dan kontrak JSON GET /api/settings (items, values, "
        "editable, google, public) tidak berubah sehingga AuthController::googleRedirect dan googleCallback tetap "
        "membaca kredensial yang sama. Subjudul halaman disesuaikan menjadi '…parameter penjualan, dan kebijakan "
        "keamanan login.' karena dua butir Sistem & Sesi Login kini mengatur keamanan login.",
        ["TC-R25", "FF-A11", "FF-R32"],
        "Sumber HTML /pengaturan (TC-R25) tidak memuat teks 'Status Masuk dengan Google', 'Integrasi Login Google', "
        "maupun satu pun kunci google_; DOM teramati hanya merender tiga seksi — Identitas Perumahan, Penjualan & "
        "Pembayaran, Sistem & Sesi Login — dengan sepuluh isian dan tanpa #set-google_client_id. GET /api/settings "
        "tetap 200 dan daftar editable-nya masih memuat google_login_enabled, sedangkan halaman /login masih "
        "menyediakan id google-btn.",
    ),
    (
        "Revisi 34", "Tiga kali gagal login mengunci akun selama durasi yang diatur super admin",
        "Jika tiga kali gagal login maka akan diberi waktu tunggu beberapa menit, disesuaikan oleh super admin pada "
        "pengaturan Sistem & Sesi Login.",
        "Konstanta ATTEMPTS pada app/Support/LoginThrottle.php diturunkan dari 5 menjadi 3 dan jendela hitung "
        "dibuka ulang setiap kegagalan memakai RateLimiter::hit(kunci, SettingController::lockoutMinutes() * 60), "
        "dengan kunci login|<email kecil>|<ip> dan cache driver file sehingga bertahan antar permintaan. "
        "SettingController::lockoutMinutes() membaca kunci baru login_lockout_minutes (baku 5, rentang 1-120 menit, "
        "divalidasi required|integer|min:1|max:120 pada RULES, digrupkan 'sistem' oleh groupOf) yang ikut ditanam "
        "SettingSeeder lewat firstOrCreate. AuthController::login membalas 429 lebih awal dengan pesan 'Akun "
        "dikunci <n> menit karena 3 kali percobaan login gagal. Coba lagi dalam <menit> menit.' beserta retry_after "
        "dan locked_until. Antarmuka menampilkan isian 'Durasi Penguncian Login (menit)' pada seksi Sistem & Sesi "
        "Login, dan halaman /login menampilkan toast pesan tersebut tanpa mengunci akun pengguna lain.",
        ["TC-R24", "TC-Q01", "TC-Q02", "FF-R33"],
        "TC-R24 dan TC-Q01/TC-Q02 menjalankan tiga percobaan salah (401 'Email atau password salah') lalu percobaan "
        "keempat yang berbalas 429 dengan retry_after tidak lebih dari jendela kunci, pesan berpadanan 'dikunci', "
        "dan locked_until di masa datang; akun pembanding pada email berbeda tetap 200 sehingga kunci berbentuk "
        "email+IP, bukan global. Durasi dibaca ulang dari pengaturan pada tiap kegagalan sehingga perubahan super "
        "admin langsung berlaku tanpa migrasi.",
    ),
    (
        "Revisi 35", "Sistem keluar otomatis bila tidak ada aktivitas",
        "Sistem akan logout otomatis apabila tidak ada aktivitas; batas waktu tanpa aktivitas dikelola super admin.",
        "Penghitung sesi pada public/js/luminpark.js diubah dari 'umur token' menjadi 'waktu tanpa aktivitas': "
        "pantauAktivitas() mendaftarkan pointerdown/keydown/wheel/touchstart sekali (capture, passive) dan "
        "catatAktivitas() menandai sesiAktivitasTerakhir; mulaiPenghitungSesi() membaca session_timeout_minutes "
        "dari GET /api/session/status sebagai sesiBatasIdleDetik, dan tiap detik memeriksa idle lebih dulu — "
        "melewatinya memanggil keluarSesi() yang menghapus token, menampilkan toast 'Tidak ada aktivitas selama "
        "<hh:mm:ss>. Silakan masuk kembali.', lalu menuju /login?sesi=berakhir. Layar yang masih dipakai dan "
        "bersisa di bawah enam puluh detik diperpanjang sendiri lewat perpanjangSesi(hening=true) tanpa toast, "
        "sedangkan layar yang ditinggalkan dibiarkan habis; tombol Perpanjang manual tetap muncul pada peringatan "
        "300 detik. Label pengaturan session_timeout_minutes menjadi 'Batas Waktu Tanpa Aktivitas (menit)' dengan "
        "petunjuk baru, atribut title chip pada layouts/app.blade.php dan pesan pada auth/login.blade.php "
        "disesuaikan. Token hasil POST /api/session/refresh tetap berumur session_timeout_minutes lewat "
        "SettingController::sessionTimeoutMinutes().",
        ["TC-R26", "FF-A21", "FF-R34"],
        "TC-R26 memastikan nilai session_timeout_minutes yang tersimpan sama dengan yang dikirim GET "
        "/api/session/status dan bahwa token hasil POST /session/refresh berumur hampir persis batas tersebut. Di "
        "peramban: chip berjudul 'Sisa waktu sebelum sistem keluar otomatis karena tidak ada aktivitas' menghitung "
        "mundur 23:59:46; tanpa aktivitas melewati batas muncul toast 'Tidak ada aktivitas selama 24:01:07…' lalu "
        "mendarat di /login dengan localStorage luminpark_token kosong. Dengan satu pointerdown pada sisa 00:12 "
        "tercatat POST /api/session/refresh 200 dan GET /api/session/status 200, token berganti, hitungan kembali "
        "23:59:53, tanpa toast dan tanpa berpindah halaman.",
    ),
    (
        "Revisi 36", "Foto sales tampil penuh pada kartu narahubung publik",
        "Pada halaman publik, kontak sales langsung diberikan foto sales tersebut.",
        "SalesController::update (app/Http/Controllers/Api/SalesController.php:173) menerima berkas avatar dengan "
        "aturan nullable|image|mimes:jpg,jpeg,png|max:2048 melalui POST multipart + _method=PUT; berkas lama "
        "dihapus lebih dulu dari disk public lalu berkas baru disimpan ke folder avatars pada kolom users.avatar yang "
        "sudah ada, sehingga tidak ada migrasi baru. Seksi 'Foto Sales' pada resources/views/app/"
        "detail-sales.blade.php:107 menampilkan pratinjau 128 px atau placeholder 'Belum ada foto sales', isian "
        "berkas #ds-avatar (accept image/jpeg,image/png, petunjuk maksimal 2 MB), dan penjelasan bahwa foto tersebut "
        "langsung tampil pada halaman publik; detailSave() beralih ke multipart hanya bila berkas dipilih. Kartu "
        "narahubung pada resources/views/public/kontak.blade.php:67 memakai LP.fotoSales() berukuran h-20 w-20 "
        "(80×80 px) dan tetap jatuh ke lingkaran inisial bila avatar kosong.",
        ["TC-R27", "FF-R35", "FF-R01", "FF-D08"],
        "TC-R27 mengunggah PNG lewat /api/sales/{id} (200), membaca kembali avatar_url pada GET "
        "/public/sales-contacts (berisi /storage/avatars/), lalu menolak berkas PDF dengan 422. Di peramban, "
        "unggahan dari /data-sales/4 memunculkan toast 'Data sales berhasil diperbarui' dan kartu /kontak berubah "
        "menjadi <img alt=\"Foto Sari Sales Timur\"> 80×80 px. Catatan pelaksanaan: pada basis data kerja seluruh "
        "avatar sales masih kosong sehingga kartu menampilkan inisial sampai foto diunggah pengelola.",
    ),
    (
        "Revisi 37", "Peta beranda memperlihatkan detail jalan dan rute menuju proyek",
        "Petanya bisa dilihat detail jalan menuju ke proyek.",
        "resources/views/public/beranda.blade.php mendefinisikan ZOOM_JALAN = 17 dan LAYANAN_RUTE = "
        "'https://router.project-osrm.org/route/v1/driving/' (baris 223-224); peta dibuat dengan maxZoom 19 dan "
        "scrollWheelZoom mati, klik penanda memanggil flyTo ke koordinat proyek pada zoom 17 sehingga grid dan nama "
        "jalan terbaca (baris 337), popup proyek serta kartu pada #beranda-map-list menyediakan dua tautan — 'Lihat "
        "unit' menuju /unit?proyek=<nama> dan 'Rute menuju lokasi' menuju https://www.google.com/maps/dir dengan "
        "travelmode=driving. Blok kendali #beranda-rute berisi tombol 'Gambar rute dari lokasi saya', tautan Google "
        "Maps proyek terpilih, dan paragraf informasi: posisiPengguna() meminta geolocation dengan batas 12 detik, "
        "gambarRute() mengambil geometri GeoJSON OSRM lalu merendernya sebagai L.geoJSON (#1d4ed8) beserta "
        "circleMarker 'Posisi Anda', melakukan fitBounds dengan maxZoom ZOOM_JALAN, dan menulis ringkasan jarak serta "
        "estimasi waktu; kegagalan geolocation atau jaringan diganti pesan berbahasa Indonesia tanpa menghilangkan "
        "tautan Google Maps.",
        ["TC-R28", "FF-R36", "FF-R12", "FF-D01"],
        "TC-R28 membaca sumber HTML beranda (id='beranda-rute', tautan Google Maps dir, LAYANAN_RUTE OSRM, flyTo, "
        "ZOOM_JALAN = 17) dan memastikan GET /api/public/project-locations 200 dengan koordinat berhingga pada "
        "seluruh barisnya. Di peramban tingkat tile naik dari z=11 ke z=17 setelah penanda diklik, dan rute OSRM "
        "dari posisi simulasi (-0.83, 100.36) menghasilkan dua path SVG dengan teks 'Rute ke Proyek Asena: 18.2 km, "
        "estimasi 18 menit berkendara melalui jalan umum.'; ketika geolocation ditolak, pesan fallback yang tampil.",
    ),
    (
        "Revisi 38", "Kotak pencarian kode unit/tipe rumah dihapus dari halaman publik",
        "Selanjutnya pada search bar, hapus cari kode unit atau tipe rumah.",
        "resources/views/public/daftar-unit.blade.php kehilangan kendali #cari beserta labelnya dan fungsi render() "
        "hanya menyaring SEMUA_UNIT lewat pemilih #cluster (Proyek) dan #sort (Urutkan harga); pembacaan parameter "
        "cari pada URL dihapus, subjudul diganti menjadi 'Saring unit melalui pilihan proyek dan urutan harga untuk "
        "menemukan rumah yang sesuai.' dan pesan kosong dirujuk ke pilihan proyek. Karena kotak tersebut adalah "
        "satu-satunya tujuan form pencarian pada hero beranda, form #hero-search pada resources/views/public/"
        "beranda.blade.php:17 ikut dihapus dan digantikan dua tombol tautan 'Lihat Daftar Unit' (/unit) dan "
        "'Hubungi Sales' (/kontak) agar tidak ada kendali yang diam-diam mati; parameter ?proyek= yang dipakai kartu "
        "lokasi tetap dibaca. Pencarian Blok/Status/Proyek pada modul internal Data Unit (revisi 11) tidak diubah.",
        ["TC-R29", "FF-R37", "FF-D01"],
        "TC-R29 menuntut sumber /unit tidak lagi memuat id=\"cari\", frasa 'Kode unit atau tipe rumah', maupun "
        "params.get('cari') — namun tetap memuat #cluster dan #sort — dan menuntut GET /api/public/units 200 dengan "
        "minimal satu unit; sumber beranda tidak memuat hero-search atau ?cari= tetapi tetap memuat /unit?proyek=. "
        "Di peramban: 8 unit ditemukan, penapis 'Proyek Asena' menyisakan 2 kartu, dan 'Termurah dulu' menyusun "
        "Rp 350 juta → Rp 750 juta → Rp 815 juta → Rp 1,05 miliar.",
    ),
    (
        "Revisi 39", "Kolom Status, pilihan Calon Konsumen, dan penapis Sales dilepas dari role sales",
        "Hapus kolom statusnya, hapus pilihan calon konsumen, dan hapus sales penanganan pada role sales.",
        "resources/views/app/data-konsumen.blade.php memindahkan keputusan penampilan kolom ke dua fungsi: "
        "leadPakaiStatus() (baris 187) mengembalikan false untuk seluruh kategori ketika leadIsSales(), sehingga "
        "leadKolom() (baris 258) tidak pernah mendorong kolom 'Status' untuk sales; dan "
        "leadPaintFilterVisibility() (baris 454) menandai label #lead-label-sales hidden (baris 462) lalu menyetel "
        "opsi 'Calon Konsumen' dengan properti hidden (baris 467). Opsi tersebut tidak dihapus dari DOM supaya "
        "tautan menu Database Konsumen (/data-konsumen?kategori=prospek) masih dapat menyetel select.value — "
        "pilihan hidden tetap bisa dipilih lewat program tetapi tidak muncul pada daftar dropdown. Penapis Status "
        "Cold/Warm/Hot dipertahankan pada kategori prospek karena status masih menjadi milik calon konsumen "
        "(revisi 26); yang dihapus adalah kolomnya pada tabel. Super Admin dan Admin Sales tidak berubah: keduanya "
        "tetap melihat kolom Status pada halaman gabungan.",
        ["TC-R32", "FF-R38", "FF-R24"],
        "TC-R32 memeriksa sumber blade (leadPakaiStatus, push kolom Status yang bersyarat, tidak adanya render "
        "statis kolom Status, opsiCalon.hidden, dan toggle label sales) lalu menuntut "
        "GET /api/customer-leads?category=prospek untuk role sales tetap menghasilkan baris berkategori prospek "
        "meski pilihannya disembunyikan. Di peramban sebagai sales: kategori konsumen/dibatalkan 9 kolom tanpa "
        "Status, kategori prospek 8 kolom tanpa Status dengan #lead-category bernilai 'prospek' dan opsi "
        "Calon Konsumen hidden=true, serta #lead-label-sales hidden.",
    ),
    (
        "Revisi 40", "Penapis Sales Penanganan menjadi pencarian berisi seluruh nama sales",
        "Pada role admin sales, pilihan pada search bar sales penanganan menampilkan nama semua sales, bukan hanya "
        "opsi Semua Sales, dan opsi Semua Sales dihapus.",
        "Kendali <select id=\"lead-sales\"> beserta opsinya diganti menjadi <input id=\"lead-sales\" "
        "type=\"search\" list=\"lead-sales-list\"> dan sebuah <datalist id=\"lead-sales-list\"> kosong yang diisi "
        "dari halaman (resources/views/app/data-konsumen.blade.php:45-46). leadPaintFilters() (baris 446) hanya "
        "menulis opsi datalist dari leadSalesNames, sementara pencocokan baris dilakukan leadSalesCocok() "
        "(baris 171) terhadap gabungan nama dan sales_code sehingga isian 'Budi' maupun 'SL-002' bekerja dan isian "
        "kosong menampilkan seluruh baris; kendali dipantau dengan event 'input' + renderDebounced. Perbaikan "
        "mendasar: pemanggilan GET /api/sales keluar dari gerbang leadBolehKelola() (super_admin + sales) dan "
        "dipindah ke leadMuatPilihan() (baris 491) dengan syarat !leadIsSales(), sebab selama ini Admin Sales "
        "tidak pernah menerima daftar nama sehingga dropdown-nya hanya berisi 'Semua Sales'. Rute /api/sales sendiri "
        "sudah mengizinkan admin_sales (routes/api.php:107-109). Role sales tetap tidak menampilkan kendali ini "
        "(revisi 39).",
        ["TC-R31", "FF-R39", "FF-A08"],
        "TC-R31 menuntut GET /api/sales menghasilkan 200 dengan minimal dua baris bersales_profile untuk Super "
        "Admin, memeriksa potongan sumber leadMuatPilihan() memuat LP.fetchJson('/sales') dan tidak lagi dibungkus "
        "gerbang lama, menuntut keberadaan leadSalesCocok + input type=search + datalist, serta menuntut frasa "
        "'Semua Sales' dan pemanggilan tugas lama tidak ditemukan lagi. Di peramban sebagai Admin Sales: datalist "
        "berisi 9 nama sales, isian 'Budi' menyusut 41 → 35 baris, 'SL-002' → 4 baris, dan isian kosong kembali 41 "
        "baris; teks 'Semua Sales' tidak ada di halaman.",
    ),
    (
        "Revisi 41", "Unit Ditempati dan Pesanan Tambahan pada konsumen, Biaya Tambahan pada pembayaran",
        "Kolom status pada konsumen yang sudah diverifikasi dihapus, kolom unit diminati menjadi unit ditempati "
        "(tetap ada pada kategori calon konsumen), ditambah kolom pesanan tambahan pada konsumen serta kolom "
        "pesanan tambahan dan biaya tambahan pada pembayaran.",
        "app/Http/Controllers/Api/CustomerLeadController.php menambahkan addition_description pada eager load "
        "bookings lalu membaca booking aktif — atau booking pembatalan terakhir bila pemesananannya sudah dibatalkan "
        "— menjadi dua atribut turunan pada setiap baris: unit_ditempati (kode unit) dan pesanan_tambahan "
        "(keterangan permintaan tambahan konstruksi), baris 85-92. Di antarmuka, leadKolom() memberi label "
        "'Unit Ditempati' untuk kategori selain calon konsumen dan mempertahankan 'Unit Diminati' pada kategori "
        "prospek (resources/views/app/data-konsumen.blade.php:262), menambah kolom 'Pesanan Tambahan' dengan nilai "
        "'Tidak ada' bila kosong (baris 267), sementara kolom Status hanya dirender saat leadPakaiStatus() sehingga "
        "pada kategori konsumen dan dibatalkan kolom tersebut tidak tampil untuk semua peran (baris 258). "
        "resources/views/app/pembayaran.blade.php menyisipkan kolom 'Pesanan Tambahan' dan 'Biaya Tambahan' tepat "
        "setelah kolom Konsumen (baris 181-182) lewat paymentPesananTambahan()/paymentBiayaTambahan() "
        "(baris 101-106) yang membaca booking induknya karena permintaan dan biaya tambahan melekat pada booking, "
        "bukan pada catatan pembayaran.",
        ["TC-R30", "FF-R40", "FF-R15"],
        "TC-R30 membuat lead hot, membooking-nya dengan addition_cost 18.000.000 dan keterangan 'Menaikkan plafon "
        "dan kanopi carport', mencatat lalu memverifikasi pembayaran, dan menuntut baris GET /api/customer-leads "
        "bercategory 'konsumen' membawa unit_ditempati sama dengan kode unit serta pesanan_tambahan sama dengan keterangan "
        "tersebut, plus GET /api/payments?booking_id= yang membawa booking.addition_description dan "
        "booking.addition_cost = 18000000. Di peramban, kategori konsumen Super Admin menampilkan 6 baris dengan "
        "header 9 kolom [Konsumen, No. Telepon, Kategori, Sales, Unit Ditempati, Pesanan Tambahan, Chat Awal, "
        "Follow Up Terakhir, Aksi]; baris 'Konsumen Tampilan 791857' terbaca R15-465138-9 dan keterangan "
        "tambahannya; halaman /pembayaran memiliki 12 kolom dan baris BK2026110039 menampilkan Rp 18.000.000 "
        "sedangkan baris tanpa permintaan tambahan menampilkan 'Tidak ada' dan Rp 0.",
    ),
]

ada = {r["kode"] for r in kf}
for i, (kode, judul, kebutuhan, impl, rujukan, catatan) in enumerate(REVISI, 43):
    if kode in indeks:
        baris = indeks[kode]
        baris.update({"kebutuhan": kebutuhan, "impl": impl, "catatan": catatan, "status": "Sesuai"})
        baris["rujukan"] = list(dict.fromkeys(baris["rujukan"] + rujukan))
        baris["bukti"] = ", ".join(baris["rujukan"])
        continue
    kf.append({
        "no": str(i),
        "kode": kode,
        "kebutuhan": f"{judul}. {kebutuhan}",
        "impl": impl,
        "bukti": ", ".join(rujukan),
        "status": "Sesuai",
        "catatan": catatan,
        "rujukan": list(dict.fromkeys(rujukan)),
    })

# Bukti revisi 4 juga dipakai pada butir pengelolaan cluster lama.
ubah("b.1 cluster", tambah=["FF-R04"])

# Revisi 14 dan 19 menutup dua butir yang sebelumnya berstatus Sebagian.
ubah(
    "a.3",
    tambah=["TC-R10", "TC-R11", "FF-R14"],
    status="Sesuai",
    impl=("AuthController::resetPassword untuk pemulihan mandiri, SalesController::resetPassword untuk reset oleh "
          "super admin, serta POST /api/forgot-password yang menyimpan token 64 karakter sebagai hash SHA-256 "
          "(berlaku 10 menit) dan mengirim Mailable LinkAturUlangSandi berisi tautan /reset-password?token=...; "
          "POST /api/reset-password/token menetapkan sandi baru cukup dengan token tersebut lalu mencabut token dan "
          "seluruh sesi lama. Halaman auth/reset-password.blade.php memakai tiga langkah tanpa meminta sandi lama."),
    catatan=("Pemulihan sandi tersedia bagi seluruh akun internal dan tidak lagi memerlukan sandi lama karena bukti "
             "kepemilikan dipindahkan ke tautan verifikasi yang dikirim ke email akun (revisi 14); akun konsumen "
             "memang diblokir dari login sehingga tidak memerlukan jalur ini."),
)
ubah(
    "Integritas masukan",
    tambah=["TC-R15", "FF-R19"],
    impl=("Rule::unique(...)->whereNull('deleted_at') pada unit_code, email sales, nama proyek per perumahan, dan "
          "nomor telepon lead (CustomerLeadController::aturanTelepon dengan ignore(id) saat pembaruan); findOrFail "
          "menghasilkan 404 terkendali dan aksi pada data berelasi ditolak."),
    catatan=("Indeks unik tingkat basis data pada email, kode unit, kode sales, dan nama skema diturunkan menjadi "
             "indeks biasa oleh migration penghapusan lunak sehingga keunikan dijaga di lapisan validasi. Nomor "
             "handphone lead kini hanya sekali pakai (revisi 19) dan lead yang dihapus lembut membebaskan "
             "nomornya, sementara email pada customer_leads tetap boleh kosong atau sama karena identitas lead yang "
             "dipakai sistem adalah nomor telepon."),
)

# Revisi 38 mengubah cara konsumen menjelajah daftar unit publik: tanpa kotak pencarian.
ubah(
    "g.1",
    tambah=["TC-R29", "FF-R37"],
    impl=("PublicInfoController::publicUnits memuat unit_type, land_size, building_size, dan price. Halaman daftar "
          "unit publik menyaring hasilnya lewat pemilih proyek (#cluster) dan urutan harga (#sort); kotak pencarian "
          "kode unit/tipe rumah dihapus pada revisi 38 sehingga penelusuran konsumen bersifat jelajah, bukan kueri."),
    catatan=("Pengguna meminta pencarian kode unit/tipe rumah pada search bar publik dihapus (revisi 38); penapis "
             "proyek dan urutan harga tetap ada dan parameter ?proyek= dari kartu lokasi peta tetap berfungsi, "
             "sedangkan pencarian Blok/Status/Proyek pada modul internal Data Unit (revisi 11) tidak terpengaruh."),
)

# Revisi 39-41 menyesuaikan tampilan daftar konsumen dan pembayaran untuk semua peran.
ubah(
    "e.3",
    tambah=["TC-R32", "FF-R38"],
    impl=("CustomerLeadController::index dipangkas ke sales_id milik pengguna yang login. Pada daftar versi sales, "
          "resources/views/app/data-konsumen.blade.php tidak lagi merender kolom Status untuk kategori apa pun "
          "(leadPakaiStatus, revisi 39), label penapis Sales Penanganan disembunyikan, dan opsi 'Calon Konsumen' "
          "pada penapis Kategori diberi atribut hidden tanpa menghapusnya dari DOM sehingga tautan menu Database "
          "Konsumen (?kategori=prospek) tetap berfungsi."),
    catatan=("Role sales hanya menangani konsumennya sendiri, sehingga penapis sales dan kolom status diminta "
             "dihapus (revisi 39). Penapis Status Cold/Warm/Hot tetap tersedia pada kategori prospek karena status "
             "masih menjadi milik calon konsumen (revisi 26); yang dihilangkan hanyalah kolom tabelnya."),
)
ubah(
    "e.5",
    tambah=["TC-R31", "FF-R39", "FF-A08"],
    impl=("Indeks tidak dipangkas untuk role super_admin/admin_sales. Penapis Sales Penanganan pada halaman "
          "Data Konsumen berupa kotak pencarian (#lead-sales bertipe search) yang bersumber dari GET /api/sales dan "
          "menampilkan seluruh nama sales pada datalist; isian kosong berarti semua baris ditampilkan dan pencocokan "
          "menerima nama maupun kode sales (revisi 40)."),
    catatan=("Permintaan pengguna: opsi 'Semua Sales' dihapus dan pilihannya diganti daftar nama sales untuk Admin "
             "Sales. Akar masalah dropdown yang sebelumnya kosong adalah pemanggilan /api/sales yang berada di "
             "dalam gerbang leadBolehKelola() (super_admin + sales), padahal rute tersebut juga mengizinkan "
             "admin_sales; sejak leadMuatPilihan() peran internal selain sales selalu menerima daftar nama."),
)
ubah(
    "d.2",
    tambah=["TC-R30", "FF-R40"],
    impl=("PUT /payments/{id}/verify khusus role:super_admin; kolom verified_by dan verified_at terisi; penolakan "
          "tidak mengubah sisa pembayaran. Tabel Data Pembayaran kini menampilkan kolom Pesanan Tambahan dan Biaya "
          "Tambahan yang dibaca dari booking induknya, sehingga super admin melihat permintaan konstruksi sebelum "
          "memutuskan verifikasi (revisi 41)."),
)
ubah(
    "Revisi 16",
    tambah=["TC-R30", "FF-R40"],
    catatan=("Semenjak revisi 41 kolom 'Unit Diminati' berganti menjadi 'Unit Ditempati' pada kategori konsumen dan "
             "dibatalkan; nilainya dibaca dari booking aktif atau booking pembatalan terakhir, sehingga rumah yang "
             "pernah ditempati lalu dibatalkan tetap tercantum pada barisnya (unit_ditempati + pesanan_tambahan pada "
             "GET /api/customer-leads)."),
)
ubah(
    "Revisi 17",
    tambah=["TC-R30", "FF-R40"],
    catatan=("Permintaan dan biaya tambahan konstruksi yang semula hanya tampak pada formulir dan detail booking kini "
             "ikut tampil pada daftar: kolom Pesanan Tambahan pada Data Konsumen dan kolom Pesanan Tambahan serta "
             "Biaya Tambahan pada Data Pembayaran (revisi 41). Baris booking tanpa permintaan menampilkan 'Tidak ada' "
             "dan biaya Rp 0."),
)

def tambah(kode, *, no, kebutuhan, impl, rujukan, catatan=""):
    """Menambahkan butir kebutuhan baru bila belum ada, lalu menyegarisnya."""
    baris = indeks.get(kode)
    if baris is None:
        baris = {"no": no, "kode": kode, "kebutuhan": kebutuhan, "impl": impl, "bukti": "",
                 "status": "Sesuai", "catatan": catatan, "rujukan": list(rujukan)}
        kf.append(baris)
        indeks[kode] = baris
    baris["no"] = no
    baris["kebutuhan"] = kebutuhan
    baris["impl"] = impl
    baris["rujukan"] = list(dict.fromkeys(baris["rujukan"] + rujukan))
    baris["bukti"] = ", ".join(baris["rujukan"])
    baris["status"] = "Sesuai"
    if catatan:
        baris["catatan"] = catatan
    return baris


# ---------------------------------------------------------------- revisi 42-43
tambah(
    "Revisi 42",
    no="84",
    kebutuhan="Rumah yang sudah dibooking otomatis tidak lagi muncul dalam pilihan booking, dan pilihan booking "
              "berjenjang berdasarkan proyek lebih dahulu lalu unit/rumah atau bloknya.",
    impl="UnitController::availableUnits (app/Http/Controllers/Api/UnitController.php:151-158) menyaring unit "
         "berstatus available sekaligus whereDoesntHave('bookings', status != Booking::STATUS_DIBATALKAN), sehingga "
         "rumah yang memegang pemesanan aktif hilang dari GET /api/units-available dan kembali muncul begitu "
         "bookingnya dihapus atau dibatalkan. BookingController::store menjaga hal yang sama di sisi tulis: bila unit "
         "masih punya booking aktif, permintaan ditolak HTTP 422 'Unit tidak dapat dipesan karena sudah memiliki "
         "booking aktif.' (app/Http/Controllers/Api/BookingController.php:108) walaupun klien mengirim daftar basi. "
         "Formulir Buat Booking memakai pemilih wajib 'Proyek' (name cluster_id, resources/views/app/"
         "booking.blade.php:283) yang opsinya disusun bookingProjectOptions() (baris 231) dari unit tersedia dan "
         "disortir localeCompare 'id'; opsi unit ditulis ulang oleh bookingPaintUnits() (baris 253) lewat "
         "bookingUnitSelectOptions() (baris 242) dengan label 'Blok <unit_code> — <unit_type> — <harga>' serta "
         "placeholder 'Pilih proyek lebih dahulu' / 'Pilih unit dan bloknya' / 'Semua unit pada proyek ini sudah "
         "dibooking'. bookingOpenCreate() menunggu bookingRefreshChoices() (baris 507) yang memuat ulang "
         "/customer-leads dan /units-available setiap modal dibuka, sehingga pilihan tidak pernah kedaluwarsa.",
    rujukan=["TC-R33", "FF-R41"],
    catatan="Kata 'Blok' dipakai sebagai label tampilan untuk unit_code karena tabel units tidak memiliki kolom blok "
            "tersendiri (kolom yang sama sudah dipakai halaman Data Unit sejak revisi 11). Penambahan booking aktif "
            "pada satu unit juga tetap ditolak lewat penjaga status unit, jadi revisi ini hanya menutup celah unit "
            "yang statusnya belum sempat berubah. TC-R33 membuktikan tiga hal: unit lenyap dari GET /api/units-available, "
            "percobaan booking ulang atas unit yang sama (setelah statusnya dikembalikan paksa menjadi available) "
            "dibalas 422, dan unit kembali tersedia setelah DELETE /bookings/{id}.",
)

tambah(
    "Revisi 43",
    no="85",
    kebutuhan="Pada booking dan pencatatan pembayaran ditambahkan pilihan pembayaran dengan kategori DP, Cicilan, "
              "dan Biaya Tambahan; biaya tambahan berada di luar harga unit sehingga tidak mengurangi sisa pembayaran.",
    impl="Migrasi 2026_11_21_170000_add_payment_category menambahkan payments.payment_category (varchar 20, nullable) "
         "dengan penjaga Schema::hasColumn. Payment (app/Models/Payment.php) menyimpan CATEGORY_DP/CICILAN/"
         "BIAYA_TAMBAHAN, CATEGORY_LABELS 'DP'/'Cicilan'/'Biaya Tambahan', KATEGORI_HARGA_UNIT, dan "
         "scopeMengurangiHargaUnit() yang tetap menghitung baris lama ber-kategori NULL; cangkupan inilah yang dipakai "
         "Booking::settlementStatus() dan PaymentController::recalculateBooking() "
         "(app/Http/Controllers/Api/PaymentController.php:241) sehingga pembayaran berkategori biaya tambahan tidak "
         "pernah menurunkan remaining_amount maupun memicu status lunas. PaymentController::store memvalidasi "
         "'nullable|in:' . Payment::CATEGORIES, membuka penjaga 'booking sudah lunas' khusus kategori biaya tambahan, "
         "menolak kategori itu bila addition_cost kosong, menuntut pembayaran tambahan tidak melebihi sisa biaya "
         "tambahan, serta menyaring GET /payments?payment_category=. BookingController::store menerima initial_payment "
         "{payment_category, amount, payment_date, payment_method} yang diuraikan catatPembayaranAwal() "
         "(app/Http/Controllers/Api/BookingController.php:383-402) menjadi satu ledger berstatus menunggu verifikasi "
         "dengan payment_type warisan skema booking, dan ditolak lebih dahulu oleh tolakKategoriDiLuarBiayaTambahan() "
         "bila kategori biaya tambahan dipakai tanpa addition_cost. Antarmuka: modal Buat Booking mendapat saklar "
         "'Catat Pembayaran Sekarang' plus pilihan kategori, nominal (terisi otomatis dari simulasi uang muka/angsuran "
         "atau addition_cost), tanggal, dan metode; halaman Data Pembayaran mendapat kolom Kategori (label "
         "'Pembayaran unit' untuk baris lama), pilihan kategori wajib pada modal Catat Pembayaran dengan saran nominal "
         "per kategori, penapis pencarian, kolom Kategori pada riwayat pembayaran detail booking, serta penyebutan "
         "kategori pada dialog dan notifikasi verifikasi.",
    rujukan=["TC-R34", "TC-R35", "FF-R42", "FF-R43"],
    catatan="Keputusan akuntansi yang diterima pengguna: DP dan cicilan mengurangi harga unit, biaya tambahan dicatat "
            "terpisah dan tidak pernah mengubah sisa pembayaran maupun memicu pelunasan — karena itu booking yang "
            "sudah lunas harga unitnya tetap boleh menerima pembayaran biaya tambahan, dan pemilih booking pada modal "
            "Catat Pembayaran menyisakan booking lunas yang sisa biaya tambahannya belum terbayar. Nominal tetap "
            "boleh diubah setelah terisi otomatis; kategori tanpa saran nominal (misalnya biaya tambahan pada booking "
            "tanpa addition_cost) mengosongkan isian supaya nilai dari kategori lain tidak ikut terkirim. Nama "
            "atribut 'kategori pembayaran' dipasang pada lang/id/validation.php agar pesan validasi tetap "
            "berbahasa Indonesia. TC-R34 menguji ketiga kategori sampai terverifikasi dan menuntut sisa hanya "
            "berkurang dari DP dan cicilan; TC-R35 menguji pembayaran pertama dari form booking.",
)

ubah(
    "c.1",
    tambah=["TC-R33", "FF-R41"],
    impl=("BookingController::store menolak lead yang statusnya bukan hot (422) dan menolak unit yang masih memegang "
          "booking aktif (422, revisi 42). Formulir Buat Booking memilih konsumen hot, lalu proyek, lalu unit/blok "
          "milik proyek itu yang masih tersedia."),
)
ubah(
    "b.2",
    tambah=["TC-R33", "FF-R41"],
    impl=("UnitController::availableUnits (GET /api/units-available) mengembalikan unit berstatus tersedia yang tidak "
          "sedang memegang booking aktif (revisi 42)."),
)
ubah(
    "d.1",
    tambah=["TC-R34", "TC-R35", "FF-R42", "FF-R43"],
    impl=("PaymentController::store menerima multipart transfer_receipt (jpg/jpeg/png/pdf, maksimum 5 MB) pada disk "
          "public beserta kategori pembayaran dp|cicilan|biaya tambahan (payment_category, revisi 43); modal Catat "
          "Pembayaran mengisi nominal otomatis sesuai kategori dan booking terpilih, dan pembayaran pertama dapat "
          "dicatat langsung dari formulir Buat Booking lewat initial_payment."),
)
ubah(
    "d.3",
    tambah=["TC-R34"],
    impl=("PaymentController::recalculateBooking + Booking::settlementStatus() menurunkan status (menunggu DP, dp "
          "lunas, cicilan, lunas) dari ledger pembayaran yang terverifikasi; sejak revisi 43 hanya pembayaran "
          "berkategori DP dan cicilan (termasuk baris lama tanpa kategori) yang mengurangi harga unit, sedangkan "
          "biaya tambahan dihitung terpisah."),
)
ubah(
    "d.2",
    tambah=["TC-R34", "FF-R43"],
    catatan=("Dialog verifikasi dan notifikasi menunggu verifikasi kini menyebut kategori pembayaran "
             "('Pembayaran Biaya Tambahan (KPR) sebesar Rp 5.000.000 untuk booking BK2026110019 menunggu verifikasi.') "
             "dan tabel Data Pembayaran bertambah menjadi 13 kolom dengan Kategori di antara Jenis dan Metode."),
)
ubah(
    "Revisi 17",
    tambah=["TC-R34", "FF-R42", "FF-R43"],
    catatan=("Permintaan dan biaya tambahan konstruksi yang semula hanya tampak pada formulir dan detail booking kini "
             "ikut tampil pada daftar: kolom Pesanan Tambahan pada Data Konsumen dan kolom Pesanan Tambahan serta "
             "Biaya Tambahan pada Data Pembayaran (revisi 41). Baris booking tanpa permintaan menampilkan 'Tidak ada' "
             "dan biaya Rp 0. Sejak revisi 43 biaya tambahan juga dapat dibayar sebagai kategori tersendiri — "
             "langsung dari form booking atau dari modal Catat Pembayaran — tanpa pernah mengurangi sisa pembayaran "
             "harga unit."),
)
ubah(
    "Revisi 41",
    tambah=["FF-R43"],
    catatan=("TC-R30 membuat lead hot, membooking-nya dengan addition_cost 18.000.000 dan keterangan 'Menaikkan plafon "
             "dan kanopi carport', mencatat lalu memverifikasi pembayaran, dan menuntut baris GET /api/customer-leads "
             "bercategory 'konsumen' membawa unit_ditempati sama dengan kode unit serta pesanan_tambahan sama dengan "
             "keterangan tersebut, plus GET /api/payments?booking_id= yang membawa booking.addition_description dan "
             "booking.addition_cost = 18000000. Di peramban, kategori konsumen Super Admin menampilkan header 9 kolom "
             "[Konsumen, No. Telepon, Kategori, Sales, Unit Ditempati, Pesanan Tambahan, Chat Awal, Follow Up "
             "Terakhir, Aksi]; baris 'Konsumen Tampilan 791857' terbaca R15-465138-9 dan keterangan tambahannya. "
             "Halaman /pembayaran semula 12 kolom dan menjadi 13 kolom sejak revisi 43 menambah Kategori di antara "
             "Jenis dan Metode; kolom Pesanan Tambahan dan Biaya Tambahan tetap terbaca, contoh baris BK2026110019 "
             "'Kanopi carport uji revisi 43 · Rp 5.000.000 · KPR · Biaya Tambahan · Transfer Bank · Rp 5.000.000'."),
)

json.dump(kf, open(PATH, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
hitung = {}
for r in kf:
    hitung[r["status"]] = hitung.get(r["status"], 0) + 1
print(f"{len(kf)} butir kebutuhan; status {hitung}")
print("belum Sesuai:", ", ".join(f"{r['kode']}={r['status']}" for r in kf if r["status"] != "Sesuai"))
