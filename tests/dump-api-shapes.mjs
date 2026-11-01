/**
 * Mengambil contoh respons setiap endpoint API supaya halaman frontend memuat
 * field yang benar-benar ada. Hasil: tests/api-shapes.json (skala satu baris per endpoint,
 * array dipotong ke 2 elemen).
 */
const BASE = 'http://127.0.0.1:8080/api';
const out = {};

async function login(email) {
  const r = await fetch(BASE + '/login', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: 'password123' }),
  });
  const j = await r.json();
  return j.access_token;
}

function trim(value) {
  if (Array.isArray(value)) return value.slice(0, 2).map(trim);
  if (value && typeof value === 'object') {
    const o = {};
    for (const [k, v] of Object.entries(value)) o[k] = trim(v);
    return o;
  }
  return value;
}

async function get(token, key, path) {
  const res = await fetch(BASE + path, {
    headers: { Accept: 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
  });
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { json = text.slice(0, 200); }
  out[key] = { status: res.status, shape: trim(json) };
}

async function main() {
  const manager = await login('manager@luminpark.com');
  const adminSales = await login('admin.sales@luminpark.com');
  const sales = await login('sales@luminpark.com');

  // Publik
  for (const [k, p] of [
    ['public.units', '/public/units'],
    ['public.payment_schemes', '/public/payment-schemes'],
    ['public.sales_contacts', '/public/sales-contacts'],
    ['public.news_gallery', '/public/news-gallery'],
    ['public.settings', '/public/settings'],
  ]) await get(null, k, p);

  const firstPublic = out['public.units']?.shape?.data?.[0]?.id ?? 1;
  await get(null, 'public.unit_detail', `/public/units/${firstPublic}`);

  // Internal: unit
  await get(manager, 'units.index', '/units');
  await get(manager, 'units.clusters', '/units/clusters');
  await get(sales, 'units.available', '/units-available');
  const firstUnit = out['units.index']?.shape?.[0]?.id ?? 1;
  await get(manager, 'units.show', `/units/${firstUnit}`);

  // Konsumen & follow up
  await get(manager, 'leads.index', '/customer-leads');
  const firstLead = out['leads.index']?.shape?.data?.[0]?.id ?? 1;
  await get(manager, 'leads.show', `/customer-leads/${firstLead}`);
  await get(manager, 'followups.index', `/follow-ups?lead_id=${firstLead}`);

  // Booking & pembayaran
  await get(manager, 'bookings.index', '/bookings');
  const firstBooking = out['bookings.index']?.shape?.data?.[0]?.id ?? 1;
  await get(manager, 'bookings.show', `/bookings/${firstBooking}`);
  await get(manager, 'payments.index', '/payments');
  await get(manager, 'schemes.index', '/payment-schemes');
  const firstScheme = out['schemes.index']?.shape?.data?.[0]?.id ?? 1;
  await get(sales, 'schemes.simulate', `/payment-schemes/${firstScheme}/simulate`);

  // Sales
  await get(adminSales, 'sales.index', '/sales');
  const firstSales = out['sales.index']?.shape?.data?.[0]?.id ?? 1;
  await get(adminSales, 'sales.show', `/sales/${firstSales}`);
  await get(adminSales, 'sales.performance', `/sales/${firstSales}/performance?start_date=2026-10-01&end_date=2026-10-31`);

  // Dashboard
  await get(manager, 'dashboard.manager', '/dashboard/manager');
  await get(adminSales, 'dashboard.admin_sales', '/dashboard/admin-sales');
  await get(sales, 'dashboard.sales', '/dashboard/sales');

  // Laporan
  const per = '?start_date=2026-01-01&end_date=2026-12-31';
  await get(manager, 'reports.sales', '/reports/sales' + per);
  await get(manager, 'reports.settlement', '/reports/settlement' + per);
  await get(manager, 'reports.performance', '/reports/performance' + per);
  await get(manager, 'reports.consumers', '/reports/consumers' + per);
  await get(sales, 'reports.my_sales', '/reports/my-sales' + per);

  // Pendukung
  await get(manager, 'settings.index', '/settings');
  await get(manager, 'profile.manager', '/profile');
  await get(sales, 'profile.sales', '/profile');
  await get(manager, 'notifications.index', '/notifications');
  await get(manager, 'notifications.unread', '/notifications/unread-count');
  await get(manager, 'sessions.index', '/sessions');
  await get(manager, 'system_logs.index', '/system-logs');
  await get(manager, 'auth.me', '/me');

  const { writeFileSync } = await import('node:fs');
  writeFileSync(new URL('./api-shapes.json', import.meta.url), JSON.stringify(out, null, 2));
  const bad = Object.entries(out).filter(([, v]) => v.status >= 400).map(([k, v]) => `${k}=${v.status}`);
  console.log(`sampled ${Object.keys(out).length} endpoints`);
  if (bad.length) console.log('non-2xx: ' + bad.join(', '));
}

main();
