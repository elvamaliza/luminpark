@extends('layouts.app')

@section('title', 'Data Proyek')
@section('page_title', 'Pengelolaan Proyek')
@section('page_desc', 'Proyek mengelompokkan unit di dalam satu perumahan. Super Admin mengubah, mengarsipkan, dan menghapus proyek; Monitoring Manajemen hanya melihat.')

@section('page_actions')
    <button id="proyek-add" type="button" class="hidden inline-flex items-center gap-2 rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah Proyek
    </button>
@endsection

@section('content')
    <div id="proyek-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cari Proyek</span>
                <input id="proyek-search" type="search" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="nama proyek, alamat, atau deskripsi">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status</span>
                <select id="proyek-status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="diarsipkan">Diarsipkan</option>
                </select>
            </label>
            <div class="flex items-end">
                <p id="proyek-count" class="pb-2 text-sm text-slate-500">Memuat data proyek...</p>
            </div>
        </div>
    </section>

    <div id="proyek-table"></div>

    <p class="text-xs text-slate-500">Alamat serta koordinat lokasi dipakai peta pada halaman beranda. Kolom yang dibiarkan kosong membuat proyek ini tidak muncul di peta.</p>
@endsection

@push('scripts')
<script>
  let proyekAll = [];
  let perumahanList = [];

  const proyekSearch = document.getElementById('proyek-search');
  const proyekStatusFilter = document.getElementById('proyek-status');

  const bisaKelola = () => LP.can(['super_admin']);

  function proyekStatusBadge(row) {
    const diarsip = row.status === 'diarsipkan';
    const kelas = diarsip ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800';
    return '<span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold ' + kelas + '">'
      + (diarsip ? 'Diarsipkan' : 'Aktif') + '</span>';
  }

  function proyekFiltered() {
    const kata = proyekSearch.value.trim().toLowerCase();
    const status = proyekStatusFilter.value;

    return proyekAll.filter((row) => {
      if (status && row.status !== status) return false;
      if (!kata) return true;
      return [row.name, row.address || '', row.description || '', row.project ? row.project.name : ''].join(' ').toLowerCase().includes(kata);
    });
  }

  function proyekStats() {
    const totalUnit = proyekAll.reduce((jumlah, row) => jumlah + Number(row.units_count || 0), 0);
    const kartu = [
      { label: 'Total Proyek', value: LP.number(proyekAll.length), hint: 'seluruh proyek termasuk arsip' },
      { label: 'Proyek Aktif', value: LP.number(proyekAll.filter((row) => row.status !== 'diarsipkan').length), hint: 'muncul pada formulir unit' },
      { label: 'Proyek Arsip', value: LP.number(proyekAll.filter((row) => row.status === 'diarsipkan').length), hint: 'ditutup dari formulir unit' },
      { label: 'Unit Terdaftar', value: LP.number(totalUnit), hint: 'unit yang menempati proyek' },
    ];

    document.getElementById('proyek-stats').innerHTML = '<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">'
      + kartu.map((item) => LP.statCard(item)).join('') + '</div>';
  }

  function proyekActions(row) {
    const arsip = row.status === 'diarsipkan'
      ? '<button type="button" data-stop data-aksi="aktifkan" data-id="' + row.id + '" class="rounded-lg border border-emerald-300 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Aktifkan</button>'
      : '<button type="button" data-stop data-aksi="arsipkan" data-id="' + row.id + '" class="rounded-lg border border-amber-300 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">Arsipkan</button>';

    return '<div class="flex flex-wrap items-center gap-1.5" data-stop>'
      + '<button type="button" data-aksi="ubah" data-id="' + row.id + '" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Ubah</button>'
      + arsip
      + '<button type="button" data-aksi="hapus" data-id="' + row.id + '" class="rounded-lg border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button>'
      + '</div>';
  }

  function proyekRender() {
    const list = proyekFiltered();
    document.getElementById('proyek-count').textContent = list.length + ' dari ' + proyekAll.length + ' proyek ditampilkan';

    LP.table({
      mount: 'proyek-table',
      rows: list,
      empty: proyekAll.length ? 'Tidak ada proyek yang cocok dengan penyaring.' : 'Belum ada proyek. Tambahkan proyek sebelum membuat unit.',
      columns: [
        { label: 'Proyek', render: (row) => '<span class="font-semibold text-slate-800">' + LP.esc(row.name) + '</span>' },
        { label: 'Perumahan', render: (row) => LP.esc(row.project ? row.project.name : '-') },
        { label: 'Alamat', render: (row) => LP.esc(row.address || '-') },
        { label: 'Jumlah Unit', align: 'right', render: (row) => LP.number(row.units_count || 0) },
        { label: 'Status', render: proyekStatusBadge },
        { label: 'Deskripsi', render: (row) => { const teks = String(row.description || '-'); return '<span title="' + LP.esc(teks) + '">' + LP.esc(teks.length > 60 ? teks.slice(0, 57) + '...' : teks) + '</span>'; } },
        ...(bisaKelola() ? [{ label: 'Aksi', render: proyekActions }] : []),
      ],
    });
  }

  function proyekFields() {
    return [
      {
        name: 'project_id', label: 'Perumahan', type: 'select', required: true, col: 2,
        options: perumahanList.map((item) => ({ value: item.id, label: item.name })),
        placeholder: 'Pilih perumahan',
      },
      { name: 'name', label: 'Nama Proyek', type: 'text', required: true, placeholder: 'Proyek Asena', hint: 'Nama harus unik di dalam satu perumahan, maksimal 100 karakter.' },
      {
        name: 'status', label: 'Status', type: 'select', required: true,
        options: [{ value: 'aktif', label: 'Aktif' }, { value: 'diarsipkan', label: 'Diarsipkan' }],
        hint: 'Proyek arsip tidak ditawarkan lagi pada formulir unit.',
      },
      { name: 'address', label: 'Alamat Lokasi', type: 'text', col: 2, placeholder: 'Jalan, kecamatan, kota' },
      { name: 'latitude', label: 'Latitude', type: 'text', placeholder: '-0.8565000', hint: 'Angka -90 s.d. 90; dipakai penanda peta beranda.' },
      { name: 'longitude', label: 'Longitude', type: 'text', placeholder: '100.3512000', hint: 'Angka -180 s.d. 180; dipakai penanda peta beranda.' },
      { name: 'description', label: 'Deskripsi Proyek', type: 'textarea', rows: 3, col: 2, placeholder: 'Contoh: proyek depan dengan jalur hijau dan taman bermain.' },
    ];
  }

  function angkaKoordinat(nilai) {
    const teks = String(nilai ?? '').replace(',', '.').trim();
    if (!teks) return null;
    const angka = Number(teks);
    return Number.isFinite(angka) ? angka : null;
  }

  async function proyekSave(values, context, original) {
    const latitude = angkaKoordinat(values.latitude);
    const longitude = angkaKoordinat(values.longitude);

    if (latitude === null && longitude !== null) throw new Error('Isi latitude beserta longitude, atau kosongkan keduanya.');
    if (latitude !== null && longitude === null) throw new Error('Isi longitude beserta latitude, atau kosongkan keduanya.');
    if (latitude !== null && (latitude < -90 || latitude > 90)) throw new Error('Latitude harus berada di rentang -90 sampai 90.');
    if (longitude !== null && (longitude < -180 || longitude > 180)) throw new Error('Longitude harus berada di rentang -180 sampai 180.');

    const body = {
      project_id: Number(values.project_id),
      name: (values.name || '').trim(),
      description: (values.description || '').trim() || null,
      status: values.status || 'aktif',
      address: (values.address || '').trim() || null,
      latitude,
      longitude,
    };

    const hasil = original
      ? await LP.http.put('/clusters/' + original.id, body)
      : await LP.http.post('/clusters', body);

    LP.toast((hasil.json && hasil.json.message) || 'Proyek berhasil disimpan.');
    context.close();
    await proyekLoad();
  }

  function proyekOpenForm(original) {
    if (!perumahanList.length) {
      LP.toast('Daftar perumahan belum tersedia, padahal setiap proyek membutuhkan perumahan induk.', 'error');
      return;
    }

    LP.formModal({
      title: original ? 'Ubah Proyek ' + original.name : 'Tambah Proyek Baru',
      description: original
        ? 'Perubahan nama tidak memindahkan unit yang sudah menempati proyek ini.'
        : 'Proyek mengelompokkan unit di dalam satu perumahan. Unit baru hanya dapat dibuat setelah proyeknya tersedia.',
      fields: proyekFields(),
      values: original
        ? {
            project_id: original.project_id,
            name: original.name,
            status: original.status || 'aktif',
            description: original.description || '',
            address: original.address || '',
            latitude: original.latitude ?? '',
            longitude: original.longitude ?? '',
          }
        : { project_id: perumahanList.length === 1 ? perumahanList[0].id : '', status: 'aktif' },
      submitLabel: original ? 'Simpan Perubahan' : 'Simpan Proyek',
      onSubmit: (values, context) => proyekSave(values, context, original),
    });
  }

  function proyekCari(id) {
    return proyekAll.find((row) => String(row.id) === String(id));
  }

  async function proyekGantiStatus(row, status) {
    try {
      const hasil = await LP.http.patch('/clusters/' + row.id + '/arsipkan', { status });
      LP.toast((hasil.json && hasil.json.message) || 'Status proyek diperbarui.');
    } catch (error) {
      LP.toast(error.message || 'Status proyek gagal diperbarui.', 'error');
      return;
    }
    await proyekLoad();
  }

  async function proyekHapus(row) {
    const setuju = await LP.confirm(
      'Proyek "' + row.name + '" akan dikeluarkan dari daftar. Proyek yang masih punya unit tidak dapat dihapus; '
      + 'barisnya tetap tersimpan di basis data dan dapat dipulihkan administrator.',
      { title: 'Hapus Proyek', okLabel: 'Ya, Hapus', danger: true },
    );
    if (!setuju) return;

    try {
      const hasil = await LP.http.del('/clusters/' + row.id);
      LP.toast((hasil.json && hasil.json.message) || 'Proyek berhasil dihapus.');
    } catch (error) {
      LP.toast(error.message || 'Proyek gagal dihapus.', 'error');
      return;
    }
    await proyekLoad();
  }

  document.getElementById('proyek-table').addEventListener('click', async (event) => {
    const tombol = event.target.closest('[data-aksi]');
    if (!tombol) return;

    const row = proyekCari(tombol.dataset.id);
    if (!row) return;

    if (tombol.dataset.aksi === 'ubah') return proyekOpenForm(row);
    if (tombol.dataset.aksi === 'hapus') return proyekHapus(row);
    if (tombol.dataset.aksi === 'arsipkan') return LP.withBusy(tombol, () => proyekGantiStatus(row, 'diarsipkan'));
    if (tombol.dataset.aksi === 'aktifkan') return LP.withBusy(tombol, () => proyekGantiStatus(row, 'aktif'));
  });

  async function proyekLoad() {
    LP.loading('proyek-table', 'Memuat data proyek...');
    document.getElementById('proyek-count').textContent = 'Memuat data proyek...';

    try {
      const [daftar, perumahan] = await Promise.all([
        LP.fetchJson('/clusters'),
        LP.fetchJson('/projects').catch(() => []),
      ]);
      proyekAll = Array.isArray(daftar) ? daftar : [];
      perumahanList = Array.isArray(perumahan) ? perumahan : [];
    } catch (error) {
      LP.errorBox('proyek-table', error.message || 'Data proyek gagal dimuat.');
      document.getElementById('proyek-count').textContent = 'Data gagal dimuat.';
      return;
    }

    proyekStats();
    proyekRender();
  }

  proyekSearch.addEventListener('input', LP.debounce(proyekRender, 200));
  proyekStatusFilter.addEventListener('change', proyekRender);
  document.getElementById('proyek-add').addEventListener('click', () => proyekOpenForm(null));

  LP.guard(['super_admin', 'admin_sales'], (user) => {
    if (user.role === 'super_admin') document.getElementById('proyek-add').classList.remove('hidden');
    proyekLoad();
  });
</script>
@endpush
