@extends('layouts.app')

@section('title', 'Log Aktivitas')

@section('page_title', 'Log Aktivitas Sistem')

@section('page_desc', 'Khusus peran Super Admin. Dua jejak berbeda: perubahan data per modul (activity_logs) dan kejadian akun/sistem (system_logs).')

@section('page_actions')
<button id="log-reload" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Muat Ulang</button>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" data-log-source="audit" class="log-tab rounded-xl border px-4 py-2 text-sm font-semibold">Jejak audit data</button>
        <button type="button" data-log-source="system" class="log-tab rounded-xl border px-4 py-2 text-sm font-semibold">Aktivitas sistem &amp; login</button>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block sm:col-span-2">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Cari Aktivitas</span>
                <input id="log-search" type="search" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm" placeholder="Ketik nama pengguna, aktivitas, atau keterangan">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Filter Pengguna</span>
                <select id="log-user" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm">
                    <option value="">Semua pengguna</option>
                </select>
            </label>
            <div class="flex items-end">
                <p id="log-count" class="pb-2 text-sm text-zinc-500">Memuat log...</p>
            </div>
        </div>
    </div>

    <div id="log-table"></div>
</div>
@endsection

@push('scripts')
<script>
  let rows = [];
  let sumber = 'audit';

  const SUMBER = {
    audit: { endpoint: '/activity-logs?per_page=200', kosong: 'Belum ada perubahan data tercatat.', kolom: ['Modul & aksi', 'Perangkat'] },
    system: { endpoint: '/system-logs', kosong: 'Belum ada aktivitas sistem tersimpan.', kolom: ['Aktivitas', 'Perangkat'] },
  };

  const activityLabel = (value) => String(value || '-').replace(/_/g, ' ');

  function fillUserFilter() {
    const users = [];
    rows.forEach((row) => {
      if (!row.user_id) return;
      if (!users.some((item) => item.id === row.user_id)) {
        users.push({ id: row.user_id, name: row.user ? row.user.name : 'Pengguna #' + row.user_id });
      }
    });
    users.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    const select = document.getElementById('log-user');
    const keep = select.value;
    select.innerHTML = '<option value="">Semua pengguna</option>'
      + users.map((user) => '<option value="' + LP.esc(user.id) + '">' + LP.esc(user.name) + '</option>').join('');
    select.value = keep;
  }

  function filtered() {
    const term = document.getElementById('log-search').value.trim().toLowerCase();
    const userId = document.getElementById('log-user').value;
    return rows.filter((row) => {
      if (userId && String(row.user_id) !== String(userId)) return false;
      if (!term) return true;
      const haystack = [row.user ? row.user.name : '', activityLabel(row.activity), row.description || '', row.ip_address || '']
        .join(' ')
        .toLowerCase();
      return haystack.includes(term);
    });
  }

  function render() {
    const list = filtered();
    document.getElementById('log-count').textContent = list.length + ' dari ' + rows.length + ' log ditampilkan';

    LP.table({
      mount: 'log-table',
      rows: list,
      empty: rows.length ? 'Tidak ada log yang cocok dengan pencarian.' : SUMBER[sumber].kosong,
      columns: [
        { label: 'Waktu', render: (row) => LP.datetime(row.created_at) },
        { label: 'Pengguna', render: (row) => LP.esc(row.user ? row.user.name : 'Sistem') + '<span class="block text-xs text-zinc-500">' + LP.esc(row.user ? LP.roleLabel(row.user.role) : 'tanpa akun') + '</span>' },
        { label: SUMBER[sumber].kolom[0], render: (row) => '<span class="inline-flex items-center rounded-lg bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700">' + LP.esc(activityLabel(row.activity)) + '</span>' },
        { label: 'Keterangan', render: (row) => LP.esc(row.description || '-') },
        { label: 'Alamat IP', render: (row) => LP.esc(row.ip_address || '-') },
        { label: SUMBER[sumber].kolom[1], render: (row) => { const text = String(row.user_agent || '-'); return '<span title="' + LP.esc(text) + '">' + LP.esc(text.length > 40 ? text.slice(0, 37) + '...' : text) + '</span>'; } },
      ],
    });
  }

  function aktifkanTab() {
    document.querySelectorAll('.log-tab').forEach((tab) => {
      const aktif = tab.dataset.logSource === sumber;
      tab.classList.toggle('border-[#1b3225]', aktif);
      tab.classList.toggle('bg-[#1b3225]', aktif);
      tab.classList.toggle('text-white', aktif);
      tab.classList.toggle('border-zinc-300', !aktif);
      tab.classList.toggle('bg-white', !aktif);
      tab.classList.toggle('text-zinc-700', !aktif);
      tab.setAttribute('aria-current', aktif ? 'true' : 'false');
    });
  }

  async function load() {
    LP.loading('log-table', 'Memuat log aktivitas...');
    document.getElementById('log-count').textContent = 'Memuat log...';
    aktifkanTab();
    try {
      const data = await LP.fetchJson(SUMBER[sumber].endpoint);
      // Baris audit memakai kolom action, sedangkan system log memakai activity.
      rows = (Array.isArray(data) ? data : []).map((row) => ({ ...row, activity: row.activity || row.action }));
      fillUserFilter();
      render();
    } catch (error) {
      LP.errorBox('log-table', error.message || 'Log aktivitas gagal dimuat.');
      document.getElementById('log-count').textContent = 'Log gagal dimuat.';
      LP.toast(error.message || 'Log aktivitas gagal dimuat.', 'error');
    }
  }

  document.getElementById('log-search').addEventListener('input', LP.debounce(render, 200));
  document.getElementById('log-user').addEventListener('change', render);
  document.getElementById('log-reload').addEventListener('click', (event) => {
    LP.withBusy(event.currentTarget, load);
  });
  document.querySelectorAll('.log-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      if (sumber === tab.dataset.logSource) return;
      sumber = tab.dataset.logSource;
      document.getElementById('log-search').value = '';
      load();
    });
  });

  LP.guard(['super_admin'], load);
</script>
@endpush
