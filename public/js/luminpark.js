/*
 * Klien API + komponen bersama untuk frontend Lumin Park.
 * Autentikasi memakai token Sanctum di localStorage; seluruh halaman internal
 * memanggil LP.guard() sebelum meminta data.
 */
window.LP = (() => {
  const BASE = '/api';
  const TOKEN_KEY = 'luminpark_token';
  const MONTHS_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  const token = {
    get: () => localStorage.getItem(TOKEN_KEY),
    set: (value) => localStorage.setItem(TOKEN_KEY, value),
    clear: () => localStorage.removeItem(TOKEN_KEY),
  };

  class ApiError extends Error {
    constructor(status, message, payload) {
      super(message || `Request gagal (HTTP ${status})`);
      this.status = status;
      this.payload = payload;
      this.errors = (payload && payload.errors) || null;
    }
  }

  async function request(method, path, body, options = {}) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    const current = token.get();
    if (current && !options.anonymous) headers.Authorization = 'Bearer ' + current;

    let payload = body;
    if (payload && !(payload instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(payload);
    }

    const res = await fetch(BASE + path, { method, headers, body: payload });
    const text = await res.text();
    let json = null;
    try { json = JSON.parse(text); } catch { /* CSV / HTML */ }

    if (res.status === 401 && !options.anonymous && !options.allowUnauthorized) {
      token.clear();
      if (!options.quiet) location.replace('/login');
      throw new ApiError(401, (json && json.message) || 'Sesi berakhir, silakan masuk kembali.', json);
    }
    if (res.status === 403 && json && json.code === 'password_change_required' && location.pathname !== '/ganti-sandi') {
      location.replace('/ganti-sandi');
    }
    if (!res.ok) {
      throw new ApiError(res.status, (json && json.message) || text.slice(0, 160) || 'Terjadi kesalahan.', json);
    }
    return { json, text, headers: res.headers };
  }

  const http = {
    get: (path, options) => request('GET', path, null, options),
    post: (path, body, options) => request('POST', path, body, options),
    put: (path, body, options) => request('PUT', path, body, options),
    patch: (path, body, options) => request('PATCH', path, body, options),
    del: (path, options) => request('DELETE', path, null, options),
  };

  // Respons API ada yang berupa array polos dan ada yang dibungkus {success, data}.
  function unwrap(json) {
    if (json == null) return null;
    if (Array.isArray(json)) return json;
    if (Object.prototype.hasOwnProperty.call(json, 'data')) return json.data;
    return json;
  }

  async function fetchJson(path, options) {
    const { json } = await http.get(path, options);
    return unwrap(json);
  }

  let meCache = null;
  async function me(force = false) {
    if (meCache && !force) return meCache;
    meCache = unwrap(await request('GET', '/me').then((r) => r.json));
    return meCache;
  }

  async function login(email, password) {
    const { json } = await request('POST', '/login', { email, password }, { anonymous: true });
    if (!json || !json.access_token) throw new ApiError(401, 'Login gagal. Periksa nama pengguna dan kata sandi.', json);
    token.set(json.access_token);
    meCache = json.user || null;
    return meCache || me(true);
  }

  async function logout(redirectTo = '/login') {
    try { await http.post('/logout', {}); } catch { /* token sudah tidak valid */ }
    token.clear();
    meCache = null;
    location.replace(redirectTo);
  }

  // Guard halaman internal: wajib token, peran harus terdaftar.
  let currentUser = null;

  async function guard(roles, onReady) {
    const shell = document.getElementById('app-shell');
    if (!token.get()) { location.replace('/login'); return; }

    let user;
    try {
      user = await me();
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) return;
      renderProblem(shell, 'Data akun gagal dimuat.', e.message);
      return;
    }

    if (user.must_change_password && location.pathname !== '/ganti-sandi') {
      location.replace('/ganti-sandi');
      return;
    }
    if (roles && roles.length && !roles.includes(user.role)) {
      renderDenied(shell, user.role);
      return;
    }

    currentUser = user;
    paintIdentity(user);
    buildShell(user);
    loadUnreadBadge();
    mulaiPenghitungSesi();

    if (onReady) {
      try {
        await onReady(user);
      } catch (e) {
        // Kegagalan muat data bukan penolakan peran: biarkan halaman menampilkan errornya sendiri.
        toast(e.message || 'Konten halaman gagal dimuat.', 'error');
      }
    }
  }

  function paintIdentity(user) {
    const name = document.getElementById('who-name');
    const role = document.getElementById('who-role');
    const avatar = document.getElementById('who-avatar');
    const initial = document.getElementById('who-initial');
    if (name) name.textContent = user.name || '-';
    if (role) role.textContent = ROLE_LABEL[user.role] || user.role;
    if (avatar && user.avatar_url) {
      avatar.src = user.avatar_url;
      avatar.classList.remove('hidden');
      if (initial) initial.classList.add('hidden');
    } else if (initial) {
      initial.textContent = (user.name || '?').trim().charAt(0).toUpperCase();
    }
  }

  function renderDenied(shell, role, message) {
    if (!shell) return;
    const peranPunyaDashboard = NAV.some((item) => item.roles.includes(role));
    const tautan = peranPunyaDashboard ? '/dashboard' : '/';
    const label = peranPunyaDashboard ? 'Kembali ke Dashboard' : 'Kembali ke Halaman Publik';
    shell.innerHTML = `
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center">
        <h2 class="text-lg font-semibold text-amber-900">Halaman belum tersedia untuk peran Anda</h2>
        <p class="mt-2 text-sm text-amber-800">${esc(message || 'Peran ' + (ROLE_LABEL[role] || role) + ' tidak memiliki hak akses ke modul ini.')}</p>
        <a href="${tautan}" class="mt-4 inline-flex rounded-xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white">${esc(label)}</a>
      </div>`;
  }

  function renderProblem(shell, judul, pesan) {
    if (!shell) return;
    shell.innerHTML = `
      <div class="rounded-2xl border border-rose-200 bg-rose-50 p-8 text-center">
        <h2 class="text-lg font-semibold text-rose-900">${esc(judul)}</h2>
        <p class="mt-2 text-sm text-rose-800">${esc(pesan || 'Periksa koneksi lalu coba muat ulang.')}</p>
        <button type="button" id="lp-retry" class="mt-4 inline-flex rounded-xl bg-zinc-900 px-4 py-2 text-sm font-medium text-white">Muat Ulang Halaman</button>
      </div>`;
    const retry = document.getElementById('lp-retry');
    if (retry) retry.onclick = () => location.reload();
  }

  /* ---------- format ---------- */

  const number = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(roundValue(value));
  const roundValue = (value) => {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? Math.round(n) : 0;
  };
  const money = (value) => 'Rp ' + number(value);
  function shortMoney(value) {
    const n = Number(value ?? 0);
    if (Math.abs(n) >= 1e9) return 'Rp ' + (n / 1e9).toFixed(n % 1e9 === 0 ? 0 : 2).replace('.', ',') + ' M';
    if (Math.abs(n) >= 1e6) return 'Rp ' + (n / 1e6).toFixed(n % 1e6 === 0 ? 0 : 1).replace('.', ',') + ' jt';
    return money(n);
  }
  function parseDate(value) {
    if (!value) return null;
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
  }
  function date(value) {
    const d = parseDate(value);
    return d ? `${d.getDate()} ${MONTHS_ID[d.getMonth()]} ${d.getFullYear()}` : '-';
  }
  function datetime(value) {
    const d = parseDate(value);
    if (!d) return '-';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date(value)}, ${pad(d.getHours())}.${pad(d.getMinutes())}`;
  }
  function monthLabel(yyyymm) {
    const [year, month] = String(yyyymm || '').split('-');
    return `${MONTHS_ID[Number(month) - 1] || month} ${year}`;
  }
  // Nilai tanggal untuk <input type="date"> dan kolom DATE API.
  const isoDate = (value) => (value ? new Date(value).toISOString().slice(0, 10) : '');
  const today = () => isoDate(new Date());
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));

  /* ---------- vocabulary ---------- */

  const ROLE_LABEL = {
    super_admin: 'Super Admin', admin_sales: 'Monitoring Manajemen', sales: 'Sales', consumer: 'Konsumen',
  };

  const STATUS = {
    available: ['Tersedia', 'emerald'],
    booking: ['Booking', 'amber'],
    sold_out: ['Terjual', 'zinc'],
    delivery_process: ['Proses Serah Terima', 'sky'],
    'menunggu dp': ['Menunggu DP', 'amber'],
    'dp lunas': ['DP Lunas', 'sky'],
    cicilan: ['Cicilan Berjalan', 'violet'],
    lunas: ['Lunas', 'emerald'],
    'dibatalkan': ['Dibatalkan', 'rose'],
    prospek: ['Prospek', 'sky'],
    konsumen: ['Konsumen', 'emerald'],
    cold: ['Cold', 'sky'],
    warm: ['Warm', 'amber'],
    hot: ['Hot', 'rose'],
    pending: ['Menunggu Verifikasi', 'amber'],
    verified: ['Terverifikasi', 'emerald'],
    rejected: ['Ditolak', 'rose'],
    aktif: ['Aktif', 'emerald'],
    nonaktif: ['Nonaktif', 'zinc'],
    'belum proses': ['Belum Proses', 'zinc'],
    'sedang proses': ['Sedang Proses', 'sky'],
    selesai: ['Selesai', 'emerald'],
    Terjual: ['Terjual', 'emerald'],
    Pemesanan: ['Pemesanan', 'amber'],
    'Calon Pembeli': ['Calon Pembeli', 'sky'],
  };

  function badge(value) {
    const [label, tone] = STATUS[value] || [value || '-', 'zinc'];
    const tones = {
      emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
      amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
      rose: 'bg-rose-50 text-rose-700 ring-rose-600/20',
      sky: 'bg-sky-50 text-sky-700 ring-sky-600/20',
      violet: 'bg-violet-50 text-violet-700 ring-violet-600/20',
      zinc: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
    };
    return `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${tones[tone]}">${esc(label)}</span>`;
  }

  const roleLabel = (role) => ROLE_LABEL[role] || role;

  // Revisi 1: foto sales pada daftar narahubung halaman publik. Tanpa foto, halaman
  // tetap menampilkan ikon atau inisial yang sudah ada.
  const fotoSales = (url, nama, classes = 'h-11 w-11') => '<img src="' + esc(url || '')
    + '" alt="Foto ' + esc(nama || 'Sales') + '" loading="lazy" class="' + classes + ' shrink-0 rounded-full object-cover">';

  // Aturan kata sandi pada alur ganti sandi mandiri (revisi 8).
  const SANDI_POLA = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
  const sandiKuat = (value) => SANDI_POLA.test(String(value || ''));
  const PESAN_SANDI = 'Kata sandi minimal 8 karakter serta memuat huruf besar, huruf kecil, dan angka.';

  /* ---------- toast ---------- */

  function toast(message, type = 'success') {
    let host = document.getElementById('lp-toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'lp-toast-host';
      host.className = 'fixed bottom-6 right-6 z-[70] flex w-80 flex-col gap-2';
      document.body.appendChild(host);
    }
    const tone = type === 'error'
      ? 'border-rose-200 bg-rose-50 text-rose-800'
      : type === 'info' ? 'border-sky-200 bg-sky-50 text-sky-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800';
    const box = document.createElement('div');
    box.className = `rounded-xl border px-4 py-3 text-sm shadow-lg ${tone}`;
    box.textContent = message;
    host.appendChild(box);
    setTimeout(() => box.remove(), 4200);
  }

  /* ---------- dialog & form ---------- */

  function overlay(inner) {
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-zinc-900/40 p-4 pt-16';
    wrap.innerHTML = inner;
    document.body.appendChild(wrap);
    const close = () => wrap.remove();
    wrap.addEventListener('click', (e) => { if (e.target === wrap) close(); });
    return { wrap, close };
  }

  function confirm(message, { title = 'Konfirmasi', okLabel = 'Ya, Lanjutkan', danger = false } = {}) {
    return new Promise((resolve) => {
      const { wrap, close } = overlay(`
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
          <h3 class="text-base font-semibold text-zinc-900">${esc(title)}</h3>
          <p class="mt-2 text-sm text-zinc-600">${esc(message)}</p>
          <div class="mt-6 flex justify-end gap-2">
            <button data-no class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700">Batal</button>
            <button data-yes class="rounded-xl px-4 py-2 text-sm font-medium text-white ${danger ? 'bg-rose-600' : 'bg-zinc-900'}">${esc(okLabel)}</button>
          </div>
        </div>`);
      wrap.querySelector('[data-yes]').onclick = () => { close(); resolve(true); };
      wrap.querySelector('[data-no]').onclick = () => { close(); resolve(false); };
    });
  }

  function fieldHtml(field, value = '') {
    const id = `f-${field.name.replace(/[[\]]/g, '-')}`;
    const req = field.required ? 'required' : '';
    const cls = 'w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10';
    let control;
    if (field.type === 'select') {
      const opts = (field.options || []).map((o) => {
        const v = typeof o === 'string' ? o : o.value;
        const l = typeof o === 'string' ? (STATUS[v] ? STATUS[v][0] : v) : o.label;
        return `<option value="${esc(v)}" ${String(v) === String(value) ? 'selected' : ''}>${esc(l)}</option>`;
      }).join('');
      control = `<select id="${id}" name="${esc(field.name)}" class="${cls}" ${req}>${field.placeholder ? `<option value="">${esc(field.placeholder)}</option>` : ''}${opts}</select>`;
    } else if (field.type === 'textarea') {
      control = `<textarea id="${id}" name="${esc(field.name)}" rows="${field.rows || 3}" class="${cls}" ${req} placeholder="${esc(field.placeholder || '')}">${esc(value)}</textarea>`;
    } else {
      const type = field.type || 'text';
      control = `<input id="${id}" name="${esc(field.name)}" type="${type}" class="${cls}" ${req}
        ${field.min !== undefined ? `min="${field.min}"` : ''} ${field.max !== undefined ? `max="${field.max}"` : ''}
        ${field.step !== undefined ? `step="${field.step}"` : ''} ${field.accept ? `accept="${esc(field.accept)}"` : ''}
        value="${type === 'file' ? '' : esc(value)}" placeholder="${esc(field.placeholder || '')}">`;
    }
    const span = field.col === 2 ? 'sm:col-span-2' : '';
    return `<label class="block ${span}">
      <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">${esc(field.label)}${field.required ? ' *' : ''}</span>
      ${control}
      ${field.hint ? `<span class="mt-1 block text-xs text-zinc-500">${esc(field.hint)}</span>` : ''}
      <span data-error="${esc(field.name)}" class="mt-1 hidden text-xs font-medium text-rose-600"></span>
    </label>`;
  }

  function formModal({ title, description, fields = [], values = {}, submitLabel = 'Simpan', width = 'max-w-2xl', onSubmit }) {
    const grid = fields.map((f) => fieldHtml(f, values[f.name] ?? f.value ?? '')).join('');
    const { wrap, close } = overlay(`
      <div class="w-full ${width} rounded-2xl bg-white shadow-xl">
        <form class="p-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-zinc-900">${esc(title)}</h3>
              ${description ? `<p class="mt-1 text-sm text-zinc-500">${esc(description)}</p>` : ''}
            </div>
            <button type="button" data-x class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700" aria-label="Tutup">&#10005;</button>
          </div>
          <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">${grid}</div>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" data-cancel class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700">Batal</button>
            <button type="submit" class="rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">${esc(submitLabel)}</button>
          </div>
        </form>
      </div>`);

    const form = wrap.querySelector('form');
    const submit = form.querySelector('[type=submit]');
    wrap.querySelector('[data-x]').onclick = close;
    wrap.querySelector('[data-cancel]').onclick = close;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = new FormData(form);
      const plain = Object.fromEntries(data.entries());
      // File tetap perlu diakses sebagai objek FormData saat submit.
      plain.__files = Object.fromEntries([...data.entries()].filter(([, v]) => v instanceof File));
      submit.disabled = true;
      submit.textContent = 'Menyimpan...';
      form.querySelectorAll('[data-error]').forEach((n) => { n.classList.add('hidden'); n.textContent = ''; });
      try {
        await onSubmit(plain, { close, form });
      } catch (e) {
        submit.disabled = false;
        submit.textContent = submitLabel;
        if (e instanceof ApiError && e.errors) {
          for (const [key, messages] of Object.entries(e.errors)) {
            const slot = form.querySelector(`[data-error="${key}"]`);
            if (slot) { slot.textContent = messages.join(', '); slot.classList.remove('hidden'); }
          }
          toast(firstErrorText(e), 'error');
        } else {
          toast(e.message || 'Gagal menyimpan data.', 'error');
        }
        return;
      }
      close();
    });
    form.querySelector('input,select,textarea')?.focus();
    return { wrap, close };
  }

  const firstErrorText = (e) => Object.values(e.errors || {}).flat().join('. ') || e.message;

  /* ---------- tabel ---------- */

  function table({ mount, columns, rows, empty = 'Belum ada data.', onRow }) {
    const host = typeof mount === 'string' ? document.getElementById(mount) : mount;
    if (!host) return;
    const head = columns.map((c) => `<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 ${c.align === 'right' ? 'text-right' : ''}">${esc(c.label)}</th>`).join('');
    const body = (rows || []).length
      ? rows.map((row, i) => {
          const cells = columns.map((c) => {
            const value = c.render ? c.render(row, i) : esc(row[c.key] ?? '-');
            return `<td class="px-4 py-3 text-sm text-zinc-700 ${c.align === 'right' ? 'text-right tabular-nums' : ''}">${value}</td>`;
          }).join('');
          return `<tr data-index="${i}" class="border-t border-zinc-100 hover:bg-zinc-50 ${onRow ? 'cursor-pointer' : ''}">${cells}</tr>`;
        }).join('')
      : `<tr><td colspan="${columns.length}" class="px-4 py-10 text-center text-sm text-zinc-500">${esc(empty)}</td></tr>`;

    host.innerHTML = `<div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-zinc-100"><thead class="bg-zinc-50"><tr>${head}</tr></thead><tbody>${body}</tbody></table>
    </div>`;

    if (onRow) {
      host.querySelectorAll('tr[data-index]').forEach((tr) => {
        tr.addEventListener('click', (event) => {
          if (event.target.closest('[data-stop]')) return;
          onRow(rows[Number(tr.dataset.index)], Number(tr.dataset.index));
        });
      });
    }
  }

  /* ---------- kartu, loader, grafik ---------- */

  function statCard({ label, value, hint, tone = 'zinc' }) {
    const tones = {
      zinc: 'from-zinc-50 text-zinc-900', emerald: 'from-emerald-50 text-emerald-900',
      amber: 'from-amber-50 text-amber-900', sky: 'from-sky-50 text-sky-900', violet: 'from-violet-50 text-violet-900',
    };
    return `<div class="rounded-2xl border border-zinc-200 bg-gradient-to-br ${tones[tone] || tones.zinc} to-white p-5 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">${esc(label)}</p>
      <p class="mt-2 text-2xl font-bold tabular-nums">${esc(value)}</p>
      ${hint ? `<p class="mt-1 text-xs text-zinc-500">${esc(hint)}</p>` : ''}
    </div>`;
  }

  function loading(mount, text = 'Memuat data...') {
    const host = typeof mount === 'string' ? document.getElementById(mount) : mount;
    if (host) host.innerHTML = `<div class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-8 text-sm text-zinc-500">
      <span class="h-5 w-5 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-800"></span>${esc(text)}</div>`;
  }

  function errorBox(mount, message) {
    const host = typeof mount === 'string' ? document.getElementById(mount) : mount;
    if (host) host.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">${esc(message)}</div>`;
  }

  // Bar chart SVG murni: tidak butuh library eksternal.
  function barChart({ mount, rows, valueKey = 'revenue', labelKey = 'label', format = shortMoney, height = 220 }) {
    const host = typeof mount === 'string' ? document.getElementById(mount) : mount;
    if (!host) return;
    const data = rows || [];
    const max = Math.max(1, ...data.map((r) => Number(r[valueKey] || 0)));
    const bar = 100 / Math.max(1, data.length);
    host.innerHTML = `
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-end gap-3" style="height:${height}px">
          ${data.map((r) => {
            const v = Number(r[valueKey] || 0);
            const pct = (v / max) * 100;
            return `<div class="flex h-full flex-1 flex-col justify-end gap-2" style="max-width:${bar * 3}%">
              <span class="text-center text-[11px] font-medium text-zinc-500">${esc(format(v))}</span>
              <div class="w-full rounded-t-lg bg-gradient-to-t from-emerald-600 to-emerald-400" style="height:${Math.max(pct, 2)}%" title="${esc(r[labelKey])}: ${esc(format(v))}"></div>
              <span class="text-center text-[11px] text-zinc-500">${esc(r[labelKey] ?? '')}</span>
            </div>`;
          }).join('')}
        </div>
      </div>`;
  }

  // Unduh CSV laporan: butuh header Authorization sehingga tidak bisa pakai <a href>.
  async function download(path, filename) {
    const res = await fetch(BASE + path, { headers: { Authorization: 'Bearer ' + token.get() } });
    if (!res.ok) throw new ApiError(res.status, 'Berkas laporan gagal diunduh.');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'laporan.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  // Mengisi elemen [data-setting="kunci"] dengan nilai pengaturan publik.
  let nilaiPengaturan = null;

  async function fillSettings(scope = document, muatUlang = false) {
    if (!nilaiPengaturan || muatUlang) {
      nilaiPengaturan = fetchJson('/public/settings', { anonymous: true, quiet: true })
        .catch((error) => { nilaiPengaturan = null; throw error; });
    }
    const values = await nilaiPengaturan;
    scope.querySelectorAll('[data-setting]').forEach((node) => {
      const value = values?.[node.dataset.setting];
      if (value === undefined || value === null || value === '') return;
      if (node.tagName === 'A') {
        const mode = node.dataset.link;
        node.textContent = mode === 'wa' || mode === 'phone' ? value : node.textContent || value;
        node.href = mode === 'mailto' ? 'mailto:' + value
          : mode === 'tel' ? 'tel:' + String(value).replace(/[^\d+]/g, '')
          : mode === 'wa' ? 'https://wa.me/' + String(value).replace(/[^\d+]/g, '')
          : node.href;
      } else {
        node.textContent = value;
      }
    });
    return values;
  }

  /* ---------- lembar cetak laporan ---------- */

  function blokCetak(id, html) {
    let node = document.getElementById(id);
    const shell = document.getElementById('app-shell');
    if (!node) {
      node = document.createElement('div');
      node.id = id;
      node.className = 'lp-print-only';
      if (id === 'lp-print-kop') shell.insertBefore(node, shell.firstChild);
      else shell.appendChild(node);
    }
    node.innerHTML = html;
  }

  /**
   * Menyiapkan kop dan blok pengesahan lalu membuka dialog cetak peramban.
   * "Simpan sebagai PDF" pada dialog tersebut menghasilkan berkas PDF-nya.
   */
  async function cetakLaporan({ judul, periode, catatan }) {
    const identitas = await fillSettings().catch(() => ({}));
    const pembuat = currentUser
      ? currentUser.name + ' — ' + (ROLE_LABEL[currentUser.role] || currentUser.role)
      : 'Pengguna tidak dikenal';

    blokCetak('lp-print-kop', `
      <div class="mb-4 border-b-2 border-zinc-800 pb-3">
        <p class="text-base font-bold uppercase tracking-wide text-zinc-900">${esc(identitas.estate_name || 'Luminpark')}</p>
        ${identitas.estate_address ? `<p class="text-[11px] text-zinc-700">${esc(identitas.estate_address)}</p>` : ''}
        <p class="text-[11px] text-zinc-700">${esc([identitas.estate_phone, identitas.estate_email].filter(Boolean).join('  ·  '))}</p>
      </div>
      <div class="mb-4 text-center">
        <p class="text-sm font-bold uppercase tracking-wide text-zinc-900">${esc(judul)}</p>
        <p class="text-[11px] text-zinc-700">Periode: ${esc(periode || '-')} &nbsp;·&nbsp; Dicetak: ${esc(datetime(Date.now()))}</p>
        ${catatan ? `<p class="text-[11px] text-zinc-700">${esc(catatan)}</p>` : ''}
      </div>`);

    blokCetak('lp-print-sign', `
      <div class="mt-10 text-[12px] text-zinc-900">
        <p class="mb-1">Dokumen ini dihasilkan sistem dan sah setelah ditandatangani pada kolom berikut.</p>
        <table class="mt-6 w-full table-fixed">
          <tbody>
            <tr>
              <td class="w-1/2 pr-6 text-center">
                <p>Dibuat oleh,</p>
                <p class="pt-1">${esc(currentUser ? ROLE_LABEL[currentUser.role] || currentUser.role : '')}</p>
                <div class="h-20"></div>
                <p class="border-t border-zinc-800 pt-1 font-semibold">${esc(currentUser ? currentUser.name : '.................................')}</p>
              </td>
              <td class="w-1/2 pl-6 text-center">
                <p>Mengetahui,</p>
                <p class="pt-1">Pimpinan / Atasan langsung</p>
                <div class="h-20"></div>
                <p class="border-t border-zinc-800 pt-1 font-semibold">.................................</p>
              </td>
            </tr>
          </tbody>
        </table>
        <p class="mt-6 text-[11px] text-zinc-700">${esc(identitas.estate_name || 'Luminpark')}, ${esc(date(Date.now()))}. Tanda tangan digital dapat ditempelkan pada kolom di atas sebelum dokumen diedarkan.</p>
      </div>`);

    window.print();
  }

  const debounce = (fn, wait = 250) => {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
  };

  async function withBusy(node, task) {
    if (node) { node.disabled = true; node.dataset.label = node.textContent; node.textContent = 'Memproses...'; }
    try { await task(); } finally {
      if (node) { node.disabled = false; node.textContent = node.dataset.label; }
    }
  }

  /* ---------- shell internal ---------- */

  const NAV = [
    { href: '/dashboard', label: 'Dashboard', icon: 'M3 12l9-8 9 8M5 10v10h14V10', roles: ['super_admin', 'admin_sales', 'sales'] },
    { href: '/data-unit', label: 'Data Unit Perumahan', icon: 'M4 20h16M6 20V8l6-4 6 4v12M9 20v-6h6v6', roles: ['super_admin', 'admin_sales'] },
    { href: '/data-proyek', label: 'Data Proyek', icon: 'M4 20h16M6 20v-7h5v7M13 20v-11h5v11', roles: ['super_admin', 'admin_sales'] },
    { href: '/data-sales', label: 'Data Sales', icon: 'M16 11a4 4 0 10-8 0M4 20a8 8 0 0116 0', roles: ['super_admin', 'admin_sales'] },
    { href: '/data-konsumen', label: 'Data Konsumen', icon: 'M12 12a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 0114 0', roles: ['super_admin', 'admin_sales'] },
    // Revisi 7: bagi sales, konsumen (sudah memesan) dipisah dari database prospek.
    { href: '/data-konsumen?kategori=konsumen', label: 'Konsumen', icon: 'M12 12a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 0114 0', roles: ['sales'] },
    { href: '/data-konsumen?kategori=prospek', label: 'Database Konsumen', icon: 'M5 13a7 7 0 0114 0M12 3v4M4 20h16', roles: ['sales'] },
    { href: '/booking', label: 'Booking Unit', icon: 'M8 3v4M16 3v4M3 9h18M5 5h14v16H5z', roles: ['super_admin', 'admin_sales', 'sales'] },
    { href: '/pembayaran', label: 'Data Pembayaran', icon: 'M3 10h18M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z', roles: ['super_admin', 'admin_sales', 'sales'] },
    { href: '/laporan', label: 'Laporan', icon: 'M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm2 6h6M9 13h6M9 17h4', roles: ['super_admin', 'admin_sales'] },
    { href: '/laporan-sales', label: 'Laporan Sales', icon: 'M9 17V7l4 5 4-5v10M5 21h14V3H5z', roles: ['sales'] },
    // Revisi 30: Log Aktivitas tidak lagi dibuka Monitoring Manajemen, melainkan Super Admin.
    { href: '/log-aktivitas', label: 'Log Aktivitas', icon: 'M4 6h16M4 12h16M4 18h10', roles: ['super_admin'] },
  ];

  // Penanda menu aktif; tautan konsumen sales dibedakan lewat parameter ?kategori=.
  function navAktif(item) {
    const [path, query = ''] = item.href.split('?');
    if (location.pathname !== path && !location.pathname.startsWith(path + '/')) return false;
    const kategoriItem = new URLSearchParams(query).get('kategori');
    if (!kategoriItem) return true;
    const kategoriHalaman = new URLSearchParams(location.search).get('kategori') || 'konsumen';
    return kategoriItem === kategoriHalaman;
  }

  function buildShell(user) {
    const nav = document.getElementById('lp-nav');
    if (nav) {
      nav.innerHTML = NAV.filter((item) => item.roles.includes(user.role)).map((item) => {
        const active = navAktif(item);
        return `<a href="${item.href}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium ${active
          ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900'}">
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="${item.icon}"/></svg>
        <span>${esc(item.label)}</span>
      </a>`;
      }).join('');
    }

    // Item dropdown profil disaring menurut peran; Pengaturan hanya milik Super Admin.
    document.querySelectorAll('#lp-user-panel [data-menu-role]').forEach((node) => {
      node.classList.toggle('hidden', !node.dataset.menuRole.split(',').includes(user.role));
    });

    const mobileToggle = document.getElementById('lp-mobile-nav');
    if (mobileToggle) {
      mobileToggle.onclick = () => document.getElementById('lp-aside')?.classList.toggle('hidden');
    }
  }

  async function loadUnreadBadge() {
    const dot = document.getElementById('lp-unread');
    if (!dot) return;
    try {
      const data = await fetchJson('/notifications/unread-count', { quiet: true, allowUnauthorized: true });
      const count = Number(data?.unread_count ?? 0);
      dot.textContent = count > 99 ? '99+' : String(count);
      dot.classList.toggle('hidden', count === 0);
    } catch { dot.classList.add('hidden'); }
  }

  /* ---------- lonceng notifikasi (header) ---------- */

  const NOTIF_TUJUAN = {
    payment: '/pembayaran',
    booking: '/booking',
    consumer: '/data-konsumen',
    system: '/log-aktivitas',
  };

  const notifHref = (row) => row.link || NOTIF_TUJUAN[row.type] || '/notifikasi';

  function renderNotifPanel(rows) {
    const host = document.getElementById('lp-notif-list');
    if (!host) return;
    if (!rows.length) {
      host.innerHTML = '<p class="px-3 py-6 text-center text-sm text-zinc-500">Belum ada notifikasi.</p>';
      return;
    }
    host.innerHTML = rows.slice(0, 6).map((row) => `
      <a href="${esc(notifHref(row))}" data-notif="${esc(row.id)}"
         class="flex gap-3 rounded-xl px-3 py-2.5 hover:bg-zinc-50 ${row.is_read ? '' : 'bg-amber-50/70'}">
        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full ${row.is_read ? 'bg-zinc-300' : 'bg-amber-500'}"></span>
        <span class="min-w-0">
          <span class="block truncate text-sm font-semibold text-zinc-900">${esc(row.title || 'Notifikasi')}</span>
          <span class="mt-0.5 block max-h-8 overflow-hidden text-xs text-zinc-500">${esc(row.message || '')}</span>
          <span class="mt-1 block text-[11px] text-zinc-400">${esc(datetime(row.created_at))}</span>
        </span>
      </a>`).join('');
  }

  async function loadNotifPanel() {
    const host = document.getElementById('lp-notif-list');
    if (!host) return;
    host.innerHTML = '<p class="px-3 py-6 text-center text-sm text-zinc-500">Memuat notifikasi...</p>';
    try {
      const rows = await fetchJson('/notifications', { quiet: true });
      renderNotifPanel(Array.isArray(rows) ? rows : []);
    } catch {
      host.innerHTML = '<p class="px-3 py-6 text-center text-sm text-rose-600">Notifikasi gagal dimuat.</p>';
    }
  }

  function markNotifRead(id) {
    http.put('/notifications/' + encodeURIComponent(id) + '/read', {}, { quiet: true })
      .then(() => loadUnreadBadge())
      .catch(() => {});
  }

  /* ---------- penghitung waktu sesi ---------- */

  const PERINGATAN_SESI_DETIK = 300;
  let sesiTimer = null;
  let sesiDiperingatkan = false;
  let sesiBatasIdleDetik = 0;
  let sesiAktivitasTerakhir = Date.now();
  let sesiSedangDiperpanjang = false;

  function catatAktivitas() {
    sesiAktivitasTerakhir = Date.now();
  }

  // Revisi 35: hanya ketiadaan aktivitas yang boleh mengakhiri sesi, jadi setiap
  // interaksi pengguna menjadi penanda bahwa layar masih dipakai.
  function pantauAktivitas() {
    if (pantauAktivitas.siap) return;
    pantauAktivitas.siap = true;
    ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach((jenis) => {
      document.addEventListener(jenis, catatAktivitas, { passive: true, capture: true });
    });
  }

  function hentikanPenghitungSesi() {
    if (sesiTimer) {
      clearInterval(sesiTimer);
      sesiTimer = null;
    }
  }

  function keluarSesi(pesan) {
    hentikanPenghitungSesi();
    token.clear();
    toast(pesan, 'error');
    setTimeout(() => location.replace('/login?sesi=berakhir'), 1800);
  }

  function formatSisaSesi(detik) {
    const dua = (n) => String(n).padStart(2, '0');
    const jam = Math.floor(detik / 3600);
    const menit = Math.floor((detik % 3600) / 60);
    const detikKecil = Math.floor(detik % 60);
    return jam > 0 ? `${dua(jam)}:${dua(menit)}:${dua(detikKecil)}` : `${dua(menit)}:${dua(detikKecil)}`;
  }

  async function perpanjangSesi(hening = false) {
    const { json } = await request('POST', '/session/refresh', {});
    token.set(json.access_token);
    if (!hening) toast(json.message || 'Sesi berhasil diperpanjang.');
    catatAktivitas();
    await mulaiPenghitungSesi();
  }

  async function mulaiPenghitungSesi() {
    const kotak = document.getElementById('lp-session');
    if (!kotak) return;

    let status;
    try {
      status = await fetchJson('/session/status', { quiet: true });
    } catch {
      return;
    }

    const sisa = Number(status?.remaining_seconds);
    if (!Number.isFinite(sisa)) {
      kotak.classList.add('hidden');
      return;
    }

    sesiBatasIdleDetik = Math.max(0, Number(status?.session_timeout_minutes) * 60) || sisa;
    sesiAktivitasTerakhir = Date.now();
    pantauAktivitas();

    const akhir = Date.now() + sisa * 1000;
    const label = document.getElementById('lp-session-left');
    const tombol = document.getElementById('lp-session-extend');
    sesiDiperingatkan = false;
    kotak.classList.remove('hidden', 'border-amber-300', 'bg-amber-50', 'text-amber-900');
    if (tombol) tombol.classList.add('hidden');
    hentikanPenghitungSesi();

    if (tombol && !tombol.dataset.siap) {
      tombol.dataset.siap = '1';
      tombol.onclick = () => withBusy(tombol, perpanjangSesi);
    }

    const jalankan = () => {
      const idle = Math.round((Date.now() - sesiAktivitasTerakhir) / 1000);
      if (sesiBatasIdleDetik > 0 && idle >= sesiBatasIdleDetik) {
        keluarSesi('Tidak ada aktivitas selama ' + formatSisaSesi(idle) + '. Silakan masuk kembali.');
        return;
      }

      const detik = Math.max(0, Math.round((akhir - Date.now()) / 1000));
      if (label) label.textContent = formatSisaSesi(detik);

      if (detik <= PERINGATAN_SESI_DETIK) {
        kotak.classList.add('border-amber-300', 'bg-amber-50', 'text-amber-900');
        if (tombol) tombol.classList.remove('hidden');
        if (!sesiDiperingatkan) {
          sesiDiperingatkan = true;
          toast('Sesi berakhir dalam ' + formatSisaSesi(detik) + '. Klik Perpanjang agar tidak keluar otomatis.', 'info');
        }
      }

      // Layar yang masih dipakai diperpanjang sendiri; yang ditinggalkan dibiarkan habis.
      if (detik <= 60 && idle < PERINGATAN_SESI_DETIK && !sesiSedangDiperpanjang) {
        sesiSedangDiperpanjang = true;
        perpanjangSesi(true)
          .catch(() => keluarSesi('Sesi berakhir, silakan masuk kembali.'))
          .finally(() => { sesiSedangDiperpanjang = false; });
        return;
      }

      if (detik <= 0) {
        keluarSesi('Waktu sesi habis. Silakan masuk kembali.');
      }
    };

    jalankan();
    sesiTimer = setInterval(jalankan, 1000);
  }

  function initShell() {
    const logoutButton = document.getElementById('lp-logout');
    if (logoutButton) logoutButton.onclick = (e) => { e.preventDefault(); logout(); };

    const menu = document.getElementById('lp-user-menu');
    const userPanel = document.getElementById('lp-user-panel');
    const bell = document.getElementById('lp-notif-btn');
    const notifPanel = document.getElementById('lp-notif-panel');

    if (menu && userPanel) {
      menu.onclick = (e) => {
        e.stopPropagation();
        userPanel.classList.toggle('hidden');
        notifPanel?.classList.add('hidden');
      };
    }

    if (bell && notifPanel) {
      bell.onclick = (e) => {
        e.stopPropagation();
        const akanTampil = notifPanel.classList.contains('hidden');
        notifPanel.classList.toggle('hidden', !akanTampil);
        userPanel?.classList.add('hidden');
        if (akanTampil) loadNotifPanel();
      };
      notifPanel.addEventListener('click', (event) => {
        const item = event.target.closest('[data-notif]');
        if (item) markNotifRead(item.dataset.notif);
      });
    }

    const markAll = document.getElementById('lp-notif-all');
    if (markAll) {
      markAll.onclick = () => withBusy(markAll, async () => {
        try {
          await http.put('/notifications/read-all', {});
          toast('Semua notifikasi ditandai sudah dibaca.');
          await loadUnreadBadge();
          await loadNotifPanel();
        } catch (error) {
          toast(error.message || 'Gagal menandai semua notifikasi.', 'error');
        }
      });
    }

    document.addEventListener('click', () => {
      userPanel?.classList.add('hidden');
      notifPanel?.classList.add('hidden');
    });
  }

  initShell();

  return {
    BASE, ApiError, http, request, unwrap, fetchJson,
    token, login, logout, me, guard, NAV, buildShell, paintIdentity, loadUnreadBadge, initShell,
    mulaiPenghitungSesi, perpanjangSesi,
    can: (roles) => !!currentUser && roles.indexOf(currentUser.role) !== -1,
    currentUserRole: () => (currentUser ? currentUser.role : ''),
    money, shortMoney, number, date, datetime, monthLabel, isoDate, today, esc, badge, roleLabel, fotoSales,
    sandiKuat, PESAN_SANDI,
    toast, confirm, formModal, table, statCard, loading, errorBox, barChart, download, debounce, withBusy,
    fillSettings, cetakLaporan,
  };
})();
