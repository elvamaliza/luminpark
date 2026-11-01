@extends('layouts.app')

@section('title', 'Sesi Login')

@section('page_title', 'Sesi Login')

@section('page_desc', 'Daftar aktivitas login akun Anda beserta perangkat dan waktunya.')

@section('page_actions')
<button id="sesi-logout" type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Keluar</button>
@endsection

@section('content')
<div class="space-y-6">
    <div id="sesi-cards" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"></div>

    <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
      Sesi ditandai berakhir saat Anda menekan tombol Keluar. Menutup tab atau browser tidak langsung mengakhiri sesi, sehingga sebaiknya selalu gunakan tombol Keluar pada perangkat umum.
    </div>

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Riwayat Sesi</h2>
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
      zinc: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
    };
    return '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset '
      + (tones[conf[1]] || tones.zinc) + '">' + LP.esc(conf[0]) + '</span>';
  }

  function shortenAgent(agent) {
    const text = String(agent || 'Perangkat tidak dikenal');
    return text.length > 60 ? text.slice(0, 57) + '...' : text;
  }

  async function load() {
    LP.loading('sesi-cards', 'Memuat sesi...');
    LP.loading('sesi-table', 'Memuat riwayat sesi...');

    try {
      const data = await LP.fetchJson('/sessions');
      const rows = Array.isArray(data) ? data : [];
      const active = rows.filter((row) => row.status === 'active' && !row.logout_at);
      const newest = active.length ? active[0] : null;
      const lastLogin = rows.length ? rows[0] : null;

      document.getElementById('sesi-cards').innerHTML = [
        { label: 'Sesi Aktif', value: LP.number(active.length), hint: 'Termasuk sesi yang sedang Anda pakai', tone: 'emerald' },
        { label: 'Total Riwayat Sesi', value: LP.number(rows.length), hint: '20 sesi terakhir' },
        { label: 'Login Terakhir', value: lastLogin ? LP.datetime(lastLogin.login_at) : '-', hint: 'Waktu masuk paling baru' },
        { label: 'IP Sesi Ini', value: newest ? LP.esc(newest.ip_address || '-') : '-', hint: 'Perangkat yang sedang aktif' },
      ].map((item) => LP.statCard(item)).join('');

      LP.table({
        mount: 'sesi-table',
        rows: rows,
        empty: 'Belum ada riwayat sesi login.',
        columns: [
          {
            label: 'Alamat IP',
            render: (row) => LP.esc(row.ip_address || '-')
              + (newest && row.id === newest.id ? ' <span class="ml-1 inline-flex items-center rounded-full bg-zinc-900 px-2 py-0.5 text-[10px] font-semibold text-white">Sesi ini</span>' : ''),
          },
          { label: 'Perangkat', render: (row) => '<span title="' + LP.esc(row.user_agent || '') + '">' + LP.esc(shortenAgent(row.user_agent)) + '</span>' },
          { label: 'Masuk', render: (row) => LP.datetime(row.login_at) },
          { label: 'Keluar', render: (row) => (row.logout_at ? LP.datetime(row.logout_at) : '-') },
          { label: 'Status', render: (row) => statusChip(row.status) },
        ],
      });
    } catch (error) {
      LP.errorBox('sesi-cards', error.message || 'Sesi gagal dimuat.');
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
