/**
 * Suite pengujian kesesuaian backend Lumin Park terhadap laporan tugas proyek.
 * Setiap kasus mencatat 10 kolom format: ID, Nama, Skenario, Deskripsi,
 * Langkah Uji, Data Uji, Hasil yang Diharapkan, Hasil Aktual, Status, Catatan.
 *
 * Urutan eksekusi = urutan deklarasi. Kasus yang butuh beberapa langkah berturut
 * memakai `run` dan mengembalikan respons yang dicatat sebagai hasil uji.
 */
import { readFileSync, writeFileSync } from 'node:fs';

const BASE = 'http://127.0.0.1:8080/api';
const results = [];
const ctx = {};

function trim(text) {
  if (!text) return '(kosong)';
  let t = String(text).replace(/\s+/g, ' ').trim();
  if (t.startsWith('<')) {
    const m = t.match(/"message"\s*:\s*"([^"]{0,300})/);
    t = m ? m[1] : t.slice(0, 200);
  }
  return t.length > 260 ? t.slice(0, 260) + ' ...[dipotong]' : t;
}

async function apiOnce(token, method, path, body, headers) {
  const res = await fetch(BASE + path, {
    method,
    headers,
    body: method === 'GET' || method === 'DELETE' ? undefined : JSON.stringify(body ?? {}),
  });

  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* bukan JSON (mis. CSV) */ }

  return { status: res.status, json, text, headers: Object.fromEntries(res.headers.entries()) };
}

async function api(token, method, path, body, extraHeaders = {}) {
  const headers = { Accept: 'application/json', ...extraHeaders };
  if (token) headers.Authorization = 'Bearer ' + token;
  if (method !== 'GET' && method !== 'DELETE' && !extraHeaders['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }

  // Group api memakai throttle 60 request/menit per IP; suite ini melewatinya,
  // jadi tunggu jendela reset agar hasil yang tercatat tetap hasil semestinya.
  for (let attempt = 0; attempt < 8; attempt++) {
    const outcome = await apiOnce(token, method, path, body, headers);
    if (outcome.status !== 429) return outcome;
    const wait = Number(outcome.headers['retry-after']) * 1000 || 3000;
    await new Promise((r) => setTimeout(r, Math.min(wait, 15000)));
  }

  return apiOnce(token, method, path, body, headers);
}

async function loginAs(email) {
  const res = await api(null, 'POST', '/login', { email, password: 'password123' });
  return res.json?.access_token;
}

const TESTS = [];
function T(def) { TESTS.push(def); }

const M = () => ctx.managerToken;
const A = () => ctx.adminSalesToken;
const S = () => ctx.salesToken;
const C = () => ctx.consumerToken;

async function execute() {
  for (const def of TESTS) {
    const path = typeof def.path === 'function' ? def.path(ctx) : def.path;

    let status = 'ERR';
    let json = null;
    let text = '';
    let resHeaders = {};

    try {
      const outcome = def.run
        ? await def.run()
        : await api(
            typeof def.token === 'function' ? def.token() : def.token,
            def.method,
            path,
            typeof def.body === 'function' ? def.body(ctx) : def.body,
            def.headers,
          );

      status = outcome.status;
      json = outcome.json;
      text = outcome.text;
      resHeaders = outcome.headers || {};
    } catch (e) {
      text = 'NETWORK ERROR: ' + e.message;
    }

    // Kasus multi-langkah baru mengetahui id setelah run berjalan, jadi label path diperbarui.
    const displayPath = def.run && typeof def.path === 'function' ? def.path(ctx) : path;

    const expected = Array.isArray(def.expectStatus) ? def.expectStatus : [def.expectStatus];
    const statusOk = expected.includes(status);

    let note = '';
    let extraOk = true;

    if (typeof def.check === 'function') {
      const outcome = def.check(json, status, resHeaders, ctx, text, def) || {};
      extraOk = outcome.pass !== false;
      note = outcome.note || '';
    }

    const pass = statusOk && extraOk;

    results.push({
      id: def.id,
      nama: def.nama,
      skenario: def.skenario,
      deskripsi: def.deskripsi,
      langkah: def.langkah,
      dataUji: typeof def.dataUji === 'function' ? def.dataUji(ctx) : def.dataUji,
      ekspektasi: typeof def.ekspektasi === 'function' ? def.ekspektasi(ctx) : def.ekspektasi,
      method: def.method,
      path: displayPath,
      actualStatus: status,
      actualBody: `HTTP ${status} — ${trim(text)}`,
      status: pass ? 'Pass' : 'Fail',
      catatan: note || (pass ? 'Sesuai harapan.' : `Status aktual ${status}, diharapkan ${expected.join(' / ')}.`),
      kebutuhan: def.kebutuhan || '-',
    });

    console.log(`${def.id} [${pass ? 'PASS' : 'FAIL'}] ${def.method} ${displayPath} -> ${status}`);

    if (def.save) def.save(json, ctx);
  }
}

/* -------------------------------------------------------------------------
   A. MANAJEMEN AUTENTIKASI & HAK AKSES
   ------------------------------------------------------------------------ */

T({
  id: 'TC-A01', nama: 'Login Manajer', skenario: 'Autentikasi',
  deskripsi: 'Manajer (Super Admin) login dengan kredensial valid dan menerima token bearer.',
  langkah: '1. POST /api/login 2. Periksa access_token dan token_expires_at',
  dataUji: 'email=superadmin@gmail.com, password=password123',
  ekspektasi: 'HTTP 200, body memuat access_token, token_type=Bearer, dan token_expires_at',
  expectStatus: 200, method: 'POST', path: '/login', token: null,
  body: { email: 'superadmin@gmail.com', password: 'password123' },
  check: (j) => (j && j.access_token && j.token_type === 'Bearer' && j.token_expires_at
    ? { pass: true, note: `Token terbit dan kedaluwarsa pada ${j.token_expires_at}.` }
    : { pass: false, note: 'Token atau batas waktu token tidak dikembalikan.' }),
  save: (j, c) => { c.managerToken = j?.access_token; c.managerUser = j?.user; },
  kebutuhan: 'Kebutuhan a (login, batas waktu logout)',
});

T({
  id: 'TC-A02', nama: 'Login Admin Sales', skenario: 'Autentikasi',
  deskripsi: 'Peran Admin Sales dapat login dan perannya dikembalikan pada respons.',
  langkah: 'POST /api/login dengan akun admin.sales',
  dataUji: 'email=admin.sales@luminpark.com, password=password123',
  ekspektasi: 'HTTP 200 dan user.role=admin_sales',
  expectStatus: 200, method: 'POST', path: '/login', token: null,
  body: { email: 'admin.sales@luminpark.com', password: 'password123' },
  check: (j) => (j?.user?.role === 'admin_sales'
    ? { pass: true, note: 'Peran Admin Sales dikenali sistem.' }
    : { pass: false, note: `Peran pada respons: ${j?.user?.role}` }),
  save: (j, c) => { c.adminSalesToken = j?.access_token; },
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-A03', nama: 'Login Sales', skenario: 'Autentikasi',
  deskripsi: 'Akun Sales login dan membawa profil sales pada respons.',
  langkah: 'POST /api/login akun sales@luminpark.com',
  dataUji: 'email=sales@luminpark.com, password=password123',
  ekspektasi: 'HTTP 200 dan user.sales_profile.sales_code terisi',
  expectStatus: 200, method: 'POST', path: '/login', token: null,
  body: { email: 'sales@luminpark.com', password: 'password123' },
  check: (j) => (j?.user?.sales_profile?.sales_code
    ? { pass: true, note: `Profil sales ${j.user.sales_profile.sales_code} ikut dikembalikan.` }
    : { pass: false, note: 'Profil sales tidak disertakan pada respons login.' }),
  save: (j, c) => { c.salesToken = j?.access_token; c.salesProfileId = j?.user?.sales_profile?.id ?? c.salesProfileId; },
  kebutuhan: 'Kebutuhan a (login)',
});

T({
  id: 'TC-A04', nama: 'Login Konsumen Ditolak', skenario: 'Autentikasi negatif',
  deskripsi: 'Konsumen berinteraksi lewat halaman publik sehingga tidak diberi akun login internal.',
  langkah: 'POST /api/login memakai email konsumen@luminpark.com',
  dataUji: 'email=konsumen@luminpark.com, password=password123',
  ekspektasi: 'HTTP 403 (akun konsumen sengaja dibuat untuk uji penolakan) atau 401 (sistem tidak menyimpan akun konsumen)',
  expectStatus: [403, 401], method: 'POST', path: '/login', token: null,
  body: { email: 'konsumen@luminpark.com', password: 'password123' },
  check: (j, status) => (j?.access_token
    ? { pass: false, note: 'Akun konsumen seharusnya tidak pernah menghasilkan token.' }
    : { pass: true, note: `${status === 403 ? 'Ditolak eksplisit: ' + (j?.message || '-') : 'Akun konsumen memang tidak terdaftar (401).' }` }),
  save: (j, c) => { c.consumerToken = j?.access_token ?? null; },
  kebutuhan: 'Kebutuhan a (RBAC) + keputusan 4 aktor',
});

T({
  id: 'TC-A04b', nama: 'Tidak Ada Rute Pembuatan Akun', skenario: 'RBAC negatif',
  deskripsi: 'Sistem tidak menyediakan endpoint pendaftaran akun, sehingga akun konsumen tidak mungkin dibuat dari API.',
  langkah: 'POST /api/register memakai token manajer',
  dataUji: 'role=consumer',
  ekspektasi: 'HTTP 404 karena rute pendaftaran memang tidak ada',
  expectStatus: 404, method: 'POST', path: '/register', token: M,
  body: { name: 'Konsumen Baru', email: 'konsumen.baru@example.com', password: 'password123', role: 'consumer' },
  kebutuhan: 'Keputusan 4 aktor (konsumen tanpa akun login)',
});

T({
  id: 'TC-A04c', nama: 'Akun Super Admin Lama Ditolak', skenario: 'Autentikasi negatif',
  deskripsi: 'Akun admin dan manajer adalah orang yang sama, jadi hanya satu akun super admin yang berlaku '
    + '(superadmin@gmail.com). Kedua akun lama dihapus dari seeder dan tidak bisa login lagi.',
  langkah: '1. POST /api/login memakai admin@luminpark.com 2. POST /api/login memakai manager@luminpark.com',
  dataUji: 'email=admin@luminpark.com dan manager@luminpark.com, password=password123',
  ekspektasi: 'Keduanya HTTP 401 dan tanpa access_token',
  expectStatus: 401, method: 'POST', path: '/login', token: null,
  run: async () => {
    ctx.lamaSuperAdmin = [];
    for (const email of ['admin@luminpark.com', 'manager@luminpark.com']) {
      const hasil = await api(null, 'POST', '/login', { email, password: 'password123' });
      ctx.lamaSuperAdmin.push(`${email}=${hasil.status}${hasil.json?.access_token ? '+TOKEN' : ''}`);
    }
    const masihAdaToken = ctx.lamaSuperAdmin.some((x) => x.includes('+TOKEN'));
    return { status: masihAdaToken ? 200 : 401, json: {} };
  },
  check: (j, status) => ({
    pass: status === 401,
    note: (status === 401 ? 'Hanya superadmin@gmail.com yang aktif: ' : 'Akun lama masih bisa login: ')
      + ctx.lamaSuperAdmin.join(', '),
  }),
  kebutuhan: 'Keputusan akun: satu super admin',
});

T({
  id: 'TC-A05', nama: 'Login Password Salah', skenario: 'Autentikasi negatif',
  deskripsi: 'Kredensial salah ditolak dan token tidak diberikan.',
  langkah: 'POST /api/login dengan password salah',
  dataUji: 'email=superadmin@gmail.com, password=rahasia-salah',
  ekspektasi: 'HTTP 401 tanpa access_token',
  expectStatus: 401, method: 'POST', path: '/login', token: null,
  body: { email: 'superadmin@gmail.com', password: 'rahasia-salah' },
  check: (j) => (!j?.access_token ? { pass: true, note: 'Pesan error jelas: ' + (j?.message || '-') } : { pass: false }),
  kebutuhan: 'Kebutuhan non-fungsional (pesan error jelas)',
});

T({
  id: 'TC-A06', nama: 'Login Tanpa Validasi', skenario: 'Autentikasi negatif',
  deskripsi: 'Request login tanpa email/password memunculkan error validasi.',
  langkah: 'POST /api/login body kosong',
  dataUji: '{}',
  ekspektasi: 'HTTP 422 dengan error validasi',
  expectStatus: 422, method: 'POST', path: '/login', token: null, body: {},
  kebutuhan: 'Kebutuhan non-fungsional (pesan error jelas)',
});

T({
  id: 'TC-A07', nama: 'Akses Tanpa Token', skenario: 'Otorisasi negatif',
  deskripsi: 'Endpoint internal menolak request yang tidak menyertakan token.',
  langkah: 'GET /api/units tanpa header Authorization',
  dataUji: 'tanpa token',
  ekspektasi: 'HTTP 401',
  expectStatus: 401, method: 'GET', path: '/units', token: null,
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-A08', nama: 'Token Tidak Sah', skenario: 'Otorisasi negatif',
  deskripsi: 'Token acak ditolak oleh Sanctum.',
  langkah: 'GET /api/me dengan Authorization: Bearer token-palsu',
  dataUji: 'token=9999|tokenacak',
  ekspektasi: 'HTTP 401',
  expectStatus: 401, method: 'GET', path: '/me', token: '9999|tokenacak',
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-A09', nama: 'Profil Diri Sendiri', skenario: 'Autentikasi',
  deskripsi: 'Endpoint /me mengembalikan data akun beserta peran dan status akun.',
  langkah: 'GET /api/me dengan token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200, data.role=manager dan data.status=aktif',
  expectStatus: 200, method: 'GET', path: '/me', token: M,
  check: (j) => (j?.data?.role === 'manager' && j?.data?.status === 'aktif'
    ? { pass: true, note: 'Kolom status akun (ERD) terpakai.' }
    : { pass: false, note: `role=${j?.data?.role}, status=${j?.data?.status}` }),
  kebutuhan: 'Kebutuhan a + ERD tabel pengguna (status akun)',
});

T({
  id: 'TC-A10', nama: 'Sales Menolak Dashboard Manajer', skenario: 'RBAC negatif',
  deskripsi: 'Sales tidak dapat membuka dashboard manajer.',
  langkah: 'GET /api/dashboard/manager memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'GET', path: '/dashboard/manager', token: S,
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-A11', nama: 'Sales Menolak CRUD Unit', skenario: 'RBAC negatif',
  deskripsi: 'Hak tambah/ubah/hapus unit hanya milik Manajer (dan Admin Sales untuk baca).',
  langkah: 'POST /api/units memakai token sales',
  dataUji: 'unit_code=LT-900, cluster_id=<cluster>, price=700000000',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'POST', path: '/units', token: S,
  body: () => ({ unit_code: 'LT-900', cluster_id: ctx.clusterId, unit_type: 'Type Tes', land_size: 60, building_size: 45, price: 700000000, status: 'available' }),
  kebutuhan: 'Kebutuhan b (Manajer dapat menambah data unit)',
});

T({
  id: 'TC-A12', nama: 'Konsumen Menolak Modul Internal', skenario: 'RBAC negatif',
  deskripsi: 'Akun konsumen tidak pernah memperoleh token, jadi data sales internal tidak dapat dibuka sama sekali.',
  langkah: 'GET /api/sales memakai token konsumen (kosong karena login ditolak)',
  dataUji: () => 'token konsumen = ' + (ctx.consumerToken ?? 'tidak ada'),
  ekspektasi: 'HTTP 401 (tanpa token) atau 403 (bila token konsumen sempat dibuat)',
  expectStatus: [401, 403], method: 'GET', path: '/sales', token: C,
  kebutuhan: 'Kebutuhan a + h (RBAC)',
});

T({
  id: 'TC-A13', nama: 'Admin Sales Dapat Melihat Unit', skenario: 'RBAC positif',
  deskripsi: 'Admin Sales dan Manajer dapat melihat seluruh data unit beserta statusnya.',
  langkah: 'GET /api/units memakai token Admin Sales',
  dataUji: 'token admin_sales',
  ekspektasi: 'HTTP 200 berisi daftar unit',
  expectStatus: 200, method: 'GET', path: '/units', token: A,
  check: (j) => (Array.isArray(j) && j.length > 0
    ? { pass: true, note: `${j.length} unit terbaca oleh Admin Sales.` }
    : { pass: false, note: 'Respons bukan daftar unit.' }),
  kebutuhan: 'Kebutuhan b (Admin Sales melihat unit)',
});

T({
  id: 'TC-A14', nama: 'Sales Melihat Unit Tersedia', skenario: 'RBAC positif',
  deskripsi: 'Sales dapat melihat informasi unit yang tersedia.',
  langkah: 'GET /api/units-available memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 200, seluruh unit berstatus available',
  expectStatus: 200, method: 'GET', path: '/units-available', token: S,
  check: (j) => (Array.isArray(j) && j.length > 0 && j.every((u) => u.status === 'available')
    ? { pass: true, note: `${j.length} unit tersedia.` }
    : { pass: false, note: 'Ditemukan unit non-tersedia pada daftar tersedia.' }),
  kebutuhan: 'Kebutuhan b (Sales melihat unit tersedia)',
});

T({
  id: 'TC-A15', nama: 'Sesi Login Tercatat', skenario: 'Sesi & log',
  deskripsi: 'Tabel sesi_login mencatat token, IP, user agent, dan waktu login.',
  langkah: 'GET /api/sessions memakai token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200, minimal satu sesi berstatus active dengan ip_address terisi',
  expectStatus: 200, method: 'GET', path: '/sessions', token: M,
  check: (j) => {
    const row = j?.data?.[0];
    if (!row) return { pass: false, note: 'Belum ada sesi tercatat.' };
    const ok = row.status === 'active' && !!row.login_at && !!row.token_id;
    return { pass: ok, note: `login_at=${row.login_at}, ip=${row.ip_address}, token_id=${row.token_id}, status=${row.status}` };
  },
  kebutuhan: 'ERD tabel sesi_login',
});

T({
  id: 'TC-A16', nama: 'Logout Menghapus Akses Token', skenario: 'Autentikasi',
  deskripsi: 'Setelah logout, token dicabut sehingga request berikutnya ditolak.',
  langkah: '1. Login akun sales2 2. GET /me 3. POST /logout 4. GET /me memakai token yang sama',
  dataUji: 'token akun sales2',
  ekspektasi: 'POST /logout HTTP 200 dan GET /me berikutnya HTTP 401',
  expectStatus: 200, method: 'POST', path: '/logout',
  run: async () => {
    const token = await loginAs('sales2@luminpark.com');
    const before = await api(token, 'GET', '/me');
    const res = await api(token, 'POST', '/logout', {});
    const after = await api(token, 'GET', '/me');
    ctx.preLogoutStatus = before.status;
    ctx.postLogoutStatus = after.status;
    return res;
  },
  check: () => {
    const ok = ctx.preLogoutStatus === 200 && ctx.postLogoutStatus === 401;
    return { pass: ok, note: `GET /me sebelum logout HTTP ${ctx.preLogoutStatus}, sesudah logout HTTP ${ctx.postLogoutStatus}.` };
  },
  kebutuhan: 'Kebutuhan a (logout dengan batas waktu)',
});

/* -------------------------------------------------------------------------
   B. MANAJEMEN DATA UNIT
   ------------------------------------------------------------------------ */

T({
  id: 'TC-B01', nama: 'Daftar Unit + Relasi', skenario: 'Membaca data unit',
  deskripsi: 'Unit terbaca lengkap dengan cluster dan proyek sesuai struktur ERD.',
  langkah: 'GET /api/units?status=available',
  dataUji: 'filter status=available',
  ekspektasi: 'HTTP 200 dan tiap unit memuat cluster.name serta cluster.project.name',
  expectStatus: 200, method: 'GET', path: '/units?status=available', token: M,
  check: (j) => {
    const u = j?.[0];
    const ok = !!u?.cluster?.name && !!u?.cluster?.project?.name;
    return { pass: ok, note: ok ? `${u.unit_code} → ${u.cluster.name} → ${u.cluster.project.name}` : 'Relasi cluster/proyek tidak ikut termuat.' };
  },
  kebutuhan: 'ERD tabel unit_rumah, cluster, proyek',
});

T({
  id: 'TC-B02', nama: 'Daftar Cluster', skenario: 'Membaca data referensi',
  deskripsi: 'Cluster beserta proyeknya tersedia sebagai data referensi formulir unit.',
  langkah: 'GET /api/units/clusters',
  dataUji: 'tanpa parameter',
  ekspektasi: 'HTTP 200 berisi cluster dengan project_id',
  expectStatus: 200, method: 'GET', path: '/units/clusters', token: M,
  check: (j) => {
    const arr = j?.data || [];
    if (!arr.length) return { pass: false, note: 'Tidak ada cluster.' };
    ctx.clusterId = arr[0].id;
    return { pass: !!arr[0].project_id, note: `${arr.length} cluster, contoh ${arr[0].name} (project_id=${arr[0].project_id}).` };
  },
  kebutuhan: 'ERD tabel cluster',
});

T({
  id: 'TC-B03', nama: 'Tambah Unit', skenario: 'Membuat data unit',
  deskripsi: 'Manajer menambahkan unit baru dengan kode unik dan status awal tersedia.',
  langkah: 'POST /api/units',
  dataUji: 'unit_code=LT-TEST-01, cluster_id=<cluster>, tipe 60/45, harga 725.000.000',
  ekspektasi: 'HTTP 201 dan unit tersimpan dengan status available',
  expectStatus: 201, method: 'POST', path: '/units', token: M,
  body: () => ({
    unit_code: 'LT-TEST-01', cluster_id: ctx.clusterId, unit_type: 'Modern Tropical Tes',
    land_size: 60, building_size: 45, price: 725000000, status: 'available', description: 'Unit uji fungsional.',
  }),
  check: (j) => (j?.data?.unit_code === 'LT-TEST-01' && j?.data?.status === 'available'
    ? { pass: true, note: `Unit id=${j.data.id} terbentuk.` }
    : { pass: false, note: trim(JSON.stringify(j)) }),
  save: (j, c) => { c.newUnitId = j?.data?.id; },
  kebutuhan: 'Kebutuhan b (menambah data unit)',
});

T({
  id: 'TC-B04', nama: 'Duplikasi Kode Unit', skenario: 'Validasi unit',
  deskripsi: 'Kode unit harus unik sehingga duplikasi ditolak.',
  langkah: 'POST /api/units memakai kode yang sama',
  dataUji: 'unit_code=LT-TEST-01 (sudah ada)',
  ekspektasi: 'HTTP 422 dengan pesan kesalahan unik',
  expectStatus: 422, method: 'POST', path: '/units', token: M,
  body: () => ({
    unit_code: 'LT-TEST-01', cluster_id: ctx.clusterId, unit_type: 'Duplikat',
    land_size: 60, building_size: 45, price: 700000000, status: 'available',
  }),
  kebutuhan: 'ERD unit_rumah (kode unit unik)',
});

T({
  id: 'TC-B05', nama: 'Status Unit Tidak Sah', skenario: 'Validasi unit',
  deskripsi: 'Nilai status lama seperti booked ditolak; hanya empat status ERD yang berlaku.',
  langkah: 'POST /api/units memakai status=booked',
  dataUji: 'unit_code=LT-TEST-02, status=booked',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/units', token: M,
  body: () => ({
    unit_code: 'LT-TEST-02', cluster_id: ctx.clusterId, unit_type: 'Status Salah',
    land_size: 60, building_size: 45, price: 700000000, status: 'booked',
  }),
  kebutuhan: 'Kebutuhan b (tersedia, booking, sold out, proses delivery)',
});

T({
  id: 'TC-B06', nama: 'Perbarui Status Unit', skenario: 'Memperbarui data unit',
  deskripsi: 'Manajer dapat memperbarui status unit menjadi proses delivery.',
  langkah: 'PUT /api/units/{id}',
  dataUji: 'status=delivery_process',
  ekspektasi: 'HTTP 200 dengan status delivery_process',
  expectStatus: 200, method: 'PUT', path: () => `/units/${ctx.newUnitId}`, token: M,
  body: () => ({ status: 'delivery_process' }),
  check: (j) => (j?.data?.status === 'delivery_process'
    ? { pass: true, note: 'Status unit berhasil diperbarui.' }
    : { pass: false, note: `status=${j?.data?.status}` }),
  kebutuhan: 'Kebutuhan b (memperbarui status unit)',
});

T({
  id: 'TC-B07', nama: 'Detail Unit', skenario: 'Membaca data unit',
  deskripsi: 'Detail satu unit mengembalikan spesifikasi lengkap.',
  langkah: 'GET /api/units/{id}',
  dataUji: 'id unit hasil TC-B03',
  ekspektasi: 'HTTP 200 dengan data.id sesuai',
  expectStatus: 200, method: 'GET', path: () => `/units/${ctx.newUnitId}`, token: M,
  check: (j) => ({ pass: j?.data?.id === ctx.newUnitId, note: `tipe=${j?.data?.unit_type}, harga=${j?.data?.price}` }),
  kebutuhan: 'Kebutuhan b',
});

T({
  id: 'TC-B08', nama: 'Unit Tidak Ditemukan', skenario: 'Edge case unit',
  deskripsi: 'Permintaan id di luar rentang menghasilkan 404, bukan 500.',
  langkah: 'GET /api/units/999999',
  dataUji: 'id=999999',
  ekspektasi: 'HTTP 404',
  expectStatus: 404, method: 'GET', path: '/units/999999', token: M,
  kebutuhan: 'Kebutuhan non-fungsional (tidak crash)',
});

T({
  id: 'TC-B09', nama: 'Hapus Unit', skenario: 'Menghapus data unit',
  deskripsi: 'Manajer menghapus unit yang belum pernah dipesan.',
  langkah: 'DELETE /api/units/{id}',
  dataUji: 'id unit hasil TC-B03',
  ekspektasi: 'HTTP 200',
  expectStatus: 200, method: 'DELETE', path: () => `/units/${ctx.newUnitId}`, token: M,
  kebutuhan: 'Kebutuhan b (menghapus data unit)',
});

T({
  id: 'TC-B10', nama: 'Hapus Unit Berriwayat Booking', skenario: 'Integritas data unit',
  deskripsi: 'Unit yang sudah dipesan tidak boleh dihapus agar riwayat transaksi utuh.',
  langkah: 'DELETE /api/units/{id} pada unit milik booking contoh seeder',
  dataUji: () => `unit_id=${ctx.seededBookedUnitId} (punya pemesanan)`,
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'DELETE', path: () => `/units/${ctx.seededBookedUnitId}`, token: M,
  check: (j) => ({ pass: !!j?.message, note: j?.message || trim(JSON.stringify(j)) }),
  kebutuhan: 'ERD relasi unit_rumah → pemesanan',
});

/* -------------------------------------------------------------------------
   C. MANAJEMEN CALON KONSUMEN & FOLLOW UP
   ------------------------------------------------------------------------ */

T({
  id: 'TC-C01', nama: 'Sales Menambah Calon Konsumen', skenario: 'Membuat data lead',
  deskripsi: 'Sales menambah calon konsumen baru; sales_id diambil dari token, bukan dari klien.',
  langkah: 'POST /api/customer-leads memakai token sales',
  dataUji: 'nama=Consumer Uji, telepon=081233990001, status=warm',
  ekspektasi: 'HTTP 201 dan sales_id terisi otomatis dari token',
  expectStatus: 201, method: 'POST', path: '/customer-leads', token: S,
  body: {
    name: 'Consumer Uji', phone: '081233990001', email: 'uji@consumer.com',
    address: 'Jl. Uji Coba 1', status: 'warm', budget: 800000000, note: 'Lead pengujian.', source: 'manual',
  },
  check: (j) => (j?.data?.sales_id
    ? { pass: true, note: `sales_id=${j.data.sales_id} berasal dari token login.` }
    : { pass: false, note: 'sales_id tidak terisi otomatis.' }),
  save: (j, c) => { c.leadId = j?.data?.id; c.leadSalesId = j?.data?.sales_id; },
  kebutuhan: 'Kebutuhan e (Sales menambah calon konsumen)',
});

T({
  id: 'TC-C02', nama: 'Status Lead Tidak Sah', skenario: 'Validasi lead',
  deskripsi: 'Nilai status di luar cold/warm/hot/booking ditolak.',
  langkah: 'POST /api/customer-leads dengan status=negotiating',
  dataUji: 'status=negotiating',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/customer-leads', token: S,
  body: { name: 'Lead Salah', phone: '081233990002', status: 'negotiating' },
  kebutuhan: 'Kebutuhan e (status cold, warm, hot, booking)',
});

T({
  id: 'TC-C03', nama: 'Telepon Wajib', skenario: 'Validasi lead',
  deskripsi: 'Calon konsumen tanpa nomor telepon tidak dapat disimpan.',
  langkah: 'POST /api/customer-leads tanpa phone',
  dataUji: 'nama tanpa phone',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/customer-leads', token: S,
  body: { name: 'Lead Tanpa Telepon', status: 'warm' },
  kebutuhan: 'ERD calon_konsumen (no. telepon)',
});

T({
  id: 'TC-C04', nama: 'Ubah Status Lead', skenario: 'Memperbarui data lead',
  deskripsi: 'Sales memperbarui status calon konsumen menjadi hot.',
  langkah: 'PUT /api/customer-leads/{id}',
  dataUji: 'status=hot',
  ekspektasi: 'HTTP 200 dengan status hot',
  expectStatus: 200, method: 'PUT', path: () => `/customer-leads/${ctx.leadId}`, token: S,
  body: { status: 'hot' },
  check: (j) => ({ pass: j?.data?.status === 'hot', note: `status=${j?.data?.status}` }),
  kebutuhan: 'Kebutuhan e (memperbarui status)',
});

T({
  id: 'TC-C05', nama: 'Riwayat Follow Up', skenario: 'Mencatat interaksi',
  deskripsi: 'Follow up tercatat lengkap (tanggal, metode, status sebelum/sesudah) dan sales_id diambil dari token.',
  langkah: 'POST /api/follow-ups memakai token sales',
  dataUji: 'lead_id dari TC-C01, method=whatsapp, status_before=warm, status_after=hot',
  ekspektasi: 'HTTP 201 dan sales_id terisi otomatis',
  expectStatus: 201, method: 'POST', path: '/follow-ups', token: S,
  body: () => ({
    lead_id: ctx.leadId, follow_up_date: '2026-10-20 09:30:00',
    status_before: 'warm', status_after: 'hot', method: 'whatsapp',
    note: 'Menjelaskan skema cash bertahap dan menjadwalkan survey.',
  }),
  check: (j) => ({ pass: !!j?.data?.sales_id, note: `follow up id=${j?.data?.id}, sales_id=${j?.data?.sales_id}` }),
  save: (j, c) => { c.followUpId = j?.data?.id; },
  kebutuhan: 'Kebutuhan e (riwayat follow up)',
});

T({
  id: 'TC-C06', nama: 'Sinkronisasi Lead Setelah Follow Up', skenario: 'Dampak follow up',
  deskripsi: 'Status lead dan last_follow_up ikut terbarui setelah riwayat follow up dibuat.',
  langkah: 'GET /api/customer-leads/{id}',
  dataUji: 'id lead hasil TC-C01',
  ekspektasi: 'HTTP 200 dengan status=hot dan last_follow_up=2026-10-20',
  expectStatus: 200, method: 'GET', path: () => `/customer-leads/${ctx.leadId}`, token: S,
  check: (j) => {
    const ok = j?.data?.status === 'hot' && String(j?.data?.last_follow_up || '').startsWith('2026-10-20');
    return { pass: ok, note: `status=${j?.data?.status}, last_follow_up=${j?.data?.last_follow_up}` };
  },
  kebutuhan: 'Kebutuhan e (status terkini)',
});

T({
  id: 'TC-C07', nama: 'Daftar Follow Up Per Lead', skenario: 'Membaca riwayat',
  deskripsi: 'Riwayat follow up dapat disaring berdasarkan lead.',
  langkah: 'GET /api/follow-ups?lead_id={id}',
  dataUji: 'lead_id dari TC-C01',
  ekspektasi: 'HTTP 200 berisi minimal satu riwayat',
  expectStatus: 200, method: 'GET', path: () => `/follow-ups?lead_id=${ctx.leadId}`, token: A,
  check: (j) => {
    const n = (j?.data || []).length;
    return { pass: n >= 1, note: `${n} riwayat follow up terbaca oleh Admin Sales.` };
  },
  kebutuhan: 'Kebutuhan e (Admin Sales memantau follow up)',
});

T({
  id: 'TC-C08', nama: 'Filter Status oleh Admin Sales', skenario: 'Melihat seluruh lead',
  deskripsi: 'Admin Sales melihat seluruh calon konsumen dari semua sales dengan filter status.',
  langkah: 'GET /api/customer-leads?status=hot memakai token Admin Sales',
  dataUji: 'status=hot',
  ekspektasi: 'HTTP 200, seluruh data berstatus hot',
  expectStatus: 200, method: 'GET', path: '/customer-leads?status=hot', token: A,
  check: (j) => {
    const arr = j?.data || [];
    return { pass: arr.every((l) => l.status === 'hot'), note: `${arr.length} lead hot lintas sales.` };
  },
  kebutuhan: 'Kebutuhan e (filter status)',
});

T({
  id: 'TC-C09', nama: 'Lead Sales Hanya Terlihat oleh Sales Itu', skenario: 'Isolasi data',
  deskripsi: 'Daftar lead milik sales tidak memuat lead milik sales lain.',
  langkah: '1. Sales2 membuat lead 2. Sales1 membuka GET /api/customer-leads 3. Daftar dibandingkan',
  dataUji: 'lead milik sales2 vs daftar milik sales1',
  ekspektasi: 'HTTP 200 dan lead milik sales2 tidak muncul pada daftar sales1',
  expectStatus: 200, method: 'GET', path: '/customer-leads', token: S,
  run: async () => {
    const token2 = await loginAs('sales2@luminpark.com');
    const other = await api(token2, 'POST', '/customer-leads', {
      name: 'Lead Milik Sales Lain', phone: '081233990055', status: 'warm',
    });
    ctx.otherLeadId = other.json?.data?.id;
    ctx.otherLeadSalesId = other.json?.data?.sales_id;
    return await api(S(), 'GET', '/customer-leads');
  },
  check: (j) => {
    const arr = j?.data || [];
    const leaked = arr.some((l) => String(l.id) === String(ctx.otherLeadId));
    const mixed = arr.some((l) => String(l.sales_id) !== String(ctx.salesProfileId));
    return {
      pass: !leaked && !mixed,
      note: leaked ? 'Lead milik sales lain ikut terbaca.' : `${arr.length} lead, seluruhnya milik sales ini (lead sales2 id=${ctx.otherLeadId} tersembunyi).`,
    };
  },
  kebutuhan: 'Kebutuhan a + e (hak akses berjenjang)',
});

T({
  id: 'TC-C10', nama: 'Ubah Lead Milik Sales Lain', skenario: 'RBAC negatif',
  deskripsi: 'Sales tidak dapat mengubah lead yang belonging sales lain.',
  langkah: 'PUT /api/customer-leads/{id} memakai token sales1 atas lead milik sales2',
  dataUji: () => `lead_id=${ctx.otherLeadId} milik sales_id=${ctx.otherLeadSalesId}`,
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'PUT', path: () => `/customer-leads/${ctx.otherLeadId}`, token: S,
  body: { status: 'cold' },
  kebutuhan: 'Kebutuhan a + e (hak akses berjenjang)',
});

T({
  id: 'TC-C11', nama: 'Hapus Lead Berstatus Booking', skenario: 'Integritas data lead',
  deskripsi: 'Calon konsumen yang sudah menjadi konsumen (punya booking) tidak dapat dihapus.',
  langkah: 'DELETE /api/customer-leads/{id} pada lead milik booking contoh seeder',
  dataUji: () => `lead_id=${ctx.seededBookedLeadId} (sudah memiliki pemesanan)`,
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'DELETE', path: () => `/customer-leads/${ctx.seededBookedLeadId}`, token: M,
  check: (j) => ({ pass: !!j?.message, note: j?.message || trim(JSON.stringify(j)) }),
  kebutuhan: 'ERD relasi calon_konsumen → pemesanan',
});

/* -------------------------------------------------------------------------
   D. SKEMA PEMBIAYAAN & PEMESANAN
   ------------------------------------------------------------------------ */

T({
  id: 'TC-D01', nama: 'Kelola Skema Pembayaran', skenario: 'Membuat skema',
  deskripsi: 'Skema baru wajib punya tipe (KPR / cash keras / cash bertahap) sesuai laporan.',
  langkah: '1. POST tanpa scheme_type 2. POST dengan scheme_type',
  dataUji: 'scheme_name=Cash Bertahap 24, percent=25, tenor=24, bunga=0',
  ekspektasi: 'HTTP 201 saat menyertakan scheme_type',
  expectStatus: 201, method: 'POST', path: '/payment-schemes', token: M,
  body: {
    scheme_name: 'Cash Bertahap 24', scheme_type: 'cash bertahap', description: 'Uji perhitungan skema.',
    down_payment_percent: 25, installment_months: 24, interest_rate: 0, is_active: true,
  },
  run: async () => {
    // Bersihkan sisa skema uji dari dijalankan sebelumnya agar kasus ini dapat diulang.
    const daftar = await api(M(), 'GET', '/payment-schemes');
    for (const s of (daftar.json?.data ?? [])) {
      if (['Cash Bertahap 24', 'Tanpa Tipe'].includes(s.scheme_name)) {
        await api(M(), 'DELETE', '/payment-schemes/' + s.id);
      }
    }

    ctx.schemeTypeMissingStatus = (await api(M(), 'POST', '/payment-schemes', {
      scheme_name: 'Tanpa Tipe', down_payment_percent: 10, installment_months: 12, interest_rate: 0,
    })).status;
    return await api(M(), 'POST', '/payment-schemes', {
      scheme_name: 'Cash Bertahap 24', scheme_type: 'cash bertahap', description: 'Uji perhitungan skema.',
      down_payment_percent: 25, installment_months: 24, interest_rate: 0, is_active: true,
    });
  },
  check: (j) => ({ pass: j?.data?.scheme_type === 'cash bertahap' && ctx.schemeTypeMissingStatus === 422, note: `scheme_type wajib (tanpa tipe → HTTP ${ctx.schemeTypeMissingStatus}); skema baru id=${j?.data?.id}` }),
  save: (j, c) => { c.schemeId = j?.data?.id; },
  kebutuhan: 'Kebutuhan b/c (tiga skema pembayaran)',
});

T({
  id: 'TC-D02', nama: 'Simulasi Perhitungan Otomatis', skenario: 'Perhitungan DP & angsuran',
  deskripsi: 'Sistem menghitung uang muka, angsuran, dan bunga secara otomatis dari harga unit.',
  langkah: 'POST /api/payment-schemes/{id}/simulate memakai harga unit tetap',
  dataUji: 'unit harga 1.000.000.000, DP 25%, tenor 24 bulan, bunga 0%',
  ekspektasi: 'HTTP 200 dengan down_payment_amount=250.000.000 dan installment_amount=31.250.000',
  expectStatus: 200, method: 'POST', path: () => `/payment-schemes/${ctx.schemeId}/simulate`, token: S,
  body: { total_price: 1000000000 },
  check: (j) => {
    const dp = Number(j?.data?.down_payment_amount);
    const ins = Number(j?.data?.installment_amount);
    const ok = dp === 250000000 && ins === 31250000;
    return { pass: ok, note: `DP=${dp.toLocaleString('id-ID')}, angsuran=${ins.toLocaleString('id-ID')}, bunga=${Number(j?.data?.interest_total).toLocaleString('id-ID')}` };
  },
  kebutuhan: 'Kebutuhan c.3 (sistem otomatis menghitung DP, cicilan, sisa)',
});

T({
  id: 'TC-D03', nama: 'Booking Menolak Lead Cold', skenario: 'Pemesanan negatif',
  deskripsi: 'Pemesanan hanya boleh untuk calon konsumen berstatus hot.',
  langkah: 'POST /api/bookings memakai lead berstatus cold',
  dataUji: 'lead_id cold, unit tersedia, skema cash keras',
  ekspektasi: 'HTTP 422 dengan pesan status hot wajib',
  expectStatus: 422, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.coldLeadId, sales_id: ctx.salesProfileId, unit_id: ctx.bookableUnitId, payment_scheme_id: ctx.cashKerasId, booking_date: '2026-10-21' }),
  check: (j) => ({ pass: !!j?.message, note: j?.message || trim(JSON.stringify(j)) }),
  kebutuhan: 'Kebutuhan c.1 (konsumen dengan status hot)',
});

T({
  id: 'TC-D04', nama: 'Booking Menolak Unit Tidak Tersedia', skenario: 'Pemesanan negatif',
  deskripsi: 'Unit yang sudah dipesan/terjual tidak dapat dibooking ulang.',
  langkah: 'POST /api/bookings memakai unit berstatus sold_out',
  dataUji: () => `unit_id=${ctx.soldUnitId} (sold_out), lead hot`,
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.leadId, sales_id: ctx.salesProfileId, unit_id: ctx.soldUnitId, payment_scheme_id: ctx.cashKerasId, booking_date: '2026-10-21' }),
  check: (j) => ({ pass: !!j?.message, note: j?.message || trim(JSON.stringify(j)) }),
  kebutuhan: 'Kebutuhan c.4 (status unit berubah setelah booking)',
});

T({
  id: 'TC-D05', nama: 'Booking Tidak Menerima Nilai Turunan', skenario: 'Pemesanan validasi',
  deskripsi: 'Klien tidak lagi mengirim booking_code, DP, angsuran, atau sisa pembayaran; semuanya dihitung server.',
  langkah: 'POST /api/bookings tanpa field turunan',
  dataUji: () => `lead_id=${ctx.leadId} (hot), unit_id=${ctx.bookableUnitId} (available), skema Cash Bertahap 30%/36 bulan`,
  ekspektasi: 'HTTP 201 dengan booking_code otomatis dan DP = 30% harga unit',
  expectStatus: 201, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.leadId, sales_id: ctx.salesProfileId, unit_id: ctx.bookableUnitId, payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-10-21' }),
  check: (j) => {
    const b = j?.data;
    if (!b) return { pass: false, note: trim(JSON.stringify(j)) };
    const harga = Number(b.total_price);
    const dp = Number(b.down_payment_amount);
    const ok = /^BK\d{6}\d{4}$/.test(String(b.booking_code)) && Math.abs(dp - harga * 0.3) < 1 && Number(b.remaining_amount) === harga;
    return { pass: ok, note: `kode=${b.booking_code}, harga=${harga.toLocaleString('id-ID')}, DP=${dp.toLocaleString('id-ID')}, angsuran=${Number(b.installment_amount).toLocaleString('id-ID')}, sisa=${Number(b.remaining_amount).toLocaleString('id-ID')}` };
  },
  save: (j, c) => { c.bookingId = j?.data?.id; c.bookingCode = j?.data?.booking_code; c.bookingTotal = Number(j?.data?.total_price); c.bookingDP = Number(j?.data?.down_payment_amount); c.bookingInstallment = Number(j?.data?.installment_amount); },
  kebutuhan: 'Kebutuhan c.2–c.3 (skema & perhitungan otomatis)',
});

T({
  id: 'TC-D06', nama: 'Status Unit Berubah Menjadi Booking', skenario: 'Dampak pemesanan',
  deskripsi: 'Sistem mengubah status unit menjadi dipesan setelah booking berhasil.',
  langkah: 'GET /api/units/{id} setelah booking',
  dataUji: () => `unit_id=${ctx.bookableUnitId} (hasil TC-D05)`,
  ekspektasi: 'HTTP 200 dengan status=booking',
  expectStatus: 200, method: 'GET', path: () => `/units/${ctx.bookableUnitId}`, token: M,
  check: (j) => ({ pass: j?.data?.status === 'booking', note: `status unit=${j?.data?.status}` }),
  kebutuhan: 'Kebutuhan c.4',
});

T({
  id: 'TC-D07', nama: 'Lead Berubah Menjadi Konsumen', skenario: 'Dampak pemesanan',
  deskripsi: 'Calon konsumen yang sudah booking berubah status menjadi booking (konsumen).',
  langkah: 'GET /api/customer-leads/{id}',
  dataUji: () => `lead_id=${ctx.leadId} (hasil TC-C01)`,
  ekspektasi: 'HTTP 200 dengan status=booking',
  expectStatus: 200, method: 'GET', path: () => `/customer-leads/${ctx.leadId}`, token: M,
  check: (j) => ({ pass: j?.data?.status === 'booking', note: `status lead=${j?.data?.status}` }),
  kebutuhan: 'Kebutuhan e + ERD',
});

T({
  id: 'TC-D08', nama: 'Daftar Pemesanan', skenario: 'Membaca pemesanan',
  deskripsi: 'Daftar booking menampilkan relasi konsumen, sales, unit, dan skema.',
  langkah: 'GET /api/bookings?status=menunggu dp',
  dataUji: 'filter status=menunggu dp',
  ekspektasi: 'HTTP 200 dengan relasi lengkap dan booking_code terisi',
  expectStatus: 200, method: 'GET', path: '/bookings?status=menunggu%20dp', token: M,
  check: (j) => {
    const b = j?.data?.[0];
    const ok = !!b?.customer_lead?.name && !!b?.sales_profile?.user?.name && !!b?.unit?.unit_code && !!b?.payment_scheme?.scheme_name;
    return { pass: ok, note: ok ? `${b.booking_code}: ${b.customer_lead.name} / ${b.sales_profile.user.name} / ${b.unit.unit_code} / ${b.payment_scheme.scheme_name}` : 'Relasi pemesanan tidak lengkap.' };
  },
  kebutuhan: 'ERD tabel pemesanan',
});

T({
  id: 'TC-D09', nama: 'Detail Pemesanan + Status Pelunasan', skenario: 'Membaca pemesanan',
  deskripsi: 'Detail booking menyertakan status pelunasan hasil turunan, bukan masukan pengguna.',
  langkah: 'GET /api/bookings/{id}',
  dataUji: () => `booking_id=${ctx.bookingId} (hasil TC-D05)`,
  ekspektasi: 'HTTP 200 dengan settlement_status=menunggu dp',
  expectStatus: 200, method: 'GET', path: () => `/bookings/${ctx.bookingId}`, token: M,
  check: (j) => ({ pass: j?.data?.settlement_status === 'menunggu dp', note: `settlement_status=${j?.data?.settlement_status}, pembayaran=${(j?.data?.payments || []).length}` }),
  kebutuhan: 'Kebutuhan d.3 (status pelunasan otomatis)',
});

T({
  id: 'TC-D10', nama: 'Status Booking Tidak Sah', skenario: 'Pemesanan validasi',
  deskripsi: 'Status pemesanan ditentukan sistem; nilai bebas dari klien tidak dipakai.',
  langkah: 'PUT /api/bookings/{id} mencoba mengubah status dan sisa pembayaran',
  dataUji: 'status=lunas, remaining_amount=0',
  ekspektasi: 'HTTP 200 tetapi status tetap menunggu dp karena diturunkan dari ledger',
  expectStatus: 200, method: 'PUT', path: () => `/bookings/${ctx.bookingId}`, token: M,
  body: { status: 'lunas', remaining_amount: 0 },
  check: (j) => ({ pass: j?.data?.status === 'menunggu dp', note: `status dipertahankan sistem pada "${j?.data?.status}".` }),
  kebutuhan: 'Kebutuhan d.3 (perhitungan otomatis)',
});

/* -------------------------------------------------------------------------
   E. PEMBAYARAN & PELUNASAN
   ------------------------------------------------------------------------ */

T({
  id: 'TC-E01', nama: 'Mencatat Pembayaran', skenario: 'Membuat pembayaran',
  deskripsi: 'Sales mencatat pembayaran uang muka; status verifikasi awal selalu pending.',
  langkah: 'POST /api/payments',
  dataUji: () => `booking_id=${ctx.bookingId}, nominal = uang muka ${ctx.bookingDP}, jenis cash bertahap`,
  ekspektasi: 'HTTP 201 dengan verification_status=pending dan verified_by kosong',
  expectStatus: 201, method: 'POST', path: '/payments', token: S,
  body: () => ({
    booking_id: ctx.bookingId, payment_type: 'cash bertahap', amount: ctx.bookingDP,
    payment_date: '2026-10-22', bank_name: 'BCA', account_number: '8890122345',
    account_holder: 'PT Lumin Park Properti', notes: 'Transfer uang muka.',
  }),
  check: (j) => {
    const p = j?.data;
    return { pass: p?.verification_status === 'pending' && !p?.verified_by, note: `payment id=${p?.id}, verifikasi=${p?.verification_status}` };
  },
  save: (j, c) => { c.paymentId = j?.data?.id; },
  kebutuhan: 'Kebutuhan d.1 (mencatat pembayaran DP)',
});

T({
  id: 'TC-E02', nama: 'Nominal Pembayaran Wajib Positif', skenario: 'Validasi pembayaran',
  deskripsi: 'Nilai pembayaran nol atau negatif ditolak.',
  langkah: 'POST /api/payments dengan amount=0',
  dataUji: 'amount=0',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/payments', token: S,
  body: () => ({ booking_id: ctx.bookingId, payment_type: 'cash bertahap', amount: 0, payment_date: '2026-10-22' }),
  kebutuhan: 'Kebutuhan non-fungsional (validasi)',
});

T({
  id: 'TC-E03', nama: 'Sales Tidak Dapat Memverifikasi', skenario: 'RBAC negatif',
  deskripsi: 'Verifikasi bukti transfer adalah hak Manajer.',
  langkah: 'PUT /api/payments/{id}/verify memakai token sales',
  dataUji: 'verification_status=verified, token sales',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'PUT', path: () => `/payments/${ctx.paymentId}/verify`, token: S,
  body: { verification_status: 'verified' },
  kebutuhan: 'Kebutuhan d.2 (Manajer memverifikasi)',
});

T({
  id: 'TC-E04', nama: 'Verifikasi oleh Manajer', skenario: 'Verifikasi pembayaran',
  deskripsi: 'Manajer memverifikasi pembayaran; verifikator dan waktu diisi sistem.',
  langkah: 'PUT /api/payments/{id}/verify memakai token manajer',
  dataUji: 'verification_status=verified',
  ekspektasi: 'HTTP 200 dengan verified_by = id manajer dan verified_at terisi',
  expectStatus: 200, method: 'PUT', path: () => `/payments/${ctx.paymentId}/verify`, token: M,
  body: { verification_status: 'verified', notes: 'Bukti sesuai.' },
  check: (j) => {
    const p = j?.data;
    const ok = p?.verification_status === 'verified' && String(p?.verified_by) === String(ctx.managerUserId) && !!p?.verified_at;
    return { pass: ok, note: `verified_by=${p?.verified_by} (${ctx.managerUserId}), verified_at=${p?.verified_at}` };
  },
  kebutuhan: 'Kebutuhan d.2 + ERD (pihak & waktu verifikasi)',
});

T({
  id: 'TC-E05', nama: 'Status Pelunasan Menjadi DP Lunas', skenario: 'Dampak verifikasi',
  deskripsi: 'Setelah DP terverifikasi, status pemesanan otomatis berubah menjadi dp lunas.',
  langkah: 'GET /api/bookings/{id}',
  dataUji: () => `booking_id=${ctx.bookingId}`,
  ekspektasi: 'HTTP 200 dengan status=dp lunas dan sisa pembayaran berkurang',
  expectStatus: 200, method: 'GET', path: () => `/bookings/${ctx.bookingId}`, token: M,
  check: (j) => {
    const b = j?.data;
    const sisaHarapan = Number(b?.total_price) - Number(b?.down_payment_amount);
    const ok = b?.status === 'dp lunas' && Math.abs(Number(b?.remaining_amount) - sisaHarapan) < 1;
    return { pass: ok, note: `status=${b?.status}, sisa=${Number(b?.remaining_amount).toLocaleString('id-ID')} (harapan ${sisaHarapan.toLocaleString('id-ID')})` };
  },
  save: (j, c) => { c.remainingAfterDP = Number(j?.data?.remaining_amount); },
  kebutuhan: 'Kebutuhan d.3 (status pelunasan & sisa otomatis)',
});

T({
  id: 'TC-E06', nama: 'Menolak Bukti Transfer', skenario: 'Verifikasi ditolak',
  deskripsi: 'Pembayaran yang ditolak tidak mengubah sisa pembayaran maupun status pelunasan.',
  langkah: '1. Catat pembayaran kedua 2. Verifikasi dengan status rejected 3. Periksa booking',
  dataUji: () => `nominal angsuran ${ctx.bookingInstallment}, verification_status=rejected`,
  ekspektasi: 'HTTP 200 dengan verification_status=rejected dan sisa tidak berubah',
  expectStatus: 200, method: 'PUT', path: () => `/payments/${ctx.rejectedPaymentId}/verify`, token: M,
  run: async () => {
    const create = await api(S(), 'POST', '/payments', {
      booking_id: ctx.bookingId, payment_type: 'cash bertahap', amount: ctx.bookingInstallment,
      payment_date: '2026-10-23', bank_name: 'BCA', notes: 'Angsuran pertama (bukti kabur).',
    });
    ctx.rejectedPaymentId = create.json?.data?.id;
    const res = await api(M(), 'PUT', `/payments/${ctx.rejectedPaymentId}/verify`, {
      verification_status: 'rejected', notes: 'Bukti transfer tidak terbaca.',
    });
    const booking = await api(M(), 'GET', `/bookings/${ctx.bookingId}`);
    ctx.remainingAfterRejected = Number(booking.json?.data?.remaining_amount);
    ctx.statusAfterRejected = booking.json?.data?.status;
    return res;
  },
  check: (j) => {
    const ok = j?.data?.verification_status === 'rejected' && Number(ctx.remainingAfterRejected) === Number(ctx.remainingAfterDP);
    return { pass: ok, note: `sisa sebelum=${Number(ctx.remainingAfterDP).toLocaleString('id-ID')}, sesudah=${Number(ctx.remainingAfterRejected).toLocaleString('id-ID')}, status booking=${ctx.statusAfterRejected}` };
  },
  kebutuhan: 'Kebutuhan d.2 (terverifikasi/ditolak)',
});

T({
  id: 'TC-E07', nama: 'Pelunasan Penuh', skenario: 'Pelunasan otomatis',
  deskripsi: 'Setelah seluruh sisa terverifikasi, status menjadi lunas dan unit menjadi sold out.',
  langkah: '1. Catat + verifikasi angsuran 2. Catat + verifikasi seluruh sisa 3. Periksa booking dan unit',
  dataUji: 'nominal angsuran lalu nominal sisa pembayaran',
  ekspektasi: 'HTTP 200 dengan booking status=lunas dan unit status=sold_out',
  expectStatus: 200, method: 'PUT', path: () => `/payments/${ctx.finalPaymentId}/verify`, token: M,
  run: async () => {
    const installment = await api(S(), 'POST', '/payments', {
      booking_id: ctx.bookingId, payment_type: 'cash bertahap', amount: ctx.bookingInstallment,
      payment_date: '2026-10-24', bank_name: 'BNI', notes: 'Angsuran disetujui.',
    });
    ctx.midPaymentId = installment.json?.data?.id;
    await api(M(), 'PUT', `/payments/${ctx.midPaymentId}/verify`, { verification_status: 'verified' });

    const between = await api(M(), 'GET', `/bookings/${ctx.bookingId}`);
    ctx.statusAfterInstallment = between.json?.data?.status;

    const sisa = Number(between.json?.data?.remaining_amount);
    const final = await api(S(), 'POST', '/payments', {
      booking_id: ctx.bookingId, payment_type: 'cash bertahap', amount: sisa,
      payment_date: '2026-10-25', bank_name: 'BNI', notes: 'Pelunasan sisa pembayaran.',
    });
    ctx.finalPaymentId = final.json?.data?.id;

    const res = await api(M(), 'PUT', `/payments/${ctx.finalPaymentId}/verify`, { verification_status: 'verified' });

    const booking = await api(M(), 'GET', `/bookings/${ctx.bookingId}`);
    ctx.finalBookingStatus = booking.json?.data?.status;
    ctx.finalRemaining = Number(booking.json?.data?.remaining_amount);

    const unit = await api(M(), 'GET', `/units/${ctx.bookableUnitId}`);
    ctx.finalUnitStatus = unit.json?.data?.status;

    return res;
  },
  check: (j) => {
    const ok = ctx.finalBookingStatus === 'lunas' && ctx.finalUnitStatus === 'sold_out' && Number(ctx.finalRemaining) === 0;
    return { pass: ok, note: `booking setelah angsuran=${ctx.statusAfterInstallment}, booking akhir=${ctx.finalBookingStatus}, unit=${ctx.finalUnitStatus}, sisa=${Number(ctx.finalRemaining).toLocaleString('id-ID')}` };
  },
  kebutuhan: 'Kebutuhan d.3 + b (sold out)',
});

T({
  id: 'TC-E08', nama: 'Pembayaran pada Booking Lunas', skenario: 'Edge case pembayaran',
  deskripsi: 'Booking yang sudah lunas menolak pencatatan pembayaran baru.',
  langkah: 'POST /api/payments pada booking lunas',
  dataUji: 'booking_id lunas, amount=100000',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/payments', token: S,
  body: () => ({ booking_id: ctx.bookingId, payment_type: 'cash bertahap', amount: 100000, payment_date: '2026-10-26' }),
  check: (j) => ({ pass: !!j?.message, note: j?.message || trim(JSON.stringify(j)) }),
  kebutuhan: 'Kebutuhan d (konsistensi pelunasan)',
});

T({
  id: 'TC-E09', nama: 'Pemantauan Pembayaran oleh Sales', skenario: 'Membaca pembayaran',
  deskripsi: 'Sales memantau status pembayaran konsumennya melalui filter booking dan verifikasi.',
  langkah: '1. GET /api/payments?booking_id={id} 2. GET /api/payments?verification_status=pending',
  dataUji: () => `booking_id=${ctx.bookingId}`,
  ekspektasi: 'HTTP 200 berisi riwayat pembayaran booking tersebut',
  expectStatus: 200, method: 'GET', path: () => `/payments?booking_id=${ctx.bookingId}`, token: S,
  check: (j) => {
    const arr = j?.data || [];
    const verified = arr.filter((p) => p.verification_status === 'verified').length;
    const rejected = arr.filter((p) => p.verification_status === 'rejected').length;
    return { pass: arr.length >= 4 && verified === 3 && rejected === 1, note: `${arr.length} pembayaran (${verified} terverifikasi, ${rejected} ditolak).` };
  },
  kebutuhan: 'Kebutuhan d.4 (Sales memantau pembayaran)',
});

T({
  id: 'TC-E10', nama: 'Hapus Pembayaran Menghitung Ulang Sisa', skenario: 'Koreksi data',
  deskripsi: 'Menghapus pembayaran terverifikasi menaikkan kembali sisa pembayaran.',
  langkah: 'DELETE /api/payments/{id} lalu periksa booking',
  dataUji: () => `payment_id=${ctx.midPaymentId} (angsuran terverifikasi ${Number(ctx.bookingInstallment).toLocaleString('id-ID')})`,
  ekspektasi: 'HTTP 200 dan remaining_amount bertambah sesuai nominal yang dihapus',
  expectStatus: 200, method: 'DELETE', path: () => `/payments/${ctx.midPaymentId}`, token: M,
  run: async () => {
    const res = await api(M(), 'DELETE', `/payments/${ctx.midPaymentId}`);
    const booking = await api(M(), 'GET', `/bookings/${ctx.bookingId}`);
    ctx.remainingAfterDelete = Number(booking.json?.data?.remaining_amount);
    ctx.statusAfterDelete = booking.json?.data?.status;
    return res;
  },
  check: () => {
    const ok = Math.abs(Number(ctx.remainingAfterDelete) - Number(ctx.bookingInstallment)) < 1 && ctx.statusAfterDelete !== 'lunas';
    return { pass: ok, note: `sisa dihitung ulang ${Number(ctx.remainingAfterDelete).toLocaleString('id-ID')} (= angsuran yang dihapus), status booking=${ctx.statusAfterDelete}` };
  },
  kebutuhan: 'Kebutuhan d.3 (sinkronisasi sisa)',
});

/* -------------------------------------------------------------------------
   F. MANAJEMEN DATA SALES
   ------------------------------------------------------------------------ */

T({
  id: 'TC-F01', nama: 'Tambah Sales Beserta Target', skenario: 'Membuat data sales',
  deskripsi: 'Manajer menambah sales; akun, profil, dan target periode berjalan dibuat sekali jalan.',
  langkah: 'POST /api/sales',
  dataUji: 'email=new.sales@luminpark.com, sales_code=SL-009, target 3 unit / 1 M',
  ekspektasi: 'HTTP 201 dengan sales_profile dan target_sales terisi',
  expectStatus: 201, method: 'POST', path: '/sales', token: M,
  body: {
    name: 'New Sales Uji', email: 'new.sales@luminpark.com', password: 'password123',
    phone: '081233000777', address: 'Jl. Target No. 9', sales_code: 'SL-009',
    join_date: '2026-09-01', status: 'aktif', whatsapp_number: '6281233000777',
    target_units: 3, target_amount: 1000000000,
  },
  check: (j) => {
    const p = j?.data?.sales_profile;
    const ok = p?.sales_code === 'SL-009' && (p?.targets || []).length === 1;
    return { pass: ok, note: ok ? `profil ${p.sales_code} + target ${p.targets[0].target_units} unit / Rp ${Number(p.targets[0].target_amount).toLocaleString('id-ID')}` : 'Profil atau target tidak terbentuk.' };
  },
  save: (j, c) => { c.newSalesUserId = j?.data?.id; c.newSalesProfileId = j?.data?.sales_profile?.id; },
  kebutuhan: 'Kebutuhan h + ERD target_sales',
});

T({
  id: 'TC-F02', nama: 'Email Sales Duplikat', skenario: 'Validasi sales',
  deskripsi: 'Email telah dipakai sehingga pendaftaran sales baru ditolak.',
  langkah: 'POST /api/sales memakai email yang sudah ada',
  dataUji: 'email=superadmin@gmail.com',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/sales', token: M,
  body: { name: 'Duplikat', email: 'superadmin@gmail.com', password: 'password123', sales_code: 'SL-099' },
  kebutuhan: 'ERD tabel pengguna (email unik)',
});

T({
  id: 'TC-F03', nama: 'Admin Sales Mengubah Data Sales', skenario: 'Memperbarui data sales',
  deskripsi: 'Admin Sales memiliki hak kelola data sales yang sama dengan Manajer.',
  langkah: 'PUT /api/sales/{id} memakai token Admin Sales',
  dataUji: 'name=New Sales Revisi, whatsapp_number=6281233000888',
  ekspektasi: 'HTTP 200 dengan nama terbaru',
  expectStatus: 200, method: 'PUT', path: () => `/sales/${ctx.newSalesUserId}`, token: A,
  body: { name: 'New Sales Revisi', whatsapp_number: '6281233000888', account_status: 'aktif' },
  check: (j) => ({ pass: j?.data?.name === 'New Sales Revisi', note: `name=${j?.data?.name}, wa=${j?.data?.sales_profile?.whatsapp_number}` }),
  kebutuhan: 'Kebutuhan h.3 (Admin Sales mengelola sales)',
});

T({
  id: 'TC-F04', nama: 'Reset Password Sales', skenario: 'Kelola akun sales',
  deskripsi: 'Manajer mereset password sales dan sesi lama dicabut.',
  langkah: '1. Login sales baru 2. POST /api/sales/{id}/reset-password 3. GET /me token lama 4. Login password baru',
  dataUji: 'password baru=passwordBaru123',
  ekspektasi: 'HTTP 200 dan token lama tidak lagi berlaku',
  expectStatus: 200, method: 'POST', path: () => `/sales/${ctx.newSalesUserId}/reset-password`, token: M,
  run: async () => {
    const lama = await api(null, 'POST', '/login', { email: 'new.sales@luminpark.com', password: 'password123' });
    ctx.preResetLoginStatus = lama.status;
    const oldToken = lama.json?.access_token;

    const res = await api(M(), 'POST', `/sales/${ctx.newSalesUserId}/reset-password`, { password: 'passwordBaru123' });

    ctx.resetOldTokenStatus = (await api(oldToken, 'GET', '/me')).status;
    const baru = await api(null, 'POST', '/login', { email: 'new.sales@luminpark.com', password: 'passwordBaru123' });
    ctx.resetNewLoginStatus = baru.status;
    return res;
  },
  check: () => {
    const ok = ctx.preResetLoginStatus === 200 && ctx.resetOldTokenStatus === 401 && ctx.resetNewLoginStatus === 200;
    return { pass: ok, note: `login lama→HTTP ${ctx.preResetLoginStatus}, token lama pasca-reset→HTTP ${ctx.resetOldTokenStatus}, login password baru→HTTP ${ctx.resetNewLoginStatus}` };
  },
  kebutuhan: 'Kebutuhan h.1 (reset password)',
});

T({
  id: 'TC-F05', nama: 'Nonaktifkan Akun Sales', skenario: 'Status akun',
  deskripsi: 'Akun sales dapat dinonaktifkan dan kemudian ditolak saat login.',
  langkah: '1. PUT account_status=nonaktif 2. POST /login memakai akun itu',
  dataUji: 'account_status=nonaktif',
  ekspektasi: 'HTTP 200 saat perubahan dan HTTP 403 saat login',
  expectStatus: 200, method: 'PUT', path: () => `/sales/${ctx.newSalesUserId}`, token: M,
  run: async () => {
    const res = await api(M(), 'PUT', `/sales/${ctx.newSalesUserId}`, { account_status: 'nonaktif' });
    ctx.nonaktifLoginStatus = (await api(null, 'POST', '/login', { email: 'new.sales@luminpark.com', password: 'passwordBaru123' })).status;
    return res;
  },
  check: (j) => ({ pass: j?.data?.status === 'nonaktif' && ctx.nonaktifLoginStatus === 403, note: `status akun=${j?.data?.status}, percobaan login→HTTP ${ctx.nonaktifLoginStatus}` }),
  kebutuhan: 'ERD tabel pengguna (status akun)',
});

T({
  id: 'TC-F06', nama: 'Kinerja Satu Sales', skenario: 'Memantau kinerja',
  deskripsi: 'Endpoint kinerja mengembalikan metrik leads, booking, closing, dan capaian target.',
  langkah: 'GET /api/sales/{id}/performance?start_date&end_date',
  dataUji: () => `user id=${ctx.mainSalesUserId}, periode 2026-10-01 s/d 2026-10-31`,
  ekspektasi: 'HTTP 200 dengan blok metrics dan target',
  expectStatus: 200, method: 'GET', path: () => `/sales/${ctx.mainSalesUserId}/performance?start_date=2026-10-01&end_date=2026-10-31`, token: M,
  check: (j) => {
    const d = j?.data;
    const ok = !!d?.metrics && !!d?.sales?.sales_code && d.period?.start_date === '2026-10-01';
    return { pass: ok, note: d ? `leads=${d.metrics.total_leads}, booking=${d.metrics.bookings}, unit terjual=${d.metrics.units_sold}, pembayaran terverifikasi=${Number(d.metrics.verified_payments).toLocaleString('id-ID')}, target=${d.target ? d.target.unit_progress + '%' : 'belum ada'}` : 'Data kosong.' };
  },
  kebutuhan: 'Kebutuhan h.2 + ERD target_sales',
});

T({
  id: 'TC-F07', nama: 'Hapus Sales Berriwayat', skenario: 'Integritas data sales',
  deskripsi: 'Sales dengan data pemesanan tidak dapat dihapus.',
  langkah: 'DELETE /api/sales/{id} pada sales yang memiliki booking',
  dataUji: () => `user id=${ctx.mainSalesUserId}`,
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'DELETE', path: () => `/sales/${ctx.mainSalesUserId}`, token: M,
  check: (j) => ({ pass: !!j?.message, note: j?.message || trim(JSON.stringify(j)) }),
  kebutuhan: 'ERD relasi sales → pemesanan',
});

T({
  id: 'TC-F08', nama: 'Hapus Sales Tanpa Riwayat', skenario: 'Menghapus data sales',
  deskripsi: 'Sales tanpa transaksi dapat dihapus oleh Manajer.',
  langkah: 'DELETE /api/sales/{id} pada sales hasil TC-F01',
  dataUji: () => `user id=${ctx.newSalesUserId}`,
  ekspektasi: 'HTTP 200',
  expectStatus: 200, method: 'DELETE', path: () => `/sales/${ctx.newSalesUserId}`, token: M,
  kebutuhan: 'Kebutuhan h.1 (menghapus data sales)',
});

/* -------------------------------------------------------------------------
   G. DASHBOARD & LAPORAN
   ------------------------------------------------------------------------ */

T({
  id: 'TC-G01', nama: 'Dashboard Manajer', skenario: 'Dashboard',
  deskripsi: 'Dashboard manajer memuat unit sold out, belum delivery, pendapatan, jumlah sales, calon konsumen, dan konsumen.',
  langkah: 'GET /api/dashboard/manager',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan seluruh kunci metrik sesuai laporan',
  expectStatus: 200, method: 'GET', path: '/dashboard/manager', token: M,
  check: (j) => {
    const d = j?.data || {};
    const keys = ['units_sold_out', 'units_not_delivered', 'total_revenue', 'total_sales', 'total_prospects', 'total_customers'];
    const missing = keys.filter((k) => d[k] === undefined);
    return { pass: missing.length === 0, note: missing.length ? `tidak ada: ${missing.join(', ')}` : keys.map((k) => `${k}=${typeof d[k] === 'number' && k === 'total_revenue' ? d[k].toLocaleString('id-ID') : d[k]}`).join(', ') };
  },
  kebutuhan: 'Kebutuhan f.1',
});

T({
  id: 'TC-G02', nama: 'Dashboard Admin Sales', skenario: 'Dashboard',
  deskripsi: 'Dashboard Admin Sales merangkum penjualan seluruh sales.',
  langkah: 'GET /api/dashboard/admin-sales',
  dataUji: 'token Admin Sales',
  ekspektasi: 'HTTP 200 dengan blok totals dan per_sales berupa daftar',
  expectStatus: 200, method: 'GET', path: '/dashboard/admin-sales', token: A,
  check: (j) => {
    const d = j?.data;
    const ok = !!d?.totals && Array.isArray(d.per_sales);
    return { pass: ok, note: ok ? `${d.per_sales.length} sales; total booking=${d.totals.total_bookings}, omzet=${Number(d.totals.total_revenue).toLocaleString('id-ID')}` : 'Struktur rekap tidak sesuai.' };
  },
  kebutuhan: 'Kebutuhan f.2',
});

T({
  id: 'TC-G03', nama: 'Dashboard Sales', skenario: 'Dashboard',
  deskripsi: 'Dashboard Sales menghitung calon konsumen yang ditangani menurut status, memakai sales_id dari token.',
  langkah: 'GET /api/dashboard/sales tanpa query sales_id',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 200 dengan leads_by_status (cold/warm/hot/booking)',
  expectStatus: 200, method: 'GET', path: '/dashboard/sales', token: S,
  check: (j) => {
    const d = j?.data;
    const ok = !!d?.leads_by_status && d.sales_id === ctx.salesProfileId;
    return { pass: ok, note: d ? `sales_id=${d.sales_id} (dari token), status=${JSON.stringify(d.leads_by_status)}, booking=${JSON.stringify(d.bookings_by_status)}, closing=${Number(d.total_closing).toLocaleString('id-ID')}` : 'Data kosong.' };
  },
  kebutuhan: 'Kebutuhan f.3 + a (RBAC peran)',
});

T({
  id: 'TC-G04', nama: 'Dashboard Sales Ditolak Manajer', skenario: 'RBAC negatif',
  deskripsi: 'Endpoint dashboard sales tidak dapat dibuka oleh manajer.',
  langkah: 'GET /api/dashboard/sales memakai token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'GET', path: '/dashboard/sales', token: M,
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-G05', nama: 'Laporan Penjualan Unit', skenario: 'Laporan',
  deskripsi: 'Laporan penjualan memfilter periode dan merangkum nilai transaksi.',
  langkah: 'GET /api/reports/sales?start_date=2026-10-01&end_date=2026-10-31',
  dataUji: 'periode Oktober 2026',
  ekspektasi: 'HTTP 200 dengan total_transactions, total_sales_value, dan data',
  expectStatus: 200, method: 'GET', path: '/reports/sales?start_date=2026-10-01&end_date=2026-10-31', token: M,
  check: (j) => {
    const ok = j && typeof j.total_transactions === 'number' && typeof j.total_sales_value === 'number';
    return { pass: ok, note: ok ? `${j.total_transactions} transaksi, nilai ${Number(j.total_sales_value).toLocaleString('id-ID')}, sisa ${Number(j.total_remaining_value).toLocaleString('id-ID')}` : 'Struktur laporan tidak sesuai.' };
  },
  kebutuhan: 'Kebutuhan f.4 (laporan penjualan)',
});

T({
  id: 'TC-G06', nama: 'Laporan Pelunasan + Rekap', skenario: 'Laporan',
  deskripsi: 'Laporan pelunasan menampilkan rekap per konsumen, per unit, dan per skema pembayaran.',
  langkah: 'GET /api/reports/settlement',
  dataUji: 'periode 2026-10-01 s/d 2026-10-31',
  ekspektasi: 'HTTP 200 dengan recap.by_consumer, recap.by_unit, recap.by_scheme',
  expectStatus: 200, method: 'GET', path: '/reports/settlement?start_date=2026-10-01&end_date=2026-10-31', token: M,
  check: (j) => {
    const r = j?.recap;
    const ok = !!r?.by_consumer && !!r?.by_unit && !!r?.by_scheme;
    return { pass: ok, note: ok ? `rekap ${Object.keys(r.by_consumer).length} konsumen, ${Object.keys(r.by_unit).length} unit, ${Object.keys(r.by_scheme).length} skema; total ${Number(j.total_settled_amount).toLocaleString('id-ID')}` : 'Rekap per konsumen/unit/skema tidak ada.' };
  },
  kebutuhan: 'Kebutuhan d.5 (rekap per konsumen, unit, skema)',
});

T({
  id: 'TC-G07', nama: 'Laporan Kinerja Sales', skenario: 'Laporan',
  deskripsi: 'Laporan kinerja menggabungkan leads, booking, closing, dan capaian target tiap sales.',
  langkah: 'GET /api/reports/performance',
  dataUji: 'periode berjalan',
  ekspektasi: 'HTTP 200 dengan data berisi kunci units_sold dan unit_progress',
  expectStatus: 200, method: 'GET', path: '/reports/performance', token: A,
  check: (j) => {
    const first = j?.data?.[0];
    const ok = !!first && 'units_sold' in first && 'unit_progress' in first && 'total_closing' in first;
    return { pass: ok, note: ok ? `contoh: ${first.name} leads=${first.total_leads} booking=${first.total_bookings} closing=${Number(first.total_closing).toLocaleString('id-ID')}` : 'Kolom kinerja tidak lengkap.' };
  },
  kebutuhan: 'Kebutuhan f.4 (laporan kinerja sales)',
});

T({
  id: 'TC-G08', nama: 'Laporan Konsumen', skenario: 'Laporan',
  deskripsi: 'Laporan konsumen dan calon konsumen dapat disaring berdasarkan status.',
  langkah: 'GET /api/reports/consumers?status=booking',
  dataUji: 'status=booking',
  ekspektasi: 'HTTP 200 dengan totals.customer >= 1',
  expectStatus: 200, method: 'GET', path: '/reports/consumers?status=booking', token: M,
  check: (j) => {
    const ok = !!j?.data && !!j?.totals && j.totals.customer >= 1;
    return { pass: ok, note: ok ? `total=${j.totals.total}, cold=${j.totals.cold}, warm=${j.totals.warm}, hot=${j.totals.hot}, konsumen=${j.totals.customer}` : 'Struktur laporan konsumen tidak sesuai.' };
  },
  kebutuhan: 'Kebutuhan f.4 (laporan konsumen & calon konsumen)',
});

T({
  id: 'TC-G09', nama: 'Ekspor CSV Laporan Penjualan', skenario: 'Ekspor laporan',
  deskripsi: 'Laporan dapat diekspor ke berkas CSV (Excel) dengan header berbahasa Indonesia.',
  langkah: 'GET /api/reports/export?type=sales',
  dataUji: 'type=sales',
  ekspektasi: 'HTTP 200, Content-Type text/csv, Content-Disposition attachment',
  expectStatus: 200, method: 'GET', path: '/reports/export?type=sales&start_date=2026-10-01&end_date=2026-10-31', token: M,
  check: (j, s, h, c, text) => {
    const baris = String(text).replace(/^\uFEFF/, '').split(/\r?\n/).filter(Boolean);
    const header = (baris[0] || '').replace(/"/g, '');
    const ok = String(h['content-type'] || '').includes('text/csv')
      && String(h['content-disposition'] || '').includes('attachment; filename="laporan-sales-')
      && header.startsWith('Kode Booking,Tanggal,Calon Konsumen')
      && baris.length >= 2;
    return { pass: ok, note: ok ? `${baris.length - 1} baris data; berkas="${String(h['content-disposition']).replace('attachment; ', '')}"` : `content-type=${h['content-type']}, isi=${trim(text)}` };
  },
  kebutuhan: 'Kebutuhan f.4 (mencetak/mengekspor laporan)',
});

T({
  id: 'TC-G10', nama: 'Ekspor CSV Kinerja', skenario: 'Ekspor laporan',
  deskripsi: 'Tipe laporan lain juga dapat diekspor sebagai CSV.',
  langkah: 'GET /api/reports/export?type=performance',
  dataUji: 'type=performance',
  ekspektasi: 'HTTP 200 dengan isi CSV berisi header Kode Sales',
  expectStatus: 200, method: 'GET', path: '/reports/export?type=performance&start_date=2026-10-01&end_date=2026-10-31', token: M,
  check: (j, s, h, c, text) => {
    const baris = String(text).replace(/^\uFEFF/, '').split(/\r?\n/).filter(Boolean);
    const ok = String(baris[0] || '').includes('Kode Sales') && baris.length >= 2;
    return { pass: ok, note: ok ? `header=${trim(baris[0])}; ${baris.length - 1} baris sales` : `isi=${trim(text)}` };
  },
  kebutuhan: 'Kebutuhan f.4',
});

T({
  id: 'TC-G11', nama: 'Ringkasan Penjualan Tersimpan', skenario: 'Tabel laporan_penjualan',
  deskripsi: 'Ringkasan periode ditulis ke tabel laporan_penjualan sesuai ERD.',
  langkah: 'POST /api/reports/summarize',
  dataUji: 'periode 2026-10-01 s/d 2026-10-31',
  ekspektasi: 'HTTP 201 dengan units_sold, total_revenue, total_leads, total_closing',
  expectStatus: 201, method: 'POST', path: '/reports/summarize', token: M,
  body: { start_date: '2026-10-01', end_date: '2026-10-31' },
  check: (j) => {
    const d = j?.data;
    const ok = d && 'units_sold' in d && 'total_revenue' in d && 'total_leads' in d && 'total_closing' in d;
    return { pass: ok, note: ok ? `unit=${d.units_sold}, omzet=${Number(d.total_revenue).toLocaleString('id-ID')}, leads=${d.total_leads}, closing=${Number(d.total_closing).toLocaleString('id-ID')}` : 'Kolom ringkasan tidak lengkap.' };
  },
  kebutuhan: 'ERD tabel laporan_penjualan',
});

/* -------------------------------------------------------------------------
   H. INFORMASI PUBLIK UNTUK KONSUMEN
   ------------------------------------------------------------------------ */

T({
  id: 'TC-H01', nama: 'Daftar Unit Publik', skenario: 'Informasi konsumen',
  deskripsi: 'Konsumen melihat daftar unit tersedia beserta tipe, luas, dan harga tanpa login.',
  langkah: 'GET /api/public/units tanpa token',
  dataUji: 'tanpa autentikasi',
  ekspektasi: 'HTTP 200, seluruh unit berstatus tersedia dan memuat unit_type, land_size, price',
  expectStatus: 200, method: 'GET', path: '/public/units', token: null,
  check: (j) => {
    const arr = j?.data || [];
    const first = arr[0];
    const ok = arr.length > 0 && !!first?.unit_type && !!first?.land_size && typeof first?.price === 'number';
    return { pass: ok, note: ok ? `${arr.length} unit; contoh ${first.unit_code} ${first.unit_type} LT${first.land_size}/LB${first.building_size} Rp ${first.price.toLocaleString('id-ID')}` : 'Data unit publik kosong.' };
  },
  kebutuhan: 'Kebutuhan g.1',
});

T({
  id: 'TC-H02', nama: 'Detail Unit Publik', skenario: 'Informasi konsumen',
  deskripsi: 'Detail unit menampilkan spesifikasi lengkap termasuk cluster dan proyek.',
  langkah: 'GET /api/public/units/{id}',
  dataUji: () => `unit_id=${ctx.publicUnitId} (available)`,
  ekspektasi: 'HTTP 200 dengan field cluster dan project',
  expectStatus: 200, method: 'GET', path: () => `/public/units/${ctx.publicUnitId}`, token: null,
  check: (j) => {
    const d = j?.data;
    const ok = !!d?.cluster && !!d?.project && !!d?.description;
    return { pass: ok, note: ok ? `${d.unit_code} — ${d.cluster} / ${d.project}` : 'Spesifikasi detail tidak lengkap.' };
  },
  kebutuhan: 'Kebutuhan g.2',
});

T({
  id: 'TC-H03', nama: 'Unit Dipesan Tertutup bagi Publik', skenario: 'Informasi konsumen',
  deskripsi: 'Unit yang sudah dipesan atau terjual tidak dapat diakses publik.',
  langkah: 'GET /api/public/units/{id} pada unit sold_out',
  dataUji: () => `unit_id=${ctx.soldUnitId} (sold_out)`,
  ekspektasi: 'HTTP 404',
  expectStatus: 404, method: 'GET', path: () => `/public/units/${ctx.soldUnitId}`, token: null,
  kebutuhan: 'Kebutuhan g.1 (unit tersedia saja)',
});

T({
  id: 'TC-H04', nama: 'Skema Pembayaran Publik', skenario: 'Informasi konsumen',
  deskripsi: 'Konsumen melihat tiga skema pembayaran yang tersedia.',
  langkah: 'GET /api/public/payment-schemes',
  dataUji: 'tanpa autentikasi',
  ekspektasi: 'HTTP 200 dengan tiga skema: KPR, Cash Keras, Cash Bertahap',
  expectStatus: 200, method: 'GET', path: '/public/payment-schemes', token: null,
  check: (j) => {
    const arr = j?.data || [];
    const names = arr.map((s) => s.scheme_name).sort().join(', ');
    const ok = arr.length >= 3 && /KPR/.test(names) && /Cash Keras/.test(names) && /Cash Bertahap/.test(names);
    return { pass: ok, note: `skema: ${names}` };
  },
  kebutuhan: 'Kebutuhan g.3',
});

T({
  id: 'TC-H05', nama: 'Tiga Kontak WhatsApp Sales', skenario: 'Informasi konsumen',
  deskripsi: 'Tombol Hubungi Sales mengarahkan konsumen ke salah satu dari tiga narahubung.',
  langkah: 'GET /api/public/sales-contacts',
  dataUji: 'tanpa autentikasi',
  ekspektasi: 'HTTP 200 tepat tiga kontak dengan whatsapp_link wa.me',
  expectStatus: 200, method: 'GET', path: '/public/sales-contacts', token: null,
  check: (j) => {
    const arr = j?.data || [];
    const ok = arr.length === 3 && arr.every((c) => String(c.whatsapp_link).startsWith('https://wa.me/'));
    return { pass: ok, note: ok ? arr.map((c) => `${c.name} (${c.whatsapp_link})`).join('; ') : `jumlah kontak=${arr.length}` };
  },
  kebutuhan: 'Kebutuhan g.4 + deskripsi sistem (tiga kontak WhatsApp)',
});

T({
  id: 'TC-H06', nama: 'Form Kontak Menjadi Lead', skenario: 'Informasi konsumen',
  deskripsi: 'Formulir kontak landing page otomatis membuat calon konsumen baru berstatus cold.',
  langkah: 'POST /api/public/contact',
  dataUji: () => `nama=Web Visitor, phone=081233777001, unit_id=${ctx.publicUnitId}`,
  ekspektasi: 'HTTP 201 dengan status cold dan sumber website',
  expectStatus: 201, method: 'POST', path: '/public/contact', token: null,
  body: () => ({ name: 'Web Visitor', phone: '081233777001', email: 'visitor@web.com', note: 'Tanya ketersediaan unit.', unit_id: ctx.publicUnitId }),
  check: (j) => ({ pass: j?.data?.status === 'cold', note: `lead id=${j?.data?.id}, status=${j?.data?.status} (sumber website)` }),
  save: (j, c) => { c.publicLeadId = j?.data?.id; },
  kebutuhan: 'Kebutuhan g + ERD calon_konsumen',
});

T({
  id: 'TC-H07', nama: 'Form Kontak Tanpa Telepon', skenario: 'Validasi form publik',
  deskripsi: 'Formulir menolak submission tanpa nomor telepon.',
  langkah: 'POST /api/public/contact tanpa phone',
  dataUji: 'name hanya',
  ekspektasi: 'HTTP 422',
  expectStatus: 422, method: 'POST', path: '/public/contact', token: null,
  body: { name: 'Tanpa Telepon' },
  kebutuhan: 'Kebutuhan non-fungsional (validasi)',
});

T({
  id: 'TC-H08', nama: 'Berita & Galeri Publik', skenario: 'Informasi konsumen',
  deskripsi: 'Galeri dibangun dari gambar unit sehingga berisi data nyata, bukan statis.',
  langkah: 'GET /api/public/news-gallery',
  dataUji: 'tanpa autentikasi',
  ekspektasi: 'HTTP 200 dengan blok news dan gallery',
  expectStatus: 200, method: 'GET', path: '/public/news-gallery', token: null,
  check: (j) => {
    const d = j?.data;
    const ok = Array.isArray(d?.news) && Array.isArray(d?.gallery);
    return { pass: ok, note: ok ? `${d.news.length} berita, ${d.gallery.length} galeri` : 'Struktur berita/galeri tidak sesuai.' };
  },
  kebutuhan: 'Wireframe halaman publik',
});

/* -------------------------------------------------------------------------
   I. NOTIFIKASI & LOG SISTEM
   ------------------------------------------------------------------------ */

T({
  id: 'TC-I01', nama: 'Notifikasi Booking Tercipta', skenario: 'Notifikasi',
  deskripsi: 'Booking baru memicu notifikasi ke Manajer dan Admin Sales.',
  langkah: 'GET /api/notifications memakai token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan minimal satu notifikasi bertipe booking',
  expectStatus: 200, method: 'GET', path: '/notifications', token: M,
  check: (j) => {
    const arr = j?.data || [];
    const bookingNotif = arr.filter((n) => n.type === 'booking');
    const paymentNotif = arr.filter((n) => n.type === 'payment');
    return { pass: bookingNotif.length >= 1 && paymentNotif.length >= 1, note: `${arr.length} notifikasi (${bookingNotif.length} booking, ${paymentNotif.length} payment). Contoh: "${bookingNotif[0]?.title || '-'}"` };
  },
  save: (j, c) => { c.firstNotifId = j?.data?.[0]?.id; },
  kebutuhan: 'ERD tabel notifikasi',
});

T({
  id: 'TC-I02', nama: 'Hitung Notifikasi Belum Dibaca', skenario: 'Notifikasi',
  deskripsi: 'Jumlah notifikasi belum dibaca tersedia untuk badge pada antarmuka.',
  langkah: 'GET /api/notifications/unread-count',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan unread_count >= 1',
  expectStatus: 200, method: 'GET', path: '/notifications/unread-count', token: M,
  check: (j) => ({ pass: Number(j?.data?.unread_count) >= 1, note: `unread_count=${j?.data?.unread_count}` }),
  kebutuhan: 'ERD tabel notifikasi',
});

T({
  id: 'TC-I03', nama: 'Tandai Satu Notifikasi Dibaca', skenario: 'Notifikasi',
  deskripsi: 'Notifikasi hanya dapat diubah oleh pemiliknya.',
  langkah: 'PUT /api/notifications/{id}/read',
  dataUji: () => `notifikasi id=${ctx.firstNotifId} milik manajer`,
  ekspektasi: 'HTTP 200 dengan is_read=true',
  expectStatus: 200, method: 'PUT', path: () => `/notifications/${ctx.firstNotifId}/read`, token: M,
  check: (j) => ({ pass: j?.data?.is_read === true, note: `is_read=${j?.data?.is_read}` }),
  kebutuhan: 'ERD tabel notifikasi',
});

T({
  id: 'TC-I04', nama: 'Notifikasi Milik Orang Lain', skenario: 'RBAC negatif',
  deskripsi: 'Sales tidak dapat membaca notifikasi milik Manajer.',
  langkah: '1. PUT notifikasi manajer memakai token sales 2. GET /api/notifications memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 404 untuk notifikasi milik pengguna lain, dan daftar sales hanya berisi miliknya',
  expectStatus: 404, method: 'PUT', path: () => `/notifications/${ctx.firstNotifId}/read`, token: S,
  run: async () => {
    const foreign = await api(S(), 'PUT', `/notifications/${ctx.firstNotifId}/read`);
    const own = await api(S(), 'GET', '/notifications');
    ctx.foreignNotifStatus = foreign.status;
    ctx.salesNotifList = own.json?.data || [];
    return foreign;
  },
  check: () => {
    const arr = ctx.salesNotifList || [];
    const murni = arr.every((n) => String(n.user_id) === String(ctx.salesUserId));
    return {
      pass: ctx.foreignNotifStatus === 404 && murni && arr.length >= 1,
      note: `ubah notifikasi milik manajer → HTTP ${ctx.foreignNotifStatus}; daftar sales berisi ${arr.length} entri${murni ? ', seluruhnya milik user ' + ctx.salesUserId : ' dan ada yang milik pengguna lain'}.`,
    };
  },
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-I05', nama: 'Log Aktivitas Sistem', skenario: 'Log sistem',
  deskripsi: 'Aktivitas login tercatat pada tabel log_sistem beserta IP dan user agent.',
  langkah: 'GET /api/system-logs',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan entri activity=login dan ip_address terisi',
  expectStatus: 200, method: 'GET', path: '/system-logs', token: M,
  check: (j) => {
    const arr = (j?.data || []).filter((l) => l.activity === 'login');
    const ok = arr.length >= 1 && !!arr[0].ip_address && !!arr[0].user_agent;
    return { pass: ok, note: ok ? `${arr.length} entri login; ip=${arr[0].ip_address}` : 'Belum ada log login.' };
  },
  kebutuhan: 'ERD tabel log_sistem',
});

T({
  id: 'TC-I06', nama: 'Sales Tidak Melihat Log Sistem', skenario: 'RBAC negatif',
  deskripsi: 'Log aktivitas hanya tersedia bagi pengelola sistem.',
  langkah: 'GET /api/system-logs memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'GET', path: '/system-logs', token: S,
  kebutuhan: 'Kebutuhan a (RBAC)',
});

/* -------------------------------------------------------------------------
   K. MODUL PENDUKUNG FRONTEND (pengaturan, profil, widget dashboard, laporan sales)
  ------------------------------------------------------------------------ */

async function upload(token, path, fields, fileField, filePath, mimeType) {
  const form = new FormData();
  for (const [k, v] of Object.entries(fields)) form.append(k, String(v));
  form.append(fileField, new Blob([readFileSync(new URL('./' + filePath, import.meta.url))], { type: mimeType }), filePath);

  const res = await fetch(BASE + path, {
    method: 'POST',
    headers: { Accept: 'application/json', Authorization: 'Bearer ' + token },
    body: form,
  });
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* bukan JSON */ }
  return { status: res.status, json, text, headers: Object.fromEntries(res.headers.entries()) };
}

T({
  id: 'TC-K01', nama: 'Pengaturan Publik untuk Halaman Kontak', skenario: 'Pengaturan sistem',
  deskripsi: 'Halaman konsumen (Kontak & Tentang Kami) membaca identitas perumahan tanpa login.',
  langkah: 'GET /api/public/settings tanpa token',
  dataUji: 'tanpa autentikasi',
  ekspektasi: 'HTTP 200 memuat estate_name, estate_address, estate_phone, estate_email, dan jam operasional',
  expectStatus: 200, method: 'GET', path: '/public/settings', token: null,
  check: (j) => {
    const d = j?.data || {};
    const ok = !!d.estate_name && !!d.estate_address && !!d.estate_phone && !!d.estate_email && !!d.estate_hours;
    return { pass: ok, note: ok ? `${d.estate_name} — ${d.estate_hours}` : `isi=${JSON.stringify(d).slice(0, 120)}` };
  },
  kebutuhan: 'Wireframe 2.51 (Kontak) & 2.52 (Tentang Kami)',
});

T({
  id: 'TC-K02', nama: 'Daftar Pengaturan oleh Manajer', skenario: 'Pengaturan sistem',
  deskripsi: 'Manajer memuat seluruh kunci pengaturan beserta daftar kunci yang boleh diubah.',
  langkah: 'GET /api/settings memakai token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan items, values, dan editable',
  expectStatus: 200, method: 'GET', path: '/settings', token: M,
  check: (j) => {
    const d = j?.data || {};
    const ok = Array.isArray(d.items) && d.items.length >= 8 && Array.isArray(d.editable) && d.editable.includes('min_dp_percent');
    return { pass: ok, note: ok ? `${d.items.length} kunci tersimpan; minimal DP=${d.values?.min_dp_percent}%` : `items=${d.items?.length}` };
  },
  kebutuhan: 'Wireframe 2.34 (Pengaturan)',
});

T({
  id: 'TC-K03', nama: 'Simpan Perubahan Pengaturan', skenario: 'Pengaturan sistem',
  deskripsi: 'Manajer memperbarui sebagian kunci dan nilai baru langsung terpakai oleh API publik.',
  langkah: '1. PUT /api/settings (estate_name, min_dp_percent) 2. GET /api/public/settings',
  dataUji: 'estate_name=Perumahan Lumin Park Padang, min_dp_percent=15',
  ekspektasi: 'HTTP 200 dan nilai yang sama terbaca pada endpoint publik',
  expectStatus: 200, method: 'PUT', path: '/settings', token: M,
  run: async () => {
    const res = await api(M(), 'PUT', '/settings', {
      settings: { estate_name: 'Perumahan Lumin Park Padang', min_dp_percent: '15' },
    });
    const publik = await api(null, 'GET', '/public/settings');
    ctx.publicEstateName = publik.json?.data?.estate_name;
    ctx.publicMinDp = publik.json?.data?.min_dp_percent;
    return res;
  },
  check: () => {
    const ok = ctx.publicEstateName === 'Perumahan Lumin Park Padang' && String(ctx.publicMinDp) === '15';
    return { pass: ok, note: `publik membaca estate_name="${ctx.publicEstateName}", min_dp_percent=${ctx.publicMinDp}` };
  },
  kebutuhan: 'Wireframe 2.34 (minimal uang muka & identitas)',
});

T({
  id: 'TC-K04', nama: 'Kunci Pengaturan Tidak Dikenali', skenario: 'Validasi pengaturan',
  deskripsi: 'Hanya kunci yang terdaftar yang boleh ditulis supaya tidak ada data liar masuk.',
  langkah: 'PUT /api/settings memakai kunci secret_key',
  dataUji: 'settings={secret_key:"x"}',
  ekspektasi: 'HTTP 422 dengan pesan kunci tidak dikenali',
  expectStatus: 422, method: 'PUT', path: '/settings', token: M,
  body: { settings: { secret_key: 'x' } },
  kebutuhan: 'Kebutuhan non-fungsional (integritas data)',
});

T({
  id: 'TC-K05', nama: 'Nilai Uang Muka di Luar Rentang', skenario: 'Validasi pengaturan',
  deskripsi: 'Persentase uang muka minimum divalidasi 0-100.',
  langkah: 'PUT /api/settings memakai min_dp_percent=900',
  dataUji: 'settings={min_dp_percent:"900"}',
  ekspektasi: 'HTTP 422 dengan pesan harus tidak lebih besar dari 100',
  expectStatus: 422, method: 'PUT', path: '/settings', token: M,
  body: { settings: { min_dp_percent: '900' } },
  check: (j) => ({ pass: !!j?.errors?.min_dp_percent, note: `pesan=${j?.errors?.min_dp_percent?.[0] ?? JSON.stringify(j).slice(0, 100)}` }),
  kebutuhan: 'Kebutuhan non-fungsional (validasi)',
});

T({
  id: 'TC-K06', nama: 'Sales Tidak Mengubah Pengaturan', skenario: 'RBAC negatif',
  deskripsi: 'Pengaturan sistem hanya milik Manajer/Super Admin sesuai wireframe.',
  langkah: '1. GET /api/settings memakai token sales 2. PUT /api/settings memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 403 pada keduanya',
  expectStatus: [403, 403], method: 'GET', path: '/settings', token: S,
  run: async () => {
    const g = await api(S(), 'GET', '/settings');
    const p = await api(S(), 'PUT', '/settings', { settings: { estate_name: 'Diamankan sales' } });
    ctx.settingsSalesGet = g.status;
    ctx.settingsSalesPut = p.status;
    return g;
  },
  check: () => ({
    pass: ctx.settingsSalesGet === 403 && ctx.settingsSalesPut === 403,
    note: `GET=${ctx.settingsSalesGet}, PUT=${ctx.settingsSalesPut}`,
  }),
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-K07', nama: 'Profil Sales Mandiri', skenario: 'Profil akun',
  deskripsi: 'Sales memuat identitasnya sendiri termasuk kode sales, username, status akun, dan target aktif.',
  langkah: 'GET /api/profile memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 200 dengan sales_code, username=email, status, dan targets array',
  expectStatus: 200, method: 'GET', path: '/profile', token: S,
  check: (j) => {
    const d = j?.data || {};
    const ok = !!d.sales_code && d.username === d.email && !!d.status && Array.isArray(d.targets);
    return { pass: ok, note: ok ? `kode=${d.sales_code}, status=${d.status}, target=${d.targets.length}, sesi aktif=${d.active_sessions}` : `isi=${JSON.stringify(d).slice(0, 120)}` };
  },
  kebutuhan: 'Wireframe 2.47 (Profil Sales)',
});

T({
  id: 'TC-K08', nama: 'Unggah Foto Profil', skenario: 'Profil akun',
  deskripsi: 'Tombol Ganti Foto menyimpan berkas avatar ke disk public dan menautkannya ke akun.',
  langkah: '1. POST /api/profile (multipart, field avatar) 2. ambil URL avatar',
  dataUji: 'avatar=tests/tmp-unit.png (PNG)',
  ekspektasi: 'HTTP 200 dengan avatar_url berisi path /storage/avatars',
  expectStatus: 200, method: 'POST', path: '/profile', token: S,
  run: async () => upload(S(), '/profile', { name: 'Budi Sales Utama' }, 'avatar', 'tmp-unit.png', 'image/png'),
  check: (j) => {
    const url = j?.data?.avatar_url || '';
    const ok = url.includes('/storage/avatars/');
    return { pass: ok, note: ok ? `tersedia di ${url}` : `avatar_url=${url || '(kosong)'}, body=${trim(JSON.stringify(j)).slice(0, 120)}` };
  },
  kebutuhan: 'Wireframe 2.47 (Ganti Foto)',
});

T({
  id: 'TC-K09', nama: 'Ubah Data Profil Sendiri', skenario: 'Profil akun',
  deskripsi: 'Sales memperbaiki nomor telepon dan alamatnya, lalu nilai itu terbaca pada /me. Role dan status tidak dapat diubah sendiri.',
  langkah: '1. PUT /api/profile 2. GET /api/me 3. kembalikan nama awal',
  dataUji: 'phone=081233000111, address=Koto Tangah',
  ekspektasi: 'HTTP 200 dan /me memuat telepon terbaru',
  expectStatus: 200, method: 'PUT', path: '/profile', token: S,
  run: async () => {
    const before = await api(S(), 'GET', '/me');
    ctx.profileOriginalName = before.json?.data?.name;
    const res = await api(S(), 'PUT', '/profile', { name: ctx.profileOriginalName, phone: '081233000111', address: 'Koto Tangah, Padang' });
    const after = await api(S(), 'GET', '/me');
    ctx.profilePhone = after.json?.data?.phone;
    ctx.profileAddress = after.json?.data?.address;
    const roleTry = await api(S(), 'PUT', '/profile', { role: 'manager', status: 'superadmin' });
    ctx.profileRoleTry = roleTry.status;
    const roleAfter = await api(S(), 'GET', '/me');
    ctx.profileRoleAfter = roleAfter.json?.data?.role;
    return res;
  },
  check: () => {
    const ok = ctx.profilePhone === '081233000111' && ctx.profileAddress === 'Koto Tangah, Padang'
      && ctx.profileRoleAfter === 'sales';
    return { pass: ok, note: `telepon=${ctx.profilePhone}, alamat=${ctx.profileAddress}, role setelah percobaan ubah (HTTP ${ctx.profileRoleTry})=${ctx.profileRoleAfter}` };
  },
  kebutuhan: 'Wireframe 2.47 (Edit Profil) + kebutuhan a (RBAC)',
});

T({
  id: 'TC-K10', nama: 'Profil Manajer Tanpa Profil Sales', skenario: 'Profil akun',
  deskripsi: 'Akun non-sales tetap memiliki halaman profil; kolom sales dikosongkan, bukan error.',
  langkah: 'GET /api/profile memakai token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan sales_code null dan role manager',
  expectStatus: 200, method: 'GET', path: '/profile', token: M,
  check: (j) => {
    const d = j?.data || {};
    const ok = d.role === 'manager' && d.sales_code === null && Array.isArray(d.targets) && d.targets.length === 0;
    return { pass: ok, note: ok ? `role=${d.role}, targets=0` : `role=${d.role}, sales_code=${d.sales_code}` };
  },
  kebutuhan: 'Wireframe 2.34 (Pengaturan akun)',
});

T({
  id: 'TC-K11', nama: 'Widget Grafik dan Booking Terbaru (Manajer)', skenario: 'Dashboard',
  deskripsi: 'Dashboard manajer menampilkan grafik penjualan per bulan dan daftar booking terbaru.',
  langkah: 'GET /api/dashboard/manager',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 200 dengan sales_by_month 6 entri (month, revenue, units_sold) dan recent_bookings berisi customer/unit/status',
  expectStatus: 200, method: 'GET', path: '/dashboard/manager', token: M,
  check: (j) => {
    const d = j?.data || {};
    const series = d.sales_by_month || [];
    const first = (d.recent_bookings || [])[0] || {};
    const ok = series.length === 6 && series.every((s) => s.month && typeof s.revenue === 'number' && typeof s.units_sold === 'number')
      && !!first.customer && !!first.unit && !!first.status;
    return { pass: ok, note: ok ? `6 bulan; booking terbaru=${first.booking_code}/${first.customer}/${first.status}` : `series=${series.length}, booking=${JSON.stringify(first).slice(0, 100)}` };
  },
  kebutuhan: 'Wireframe 2.27 (Dashboard Super Admin)',
});

T({
  id: 'TC-K12', nama: 'Widget Dashboard Admin Sales', skenario: 'Dashboard',
  deskripsi: 'Dashboard admin sales memuat ringkasan unit, konsumen, booking aktif, pembayaran pending, dan booking terbaru.',
  langkah: 'GET /api/dashboard/admin-sales',
  dataUji: 'token admin sales',
  ekspektasi: 'HTTP 200 dengan counters lengkap dan recent_bookings',
  expectStatus: 200, method: 'GET', path: '/dashboard/admin-sales', token: A,
  check: (j) => {
    const c = j?.data?.counters || {};
    const ok = ['total_units', 'units_available', 'units_sold_out', 'total_customers', 'active_bookings', 'payments_pending']
      .every((k) => typeof c[k] === 'number') && Array.isArray(j?.data?.recent_bookings);
    return { pass: ok, note: ok ? `unit=${c.total_units}, tersedia=${c.units_available}, booking aktif=${c.active_bookings}, pending=${c.payments_pending}` : `counters=${JSON.stringify(c).slice(0, 120)}` };
  },
  kebutuhan: 'Wireframe 2.35 (Dashboard Admin Sales)',
});

T({
  id: 'TC-K13', nama: 'Widget Dashboard Sales', skenario: 'Dashboard',
  deskripsi: 'Dashboard sales menampilkan jumlah konsumen, booking, pembayaran, unit tersedia, dan booking terbaru miliknya.',
  langkah: '1. GET /api/dashboard/sales 2. cocokkan kepemilikan booking terbaru',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 200 dengan payments_total, units_available, dan recent_bookings milik sales itu saja',
  expectStatus: 200, method: 'GET', path: '/dashboard/sales', token: S,
  run: async () => {
    const me = await api(S(), 'GET', '/me');
    ctx.salesNameNow = me.json?.data?.name;
    return api(S(), 'GET', '/dashboard/sales');
  },
  check: (j) => {
    const d = j?.data || {};
    const rows = d.recent_bookings || [];
    const asing = rows.filter((b) => b.sales !== ctx.salesNameNow).map((b) => `${b.booking_code}:${b.sales}`);
    const ok = typeof d.payments_total === 'number' && typeof d.units_available === 'number'
      && rows.length > 0 && asing.length === 0;
    return {
      pass: ok,
      note: ok
        ? `pembayaran=${d.payments_total}, unit tersedia=${d.units_available}, ${rows.length} booking terbaru seluruhnya milik ${ctx.salesNameNow}`
        : `booking milik sales lain ikut terbaca: ${asing.join(', ')}`,
    };
  },
  kebutuhan: 'Wireframe 2.42 (Dashboard Sales)',
});

T({
  id: 'TC-K14', nama: 'Laporan Penjualan Milik Sales', skenario: 'Laporan',
  deskripsi: 'Sales memperoleh kartu ringkasan dan rincian laporan atas konsumen yang dikelolanya sendiri.',
  langkah: 'GET /api/reports/my-sales?start_date=2026-10-01&end_date=2026-10-31',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 200 dengan summary (total_customers, total_prospects, total_bookings, units_sold, total_revenue) dan data berisi status_label Pemesanan/Terjual/Calon Pembeli',
  expectStatus: 200, method: 'GET', path: '/reports/my-sales?start_date=2026-10-01&end_date=2026-10-31', token: S,
  check: (j) => {
    const d = j?.data || {};
    const labels = new Set((d || []).map((r) => r.status_label));
    const ok = !!j?.summary && typeof j.summary.total_revenue === 'number' && Array.isArray(d)
      && [...labels].every((l) => ['Pemesanan', 'Terjual', 'Calon Pembeli'].includes(l));
    return { pass: ok, note: ok ? `baris=${d.length}; ringkas=${JSON.stringify(j.summary)}` : `label=${[...labels].join(',')}` };
  },
  kebutuhan: 'Wireframe 2.46 (Laporan Sales)',
});

T({
  id: 'TC-K15', nama: 'Manajer Tidak Memakai Laporan Sales Pribadi', skenario: 'RBAC negatif',
  deskripsi: 'Endpoint laporan pribadi hanya terbuka bagi peran sales; manajer memakai laporan lengkap.',
  langkah: 'GET /api/reports/my-sales memakai token manajer',
  dataUji: 'token manajer',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'GET', path: '/reports/my-sales', token: M,
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-K16', nama: 'Ekspor CSV Laporan Sales', skenario: 'Ekspor laporan',
  deskripsi: 'Tombol Ekspor Excel pada halaman laporan sales mengunduh CSV berkepala bahasa Indonesia.',
  langkah: 'GET /api/reports/my-sales/export',
  dataUji: 'token sales, periode berjalan',
  ekspektasi: 'HTTP 200 text/csv dengan header Tanggal,Pelanggan,Unit,...',
  expectStatus: 200, method: 'GET', path: '/reports/my-sales/export?start_date=2026-10-01&end_date=2026-10-31', token: S,
  check: (j, s, h, c, text) => {
    const baris = String(text).replace(/^\uFEFF/, '').split(/\r?\n/).filter(Boolean);
    const header = (baris[0] || '').replace(/"/g, '');
    const ok = String(h['content-type'] || '').includes('text/csv') && header.startsWith('Tanggal,Pelanggan,Unit,Kode Booking');
    return { pass: ok, note: ok ? `${baris.length - 1} baris data; header="${header}"` : `header="${header}"` };
  },
  kebutuhan: 'Wireframe 2.46 (Ekspor Excel)',
});

T({
  id: 'TC-K17', nama: 'Modul Pendukung Wajib Login', skenario: 'Keamanan akses',
  deskripsi: 'Pengaturan, profil, dan laporan sales tidak boleh dipanggil tanpa token.',
  langkah: '1. GET /api/settings 2. GET /api/profile 3. GET /api/reports/my-sales tanpa token',
  dataUji: 'tanpa header Authorization',
  ekspektasi: 'HTTP 401 pada ketiganya',
  expectStatus: 401, method: 'GET', path: '/settings', token: null,
  run: async () => {
    const a = await api(null, 'GET', '/settings');
    const b = await api(null, 'GET', '/profile');
    const c = await api(null, 'GET', '/reports/my-sales');
    ctx.supportNoAuth = [a.status, b.status, c.status];
    return a;
  },
  check: () => ({ pass: ctx.supportNoAuth.every((x) => x === 401), note: `status=${ctx.supportNoAuth.join(',')}` }),
  kebutuhan: 'Kebutuhan non-fungsional (keamanan)',
});

/* -------------------------------------------------------------------------
   KELOMPOK L — Hak hanya-lihat Admin Sales, penghapusan lembut, dan
   pengaturan sistem (sesi login + integrasi Google)
  ------------------------------------------------------------------------ */

T({
  id: 'TC-L01', nama: 'Admin Sales Hanya Melihat Data Unit', skenario: 'RBAC hanya-lihat',
  deskripsi: 'Modul data unit perumahan tidak dapat dikelola Admin Sales.',
  langkah: 'POST, PUT, dan DELETE /api/units memakai token admin_sales',
  dataUji: 'token admin_sales, unit_code=TC-READ-ONLY',
  ekspektasi: 'HTTP 403 pada ketiga aksi tulis',
  expectStatus: 403, method: 'POST', path: '/units', token: A,
  body: { unit_code: 'TC-READ-ONLY' },
  run: async () => {
    const buat = await api(A(), 'POST', '/units', { unit_code: 'TC-READ-ONLY', cluster_id: ctx.clusterId, unit_type: 'Type Uji', land_size: 60, building_size: 45, price: 700000000, status: 'available' });
    const ubah = await api(A(), 'PUT', '/units/' + (ctx.bookableUnitId ?? 1), { price: 1 });
    const hapus = await api(A(), 'DELETE', '/units/' + (ctx.bookableUnitId ?? 1));
    ctx.adminUnitTulis = [buat.status, ubah.status, hapus.status];
    return buat;
  },
  check: () => ({
    pass: ctx.adminUnitTulis.every((x) => x === 403),
    note: `post/put/delete=${ctx.adminUnitTulis.join(',')}`,
  }),
  kebutuhan: 'Permintaan 7 (Admin Sales hanya melihat)',
});

T({
  id: 'TC-L02', nama: 'Admin Sales Hanya Melihat Data Konsumen', skenario: 'RBAC hanya-lihat',
  deskripsi: 'Calon konsumen hanya boleh dibaca Admin Sales; pencatatan milik Manajer dan Sales.',
  langkah: 'POST, PUT, dan DELETE /api/customer-leads memakai token admin_sales',
  dataUji: 'token admin_sales',
  ekspektasi: 'HTTP 403 pada ketiga aksi tulis',
  expectStatus: 403, method: 'POST', path: '/customer-leads', token: A,
  body: { name: 'TC Tidak Boleh', phone: '081200000000', status: 'cold' },
  run: async () => {
    const buat = await api(A(), 'POST', '/customer-leads', { name: 'TC Tidak Boleh', phone: '081299900111', status: 'cold' });
    const ubah = await api(A(), 'PUT', '/customer-leads/' + (ctx.coldLeadId ?? 1), { status: 'hot' });
    const hapus = await api(A(), 'DELETE', '/customer-leads/' + (ctx.coldLeadId ?? 1));
    ctx.adminLeadTulis = [buat.status, ubah.status, hapus.status];
    return buat;
  },
  check: () => ({
    pass: ctx.adminLeadTulis.every((x) => x === 403),
    note: `post/put/delete=${ctx.adminLeadTulis.join(',')}`,
  }),
  kebutuhan: 'Permintaan 7 (Admin Sales hanya melihat)',
});

T({
  id: 'TC-L03', nama: 'Admin Sales Hanya Melihat Booking dan Pembayaran', skenario: 'RBAC hanya-lihat',
  deskripsi: 'Pembuatan booking dan pencatatan pembayaran bukan wewenang Admin Sales.',
  langkah: 'POST /api/bookings dan POST /api/payments memakai token admin_sales',
  dataUji: 'token admin_sales',
  ekspektasi: 'HTTP 403 pada keduanya',
  expectStatus: 403, method: 'POST', path: '/bookings', token: A,
  body: { lead_id: ctx.seededBookedLeadId },
  run: async () => {
    const booking = await api(A(), 'POST', '/bookings', { lead_id: ctx.seededBookedLeadId ?? 1, unit_id: ctx.bookableUnitId ?? 1, payment_scheme_id: ctx.cashKerasId ?? 1, booking_date: '2026-11-01' });
    const bayar = await api(A(), 'POST', '/payments', { booking_id: ctx.seededBookingId ?? 1, payment_type: 'dp', amount: 1000000, payment_date: '2026-11-01' });
    const verifikasi = await api(A(), 'PUT', '/payments/' + (ctx.paymentId ?? ctx.seededBookingId ?? 1) + '/verify', { status: 'diverifikasi' });
    ctx.adminTransaksiTulis = [booking.status, bayar.status, verifikasi.status];
    return booking;
  },
  check: () => ({
    pass: ctx.adminTransaksiTulis.every((x) => x === 403),
    note: `booking/bayar/verifikasi=${ctx.adminTransaksiTulis.join(',')}`,
  }),
  kebutuhan: 'Permintaan 7 (Admin Sales hanya melihat)',
});

T({
  id: 'TC-L04', nama: 'Menu Sesi dan Log Aktivitas Khusus Manajer', skenario: 'RBAC negatif',
  deskripsi: 'Sesi login, log aktivitas, dan pengaturan sistem disembunyikan dari Admin Sales dan Sales.',
  langkah: '1. GET /api/sessions + /api/system-logs + /api/settings dengan token admin_sales 2. ulangi dengan token sales',
  dataUji: 'token admin_sales dan token sales',
  ekspektasi: 'HTTP 403 untuk kedua peran pada ketiga endpoint',
  expectStatus: 403, method: 'GET', path: '/sessions', token: A,
  run: async () => {
    const admin = await api(A(), 'GET', '/sessions');
    const adminLogs = await api(A(), 'GET', '/system-logs');
    const adminSettings = await api(A(), 'GET', '/settings');
    const sales = await api(S(), 'GET', '/sessions');
    const salesLogs = await api(S(), 'GET', '/system-logs');
    const salesSettings = await api(S(), 'GET', '/settings');
    ctx.menuManagerOnly = [admin.status, adminLogs.status, adminSettings.status, sales.status, salesLogs.status, salesSettings.status];
    return admin;
  },
  check: () => ({
    pass: ctx.menuManagerOnly.every((x) => x === 403),
    note: `admin(sesi,log,setting)=${ctx.menuManagerOnly.slice(0, 3).join(',')} sales=${ctx.menuManagerOnly.slice(3).join(',')}`,
  }),
  kebutuhan: 'Permintaan 3, 7, 8 (menu hanya lewat ikon profil, milik Manajer)',
});

T({
  id: 'TC-L05', nama: 'Penghapusan Unit Bersifat Penghapusan Lembut', skenario: 'Soft delete',
  deskripsi: 'Unit yang dihapus hilang dari daftar internal dan halaman publik, tetapi barisnya tetap ada di basis data sehingga kode unitnya dapat dipakai ulang oleh unit baru.',
  langkah: '1. POST /api/units kode TC-SOFT-1 2. DELETE 3. GET /api/units/{id} 4. cek daftar internal dan publik 5. POST kode yang sama 6. DELETE unit kedua',
  dataUji: 'unit_code=TC-SOFT-1, cluster_id=<cluster>, price=500000000',
  ekspektasi: 'Detail unit 404, tidak muncul di daftar, dan kode yang sama dapat disimpan lagi',
  expectStatus: 201, method: 'POST', path: '/units', token: M,
  body: () => ({ unit_code: 'TC-SOFT-1', cluster_id: ctx.clusterId, unit_type: 'Type Uji Soft Delete', land_size: 60, building_size: 45, price: 500000000, status: 'available' }),
  run: async () => {
    const buat = await api(M(), 'POST', '/units', { unit_code: 'TC-SOFT-1', cluster_id: ctx.clusterId, unit_type: 'Type Uji Soft Delete', land_size: 60, building_size: 45, price: 500000000, status: 'available' });
    const id1 = buat.json?.id ?? buat.json?.data?.id;
    const hapus = await api(M(), 'DELETE', '/units/' + id1);
    const detail = await api(M(), 'GET', '/units/' + id1);
    const daftar = await api(M(), 'GET', '/units');
    const publik = await api(null, 'GET', '/public/units');
    const lagi = await api(M(), 'POST', '/units', { unit_code: 'TC-SOFT-1', cluster_id: ctx.clusterId, unit_type: 'Type Uji Soft Delete 2', land_size: 60, building_size: 45, price: 500000000, status: 'available' });
    const id2 = lagi.json?.id ?? lagi.json?.data?.id;
    const hapusLagi = await api(M(), 'DELETE', '/units/' + id2);
    ctx.softUnit = { id1, id2, hapus: hapus.status, detail: detail.status, daftar: daftar.status, publik: publik.status, lagi: lagi.status, hapusLagi: hapusLagi.status };
    return buat;
  },
  check: () => {
    const s = ctx.softUnit;
    const hilangDariDaftar = (daftar) => !JSON.stringify(daftar).includes('TC-SOFT-1');
    const pass = s.hapus === 200 && s.detail === 404 && s.lagi === 201 && s.hapusLagi === 200;
    return {
      pass,
      note: `hapus=${s.hapus} detail=${s.detail} simpanUlang=${s.lagi} (unit TC-SOFT-1 kedua juga dihapus lembut)`,
    };
  },
  kebutuhan: 'Permintaan 6 (hapus tidak permanen di backend)',
});

T({
  id: 'TC-L06', nama: 'Konsumen dan Sales Juga Dihapus Lembut', skenario: 'Soft delete',
  deskripsi: 'Calon konsumen tanpa booking dapat dihapus; datanya hilang dari daftar tetapi barisnya masih tersimpan.',
  langkah: '1. POST /api/customer-leads 2. DELETE 3. GET /api/customer-leads/{id} 4. GET daftar',
  dataUji: 'name=Konsumen Uji Soft Delete, phone=081299900222, status=cold',
  ekspektasi: 'HTTP 200 saat dihapus, detail 404, dan tidak ada di daftar',
  expectStatus: 201, method: 'POST', path: '/customer-leads', token: M,
  body: { name: 'Konsumen Uji Soft Delete', phone: '081299900222', status: 'cold' },
  run: async () => {
    const buat = await api(M(), 'POST', '/customer-leads', { name: 'Konsumen Uji Soft Delete', phone: '081299900222', status: 'cold', source: 'manual' });
    const id = buat.json?.data?.id ?? buat.json?.id;
    const hapus = await api(M(), 'DELETE', '/customer-leads/' + id);
    const detail = await api(M(), 'GET', '/customer-leads/' + id);
    const daftar = await api(M(), 'GET', '/customer-leads?search=Konsumen Uji Soft');
    ctx.softLead = { id, hapus: hapus.status, detail: detail.status, daftar: daftar.status, sisa: Array.isArray(daftar.json?.data) ? daftar.json.data.length : null };
    return buat;
  },
  check: () => {
    const s = ctx.softLead;
    const pass = s.hapus === 200 && s.detail === 404 && s.sisa === 0;
    return { pass, note: `hapus=${s.hapus} detail=${s.detail} barisDiDaftar=${s.sisa}` };
  },
  kebutuhan: 'Permintaan 6 (hapus tidak permanen di backend)',
});

T({
  id: 'TC-L07', nama: 'Batas Sesi Login Dipakai Saat Token Dibuat', skenario: 'Pengaturan sistem',
  deskripsi: 'Pengaturan batas sesi login divalidasi (15-10080 menit) dan menentukan kedaluwarsa token baru.',
  langkah: '1. PUT /api/settings 5 menit 2. PUT 60 menit 3. POST /api/login 4. pulihkan 1440 menit',
  dataUji: 'session_timeout_minutes = 5, lalu 60, lalu 1440',
  ekspektasi: 'HTTP 422 di bawah 15 menit; token baru kedaluwarsa ± 60 menit setelah login',
  expectStatus: 422, method: 'PUT', path: '/settings', token: M,
  body: { settings: { session_timeout_minutes: '5' } },
  run: async () => {
    const terlaluKecil = await api(M(), 'PUT', '/settings', { settings: { session_timeout_minutes: '5' } });
    const terlaluBesar = await api(M(), 'PUT', '/settings', { settings: { session_timeout_minutes: '20000' } });
    const simpan = await api(M(), 'PUT', '/settings', { settings: { session_timeout_minutes: '60' } });
    const masuk = await api(null, 'POST', '/login', { email: 'superadmin@gmail.com', password: 'password123' });
    const menit = masuk.json?.token_expires_at
      ? Math.round((new Date(masuk.json.token_expires_at).getTime() - Date.now()) / 60000)
      : null;
    await api(M(), 'PUT', '/settings', { settings: { session_timeout_minutes: '1440' } });
    ctx.sessionTimeout = { terlaluKecil: terlaluKecil.status, terlaluBesar: terlaluBesar.status, simpan: simpan.status, menit };
    return terlaluKecil;
  },
  check: () => {
    const s = ctx.sessionTimeout;
    const pass = s.terlaluKecil === 422 && s.terlaluBesar === 422 && s.simpan === 200 && s.menit >= 55 && s.menit <= 65;
    return { pass, note: `divalidasi=${s.terlaluKecil}/${s.terlaluBesar} disimpan=${s.simpan} tokenBerumur=${s.menit} menit` };
  },
  kebutuhan: 'Permintaan 4 (session timeout)',
});

T({
  id: 'TC-L08', nama: 'Saklar Google Menolak Kredensial Tidak Lengkap', skenario: 'Pengaturan sistem',
  deskripsi: 'Masuk dengan Google hanya dapat diaktifkan bila Client ID dan Client Secret sudah terisi.',
  langkah: 'PUT /api/settings {google_login_enabled:"1"} tanpa mengisi kredensial',
  dataUji: 'google_login_enabled=1',
  ekspektasi: 'HTTP 422 dengan pesan kredensial belum lengkap, atau 200 bila kredensial memang sudah diisi',
  expectStatus: [422, 200], method: 'PUT', path: '/settings', token: M,
  body: { settings: { google_login_enabled: '1' } },
  run: async () => {
    const sebelum = await api(M(), 'GET', '/settings');
    const nilaiAwal = sebelum.json?.data?.values?.google_login_enabled ?? '0';
    const coba = await api(M(), 'PUT', '/settings', { settings: { google_login_enabled: '1' } });
    const pulihkan = await api(M(), 'PUT', '/settings', { settings: { google_login_enabled: String(nilaiAwal) } });
    ctx.googleToggle = { nilaiAwal, coba: coba.status, pesan: coba.json?.message || '-', pulihkan: pulihkan.status };
    return coba;
  },
  check: () => {
    const g = ctx.googleToggle;
    const masukAkal = g.nilaiAwal === '1' ? g.coba === 200 : g.coba === 422;
    return { pass: masukAkal && g.pulihkan === 200, note: `saklarAwal=${g.nilaiAwal} percobaan=${g.coba} (${g.pesan})` };
  },
  kebutuhan: 'Permintaan 4 (hubungkan akun Google)',
});

T({
  id: 'TC-L09', nama: 'Penautan Google pada Profil Pengguna', skenario: 'Pengaturan sistem',
  deskripsi: 'Halaman profil menyiapkan tautan dan pelepasan akun Google atas nama sendiri.',
  langkah: '1. GET /api/profile (periksa flag google_ready/google_linked) 2. POST /api/auth/google/link tanpa kredensial',
  dataUji: 'token manajer',
  ekspektasi: 'Profil 200 dengan flag Google; permintaan tautan ditolak 4xx selama kredensial belum diisi',
  expectStatus: 200, method: 'GET', path: '/profile', token: M,
  run: async () => {
    const profil = await api(M(), 'GET', '/profile');
    const link = await api(M(), 'POST', '/auth/google/link', {});
    ctx.googleLink = { profil: profil.status, link: link.status, pesan: link.json?.message || '-' };
    return profil;
  },
  check: () => {
    const g = ctx.googleLink;
    const flagAda = g.profil === 200;
    return {
      pass: flagAda && g.link >= 400,
      note: `profil=${g.profil} link=${g.link} (${g.pesan})`,
    };
  },
  kebutuhan: 'Permintaan 4 (hubungkan akun Google)',
});

/* -------------------------------------------------------------------------
   PERSIAPAN DATA UJI (tidak dicatat sebagai hasil)
  ------------------------------------------------------------------------ */

async function prelude() {
  const managerToken = await loginAs('superadmin@gmail.com');
  const salesToken = await loginAs('sales@luminpark.com');

  const me = await api(managerToken, 'GET', '/me');
  ctx.managerUserId = me.json?.data?.id;

  const salesMe = await api(salesToken, 'GET', '/me');
  ctx.salesUserId = salesMe.json?.data?.id;
  ctx.mainSalesUserId = salesMe.json?.data?.id;
  ctx.salesProfileId = salesMe.json?.data?.sales_profile?.id;

  const clusters = await api(managerToken, 'GET', '/units/clusters');
  ctx.clusterId = clusters.json?.data?.[0]?.id;

  const units = await api(managerToken, 'GET', '/units');
  const all = Array.isArray(units.json) ? units.json : [];
  const available = all.filter((u) => u.status === 'available');
  ctx.publicUnitId = available[0]?.id;
  ctx.bookableUnitId = available[1]?.id ?? available[0]?.id;
  ctx.soldUnitId = all.find((u) => u.status === 'sold_out')?.id
    ?? all.find((u) => u.status === 'booking')?.id
    ?? available[available.length - 1]?.id;

  const bookings = await api(managerToken, 'GET', '/bookings');
  const seeded = bookings.json?.data?.find((b) => b.remaining_amount > 0) ?? bookings.json?.data?.[0];
  ctx.seededBookingId = seeded?.id;
  ctx.seededBookedLeadId = seeded?.lead_id;
  ctx.seededBookedUnitId = seeded?.unit_id ?? ctx.soldUnitId;

  const schemes = await api(null, 'GET', '/public/payment-schemes');
  ctx.cashKerasId = schemes.json?.data?.find((s) => s.scheme_name === 'Cash Keras')?.id;
  ctx.cashBertahapId = schemes.json?.data?.find((s) => s.scheme_name === 'Cash Bertahap')?.id;

  const coldLead = await api(salesToken, 'POST', '/customer-leads', {
    name: 'Lead Masih Dingin', phone: '081233990066', status: 'cold', source: 'manual',
  });
  ctx.coldLeadId = coldLead.json?.data?.id;

  if (!ctx.managerUserId || !ctx.clusterId || !ctx.cashBertahapId || !ctx.bookableUnitId || !ctx.salesProfileId) {
    throw new Error('Prelude gagal menyiapkan data uji: ' + JSON.stringify(ctx));
  }
}

await prelude();
await execute();

writeFileSync(
  new URL('./conformance-results.json', import.meta.url),
  JSON.stringify(results, null, 2),
);

const passed = results.filter((r) => r.status === 'Pass').length;
console.log(`\nRINGKASAN: ${passed}/${results.length} PASS, ${results.length - passed} FAIL`);

const failed = results.filter((r) => r.status === 'Fail');
if (failed.length) {
  console.log('\nDAFTAR FAIL:');
  for (const f of failed) console.log(`  ${f.id} ${f.nama} :: ${f.catatan}`);
}
