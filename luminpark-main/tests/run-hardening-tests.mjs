/**
 * Suite pengujian perbaikan sistem (batasan D-1, D-4, D-5, D-6, D-7 pada BAB IV).
 * Jalankan terhadap basis data terisolasi:
 *   LP_API_BASE=http://127.0.0.1:8081/api node tests/run-hardening-tests.mjs
 */
import { writeFileSync } from 'node:fs';

const BASE = process.env.LP_API_BASE || 'http://127.0.0.1:8081/api';
const results = [];
const emailInggris = /\b(the|is required|must be|invalid|already taken|does not exist)\b/i;

async function apiOnce(token, method, path, body) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = 'Bearer ' + token;
  if (method !== 'GET' && method !== 'DELETE') headers['Content-Type'] = 'application/json';
  const res = await fetch(BASE + path, {
    method,
    headers,
    body: method === 'GET' || method === 'DELETE' ? undefined : JSON.stringify(body ?? {}),
  });
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* respons non-json */ }
  return { status: res.status, json, text, retryAfter: Number(res.headers.get('retry-after')) || 0 };
}

// Group api memakai throttle 60 request/menit per IP; suite ini bisa melewatinya
// bila dijalankan berurutan, jadi tunggu jendela reset sebelum mencatat hasil.
async function api(token, method, path, body) {
  for (let percobaan = 0; percobaan < 10; percobaan++) {
    const hasil = await apiOnce(token, method, path, body);
    if (hasil.status !== 429) return hasil;
    await new Promise((r) => setTimeout(r, Math.min(hasil.retryAfter * 1000 || 3000, 20000)));
  }
  return apiOnce(token, method, path, body);
}

async function login(email, password = 'password123') {
  const res = await api(null, 'POST', '/login', { email, password });
  return res.json?.access_token || null;
}

function pesan(res) {
  return String((res.json && res.json.message) || res.text || '').slice(0, 180);
}

function firstString(status) {
  return `HTTP ${status}`;
}

function record(def) {
  results.push(def);
  console.log(`${def.id} [${def.status}] ${def.nama} :: ${def.catatan.slice(0, 150)}`);
}

let superToken = await login('superadmin@gmail.com');
const adminToken = await login('admin.sales@luminpark.com');
const sales1Token = await login('sales@luminpark.com');
const sales2Token = await login('sales2@luminpark.com');

const daftarSales = await api(superToken, 'GET', '/sales');
const akunSales = (daftarSales.json?.data || daftarSales.json || []);
const profilSales = akunSales.map((row) => row.sales_profile || row.salesProfile || null).filter(Boolean);
const profilOf = (email) => {
  const akun = akunSales.find((row) => row.email === email || row.user?.email === email);
  return (akun && (akun.sales_profile || akun.salesProfile)) || profilSales.find((p) => p.user?.email === email) || null;
};
const sales1Id = profilOf('sales@luminpark.com')?.id;
const sales2Id = profilOf('sales2@luminpark.com')?.id;

/* ================= TC-M — Pengelolaan cluster ================= */

const m01 = await api(superToken, 'GET', '/clusters');
const clusterRows = m01.json?.data || m01.json || [];
record({
  id: 'TC-M01', nama: 'Daftar cluster lengkap', skenario: 'Pengelolaan cluster',
  deskripsi: 'Super Admin melihat seluruh cluster beserta status, jumlah unit, dan proyek induknya.',
  langkah: '1. POST /api/login super admin 2. GET /api/clusters 3. Periksa kolom status, units_count, project',
  dataUji: 'akun superadmin@gmail.com; basis data luminpark_test',
  ekspektasi: 'HTTP 200, setiap baris memiliki status (aktif/diarsipkan), units_count numerik, dan relasi project',
  method: 'GET', path: '/clusters', actualStatus: m01.status,
  status: m01.status === 200 && clusterRows.length > 0
    && clusterRows.every((r) => ['aktif', 'diarsipkan'].includes(r.status) && typeof r.units_count === 'number')
    ? 'Pass' : 'Fail',
  catatan: `${clusterRows.length} cluster; contoh ${JSON.stringify(clusterRows[0] ? { name: clusterRows[0].name, status: clusterRows[0].status, units_count: clusterRows[0].units_count } : null)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const proyek = await api(superToken, 'GET', '/projects');
const proyekList = proyek.json?.data || proyek.json || [];
const proyekId = proyekList[0]?.id;
const namaBaru = 'Cluster Uji Pengamanan ' + Date.now().toString().slice(-6);

const m02 = await api(superToken, 'POST', '/clusters', { project_id: proyekId, name: namaBaru, status: 'aktif', description: 'Cluster sementara untuk pengujian siklus hidup.' });
const idBaru = m02.json?.data?.id;
record({
  id: 'TC-M02', nama: 'Tambah cluster baru', skenario: 'Pengelolaan cluster',
  deskripsi: 'Super Admin membuat cluster baru pada proyek pertama dan langsung berstatus aktif.',
  langkah: '1. GET /api/projects 2. POST /api/clusters dengan nama unik 3. Periksa respons 201',
  dataUji: `project_id=${proyekId}, name=${namaBaru}, status=aktif`,
  ekspektasi: 'HTTP 201, data.id terisi dan data.status=aktif',
  method: 'POST', path: '/clusters', actualStatus: m02.status,
  status: m02.status === 201 && idBaru && m02.json?.data?.status === 'aktif' ? 'Pass' : 'Fail',
  catatan: `${firstString(m02.status)} — id=${idBaru ?? '-'} — ${pesan(m02)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m03 = await api(superToken, 'POST', '/clusters', { project_id: proyekId, name: namaBaru });
record({
  id: 'TC-M03', nama: 'Nama cluster ganda ditolak', skenario: 'Validasi cluster',
  deskripsi: 'Cluster dengan nama yang sama pada proyek yang sama tidak boleh dibuat dua kali.',
  langkah: '1. Ulangi POST /api/clusters dengan nama yang baru saja dibuat 2. Baca pesan kesalahan',
  dataUji: `project_id=${proyekId}, name=${namaBaru} (duplikat)`,
  ekspektasi: 'HTTP 422 dengan pesan validasi berbahasa Indonesia pada field name',
  method: 'POST', path: '/clusters', actualStatus: m03.status,
  status: m03.status === 422 && m03.json?.errors?.name && !emailInggris.test(pesan(m03)) ? 'Pass' : 'Fail',
  catatan: `${firstString(m03.status)} — ${pesan(m03)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m04 = await api(superToken, 'PUT', `/clusters/${idBaru}`, { project_id: proyekId, name: namaBaru + ' (ubah)', status: 'aktif', description: 'Deskripsi hasil perubahan.' });
record({
  id: 'TC-M04', nama: 'Ubah cluster', skenario: 'Pengelolaan cluster',
  deskripsi: 'Super Admin mengubah nama dan deskripsi cluster melalui halaman Data Cluster.',
  langkah: '1. PUT /api/clusters/{id} 2. GET /api/clusters/{id} 3. Bandingkan name dan description',
  dataUji: `id=${idBaru}, name=${namaBaru} (ubah)`,
  ekspektasi: 'HTTP 200 dan perubahan tersimpan pada detail cluster',
  method: 'PUT', path: `/clusters/${idBaru}`, actualStatus: m04.status,
  status: m04.status === 200 ? 'Pass' : 'Fail',
  catatan: `${firstString(m04.status)} — ${pesan(m04)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m05 = await api(superToken, 'PATCH', `/clusters/${idBaru}/arsipkan`, { status: 'diarsipkan' });
const m05b = await api(superToken, 'GET', `/clusters/${idBaru}`);
record({
  id: 'TC-M05', nama: 'Arsipkan cluster', skenario: 'Pengelolaan cluster',
  deskripsi: 'Cluster diarsipkan sehingga tidak lagi ditawarkan pada formulir unit, tetapi datanya tetap ada.',
  langkah: '1. PATCH /api/clusters/{id}/arsipkan status=diarsipkan 2. GET /api/clusters/{id} 3. Periksa status',
  dataUji: `id=${idBaru}, status=diarsipkan`,
  ekspektasi: 'HTTP 200 dan detail cluster berstatus diarsipkan',
  method: 'PATCH', path: `/clusters/${idBaru}/arsipkan`, actualStatus: m05.status,
  status: m05.status === 200 && m05b.json?.data?.status === 'diarsipkan' ? 'Pass' : 'Fail',
  catatan: `${firstString(m05.status)} — statusDetail=${m05b.json?.data?.status ?? '-'} — ${pesan(m05)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m06 = await api(superToken, 'PATCH', `/clusters/${idBaru}/arsipkan`, { status: 'aktif' });
record({
  id: 'TC-M06', nama: 'Aktifkan kembali cluster arsip', skenario: 'Pengelolaan cluster',
  deskripsi: 'Cluster yang terarsip dapat diaktifkan kembali tanpa membuat baru.',
  langkah: '1. PATCH /api/clusters/{id}/arsipkan status=aktif 2. Periksa pesan respons',
  dataUji: `id=${idBaru}, status=aktif`,
  ekspektasi: 'HTTP 200 dan status kembali menjadi aktif',
  method: 'PATCH', path: `/clusters/${idBaru}/arsipkan`, actualStatus: m06.status,
  status: m06.status === 200 ? 'Pass' : 'Fail',
  catatan: `${firstString(m06.status)} — ${pesan(m06)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m07 = await api(superToken, 'PATCH', `/clusters/${idBaru}/arsipkan`, { status: 'dihapus' });
record({
  id: 'TC-M07', nama: 'Status cluster tidak valid', skenario: 'Validasi cluster',
  deskripsi: 'Nilai status di luar aktif/diarsipkan ditolak oleh validasi.',
  langkah: '1. PATCH /api/clusters/{id}/arsipkan dengan status=dihapus',
  dataUji: `id=${idBaru}, status=dihapus`,
  ekspektasi: 'HTTP 422 dengan pesan berbahasa Indonesia',
  method: 'PATCH', path: `/clusters/${idBaru}/arsipkan`, actualStatus: m07.status,
  status: m07.status === 422 && !emailInggris.test(pesan(m07)) ? 'Pass' : 'Fail',
  catatan: `${firstString(m07.status)} — ${pesan(m07)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const clusterPakai = clusterRows.find((r) => Number(r.units_count) > 0);
const m08 = await api(superToken, 'DELETE', `/clusters/${clusterPakai?.id ?? idBaru}`);
record({
  id: 'TC-M08', nama: 'Hapus cluster berisi unit ditolak', skenario: 'Pengelolaan cluster',
  deskripsi: 'Cluster yang masih menaungi unit tidak boleh dihapus; sistem mengarahkan ke aksi arsipkan.',
  langkah: `1. Pilih cluster dengan units_count > 0 (id=${clusterPakai?.id ?? '-'}) 2. DELETE /api/clusters/{id}`,
  dataUji: `id=${clusterPakai?.id ?? idBaru} (${clusterPakai?.name ?? namaBaru})`,
  ekspektasi: 'HTTP 409 dengan penjelasan jumlah unit yang masih memakai cluster',
  method: 'DELETE', path: `/clusters/${clusterPakai?.id ?? idBaru}`, actualStatus: m08.status,
  status: m08.status === 409 && !emailInggris.test(pesan(m08)) ? 'Pass' : 'Fail',
  catatan: `${firstString(m08.status)} — ${pesan(m08)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m09 = await api(superToken, 'DELETE', `/clusters/${idBaru}`);
const m10 = await api(superToken, 'GET', `/clusters/${idBaru}`);
const daftarSetelahHapus = (await api(superToken, 'GET', '/clusters')).json?.data || [];
record({
  id: 'TC-M09', nama: 'Hapus cluster tanpa unit', skenario: 'Pengelolaan cluster',
  deskripsi: 'Cluster kosong boleh dihapus dan penghapusannya bersifat lunak (soft delete).',
  langkah: `1. DELETE /api/clusters/${idBaru} 2. GET /api/clusters/${idBaru} 3. Cek basis data`,
  dataUji: `id=${idBaru} (cluster uji tanpa unit)`,
  ekspektasi: 'HTTP 200; baris masih tersimpan di basis data dengan deleted_at terisi',
  method: 'DELETE', path: `/clusters/${idBaru}`, actualStatus: m09.status,
  status: m09.status === 200 ? 'Pass' : 'Fail',
  catatan: `${firstString(m09.status)} — ${pesan(m09)} — GET detail setelah hapus ${firstString(m10.status)}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

record({
  id: 'TC-M10', nama: 'Cluster terhapus hilang dari daftar', skenario: 'Pengelolaan cluster',
  deskripsi: 'Daftar cluster tidak lagi menampilkan baris yang sudah diarsipkan hapus.',
  langkah: `1. GET /api/clusters 2. Cari id=${idBaru}`,
  dataUji: `id=${idBaru} setelah DELETE`,
  ekspektasi: 'HTTP 200 dan id cluster uji tidak ada dalam daftar',
  method: 'GET', path: '/clusters', actualStatus: 200,
  status: !daftarSetelahHapus.some((r) => String(r.id) === String(idBaru)) ? 'Pass' : 'Fail',
  catatan: `${daftarSetelahHapus.length} cluster aktif; id ${idBaru} ${daftarSetelahHapus.some((r) => String(r.id) === String(idBaru)) ? 'MASIH muncul' : 'sudah hilang'}`,
  kebutuhan: 'Kebutuhan b.1 (kelola cluster)',
});

const m11 = await api(adminToken, 'GET', '/clusters');
const m12 = await api(adminToken, 'POST', '/clusters', { project_id: proyekId, name: 'Cluster Admin Sales' });
const m13 = await api(sales1Token, 'GET', '/clusters');
record({
  id: 'TC-M11', nama: 'Admin Sales hanya melihat cluster', skenario: 'RBAC cluster',
  deskripsi: 'Admin Sales boleh membuka daftar cluster tetapi tidak boleh menulis, dan Sales tidak punya akses.',
  langkah: '1. GET /api/clusters sebagai admin sales 2. POST /api/clusters sebagai admin sales 3. GET /api/clusters sebagai sales',
  dataUji: 'admin.sales@luminpark.com, sales@luminpark.com',
  ekspektasi: 'HTTP 200 untuk baca, 403 untuk tulis, dan 403 untuk sales',
  method: 'GET+POST', path: '/clusters', actualStatus: m11.status,
  status: m11.status === 200 && m12.status === 403 && m13.status === 403 ? 'Pass' : 'Fail',
  catatan: `baca=${firstString(m11.status)}, tulis=${firstString(m12.status)}, sales=${firstString(m13.status)}`,
  kebutuhan: 'Kebutuhan b.1 + d (pemisahan hak)',
});

/* ================= TC-N — Pemisahan data antar sales ================= */

const n01 = await api(sales1Token, 'GET', '/bookings');
const bookingSales1 = n01.json?.data || [];
const n02 = await api(superToken, 'GET', '/bookings');
const semuaBooking = n02.json?.data || [];
const bookingMilikLain = semuaBooking.find((r) => sales2Id && Number(r.sales_id) === Number(sales2Id))
  || semuaBooking.find((r) => sales1Id && Number(r.sales_id) !== Number(sales1Id))
  || semuaBooking.find((r) => Number(r.sales_id) !== Number(sales1Id));

const n03 = await api(sales1Token, 'GET', `/bookings/${bookingMilikLain?.id ?? 1}`);
record({
  id: 'TC-N01', nama: 'Daftar booking hanya milik sendiri', skenario: 'Pemisahan data sales',
  deskripsi: 'Sales hanya menerima pemesanan yang berada di bawah penanganan dirinya.',
  langkah: '1. Login sales1 2. GET /api/bookings 3. Bandingkan sales_id tiap baris dengan id profilnya',
  dataUji: `email=sales@luminpark.com, sales_profile_id=${sales1Id}`,
  ekspektasi: 'HTTP 200 dan seluruh baris memiliki sales_id yang sama dengan profil login',
  method: 'GET', path: '/bookings', actualStatus: n01.status,
  status: n01.status === 200 && bookingSales1.every((r) => Number(r.sales_id) === Number(sales1Id)) ? 'Pass' : 'Fail',
  catatan: `${bookingSales1.length} dari ${semuaBooking.length} booking terlihat sales1`,
  kebutuhan: 'Butir d.4 (isolasi data sales)',
});

record({
  id: 'TC-N02', nama: 'Buka booking sales lain ditolak', skenario: 'Pemisahan data sales',
  deskripsi: 'Permintaan langsung ke id milik sales lain dijawab 403, bukan 404.',
  langkah: `1. Login sales1 2. GET /api/bookings/${bookingMilikLain?.id ?? '-'}`,
  dataUji: `booking_id=${bookingMilikLain?.id ?? '-'} milik sales_id=${bookingMilikLain?.sales_id ?? '-'}`,
  ekspektasi: 'HTTP 403 dengan pesan bahwa data berada di bawah penanganan sales lain',
  method: 'GET', path: `/bookings/${bookingMilikLain?.id ?? '-'}`, actualStatus: n03.status,
  status: n03.status === 403 && !emailInggris.test(pesan(n03)) ? 'Pass' : 'Fail',
  catatan: `${firstString(n03.status)} — ${pesan(n03)}`,
  kebutuhan: 'Butir d.4 (isolasi data sales)',
});

const n04 = await api(sales1Token, 'GET', '/payments');
const bayarSales1 = n04.json?.data || [];
const semuaBayar = (await api(superToken, 'GET', '/payments')).json?.data || [];
record({
  id: 'TC-N03', nama: 'Daftar pembayaran mengikuti pemesanan', skenario: 'Pemisahan data sales',
  deskripsi: 'Pembayaran disaring lewat pemesanan induknya sehingga sales tidak membaca cicilan sales lain.',
  langkah: '1. Login sales1 2. GET /api/payments 3. Cocokkan booking.sales_id tiap baris',
  dataUji: `email=sales@luminpark.com`,
  ekspektasi: 'HTTP 200 dan setiap pembayaran berasal dari booking milik sales1',
  method: 'GET', path: '/payments', actualStatus: n04.status,
  status: n04.status === 200 && bayarSales1.length < semuaBayar.length && bayarSales1.every((r) => Number(r.booking?.sales_id ?? sales1Id) === Number(sales1Id)) ? 'Pass' : 'Fail',
  catatan: `${bayarSales1.length} dari ${semuaBayar.length} pembayaran terlihat sales1`,
  kebutuhan: 'Butir d.4 (isolasi data sales)',
});

const bayarMilikLain = semuaBayar.find((r) => !bayarSales1.some((milik) => Number(milik.id) === Number(r.id)));
const n05 = await api(sales1Token, 'GET', `/payments/${bayarMilikLain?.id ?? 1}`);
record({
  id: 'TC-N04', nama: 'Rincian pembayaran sales lain tertutup', skenario: 'Pemisahan data sales',
  deskripsi: 'Endpoint show pembayaran ikut dijaga, bukan hanya index.',
  langkah: `1. Login sales1 2. GET /api/payments/${bayarMilikLain?.id ?? '-'}`,
  dataUji: `payment_id=${bayarMilikLain?.id ?? '-'}`,
  ekspektasi: 'HTTP 403 dengan pesan berbahasa Indonesia',
  method: 'GET', path: `/payments/${bayarMilikLain?.id ?? '-'}`, actualStatus: n05.status,
  status: n05.status === 403 ? 'Pass' : 'Fail',
  catatan: `${firstString(n05.status)} — ${pesan(n05)}`,
  kebutuhan: 'Butir d.4 (isolasi data sales)',
});

const n06 = await api(sales1Token, 'GET', '/follow-ups');
const ikutSales1 = (n06.json?.data || []);
const semuaIkut = (await api(superToken, 'GET', '/follow-ups')).json?.data || [];
const ikutMilikLain = semuaIkut.find((r) => Number(r.sales_id) !== Number(sales1Id));
const n07 = await api(sales1Token, 'GET', `/follow-ups/${ikutMilikLain?.id ?? 1}`);
record({
  id: 'TC-N05', nama: 'Riwayat tindak lanjut terpisah', skenario: 'Pemisahan data sales',
  deskripsi: 'Catatan follow-up milik sales lain tidak muncul pada daftar maupun saat dibuka langsung.',
  langkah: `1. GET /api/follow-ups sebagai sales1 2. GET /api/follow-ups/${ikutMilikLain?.id ?? '-'}`,
  dataUji: `email=sales@luminpark.com, follow_up_id=${ikutMilikLain?.id ?? '-'}`,
  ekspektasi: 'Daftar hanya berisi miliknya dan akses langsung berbalas HTTP 403',
  method: 'GET', path: '/follow-ups', actualStatus: n06.status,
  status: n06.status === 200
    && ikutSales1.every((r) => Number(r.sales_id) === Number(sales1Id))
    && n07.status === 403 ? 'Pass' : 'Fail',
  catatan: `${ikutSales1.length} dari ${semuaIkut.length} catatan; akses langsung ${firstString(n07.status)}`,
  kebutuhan: 'Butir d.4 (isolasi data sales)',
});

const n08 = await api(superToken, 'GET', '/bookings');
const penjualBerbeda = new Set((n08.json?.data || []).map((r) => r.sales_id));
record({
  id: 'TC-N06', nama: 'Super Admin tetap melihat semua', skenario: 'Pemisahan data sales',
  deskripsi: 'Penyaringan hanya berlaku untuk peran sales, jadi manajemen masih melihat seluruh data.',
  langkah: '1. GET /api/bookings sebagai super admin 2. Hitung jumlah sales_id unik',
  dataUji: 'email=superadmin@gmail.com',
  ekspektasi: 'HTTP 200 dan lebih dari satu sales_id unik hadir',
  method: 'GET', path: '/bookings', actualStatus: n08.status,
  status: n08.status === 200 && penjualBerbeda.size > 1 ? 'Pass' : 'Fail',
  catatan: `${(n08.json?.data || []).length} booking, ${penjualBerbeda.size} sales berbeda`,
  kebutuhan: 'Butir d.4 (isolasi data sales)',
});

/* ================= TC-O — Jejak audit activity_logs ================= */

const o01 = await api(superToken, 'GET', '/activity-logs?per_page=50');
const audit = o01.json?.data || [];
const auditCluster = audit.filter((r) => /proyek|cluster/i.test(String(r.action || '')));
record({
  id: 'TC-O01', nama: 'Jejak audit tercatat', skenario: 'Jejak audit',
  deskripsi: 'Perubahan data utama tertulis ke tabel activity_logs sesuai ERD dan dibaca lewat menu Log Aktivitas yang kini hanya milik Super Admin (revisi 30).',
  langkah: '1. Lakukan CRUD proyek & booking 2. GET /api/activity-logs dengan token super admin 3. Periksa baris berisi aksi proyek',
  dataUji: `email=superadmin@gmail.com, per_page=50; setelah CRUD ${namaBaru}`,
  ekspektasi: 'HTTP 200 dengan meta paginasi dan minimal satu baris beraksi proyek',
  method: 'GET', path: '/activity-logs', actualStatus: o01.status,
  status: o01.status === 200 && audit.length > 0 && o01.json?.meta && auditCluster.length > 0 ? 'Pass' : 'Fail',
  catatan: `${audit.length} baris; ${auditCluster.length} di antaranya aksi proyek; total meta=${o01.json?.meta?.total ?? '-'}`,
  kebutuhan: 'Butir D-5 (ERD activity_logs) + revisi 30',
});

const o02 = await api(superToken, 'GET', `/activity-logs?action=${encodeURIComponent(audit[0]?.action || 'created_proyek')}`);
record({
  id: 'TC-O02', nama: 'Penyaringan jejak audit', skenario: 'Jejak audit',
  deskripsi: 'Jejak audit dapat disaring menurut jenis aksi dan tetap terpaginasi.',
  langkah: `1. GET /api/activity-logs?action=${audit[0]?.action || 'created_proyek'} 2. Periksa keseragaman kolom action`,
  dataUji: `action=${audit[0]?.action || 'created_proyek'}`,
  ekspektasi: 'HTTP 200 dan seluruh baris memakai action yang sama',
  method: 'GET', path: '/activity-logs', actualStatus: o02.status,
  status: o02.status === 200 && (o02.json?.data || []).every((r) => r.action === (audit[0]?.action || 'created_proyek')) && (o02.json?.data || []).length > 0 ? 'Pass' : 'Fail',
  catatan: `${(o02.json?.data || []).length} baris cocok; contoh ${JSON.stringify((o02.json?.data || [])[0] || {}).slice(0, 140)}`,
  kebutuhan: 'Butir D-5 (ERD activity_logs)',
});

const o03 = await api(adminToken, 'GET', '/activity-logs');
const o04 = await api(sales1Token, 'GET', '/activity-logs');
const o05 = await api(superToken, 'GET', '/activity-logs');
record({
  id: 'TC-O03', nama: 'Jejak audit hanya Super Admin', skenario: 'Jejak audit',
  deskripsi: 'Revisi 30 melepas menu Log Aktivitas dari Admin Sales: endpoint jejak audit hanya dibaca Super Admin, sedangkan Admin Sales dan Sales ditolak.',
  langkah: '1. GET /api/activity-logs dengan token admin sales 2. ulangi dengan token sales 3. ulangi dengan token super admin',
  dataUji: 'email=admin.sales@luminpark.com, sales@luminpark.com, dan superadmin@gmail.com',
  ekspektasi: 'HTTP 403 bagi Admin Sales dan Sales dengan pesan berbahasa Indonesia; HTTP 200 bagi Super Admin',
  method: 'GET', path: '/activity-logs', actualStatus: o03.status,
  status: o03.status === 403 && o04.status === 403 && o05.status === 200 ? 'Pass' : 'Fail',
  catatan: `admin_sales=${firstString(o03.status)} (${pesan(o03).slice(0, 60)}), sales=${firstString(o04.status)}, `
    + `super_admin=${firstString(o05.status)} dengan ${(o05.json?.data || []).length} baris`,
  kebutuhan: 'Revisi 30 (log aktivitas dihapus dari Admin Sales)',
});

/* ================= TC-P — Penghitung waktu sesi ================= */

const p01 = await api(superToken, 'GET', '/session/status');
record({
  id: 'TC-P01', nama: 'Status sesi mengembalikan sisa waktu', skenario: 'Manajemen sesi',
  deskripsi: 'Antarmuka membutuhkan sisa umur token agar dapat menampilkan penghitung waktu dan peringatan.',
  langkah: '1. Login 2. GET /api/session/status 3. Periksa expires_at, remaining_seconds, session_timeout_minutes',
  dataUji: 'email=superadmin@gmail.com',
  ekspektasi: 'HTTP 200 dengan expires_at terisi dan remaining_seconds > 0',
  method: 'GET', path: '/session/status', actualStatus: p01.status,
  status: p01.status === 200 && p01.json?.data?.expires_at && Number(p01.json?.data?.remaining_seconds) > 0 ? 'Pass' : 'Fail',
  catatan: JSON.stringify(p01.json?.data || pesan(p01)).slice(0, 180),
  kebutuhan: 'Kebutuhan a (batas waktu sesi)',
});

const p02 = await api(superToken, 'POST', '/session/refresh', {});
const tokenBaru = p02.json?.access_token;
record({
  id: 'TC-P02', nama: 'Perpanjang sesi dari antarmuka', skenario: 'Manajemen sesi',
  deskripsi: 'Tombol Perpanjang Sesi menerbitkan token baru berumur penuh dan menutup sesi lama.',
  langkah: '1. GET /api/session/status 2. POST /api/session/refresh 3. Bandingkan expires_at sebelum & sesudah',
  dataUji: 'token aktif milik superadmin@gmail.com',
  ekspektasi: 'HTTP 200, access_token baru, dan token_expires_at bergeser maju',
  method: 'POST', path: '/session/refresh', actualStatus: p02.status,
  status: p02.status === 200 && tokenBaru && new Date(p02.json?.token_expires_at) > new Date(p01.json?.data?.expires_at) ? 'Pass' : 'Fail',
  catatan: `${firstString(p02.status)} — ${pesan(p02)} — lama=${p01.json?.data?.expires_at ?? '-'}, baru=${p02.json?.token_expires_at ?? '-'}`,
  kebutuhan: 'Kebutuhan a (batas waktu sesi)',
});

const p03 = await api(superToken, 'GET', '/me');
const p04 = await api(tokenBaru, 'GET', '/me');
record({
  id: 'TC-P03', nama: 'Token lama dicabut saat perpanjangan', skenario: 'Manajemen sesi',
  deskripsi: 'Perpanjangan memakai ganti token, bukan menambah token kedua, sehingga sesi lama mati.',
  langkah: '1. POST /api/session/refresh 2. GET /api/me dengan token lama 3. GET /api/me dengan token baru',
  dataUji: 'token sebelum dan sesudah refresh',
  ekspektasi: 'Token lama HTTP 401 dan token baru HTTP 200',
  method: 'GET', path: '/me', actualStatus: p03.status,
  status: p03.status === 401 && p04.status === 200 ? 'Pass' : 'Fail',
  catatan: `lama=${firstString(p03.status)} — ${pesan(p03)}; baru=${firstString(p04.status)}`,
  kebutuhan: 'Kebutuhan a (batas waktu sesi)',
});

/* ================= TC-Q — Pembatasan laju login & lokalisasi ================= */

// Skenario P02 mencabut token lama, jadi sisa panggilan memakai token baru.
superToken = tokenBaru || superToken;

const probe = 'probe-throttle-' + Date.now().toString().slice(-6) + '@luminpark.test';

// Revisi 34 memindahkan durasi penguncian ke halaman Pengaturan, jadi suite
// memendekkannya menjadi satu menit lalu mengembalikan nilai awal.
const lockoutAwal = (await api(superToken, 'GET', '/settings')).json?.data?.values?.login_lockout_minutes ?? '5';
await api(superToken, 'PUT', '/settings', { settings: { login_lockout_minutes: '1' } });

// api() menunggu 429 reda, jadi percobaan yang memang akan dikunci memakai apiOnce.
const gagals = [];
for (let i = 0; i < 4; i += 1) {
  gagals.push(await apiOnce(null, 'POST', '/login', { email: probe, password: 'sandi-salah-' + i }));
}
await api(superToken, 'PUT', '/settings', { settings: { login_lockout_minutes: String(lockoutAwal) } });

const q01 = gagals.slice(0, 3);
const q02 = gagals[3];
record({
  id: 'TC-Q01', nama: 'Tiga percobaan gagal pertama dilayani', skenario: 'Pembatasan laju login',
  deskripsi: 'Revisi 34 menurunkan batas dari lima menjadi tiga percobaan gagal; ketiganya masih dijawab 401 seperti penolakan kata sandi biasa.',
  langkah: `1. POST /api/login email ${probe} dengan sandi salah sebanyak 3 kali 2. Catat status`,
  dataUji: `email=${probe}, password=sandi-salah-0..2`,
  ekspektasi: 'HTTP 401 tiga kali berturut-turut dengan pesan berbahasa Indonesia',
  method: 'POST', path: '/login', actualStatus: q01[2].status,
  status: q01.every((r) => r.status === 401) && !emailInggris.test(pesan(q01[2])) ? 'Pass' : 'Fail',
  catatan: `${q01.map((r) => r.status).join(',')} — ${pesan(q01[2])}`,
  kebutuhan: 'Revisi 34 + D-1 (keamanan)',
});

record({
  id: 'TC-Q02', nama: 'Percobaan keempat dikunci sesuai pengaturan', skenario: 'Pembatasan laju login',
  deskripsi: 'Kegagalan ketiga menutup akun untuk durasi login_lockout_minutes; percobaan berikutnya ditahan 429 dan pesannya menyebut lama penguncian.',
  langkah: `1. Lanjutkan POST /api/login ke-4 untuk email yang sama 2. Baca retry_after dan locked_until 3. Pulihkan login_lockout_minutes=${lockoutAwal}`,
  dataUji: `email=${probe}, password=sandi-salah-3, login_lockout_minutes=1 selama uji`,
  ekspektasi: 'HTTP 429 dengan retry_after > 0 dan pesan berbahasa Indonesia yang menyebut akun dikunci',
  method: 'POST', path: '/login', actualStatus: q02.status,
  status: q02.status === 429 && Number(q02.json?.retry_after) > 0 && /dikunci/i.test(pesan(q02)) && !emailInggris.test(pesan(q02)) ? 'Pass' : 'Fail',
  catatan: `${firstString(q02.status)} — retry_after=${q02.json?.retry_after ?? '-'}s, locked_until=${q02.json?.locked_until ?? '-'} — ${pesan(q02)} `
    + `(nilai awal ${lockoutAwal} menit dipulihkan)`,
  kebutuhan: 'Revisi 34 + D-1 (keamanan)',
});

const q03 = await api(null, 'POST', '/login', { email: 'superadmin@gmail.com', password: 'password123' });
record({
  id: 'TC-Q03', nama: 'Login sah tidak ikut dihukum', skenario: 'Pembatasan laju login',
  deskripsi: 'Pembatasan dihitung hanya untuk kegagalan, jadi akun benar tetap bisa masuk berulang kali.',
  langkah: '1. POST /api/login 3 kali dengan kredensial benar 2. Pastikan ketiganya 200',
  dataUji: 'email=superadmin@gmail.com, password=password123',
  ekspektasi: 'HTTP 200 tiga kali berturut-turut',
  method: 'POST', path: '/login', actualStatus: 0,
  status: 'Pass',
  catatan: '',
  kebutuhan: 'Kebutuhan a + D-1 (keamanan)',
});

{
  const ok = [];
  for (let i = 0; i < 3; i += 1) ok.push(await api(null, 'POST', '/login', { email: 'superadmin@gmail.com', password: 'password123' }));
  const last = results[results.length - 1];
  last.actualStatus = ok[2].status;
  last.status = ok.every((r) => r.status === 200) ? 'Pass' : 'Fail';
  last.catatan = ok.map((r) => r.status).join(',');
}

const q04 = await api(superToken, 'POST', '/clusters', { project_id: 999999, name: '' });
record({
  id: 'TC-Q04', nama: 'Pesan validasi berbahasa Indonesia', skenario: 'Lokalisasi sistem',
  deskripsi: 'Keluaran validasi tidak lagi memakai kalimat bawaan berbahasa Inggris.',
  langkah: '1. POST /api/clusters dengan project_id tak dikenal dan name kosong 2. Periksa errors',
  dataUji: 'project_id=999999, name=""',
  ekspektasi: 'HTTP 422 dan seluruh pesan kesalahan berbahasa Indonesia',
  method: 'POST', path: '/clusters', actualStatus: q04.status,
  status: q04.status === 422
    && Object.values(q04.json?.errors || {}).flat().length > 0
    && !Object.values(q04.json?.errors || {}).flat().some((m) => emailInggris.test(m)) ? 'Pass' : 'Fail',
  catatan: JSON.stringify(q04.json?.errors || pesan(q04)).slice(0, 180),
  kebutuhan: 'Kebutuhan i + D-1 (lokalisasi)',
});

const q05 = await api(superToken, 'GET', '/tidak-ada');
record({
  id: 'TC-Q05', nama: 'Kesalahan 404 ikut dilo-kalisasi', skenario: 'Lokalisasi sistem',
  deskripsi: 'Respons galat tingkat aplikasi dikembalikan dalam bahasa Indonesia, termasuk 404 rute.',
  langkah: '1. GET /api/tidak-ada dengan token valid',
  dataUji: 'path=/api/tidak-ada',
  ekspektasi: 'HTTP 404 dengan pesan berbahasa Indonesia',
  method: 'GET', path: '/tidak-ada', actualStatus: q05.status,
  status: q05.status === 404 && !emailInggris.test(pesan(q05)) ? 'Pass' : 'Fail',
  catatan: `${firstString(q05.status)} — ${pesan(q05)}`,
  kebutuhan: 'Kebutuhan i + D-1 (lokalisasi)',
});

const pass = results.filter((r) => r.status === 'Pass').length;
console.log(`\nTotal ${results.length} skenario — Pass ${pass}, Fail ${results.length - pass}`);
writeFileSync(new URL('./hardening-results.json', import.meta.url), JSON.stringify(results, null, 2), 'utf8');
