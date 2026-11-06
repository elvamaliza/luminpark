@extends('layouts.app')

@section('title', 'Sesi Login')

@section('page_title', 'Sesi Login')

@section('page_desc', 'Daftar aktivitas login akun Anda beserta perangkat dan waktunya.')

@section('page_actions')
<button id="sesi-logout" type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">Keluar</button>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Kotak Statistik disesuaikan dengan variasi warna lembut -->
    <div id="sesi-stats" class="mb-4"></div>

    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 shadow-sm">
        Sesi ditandai berakhir saat Anda menekan tombol Keluar. Menutup tab atau browser tidak langsung mengakhiri sesi, sehingga sebaiknya selalu gunakan tombol Keluar pada perangkat umum.
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Riwayat Sesi</h2>
        <div id="sesi-table"></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
  const SESSION_LABEL = {
    active: ['Berjalan', 'emerald'],
    ended: ['Selesai', 'zinc'],
    expired: ['Kedaluwarsa', 'amber'],
  };

  function statusChip(value) {
    const conf = SESSION_LABEL[value] || [value || '-', 'zinc'];
    const tones = {
      emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
      amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
      zinc: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    };
    return '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset '
      + (tones[conf[1]] || tones.zinc) + '">' + LP.esc(conf[0]) + '</span>';
  }

  function shortenAgent(agent) {
    const text = String(agent || 'Perangkat tidak dikenal');
    return text.length > 60 ? text.slice(0, 57) + '...' : text;
  }

  function sesiStats(rows, active, newest) {
    const host = document.getElementById('sesi-stats');
    const lastLogin = rows.length ? rows[0] : null;

    const items = [
      { label: 'Sesi Aktif', value: LP.number(active.length), hint: 'Termasuk sesi yang sedang Anda pakai', bg: 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)', border: '#bbf7d0' },
      { label: 'Total Riwayat Sesi', value: LP.number(rows.length), hint: '20 sesi terakhir', bg: 'linear-gradient(135deg, #ffffff 0%, #fafafa 100%)', border: '#e2e8f0' },
      { label: 'Login Terakhir', value: lastLogin ? LP.datetime(lastLogin.login_at) : '-', hint: 'Waktu masuk paling baru', bg: 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)', border: '#bae6fd' },
      { label: 'IP Sesi Ini', value: newest ? LP.esc(newest.ip_address || '-') : '-', hint: 'Perangkat yang sedang aktif', bg: 'linear-gradient(135deg, #fefce8 0%, #fef9c3 100%)', border: '#fde047' },
    ];

    host.innerHTML = '<div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px;" class="max-xl:grid-cols-2">'
      + items.map((item) => {
          return `<div style="background: ${item.bg}; border: 1px solid ${item.border}; padding: 12px 14px; border-radius: 10px; min-height: 65px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);" class="transition-all hover:shadow-md">
            <p style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #334155; margin-bottom: 1px;">${LP.esc(item.label)}</p>
            <p style="font-size: 15px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 0px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 9px; color: #475569; margin-top: 1px;">${LP.esc(item.hint)}</p>` : ''}
          </div>`;
      }).join('') + '</div>';
  }

  async function load() {
    LP.loading('sesi-stats', 'Memuat sesi...');
    LP.loading('sesi-table', 'Memuat riwayat sesi...');

    try {
      const data = await LP.fetchJson('/sessions');
      const rows = Array.isArray(data) ? data : [];
      const active = rows.filter((row) => row.status === 'active' && !row.logout_at);
      const newest = active.length ? active[0] : null;

      sesiStats(rows, active, newest);

      LP.table({
        mount: 'sesi-table',
        rows: rows,
        empty: 'Belum ada riwayat sesi login.',
        columns: [
          { label: 'No.', render: (row, index) => String(index + 1) },
          {
            label: 'Alamat IP',
            render: (row) => '<span class="font-semibold text-slate-900">' + LP.esc(row.ip_address || '-') + '</span>'
              + (newest && row.id === newest.id ? ' <span class="ml-1 inline-flex items-center rounded-full bg-[#1b3225] px-2 py-0.5 text-[10px] font-semibold text-white">Sesi ini</span>' : ''),
          },
          { label: 'Perangkat', render: (row) => '<span title="' + LP.esc(row.user_agent || '') + '">' + LP.esc(shortenAgent(row.user_agent)) + '</span>' },
          { label: 'Masuk', render: (row) => LP.datetime(row.login_at) },
          { label: 'Keluar', render: (row) => (row.logout_at ? LP.datetime(row.logout_at) : '-') },
          { label: 'Status', render: (row) => statusChip(row.status) },
        ],
      });
    } catch (error) {
      LP.errorBox('sesi-stats', error.message || 'Sesi gagal dimuat.');
      LP.errorBox('sesi-table', 'Riwayat sesi tidak dapat dimuat.');
      LP.toast(error.message || 'Sesi gagal dimuat.', 'error');
    }
  }

  document.getElementById('sesi-logout').addEventListener('click', () => {
    LP.confirm('Anda akan keluar dari sesi ini dan sesi tersebut ditandai berakhir.', { title: 'Keluar', okLabel: 'Ya, Keluar', danger: true })
      .then((ok) => { if (ok) LP.logout(); });
  });

  LP.guard(['manager'], load);
</script>
@endpush