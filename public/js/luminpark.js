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
    super_admin: 'Super Admin', admin_sales: 'Admin Sales', sales: 'Sales', consumer: 'Konsumen',
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
    { href: '/data-sales', label: 'Data Sales', icon: 'M16 11a4 4 0 10-8 0M4 20a8 8 0 0116 0', roles: ['super_admin', 'admin_sales'] },
    { href: '/data-konsumen', label: 'Data Konsumen', icon: 'M12 12a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 0114 0', roles: ['super_admin', 'admin_sales', 'sales'] },
    { href: '/booking', label: 'Booking Unit', icon: 'M8 3v4M16 3v4M3 9h18M5 5h14v16H5z', roles: ['super_admin', 'admin_sales', 'sales'] },
    { href: '/pembayaran', label: 'Data Pembayaran', icon: 'M3 10h18M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z', roles: ['super_admin', 'admin_sales', 'sales'] },
    { href: '/laporan', label: 'Laporan', icon: 'M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm2 6h6M9 13h6M9 17h4', roles: ['super_admin', 'admin_sales'] },
    { href: '/laporan-sales', label: 'Laporan Sales', icon: 'M9 17V7l4 5 4-5v10M5 21h14V3H5z', roles: ['sales'] },
    { href: '/log-aktivitas', label: 'Log Aktivitas', icon: 'M4 6h16M4 12h16M4 18h10', roles: ['super_admin'] },
  ];

  function buildShell(user) {
    const nav = document.getElementById('lp-nav');
    if (nav) {
      const current = location.pathname;
      nav.innerHTML = NAV.filter((item) => item.roles.includes(user.role)).map((item) => {
        const active = current === item.href || current.startsWith(item.href + '/');
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
    can: (roles) => !!currentUser && roles.indexOf(currentUser.role) !== -1,
    currentUserRole: () => (currentUser ? currentUser.role : ''),
    money, shortMoney, number, date, datetime, monthLabel, isoDate, today, esc, badge, roleLabel,
    toast, confirm, formModal, table, statCard, loading, errorBox, barChart, download, debounce, withBusy,
    fillSettings,
  };
})();
