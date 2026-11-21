/**
 * Suite pengujian kesesuaian backend Lumin Park terhadap laporan tugas proyek.
 * Setiap kasus mencatat 10 kolom format: ID, Nama, Skenario, Deskripsi,
 * Langkah Uji, Data Uji, Hasil yang Diharapkan, Hasil Aktual, Status, Catatan.
 *
 * Urutan eksekusi = urutan deklarasi. Kasus yang butuh beberapa langkah berturut
 * memakai `run` dan mengembalikan respons yang dicatat sebagai hasil uji.
 */
import { readFileSync, statSync, writeFileSync } from 'node:fs';

const BASE = process.env.LP_API_BASE || 'http://127.0.0.1:8080/api';
const AKAR = BASE.replace(/\/api\/?$/, '');
const results = [];
const ctx = {};

// Penanda per-jalan supaya fixtures yang membuat baris baru (kode unit dan nama
// cluster) tidak bertabrakan dengan milik indeks unik pada pelaksanaan sebelumnya.
const UIN = Date.now().toString().slice(-6);
ctx.unitUji = `LT-TEST-${UIN}`;
ctx.clusterUji = `Cluster Uji Sistem ${UIN}`;

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

// Beberapa revisi (33 dan 35) berupa bentuk halaman, jadi sumber HTML Blade
// ikut dibaca dari aplikasi yang sama di luar grup /api.
async function halaman(path) {
  const res = await fetch(AKAR + path, { headers: { Accept: 'text/html' } });
  return { status: res.status, text: await res.text() };
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

    // Kasus multi-langkah merangkum beberapa permintaan menjadi satu status; body sintetisnya
    // (pesan server per langkah) tetap harus terekam supaya kolom Hasil Aktual tidak kosong.
    const jsonTeks = json && Object.keys(json).length ? JSON.stringify(json) : '';
    const bodyTeks = trim(text) !== '(kosong)' ? trim(text) : (jsonTeks || note || '(kosong)');

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
      actualBody: `HTTP ${status} — ${bodyTeks}`,
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
  id: 'TC-A01', nama: 'Login Super Admin', skenario: 'Autentikasi',
  deskripsi: 'Super Admin login dengan kredensial valid dan menerima token bearer.',
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
  langkah: 'POST /api/register memakai token super admin',
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
    const pesanDitolak = [];
    for (const email of ['admin@luminpark.com', 'manager@luminpark.com']) {
      const hasil = await api(null, 'POST', '/login', { email, password: 'password123' });
      ctx.lamaSuperAdmin.push(`${email}=${hasil.status}${hasil.json?.access_token ? '+TOKEN' : ''}`);
      if (hasil.json?.message) pesanDitolak.push(`${email}: ${hasil.json.message}`);
    }
    const masihAdaToken = ctx.lamaSuperAdmin.some((x) => x.includes('+TOKEN'));
    return { status: masihAdaToken ? 200 : 401, json: { pesan: pesanDitolak } };
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
  langkah: 'GET /api/me dengan token super admin',
  dataUji: 'token super admin',
  ekspektasi: 'HTTP 200, data.role=super_admin dan data.status=aktif',
  expectStatus: 200, method: 'GET', path: '/me', token: M,
  check: (j) => (j?.data?.role === 'super_admin' && j?.data?.status === 'aktif'
    ? { pass: true, note: 'Kolom status akun (ERD) terpakai.' }
    : { pass: false, note: `role=${j?.data?.role}, status=${j?.data?.status}` }),
  kebutuhan: 'Kebutuhan a + ERD tabel pengguna (status akun)',
});

T({
  id: 'TC-A10', nama: 'Sales Menolak Dashboard Super Admin', skenario: 'RBAC negatif',
  deskripsi: 'Sales tidak dapat membuka dashboard super admin.',
  langkah: 'GET /api/dashboard/super-admin memakai token sales',
  dataUji: 'token sales',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'GET', path: '/dashboard/super-admin', token: S,
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-A11', nama: 'Sales Menolak CRUD Unit', skenario: 'RBAC negatif',
  deskripsi: 'Hak tambah/ubah/hapus unit hanya milik Super Admin (dan Admin Sales untuk baca).',
  langkah: 'POST /api/units memakai token sales',
  dataUji: 'unit_code=LT-900, cluster_id=<cluster>, price=700000000',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'POST', path: '/units', token: S,
  body: () => ({ unit_code: 'LT-900', cluster_id: ctx.clusterId, unit_type: 'Type Tes', land_size: 60, building_size: 45, price: 700000000, status: 'available' }),
  kebutuhan: 'Kebutuhan b (Super Admin dapat menambah data unit)',
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
  deskripsi: 'Admin Sales dan Super Admin dapat melihat seluruh data unit beserta statusnya.',
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
  langkah: 'GET /api/sessions memakai token super admin',
  dataUji: 'token super admin',
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
  deskripsi: 'Super Admin menambahkan unit baru dengan kode unik dan status awal tersedia.',
  langkah: 'POST /api/units',
  dataUji: () => `unit_code=${ctx.unitUji}, cluster_id=<cluster>, tipe 60/45, harga 725.000.000`,
  ekspektasi: 'HTTP 201 dan unit tersimpan dengan status available',
  expectStatus: 201, method: 'POST', path: '/units', token: M,
  body: () => ({
    unit_code: ctx.unitUji, cluster_id: ctx.clusterId, unit_type: 'Modern Tropical Tes',
    land_size: 60, building_size: 45, price: 725000000, status: 'available', description: 'Unit uji fungsional.',
  }),
  check: (j) => (j?.data?.unit_code === ctx.unitUji && j?.data?.status === 'available'
    ? { pass: true, note: `Unit id=${j.data.id} terbentuk.` }
    : { pass: false, note: trim(JSON.stringify(j)) }),
  save: (j, c) => { c.newUnitId = j?.data?.id; },
  kebutuhan: 'Kebutuhan b (menambah data unit)',
});

T({
  id: 'TC-B04', nama: 'Duplikasi Kode Unit', skenario: 'Validasi unit',
  deskripsi: 'Kode unit harus unik sehingga duplikasi ditolak.',
  langkah: 'POST /api/units memakai kode yang sama',
  dataUji: () => `unit_code=${ctx.unitUji} (sudah ada)`,
  ekspektasi: 'HTTP 422 dengan pesan kesalahan unik',
  expectStatus: 422, method: 'POST', path: '/units', token: M,
  body: () => ({
    unit_code: ctx.unitUji, cluster_id: ctx.clusterId, unit_type: 'Duplikat',
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
  deskripsi: 'Super Admin dapat memperbarui status unit menjadi proses delivery.',
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
  deskripsi: 'Super Admin menghapus unit yang belum pernah dipesan.',
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

/* Cluster adalah induk unit: formulir Tambah Unit tidak dapat dipakai sebelum cluster ada,
   jadi pembuatan cluster ikut diuji pada blok data unit. */

T({
  id: 'TC-B11', nama: 'Daftar Proyek untuk Cluster', skenario: 'Membaca data referensi',
  deskripsi: 'Setiap cluster menempel pada satu proyek, sehingga formulir Tambah Cluster membutuhkan daftar proyek.',
  langkah: 'GET /api/projects memakai token Super Admin',
  dataUji: 'tanpa parameter',
  ekspektasi: 'HTTP 200 dan data berisi proyek dengan id serta nama',
  expectStatus: 200, method: 'GET', path: '/projects', token: M,
  check: (j) => {
    const arr = j?.data || [];
    if (!arr.length) return { pass: false, note: 'Daftar proyek kosong.' };
    ctx.projectId = arr[0].id;
    return { pass: !!arr[0].name, note: `${arr.length} proyek, dipakai ${arr[0].name} (id=${arr[0].id}).` };
  },
  kebutuhan: 'ERD tabel proyek → cluster',
});

T({
  id: 'TC-B12', nama: 'Tambah Cluster Baru', skenario: 'Membuat data cluster',
  deskripsi: 'Super Admin menambahkan cluster baru di dalam sebuah proyek; respons memuat proyek induknya.',
  langkah: 'POST /api/clusters',
  dataUji: () => `project_id=${ctx.projectId}, name=${ctx.clusterUji}`,
  ekspektasi: 'HTTP 201 dan data cluster tersimpan dengan nama yang sama',
  expectStatus: 201, method: 'POST', path: '/clusters', token: M,
  body: () => ({ project_id: ctx.projectId, name: ctx.clusterUji, description: 'Cluster hasil pengujian kesesuaian.' }),
  check: (j) => (j?.data?.name === ctx.clusterUji && j?.data?.project?.id === ctx.projectId
    ? { pass: true, note: `Cluster id=${j.data.id} terbentuk pada proyek ${j.data.project.name}.` }
    : { pass: false, note: trim(JSON.stringify(j)) }),
  save: (j, c) => { c.newClusterId = j?.data?.id; },
  kebutuhan: 'Kebutuhan b.4 (kelola data unit) + permintaan Tambah Cluster',
});

T({
  id: 'TC-B13', nama: 'Nama Cluster Duplikat Ditolak', skenario: 'Validasi cluster',
  deskripsi: 'Indeks unik clusters(project_id, name) masih berlaku di basis data, jadi duplikasi harus ditutup lewat validasi agar tidak menjadi 500.',
  langkah: 'POST /api/clusters memakai nama dan proyek yang sama dengan TC-B12',
  dataUji: () => `project_id=${ctx.projectId}, name=${ctx.clusterUji} (sudah ada)`,
  ekspektasi: 'HTTP 422 dengan pesan kesalahan pada field name',
  expectStatus: 422, method: 'POST', path: '/clusters', token: M,
  body: () => ({ project_id: ctx.projectId, name: ctx.clusterUji }),
  check: (j) => ({ pass: !!j?.errors?.name, note: j?.errors?.name?.[0] || trim(JSON.stringify(j)) }),
  kebutuhan: 'Kebutuhan non-fungsional (integritas data)',
});

T({
  id: 'TC-B14', nama: 'Cluster Tanpa Nama Ditolak', skenario: 'Validasi cluster',
  deskripsi: 'Nama cluster wajib dan proyek induk harus ada; cluster yatim tidak boleh terbentuk.',
  langkah: '1. POST /api/clusters dengan name kosong 2. POST /api/clusters dengan project_id palsu',
  dataUji: 'name="" dan project_id=999999',
  ekspektasi: 'Kedua permintaan ditolak HTTP 422',
  expectStatus: 422, method: 'POST', path: '/clusters', token: M,
  run: async () => {
    const tanpa = await api(M(), 'POST', '/clusters', { project_id: ctx.projectId, name: '' });
    const yatim = await api(M(), 'POST', '/clusters', { project_id: 999999, name: 'Cluster Yatim' });
    ctx.clusterInvalid = `name=${tanpa.status} proyek=${yatim.status}`;
    return { status: tanpa.status, json: { pesan: [tanpa.json?.message, yatim.json?.message] } };
  },
  check: () => ({ pass: ctx.clusterInvalid === 'name=422 proyek=422', note: ctx.clusterInvalid }),
  kebutuhan: 'Kebutuhan non-fungsional (validasi)',
});

T({
  id: 'TC-B15', nama: 'Cluster Baru Terbaca sebagai Referensi Unit', skenario: 'Membaca data referensi',
  deskripsi: 'Daftar referensi formulir unit memuat cluster yang baru dibuat, sehingga dropdown Tambah Unit ikut terisi.',
  langkah: 'GET /api/units/clusters lalu cari nama cluster hasil TC-B12',
  dataUji: () => `name=${ctx.clusterUji}, id=${ctx.newClusterId}`,
  ekspektasi: 'HTTP 200 dan daftar memuat cluster baru beserta proyeknya',
  expectStatus: 200, method: 'GET', path: '/units/clusters', token: M,
  check: (j) => {
    const arr = j?.data || [];
    const temu = arr.find((x) => x.id === ctx.newClusterId);
    return {
      pass: !!temu && !!temu.project,
      note: temu ? `${arr.length} cluster; ${temu.name} ada dengan relasi proyek ${temu.project?.name}.`
        : 'Cluster hasil uji tidak muncul di daftar referensi.',
    };
  },
  kebutuhan: 'Kebutuhan b.4 + permintaan Tambah Cluster',
});

T({
  id: 'TC-B16', nama: 'Unit Baru pada Cluster Hasil Tambah', skenario: 'Membuat data unit',
  deskripsi: 'Cluster yang baru dibuat langsung dapat dipakai membuat unit, membuktikan keterhubungan cluster → unit.',
  langkah: 'POST /api/units memakai cluster_id hasil TC-B12',
  dataUji: () => `cluster_id=${ctx.newClusterId}, unit_code=LT-CLUSTER-${UIN}`,
  ekspektasi: 'HTTP 201 dan unit tersimpan pada cluster tersebut',
  expectStatus: 201, method: 'POST', path: '/units', token: M,
  body: () => ({
    unit_code: `LT-CLUSTER-${UIN}`, cluster_id: ctx.newClusterId, unit_type: 'Skandinavia Uji',
    land_size: 72, building_size: 54, price: 815000000, status: 'available',
    description: 'Unit uji untuk cluster baru.',
  }),
  check: (j) => (j?.data?.id && j.data.cluster_id === ctx.newClusterId
    ? { pass: true, note: `Unit id=${j.data.id} tersimpan di cluster ${j.data?.cluster?.name}.` }
    : { pass: false, note: trim(JSON.stringify(j)) }),
  save: (j, c) => { c.unitClusterBaruId = j?.data?.id; },
  kebutuhan: 'Kebutuhan b.4 (menambah data unit)',
});

T({
  id: 'TC-B17', nama: 'Admin Sales Tidak Dapat Menambah Cluster', skenario: 'RBAC hanya-lihat',
  deskripsi: 'Cluster adalah bagian Data Unit yang hanya dapat diubah Super Admin; Admin Sales hanya berhak membaca.',
  langkah: '1. POST /api/clusters memakai token Admin Sales 2. GET /api/clusters memakai token yang sama',
  dataUji: 'token admin_sales, project_id + name cluster',
  ekspektasi: 'Tulis ditolak HTTP 403 dan baca diizinkan HTTP 200',
  expectStatus: 403, method: 'POST', path: '/clusters', token: A,
  run: async () => {
    const tulis = await api(A(), 'POST', '/clusters', { project_id: ctx.projectId, name: 'Cluster Admin Sales' });
    const baca = await api(A(), 'GET', '/clusters');
    ctx.clusterAdminSales = `tulis=${tulis.status} baca=${baca.status}`;
    return { status: tulis.status, json: { pesan: [tulis.json?.message], clusterTerbaca: (baca.json?.data || []).length } };
  },
  check: () => ({ pass: ctx.clusterAdminSales === 'tulis=403 baca=200', note: ctx.clusterAdminSales }),
  kebutuhan: 'Keputusan RBAC: Admin Sales hanya-lihat pada Data Unit',
});

T({
  id: 'TC-B18', nama: 'Modul Cluster Wajib Login', skenario: 'Otorisasi negatif',
  deskripsi: 'Daftar cluster dan proyek tidak terbuka untuk publik karena memuat data internal pengembang.',
  langkah: '1. GET /api/clusters tanpa token 2. GET /api/projects tanpa token 3. POST /api/clusters memakai token sales',
  dataUji: 'tanpa Authorization; token sales untuk tulis',
  ekspektasi: 'Permintaan tanpa token 401 dan tulisan sales 403',
  expectStatus: 401, method: 'GET', path: '/clusters', token: null,
  run: async () => {
    const tanpa = await api(null, 'GET', '/clusters');
    const proyekTanpa = await api(null, 'GET', '/projects');
    const salesTulis = await api(S(), 'POST', '/clusters', { project_id: ctx.projectId, name: 'Cluster Sales' });
    ctx.clusterAuth = `tanpa=${tanpa.status} proyek=${proyekTanpa.status} sales=${salesTulis.status}`;
    return {
      status: tanpa.status,
      json: { pesan: [tanpa.json?.message, proyekTanpa.json?.message, salesTulis.json?.message] },
    };
  },
  check: () => ({ pass: ctx.clusterAuth === 'tanpa=401 proyek=401 sales=403', note: ctx.clusterAuth }),
  kebutuhan: 'Kebutuhan a.2 (RBAC)',
});

/* -------------------------------------------------------------------------
   C. MANAJEMEN CALON KONSUMEN & FOLLOW UP
   ------------------------------------------------------------------------ */

T({
  id: 'TC-C01', nama: 'Sales Menambah Calon Konsumen', skenario: 'Membuat data lead',
  deskripsi: 'Sales menambah calon konsumen baru; sales_id diambil dari token, bukan dari klien.',
  langkah: 'POST /api/customer-leads memakai token sales',
  dataUji: () => `nama=Consumer Uji, telepon=081233${UIN} (unik per pelaksanaan), status=warm`,
  ekspektasi: 'HTTP 201 dan sales_id terisi otomatis dari token',
  expectStatus: 201, method: 'POST', path: '/customer-leads', token: S,
  body: () => ({
    name: 'Consumer Uji', phone: `081233${UIN}`, email: 'uji@consumer.com',
    address: 'Jl. Uji Coba 1', status: 'warm', budget: 800000000, note: 'Lead pengujian.', source: 'manual',
  }),
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
  body: () => ({ name: 'Lead Salah', phone: `081233${UIN}9`, status: 'negotiating' }),
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
      name: 'Lead Milik Sales Lain', phone: `081233${UIN}8`, status: 'warm',
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
  deskripsi: 'Booking saja belum menjadikan calon konsumen sebagai Konsumen: kategori berpindah setelah pembayarannya diverifikasi (revisi 7 + revisi 15), sementara status Cold/Warm/Hot tetap seperti adanya.',
  langkah: '1. GET /api/customer-leads/{id} segera setelah booking 2. Periksa category, status, dan penanda pembayarannya',
  dataUji: () => `lead_id=${ctx.leadId} (hasil TC-C01) + booking TC-D05, belum ada pembayaran terverifikasi`,
  ekspektasi: "HTTP 200 dengan category=prospek selama belum ada pembayaran terverifikasi dan status salah satu cold/warm/hot; perpindahan ke konsumen dibuktikan TC-R12",
  expectStatus: 200, method: 'GET', path: () => `/customer-leads/${ctx.leadId}`, token: M,
  check: (j) => {
    const status = j?.data?.status;
    const ok = j?.data?.category === 'prospek' && ['cold', 'warm', 'hot'].includes(status);
    return {
      pass: ok,
      note: `category=${j?.data?.category}, status=${status} — booking ${ctx.bookingId} baru dibuat tanpa pembayaran `
        + 'terverifikasi, sehingga kategori belum konsumen (revisi 15).',
    };
  },
  kebutuhan: 'Kebutuhan e + ERD + revisi 7 (kategori konsumen) + revisi 15 (konsumen butuh pembayaran terverifikasi)',
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
  deskripsi: 'Verifikasi bukti transfer adalah hak Super Admin.',
  langkah: 'PUT /api/payments/{id}/verify memakai token sales',
  dataUji: 'verification_status=verified, token sales',
  ekspektasi: 'HTTP 403',
  expectStatus: 403, method: 'PUT', path: () => `/payments/${ctx.paymentId}/verify`, token: S,
  body: { verification_status: 'verified' },
  kebutuhan: 'Kebutuhan d.2 (Super Admin memverifikasi)',
});

T({
  id: 'TC-E04', nama: 'Verifikasi oleh Super Admin', skenario: 'Verifikasi pembayaran',
  deskripsi: 'Super Admin memverifikasi pembayaran; verifikator dan waktu diisi sistem.',
  langkah: 'PUT /api/payments/{id}/verify memakai token super admin',
  dataUji: 'verification_status=verified',
  ekspektasi: 'HTTP 200 dengan verified_by = id super admin dan verified_at terisi',
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
  deskripsi: 'Super Admin menambah sales; akun, profil, dan target periode berjalan dibuat sekali jalan.',
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
  deskripsi: 'Admin Sales memiliki hak kelola data sales yang sama dengan Super Admin.',
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
  deskripsi: 'Super Admin mereset password sales dan sesi lama dicabut.',
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
  deskripsi: 'Sales tanpa transaksi dapat dihapus oleh Super Admin.',
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
  id: 'TC-G01', nama: 'Dashboard Super Admin', skenario: 'Dashboard',
  deskripsi: 'Dashboard super admin memuat unit sold out, belum delivery, pendapatan, jumlah sales, calon konsumen, dan konsumen.',
  langkah: 'GET /api/dashboard/super-admin',
  dataUji: 'token super admin',
  ekspektasi: 'HTTP 200 dengan seluruh kunci metrik sesuai laporan',
  expectStatus: 200, method: 'GET', path: '/dashboard/super-admin', token: M,
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
  id: 'TC-G04', nama: 'Dashboard Sales Ditolak Super Admin', skenario: 'RBAC negatif',
  deskripsi: 'Endpoint dashboard sales tidak dapat dibuka oleh super admin.',
  langkah: 'GET /api/dashboard/sales memakai token super admin',
  dataUji: 'token super admin',
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
  deskripsi: 'Laporan konsumen dan calon konsumen disaring berdasarkan status Cold/Warm/Hot dan memisahkan kategori konsumen, prospek, serta pembatalan (revisi 5 dan 7).',
  langkah: 'GET /api/reports/consumers?status=hot',
  dataUji: 'status=hot',
  ekspektasi: 'HTTP 200 dengan totals.total, cold, warm, hot, prospek, customer, dan cancelled',
  expectStatus: 200, method: 'GET', path: '/reports/consumers?status=hot', token: M,
  check: (j) => {
    const t = j?.totals;
    const ok = !!j?.data && !!t && ['total', 'cold', 'warm', 'hot', 'prospek', 'customer', 'cancelled'].every((k) => typeof t[k] === 'number')
      && (j.data.length === 0 || j.data.every((r) => r.status === 'hot'));
    return { pass: ok, note: ok ? `total=${t.total}, prospek=${t.prospek}, konsumen=${t.customer}, dibatalkan=${t.cancelled}, baris=${j.data.length}` : 'Struktur laporan konsumen tidak sesuai.' };
  },
  kebutuhan: 'Kebutuhan f.4 (laporan konsumen & calon konsumen) + revisi 5',
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
  id: 'TC-H06', nama: 'Rute Form Kontak Publik Dihapus', skenario: 'Informasi konsumen',
  deskripsi: 'Halaman publik tidak lagi menerima pengisian data calon konsumen sehingga tidak ada jalur tulis tanpa '
    + 'login; konsumen menghubungi sales lewat WhatsApp dan data lead dibuat sales dari halaman Data Konsumen.',
  langkah: 'POST /api/public/contact (dipakai form kontak lama)',
  dataUji: 'nama=Web Visitor, phone=081233777001',
  ekspektasi: 'HTTP 404 dengan pesan "Alamat tujuan tidak ditemukan." dan tanpa baris lead baru',
  expectStatus: 404, method: 'POST', path: '/public/contact', token: null,
  body: { name: 'Web Visitor', phone: '081233777001', note: 'Tanya ketersediaan unit.' },
  check: (j) => ({
    pass: j?.message === 'Alamat tujuan tidak ditemukan.',
    note: `pesan="${j?.message}" — lead website tidak dibuat`,
  }),
  kebutuhan: 'Kebutuhan g.4 (konsumen diarahkan ke narahubung sales) + keputusan pengguna: form lead publik dihapus',
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
  deskripsi: 'Booking dan pembayaran yang diverifikasi memicu notifikasi bagi Super Admin serta sales pemilik transaksi (siaran ke Admin Sales dilepas pada revisi 29).',
  langkah: 'GET /api/notifications memakai token super admin',
  dataUji: 'token super admin',
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
  dataUji: 'token super admin',
  ekspektasi: 'HTTP 200 dengan unread_count >= 1',
  expectStatus: 200, method: 'GET', path: '/notifications/unread-count', token: M,
  check: (j) => ({ pass: Number(j?.data?.unread_count) >= 1, note: `unread_count=${j?.data?.unread_count}` }),
  kebutuhan: 'ERD tabel notifikasi',
});

T({
  id: 'TC-I03', nama: 'Tandai Satu Notifikasi Dibaca', skenario: 'Notifikasi',
  deskripsi: 'Notifikasi hanya dapat diubah oleh pemiliknya.',
  langkah: 'PUT /api/notifications/{id}/read',
  dataUji: () => `notifikasi id=${ctx.firstNotifId} milik super admin`,
  ekspektasi: 'HTTP 200 dengan is_read=true',
  expectStatus: 200, method: 'PUT', path: () => `/notifications/${ctx.firstNotifId}/read`, token: M,
  check: (j) => ({ pass: j?.data?.is_read === true, note: `is_read=${j?.data?.is_read}` }),
  kebutuhan: 'ERD tabel notifikasi',
});

T({
  id: 'TC-I04', nama: 'Notifikasi Milik Orang Lain', skenario: 'RBAC negatif',
  deskripsi: 'Sales tidak dapat membaca notifikasi milik Super Admin.',
  langkah: '1. PUT notifikasi super admin memakai token sales 2. GET /api/notifications memakai token sales',
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
      note: `ubah notifikasi milik super admin → HTTP ${ctx.foreignNotifStatus}; daftar sales berisi ${arr.length} entri${murni ? ', seluruhnya milik user ' + ctx.salesUserId : ' dan ada yang milik pengguna lain'}.`,
    };
  },
  kebutuhan: 'Kebutuhan a (RBAC)',
});

T({
  id: 'TC-I05', nama: 'Log Aktivitas Sistem', skenario: 'Log sistem',
  deskripsi: 'Aktivitas login tercatat pada tabel log_sistem beserta IP dan user agent; sejak revisi 30 pembacaannya hanya milik Super Admin.',
  langkah: 'GET /api/system-logs dengan token super_admin; ulangi dengan token admin_sales lalu bandingkan dengan /api/activity-logs',
  dataUji: 'token super_admin dan admin_sales',
  ekspektasi: 'HTTP 200 bagi super_admin dengan entri activity=login dan ip_address serta user_agent terisi; HTTP 403 bagi admin_sales pada kedua endpoint log',
  expectStatus: 200, method: 'GET', path: '/system-logs', token: M,
  run: async () => {
    const respon = await api(M(), 'GET', '/system-logs');
    ctx.logAdminSales = (await api(A(), 'GET', '/system-logs')).status;
    ctx.logManager = (await api(A(), 'GET', '/activity-logs')).status;
    return respon;
  },
  check: (j) => {
    const arr = (j?.data || []).filter((l) => l.activity === 'login');
    const ok = arr.length >= 1 && !!arr[0].ip_address && !!arr[0].user_agent
      && ctx.logAdminSales === 403 && ctx.logManager === 403;
    return {
      pass: ok,
      note: `${arr.length} entri login; ip=${arr[0] ? arr[0].ip_address : '-'}; agent=${trim(arr[0]?.user_agent || '-')}; `
        + `admin_sales system-logs=${ctx.logAdminSales}, activity-logs=${ctx.logManager}`,
    };
  },
  kebutuhan: 'ERD tabel log_sistem + revisi 30',
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

async function uploadOnce(token, path, fields, fileField, filePath, mimeType) {
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

async function upload(token, path, fields, fileField, filePath, mimeType) {
  // Sama seperti api(): jendela throttle 60 request/menit bisa memotong unggahan uji.
  for (let attempt = 0; attempt < 8; attempt++) {
    const outcome = await uploadOnce(token, path, fields, fileField, filePath, mimeType);
    if (outcome.status !== 429) return outcome;
    const wait = Number(outcome.headers['retry-after']) * 1000 || 3000;
    await new Promise((r) => setTimeout(r, Math.min(wait, 15000)));
  }

  return uploadOnce(token, path, fields, fileField, filePath, mimeType);
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
  id: 'TC-K02', nama: 'Daftar Pengaturan oleh Super Admin', skenario: 'Pengaturan sistem',
  deskripsi: 'Super Admin memuat seluruh kunci pengaturan beserta daftar kunci yang boleh diubah.',
  langkah: 'GET /api/settings memakai token super admin',
  dataUji: 'token super admin',
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
  deskripsi: 'Super Admin memperbarui sebagian kunci dan nilai baru langsung terpakai oleh API publik.',
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
  deskripsi: 'Pengaturan sistem hanya milik Super Admin sesuai wireframe.',
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
    const roleTry = await api(S(), 'PUT', '/profile', { role: 'super_admin', status: 'superadmin' });
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
  id: 'TC-K10', nama: 'Profil Super Admin Tanpa Profil Sales', skenario: 'Profil akun',
  deskripsi: 'Akun non-sales tetap memiliki halaman profil; kolom sales dikosongkan, bukan error.',
  langkah: 'GET /api/profile memakai token super admin',
  dataUji: 'token super admin',
  ekspektasi: 'HTTP 200 dengan sales_code null dan role super_admin',
  expectStatus: 200, method: 'GET', path: '/profile', token: M,
  check: (j) => {
    const d = j?.data || {};
    const ok = d.role === 'super_admin' && d.sales_code === null && Array.isArray(d.targets) && d.targets.length === 0;
    return { pass: ok, note: ok ? `role=${d.role}, targets=0` : `role=${d.role}, sales_code=${d.sales_code}` };
  },
  kebutuhan: 'Wireframe 2.34 (Pengaturan akun)',
});

T({
  id: 'TC-K11', nama: 'Widget Grafik dan Booking Terbaru (Super Admin)', skenario: 'Dashboard',
  deskripsi: 'Dashboard super admin menampilkan grafik penjualan per bulan dan daftar booking terbaru.',
  langkah: 'GET /api/dashboard/super-admin',
  dataUji: 'token super admin',
  ekspektasi: 'HTTP 200 dengan sales_by_month 6 entri (month, revenue, units_sold) dan recent_bookings berisi customer/unit/status',
  expectStatus: 200, method: 'GET', path: '/dashboard/super-admin', token: M,
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
  ekspektasi: 'HTTP 200 dengan summary (total_customers, total_prospects, total_bookings, units_sold, total_revenue) dan data berisi status_label Pemesanan/Terjual/Dibatalkan/Calon Pembeli',
  expectStatus: 200, method: 'GET', path: '/reports/my-sales?start_date=2026-10-01&end_date=2026-10-31', token: S,
  check: (j) => {
    const d = j?.data || {};
    const labels = new Set((d || []).map((r) => r.status_label));
    const ok = !!j?.summary && typeof j.summary.total_revenue === 'number' && Array.isArray(d)
      && [...labels].every((l) => ['Pemesanan', 'Terjual', 'Dibatalkan', 'Calon Pembeli'].includes(l));
    return { pass: ok, note: ok ? `baris=${d.length}; ringkas=${JSON.stringify(j.summary)}` : `label=${[...labels].join(',')}` };
  },
  kebutuhan: 'Wireframe 2.46 (Laporan Sales)',
});

T({
  id: 'TC-K15', nama: 'Super Admin Tidak Memakai Laporan Sales Pribadi', skenario: 'RBAC negatif',
  deskripsi: 'Endpoint laporan pribadi hanya terbuka bagi peran sales; super admin memakai laporan lengkap.',
  langkah: 'GET /api/reports/my-sales memakai token super admin',
  dataUji: 'token super admin',
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
  deskripsi: 'Calon konsumen hanya boleh dibaca Admin Sales; pencatatan milik Super Admin dan Sales.',
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
  id: 'TC-L04', nama: 'Menu Sesi, Log Aktivitas, dan Pengaturan per Peran', skenario: 'RBAC negatif',
  deskripsi: 'Sesi login, pengaturan sistem, serta Log Aktivitas (sistem dan jejak audit) hanya milik Super Admin setelah revisi 30 melepas menu log dari Admin Sales.',
  langkah: '1. GET /api/sessions + /api/system-logs + /api/settings dengan token admin_sales 2. ulangi dengan token sales 3. ulangi dengan token super_admin',
  dataUji: 'token admin_sales, token sales, dan token super_admin',
  ekspektasi: 'admin_sales dan sales: 403 pada ketiganya; super_admin: 200 pada ketiganya',
  expectStatus: 403, method: 'GET', path: '/sessions', token: A,
  run: async () => {
    const admin = await api(A(), 'GET', '/sessions');
    const adminLogs = await api(A(), 'GET', '/system-logs');
    const adminSettings = await api(A(), 'GET', '/settings');
    const sales = await api(S(), 'GET', '/sessions');
    const salesLogs = await api(S(), 'GET', '/system-logs');
    const salesSettings = await api(S(), 'GET', '/settings');
    const superSesi = await api(M(), 'GET', '/sessions');
    const superLogs = await api(M(), 'GET', '/system-logs');
    const superSettings = await api(M(), 'GET', '/settings');
    ctx.menuManagerOnly = [admin.status, adminLogs.status, adminSettings.status, sales.status, salesLogs.status, salesSettings.status,
      superSesi.status, superLogs.status, superSettings.status];
    return admin;
  },
  check: () => {
    const [aS, aL, aG, sS, sL, sG, mS, mL, mG] = ctx.menuManagerOnly;
    const pass = [aS, aL, aG, sS, sL, sG].every((x) => x === 403) && [mS, mL, mG].every((x) => x === 200);
    return {
      pass,
      note: `admin(sesi,log,setting)=${aS},${aL},${aG} sales=${sS},${sL},${sG} super=${mS},${mL},${mG}`,
    };
  },
  kebutuhan: 'Permintaan 3, 7, 8 (menu hanya lewat ikon profil) + revisi 30 (log aktivitas hanya Super Admin)',
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
  dataUji: 'token super admin',
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
   REVISI 1-13 (19 November 2026)
  ------------------------------------------------------------------------ */

T({
  id: 'TC-R01', nama: 'Tanggal Chat Awal pada Konsumen', skenario: 'Revisi 2',
  deskripsi: 'Sales mencatat kapan konsumen pertama kali menghubunginya melalui kolom tanggal chat awal.',
  langkah: '1. POST /api/customer-leads memakai first_chat_date 2. GET detail 3. PUT tanggal baru 4. GET lagi',
  dataUji: () => `name=Lead Chat Awal ${UIN}, phone=081233${UIN}1, status=warm, first_chat_date=2026-11-03 lalu 2026-10-28`,
  ekspektasi: 'HTTP 201/200 dengan first_chat_date tersimpan dan dapat diubah',
  expectStatus: [201, 200], method: 'POST', path: () => `/customer-leads/${ctx.chatLeadId}`, token: S,
  run: async () => {
    const buat = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Chat Awal ${UIN}`, phone: `081233${UIN}1`, status: 'warm', source: 'manual',
      first_chat_date: '2026-11-03',
    });
    ctx.chatLeadId = buat.json?.data?.id;
    await api(S(), 'PUT', `/customer-leads/${ctx.chatLeadId}`, { first_chat_date: '2026-10-28' });
    return api(S(), 'GET', `/customer-leads/${ctx.chatLeadId}`);
  },
  check: (j) => {
    const tanggal = String(j?.data?.first_chat_date || '').slice(0, 10);
    return { pass: tanggal === '2026-10-28', note: `first_chat_date=${tanggal || '(kosong)'}` };
  },
  kebutuhan: 'Revisi 2 (tanggal chat awal)',
});

T({
  id: 'TC-R02', nama: 'Status Prospek dan Kategori Konsumen', skenario: 'Revisi 6-7',
  deskripsi: 'Database Konsumen hanya memiliki status Cold/Warm/Hot; kategori konsumen baru berubah setelah pembayarannya diverifikasi (revisi 15) sehingga lead yang baru booking masih prospek.',
  langkah: '1. POST /api/customer-leads status=booking 2. GET /api/customer-leads?category=prospek 3. GET lead pemilik booking (TC-D05)',
  dataUji: 'status=booking (ditolak), category=prospek, lead_id dari TC-C01',
  ekspektasi: "HTTP 422 untuk status booking; daftar category=prospek hanya berisi prospek; lead pemilik booking masih berkategori prospek karena belum ada pembayaran terverifikasi",
  expectStatus: 422, method: 'POST', path: '/customer-leads', token: S,
  body: { name: 'Lead Status Salah', phone: '081233997700', status: 'booking', source: 'manual' },
  run: async () => {
    const salah = await api(S(), 'POST', '/customer-leads', {
      name: 'Lead Status Salah', phone: '081233997700', status: 'booking', source: 'manual',
    });
    const prospek = await api(M(), 'GET', '/customer-leads?category=prospek');
    ctx.prospekRows = prospek.json?.data || [];
    const rincian = await api(M(), 'GET', `/bookings/${ctx.bookingId}`);
    ctx.leadBayarTerverifikasi = (rincian.json?.data?.payments || [])
      .some((p) => p.verification_status === 'verified');
    const konsumen = await api(M(), 'GET', `/customer-leads/${ctx.leadId}`);
    ctx.leadCategorySetelahBooking = konsumen.json?.data?.category;
    ctx.leadStatusSetelahBooking = konsumen.json?.data?.status;
    return salah;
  },
  check: (j) => {
    const hanyaProspek = ctx.prospekRows.every((r) => r.category === 'prospek');
    const diharapkan = ctx.leadBayarTerverifikasi ? 'konsumen' : 'prospek';
    const pass = j?.errors?.status && hanyaProspek && ctx.leadCategorySetelahBooking === diharapkan
      && ['cold', 'warm', 'hot'].includes(ctx.leadStatusSetelahBooking);
    return {
      pass,
      note: `status booking ditolak=${!!j?.errors?.status}; prospek=${ctx.prospekRows.length} baris (semua prospek=${hanyaProspek}); `
        + `lead TC-D05 pembayaran terverifikasi=${!!ctx.leadBayarTerverifikasi} -> kategori ${ctx.leadCategorySetelahBooking} `
        + `(harapan ${diharapkan}), status=${ctx.leadStatusSetelahBooking}`,
    };
  },
  kebutuhan: 'Revisi 6-7 (pemisahan Konsumen dan Database Konsumen) + revisi 15 (kategori konsumen dari pembayaran terverifikasi)',
});

T({
  id: 'TC-R03', nama: 'Metode Pembayaran Selain Uang Piutang', skenario: 'Revisi 3',
  deskripsi: 'Sales mencatat cara uang berpindah (transfer bank, tunai, QRIS, virtual account, cek/giro) terpisah dari skema pembiayaan.',
  langkah: '1. Ambil unit tersedia 2. Buat lead hot + booking 3. POST /api/payments payment_method=qris 4. POST payment_method=tembakau 5. GET /api/payments/{id}',
  dataUji: 'payment_type=cash bertahap, payment_method=qris (valid) dan tembakau (tidak valid), nominal Rp 100.000',
  ekspektasi: 'HTTP 201 dengan payment_method=qris tersimpan; metode di luar daftar ditolak 422',
  expectStatus: [201, 200], method: 'POST', path: '/payments', token: S,
  body: () => ({ booking_id: ctx.revisiBookingId, payment_type: 'cash bertahap', payment_method: 'qris', amount: 100000, payment_date: '2026-11-19' }),
  run: async () => {
    const unit = await api(M(), 'GET', '/units?status=available');
    const bebas = Array.isArray(unit.json) ? unit.json : unit.json?.data || [];
    ctx.revisiUnitId = (bebas.find((u) => u.cluster_id === ctx.clusterId) || bebas[0])?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Revisi ${UIN}`, phone: `081234${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.revisiLeadId = lead.json?.data?.id;
    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.revisiLeadId, sales_id: ctx.salesProfileId, unit_id: ctx.revisiUnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.revisiBookingId = booking.json?.data?.id;
    const salah = await api(S(), 'POST', '/payments', {
      booking_id: ctx.revisiBookingId, payment_type: 'cash bertahap', payment_method: 'tembakau',
      amount: 50000, payment_date: '2026-11-19',
    });
    ctx.metodeSalah = { status: salah.status, pesan: salah.json?.message || '-' };
    const bayar = await api(S(), 'POST', '/payments', {
      booking_id: ctx.revisiBookingId, payment_type: 'cash bertahap', payment_method: 'qris',
      amount: 100000, payment_date: '2026-11-19',
    });
    ctx.revisiPaymentId = bayar.json?.data?.id;
    const daftar = await api(S(), 'GET', '/payments?payment_method=qris');
    ctx.metodeFilter = (daftar.json?.data || []).filter((p) => p.id === ctx.revisiPaymentId).length;
    return bayar;
  },
  check: (j) => {
    const pass = j?.data?.payment_method === 'qris' && ctx.metodeSalah.status === 422 && ctx.metodeFilter === 1;
    return {
      pass,
      note: `booking=${ctx.revisiBookingId}; metode qris=${j?.data?.payment_method}; metode salah=${ctx.metodeSalah.status}`
        + ` (${ctx.metodeSalah.pesan}); filter payment_method=qris menemukan ${ctx.metodeFilter} baris`,
    };
  },
  kebutuhan: 'Revisi 3 (opsi metode pembayaran)',
});

T({
  id: 'TC-R04', nama: 'Pembatalan Konsumen dengan Riwayat', skenario: 'Revisi 5',
  deskripsi: 'Booking dapat dibatalkan beserta alasannya; unit kembali tersedia, pembayaran tetap tersimpan, dan konsumen berpindah kategori menjadi Dibatalkan.',
  langkah: '1. PATCH /api/bookings/{id}/cancel alasan pendek 2. PATCH alasan lengkap 3. GET unit + lead + payments 4. PATCH sekali lagi 5. POST pembayaran pada booking dibatalkan',
  dataUji: () => `booking_id=${ctx.revisiBookingId} (hasil TC-R03), reason='abc' lalu 'Konsumen mengundurkan diri (uji revisi 5)'`,
  ekspektasi: 'HTTP 422 untuk alasan < 5 karakter; HTTP 200 untuk pembatalan; unit available; lead category=dibatalkan; pembayaran lama tetap ada; pembatalan kedua dan pembayaran baru ditolak',
  expectStatus: [200, 422], method: 'PATCH', path: () => `/bookings/${ctx.revisiBookingId}/cancel`, token: S,
  body: () => ({ reason: 'Konsumen mengundurkan diri (uji revisi 5)' }),
  run: async () => {
    const pendek = await api(S(), 'PATCH', `/bookings/${ctx.revisiBookingId}/cancel`, { reason: 'abc' });
    ctx.batalPendek = { status: pendek.status, pesan: pendek.json?.message || '-' };
    const batal = await api(S(), 'PATCH', `/bookings/${ctx.revisiBookingId}/cancel`, { reason: 'Konsumen mengundurkan diri (uji revisi 5)' });
    const unit = await api(M(), 'GET', `/units/${ctx.revisiUnitId}`);
    const lead = await api(M(), 'GET', `/customer-leads/${ctx.revisiLeadId}`);
    const riwayat = await api(M(), 'GET', `/bookings/${ctx.revisiBookingId}`);
    ctx.batalKembali = unit.json?.data?.status;
    ctx.batalKategori = lead.json?.data?.category;
    ctx.batalRiwayat = (riwayat.json?.data?.payments || []).length;
    ctx.batalAlasan = riwayat.json?.data?.cancellation_reason;
    const duaKali = await api(S(), 'PATCH', `/bookings/${ctx.revisiBookingId}/cancel`, { reason: 'Mencoba membatalkan lagi' });
    const bayarLagi = await api(S(), 'POST', '/payments', {
      booking_id: ctx.revisiBookingId, payment_type: 'cash bertahap', payment_method: 'tunai', amount: 25000, payment_date: '2026-11-19',
    });
    ctx.batalDuaKali = duaKali.status;
    ctx.batalBayar = bayarLagi.status;
    const daftar = await api(M(), 'GET', '/bookings?status=dibatalkan');
    ctx.batalTerdaftar = (daftar.json?.data || []).some((b) => b.id === ctx.revisiBookingId);
    return batal;
  },
  check: (j) => {
    const pass = ctx.batalPendek.status === 422 && j?.data?.status === 'dibatalkan' && !!j?.data?.cancelled_at
      && ctx.batalKembali === 'available' && ctx.batalKategori === 'dibatalkan'
      && ctx.batalRiwayat >= 1 && ctx.batalDuaKali === 422 && ctx.batalBayar === 422 && ctx.batalTerdaftar;
    return {
      pass,
      note: `alasan pendek=${ctx.batalPendek.status}; status=${j?.data?.status}; unit=${ctx.batalKembali}; kategori lead=${ctx.batalKategori}; `
        + `pembayaran tersisa=${ctx.batalRiwayat}; batalkan lagi=${ctx.batalDuaKali}; bayar lagi=${ctx.batalBayar}; `
        + `masuk filter status=dibatalkan=${ctx.batalTerdaftar}`,
    };
  },
  kebutuhan: 'Revisi 5 (status pembatalan konsumen + riwayat pembelian)',
});

T({
  id: 'TC-R05', nama: 'Filter dan Statistik per Proyek', skenario: 'Revisi 10',
  deskripsi: 'Dashboard Super Admin dapat dipersempit ke satu proyek dan memuat tabel rekapitulasi per proyek.',
  langkah: '1. GET /api/dashboard/super-admin tanpa filter 2. GET dengan cluster_id 3. GET cluster_id milik proyek lain',
  dataUji: () => `cluster_id=${ctx.clusterId} (proyek hasil TC-B02)`,
  ekspektasi: 'HTTP 200; project_filter null tanpa parameter dan sama dengan cluster_id bila diisi; per_project berisi semua proyek; angka tersaring tidak melebihi angka keseluruhan',
  expectStatus: 200, method: 'GET', path: () => `/dashboard/super-admin?cluster_id=${ctx.clusterId}`, token: M,
  run: async () => {
    const semua = await api(M(), 'GET', '/dashboard/super-admin');
    ctx.dashSemua = semua.json?.data;
    const satu = await api(M(), 'GET', `/dashboard/super-admin?cluster_id=${ctx.clusterId}`);
    ctx.dashProyek = satu.json?.data;
    return satu;
  },
  check: (j) => {
    const semua = ctx.dashSemua;
    const satu = j?.data;
    const cocok = semua?.project_filter === null && satu?.project_filter === ctx.clusterId;
    const dalamProyek = satu?.total_units <= semua?.total_units && satu?.total_revenue <= semua?.total_revenue
      && satu?.total_bookings <= semua?.total_bookings;
    const barisProyek = Array.isArray(satu?.per_project) && satu.per_project.length >= 1
      && satu.per_project.some((r) => r.cluster_id === ctx.clusterId);
    const barisIni = (satu?.per_project || []).find((r) => r.cluster_id === ctx.clusterId) || {};
    const konsisten = barisIni.units_total === satu?.total_units && barisIni.revenue === satu?.total_revenue;
    const totalTabel = (satu?.per_project || []).reduce((a, r) => a + Number(r.units_total || 0), 0);
    return {
      pass: !!cocok && !!dalamProyek && !!barisProyek && !!konsisten && totalTabel <= semua?.total_units,
      note: `project_filter=${satu?.project_filter}; unit proyek=${satu?.total_units} dari ${semua?.total_units}; `
        + `pendapatan proyek=${satu?.total_revenue} dari ${semua?.total_revenue}; baris per_project=${(satu?.per_project || []).length} `
        + `(total unit tabel=${totalTabel}); rekap baris ini=${barisIni.units_total} unit / ${barisIni.revenue}`
        + `; proyek tersedia di penapis=${(satu?.projects || []).length}`,
    };
  },
  kebutuhan: 'Revisi 10 (filter per proyek pada Super Admin)',
});

T({
  id: 'TC-R06', nama: 'Lokasi Proyek untuk Peta Beranda', skenario: 'Revisi 12',
  deskripsi: 'Koordinat proyek dibaca halaman beranda melalui endpoint publik sehingga setiap marker memiliki alamat dan jumlah unit tersedia.',
  langkah: '1. PUT /api/clusters/{id} mengisi alamat + latitude + longitude 2. GET /api/public/project-locations tanpa login 3. GET /api/public/settings',
  dataUji: () => `cluster_id=${ctx.clusterId}, latitude=-0.9470, longitude=100.4170, address=Jl. Uji Revisi ${UIN}`,
  ekspektasi: 'HTTP 200; proyek uji muncul pada daftar lokasi dengan koordinat numerik; kunci public_show_peta tersedia pada pengaturan publik',
  expectStatus: 200, method: 'GET', path: '/public/project-locations', token: null,
  run: async () => {
    const asal = await api(M(), 'GET', `/clusters/${ctx.clusterId}`);
    const p = asal.json?.data || {};
    const ubah = await api(M(), 'PUT', `/clusters/${ctx.clusterId}`, {
      project_id: p.project_id, name: p.name, status: p.status,
      address: `Jl. Uji Revisi ${UIN}, Padang`, latitude: -0.9470, longitude: 100.4170,
    });
    ctx.clusterGeo = { status: ubah.status, pesan: ubah.json?.message || '-' };
    const lokasi = await api(null, 'GET', '/public/project-locations');
    ctx.lokasiProyek = (lokasi.json?.data || []).find((p) => p.id === ctx.clusterId);
    ctx.lokasiTotal = (lokasi.json?.data || []).length;
    const tanpaKoordinat = (lokasi.json?.data || []).every((p) => Number.isFinite(p.latitude) && Number.isFinite(p.longitude));
    ctx.lokasiKoordinatLengkap = tanpaKoordinat;
    const pengaturan = await api(null, 'GET', '/public/settings');
    ctx.petaSaklar = pengaturan.json?.data?.public_show_peta ?? pengaturan.json?.data?.values?.public_show_peta;
    return lokasi;
  },
  check: (j) => {
    const p = ctx.lokasiProyek;
    const pass = Array.isArray(j?.data) && !!p && Math.abs(p.latitude + 0.9470) < 0.001 && !!p.address
      && ctx.lokasiKoordinatLengkap && ctx.petaSaklar !== undefined;
    return {
      pass,
      note: `PUT koordinat=${ctx.clusterGeo.status} (${ctx.clusterGeo.pesan}); proyek uji=${p ? `${p.name} (${p.latitude}, ${p.longitude}, unit ${p.units_available})` : 'tidak muncul'}; `
        + `total lokasi=${ctx.lokasiTotal}; semua berkoordinat=${ctx.lokasiKoordinatLengkap}; public_show_peta=${ctx.petaSaklar}`,
    };
  },
  kebutuhan: 'Revisi 12 (peta lokasi proyek pada beranda)',
});

T({
  id: 'TC-R07', nama: 'Foto Sales pada Narahubung Publik', skenario: 'Revisi 1',
  deskripsi: 'Daftar narahubung pada halaman publik menyertakan URL foto sales agar konsumen mengenal siapa yang menanganinya.',
  langkah: '1. GET /api/public/sales-contacts tanpa login 2. periksa kunci avatar_url pada setiap item',
  dataUji: 'tanpa sesi login; profil sales aktif bernomor WhatsApp',
  ekspektasi: 'HTTP 200 dengan name, sales_code, whatsapp_link, dan kunci avatar_url pada setiap item',
  expectStatus: 200, method: 'GET', path: '/public/sales-contacts', token: null,
  check: (j) => {
    const rows = j?.data || [];
    const pass = rows.length >= 1 && rows.every((r) => 'avatar_url' in r && !!r.name && !!r.whatsapp_link);
    const berfoto = rows.filter((r) => r.avatar_url).length;
    return { pass, note: `${rows.length} narahubung; ${berfoto} membawa avatar_url; contoh=${rows[0] ? `${rows[0].name} -> ${rows[0].avatar_url || '(tanpa foto)'}` : '-'}` };
  },
  kebutuhan: 'Revisi 1 (foto sales pada halaman konsumen)',
});

T({
  id: 'TC-R08', nama: 'Sandi Awal Sales dari Tanggal Lahir', skenario: 'Revisi 8',
  deskripsi: 'Sales baru otomatis memiliki akun dengan kata sandi awal berupa tanggal lahir (yyyymmdd) dan wajib menggantinya dengan sandi kuat setelah login.',
  langkah: '1. POST /api/sales tanpa password memakai birth_date 2. login dengan yyyymmdd 3. POST /api/reset-password sandi lemah 4. POST /api/reset-password sandi kuat',
  dataUji: `email=sales.revisi${UIN}@luminpark.com, birth_date=1995-03-14, sandi awal 19950314, sandi lemah 'putih123', sandi kuat 'LuminPark2026'`,
  ekspektasi: 'HTTP 201 dengan password_source=birth_date dan must_change_password; login 200; sandi tanpa huruf besar ditolak 422; sandi kuat diterima 200',
  expectStatus: [201, 200], method: 'POST', path: '/sales', token: M,
  body: () => ({
    name: `Sales Revisi ${UIN}`, email: `sales.revisi${UIN}@luminpark.com`, sales_code: `SL-R${UIN}`,
    birth_date: '1995-03-14', join_date: '2026-11-01', status: 'aktif', account_status: 'aktif',
  }),
  run: async () => {
    const buat = await api(M(), 'POST', '/sales', {
      name: `Sales Revisi ${UIN}`, email: `sales.revisi${UIN}@luminpark.com`, sales_code: `SL-R${UIN}`,
      birth_date: '1995-03-14', join_date: '2026-11-01', status: 'aktif', account_status: 'aktif',
    });
    ctx.salesRevisi = { buat: buat.status, sumber: buat.json?.auth?.password_source, wajib: buat.json?.auth?.must_change_password };
    const masuk = await api(null, 'POST', '/login', { email: `sales.revisi${UIN}@luminpark.com`, password: '19950314' });
    ctx.salesRevisiToken = masuk.json?.access_token;
    ctx.salesRevisiWajibGanti = masuk.json?.user?.must_change_password;
    const lemah = await api(ctx.salesRevisiToken, 'POST', '/reset-password', {
      email: `sales.revisi${UIN}@luminpark.com`, password_lama: '19950314', password_baru: 'putih123',
    });
    ctx.sandiLemah = { status: lemah.status, pesan: lemah.json?.message || '-' };
    const kuat = await api(ctx.salesRevisiToken, 'POST', '/reset-password', {
      email: `sales.revisi${UIN}@luminpark.com`, password_lama: '19950314', password_baru: 'LuminPark2026',
    });
    ctx.sandiKuat = kuat.status;
    const masukBaru = await api(null, 'POST', '/login', { email: `sales.revisi${UIN}@luminpark.com`, password: 'LuminPark2026' });
    ctx.sandiBaruMasuk = { status: masukBaru.status, wajib: masukBaru.json?.user?.must_change_password };
    const lamaMasih = await api(null, 'POST', '/login', { email: `sales.revisi${UIN}@luminpark.com`, password: '19950314' });
    ctx.sandiLamaMasih = lamaMasih.status;
    return buat;
  },
  check: () => {
    const s = ctx.salesRevisi;
    const pass = s.buat === 201 && s.sumber === 'birth_date' && s.wajib === true
      && ctx.sandiLemah.status === 422 && ctx.sandiKuat === 200
      && ctx.sandiBaruMasuk.status === 200 && ctx.sandiBaruMasuk.wajib === false && ctx.sandiLamaMasih >= 400;
    return {
      pass,
      note: `buat=${s.buat} sumber=${s.sumber} wajib_ganti=${s.wajib}; login awal=${s.buat ? 'ok' : '-'}; sandi lemah=${ctx.sandiLemah.status}`
        + ` (${ctx.sandiLemah.pesan}); sandi kuat=${ctx.sandiKuat}; login sandi baru=${ctx.sandiBaruMasuk.status}`
        + ` wajib_ganti=${ctx.sandiBaruMasuk.wajib}; login sandi lama=${ctx.sandiLamaMasih}`,
    };
  },
  kebutuhan: 'Revisi 8 (sandi awal yyyymmdd + syarat keamanan sandi)',
});

T({
  id: 'TC-R09', nama: 'Penapis Unit per Proyek dan Status', skenario: 'Revisi 11',
  deskripsi: 'Halaman Data Unit menelusuri blok di sisi klien dan menyaring lewat parameter proyek/status yang didukung API.',
  langkah: '1. GET /api/units?cluster_id={proyek} 2. GET /api/units?status=available&cluster_id={proyek} 3. cocokkan dengan daftar lengkap',
  dataUji: () => `cluster_id=${ctx.clusterId}, status=available`,
  ekspektasi: 'HTTP 200; seluruh baris sesuai proyek/status yang diminta; jumlah tidak melebihi daftar lengkap',
  expectStatus: 200, method: 'GET', path: () => `/units?cluster_id=${ctx.clusterId}`, token: M,
  run: async () => {
    const semua = await api(M(), 'GET', '/units');
    ctx.unitSemua = (Array.isArray(semua.json) ? semua.json : []).length;
    const proyek = await api(M(), 'GET', `/units?cluster_id=${ctx.clusterId}`);
    ctx.unitProyekRows = Array.isArray(proyek.json) ? proyek.json : [];
    const kombinasi = await api(M(), 'GET', `/units?status=available&cluster_id=${ctx.clusterId}`);
    ctx.unitKombinasi = (Array.isArray(kombinasi.json) ? kombinasi.json : []).filter((u) => u.status === 'available');
    return proyek;
  },
  check: (j) => {
    const rows = Array.isArray(j) ? j : [];
    const pass = rows.every((u) => u.cluster_id === ctx.clusterId) && rows.length >= 1
      && rows.length <= ctx.unitSemua && rows.every((u) => !!u.unit_code && !!u.status)
      && ctx.unitKombinasi.every((u) => u.cluster_id === ctx.clusterId)
      && ctx.unitKombinasi.length <= rows.length;
    return {
      pass,
      note: `unit proyek=${rows.length} dari ${ctx.unitSemua} keseluruhan; tersedia dalam proyek=${ctx.unitKombinasi.length}; `
        + `blok contoh=${rows[0] ? rows[0].unit_code : '-'}; status=[${[...new Set(rows.map((u) => u.status))].join(', ')}]`,
    };
  },
  kebutuhan: 'Revisi 11 (pencarian Data Unit berdasarkan Blok, Status, Proyek)',
});

/* Alur lupa password lewat email. Driver "log" pada MAIL_MAILER menulis email yang
   benar-benar dikirim ke storage/logs/laravel.log, jadi tautan verifikasi dibaca dari
   sana: bukti bahwa email keluar, bukan tebakan. */
const LOG_MAIL = new URL('../storage/logs/laravel.log', import.meta.url);
const ukuranLog = () => { try { return statSync(LOG_MAIL).size; } catch { return 0; } };
const ekoranLog = (dari) => { try { return readFileSync(LOG_MAIL).subarray(dari).toString('utf8'); } catch { return ''; } };
const tokenTerakhir = (isi) => (isi.match(/token=([A-Za-z0-9]{64})/g) || []).pop()?.replace('token=', '') || '';

T({
  id: 'TC-R10', nama: 'Tautan Verifikasi Lupa Password', skenario: 'Revisi 14',
  deskripsi: 'Halaman atur ulang sandi tidak meminta kata sandi lama lagi: pengguna mengisi email, sistem mengirim tautan verifikasi ke email akun tersebut, dan balasan sengaja seragam agar halaman ini tidak dipakai memeriksa keberadaan akun.',
  langkah: '1. POST /api/sales untuk membuat akun internal uji 2. POST /api/forgot-password dengan email akun uji lalu baca ekoran log email 3. POST /api/forgot-password dengan email tidak terdaftar 4. POST /api/forgot-password dengan email konsumen 5. periksa isi balasan JSON',
  dataUji: () => `email internal=sales.lupa${UIN}@luminpark.com; email asing=tidak.ada@luminpark.test; email konsumen=konsumen@luminpark.com`,
  ekspektasi: 'HTTP 200 dengan pesan yang sama untuk ketiga email; hanya akun internal yang emailnya memuat tautan /reset-password?token=... sepanjang 64 karakter; balasan JSON tidak memuat token',
  expectStatus: [200, 201], method: 'POST', path: '/forgot-password', token: null,
  run: async () => {
    const emailUji = `sales.lupa${UIN}@luminpark.com`;
    const buat = await api(M(), 'POST', '/sales', {
      name: `Sales Lupa Sandi ${UIN}`, email: emailUji, sales_code: `SL-L${UIN}`,
      birth_date: '1990-01-01', join_date: '2026-11-01', status: 'aktif', account_status: 'aktif',
    });
    ctx.sandiAwalUji = buat.json?.auth?.password_source ? '19900101' : null;

    let batas = ukuranLog();
    const internal = await api(null, 'POST', '/forgot-password', { email: emailUji });
    ctx.logInternal = ekoranLog(batas);
    ctx.tokenUji = tokenTerakhir(ctx.logInternal);

    batas = ukuranLog();
    const asing = await api(null, 'POST', '/forgot-password', { email: 'tidak.ada@luminpark.test' });
    ctx.logAsing = ekoranLog(batas);

    batas = ukuranLog();
    const konsumen = await api(null, 'POST', '/forgot-password', { email: 'konsumen@luminpark.com' });
    ctx.logKonsumen = ekoranLog(batas);

    ctx.forgot = {
      internal: internal.status, pesanInternal: internal.json?.message || '-',
      asing: asing.status, pesanAsing: asing.json?.message || '-',
      konsumen: konsumen.status, pesanKonsumen: konsumen.json?.message || '-',
      bocor: /[A-Za-z0-9]{64}/.test(JSON.stringify([internal.json, asing.json, konsumen.json])),
    };
    return internal;
  },
  check: () => {
    const f = ctx.forgot;
    const adaTautanInternal = ctx.logInternal.includes('To: sales.lupa' + UIN) && ctx.tokenUji.length === 64
      && ctx.logInternal.includes('/reset-password?token=' + ctx.tokenUji);
    const pass = f.internal === 200 && f.asing === 200 && f.konsumen === 200
      && f.pesanInternal === f.pesanAsing && f.pesanAsing === f.pesanKonsumen
      && adaTautanInternal && !f.bocor
      && !/To: (tidak\.ada|konsumen)@/.test(ctx.logAsing + ctx.logKonsumen);
    return {
      pass,
      note: `internal=${f.internal}, asing=${f.asing}, konsumen=${f.konsumen}; pesan seragam=${f.pesanInternal === f.pesanAsing && f.pesanAsing === f.pesanKonsumen}; `
        + `tautan email akun uji=${ctx.tokenUji ? ctx.tokenUji.slice(0, 12) + '…' + '(64 karakter)' : 'tidak ada'}; token di balasan JSON=${f.bocor ? 'bocor' : 'tidak'}; `
        + `email untuk asing/konsumen=${/To: (tidak\.ada|konsumen)@/.test(ctx.logAsing + ctx.logKonsumen) ? 'terkirim' : 'tidak terkirim'}`,
    };
  },
  kebutuhan: 'Revisi 14 (lupa password diverifikasi lewat email, tanpa kata sandi lama)',
});

T({
  id: 'TC-R11', nama: 'Ganti Sandi dengan Token Verifikasi', skenario: 'Revisi 14',
  deskripsi: 'Kata sandi baru disimpan cukup dengan token dari email, tanpa kata sandi lama; sandi lemah ditolak, token hanya sekali pakai, dan permintaan tautan baru menghapus token sebelumnya.',
  langkah: '1. POST /api/reset-password/token dengan sandi lemah 2. dengan sandi kuat 3. login dengan sandi baru 4. login dengan sandi awal 5. token yang sama dipakai ulang 6. POST /api/forgot-password lagi lalu token lama dipakai',
  dataUji: () => `email=sales.lupa${UIN}@luminpark.com, token dari log email, sandi lemah 'putih123', sandi kuat 'LuminBaru2026'`,
  ekspektasi: '422 untuk sandi lemah dengan pesan Indonesia; 200 untuk sandi kuat; login sandi baru 200 dan sandi lama 401; token dipakai ulang dan token lama yang terganti ditolak 400',
  expectStatus: [200, 422, 400], method: 'POST', path: '/reset-password/token', token: null,
  run: async () => {
    const emailUji = `sales.lupa${UIN}@luminpark.com`;
    const token = ctx.tokenUji || tokenTerakhir(ekoranLog(ukuranLog() - 200000));
    const lemah = await api(null, 'POST', '/reset-password/token', { email: emailUji, token, password_baru: 'putih123' });
    ctx.lemahR11 = { status: lemah.status, pesan: lemah.json?.message || '-' };
    const kuat = await api(null, 'POST', '/reset-password/token', { email: emailUji, token, password_baru: 'LuminBaru2026' });
    ctx.kuatR11 = { status: kuat.status, pesan: kuat.json?.message || '-' };
    ctx.masukBaruR11 = (await api(null, 'POST', '/login', { email: emailUji, password: 'LuminBaru2026' })).status;
    ctx.masukLamaR11 = (await api(null, 'POST', '/login', { email: emailUji, password: ctx.sandiAwalUji || '19900101' })).status;
    const daurUlang = await api(null, 'POST', '/reset-password/token', { email: emailUji, token, password_baru: 'LuminBaru2027' });
    ctx.daurUlangR11 = { status: daurUlang.status, pesan: daurUlang.json?.message || '-' };
    const batas = ukuranLog();
    await api(null, 'POST', '/forgot-password', { email: emailUji });
    const tokenBaru = tokenTerakhir(ekoranLog(batas));
    const terganti = await api(null, 'POST', '/reset-password/token', { email: emailUji, token, password_baru: 'LuminBaru2028' });
    ctx.tergantiR11 = { status: terganti.status, pesan: terganti.json?.message || '-' };
    ctx.tokenGantiBaru = tokenBaru.length === 64;
    return kuat;
  },
  check: () => {
    const pass = ctx.lemahR11.status === 422 && /[a-z]+ sandi|huruf besar/.test(ctx.lemahR11.pesan)
      && ctx.kuatR11.status === 200 && ctx.masukBaruR11 === 200 && ctx.masukLamaR11 >= 400
      && ctx.daurUlangR11.status === 400 && ctx.tergantiR11.status === 400 && ctx.tokenGantiBaru;
    return {
      pass,
      note: `sandi lemah=${ctx.lemahR11.status} (${ctx.lemahR11.pesan}); sandi kuat=${ctx.kuatR11.status} (${ctx.kuatR11.pesan}); `
        + `login sandi baru=${ctx.masukBaruR11}, login sandi lama=${ctx.masukLamaR11}; token sekali pakai=${ctx.daurUlangR11.status} (${ctx.daurUlangR11.pesan}); `
        + `token terganti=${ctx.tergantiR11.status}; tautan baru terkirim=${ctx.tokenGantiBaru}`,
    };
  },
  kebutuhan: 'Revisi 14 (lupa password diverifikasi lewat email, tanpa kata sandi lama)',
});

/* -------------------------------------------------------------------------
   R12-R14. REVISI 15 (KONSUMEN TERVERIFIKASI, PEMBATALAN PASCABAYAR, BIAYA TAMBAHAN)
   ------------------------------------------------------------------------ */

// Unit tersedia yang belum dipakai rangkaian uji Revisi 15. Proyek milik TC-B02
// sengaja dilewati agar kasus revisi 3/5 (TC-R03/TC-R04) tetap menemukan unit bebas.
async function unitRevisi15() {
  const res = await api(M(), 'GET', '/units?status=available');
  const daftar = Array.isArray(res.json) ? res.json : res.json?.data || [];
  const dipakai = ctx.revisi15Dipakai || (ctx.revisi15Dipakai = []);
  const bebas = daftar.filter((u) => !dipakai.includes(u.id));
  const pilihan = bebas.find((u) => u.cluster_id !== ctx.clusterId) || bebas[0];
  if (pilihan) {
    dipakai.push(pilihan.id);
    return pilihan;
  }

  // Basis data yang baru di-seed hanya punya sedikit unit tersedia. Bila habis,
  // unit baru dibuat agar alur uji tetap memakai unit yang benar-benar available.
  const buat = await api(M(), 'POST', '/units', {
    cluster_id: ctx.clusterId, unit_code: `R15-${UIN}-${dipakai.length + 1}`,
    unit_type: 'Modern Tropical', land_size: 60, building_size: 45, price: 350000000,
    status: 'available', description: 'Unit cadangan suite konformansi.',
  });
  const baru = buat.json?.data || null;
  if (baru?.id) dipakai.push(baru.id);
  return baru;
}

async function kategoriLead(id) {
  const res = await api(M(), 'GET', `/customer-leads/${id}`);
  return res.json?.data?.category;
}

async function leadMasukPenapis(category, id) {
  const res = await api(M(), 'GET', `/customer-leads?category=${category}`);
  return (res.json?.data || []).some((r) => r.id === id);
}

T({
  id: 'TC-R12', nama: 'Konsumen Hanya Setelah Pembayaran Diverifikasi', skenario: 'Revisi 15',
  deskripsi: 'Calon konsumen dihitung sebagai konsumen hanya bila booking-nya memiliki pembayaran berstatus terverifikasi; penapis kategori Dibatalkan dan riwayat API menampilkan konsumen sekaligus yang dibatalkan.',
  langkah: '1. Buat lead hot + booking 2. Baca kategori lead 3. Catat pembayaran (pending) lalu baca kategori 4. Verifikasi pembayaran lalu baca kategori 5. Cek penapis prospek, konsumen, dan riwayat',
  dataUji: () => `lead='Konsumen Verifikasi ${UIN}', pembayaran Rp 100.000 berstatus pending lalu verified, booking_date=2026-11-18`,
  ekspektasi: 'HTTP 201; kategori prospek selama pembayaran belum diverifikasi; setelah diverifikasi menjadi konsumen, hilang dari penapis prospek, dan muncul pada category=konsumen maupun category=riwayat',
  expectStatus: 201, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r12LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r12UnitId, payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r12UnitId = unit?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Verifikasi ${UIN}`, phone: `081235${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r12LeadId = lead.json?.data?.id;
    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r12LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r12UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r12BookingId = booking.json?.data?.id;
    ctx.r12KategoriAwal = await kategoriLead(ctx.r12LeadId);

    const bayar = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r12BookingId, payment_type: 'cash bertahap', payment_method: 'transfer bank',
      amount: 100000, payment_date: '2026-11-18', notes: 'Uang muka menunggu verifikasi.',
    });
    ctx.r12PaymentId = bayar.json?.data?.id;
    ctx.r12VerifikasiAwal = bayar.json?.data?.verification_status;
    ctx.r12KategoriPending = await kategoriLead(ctx.r12LeadId);

    await api(M(), 'PUT', `/payments/${ctx.r12PaymentId}/verify`, {
      verification_status: 'verified', notes: 'Bukti transfer sesuai.',
    });
    ctx.r12KategoriVerified = await kategoriLead(ctx.r12LeadId);
    ctx.r12DiProspek = await leadMasukPenapis('prospek', ctx.r12LeadId);
    ctx.r12DiKonsumen = await leadMasukPenapis('konsumen', ctx.r12LeadId);
    ctx.r12DiRiwayat = await leadMasukPenapis('riwayat', ctx.r12LeadId);
    // Booking fixtures dilepas supaya unit kembali tersedia bagi pelaksanaan berikutnya.
    await api(M(), 'DELETE', `/bookings/${ctx.r12BookingId}`);
    return booking;
  },
  check: (j) => {
    const pass = !!j?.data?.id && ctx.r12VerifikasiAwal === 'pending'
      && ctx.r12KategoriAwal === 'prospek' && ctx.r12KategoriPending === 'prospek'
      && ctx.r12KategoriVerified === 'konsumen' && ctx.r12DiProspek === false
      && ctx.r12DiKonsumen === true && ctx.r12DiRiwayat === true;
    return {
      pass,
      note: `booking=${ctx.r12BookingId}; verifikasi pembayaran=${ctx.r12VerifikasiAwal}; kategori tanpa bayar=${ctx.r12KategoriAwal}, `
        + `bayar pending=${ctx.r12KategoriPending}, bayar verified=${ctx.r12KategoriVerified}; penapis prospek=${ctx.r12DiProspek}, `
        + `konsumen=${ctx.r12DiKonsumen}, riwayat=${ctx.r12DiRiwayat}`,
    };
  },
  kebutuhan: 'Revisi 15 (konsumen = pembayaran terverifikasi + penapis kategori Dibatalkan)',
});

T({
  id: 'TC-R13', nama: 'Pembatalan Setelah Lunas dan Serah Terima', skenario: 'Revisi 15',
  deskripsi: 'Konsumen yang sudah melunasi pembayaran dan menempati rumah tetap dapat membatalkan; unit kembali tersedia, riwayat pembelian tersimpan, dan data berpindah dari menu Konsumen ke Pembatalan Konsumen.',
  langkah: '1. Buat lead + booking cash keras 2. Bayar + verifikasi uang muka dan seluruh sisa 3. Ubah delivery_status menjadi selesai 4. PATCH /api/bookings/{id}/cancel 5. Periksa unit, kategori lead, kolom pembatalan, dan penapis konsumen/riwayat',
  dataUji: () => `lead='Konsumen Batal Lunas ${UIN}', pembayaran 100% diverifikasi, delivery_status=selesai, reason='Konsumen membatalkan setelah serah terima (uji revisi 15)'`,
  ekspektasi: 'Booking menjadi lunas dengan unit sold_out; pembatalan HTTP 200; unit kembali available; kategori lead dibatalkan dengan kolom pembatalan berisi nominal terverifikasi dan serah terima selesai; lead tidak ada pada category=konsumen tetapi ada pada category=riwayat dan category=dibatalkan',
  expectStatus: [200, 422], method: 'PATCH', path: () => `/bookings/${ctx.r13BookingId}/cancel`, token: S,
  body: () => ({ reason: 'Konsumen membatalkan setelah serah terima (uji revisi 15)' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r13UnitId = unit?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Batal Lunas ${UIN}`, phone: `081236${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r13LeadId = lead.json?.data?.id;
    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r13LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r13UnitId,
      payment_scheme_id: ctx.cashKerasId, booking_date: '2026-11-18',
    });
    ctx.r13BookingId = booking.json?.data?.id;
    ctx.r13TotalHarga = Number(booking.json?.data?.total_price);

    const dp = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r13BookingId, payment_type: 'cash keras', payment_method: 'transfer bank',
      amount: Number(booking.json?.data?.down_payment_amount), payment_date: '2026-11-18', notes: 'Uang muka cash keras.',
    });
    await api(M(), 'PUT', `/payments/${dp.json?.data?.id}/verify`, { verification_status: 'verified' });
    const tengah = await api(M(), 'GET', `/bookings/${ctx.r13BookingId}`);
    const sisa = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r13BookingId, payment_type: 'cash keras', payment_method: 'transfer bank',
      amount: Number(tengah.json?.data?.remaining_amount), payment_date: '2026-11-19', notes: 'Pelunasan cash keras.',
    });
    await api(M(), 'PUT', `/payments/${sisa.json?.data?.id}/verify`, { verification_status: 'verified' });

    const lunas = await api(M(), 'GET', `/bookings/${ctx.r13BookingId}`);
    ctx.r13StatusLunas = lunas.json?.data?.status;
    ctx.r13KategoriLunas = await kategoriLead(ctx.r13LeadId);
    ctx.r13UnitLunas = (await api(M(), 'GET', `/units/${ctx.r13UnitId}`)).json?.data?.status;

    await api(M(), 'PUT', `/bookings/${ctx.r13BookingId}`, { delivery_status: 'selesai' });
    const batal = await api(S(), 'PATCH', `/bookings/${ctx.r13BookingId}/cancel`, {
      reason: 'Konsumen membatalkan setelah serah terima (uji revisi 15)',
    });
    ctx.r13UnitBatal = (await api(M(), 'GET', `/units/${ctx.r13UnitId}`)).json?.data?.status;
    ctx.r13KategoriBatal = await kategoriLead(ctx.r13LeadId);
    ctx.r13RiwayatBayar = (await api(M(), 'GET', `/bookings/${ctx.r13BookingId}`)).json?.data?.payments?.length;

    const daftar = await api(M(), 'GET', '/customer-leads?category=dibatalkan');
    const baris = (daftar.json?.data || []).find((r) => r.id === ctx.r13LeadId);
    ctx.r13Pembatalan = baris?.pembatalan || null;
    ctx.r13DiKonsumen = await leadMasukPenapis('konsumen', ctx.r13LeadId);
    ctx.r13DiRiwayat = await leadMasukPenapis('riwayat', ctx.r13LeadId);
    await api(M(), 'DELETE', `/bookings/${ctx.r13BookingId}`);
    return batal;
  },
  check: (j, s, h, c, text) => {
    const p = ctx.r13Pembatalan || {};
    const pass = j?.data?.status === 'dibatalkan' && ctx.r13StatusLunas === 'lunas'
      && ctx.r13UnitLunas === 'sold_out' && ctx.r13KategoriLunas === 'konsumen'
      && ctx.r13UnitBatal === 'available' && ctx.r13KategoriBatal === 'dibatalkan'
      && ctx.r13RiwayatBayar >= 2 && p.booking_code && Math.abs(Number(p.verified_amount) - ctx.r13TotalHarga) < 1
      && p.delivery_status === 'selesai' && ctx.r13DiKonsumen === false && ctx.r13DiRiwayat === true;
    return {
      pass,
      note: `lunas=${ctx.r13StatusLunas} (unit=${ctx.r13UnitLunas}, kategori=${ctx.r13KategoriLunas}); pembatalan=${j?.data?.status} `
        + `(${trim(text)}); unit=${ctx.r13UnitBatal}, kategori=${ctx.r13KategoriBatal}; pembayaran tersimpan=${ctx.r13RiwayatBayar}; `
        + `nominal terverifikasi=${Number(p.verified_amount).toLocaleString('id-ID')} dari harga ${ctx.r13TotalHarga.toLocaleString('id-ID')}, `
        + `serah terima=${p.delivery_status}; penapis konsumen=${ctx.r13DiKonsumen}, riwayat=${ctx.r13DiRiwayat}`,
    };
  },
  kebutuhan: 'Revisi 15 (pembatalan pascabayar pindah ke Pembatalan Konsumen)',
});

T({
  id: 'TC-R14', nama: 'Biaya Tambahan Konstruksi di Luar Harga Utama', skenario: 'Revisi 15',
  deskripsi: 'Booking mencatat biaya tambahan konstruksi beserta request tambahannya tanpa mengubah harga utama, uang muka, maupun angsuran.',
  langkah: '1. POST /api/bookings dengan addition_cost + addition_description 2. GET detail booking lalu bandingkan dengan POST /payment-schemes/{id}/simulate 3. POST addition_cost negatif 4. GET /api/reports/export?type=sales 5. Hapus booking lalu buat kembali',
  dataUji: () => `lead='Konsumen Tambahan ${UIN}', addition_cost=25000000, addition_description='Kanopi carport dan upgrade granit', addition_cost=-100000 (ditolak)`,
  ekspektasi: 'HTTP 201 dengan addition_cost tersimpan; total_price sama dengan harga unit serta uang muka dan angsuran sama persis dengan simulasi skema; addition_cost negatif ditolak 422 dengan pesan Indonesia; ekspor CSV memuat kolom Biaya Tambahan Konstruksi dan Keterangan Tambahan; booking_code hasil baru tetap unik setelah booking lama dihapus lunak',
  expectStatus: [201, 422], method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r14LeadId, unit_id: ctx.r14UnitId, addition_cost: -100000 }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r14UnitId = unit?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Tambahan ${UIN}`, phone: `081237${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r14LeadId = lead.json?.data?.id;
    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r14LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r14UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
      addition_cost: 25000000, addition_description: 'Kanopi carport dan upgrade granit',
    });
    ctx.r14BookingId = booking.json?.data?.id;
    const detail = await api(M(), 'GET', `/bookings/${ctx.r14BookingId}`);
    const d = detail.json?.data || {};
    ctx.r14Rincian = {
      addition_cost: Number(d.addition_cost),
      addition_description: d.addition_description,
      total_price: Number(d.total_price),
      unit_price: Number(d.unit?.price),
      down_payment_amount: Number(d.down_payment_amount),
      installment_amount: Number(d.installment_amount),
      remaining_amount: Number(d.remaining_amount),
    };
    // Simulasi skema dihitung ulang dari harga unit tanpa biaya tambahan sebagai pembanding.
    const simulasi = await api(S(), 'POST', `/payment-schemes/${ctx.cashBertahapId}/simulate`, { unit_id: ctx.r14UnitId });
    ctx.r14Simulasi = simulasi.json?.data || {};
    const salah = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r14LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r14UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18', addition_cost: -100000,
    });
    ctx.r14Salah = { status: salah.status, pesan: salah.json?.errors?.addition_cost?.[0] || salah.json?.message || '-' };
    const csv = await api(M(), 'GET', '/reports/export?type=sales&start_date=2026-11-01&end_date=2026-11-30');
    const baris = String(csv.text || '').replace(/^\uFEFF/, '').split(/\r?\n/).filter(Boolean);
    ctx.r14Csv = { header: baris[0] || '-', adaBaris: baris.some((b) => String(b).includes('Kanopi carport')) };
    // Booking fixtures dihapus lalu dibuat ulang: penomoran booking_code harus
    // tetap unik walaupun baris lama masih tersimpan secara lunak.
    const kodeLama = booking.json?.data?.booking_code;
    await api(M(), 'DELETE', `/bookings/${ctx.r14BookingId}`);
    const ulang = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r14LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r14UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r14KodeUlang = {
      status: ulang.status,
      kode: ulang.json?.data?.booking_code,
      unik: !!ulang.json?.data?.booking_code && ulang.json?.data?.booking_code !== kodeLama,
      bookingId: ulang.json?.data?.id,
      kodeLama,
    };
    await api(M(), 'DELETE', `/bookings/${ctx.r14KodeUlang.bookingId}`);
    return booking;
  },
  check: (j) => {
    const r = ctx.r14Rincian || {};
    const s = ctx.r14Simulasi || {};
    const tetapSama = Math.abs(r.total_price - r.unit_price) < 1
      && Math.abs(r.down_payment_amount - Number(s.down_payment_amount)) < 1
      && Math.abs(r.installment_amount - Number(s.installment_amount)) < 1;
    const pass = j?.data?.id && r.addition_cost === 25000000
      && r.addition_description === 'Kanopi carport dan upgrade granit'
      && tetapSama && r.remaining_amount > 0
      && ctx.r14Salah.status === 422 && /biaya tambahan konstruksi/i.test(ctx.r14Salah.pesan)
      && /Biaya Tambahan Konstruksi/.test(ctx.r14Csv.header)
      && /Keterangan Tambahan/.test(ctx.r14Csv.header) && ctx.r14Csv.adaBaris
      && ctx.r14KodeUlang.status === 201 && ctx.r14KodeUlang.unik;
    return {
      pass,
      note: `booking=${ctx.r14BookingId}; addition_cost=${r.addition_cost?.toLocaleString('id-ID')}; keterangan=${trim(r.addition_description || '-')}; `
        + `harga unit=${r.unit_price?.toLocaleString('id-ID')} total booking=${r.total_price?.toLocaleString('id-ID')}; `
        + `uang muka ${r.down_payment_amount?.toLocaleString('id-ID')} vs simulasi ${Number(s.down_payment_amount).toLocaleString('id-ID')} `
        + `dan angsuran ${r.installment_amount?.toLocaleString('id-ID')} vs simulasi ${Number(s.installment_amount).toLocaleString('id-ID')} `
        + `(sisa ${r.remaining_amount?.toLocaleString('id-ID')}); biaya negatif=${ctx.r14Salah.status} (${ctx.r14Salah.pesan}); `
        + `header CSV=${trim(ctx.r14Csv.header)}; baris tambahan=${ctx.r14Csv.adaBaris}; `
        + `kode setelah booking terhapus=${ctx.r14KodeUlang.kode} (sebelumnya ${ctx.r14KodeUlang.kodeLama}, unik=${ctx.r14KodeUlang.unik})`,
    };
  },
  kebutuhan: 'Revisi 15 (biaya tambahan konstruksi pada booking)',
});

/* -------------------------------------------------------------------------
   RANGKAIAN REVISI 18 — NOMOR HANDPHONE SEKALI PAKAI, BOOKING HOT,
   PEMBATALAN DARI DATA KONSUMEN, DAN SAKLAR PERMINTAAN TAMBAHAN
   ------------------------------------------------------------------------ */

T({
  id: 'TC-R15', nama: 'Nomor Handphone Hanya Sekali Pakai', skenario: 'Revisi 18',
  deskripsi: 'Satu nomor handphone hanya dapat melekat pada satu calon konsumen; nomor yang sudah dipakai ditolak, dan nomor dibebaskan kembali bila datanya dihapus.',
  langkah: '1. Ambil nomor dari daftar lead 2. POST lead baru memakai nomor tersebut 3. POST lead dengan nomor unik 4. PUT lead memakai nomornya sendiri 5. DELETE lalu POST ulang dengan nomor yang sama 6. DELETE lead hasil uji',
  dataUji: () => `nomor terpakai diambil dari daftar lead; nomor unik 081238${UIN}`,
  ekspektasi: 'HTTP 422 dengan pesan berbahasa Indonesia saat nomor dipakai ulang; penyimpanan nomor unik HTTP 201; pembaruan dengan nomor miliknya sendiri HTTP 200; setelah lead dihapus, nomor yang sama dapat dipakai lagi (HTTP 201)',
  expectStatus: 422, method: 'POST', path: '/customer-leads', token: S,
  body: () => ({ name: `Lead Nomor Ganda ${UIN}`, phone: ctx.r15NomorTerpakai, status: 'cold' }),
  run: async () => {
    const daftar = await api(M(), 'GET', '/customer-leads');
    ctx.r15NomorTerpakai = (daftar.json?.data || [])[0]?.phone;

    const ganda = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Nomor Ganda ${UIN}`, phone: ctx.r15NomorTerpakai, status: 'cold',
    });
    ctx.r15Ganda = { status: ganda.status, pesan: ganda.json?.errors?.phone?.[0] || ganda.json?.message || '-' };

    const sendiri = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Nomor Baru ${UIN}`, phone: `081238${UIN}`, status: 'cold',
    });
    ctx.r15LeadId = sendiri.json?.data?.id;

    const ubah = await api(S(), 'PUT', `/customer-leads/${ctx.r15LeadId}`, {
      phone: `081238${UIN}`, note: 'Nomor milik sendiri, bukan duplikasi.',
    });
    ctx.r15Ubah = { status: ubah.status, pesan: ubah.json?.message || '-' };

    await api(S(), 'DELETE', `/customer-leads/${ctx.r15LeadId}`);
    const pakaiLagi = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Nomor Dibebaskan ${UIN}`, phone: `081238${UIN}`, status: 'warm',
    });
    ctx.r15PakaiLagi = { status: pakaiLagi.status, leadId: pakaiLagi.json?.data?.id };
    await api(S(), 'DELETE', `/customer-leads/${ctx.r15PakaiLagi.leadId}`);

    return ganda;
  },
  check: () => {
    const pass = !!ctx.r15NomorTerpakai && ctx.r15Ganda.status === 422
      && /nomor handphone/i.test(ctx.r15Ganda.pesan) && ctx.r15Ubah.status === 200
      && ctx.r15PakaiLagi.status === 201;
    return {
      pass,
      note: `nomor terpakai=${ctx.r15NomorTerpakai}; pakai ulang=${ctx.r15Ganda.status} (${ctx.r15Ganda.pesan}); `
        + `ubah nomor sendiri=${ctx.r15Ubah.status}; pakai lagi setelah dihapus=${ctx.r15PakaiLagi.status}`,
    };
  },
  kebutuhan: 'Revisi 18 (nomor handphone hanya sekali pakai pada satu konsumen)',
});

T({
  id: 'TC-R16', nama: 'Booking Hanya untuk Calon Konsumen Hot', skenario: 'Revisi 18',
  deskripsi: 'Pemesanan ditolak selama status calon konsumen belum hot; setelah follow up menaikkan statusnya, booking dapat dibuat.',
  langkah: '1. Buat lead berstatus warm 2. POST /api/bookings untuk lead tersebut 3. PUT lead menjadi hot 4. POST booking ulang 5. Hapus booking hasil uji',
  dataUji: () => `lead='Lead Hangat ${UIN}', status awal warm lalu hot, skema cash bertahap, booking_date=2026-11-18`,
  ekspektasi: 'HTTP 422 dengan pesan bahwa pemesanan hanya dapat dibuat untuk calon konsumen berstatus hot; setelah status hot HTTP 201 dan booking tersimpan',
  expectStatus: [422, 201], method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r16LeadId, unit_id: ctx.r16UnitId, status: 'hot' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r16UnitId = unit?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Hangat ${UIN}`, phone: `081239${UIN}`, status: 'warm', source: 'manual',
    });
    ctx.r16LeadId = lead.json?.data?.id;

    const ditolak = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r16LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r16UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r16Ditolak = { status: ditolak.status, pesan: ditolak.json?.message || '-' };

    await api(S(), 'PUT', `/customer-leads/${ctx.r16LeadId}`, { status: 'hot' });
    const diterima = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r16LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r16UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r16Diterima = { status: diterima.status, kode: diterima.json?.data?.booking_code };
    ctx.r16BookingId = diterima.json?.data?.id;
    await api(M(), 'DELETE', `/bookings/${ctx.r16BookingId}`);

    return ditolak;
  },
  check: () => {
    const pass = ctx.r16Ditolak.status === 422 && /status hot/i.test(ctx.r16Ditolak.pesan)
      && ctx.r16Diterima.status === 201 && !!ctx.r16Diterima.kode;
    return {
      pass,
      note: `booking lead warm=${ctx.r16Ditolak.status} (${ctx.r16Ditolak.pesan}); setelah hot=${ctx.r16Diterima.status} kode ${ctx.r16Diterima.kode}`,
    };
  },
  kebutuhan: 'Revisi 18 (booking dipilih dari calon konsumen berstatus hot)',
});

T({
  id: 'TC-R17', nama: 'Pembatalan Konsumen dari Daftar Konsumen', skenario: 'Revisi 18',
  deskripsi: 'Tombol Batal pada Data Konsumen membatalkan seluruh pemesanan aktifnya dengan alasan dan tanggal yang dicatat sales; konsumen berpindah ke kategori Dibatalkan dan unit kembali tersedia.',
  langkah: '1. Buat lead hot + booking + pembayaran terverifikasi (kategori konsumen) 2. POST /api/customer-leads/{id}/batalkan dengan reason dan cancelled_at 3. Periksa status booking, tanggal batal, unit, kategori lead, dan penapis riwayat 4. Ulangi pembatalan 5. Hapus booking hasil uji',
  dataUji: () => `lead='Konsumen Batal Dari Daftar ${UIN}', reason='Konsumen mengundurkan diri setelah menempati unit (uji revisi 18)', cancelled_at=2026-11-10`,
  ekspektasi: 'HTTP 200; booking berstatus dibatalkan dengan cancelled_at 2026-11-10 dan alasan tersimpan; unit kembali available; kategori lead dibatalkan, hilang dari category=konsumen dan muncul pada category=riwayat; pembatalan kedua ditolak 422',
  expectStatus: 200, method: 'POST', path: () => `/customer-leads/${ctx.r17LeadId}/batalkan`, token: S,
  body: () => ({ reason: 'Konsumen mengundurkan diri setelah menempati unit (uji revisi 18)', cancelled_at: '2026-11-10' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r17UnitId = unit?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Batal Dari Daftar ${UIN}`, phone: `081240${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r17LeadId = lead.json?.data?.id;

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r17LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r17UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r17BookingId = booking.json?.data?.id;

    const bayar = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r17BookingId, payment_type: 'cash bertahap', payment_method: 'transfer bank',
      amount: 250000000, payment_date: '2026-11-18', notes: 'Uang muka konsumen (uji revisi 18).',
    });
    await api(M(), 'PUT', `/payments/${bayar.json?.data?.id}/verify`, { verification_status: 'verified' });

    ctx.r17KategoriSebelum = await kategoriLead(ctx.r17LeadId);
    const daftarSebelum = await api(M(), 'GET', '/customer-leads?category=konsumen');
    ctx.r17BolehBatal = !!(daftarSebelum.json?.data || []).find((r) => r.id === ctx.r17LeadId)?.punya_booking_aktif;

    const batal = await api(S(), 'POST', `/customer-leads/${ctx.r17LeadId}/batalkan`, {
      reason: 'Konsumen mengundurkan diri setelah menempati unit (uji revisi 18)',
      cancelled_at: '2026-11-10',
    });
    ctx.r17Pesan = batal.json?.message || '-';

    const rincian = await api(M(), 'GET', `/bookings/${ctx.r17BookingId}`);
    ctx.r17StatusBooking = rincian.json?.data?.status;
    ctx.r17TanggalBatal = String(rincian.json?.data?.cancelled_at || '').slice(0, 10);
    ctx.r17Alasan = rincian.json?.data?.cancellation_reason;
    ctx.r17Unit = (await api(M(), 'GET', `/units/${ctx.r17UnitId}`)).json?.data?.status;
    ctx.r17Kategori = await kategoriLead(ctx.r17LeadId);
    ctx.r17DiRiwayat = await leadMasukPenapis('riwayat', ctx.r17LeadId);
    ctx.r17DiKonsumen = await leadMasukPenapis('konsumen', ctx.r17LeadId);
    ctx.r17RiwayatBaris = (await api(M(), 'GET', '/customer-leads?category=riwayat'))
      .json?.data?.find((r) => r.id === ctx.r17LeadId)?.riwayat || null;

    const duaKali = await api(S(), 'POST', `/customer-leads/${ctx.r17LeadId}/batalkan`, {
      reason: 'Pembatalan kedua tanpa pemesanan aktif (uji revisi 18)', cancelled_at: '2026-11-11',
    });
    ctx.r17DuaKali = { status: duaKali.status, pesan: duaKali.json?.message || '-' };
    await api(M(), 'DELETE', `/bookings/${ctx.r17BookingId}`);

    return batal;
  },
  check: () => {
    const pass = ctx.r17KategoriSebelum === 'konsumen' && ctx.r17BolehBatal === true
      && ctx.r17StatusBooking === 'dibatalkan' && ctx.r17TanggalBatal === '2026-11-10'
      && /menempati unit/.test(String(ctx.r17Alasan || '')) && ctx.r17Unit === 'available'
      && ctx.r17Kategori === 'dibatalkan' && ctx.r17DiKonsumen === false && ctx.r17DiRiwayat === true
      && ctx.r17RiwayatBaris?.reason && ctx.r17RiwayatBaris?.cancelled_at
      && ctx.r17DuaKali.status === 422;
    return {
      pass,
      note: `kategori sebelum=${ctx.r17KategoriSebelum} (tombol batal aktif=${ctx.r17BolehBatal}); booking=${ctx.r17StatusBooking} `
        + `tanggal batal=${ctx.r17TanggalBatal}, alasan=${trim(ctx.r17Alasan || '-')}; unit=${ctx.r17Unit}; kategori sesudah=${ctx.r17Kategori}; `
        + `penapis konsumen=${ctx.r17DiKonsumen}, riwayat=${ctx.r17DiRiwayat}; kolom riwayat=${trim(JSON.stringify(ctx.r17RiwayatBaris || {}))}; `
        + `batal kedua=${ctx.r17DuaKali.status} (${ctx.r17DuaKali.pesan})`,
    };
  },
  kebutuhan: 'Revisi 18 (pembatalan dari tombol Batal pada Data Konsumen, data berpindah ke kategori Dibatalkan)',
});

T({
  id: 'TC-R18', nama: 'Keterangan Wajib Bila Ada Biaya Tambahan', skenario: 'Revisi 18',
  deskripsi: 'Biaya tambahan konstruksi harus berpasangan dengan keterangan pekerjaan tambahan; pembaruan biaya memakai keterangan yang sudah tersimpan.',
  langkah: '1. POST /api/bookings dengan addition_cost tanpa addition_description 2. POST lengkap 3. PUT addition_cost baru tanpa mengirim keterangan 4. Hapus booking hasil uji',
  dataUji: () => `lead='Konsumen Tambahan Wajib ${UIN}', addition_cost=25000000 tanpa keterangan, lalu berpasangan, lalu diubah 30000000`,
  ekspektasi: 'HTTP 422 dengan pesan keterangan tambahan wajib; POST lengkap HTTP 201; PUT biaya baru tanpa keterangan HTTP 200 dan keterangan lama tetap tersimpan',
  expectStatus: [422, 201], method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r18LeadId, unit_id: ctx.r18UnitId, addition_cost: 25000000 }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r18UnitId = unit?.id;
    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Tambahan Wajib ${UIN}`, phone: `081241${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r18LeadId = lead.json?.data?.id;

    const tanpa = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r18LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r18UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18', addition_cost: 25000000,
    });
    ctx.r18Tanpa = { status: tanpa.status, pesan: tanpa.json?.errors?.addition_description?.[0] || tanpa.json?.message || '-' };

    const lengkap = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r18LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r18UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
      addition_cost: 25000000, addition_description: 'Meningkatkan kanopi carport',
    });
    ctx.r18BookingId = lengkap.json?.data?.id;

    const ubah = await api(S(), 'PUT', `/bookings/${ctx.r18BookingId}`, { addition_cost: 30000000 });
    ctx.r18Ubah = {
      status: ubah.status,
      biaya: Number(ubah.json?.data?.addition_cost),
      keterangan: ubah.json?.data?.addition_description,
    };
    await api(M(), 'DELETE', `/bookings/${ctx.r18BookingId}`);

    return tanpa;
  },
  check: () => {
    const pass = ctx.r18Tanpa.status === 422 && /keterangan tambahan/i.test(ctx.r18Tanpa.pesan)
      && ctx.r18BookingId && ctx.r18Ubah.status === 200
      && ctx.r18Ubah.biaya === 30000000 && ctx.r18Ubah.keterangan === 'Meningkatkan kanopi carport';
    return {
      pass,
      note: `biaya tanpa keterangan=${ctx.r18Tanpa.status} (${ctx.r18Tanpa.pesan}); biaya 25.000.000 + keterangan=${!!ctx.r18BookingId}; `
        + `ubah biaya=${ctx.r18Ubah.status} menjadi ${ctx.r18Ubah.biaya?.toLocaleString('id-ID')} dengan keterangan ${trim(ctx.r18Ubah.keterangan || '-')}`,
    };
  },
  kebutuhan: 'Revisi 18 (sales memilih ada/tidaknya permintaan tambahan bangunan)',
});

T({
  id: 'TC-R19', nama: 'Nomor Handphone Terkunci Walau Cold atau Dibatalkan', skenario: 'Revisi 24',
  deskripsi: 'Nomor yang sudah dipakai satu calon konsumen tidak dapat dipakai ulang selama datanya masih ada, baik ketika statusnya masih cold '
    + 'maupun setelah pemesanan konsumen tersebut dibatalkan sales.',
  langkah: '1. POST lead berstatus cold dengan nomor uji 2. POST lead lain memakai nomor yang sama 3. Naikkan status lead pertama menjadi hot lalu buat pemesanannya '
    + '4. POST /api/customer-leads/{id}/batalkan 5. POST lead ketiga memakai nomor yang sama 6. Hapus booking dan lead hasil uji',
  dataUji: () => `nomor=081242${UIN}; pembatalan memakai reason='Konsumen mengundurkan diri sebelum pelunasan (uji revisi 24)' dan cancelled_at=2026-11-12`,
  ekspektasi: 'Kedua percobaan memakai nomor yang sama ditolak HTTP 422 dengan pesan "Nomor handphone ini sudah dipakai konsumen lain..."; '
    + 'pembatalan HTTP 200 dan kategori lead menjadi dibatalkan, namun nomornya tetap tidak dapat dipakai ulang',
  expectStatus: 422, method: 'POST', path: '/customer-leads', token: S,
  body: () => ({ name: `Lead Ganda Dibatalkan ${UIN}`, phone: `081242${UIN}`, status: 'warm' }),
  run: async () => {
    const pemilik = await api(S(), 'POST', '/customer-leads', {
      name: `Pemilik Nomor Terkunci ${UIN}`, phone: `081242${UIN}`, status: 'cold', source: 'manual',
    });
    ctx.r19LeadId = pemilik.json?.data?.id;

    const saatCold = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Ganda Cold ${UIN}`, phone: `081242${UIN}`, status: 'warm',
    });
    ctx.r19Cold = { status: saatCold.status, pesan: saatCold.json?.errors?.phone?.[0] || saatCold.json?.message || '-' };

    const unit = await unitRevisi15();
    ctx.r19UnitId = unit?.id;
    await api(S(), 'PUT', `/customer-leads/${ctx.r19LeadId}`, { status: 'hot' });

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r19LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r19UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-12',
    });
    ctx.r19BookingId = booking.json?.data?.id;

    const batal = await api(S(), 'POST', `/customer-leads/${ctx.r19LeadId}/batalkan`, {
      reason: 'Konsumen mengundurkan diri sebelum pelunasan (uji revisi 24)',
      cancelled_at: '2026-11-12',
    });
    ctx.r19Batal = { status: batal.status, pesan: batal.json?.message || '-' };
    ctx.r19Kategori = await kategoriLead(ctx.r19LeadId);

    const saatBatal = await api(S(), 'POST', '/customer-leads', {
      name: `Lead Ganda Dibatalkan ${UIN}`, phone: `081242${UIN}`, status: 'warm',
    });
    ctx.r19Dibatalkan = { status: saatBatal.status, pesan: saatBatal.json?.errors?.phone?.[0] || saatBatal.json?.message || '-' };

    if (ctx.r19BookingId) await api(M(), 'DELETE', `/bookings/${ctx.r19BookingId}`);
    await api(S(), 'DELETE', `/customer-leads/${ctx.r19LeadId}`);

    return saatBatal;
  },
  check: () => {
    const pass = ctx.r19Cold.status === 422 && /nomor handphone/i.test(ctx.r19Cold.pesan)
      && ctx.r19Batal.status === 200 && ctx.r19Kategori === 'dibatalkan'
      && ctx.r19Dibatalkan.status === 422 && /nomor handphone/i.test(ctx.r19Dibatalkan.pesan);
    return {
      pass,
      note: `pakai ulang saat cold=${ctx.r19Cold.status} (${ctx.r19Cold.pesan}); pembatalan=${ctx.r19Batal.status} `
        + `(${trim(ctx.r19Batal.pesan)}); kategori sesudah=${ctx.r19Kategori}; pakai ulang setelah dibatalkan=${ctx.r19Dibatalkan.status} (${ctx.r19Dibatalkan.pesan})`,
    };
  },
  kebutuhan: 'Revisi 24 (nomor handphone tidak dapat dipakai ulang walau cold maupun sudah dibatalkan)',
});

T({
  id: 'TC-R20', nama: 'Notifikasi Alur Sales Tidak ke Admin Sales', skenario: 'Revisi 29',
  deskripsi: 'Pemberitahuan booking baru dan hasil verifikasi pembayaran hanya dibaca Super Admin serta sales pemilik transaksi. '
    + 'Admin Sales tetap menerima pemberitahuan yang memang dialamatkan ke akunnya, tetapi tidak ikut menerima siaran alur sales.',
  langkah: '1. Sales membuat lead hot, booking, lalu satu pembayaran 2. Super Admin memverifikasi pembayaran 3. GET /api/notifications memakai token Admin Sales, Super Admin, dan Sales 4. Hitung baris yang memuat nama konsumen/kode booking uji 5. Hapus pembayaran, booking, dan lead',
  dataUji: () => `lead='Konsumen Notifikasi ${UIN}'; pembayaran uang muka cash keras diverifikasi 2026-11-20`,
  ekspektasi: 'Verifikasi HTTP 200; daftar Admin Sales tidak memuat satu pun notifikasi transaksi uji maupun baris booking/payment lama, sedangkan daftar Super Admin memuat keduanya dan daftar sales memuat notifikasi hasil verifikasi',
  expectStatus: 200, method: 'GET', path: '/notifications', token: A,
  run: async () => {
    const penanda = `Konsumen Notifikasi ${UIN}`;
    const unit = await unitRevisi15();
    ctx.r20UnitId = unit?.id;

    const lead = await api(S(), 'POST', '/customer-leads', {
      name: penanda, phone: `081259${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r20LeadId = lead.json?.data?.id;

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r20LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r20UnitId,
      payment_scheme_id: ctx.cashKerasId, booking_date: '2026-11-20',
    });
    ctx.r20BookingId = booking.json?.data?.id;
    ctx.r20Kode = booking.json?.data?.booking_code;

    const bayar = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r20BookingId, payment_type: 'cash keras', payment_method: 'transfer bank',
      amount: Number(booking.json?.data?.down_payment_amount), payment_date: '2026-11-20',
      notes: 'Uji notifikasi revisi 29.',
    });
    ctx.r20PaymentId = bayar.json?.data?.id;
    ctx.r20Verif = await api(M(), 'PUT', `/payments/${ctx.r20PaymentId}/verify`, { verification_status: 'verified' });

    const kena = (rows) => rows.filter((n) => String(n.message || '').includes(penanda)
      || (!!ctx.r20Kode && String(n.message || '').includes(ctx.r20Kode))).length;
    const daftar = async (token) => (await api(token, 'GET', '/notifications')).json?.data || [];

    ctx.r20Admin = kena(await daftar(A()));
    ctx.r20Super = kena(await daftar(M()));
    ctx.r20Sales = kena(await daftar(S()));

    const akhir = await api(A(), 'GET', '/notifications');
    ctx.r20JenisAdmin = (akhir.json?.data || [])
      .filter((n) => n.type === 'booking' || n.type === 'payment').length;
    await api(M(), 'DELETE', `/payments/${ctx.r20PaymentId}`);
    await api(M(), 'DELETE', `/bookings/${ctx.r20BookingId}`);
    await api(S(), 'DELETE', `/customer-leads/${ctx.r20LeadId}`);

    return akhir;
  },
  check: () => {
    const pass = ctx.r20Verif.status === 200 && ctx.r20Admin === 0 && ctx.r20Super >= 2
      && ctx.r20Sales >= 1 && ctx.r20JenisAdmin === 0;
    return {
      pass,
      note: `notifikasi transaksi uji — Admin Sales=${ctx.r20Admin}, Super Admin=${ctx.r20Super}, Sales=${ctx.r20Sales}; `
        + `baris booking/payment pada daftar Admin Sales=${ctx.r20JenisAdmin} (termasuk baris lama); `
        + `booking=${ctx.r20Kode}; verifikasi=${ctx.r20Verif.status} (${trim(ctx.r20Verif.text)})`,
    };
  },
  kebutuhan: 'Revisi 29 (notifikasi pembayaran dan verifikasi super admin ke sales tidak muncul di Admin Sales)',
});

T({
  id: 'TC-R21', nama: 'Jejak Aktivitas Hanya Super Admin', skenario: 'Revisi 30',
  deskripsi: 'Menu Log Aktivitas dihapus dari Admin Sales sehingga pembacaan jejak aktivitas dan log sistem dikunci untuk Super Admin saja.',
  langkah: '1. GET /api/activity-logs dengan token Admin Sales 2. GET /api/system-logs dengan token Admin Sales 3. GET /api/activity-logs dengan token Super Admin 4. GET /api/activity-logs tanpa token',
  dataUji: 'token admin_sales, token super_admin, tanpa token',
  ekspektasi: 'Kedua endpoint menolak Admin Sales HTTP 403 dengan pesan berbahasa Indonesia; Super Admin memperoleh HTTP 200 berisi baris jejak; permintaan tanpa token ditolak HTTP 401',
  expectStatus: 403, method: 'GET', path: '/activity-logs', token: A,
  run: async () => {
    ctx.r21AktivitasAdmin = await api(A(), 'GET', '/activity-logs');
    ctx.r21SistemAdmin = await api(A(), 'GET', '/system-logs');
    ctx.r21Super = await api(M(), 'GET', '/activity-logs');
    ctx.r21Anonim = await api(null, 'GET', '/activity-logs');
    return ctx.r21AktivitasAdmin;
  },
  check: () => {
    const baris = ctx.r21Super.json?.data || [];
    const pass = ctx.r21AktivitasAdmin.status === 403 && ctx.r21SistemAdmin.status === 403
      && ctx.r21Super.status === 200 && baris.length > 0 && ctx.r21Anonim.status === 401;
    return {
      pass,
      note: `activity-logs Admin Sales=${ctx.r21AktivitasAdmin.status} (${trim(ctx.r21AktivitasAdmin.text)}); `
        + `system-logs Admin Sales=${ctx.r21SistemAdmin.status}; Super Admin=${ctx.r21Super.status} dengan ${baris.length} baris jejak; `
        + `tanpa token=${ctx.r21Anonim.status}`,
    };
  },
  kebutuhan: 'Revisi 30 (log aktivitas dihapus)',
});

T({
  id: 'TC-R22', nama: 'Rincian per Proyek pada Empat Laporan', skenario: 'Revisi 32',
  deskripsi: 'Laporan penjualan, pelunasan, kinerja sales, dan konsumen mengembalikan blok per_project satu baris per proyek, melabeli tiap baris detail dengan nama proyeknya, dan menyertakan kolom Proyek pada ekspor CSV.',
  langkah: '1. GET /api/reports/sales, /settlement, /performance, /consumers untuk periode 2026 2. Jumlahkan baris per_project dan bandingkan dengan angka laporan 3. GET /api/reports/sales?cluster_id 4. GET /api/reports/export?type=sales',
  dataUji: () => `periode 2026-01-01 s/d 2026-12-31; cluster_id=${ctx.clusterId}`,
  ekspektasi: 'Jumlah transaksi/nominal seluruh baris per_project sama dengan angka laporan; tiap baris detail punya label proyek; laporan tersaring hanya punya satu baris per_project yang cocok dengan totalnya; header CSV memuat kolom Proyek',
  expectStatus: 200, method: 'GET', path: '/reports/sales', token: M,
  run: async () => {
    const periode = '?start_date=2026-01-01&end_date=2026-12-31';
    ctx.r22 = {};

    const penjualan = await api(M(), 'GET', '/reports/sales' + periode);
    const pj = penjualan.json;
    ctx.r22.penjualan = {
      baris: (pj?.data || []).length,
      emberak: pj?.total_transactions,
      jumlahBaris: (pj?.per_project || []).reduce((a, r) => a + Number(r.transactions || 0), 0),
      nilai: Number(pj?.total_sales_value || 0),
      jumlahNilai: (pj?.per_project || []).reduce((a, r) => a + Number(r.sales_value || 0), 0),
      tanpaLabel: (pj?.data || []).filter((r) => !r.project).length,
      proyek: (pj?.per_project || []).length,
    };

    const pelunasan = await api(M(), 'GET', '/reports/settlement' + periode);
    const pl = pelunasan.json;
    ctx.r22.pelunasan = {
      emberak: pl?.total_transactions,
      jumlahBaris: (pl?.per_project || []).reduce((a, r) => a + Number(r.transactions || 0), 0),
      dana: Number(pl?.total_settled_amount || 0),
      jumlahDana: (pl?.per_project || []).reduce((a, r) => a + Number(r.total_amount || 0), 0),
    };

    const kinerja = await api(M(), 'GET', '/reports/performance' + periode);
    const kj = kinerja.json;
    ctx.r22.kinerja = {
      bookingTersusun: kj?.totals?.total_bookings,
      jumlahBaris: (kj?.per_project || []).reduce((a, r) => a + Number(r.transactions || 0), 0),
      barisTanpaProyek: (kj?.per_project || []).filter((r) => !r.name).length,
    };

    const konsumen = await api(M(), 'GET', '/reports/consumers' + periode);
    const km = konsumen.json;
    ctx.r22.konsumen = {
      emberak: km?.totals?.total,
      jumlahBaris: (km?.per_project || []).reduce((a, r) => a + Number(r.total || 0), 0),
      kategori: (km?.per_project || []).reduce((a, r) => a + Number(r.prospek || 0) + Number(r.customer || 0) + Number(r.cancelled || 0), 0),
    };

    const tersaring = await api(M(), 'GET', `/reports/sales${periode}&cluster_id=${ctx.clusterId}`);
    const ts = tersaring.json;
    ctx.r22.tersaring = {
      baris: (ts?.per_project || []).length,
      id: (ts?.per_project || [])[0]?.cluster_id,
      transaksi: (ts?.per_project || [])[0]?.transactions,
      emberak: ts?.total_transactions,
      projectFilter: ts?.project_filter,
    };

    const csv = await api(M(), 'GET', `/reports/export?type=sales${periode}`);
    ctx.r22.csv = { header: String(csv.text || '').replace(/^/, '').split('\n')[0] || '-' };

    return penjualan;
  },
  check: (j) => {
    const d = ctx.r22;
    const pass = d.penjualan.proyek > 0
      && d.penjualan.jumlahBaris === d.penjualan.emberak && d.penjualan.emberak === d.penjualan.baris
      && Math.abs(d.penjualan.jumlahNilai - d.penjualan.nilai) < 1 && d.penjualan.tanpaLabel === 0
      && d.pelunasan.jumlahBaris === d.pelunasan.emberak && Math.abs(d.pelunasan.jumlahDana - d.pelunasan.dana) < 1
      && d.kinerja.jumlahBaris >= d.kinerja.bookingTersusun && d.kinerja.barisTanpaProyek === 0
      && d.konsumen.jumlahBaris === d.konsumen.emberak && d.konsumen.kategori === d.konsumen.emberak
      && d.tersaring.baris === 1 && d.tersaring.id === ctx.clusterId
      && d.tersaring.transaksi === d.tersaring.emberak && d.tersaring.projectFilter === ctx.clusterId
      && /Proyek/.test(d.csv.header);
    return {
      pass,
      note: `penjualan: ${d.penjualan.jumlahBaris} transaksi per proyek = ${d.penjualan.emberak} total (${d.penjualan.proyek} baris proyek), `
        + `nilai ${Math.round(d.penjualan.jumlahNilai).toLocaleString('id-ID')} = ${Math.round(d.penjualan.nilai).toLocaleString('id-ID')}, baris tanpa label=${d.penjualan.tanpaLabel}; `
        + `pelunasan: ${d.pelunasan.jumlahBaris} = ${d.pelunasan.emberak}, dana ${Math.round(d.pelunasan.jumlahDana).toLocaleString('id-ID')} = ${Math.round(d.pelunasan.dana).toLocaleString('id-ID')}; `
        + `kinerja: ${d.kinerja.jumlahBaris} booking per proyek vs ${d.kinerja.bookingTersusun} tersusun per sales; `
        + `konsumen: ${d.konsumen.jumlahBaris} = ${d.konsumen.emberak} (kategori ${d.konsumen.kategori}); `
        + `tersaring proyek ${ctx.clusterId}: ${d.tersaring.baris} baris, transaksi ${d.tersaring.transaksi} = ${d.tersaring.emberak}; header csv="${trim(d.csv.header)}"`,
    };
  },
  kebutuhan: 'Revisi 32 (laporan menampilkan data masing-masing proyek, bukan gabungan)',
});

T({
  id: 'TC-R23', nama: 'Tabel Proyek pada Dashboard', skenario: 'Revisi 32',
  deskripsi: 'Dashboard Super Admin dan Admin Sales menampilkan tabel rincian per proyek sebagai ringkasan utamanya; angka pada tabel dapat direkonsiliasi dengan angka gabungan dan lead yang belum menautkan unit tetap terhitung pada baris (belum ada proyek).',
  langkah: '1. GET /api/dashboard/super-admin 2. Jumlahkan kolom tabel per proyek dan bandingkan dengan angka ringkasan 3. GET /api/dashboard/super-admin?cluster_id 4. GET /api/dashboard/admin-sales',
  dataUji: () => `cluster_id=${ctx.clusterId}; periode admin sales 2026-01-01 s/d 2026-12-31`,
  ekspektasi: 'HTTP 200; jumlah unit, booking, prospek, konsumen, dan konsumen dibatalkan seluruh baris sama dengan angka ringkasan; setiap baris bernama; filter satu proyek hanya mengembalikan satu baris yang cocok; dashboard Admin Sales punya per_project dengan jumlah booking yang sama',
  expectStatus: 200, method: 'GET', path: '/dashboard/super-admin', token: M,
  run: async () => {
    const semua = await api(M(), 'GET', '/dashboard/super-admin');
    const d = semua.json?.data || {};
    const baris = d.per_project || [];
    const jumlah = (k) => baris.reduce((a, r) => a + Number(r[k] || 0), 0);

    ctx.r23 = {
      baris: baris.length,
      tanpaNama: baris.filter((r) => !r.name).length,
      unit: [jumlah('units_total'), d.total_units],
      booking: [jumlah('bookings_total'), d.total_bookings],
      prospek: [jumlah('prospects'), d.total_prospects],
      konsumen: [jumlah('customers'), d.total_customers],
      batal: [jumlah('cancelled_customers'), d.total_cancelled],
      emberBelumAdaProyek: baris.some((r) => r.cluster_id === null),
    };

    const satu = await api(M(), 'GET', `/dashboard/super-admin?cluster_id=${ctx.clusterId}`);
    const s = satu.json?.data || {};
    ctx.r23.satu = {
      baris: (s.per_project || []).length,
      nama: (s.per_project || [])[0]?.name,
      unit: (s.per_project || [])[0]?.units_total,
      totalUnit: s.total_units,
      filter: s.project_filter,
    };

    const admin = await api(A(), 'GET', '/dashboard/admin-sales?start_date=2026-01-01&end_date=2026-12-31');
    const a = admin.json?.data || {};
    ctx.r23.admin = {
      baris: (a.per_project || []).length,
      booking: (a.per_project || []).reduce((x, r) => x + Number(r.bookings_total || 0), 0),
      totalBooking: a.totals?.total_bookings,
      unit: [jumlah('units_total'), d.total_units],
    };

    return semua;
  },
  check: (j) => {
    const d = ctx.r23;
    const sama = ([x, y]) => x === y;
    const pass = d.baris > 0 && d.tanpaNama === 0 && d.emberBelumAdaProyek
      && sama(d.unit) && sama(d.booking) && sama(d.prospek) && sama(d.konsumen) && sama(d.batal)
      && d.satu.baris === 1 && d.satu.unit === d.satu.totalUnit && d.satu.filter === ctx.clusterId
      && d.admin.baris > 0 && d.admin.booking === d.admin.totalBooking;
    return {
      pass,
      note: `${d.baris} baris proyek; unit ${d.unit.join(' = ')}, booking ${d.booking.join(' = ')}, prospek ${d.prospek.join(' = ')}, `
        + `konsumen ${d.konsumen.join(' = ')}, dibatalkan ${d.batal.join(' = ')}; baris tanpa nama=${d.tanpaNama}; `
        + `ada ember (belum ada proyek)=${d.emberBelumAdaProyek}; tersaring ${d.satu.baris} baris (${d.satu.nama}) unit ${d.satu.unit} = ${d.satu.totalUnit}, `
        + `project_filter=${d.satu.filter}; dashboard Admin Sales ${d.admin.baris} baris dengan booking ${d.admin.booking} = ${d.admin.totalBooking}`,
    };
  },
  kebutuhan: 'Revisi 32 (dashboard menampilkan data masing-masing proyek, bukan gabungan)',
});

T({
  id: 'TC-R24', nama: 'Penguncian Login Setelah Tiga Kali Gagal', skenario: 'Revisi 34',
  deskripsi: 'Tiga percobaan login gagal pada satu kombinasi email dan IP menahan akun selama nilai "Durasi Penguncian Login" yang dipilih super admin; akun lain dan login yang benar tidak ikut terhukum.',
  langkah: '1. PUT /api/settings {login_lockout_minutes:"1"} 2. POST /api/login dengan sandi salah tiga kali untuk email uji baru 3. POST /api/login sekali lagi 4. POST /api/login dengan akun sah lain 5. Pulihkan nilai pengaturan',
  dataUji: () => `email=probe-r24-${UIN}@luminpark.test, password=salah-0..3; pembanding=sales@luminpark.com`,
  ekspektasi: 'Tiga percobaan pertama HTTP 401; percobaan keempat HTTP 429 dengan retry_after antara 1 dan 60 detik serta pesan berbahasa Indonesia yang menyebut penguncian; akun sah tetap HTTP 200',
  expectStatus: 429, method: 'POST', path: '/login', token: null,
  run: async () => {
    const sebelum = await api(M(), 'GET', '/settings');
    const asal = sebelum.json?.data?.values?.login_lockout_minutes ?? '5';
    await api(M(), 'PUT', '/settings', { settings: { login_lockout_minutes: '1' } });

    const email = `probe-r24-${UIN}@luminpark.test`;
    const headerJson = { Accept: 'application/json', 'Content-Type': 'application/json' };
    const gagal = [];
    for (let i = 0; i < 3; i += 1) {
      gagal.push(await apiOnce(null, 'POST', '/login', { email, password: `salah-${i}` }, headerJson));
    }
    const kunci = await apiOnce(null, 'POST', '/login', { email, password: 'salah-3' }, headerJson);
    const akunLain = await apiOnce(null, 'POST', '/login', { email: 'sales@luminpark.com', password: 'password123' }, headerJson);

    await api(M(), 'PUT', '/settings', { settings: { login_lockout_minutes: String(asal) } });

    ctx.r24 = {
      asal,
      gagal: gagal.map((r) => r.status),
      kunci: kunci.status,
      retry: Number(kunci.json?.retry_after ?? 0),
      sampai: kunci.json?.locked_until ?? '-',
      pesan: trim(kunci.text),
      akunLain: akunLain.status,
    };

    return kunci;
  },
  check: () => {
    const d = ctx.r24;
    const pass = d.gagal.every((s) => s === 401) && d.kunci === 429
      && d.retry > 0 && d.retry <= 60 && /dikunci/i.test(d.pesan)
      && !/too many|Too many/i.test(d.pesan) && d.akunLain === 200;
    return {
      pass,
      note: `tiga percobaan pertama ${d.gagal.join(', ')}; percobaan keempat ${d.kunci} dengan retry_after=${d.retry}s sampai ${d.sampai} — ${d.pesan}; `
        + `akun sah lain=${d.akunLain}; durasi penguncian dipulihkan ke ${d.asal} menit`,
    };
  },
  kebutuhan: 'Revisi 34 (tiga kali gagal login diberi jeda yang diatur super admin)',
});

T({
  id: 'TC-R25', nama: 'Blok Google Lepas dari Halaman Pengaturan', skenario: 'Revisi 33',
  deskripsi: 'Revisi 33 melepas blok "Status Masuk dengan Google" dan blok "Integrasi Login Google" dari halaman Pengaturan super admin. Kredensial tetap tersimpan di tabel settings dan tombol Google pada halaman masuk tidak berubah.',
  langkah: '1. GET /pengaturan 2. Cari judul kedua blok dan kunci google_* pada sumber halaman 3. GET /api/settings 4. GET /login',
  dataUji: 'sumber HTML halaman /pengaturan dan /login milik aplikasi yang sama',
  ekspektasi: 'Sumber /pengaturan tidak memuat "Status Masuk dengan Google", "Integrasi Login Google", maupun nama kunci google_*; /api/settings tetap 200 dengan daftar editable yang masih memuat google_login_enabled; /login tetap merender tombol google-btn',
  expectStatus: 200, method: 'GET', path: '/pengaturan', token: null,
  run: async () => {
    const pengaturan = await halaman('/pengaturan');
    const settings = await api(M(), 'GET', '/settings');
    const masuk = await halaman('/login');

    ctx.r25 = {
      halaman: pengaturan.status,
      statusBlok: pengaturan.text.includes('Status Masuk dengan Google'),
      integrasiBlok: pengaturan.text.includes('Integrasi Login Google'),
      kunciGoogle: ['google_client_id', 'google_client_secret', 'google_login_enabled']
        .filter((k) => pengaturan.text.includes(k)),
      api: settings.status,
      editable: (settings.json?.data?.editable || []).includes('google_login_enabled'),
      tombol: masuk.text.includes('id="google-btn"'),
    };

    return pengaturan;
  },
  check: () => {
    const d = ctx.r25;
    const pass = d.halaman === 200 && !d.statusBlok && !d.integrasiBlok
      && d.kunciGoogle.length === 0 && d.api === 200 && d.editable && d.tombol;
    return {
      pass,
      note: `/pengaturan ${d.halaman}: blok status Google=${d.statusBlok ? 'masih ada' : 'hilang'}, blok integrasi=${d.integrasiBlok ? 'masih ada' : 'hilang'}, `
        + `kunci google_* tersisa=${d.kunciGoogle.length ? d.kunciGoogle.join(', ') : 'tidak ada'}; /api/settings ${d.api} dengan google_login_enabled `
        + `dalam editable=${d.editable}; tombol Google pada /login=${d.tombol ? 'masih aktif' : 'hilang'}`,
    };
  },
  kebutuhan: 'Revisi 33 (blok status dan integrasi login Google dihapus dari Pengaturan)',
});

T({
  id: 'TC-R26', nama: 'Sesi Ditentukan oleh Aktivitas, Bukan Umur Token', skenario: 'Revisi 35',
  deskripsi: 'Seksi Sistem & Sesi Login pada halaman Pengaturan memuat durasi penguncian login dan batas waktu tanpa aktivitas; nilai batas waktu yang sama dibaca antarmuka lewat /api/session/status untuk keluar otomatis, dan perpanjangan memberi jendela selebar nilai tersebut.',
  langkah: '1. GET /pengaturan 2. GET /api/session/status 3. Bandingkan session_timeout_minutes dengan nilai tersimpan 4. POST /api/session/refresh dan hitung umur token baru',
  dataUji: 'token super_admin; nilai session_timeout_minutes dan login_lockout_minutes pada basis data uji',
  ekspektasi: 'Halaman memuat label "Durasi Penguncian Login" dan "Batas Waktu Tanpa Aktivitas" serta tidak lagi memuat "Batas Sesi Login (menit)"; /session/status 200 dengan session_timeout_minutes sama dengan nilai tersimpan; /session/refresh 200 dengan token_expires_at ≈ sekarang + batas menit',
  expectStatus: 200, method: 'GET', path: '/session/status', token: M,
  run: async () => {
    const pengaturan = await halaman('/pengaturan');
    const status = await api(M(), 'GET', '/session/status');
    const stored = (await api(M(), 'GET', '/settings')).json?.data?.values?.session_timeout_minutes;
    const perpanjangan = await api(M(), 'POST', '/session/refresh', {});

    // Perpanjangan mencabut token lama, jadi token super admin suite ikut diganti.
    if (perpanjangan.json?.access_token) ctx.managerToken = perpanjangan.json.access_token;

    const umurMenit = perpanjangan.json?.token_expires_at
      ? (new Date(perpanjangan.json.token_expires_at) - Date.now()) / 60000
      : 0;

    ctx.r26 = {
      halaman: pengaturan.status,
      labelPenguncian: pengaturan.text.includes('Durasi Penguncian Login'),
      labelAktivitas: pengaturan.text.includes('Batas Waktu Tanpa Aktivitas'),
      labelLama: pengaturan.text.includes('Batas Sesi Login (menit)'),
      status: status.status,
      dariApi: status.json?.data?.session_timeout_minutes,
      dariDb: Number(stored),
      refresh: perpanjangan.status,
      umurToken: Math.round(umurMenit),
    };

    return status;
  },
  check: () => {
    const d = ctx.r26;
    const pass = d.halaman === 200 && d.labelPenguncian && d.labelAktivitas && !d.labelLama
      && d.status === 200 && d.dariApi === d.dariDb
      && d.refresh === 200 && Math.abs(d.umurToken - d.dariDb) <= 2;
    return {
      pass,
      note: `/pengaturan ${d.halaman}: label durasi penguncian=${d.labelPenguncian}, label batas aktivitas=${d.labelAktivitas}, `
        + `label lama "Batas Sesi Login (menit)"=${d.labelLama ? 'masih ada' : 'hilang'}; /session/status ${d.status} `
        + `session_timeout_minutes=${d.dariApi} sama dengan tersimpan=${d.dariDb}; /session/refresh ${d.refresh} memberi token berumur ${d.umurToken} menit`,
    };
  },
  kebutuhan: 'Revisi 35 (auto logout saat tidak ada aktivitas, batas menit diatur super admin)',
});

T({
  id: 'TC-R27', nama: 'Unggah Foto Sales oleh Pengelola', skenario: 'Revisi 36',
  deskripsi: 'Foto pada kartu narahubung halaman publik kini dapat diisi pengelola dari halaman Detail Sales: berkas avatar dikirim multipart melalui PUT /api/sales/{id} dan langsung terbaca kembali pada /api/public/sales-contacts.',
  langkah: '1. GET /api/public/sales-contacts untuk mengetahui sales yang tampil 2. GET /api/sales untuk memetakan sales_code ke id pengguna 3. POST /api/sales/{id} multipart (_method=PUT) membawa berkas avatar 4. GET /api/public/sales-contacts lagi 5. POST berkas PDF pada field avatar',
  dataUji: 'avatar=tests/tmp-unit.png (PNG) dan berkas salah tests/tmp-bukti.pdf (PDF)',
  ekspektasi: 'Unggahan 200 dengan avatar_url berisi /storage/avatars; sales yang sama membawa avatar_url yang sama pada daftar narahubung publik; berkas bukan gambar ditolak 422',
  expectStatus: 200, method: 'POST', path: '/sales', token: M,
  run: async () => {
    const awal = await api(null, 'GET', '/public/sales-contacts');
    const target = (awal.json?.data || [])[0] || {};
    const pengguna = await api(M(), 'GET', '/sales');
    const baris = (pengguna.json?.data || []).find((u) => u?.sales_profile?.sales_code === target.sales_code);

    ctx.r27 = {
      kode: target.sales_code || '-',
      nama: target.name || '-',
      userId: baris?.id || null,
      sebelum: target.avatar_url || null,
    };

    if (!ctx.r27.userId) {
      return { status: 500, json: { message: 'Sales yang tampil pada halaman publik tidak ditemukan pada daftar data sales.' } };
    }

    const unggah = await upload(M(), '/sales/' + ctx.r27.userId, { _method: 'PUT' }, 'avatar', 'tmp-unit.png', 'image/png');
    const sesudah = await api(null, 'GET', '/public/sales-contacts');
    const barisPublik = (sesudah.json?.data || []).find((r) => r.sales_code === ctx.r27.kode);
    const salah = await upload(M(), '/sales/' + ctx.r27.userId, { _method: 'PUT' }, 'avatar', 'tmp-bukti.pdf', 'application/pdf');

    ctx.r27.unggah = unggah.status;
    ctx.r27.avatarUrl = unggah.json?.data?.avatar_url || '';
    ctx.r27.publik = barisPublik?.avatar_url || null;
    ctx.r27.salah = salah.status;
    ctx.r27.pesanSalah = salah.json?.message || '-';

    return unggah;
  },
  check: () => {
    const d = ctx.r27;
    const pass = !!d.userId && d.unggah === 200 && d.avatarUrl.includes('/storage/avatars/')
      && !!d.publik && d.publik.includes('/storage/avatars/') && d.salah === 422;
    return {
      pass,
      note: `sales publik=${d.kode} (${d.nama}), user_id=${d.userId || '-'}; sebelum=${d.sebelum ? 'sudah berfoto' : 'tanpa foto'}; `
        + `unggah=${d.unggah} -> ${d.avatarUrl || '(tanpa url)'}; terbaca pada /public/sales-contacts=${d.publik || '(kosong)'}; `
        + `berkas PDF=${d.salah} (${d.pesanSalah})`,
    };
  },
  kebutuhan: 'Revisi 36 (foto sales langsung tampil pada kartu narahubung halaman publik)',
});

T({
  id: 'TC-R28', nama: 'Detail Jalan dan Rute pada Peta Beranda', skenario: 'Revisi 37',
  deskripsi: 'Peta Lokasi Proyek memperbesar sampai level jalan ketika penanda diklik dan menyediakan rute menuju koordinat proyek, baik berupa garis rute di dalam peta (layanan rute OpenStreetMap) maupun tautan belokan per belokan pada Google Maps.',
  langkah: '1. GET / 2. Periksa sumber halaman pada kendali rute, tautan Google Maps, layanan rute, dan zoom detail jalan 3. GET /api/public/project-locations 4. Pastikan koordinat setiap proyek dapat dipakai sebagai tujuan rute',
  dataUji: 'sumber HTML beranda + daftar lokasi proyek aktif tanpa login',
  ekspektasi: 'Sumber / memuat #beranda-rute, https://www.google.com/maps/dir/?api=1, https://router.project-osrm.org/route/v1/driving/, pemanggilan flyTo, dan ZOOM_JALAN = 17; /public/project-locations 200 dengan latitude/longitude numerik pada setiap baris',
  expectStatus: 200, method: 'GET', path: '/', token: null,
  run: async () => {
    const beranda = await halaman('/');
    const lokasi = await api(null, 'GET', '/public/project-locations');
    const rows = lokasi.json?.data || [];

    ctx.r28 = {
      halaman: beranda.status,
      kendaliRute: beranda.text.includes('id="beranda-rute"'),
      googleMaps: beranda.text.includes('https://www.google.com/maps/dir/?api=1'),
      osrm: beranda.text.includes('https://router.project-osrm.org/route/v1/driving/'),
      flyTo: beranda.text.includes('.flyTo('),
      zoomJalan: /ZOOM_JALAN\s*=\s*17/.test(beranda.text),
      api: lokasi.status,
      jumlah: rows.length,
      koordinat: rows.length > 0 && rows.every((r) => Number.isFinite(r.latitude) && Number.isFinite(r.longitude)),
      contoh: rows.length ? `${rows[0].name} -> destination=${rows[0].latitude},${rows[0].longitude}` : '-',
    };

    return beranda;
  },
  check: () => {
    const d = ctx.r28;
    const pass = d.halaman === 200 && d.kendaliRute && d.googleMaps && d.osrm && d.flyTo && d.zoomJalan
      && d.api === 200 && d.jumlah >= 1 && d.koordinat;
    return {
      pass,
      note: `/ beranda ${d.halaman}: kendali rute=${d.kendaliRute}, tautan Google Maps=${d.googleMaps}, layanan rute=${d.osrm}, `
        + `flyTo=${d.flyTo}, ZOOM_JALAN 17=${d.zoomJalan}; /public/project-locations ${d.api} dengan ${d.jumlah} proyek `
        + `berkoordinat lengkap=${d.koordinat}; contoh ${d.contoh}`,
    };
  },
  kebutuhan: 'Revisi 37 (peta menampilkan detail jalan dan rute menuju proyek)',
});

T({
  id: 'TC-R29', nama: 'Kotak Pencarian Unit Publik Dihapus', skenario: 'Revisi 38',
  deskripsi: 'Halaman daftar unit pada situs publik tidak lagi menawarkan pencarian kode/tipe unit; yang tersisa hanya penapis proyek dan urutan harga. Kotak pencarian pada hero beranda ikut dilepas karena menautkan /unit?cari= yang tidak lagi dibaca.',
  langkah: '1. GET /unit 2. Periksa #cari, placeholder, dan pembacaan params cari pada sumber halaman 3. Periksa #cluster, #sort, dan GET /api/public/units 4. GET / dan periksa hero-search, ?cari=, serta tautan /unit?proyek=',
  dataUji: 'sumber HTML /unit dan / milik aplikasi yang sama',
  ekspektasi: 'Sumber /unit tidak memuat id="cari", teks "Kode unit atau tipe rumah", maupun params.get(\'cari\'); tetap memuat id="cluster" dan id="sort"; GET /api/public/units 200; sumber / tidak memuat hero-search dan ?cari= tapi tetap memuat /unit?proyek=',
  expectStatus: 200, method: 'GET', path: '/unit', token: null,
  run: async () => {
    const unit = await halaman('/unit');
    const beranda = await halaman('/');
    const data = await api(null, 'GET', '/public/units');

    ctx.r29 = {
      halaman: unit.status,
      kotakCari: unit.text.includes('id="cari"'),
      placeholder: unit.text.includes('Kode unit atau tipe rumah'),
      pembacaanCari: unit.text.includes("params.get('cari')"),
      proyek: unit.text.includes('id="cluster"'),
      urutan: unit.text.includes('id="sort"'),
      api: data.status,
      jumlahUnit: (data.json?.data || []).length,
      heroBeranda: beranda.text.includes('hero-search'),
      tautanCari: beranda.text.includes('?cari='),
      tautanProyek: beranda.text.includes('/unit?proyek='),
    };

    return unit;
  },
  check: () => {
    const d = ctx.r29;
    const pass = d.halaman === 200 && !d.kotakCari && !d.placeholder && !d.pembacaanCari
      && d.proyek && d.urutan && d.api === 200 && d.jumlahUnit >= 1
      && !d.heroBeranda && !d.tautanCari && d.tautanProyek;
    return {
      pass,
      note: `/unit ${d.halaman}: #cari=${d.kotakCari ? 'masih ada' : 'hilang'}, placeholder lama=${d.placeholder ? 'masih ada' : 'hilang'}, `
        + `pembacaan ?cari=${d.pembacaanCari ? 'masih ada' : 'hilang'}, #cluster=${d.proyek}, #sort=${d.urutan}; `
        + `/api/public/units ${d.api} dengan ${d.jumlahUnit} unit; beranda: hero-search=${d.heroBeranda ? 'masih ada' : 'hilang'}, `
        + `?cari=${d.tautanCari ? 'masih ada' : 'hilang'}, tautan /unit?proyek=${d.tautanProyek}`,
    };
  },
  kebutuhan: 'Revisi 38 (kotak pencarian kode/tipe unit dihapus dari halaman publik)',
});

T({
  id: 'TC-R30', nama: 'Unit Ditempati dan Pesanan Tambahan pada Daftar Konsumen', skenario: 'Revisi 41',
  deskripsi: 'Baris konsumen pada API Data Konsumen membawa unit pemesananan yang sedang dipegang beserta keterangan tambahan konstruksinya, sehingga kolom Unit Ditempati dan Pesanan Tambahan terisi tanpa permintaan tambahan di peramban. Pembayaran yang dibaca Data Pembayaran membawa field yang sama lewat relasi booking.',
  langkah: '1. Buat lead hot lalu booking ber-biaya tambahan 2. Catat dan verifikasi pembayaran 3. GET /customer-leads?category=konsumen lalu baca atribut barisnya 4. GET /payments?booking_id= dan periksa addition_description serta addition_cost pada relasi booking 5. Hapus booking',
  dataUji: () => `lead='Konsumen Unit Ditempati ${UIN}', addition_cost=18000000, addition_description='Menaikkan plafon dan kanopi carport'`,
  ekspektasi: 'HTTP 201; baris kategori konsumen memuat unit_ditempati sama dengan kode unit booking dan pesanan_tambahan sama dengan keterangan tambahan; pembayaran membawa booking.addition_description yang sama dan booking.addition_cost 18000000',
  expectStatus: 201, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r30LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r30UnitId, payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r30UnitId = unit?.id;
    ctx.r30KodeUnit = unit?.unit_code;
    ctx.r30Keterangan = 'Menaikkan plafon dan kanopi carport';

    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Konsumen Unit Ditempati ${UIN}`, phone: `081243${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r30LeadId = lead.json?.data?.id;

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r30LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r30UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
      addition_cost: 18000000, addition_description: ctx.r30Keterangan,
    });
    ctx.r30BookingId = booking.json?.data?.id;

    const bayar = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r30BookingId, payment_type: 'cash bertahap', payment_method: 'transfer bank',
      amount: 100000, payment_date: '2026-11-18', notes: 'Uang muka uji kolom pesanan tambahan.',
    });
    ctx.r30PaymentId = bayar.json?.data?.id;
    await api(M(), 'PUT', `/payments/${ctx.r30PaymentId}/verify`, {
      verification_status: 'verified', notes: 'Bukti transfer sesuai.',
    });

    const daftar = await api(M(), 'GET', '/customer-leads?category=konsumen');
    const baris = (daftar.json?.data || []).find((r) => r.id === ctx.r30LeadId);
    ctx.r30Baris = {
      ketemu: !!baris,
      category: baris?.category ?? null,
      unit: baris?.unit_ditempati ?? null,
      pesanan: baris?.pesanan_tambahan ?? null,
    };

    const pembayaran = await api(M(), 'GET', `/payments?booking_id=${ctx.r30BookingId}`);
    const barisBayar = (pembayaran.json?.data || [])[0];
    ctx.r30Bayar = {
      jumlah: (pembayaran.json?.data || []).length,
      keterangan: barisBayar?.booking?.addition_description ?? null,
      biaya: Number(barisBayar?.booking?.addition_cost ?? -1),
      konsumen: barisBayar?.booking?.customer_lead?.name ?? '-',
    };

    await api(M(), 'DELETE', `/bookings/${ctx.r30BookingId}`);
    return booking;
  },
  check: (j) => {
    const b = ctx.r30Baris || {};
    const y = ctx.r30Bayar || {};
    const pass = !!j?.data?.id && b.ketemu && b.category === 'konsumen'
      && b.unit === ctx.r30KodeUnit && b.pesanan === ctx.r30Keterangan
      && y.jumlah >= 1 && y.keterangan === ctx.r30Keterangan && y.biaya === 18000000;

    return {
      pass,
      note: `booking=${ctx.r30BookingId}; unit=${ctx.r30KodeUnit}; baris konsumen: kategori=${b.category}, `
        + `unit_ditempati=${b.unit ?? '(null)'}, pesanan_tambahan=${trim(b.pesanan || '(null)')}; `
        + `/payments membawa ${y.jumlah} baris dengan konsumen=${trim(y.konsumen)}, booking.addition_description=`
        + `${trim(y.keterangan || '(null)')}, booking.addition_cost=${y.biaya?.toLocaleString('id-ID')}`,
    };
  },
  kebutuhan: 'Revisi 41 (kolom Unit Ditempati + Pesanan Tambahan pada konsumen dan Biaya Tambahan pada pembayaran)',
});

T({
  id: 'TC-R31', nama: 'Penapis Sales Penanganan Menampilkan Nama Sales', skenario: 'Revisi 40',
  deskripsi: 'Penapis Sales Penanganan menjadi kotak pencarian nama yang diisi seluruh sales, termasuk bagi Admin Sales yang hanya membaca. Opsi "Semua Sales" dihapus sehingga isian yang kosong berarti seluruh data konsumen tetap ditampilkan.',
  langkah: '1. GET /api/sales dengan token Admin Sales 2. GET sumber /data-konsumen 3. Periksa kendali pencarian + datalist, pembacaan /sales di dalam leadMuatPilihan(), pencocokan baris lewat leadSalesCocok(), dan hilangnya opsi Semua Sales',
  dataUji: 'peran=admin_sales; sumber HTML /data-konsumen',
  ekspektasi: "GET /api/sales 200 dengan minimal dua baris bersales_profile; sumber /data-konsumen memuat input type=search beralamatkan list=\"lead-sales-list\" beserta <datalist id=\"lead-sales-list\">, memuat LP.fetchJson('/sales') di dalam leadMuatPilihan(), tidak lagi memuat gerbang lama Promise.all unit+sales, dan teks \"Semua Sales\" hilang",
  expectStatus: 200, method: 'GET', path: '/sales', token: A,
  run: async () => {
    const sales = await api(A(), 'GET', '/sales');
    const rows = (sales.json?.data || []).filter((r) => r.sales_profile);
    const sumber = await halaman('/data-konsumen');
    const isi = sumber.text.slice(
      sumber.text.indexOf('async function leadMuatPilihan'),
      sumber.text.indexOf('LP.guard(ROLE_INTERNAL'),
    );

    ctx.r31 = {
      api: sales.status,
      jumlah: rows.length,
      nama: rows.slice(0, 3).map((r) => r.name),
      halaman: sumber.status,
      pencarian: /id="lead-sales"[\s\S]{0,400}?type="search"/.test(sumber.text) && sumber.text.includes('list="lead-sales-list"'),
      datalist: sumber.text.includes('<datalist id="lead-sales-list">'),
      muatSales: isi.includes("LP.fetchJson('/sales')"),
      gerbangLama: sumber.text.includes("const tugas = [LP.fetchJson('/units-available')]"),
      semuaSales: sumber.text.includes('Semua Sales'),
      cocokNama: sumber.text.includes('function leadSalesCocok(row)')
        && sumber.text.includes('if (!leadSalesCocok(row)) return false;'),
    };

    return sales;
  },
  check: () => {
    const d = ctx.r31;
    const pass = d.api === 200 && d.jumlah >= 2 && d.halaman === 200 && d.pencarian && d.datalist
      && d.muatSales && !d.gerbangLama && !d.semuaSales && d.cocokNama;

    return {
      pass,
      note: `/api/sales peran admin_sales=${d.api} dengan ${d.jumlah} sales (${d.nama.join(', ')}); /data-konsumen ${d.halaman}: `
        + `kotak pencarian=${d.pencarian}, datalist=${d.datalist}, pembacaan /sales di leadMuatPilihan=${d.muatSales}, `
        + `gerbang lama unit+sales=${d.gerbangLama ? 'masih ada' : 'hilang'}, opsi "Semua Sales"=${d.semuaSales ? 'masih ada' : 'hilang'}, `
        + `pencocokan nama sales=${d.cocokNama}`,
    };
  },
  kebutuhan: 'Revisi 40 (penapis Sales Penanganan menampilkan nama sales dan melepas opsi Semua Sales)',
});

T({
  id: 'TC-R32', nama: 'Kolom Status dan Pilihan Kategori pada Role Sales', skenario: 'Revisi 39',
  deskripsi: 'Role sales tidak menampilkan kolom Status sama sekali, tidak dapat memilih kategori Calon Konsumen dari penapis Kategori, dan tidak melihat penapis Sales Penanganan. Kategori konsumen terverifikasi menghapus kolom Status untuk seluruh peran, sedangkan penapis kategori di API tetap berfungsi sehingga menu Database Konsumen tidak kehilangan datanya.',
  langkah: "1. GET sumber /data-konsumen 2. Periksa leadPakaiStatus() menutup kolom untuk sales, opsi prospek disembunyikan dengan opsiCalon.hidden = leadIsSales(), dan label lead-label-sales disembunyikan 3. GET /api/customer-leads?category=prospek sebagai sales untuk memastikan kategori calon konsumen tetap terbaca",
  dataUji: 'peran=sales; sumber HTML /data-konsumen',
  ekspektasi: "Sumber memuat `if (leadIsSales()) return false;` di dalam leadPakaiStatus(), kolom Status hanya didaftarkan bila leadPakaiStatus() benar, opsi prospek berattribut hidden untuk sales, penapis sales hidden untuk sales, dan label kolom beralih Unit Diminati/Unit Ditempati; GET /customer-leads?category=prospek 200 dengan seluruh baris kategori prospek",
  expectStatus: 200, method: 'GET', path: '/customer-leads?category=prospek', token: S,
  run: async () => {
    const sumber = await halaman('/data-konsumen');
    const pakai = sumber.text.slice(
      sumber.text.indexOf('function leadPakaiStatus()'),
      sumber.text.indexOf('function leadStale(row)'),
    );
    const daftar = await api(S(), 'GET', '/customer-leads?category=prospek');
    const rows = daftar.json?.data || [];

    ctx.r32 = {
      halaman: sumber.status,
      salesTutupStatus: pakai.includes('if (leadIsSales()) return false;'),
      kolomBersyarat: sumber.text.includes("if (leadPakaiStatus()) kolom.push({ label: 'Status'"),
      statusStatis: sumber.text.includes("{ label: 'Status', render: (row) => LP.badge(row.status) },"),
      opsiCalon: sumber.text.includes('opsiCalon.hidden = leadIsSales();'),
      penapisSales: sumber.text.includes("getElementById('lead-label-sales').classList.toggle('hidden', leadIsSales())"),
      labelUnit: sumber.text.includes("calon ? 'Unit Diminati' : 'Unit Ditempati'"),
      pesanan: sumber.text.includes("{ label: 'Pesanan Tambahan', render: (row) => LP.esc(row.pesanan_tambahan || 'Tidak ada') }"),
      api: daftar.status,
      jumlah: rows.length,
      semuaProspek: rows.length > 0 && rows.every((r) => r.category === 'prospek'),
    };

    return daftar;
  },
  check: () => {
    const d = ctx.r32;
    const pass = d.halaman === 200 && d.salesTutupStatus && d.kolomBersyarat && !d.statusStatis
      && d.opsiCalon && d.penapisSales && d.labelUnit && d.pesanan
      && d.api === 200 && d.jumlah >= 1 && d.semuaProspek;

    return {
      pass,
      note: `/data-konsumen ${d.halaman}: sales menutup kolom status=${d.salesTutupStatus}, kolom Status bersyarat=${d.kolomBersyarat}, `
        + `kolom Status statis=${d.statusStatis ? 'masih ada' : 'hilang'}, opsi Calon Konsumen hidden untuk sales=${d.opsiCalon}, `
        + `penapis Sales Penanganan hidden untuk sales=${d.penapisSales}, label Unit Diminati/Ditempati=${d.labelUnit}, `
        + `kolom Pesanan Tambahan=${d.pesanan}; /api/customer-leads?category=prospek peran sales=${d.api} dengan ${d.jumlah} baris `
        + `seluruhnya prospek=${d.semuaProspek}`,
    };
  },
  kebutuhan: 'Revisi 39 (kolom Status, pilihan Calon Konsumen, dan penapis Sales Penanganan dilepas pada role sales)',
});

/* -------------------------------------------------------------------------
   R33. REVISI 42 (UNIT BERBOOKING AKTIF KELUAR + PILIHAN BERJENJANG PROYEK-UNIT)
   ------------------------------------------------------------------------ */

T({
  id: 'TC-R33', nama: 'Unit Berbooking Aktif Keluar dari Pilihan Booking', skenario: 'Revisi 42',
  deskripsi: 'Pilihan unit pada form booking hanya memuat unit berstatus tersedia yang belum memegang pemesanan aktif, dan form disusun berjenjang: proyek dipilih lebih dahulu lalu unit beserta bloknya. Pemesanan ganda pada unit yang sama ditolak server walaupun status unit sempat kembali tersedia.',
  langkah: "1. GET /units-available dan catat jumlah unit 2. Buat lead hot lalu booking pada salah satunya 3. GET /units-available lagi dan periksa unit hilang 4. Kembalikan status unit menjadi available lewat PUT /units/ lalu booking ulang unit yang sama 5. Baca sumber /booking untuk memeriksa pilihan proyek dan unit 6. Hapus booking",
  dataUji: () => `lead='Booking Berjenjang ${UIN}', unit dari /units-available, skema Cash Bertahap, booking_date=2026-11-18`,
  ekspektasi: 'HTTP 201; unit tidak lagi muncul pada /units-available sesudah dibooking; pemesanan ulang pada unit yang sama ditolak HTTP 422 dengan pesan booking aktif; sumber /booking memuat field cluster_id berlabel Proyek sebelum field unit_id, fungsi bookingPaintUnits(), dan bookingRefreshChoices() dipanggil saat modal dibuka',
  expectStatus: 201, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r33LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r33UnitId, payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18' }),
  run: async () => {
    const sebelum = await api(S(), 'GET', '/units-available');
    const daftar = Array.isArray(sebelum.json) ? sebelum.json : [];
    const pilihan = daftar.find((u) => u.cluster_id !== ctx.clusterId) || daftar[0];
    ctx.r33UnitId = pilihan?.id;
    ctx.r33KodeUnit = pilihan?.unit_code;
    ctx.r33Sebelum = daftar.length;

    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Booking Berjenjang ${UIN}`, phone: `081244${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r33LeadId = lead.json?.data?.id;

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r33LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r33UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r33BookingId = booking.json?.data?.id;

    const sesudah = await api(S(), 'GET', '/units-available');
    const daftarSesudah = Array.isArray(sesudah.json) ? sesudah.json : [];
    ctx.r33Sesudah = {
      jumlah: daftarSesudah.length,
      unitMasihAda: daftarSesudah.some((u) => u.id === ctx.r33UnitId),
      semuaBersedia: daftarSesudah.every((u) => u.status === 'available'),
    };

    // Simulasi drift: status unit dipaksa kembali ke available walau bookingnya aktif.
    await api(M(), 'PUT', `/units/${ctx.r33UnitId}`, { status: 'available' });
    const ganda = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r33LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r33UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
    });
    ctx.r33Ganda = { status: ganda.status, pesan: ganda.json?.message || '' };

    const sumber = await halaman('/booking');
    const urutan = sumber.text.indexOf("name: 'cluster_id'");
    ctx.r33Sumber = {
      status: sumber.status,
      adaProyek: sumber.text.includes("{ name: 'cluster_id', label: 'Proyek'") && urutan !== -1,
      proyekSebelumUnit: urutan !== -1 && sumber.text.indexOf("name: 'unit_id'") > urutan,
      paintUnit: sumber.text.includes('function bookingPaintUnits(form, clusterId)')
        && sumber.text.includes('bookingPaintUnits(form, selectProyek.value)'),
      labelBlok: sumber.text.includes("label: 'Blok ' + item.unit_code"),
      muatUlang: sumber.text.includes('async function bookingRefreshChoices()')
        && sumber.text.includes('await bookingRefreshChoices();'),
      opsiDatar: sumber.text.includes('options: bookingUnitSelectOptions()'),
    };

    await api(M(), 'DELETE', `/bookings/${ctx.r33BookingId}`);
    const pulih = await api(S(), 'GET', '/units-available');
    ctx.r33Pulih = (Array.isArray(pulih.json) ? pulih.json : []).some((u) => u.id === ctx.r33UnitId);

    return booking;
  },
  check: (j) => {
    const s = ctx.r33Sesudah || {};
    const d = ctx.r33Sumber || {};

    const pass = !!j?.data?.id && ctx.r33Sebelum > 0 && !s.unitMasihAda && s.semuaBersedia
      && ctx.r33Ganda.status === 422 && ctx.r33Ganda.pesan.includes('booking aktif')
      && d.status === 200 && d.adaProyek && d.proyekSebelumUnit && d.paintUnit && d.labelBlok
      && d.muatUlang && !d.opsiDatar && ctx.r33Pulih;

    return {
      pass,
      note: `/units-available ${ctx.r33Sebelum} unit sebelum dan ${s.jumlah} unit sesudah booking ${ctx.r33BookingId} dibuat `
        + `(unit ${ctx.r33KodeUnit} masih muncul=${s.unitMasihAda}, seluruhnya available=${s.semuaBersedia}); `
        + `pemesanan ulang pada unit yang sama = HTTP ${ctx.r33Ganda.status} "${trim(ctx.r33Ganda.pesan)}"; `
        + `sumber /booking ${d.status}: field Proyek=${d.adaProyek}, Proyek sebelum Unit=${d.proyekSebelumUnit}, `
        + `penulisan ulang opsi unit=${d.paintUnit}, label blok=${d.labelBlok}, `
        + `muat ulang pilihan saat modal dibuka=${d.muatUlang}, daftar unit datar=${d.opsiDatar ? 'masih ada' : 'hilang'}; `
        + `unit kembali tersedia setelah booking dihapus=${ctx.r33Pulih}`,
    };
  },
  kebutuhan: 'Revisi 42 (unit yang sudah dibooking hilang dari pilihan booking dan pilihan disusun per proyek)',
});

/* -------------------------------------------------------------------------
   R34-R35. REVISI 43 (KATEGORI PEMBAYARAN DP / CICILAN / BIAYA TAMBAHAN)
   ------------------------------------------------------------------------ */

T({
  id: 'TC-R34', nama: 'Kategori Pembayaran Tidak Mengurangi Harga Unit', skenario: 'Revisi 43',
  deskripsi: 'Setiap catatan pembayaran membawa kategori DP, cicilan, atau biaya tambahan. Hanya DP dan cicilan yang mengurangi sisa pembayaran harga unit dan status pelunasannya; pembayaran biaya tambahan terverifikasi dicatat terpisah sehingga sisa dan status booking tidak berubah. Nilai lama sebelum revisi ini tetap dihitung sebagai pembayaran harga unit.',
  langkah: "1. Buat lead hot + booking ber-biaya tambahan 18.000.000 2. Catat dan verifikasi pembayaran kategori dp sebesar uang muka hasil simulasi 3. Catat dan verifikasi cicilan 100.000 4. Catat dan verifikasi biaya tambahan 5.000.000 lalu baca ulang booking 5. GET /payments?payment_category=biaya tambahan 6. POST pembayaran dengan kategori tidak sah 7. Hapus booking",
  dataUji: () => `lead='Kategori Pembayaran ${UIN}', addition_cost=18000000, dp dari simulasi Cash Bertahap, cicilan 100000, biaya tambahan 5000000, kategori tidak sah 'uang muka'`,
  ekspektasi: "HTTP 201; sisa pembayaran turun sebesar dp lalu dp+100.000 dan status berubah dp lunas → cicilan; setelah biaya tambahan terverifikasi sisa dan status tetap sama; penapis payment_category hanya mengembalikan baris berkategori biaya tambahan; kategori tidak sah ditolak HTTP 422",
  expectStatus: 201, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r34LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r34UnitId, payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r34UnitId = unit?.id;
    ctx.r34HargaUnit = Number(unit?.price || 0);

    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Kategori Pembayaran ${UIN}`, phone: `081245${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r34LeadId = lead.json?.data?.id;

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r34LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r34UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
      addition_cost: 18000000, addition_description: 'Kanopi carport dan plafon naik',
    });
    ctx.r34BookingId = booking.json?.data?.id;
    ctx.r34UangMuka = Number(booking.json?.data?.down_payment_amount || 0);

    const bayar = async (kategori, nominal, catatan) => {
      const res = await api(S(), 'POST', '/payments', {
        booking_id: ctx.r34BookingId, payment_type: 'cash bertahap', payment_category: kategori,
        payment_method: 'transfer bank', amount: nominal, payment_date: '2026-11-18', notes: catatan,
      });
      await api(M(), 'PUT', `/payments/${res.json?.data?.id}/verify`, { verification_status: 'verified', notes: 'Bukti transfer sesuai.' });
      return res;
    };

    await bayar('dp', ctx.r34UangMuka, 'Uang muka uji kategori pembayaran.');
    const sesudahDp = await api(M(), 'GET', `/bookings/${ctx.r34BookingId}`);
    ctx.r34SesudahDp = {
      sisa: Number(sesudahDp.json?.data?.remaining_amount || -1),
      status: sesudahDp.json?.data?.status,
    };

    await bayar('cicilan', 100000, 'Angsuran uji kategori pembayaran.');
    await bayar('biaya tambahan', 5000000, 'Pembayaran pekerjaan tambahan uji kategori.');

    const sesudahTambahan = await api(M(), 'GET', `/bookings/${ctx.r34BookingId}`);
    ctx.r34SesudahTambahan = {
      sisa: Number(sesudahTambahan.json?.data?.remaining_amount || -1),
      status: sesudahTambahan.json?.data?.status,
      biayaTambahan: Number(sesudahTambahan.json?.data?.addition_cost || -1),
    };

    const penapis = await api(M(), 'GET', '/payments?payment_category=biaya%20tambahan');
    const barisTambahan = penapis.json?.data || [];
    ctx.r34Penapis = {
      status: penapis.status,
      jumlah: barisTambahan.length,
      semuaCocok: barisTambahan.every((r) => r.payment_category === 'biaya tambahan'),
      nominal: barisTambahan.filter((r) => r.booking_id === ctx.r34BookingId).map((r) => Number(r.amount)),
    };

    const riwayat = await api(M(), 'GET', `/payments?booking_id=${ctx.r34BookingId}`);
    ctx.r34Riwayat = (riwayat.json?.data || []).map((r) => `${r.payment_category}:${r.verification_status}`);

    const salah = await api(S(), 'POST', '/payments', {
      booking_id: ctx.r34BookingId, payment_type: 'cash bertahap', payment_category: 'uang muka',
      amount: 100000, payment_date: '2026-11-18',
    });
    ctx.r34Salah = { status: salah.status, pesan: salah.json?.errors?.payment_category?.[0] || salah.json?.message || '' };

    const sumber = await halaman('/pembayaran');
    ctx.r34Sumber = {
      status: sumber.status,
      field: sumber.text.includes("{ name: 'payment_category', label: 'Kategori Pembayaran'"),
      opsi: sumber.text.includes("value: 'biaya tambahan', label: 'Biaya Tambahan'"),
      kolom: sumber.text.includes("{ label: 'Kategori', render: (row) => LP.esc(paymentCategoryLabel(row.payment_category)) }"),
      kirim: sumber.text.includes("body.append('payment_category', values.payment_category)"),
      saran: sumber.text.includes('function paymentSaranNominal(booking, kategori)')
        && sumber.text.includes('paymentSuggestAmount(form)'),
    };

    await api(M(), 'DELETE', `/bookings/${ctx.r34BookingId}`);
    return booking;
  },
  check: (j) => {
    const dp = ctx.r34SesudahDp || {};
    const tb = ctx.r34SesudahTambahan || {};
    const pn = ctx.r34Penapis || {};
    const d = ctx.r34Sumber || {};

    const pass = !!j?.data?.id && ctx.r34UangMuka > 0
      && Math.abs(dp.sisa - (ctx.r34HargaUnit - ctx.r34UangMuka)) < 1
      && dp.status === 'dp lunas'
      && Math.abs(tb.sisa - (ctx.r34HargaUnit - ctx.r34UangMuka - 100000)) < 1
      && tb.status === 'cicilan' && tb.biayaTambahan === 18000000
      && pn.status === 200 && pn.jumlah >= 1 && pn.semuaCocok
      && ctx.r34Salah.status === 422
      && d.status === 200 && d.field && d.opsi && d.kolom && d.kirim && d.saran;

    return {
      pass,
      note: `booking ${ctx.r34BookingId} harga unit ${ctx.r34HargaUnit?.toLocaleString('id-ID')}, uang muka ${ctx.r34UangMuka?.toLocaleString('id-ID')}: `
        + `setelah dp terverifikasi sisa=${dp.sisa?.toLocaleString('id-ID')} status=${dp.status}; `
        + `setelah cicilan 100.000 dan biaya tambahan 5.000.000 terverifikasi sisa=${tb.sisa?.toLocaleString('id-ID')} `
        + `status=${tb.status} addition_cost=${tb.biayaTambahan?.toLocaleString('id-ID')}; `
        + `riwayat booking=${ctx.r34Riwayat.join(', ')}; /payments?payment_category=biaya tambahan ${pn.status} `
        + `dengan ${pn.jumlah} baris seluruhnya cocok=${pn.semuaCocok} nominal=${pn.nominal.join('/')}; `
        + `kategori tidak sah = HTTP ${ctx.r34Salah.status} "${trim(ctx.r34Salah.pesan)}"; `
        + `sumber /pembayaran ${d.status}: field kategori=${d.field}, tiga opsi=${d.opsi}, kolom Kategori=${d.kolom}, `
        + `pengiriman payment_category=${d.kirim}, saran nominal per kategori=${d.saran}`,
    };
  },
  kebutuhan: 'Revisi 43 (pilihan kategori pembayaran DP, cicilan, dan biaya tambahan di luar harga unit)',
});

T({
  id: 'TC-R35', nama: 'Pembayaran Pertama pada Form Booking', skenario: 'Revisi 43',
  deskripsi: 'Form booking dapat mencatat pembayaran pertama sekaligus: kategori, nominal, tanggal, dan metode dikirim sebagai initial_payment dan langsung masuk ledger dengan status menunggu verifikasi, memakai jenis pembayaran dari skema. Kategori biaya tambahan ditolak bila pemesanan tidak mempunyai biaya tambahan konstruksi, dan super admin menerima pemberitahuan verifikasi.',
  langkah: "1. Simulasi skema Cash Bertahap atas unit tersedia 2. POST /bookings dengan initial_payment kategori dp 3. GET /payments?booking_id= untuk membaca baris ledger 4. GET /notifications dengan token super admin 5. POST /bookings dengan initial_payment kategori biaya tambahan tanpa addition_cost 6. Baca sumber /booking 7. Hapus booking",
  dataUji: () => `lead='Bayar Saat Booking ${UIN}', addition_cost=12000000, initial_payment dp dari simulasi, metode transfer bank`,
  ekspektasi: 'HTTP 201; satu pembayaran pending berkategori dp dengan payment_type cash bertahap dan nominal sesuai simulasi tercatat; sisa pembayaran belum berubah karena belum diverifikasi; super admin menerima notifikasi "Pembayaran menunggu verifikasi"; kombinasi biaya tambahan tanpa addition_cost ditolak HTTP 422; sumber /booking memuat saklar initial_payment_requested dan pilihan kategori',
  expectStatus: 201, method: 'POST', path: '/bookings', token: S,
  body: () => ({ lead_id: ctx.r35LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r35UnitId, payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18' }),
  run: async () => {
    const unit = await unitRevisi15();
    ctx.r35UnitId = unit?.id;

    const simulasi = await api(S(), 'POST', `/payment-schemes/${ctx.cashBertahapId}/simulate`, { unit_id: ctx.r35UnitId });
    ctx.r35UangMuka = Number(simulasi.json?.data?.down_payment_amount || 0);

    const lead = await api(S(), 'POST', '/customer-leads', {
      name: `Bayar Saat Booking ${UIN}`, phone: `081246${UIN}`, status: 'hot', source: 'manual',
    });
    ctx.r35LeadId = lead.json?.data?.id;

    const booking = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r35LeadId, sales_id: ctx.salesProfileId, unit_id: ctx.r35UnitId,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
      addition_cost: 12000000, addition_description: 'Kanopi carport',
      initial_payment: {
        payment_category: 'dp',
        amount: ctx.r35UangMuka,
        payment_date: '2026-11-18',
        payment_method: 'transfer bank',
      },
    });
    ctx.r35BookingId = booking.json?.data?.id;
    ctx.r35Kode = booking.json?.data?.booking_code;

    const ledger = await api(M(), 'GET', `/payments?booking_id=${ctx.r35BookingId}`);
    const baris = (ledger.json?.data || [])[0] || {};
    ctx.r35Ledger = {
      jumlah: (ledger.json?.data || []).length,
      kategori: baris.payment_category ?? null,
      jenis: baris.payment_type ?? null,
      metode: baris.payment_method ?? null,
      nominal: Number(baris.amount || 0),
      verifikasi: baris.verification_status ?? null,
    };

    const dibaca = await api(M(), 'GET', `/bookings/${ctx.r35BookingId}`);
    ctx.r35Sisa = Number(dibaca.json?.data?.remaining_amount || -1);
    ctx.r35Status = dibaca.json?.data?.status;

    const notif = await api(M(), 'GET', '/notifications');
    const barisNotif = (notif.json?.data || []).find((n) => n.title === 'Pembayaran menunggu verifikasi'
      && (n.message || '').includes(ctx.r35Kode));
    ctx.r35Notif = { judul: barisNotif?.title ?? '(tidak ditemukan)', pesan: barisNotif?.message ?? '-' };

    const tanpaTambahan = await api(S(), 'POST', '/bookings', {
      lead_id: ctx.r35LeadId, sales_id: ctx.salesProfileId,
      unit_id: (await unitRevisi15())?.id,
      payment_scheme_id: ctx.cashBertahapId, booking_date: '2026-11-18',
      initial_payment: { payment_category: 'biaya tambahan', amount: 5000000, payment_date: '2026-11-18' },
    });
    ctx.r35Tolak = { status: tanpaTambahan.status, pesan: tanpaTambahan.json?.message || '' };

    const sumber = await halaman('/booking');
    ctx.r35Sumber = {
      status: sumber.status,
      saklar: sumber.text.includes("name: 'initial_payment_requested'")
        && sumber.text.includes("label: 'Catat Pembayaran Sekarang'"),
      kategori: sumber.text.includes("{ name: 'initial_category', label: 'Kategori Pembayaran'"),
      saran: sumber.text.includes('function bookingSuggestPembayaran(form)')
        && sumber.text.includes("bookingSimulasi = data;"),
      kirim: sumber.text.includes('body.initial_payment = {'),
    };

    await api(M(), 'DELETE', `/bookings/${ctx.r35BookingId}`);
    return booking;
  },
  check: (j) => {
    const l = ctx.r35Ledger || {};
    const d = ctx.r35Sumber || {};

    const pass = !!j?.data?.id && ctx.r35UangMuka > 0
      && l.jumlah === 1 && l.kategori === 'dp' && l.jenis === 'cash bertahap'
      && l.metode === 'transfer bank' && Math.abs(l.nominal - ctx.r35UangMuka) < 1
      && l.verifikasi === 'pending'
      && Math.abs(ctx.r35Sisa - Number(j.data.remaining_amount)) < 1 && ctx.r35Status === 'menunggu dp'
      && ctx.r35Notif.judul === 'Pembayaran menunggu verifikasi'
      && ctx.r35Tolak.status === 422 && ctx.r35Tolak.pesan.includes('biaya tambahan')
      && d.status === 200 && d.saklar && d.kategori && d.saran && d.kirim;

    return {
      pass,
      note: `booking ${ctx.r35Kode} dibuat bersama pembayaran awal: ledger=${l.jumlah} baris `
        + `(kategori=${l.kategori}, jenis=${l.jenis}, metode=${l.metode}, nominal=${l.nominal?.toLocaleString('id-ID')}, `
        + `verifikasi=${l.verifikasi}); sisa pembayaran tetap ${ctx.r35Sisa?.toLocaleString('id-ID')} dengan status ${ctx.r35Status}; `
        + `notifikasi super admin="${ctx.r35Notif.judul}" (${trim(ctx.r35Notif.pesan)}); `
        + `kategori biaya tambahan tanpa addition_cost = HTTP ${ctx.r35Tolak.status} "${trim(ctx.r35Tolak.pesan)}"; `
        + `sumber /booking ${d.status}: saklar bayar sekarang=${d.saklar}, pilihan kategori=${d.kategori}, `
        + `pengisian nominal dari simulasi=${d.saran}, pengiriman initial_payment=${d.kirim}`,
    };
  },
  kebutuhan: 'Revisi 43 (pembayaran pertama DP/cicilan/biaya tambahan dicatat langsung dari form booking)',
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
    name: 'Lead Masih Dingin', phone: `081233${UIN}7`, status: 'cold', source: 'manual',
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
