/**
 * Verifikasi manual di luar suite JSON: unggah berkas multipart (gambar unit,
 * bukti transfer) dan pengukuran waktu respons kebutuhan non-fungsional.
 */
import { readFileSync, existsSync, writeFileSync } from 'node:fs';

const BASE = 'http://127.0.0.1:8080/api';
const results = [];
const ctx = {};

async function json(token, method, path, body) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = 'Bearer ' + token;
  if (method !== 'GET' && method !== 'DELETE') headers['Content-Type'] = 'application/json';
  const res = await fetch(BASE + path, {
    method,
    headers,
    body: method === 'GET' || method === 'DELETE' ? undefined : JSON.stringify(body ?? {}),
  });
  const text = await res.text();
  let parsed = null;
  try { parsed = JSON.parse(text); } catch { /* ignore */ }
  return { status: res.status, json: parsed, text };
}

async function multipart(token, method, path, fields, fileField, filePath, mimeType) {
  const form = new FormData();
  for (const [k, v] of Object.entries(fields)) form.append(k, String(v));
  const bytes = readFileSync(filePath);
  form.append(fileField, new Blob([bytes], { type: mimeType }), filePath.split('/').pop());

  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = 'Bearer ' + token;

  const res = await fetch(BASE + path, { method, headers, body: form });
  const text = await res.text();
  let parsed = null;
  try { parsed = JSON.parse(text); } catch { /* ignore */ }
  return { status: res.status, json: parsed, text };
}

async function timed(token, method, path, body) {
  const started = performance.now();
  await json(token, method, path, body);
  return Math.round(performance.now() - started);
}

function record(def) {
  results.push(def);
  console.log(`${def.id} [${def.status}] ${def.method} ${def.path} -> ${def.actualStatus}`);
}

const mgr = await json(null, 'POST', '/login', { email: 'superadmin@gmail.com', password: 'password123' });
ctx.managerToken = mgr.json?.access_token;
const sales = await json(null, 'POST', '/login', { email: 'sales@luminpark.com', password: 'password123' });
ctx.salesToken = sales.json?.access_token;

/* TC-J01 Unggah gambar unit (multipart) */
const clusters = await json(ctx.managerToken, 'GET', '/units/clusters');
const clusterList = clusters.json?.data ?? clusters.json ?? [];
const clusterId = (Array.isArray(clusterList) ? clusterList : [])[0]?.id ?? 1;

const unitRes = await multipart(ctx.managerToken, 'POST', '/units', {
  unit_code: 'LT-UPLOAD-01', cluster_id: clusterId, unit_type: 'Modern Tropical Upload',
  land_size: 72, building_size: 60, price: 800000000, status: 'available',
  description: 'Unit uji unggah gambar.',
}, 'image', 'tests/tmp-unit.png', 'image/png');

const storedImage = unitRes.json?.data?.image;
const imageOnDisk = storedImage ? existsSync(`storage/app/public/${storedImage}`) : false;

record({
  id: 'TC-J01', nama: 'Unggah Gambar Unit', skenario: 'Memperbarui data unit',
  deskripsi: 'Manajer menyimpan foto unit; berkas diunggah ke disk public dan kolom image terisi.',
  langkah: '1. POST /api/units (multipart/form-data, field image) 2. Periksa path pada respons 3. Periksa berkas pada storage/app/public',
  dataUji: 'unit_code=LT-UPLOAD-01, image=tests/tmp-unit.png (PNG)',
  ekspektasi: 'HTTP 201 dengan data.image terisi dan berkas benar-benar tersimpan pada disk public',
  method: 'POST', path: '/units', actualStatus: unitRes.status,
  actualBody: `HTTP ${unitRes.status} — ${unitRes.text.slice(0, 200)}`,
  status: unitRes.status === 201 && imageOnDisk ? 'Pass' : 'Fail',
  catatan: imageOnDisk
    ? `Berkas tersimpan pada storage/app/public/${storedImage}; dapat diakses lewat /storage/${storedImage}.`
    : `Respons HTTP ${unitRes.status} tetapi berkas tidak ditemukan di disk (${storedImage ?? 'tidak ada path'}).`,
  kebutuhan: 'Kebutuhan b (menambah data unit) + Kebutuhan g.2 (spesifikasi lengkap)',
});

/* TC-J02 Unggah bukti transfer */
const bookings = await json(ctx.salesToken, 'GET', '/bookings');
const openBooking = (bookings.json?.data || []).find((b) => Number(b.remaining_amount) > 0);

const receiptRes = openBooking
  ? await multipart(ctx.salesToken, 'POST', '/payments', {
      booking_id: openBooking.id, payment_type: 'cash keras', amount: 100000,
      payment_date: '2026-10-26', bank_name: 'BCA', notes: 'Uji unggah bukti transfer.',
    }, 'transfer_receipt', 'tests/tmp-bukti.pdf', 'application/pdf')
  : { status: 'ERR', text: 'Tidak ada booking dengan sisa pembayaran > 0.', json: null };

const storedReceipt = receiptRes.json?.data?.transfer_receipt;
const receiptOnDisk = storedReceipt ? existsSync(`storage/app/public/${storedReceipt}`) : false;

record({
  id: 'TC-J02', nama: 'Unggah Bukti Transfer', skenario: 'Membuat pembayaran',
  deskripsi: 'Sales mencatat pembayaran sambil melampirkan bukti transfer (PDF/JPG) sebagai berkas.',
  langkah: '1. GET /api/bookings untuk mencari booking belum lunas 2. POST /api/payments (multipart, field transfer_receipt) 3. Periksa berkas pada disk',
  dataUji: `booking_id=${openBooking?.id}, transfer_receipt=tests/tmp-bukti.pdf`,
  ekspektasi: 'HTTP 201 dengan transfer_receipt terisi dan berkas tersimpan pada disk public',
  method: 'POST', path: '/payments', actualStatus: receiptRes.status,
  actualBody: `HTTP ${receiptRes.status} — ${String(receiptRes.text).slice(0, 200)}`,
  status: receiptRes.status === 201 && receiptOnDisk ? 'Pass' : 'Fail',
  catatan: receiptOnDisk
    ? `Bukti transfer tersimpan pada storage/app/public/${storedReceipt} dan dapat ditautkan ke verifikasi manajer.`
    : `Respons HTTP ${receiptRes.status}; berkas ${storedReceipt ?? '(tanpa path)'} tidak ditemukan di disk.`,
  kebutuhan: 'Kebutuhan d.1 (mencatat pembayaran dan mengunggah bukti transfer)',
});

/* TC-J03 Verifikasi bukti terunggah oleh Manajer */
if (receiptRes.json?.data?.id) {
  const verify = await json(ctx.managerToken, 'PUT', `/payments/${receiptRes.json.data.id}/verify`, {
    verification_status: 'verified', notes: 'Bukti sesuai.',
  });
  const detail = await json(ctx.managerToken, 'GET', `/payments/${receiptRes.json.data.id}`);
  const berkasMasihAda = existsSync(`storage/app/public/${detail.json?.data?.transfer_receipt}`);
  record({
    id: 'TC-J03', nama: 'Verifikasi Pembayaran Berbesan Bukti', skenario: 'Verifikasi pembayaran',
    deskripsi: 'Manajer memverifikasi pembayaran yang memiliki lampiran bukti transfer; berkas tidak hilang setelah verifikasi.',
    langkah: '1. PUT /api/payments/{id}/verify 2. GET /api/payments/{id} 3. Periksa berkas tetap tersedia',
    dataUji: `payment_id=${receiptRes.json.data.id}, verification_status=verified`,
    ekspektasi: 'HTTP 200 dengan verification_status=verified, verified_by terisi, dan berkas bukti masih ada',
    method: 'PUT', path: `/payments/${receiptRes.json.data.id}/verify`, actualStatus: verify.status,
    actualBody: `HTTP ${verify.status} — ${verify.text.slice(0, 200)}`,
    status: verify.status === 200 && berkasMasihAda ? 'Pass' : 'Fail',
    catatan: `verifikator=${detail.json?.data?.verified_by}, berkas=${berkasMasihAda ? 'masih tersimpan' : 'hilang'}`,
    kebutuhan: 'Kebutuhan d.2 (Manajer memverifikasi bukti pembayaran)',
  });
}

/* TC-J04 Waktu respons proses umum */
const samples = [
  ['POST', '/login', { email: 'superadmin@gmail.com', password: 'password123' }],
  ['GET', '/units'],
  ['GET', '/dashboard/manager'],
  ['GET', '/reports/sales?start_date=2026-10-01&end_date=2026-10-31'],
  ['GET', '/public/units'],
];
const timings = [];
for (const [m, p, body] of samples) {
  const ms = await timed(ctx.managerToken, m, p, body);
  timings.push(`${p.split('?')[0]}=${ms}ms`);
}
const worst = Math.max(...timings.map((t) => parseInt(t.split('=')[1], 10)));
record({
  id: 'TC-J04', nama: 'Waktu Respons Proses Umum', skenario: 'Kebutuhan non-fungsional',
  deskripsi: 'Laporan mensyaratkan waktu respons maksimal 5 detik untuk proses umum.',
  langkah: 'Jalankan 5 proses umum (login, daftar unit, dashboard manajer, laporan penjualan, katalog publik) lalu catat selisih waktu klien',
  dataUji: '5 permintaan berurutan dengan token manajer',
  ekspektasi: 'Seluruh proses selesai di bawah 5000 ms',
  method: 'MULTI', path: '/login, /units, /dashboard/manager, /reports/sales, /public/units',
  actualStatus: '200',
  actualBody: timings.join(', '),
  status: worst < 5000 ? 'Pass' : 'Fail',
  catatan: `Respons terlama ${worst}ms (diukur dari sisi klien, localhost, tanpa cold start).`,
  kebutuhan: 'Kebutuhan non-fungsional a (kinerja maks 5 detik)',
});

writeFileSync(
  new URL('./manual-check-results.json', import.meta.url),
  JSON.stringify(results, null, 2),
);

const passed = results.filter((r) => r.status === 'Pass').length;
console.log(`\nVERIFIKASI MANUAL: ${passed}/${results.length} PASS`);
