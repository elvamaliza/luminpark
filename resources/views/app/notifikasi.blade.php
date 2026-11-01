@extends('layouts.app')

@section('title', 'Notifikasi')

@section('page_title', 'Notifikasi')

@section('page_desc', 'Pemberitahuan terkait pembayaran, booking, dan aktivitas akun Anda.')

@section('page_actions')
<div class="flex flex-wrap items-center gap-2">
    <button id="ntf-all" type="button" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Tandai Semua Dibaca</button>
    <button id="ntf-reload" type="button" class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Muat Ulang</button>
</div>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 shadow-sm">
        <p id="ntf-count" class="text-sm text-zinc-600">Memuat notifikasi...</p>
        <div class="flex items-center gap-2" role="group" aria-label="Saring notifikasi">
            <button type="button" data-filter="all" class="rounded-lg border border-zinc-900 bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white">Semua</button>
            <button type="button" data-filter="unread" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700">Belum Dibaca</button>
        </div>
    </div>
    <div id="ntf-list"></div>
</div>
@endsection

@push('scripts')
<script>
  const TYPE_LABEL = {
    payment: 'Pembayaran',
    booking: 'Booking',
    consumer: 'Konsumen',
    info: 'Informasi',
    system: 'Sistem',
  };

  const PAGE_FOR_TYPE = {
    payment: '/pembayaran',
    booking: '/booking',
    consumer: '/data-konsumen',
  };

  let rows = [];
  let filter = 'all';

  const targetPage = (type) => PAGE_FOR_TYPE[type] || '/notifikasi';

  function setFilterButtons() {
    document.querySelectorAll('[data-filter]').forEach((button) => {
      const active = button.dataset.filter === filter;
      button.className = 'rounded-lg border px-3 py-1.5 text-xs font-semibold '
        + (active ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 bg-white text-zinc-700');
    });
  }

  function render() {
    const unread = rows.filter((row) => !row.is_read).length;
    document.getElementById('ntf-count').innerHTML = rows.length
      ? 'Total <span class="font-semibold">' + LP.number(rows.length) + '</span> notifikasi dimuat · '
        + '<span class="font-semibold text-amber-700">' + LP.number(unread) + '</span> belum dibaca'
      : 'Belum ada notifikasi.';

    if (!rows.length) {
      document.getElementById('ntf-list').innerHTML = `
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center shadow-sm">
          <p class="text-sm font-medium text-zinc-700">Tidak ada notifikasi</p>
          <p class="mt-1 text-xs text-zinc-500">Pemberitahuan pembayaran dan booking akan muncul di halaman ini.</p>
        </div>`;
      return;
    }

    document.getElementById('ntf-list').innerHTML = rows.map((row) => {
      const page = targetPage(row.type);
      const tone = row.is_read ? 'bg-white' : 'bg-amber-50/70 border-l-4 border-l-amber-500';
      return `
        <article class="mb-3 rounded-2xl border border-zinc-200 p-4 shadow-sm ${tone}">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-sm font-semibold text-zinc-900">${LP.esc(row.title || 'Notifikasi')}</h2>
              <p class="mt-1 text-sm text-zinc-600">${LP.esc(row.message || '-')}</p>
              <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700">${LP.esc(TYPE_LABEL[row.type] || row.type || 'Umum')}</span>
                <span>${LP.datetime(row.created_at)}</span>
                ${row.is_read ? '<span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700">Dibaca</span>' : '<span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800">Baru</span>'}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <button type="button" data-read="${LP.esc(row.id)}" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 ${row.is_read ? 'hidden' : ''}">Tandai Dibaca</button>
              <a href="${LP.esc(page)}" class="rounded-lg border border-zinc-900 px-3 py-1.5 text-xs font-semibold text-zinc-900 hover:bg-zinc-900 hover:text-white">Buka Halaman</a>
            </div>
          </div>
        </article>`;
    }).join('');
  }

  async function load() {
    LP.loading('ntf-list', 'Memuat notifikasi...');
    setFilterButtons();
    try {
      const data = await LP.fetchJson('/notifications' + (filter === 'unread' ? '?unread_only=1' : ''));
      rows = Array.isArray(data) ? data : [];
      render();
      LP.loadUnreadBadge();
    } catch (error) {
      LP.errorBox('ntf-list', error.message || 'Notifikasi gagal dimuat.');
      document.getElementById('ntf-count').textContent = 'Notifikasi gagal dimuat.';
      LP.toast(error.message || 'Notifikasi gagal dimuat.', 'error');
    }
  }

  async function markRead(id, button) {
    await LP.withBusy(button, async () => {
      try {
        await LP.http.put('/notifications/' + encodeURIComponent(id) + '/read', {});
        const row = rows.find((item) => String(item.id) === String(id));
        if (row) row.is_read = true;
        render();
        LP.loadUnreadBadge();
        LP.toast('Notifikasi ditandai sudah dibaca.');
      } catch (error) {
        LP.toast(error.message || 'Gagal menandai notifikasi.', 'error');
      }
    });
  }

  document.getElementById('ntf-list').addEventListener('click', (event) => {
    const button = event.target.closest('[data-read]');
    if (button) markRead(button.dataset.read, button);
  });

  document.querySelectorAll('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      filter = button.dataset.filter;
      load();
    });
  });

  document.getElementById('ntf-all').addEventListener('click', async (event) => {
    await LP.withBusy(event.currentTarget, async () => {
      try {
        await LP.http.put('/notifications/read-all', {});
        LP.toast('Semua notifikasi ditandai sudah dibaca.');
        await load();
      } catch (error) {
        LP.toast(error.message || 'Gagal menandai semua notifikasi.', 'error');
      }
    });
  });

  document.getElementById('ntf-reload').addEventListener('click', (event) => {
    LP.withBusy(event.currentTarget, load);
  });

  LP.guard(['manager', 'admin_sales', 'sales'], load);
</script>
@endpush
