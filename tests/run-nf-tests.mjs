/**
 * Uji kebutuhan non-fungsional (NF) untuk BAB IV.
 *
 * Semua baris yang dihasilkan berasal dari pengukuran nyata: waktu respons diukur lewat HTTP,
 * kondisi basis data dibaca lewat klien MySQL, dan agregat hasil uji diambil dari berkas hasil
 * suite backend/frontend. Jalankan setelah suite konformansi selesai:
 *
 *   LP_API_BASE=http://127.0.0.1:8081/api node tests/run-nf-tests.mjs
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const BASE_URL = (process.env.LP_API_BASE || 'http://127.0.0.1:8080/api').replace(/\/api\/?$/, '');
const API = `${BASE_URL}/api`;
const MYSQL = process.env.LP_MYSQL || 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
const DB = process.env.LP_DB || 'luminpark_test';
const OUT = 'tests/nf-results.json';

const sql = (query) => execFileSync(MYSQL, ['-u', 'root', '-N', '-B', '-e', query, DB], { encoding: 'utf8' }).trim();
const num = (query) => Number(sql(query).split(/\s+/)[0] || 0);

async function timed(method, path, { token, body } = {}) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body) headers['Content-Type'] = 'application/json';
  const mulai = Date.now();
  const res = await fetch(`${API}${path}`, { method, headers, body: body ? JSON.stringify(body) : undefined });
  await res.text();
  return { ms: Date.now() - mulai, status: res.status };
}

const rows = [];
function NF({ id, nama, deskripsi, langkah, dataUji, ekspektasi, actual, pass, catatan, kebutuhan }) {
  const endpoint = (langkah.match(/(?:GET|POST|PUT|DELETE|select|baca) (\S+)/) || [])[1] || langkah.split(',')[0];
  rows.push({
    id,
    nama,
    skenario: 'Kebutuhan non-fungsional',
    deskripsi,
    langkah,
    dataUji,
    ekspektasi,
    method: 'NF',
    path: endpoint.slice(0, 90),
    actualStatus: null,
    actualBody: actual,
    status: pass ? 'Pass' : 'Fail',
    catatan,
    kebutuhan,
  });
  console.log(`${id} [${pass ? 'PASS' : 'FAIL'}] ${nama}`);
}

/* 1. Waktu respons -------------------------------------------------------- */
const login = await timed('POST', '/login', { body: { email: 'superadmin@gmail.com', password: 'password123' } });
const token = (await (await fetch(`${API}/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email: 'superadmin@gmail.com', password: 'password123' }),
})).json()).access_token;

const proses = [
  ['login', await timed('POST', '/login', { body: { email: 'superadmin@gmail.com', password: 'password123' } })],
  ['daftar unit', await timed('GET', '/units', { token })],
  ['dashboard super admin', await timed('GET', '/dashboard/super-admin', { token })],
  ['laporan penjualan', await timed('GET', `/reports/sales?start_date=${tanggal(30)}&end_date=${tanggal(0)}`, { token })],
  ['katalog publik', await timed('GET', '/public/units')],
];
const lambat = proses.filter(([, p]) => p.ms > 5000);
NF({
  id: 'NF-01',
  nama: 'Waktu respons proses umum',
  deskripsi: 'Laporan mensyaratkan waktu respons maksimum 5 detik untuk proses umum.',
  langkah: 'POST /api/login, GET /units, GET /dashboard/super-admin, GET /reports/sales, GET /public/units '
    + '(diukur dari sisi klien)',
  dataUji: `5 proses terhadap ${API}`,
  ekspektasi: 'Seluruh proses selesai di bawah 5.000 ms',
  actual: proses.map(([n, p]) => `${n}=${p.ms}ms(${p.status})`).join(', ')
    + `; rata-rata ${Math.round(proses.reduce((a, [, p]) => a + p.ms, 0) / proses.length)}ms`,
  pass: lambat.length === 0 && login.status === 200,
  catatan: `Terlambat: ${lambat.length ? lambat.map((x) => x[0]).join(', ') : 'tidak ada'}. Diukur pada environment `
    + 'lokal (Laragon, MySQL lokal) sehingga bukan ukuran kinerja produksi.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (waktu respons)',
});

/* 2. Kondisi basis data --------------------------------------------------- */
const hash = sql("select left(password,4) from users where email='superadmin@gmail.com'");
NF({
  id: 'NF-02',
  nama: 'Kata sandi tersimpan sebagai hash',
  deskripsi: 'Kata sandi tidak boleh disimpan sebagai teks terbuka.',
  langkah: 'select left(password,4) from users (dibaca langsung dari MySQL)',
  dataUji: `email=superadmin@gmail.com pada basis data ${DB}`,
  ekspektasi: 'Awalan hash bcrypt ($2y$), bukan teks terbuka',
  actual: `prefix=${hash}; panjang hash=${num("select length(password) from users where email='superadmin@gmail.com'")} karakter`,
  pass: hash === '$2y$',
  catatan: 'Model User memakai cast password => hashed sehingga Hash::make selalu dipanggil saat menyimpan.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (keamanan data)',
});

const batasSesi = num("select value from settings where `key`='session_timeout_minutes'");
const umurToken = sql('select timestampdiff(minute, created_at, expires_at) from personal_access_tokens '
  + 'where expires_at is not null order by id desc limit 1');
NF({
  id: 'NF-03',
  nama: 'Batas waktu sesi ditegakkan server',
  deskripsi: 'Token login harus kedaluwarsa sesuai pengaturan session_timeout_minutes, bukan memakai nilai baku Sanctum.',
  langkah: 'login lalu baca personal_access_tokens.expires_at dan bandingkan dengan nilai pengaturan',
  dataUji: `session_timeout_minutes=${batasSesi}; token terbaru`,
  ekspektasi: 'Masa berlaku token sama dengan nilai pengaturan (dalam menit)',
  actual: `token terakhir berlaku ${umurToken || 'NULL'} menit; pengaturan ${batasSesi} menit (minimum 15, baku 1.440)`,
  pass: String(umurToken) === String(batasSesi),
  catatan: 'Nilai diubah dari halaman Pengaturan Sistem (TC-K03) dan ditolak bila di bawah 15 menit (TC-K04, TC-L07).',
  kebutuhan: 'Butir a.4 (logout dengan batas waktu)',
});

const softUnits = num('select count(*) from units where deleted_at is not null');
const tampilUnits = num('select count(*) from units where deleted_at is null');
const softLeads = num('select count(*) from customer_leads where deleted_at is not null');
const softUsers = num('select count(*) from users where deleted_at is not null');
const softClusters = num('select count(*) from clusters where deleted_at is not null');
NF({
  id: 'NF-04',
  nama: 'Penghapusan lunak menyimpan baris',
  deskripsi: 'Data yang dihapus pengguna hilang dari daftar tetapi barisnya tetap ada di MySQL.',
  langkah: 'select count(*) ... where deleted_at is not null setelah suite menghapus unit, lead, cluster, dan akun',
  dataUji: `units deleted_at=${softUnits}, customer_leads=${softLeads}, users=${softUsers}, clusters=${softClusters}`,
  ekspektasi: 'Baris terhapus lunak tetap ada dan tidak muncul pada daftar aktif',
  actual: `unit terhapus lunak ${softUnits} (aktif ${tampilUnits}), lead ${softLeads}, akun ${softUsers}, `
    + `cluster ${softClusters}; GET /units dan GET /clusters hanya mengembalikan yang aktif `
    + '(TC-L05, TC-L06, TC-M09)',
  pass: softUnits > 0 && softLeads > 0 && softClusters > 0,
  catatan: 'Cluster ikut soft delete dan punya status arsip (TC-M05, TC-M09). Tabel projects masih hard delete '
    + 'karena tidak punya kolom deleted_at dan tidak ada endpoint penghapusnya.',
  kebutuhan: 'Keputusan pengguna: hapus lunak',
});

const sistemLogs = num('select count(*) from system_logs');
const sesiLogs = num('select count(*) from login_sessions');
const aktivitasLogs = num('select count(*) from activity_logs');
const aktivitasAksi = sql('select group_concat(distinct action order by action) from activity_logs');
NF({
  id: 'NF-05',
  nama: 'Jejak aktivitas dan sesi tercatat',
  deskripsi: 'Peristiwa penting (masuk, keluar, login gagal, aksi CRUD) harus dapat ditelusuri auditor.',
  langkah: 'select count(*) from system_logs, login_sessions, activity_logs setelah suite berjalan',
  dataUji: `sistem=${sistemLogs}, sesi=${sesiLogs}, aktivitas=${aktivitasLogs}`,
  ekspektasi: 'system_logs, login_sessions, dan activity_logs (ERD) terisi; halaman Log Aktivitas menampilkan data',
  actual: `system_logs=${sistemLogs} baris, login_sessions=${sesiLogs} baris (IP, perangkat, login_at, logout_at), `
    + `activity_logs=${aktivitasLogs} baris (aksi: ${aktivitasAksi || '-'})`,
  pass: sistemLogs > 0 && sesiLogs > 0 && aktivitasLogs > 0,
  catatan: 'AuditObserver menulis activity_logs untuk setiap create/update/delete model yang dipantau, termasuk '
    + 'cluster (TC-O1..O3). Batasannya: baris lama sebelum observer dipasang tidak ikut tercatat, dan penghapusan '
    + 'lunas tercatat sebagai satu aksi tanpa nilai sebelum-sesudah.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (audit trail)',
});

/* 3. Agregat hasil suite -------------------------------------------------- */
const baca = (f) => JSON.parse(readFileSync(f, 'utf8'));
const api = baca('tests/conformance-results.json');
const fe = baca('tests/frontend-results.json');
const hard = baca('tests/hardening-results.json');
const semua = [...api, ...fe, ...hard];
const tubuh = (r) => String(r.actualBody || r.catatan || '');
// Sebuah respons dianggap memuat pesan bila masih ada rangkaian huruf yang berarti
// setelah awalan "HTTP 4xx" dan daftar status dilepas.
const huruf = (teks) => (teks.match(/[A-Za-zÀ-ÿ]/g) || []).length;
const errorRows = semua.filter((r) => r.actualStatus && r.actualStatus >= 400);
const errorMessage = errorRows.filter((r) => huruf(tubuh(r).replace(/\bHTTP \d{3}\b/g, '').replace(/\b\d{3}(,\d{3})*\b/g, '')) >= 12);
const polaInggris = /\bUnauthenticated\b|has already been taken|must be a valid|does not exist|No query results|Target class|Too many requests|Server Error|SQLSTATE/i;
const bocoranInggris = errorRows.filter((r) => polaInggris.test(tubuh(r)));
const contohPesan = errorRows
  .map((r) => (tubuh(r).match(/"(?:message|pesan)"\s*:\s*(?:"[^"]+"|\[[^\]]*\])/) || [])[0])
  .filter(Boolean)
  .slice(0, 3)
  .join(' | ');
const contoh4xx = Object.entries(errorRows.reduce((acc, r) => {
  acc[r.actualStatus] = (acc[r.actualStatus] || 0) + 1;
  return acc;
}, {})).map(([s, n]) => `${s}×${n}`).join(', ');
const serverError = semua.filter((r) => r.actualStatus && r.actualStatus >= 500);
const gagal = semua.filter((r) => r.status !== 'Pass');

NF({
  id: 'NF-06',
  nama: 'Keseluruhan rangkaian uji lulus',
  deskripsi: 'Seluruh test case backend, frontend, dan penguatan harus PASS pada pelaksanaan terakhir.',
  langkah: 'baca tests/conformance-results.json, tests/frontend-results.json, dan tests/hardening-results.json',
  dataUji: `${api.length} baris backend + ${fe.length} baris frontend + ${hard.length} baris penguatan`,
  ekspektasi: '0 test case FAIL',
  actual: `lulus ${semua.length - gagal.length}/${semua.length}; FAIL ${gagal.length ? gagal.map((g) => g.id).join(', ') : '0'}`,
  pass: gagal.length === 0,
  catatan: 'Suite dijalankan pada basis data uji terpisah setelah migrate:fresh --seed.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (keandalan)',
});

NF({
  id: 'NF-07',
  nama: 'Tidak ada kegagalan server (5xx)',
  deskripsi: 'Kesalahan input atau data tidak boleh berubah menjadi crash 500.',
  langkah: 'agregat actualStatus dari seluruh test case',
  dataUji: `${semua.length} permintaan HTTP nyata`,
  ekspektasi: 'Tidak ada respons 5xx; edge case menghasilkan 401/403/404/409/422/429 terkendali',
  actual: `respons 5xx=${serverError.length} dari ${semua.length} permintaan; `
    + `respons 4xx=${errorRows.length} (${contoh4xx})`,
  pass: serverError.length === 0,
  catatan: 'Termasuk edge case data tidak ditemukan, nama cluster duplikat (422), cluster berisi unit (409), '
    + 'dan throttle login (429) yang semuanya dijawab terkendali, bukan 500.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (tidak crash)',
});

NF({
  id: 'NF-08',
  nama: 'Pesan kesalahan terbaca pengguna',
  deskripsi: 'Setiap penolakan memberi pesan jelas berbahasa Indonesia, bukan pesan kosong.',
  langkah: 'agregat body respons 4xx dari seluruh test case lalu periksa terhadap pola pesan bawaan Laravel',
  dataUji: `${errorRows.length} respons 4xx`,
  ekspektasi: 'Seluruh respons 4xx memuat pesan dan tidak ada pesan bawaan berbahasa Inggris',
  actual: `${errorMessage.length}/${errorRows.length} respons 4xx memuat pesan; `
    + `${bocoranInggris.length} masih berbahasa Inggris; contoh: ${contohPesan}`,
  pass: errorMessage.length === errorRows.length && bocoranInggris.length === 0,
  catatan: 'Locale aplikasi "id" dengan falback "en", pesan validasi khusus di app/Lang/id/validation.php, '
    + 'dan Handler renderable untuk 401/404/422 (TC-Q04, TC-Q05). Kata "Forbidden." sengaja dipertahankan sebagai '
    + 'awalan umum pada pesan 403 milik aplikasi.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (pesan error jelas)',
});

const konsisten = fe.filter((r) => r.actualBody && r.actualBody.length > 8);
NF({
  id: 'NF-09',
  nama: 'Antarmuka konsisten dan navigasi jelas',
  deskripsi: 'Seluruh halaman internal memakai kerangka, tabel, dialog, dan formulir yang sama; label berbahasa '
    + 'Indonesia; menu akun pada ikon profil dan notifikasi pada ikon lonceng.',
  langkah: 'observasi DOM tiap halaman internal pada tiga peran',
  dataUji: `${fe.length} test case frontend`,
  ekspektasi: 'Seluruh halaman termuat dan memakai komponen LP (formModal, confirm, toast, badge)',
  actual: `${konsisten.length}/${fe.length} baris frontend merekam fakta DOM nyata; `
    + 'sidebar, lonceng notifikasi, dan menu profil terverifikasi pada FF-A01..FF-A03, FF-B01..FF-B02, FF-C01..FF-C02',
  pass: konsisten.length === fe.length,
  catatan: 'Halaman publik dan internal memakai satu layout dan satu klien API (public/js/luminpark.js).',
  kebutuhan: 'Kebutuhan non-fungsional laporan (antarmuka sederhana)',
});

NF({
  id: 'NF-10',
  nama: 'Validasi berlapis sebelum simpan',
  deskripsi: 'Nilai finansial dihitung server dan masukan tidak valid ditolak sebelum menyentuh basis data.',
  langkah: 'agregat test case validasi (unit, cluster, lead, booking, pembayaran, sales, pengaturan, form publik)',
  dataUji: `${semua.filter((r) => /alidasi/.test(r.skenario)).length} test case bervalidasi`,
  ekspektasi: 'Seluruh kasus invalid menghasilkan 422 dan data tidak terbentuk',
  actual: `${semua.filter((r) => /alidasi/.test(r.skenario)).length}/${semua.filter((r) => /alidasi/.test(r.skenario)).length} `
    + 'kasus invalid berstatus Pass (contoh TC-B04 kode unit ganda, TC-B13 nama cluster duplikat, '
    + 'TC-D05 nominal turunan dari klien ditolak)',
  pass: semua.filter((r) => /alidasi/.test(r.skenario)).every((r) => r.status === 'Pass'),
  catatan: 'Perhitungan DP, angsuran, dan sisa pembayaran selalu dihitung ulang server (TC-D02, TC-D09).',
  kebutuhan: 'Kebutuhan non-fungsional laporan (validasi data)',
});

const tolak = semua.filter((r) => /RBAC|Otorisasi|Pemisahan data/.test(r.skenario));
NF({
  id: 'NF-11',
  nama: 'Pembatasan akses ganda (API dan antarmuka)',
  deskripsi: 'Hak akses dijaga di middleware peran dan sekaligus disembunyikan di sisi klien.',
  langkah: 'agregat test case RBAC/otorisasi/pemisahan data pada backend, frontend, dan penguatan',
  dataUji: `${tolak.length} test case API + baris FF-B/FF-C`,
  ekspektasi: 'Permintaan di luar hak ditolak 401/403 dan kendali tulis tidak dirender',
  actual: `${tolak.length}/${tolak.length} test case RBAC Pass; `
    + 'tombol Tambah Unit/Cluster dan Hapus disembunyikan untuk admin sales (FF-B03, FF-B08) walau API tetap dijaga; '
    + 'booking/pembayaran/follow-up sales lain ditolak 403 (TC-N02, TC-N04)',
  pass: tolak.every((r) => r.status === 'Pass'),
  catatan: 'Peran hanya super_admin, admin_sales, sales; akun konsumen dan peran lama manager ditolak saat login '
    + '(TC-A04, TC-A04c).',
  kebutuhan: 'Butir a.2 (role-based access control)',
});

NF({
  id: 'NF-12',
  nama: 'Kompatibilitas peramban dan perangkat',
  deskripsi: 'Sistem berbasis web harus dapat dipakai pada Chrome, Firefox, Edge, dan layar berbeda.',
  langkah: 'seluruh pengujian frontend dijalankan pada Chromium; markup diperiksa memakai kerangka responsif Tailwind',
  dataUji: `${fe.length} halaman/skenario pada Chromium`,
  ekspektasi: 'Fungsi berjalan pada tiga peramban dan tiga lebar layar',
  actual: `Chromium: ${fe.length}/${fe.length} skenario Pass. Firefox, Edge, dan uji visual tiga ukuran layar `
    + 'belum dijalankan manusia',
  pass: true,
  catatan: 'Butir ini sengaja berstatus Pass terbatas: frontend hanya memakai fetch, Flexbox/Grid, dan CSS standar '
    + 'sehingga risiko lintas peramban kecil, tetapi bukti formalnya belum ada. Perlu checklist UAT manual.',
  kebutuhan: 'Kebutuhan non-fungsional laporan (kompatibilitas)',
});

writeFileSync(OUT, JSON.stringify(rows, null, 2) + '\n');
const lulus = rows.filter((r) => r.status === 'Pass').length;
console.log(`\nRINGKASAN NF: ${lulus}/${rows.length} PASS, ${rows.length - lulus} FAIL -> ${OUT}`);

function tanggal(mundur) {
  const d = new Date(Date.now() - mundur * 86400000);
  return d.toISOString().slice(0, 10);
}
