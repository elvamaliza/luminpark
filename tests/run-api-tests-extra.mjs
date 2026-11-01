import { call, results, BASE } from './api-test-harness.mjs';
import { writeFileSync } from 'node:fs';

const login = async (email, password) => {
  const res = await fetch(BASE + '/login', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password })
  });
  return { status: res.status, json: await res.json().catch(() => ({})) };
};

const T = [];
const add = (o) => T.push(o);

add({ id: 'TC-005', nama: 'Login Success (Role Sales)', skenario: 'Autentikasi - Positif',
  deskripsi: 'Akun default role sales dapat login dan memperoleh token Sanctum.',
  langkah: '1. POST /api/login dengan kredensial sales seeder',
  dataUji: '{"email":"sales@luminpark.com","password":"password123"}',
  ekspektasi: 'HTTP 200, success=true, user.role=sales, access_token terisi',
  method: 'POST', path: '/login', body: { email: 'sales@luminpark.com', password: 'password123' }, expectStatus: 200 });

add({ id: 'TC-009', nama: 'Login Success (Role Manager)', skenario: 'Autentikasi - Positif',
  deskripsi: 'Akun default role manager dapat login dan memperoleh token Sanctum.',
  langkah: '1. POST /api/login dengan kredensial manager seeder',
  dataUji: '{"email":"manager@luminpark.com","password":"password123"}',
  ekspektasi: 'HTTP 200, success=true, user.role=manager',
  method: 'POST', path: '/login', body: { email: 'manager@luminpark.com', password: 'password123' }, expectStatus: 200 });

add({ id: 'TC-027', nama: 'Delete Data Sales', skenario: 'CRUD Sales - Delete',
  deskripsi: 'Menghapus user ber-role sales berdasarkan id.',
  langkah: '1. DELETE /api/sales/99999 (id tidak ada) untuk menguji cabang 404',
  dataUji: 'id = 99999',
  ekspektasi: 'HTTP 404, message "Sales tidak ditemukan"',
  method: 'DELETE', path: '/sales/99999', expectStatus: 404 });

add({ id: 'TC-028', nama: 'Reset Password Sales Oleh Admin', skenario: 'CRUD Sales - Reset Password',
  deskripsi: 'Rute POST /api/sales/{id}/reset-password dipanggil controller.',
  langkah: '1. POST /api/sales/3/reset-password',
  dataUji: '{"password":"passwordbaru123"}',
  ekspektasi: 'HTTP 200 dengan pesan reset berhasil',
  method: 'POST', path: '/sales/3/reset-password', body: { password: 'passwordbaru123' }, expectStatus: 200 });

add({ id: 'TC-047', nama: 'Detail Lead Tidak Ditemukan', skenario: 'CRUD Lead - Negatif',
  deskripsi: 'Mengambil detail lead dengan id tidak terdaftar.',
  langkah: '1. GET /api/customer-leads/99999',
  dataUji: 'id = 99999',
  ekspektasi: 'HTTP 404, message "Data tidak ditemukan"',
  method: 'GET', path: '/customer-leads/99999', expectStatus: 404 });

add({ id: 'TC-064', nama: 'Delete Skema Tidak Ditemukan', skenario: 'CRUD Skema - Negatif',
  deskripsi: 'Menghapus skema pembayaran yang tidak ada.',
  langkah: '1. DELETE /api/payment-schemes/99999',
  dataUji: 'id = 99999',
  ekspektasi: 'HTTP 404, message "Not found"',
  method: 'DELETE', path: '/payment-schemes/99999', expectStatus: 404 });

add({ id: 'TC-074', nama: 'Detail Booking Tidak Ditemukan', skenario: 'CRUD Booking - Negatif',
  deskripsi: 'Mengambil detail booking yang tidak ada.',
  langkah: '1. GET /api/bookings/99999',
  dataUji: 'id = 99999',
  ekspektasi: 'HTTP 404, message "Data booking tidak ditemukan"',
  method: 'GET', path: '/bookings/99999', expectStatus: 404 });

add({ id: 'TC-085', nama: 'Detail Pembayaran Tidak Ditemukan', skenario: 'CRUD Payment - Negatif',
  deskripsi: 'Mengambil detail pembayaran yang tidak ada.',
  langkah: '1. GET /api/payments/99999',
  dataUji: 'id = 99999',
  ekspektasi: 'HTTP 404, message "Data pembayaran tidak ditemukan"',
  method: 'GET', path: '/payments/99999', expectStatus: 404 });

add({ id: 'TC-092', nama: 'Create Log User Tidak Valid', skenario: 'CRUD Log - Negatif',
  deskripsi: 'user_id merujuk user yang tidak terdaftar.',
  langkah: '1. POST /api/system-logs 2. user_id = 99999',
  dataUji: '{"user_id":99999,"activity":"test"}',
  ekspektasi: 'HTTP 422, error validasi user_id terpilih tidak valid',
  method: 'POST', path: '/system-logs', body: { user_id: 99999, activity: 'test' }, expectStatus: 422 });

add({ id: 'TC-115', nama: 'Publik: Filter Unit Berdasarkan Tipe', skenario: 'API Publik - Filter',
  deskripsi: 'Parameter type memfilter unit berdasarkan unit_type.',
  langkah: '1. GET /api/public/units?type=Modern Tropical',
  dataUji: 'type = Modern Tropical',
  ekspektasi: 'HTTP 200, data hanya unit bertipe tersebut',
  method: 'GET', path: '/public/units?type=Modern%20Tropical', expectStatus: 200 });

add({ id: 'TC-140', nama: 'Token Hangus Setelah Logout', skenario: 'Autentikasi - Negatif',
  deskripsi: 'Token Sanctum yang sudah di-logout tidak boleh masih berlaku.',
  langkah: '1. Login manager 2. POST /api/logout 3. GET /api/me memakai token lama',
  dataUji: 'Bearer <token manager setelah logout>',
  ekspektasi: 'HTTP 401 Unauthenticated (token telah dicabut)',
  method: 'GET', path: '/me', afterLogout: true, expectStatus: 401 });

// ---- run ----
let token = null;
for (const tc of T) {
  if (tc.afterLogout) {
    const l = await login('manager@luminpark.com', 'password123');
    const t2 = l.json.access_token;
    await fetch(BASE + '/logout', { method: 'POST', headers: { Authorization: 'Bearer ' + t2, Accept: 'application/json' } });
    await call(tc, t2);
    continue;
  }
  const r = await call(tc, token);
  if (tc.id === 'TC-005' && r.status === 200) {
    const j = JSON.parse(r.body); token = j.access_token;
  }
}

writeFileSync(new URL('./api-test-results-extra.json', import.meta.url), JSON.stringify(results, null, 2));
console.log(`\nTotal tambahan: ${results.length}`);
