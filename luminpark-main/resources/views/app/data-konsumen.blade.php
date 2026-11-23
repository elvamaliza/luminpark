@extends('layouts.app')

@section('title', 'Data Konsumen')
@section('page_title')<span id="lead-judul">Data Konsumen</span>@endsection
@section('page_desc')<span id="lead-desc">Daftar konsumen dan calon konsumen. Buka Detail untuk mengubah data, menyesuaikan status, atau mencatat follow up.</span>@endsection

@section('page_actions')
    <a href="/booking" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all shadow-sm">Booking Unit</a>
    <button id="btn-add-lead" type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Tambah Konsumen
    </button>
@endsection

@section('content')
    <!-- Kotak statistik dengan variasi warna lembut seperti halaman sales -->
    <div id="lead-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cari Konsumen</span>
                <input id="lead-search" type="search" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20" placeholder="nama, nomor telepon, atau email">
            </label>
            <label id="lead-label-status" class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status</span>
                <select id="lead-status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                    <option value="">Semua Status</option>
                    <option value="cold">Cold</option>
                    <option value="warm">Warm</option>
                    <option value="hot">Hot</option>
                </select>
            </label>
            <label id="lead-label-category" class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Kategori</span>
                <select id="lead-category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                    <option value="">Semua Kategori</option>
                    <option value="konsumen">Konsumen (pembayaran terverifikasi)</option>
                    <option value="prospek">Calon Konsumen</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
            </label>
            <label id="lead-label-sales" class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Sales Penanganan</span>
                <input id="lead-sales" type="search" list="lead-sales-list" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20" placeholder="ketik atau pilih nama sales" autocomplete="off">
                <datalist id="lead-sales-list"></datalist>
            </label>
            <p id="lead-count" class="self-end text-sm text-slate-500">Memuat data konsumen...</p>
        </div>
    </section>

    <div id="lead-table"></div>

    <p class="mt-4 text-xs text-slate-500">
      Klik baris konsumen untuk membuka detail beserta riwayat interaksinya. Pemesanan hanya dapat dibuat untuk calon konsumen berstatus Hot. Data konsumen ditambah pada menu Database Konsumen; daftar Konsumen terisi otomatis begitu pembayaran pemesanan seorang calon konsumen disetujui super admin. Pembatalan dilakukan melalui tombol Batal pada kolom Aksi dan memindahkan konsumen ke kategori Dibatalkan pada penapis Kategori.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_INTERNAL = ['super_admin', 'admin_sales', 'sales'];
  const LEAD_STATUS_LIST = [
    { value: 'cold', label: 'Cold' },
    { value: 'warm', label: 'Warm' },
    { value: 'hot', label: 'Hot' },
  ];

  // Kategori dibaca dari pembayaran yang sudah disetujui, bukan dari ada tidaknya booking.
  const LEAD_CATEGORY_LABEL = {
    konsumen: 'Konsumen',
    prospek: 'Calon Konsumen',
    dibatalkan: 'Dibatalkan',
  };
  // Revisi 25: riwayat pembatalan tidak lagi punya halaman sendiri, melainkan
  // dibuka dari penapis Kategori pada daftar ini.
  const LEAD_KATEGORI_PENAPIS = Object.keys(LEAD_CATEGORY_LABEL);

  const leadSearch = document.getElementById('lead-search');
  const leadStatusFilter = document.getElementById('lead-status');
  const leadCategoryFilter = document.getElementById('lead-category');
  const leadSalesFilter = document.getElementById('lead-sales');

  let leadAll = [];
  let leadView = [];
  let leadUser = null;
  let leadSalesOptions = [];
  let leadSalesNames = [];
  let leadUnitOptions = [];

  const leadIsSales = () => !!leadUser && leadUser.role === 'sales';

  function leadKategoriUrl() {
    const nilai = new URLSearchParams(location.search).get('kategori');
    return LEAD_KATEGORI_PENAPIS.indexOf(nilai) === -1 ? '' : nilai;
  }

  // Kategori aktif: dari penapis; menu sales sudah membawanya lewat ?kategori=.
  function leadKategoriAktif() {
    return leadCategoryFilter.value;
  }

  function leadJudul() {
    const kategori = leadCategoryFilter.value;
    if (!leadIsSales()) return 'Data Konsumen';
    if (kategori === 'prospek') return 'Database Konsumen';
    if (kategori === 'dibatalkan') return 'Konsumen Dibatalkan';
    return 'Konsumen';
  }

  function leadPaintTitle() {
    const judul = leadJudul();
    document.getElementById('lead-judul').textContent = judul;
    document.title = judul + ' · Luminpark';

    if (!leadIsSales()) {
        document.getElementById('lead-desc').textContent =
          'Konsumen, calon konsumen, dan riwayat pembatalan. Buka Detail untuk mengubah data, menyesuaikan status, atau mencatat follow up.';
        return;
    }

    const deskripsi = {
      prospek: 'Calon konsumen yang Anda tangani, dengan status Cold, Warm, atau Hot. Data berpindah menjadi Konsumen setelah pembayaran pertamanya disetujui.',
      dibatalkan: 'Konsumen dengan pemesanan yang dibatalkan melalui tombol Batal, termasuk yang sudah lunas atau selesai serah terima. Riwayat pembeliannya tetap tersimpan dan dapat dibuka pada halaman detail maupun daftar kategori Dibatalkan ini.',
      konsumen: 'Konsumen yang pembayaran pemesanannya sudah disetujui sehingga sah menjadi pembeli. Data terisi otomatis dan pembatalan dilakukan dari tombol Batal pada kolom Aksi.',
    };
    document.getElementById('lead-desc').textContent = deskripsi[leadCategoryFilter.value]
      || 'Seluruh konsumen dan calon konsumen yang Anda tangani. Data berpindah menjadi Konsumen setelah pembayaran pertama disetujui.';
  }

  // Revisi 18: penambahan data hanya lewat Database Konsumen; daftar Konsumen dan
  // baris pembatalan terisi otomatis menurut pembayaran yang disetujui.
  function leadPaintActions() {
    const tombol = document.getElementById('btn-add-lead');

    if (!leadBolehKelola()) {
      tombol.classList.add('hidden');
      return;
    }

    const kategori = leadCategoryFilter.value;
    const bolehTambah = !leadIsSales() || kategori === '' || kategori === 'prospek';
    tombol.classList.toggle('hidden', !bolehTambah);
  }

  function leadProfile(row) {
    return row.sales_profile || row.salesProfile || null;
  }

  function leadSalesName(row) {
    const profile = leadProfile(row);
    if (profile && profile.user && profile.user.name) return profile.user.name;
    if (profile && profile.sales_code) return profile.sales_code;
    return 'Belum ditangani';
  }

  function leadUnitLabel(row) {
    if (row.interested_unit) return row.interested_unit;
    if (row.unit && row.unit.unit_code) return row.unit.unit_code;
    return '-';
  }

  // Revisi 41: konsumen yang sudah diverifikasi diwakili unit pemesananannya,
  // bukan unit yang dulu hanya diminati.
  function leadUnitDitempati(row) {
    if (row.unit_ditempati) return row.unit_ditempati;
    return leadUnitLabel(row);
  }

  // Revisi 40: penapis Search Sales Penanganan menerima nama atau kode sales;
  // isian kosong berarti seluruh data konsumen tetap ditampilkan.
  function leadSalesCocok(row) {
    const kata = (leadSalesFilter.value || '').trim().toLowerCase();
    if (!kata) return true;

    const profil = leadProfile(row);
    const haystack = [leadSalesName(row), profil ? profil.sales_code : ''].join(' ').toLowerCase();

    return haystack.indexOf(kata) !== -1;
  }

  // Revisi 39-41: status cold/warm/hot hanya dipakai pada kategori calon konsumen,
  // dan role sales tidak menampilkan kolom status sama sekali.
  function leadKategoriCalon() {
    return leadKategoriAktif() === 'prospek';
  }

  function leadPakaiStatus() {
    if (leadIsSales()) return false;
    return leadKategoriAktif() === '' || leadKategoriCalon();
  }

  function leadStale(row) {
    if (row.category && row.category !== 'prospek') return false;
    if (!row.last_follow_up) return true;
    const hari = new Date(row.last_follow_up);
    if (Number.isNaN(hari.getTime())) return true;
    return (Date.now() - hari.getTime()) > 7 * 24 * 60 * 60 * 1000;
  }

  function leadCocokKategori(row, kategori) {
    if (!kategori) return true;
    return row.category === kategori;
  }

  function leadFiltered() {
    const kata = (leadSearch.value || '').trim().toLowerCase();
    const status = leadStatusFilter.value;
    const kategori = leadKategoriAktif();

    return leadAll.filter((row) => {
      const haystack = [
        row.name, row.phone, row.email, leadUnitLabel(row),
        row.unit_ditempati, row.pesanan_tambahan,
      ].join(' ').toLowerCase();
      if (kata && haystack.indexOf(kata) === -1) return false;
      if (status && row.status !== status) return false;
      if (!leadCocokKategori(row, kategori)) return false;
      if (!leadSalesCocok(row)) return false;
      return true;
    });
  }

  // KOTAK STATISTIK DENGAN VARIASI WARNA LEMBUT (PUTIH, HIJAU, KUNING, BIRU)
  function leadRenderStats() {
    const host = document.getElementById('lead-stats');
    const kategori = (nama) => leadAll.filter((row) => row.category === nama).length;

    const items = [
      { label: 'Total Data Konsumen', value: LP.number(leadAll.length), hint: 'Konsumen, calon, dan riwayat pembatalan', bg: 'linear-gradient(135deg, #ffffff 0%, #fafafa 100%)', border: '#e2e8f0' },
      { label: 'Calon Konsumen', value: LP.number(kategori('prospek')), hint: 'Cold, Warm, Hot', bg: 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)', border: '#bae6fd' },
      { label: 'Konsumen', value: LP.number(kategori('konsumen')), hint: 'Pembayaran terverifikasi', bg: 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)', border: '#bbf7d0' },
      { label: 'Konsumen Dibatalkan', value: LP.number(kategori('dibatalkan')), hint: 'Dibuka lewat penapis Kategori', bg: 'linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%)', border: '#ddd6fe' },
      { label: 'Perlu Follow Up', value: LP.number(leadAll.filter(leadStale).length), hint: 'Belum ada kontak 7 hari terakhir', bg: 'linear-gradient(135deg, #fefce8 0%, #fef9c3 100%)', border: '#fde047' },
    ];

    host.innerHTML = '<div style="display: grid; grid-template-columns: repeat(' + items.length + ', minmax(0, 1fr)); gap: 10px;" class="max-xl:grid-cols-2">'
      + items.map((item) => {
          return `<div style="background: ${item.bg}; border: 1px solid ${item.border}; padding: 12px 14px; border-radius: 10px; min-height: 65px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);" class="transition-all hover:shadow-md">
            <p style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #334155; margin-bottom: 1px;">${LP.esc(item.label)}</p>
            <p style="font-size: 15px; font-weight: 700; line-height: 1.1; color: #0f172a; margin-top: 0px;">${item.value}</p>
            ${item.hint ? `<p style="font-size: 9px; color: #475569; margin-top: 1px;">${LP.esc(item.hint)}</p>` : ''}
          </div>`;
      }).join('') + '</div>';
  }

  function leadKolom() {
    const calon = leadKategoriCalon();
    const kolom = [
      {
        label: 'Konsumen',
        render: (row) => '<span class="font-semibold text-slate-900">' + LP.esc(row.name) + '</span>'
          + '<span class="block text-xs text-slate-500">' + LP.esc(row.email || 'Tanpa email') + '</span>',
      },
      { label: 'No. Telepon', render: (row) => LP.esc(row.phone) },
      { label: 'Kategori', render: (row) => LP.badge(row.category || 'prospek') },
    ];

    if (leadPakaiStatus()) kolom.push({ label: 'Status', render: (row) => LP.badge(row.status) });

    kolom.push({ label: 'Sales', render: (row) => LP.esc(leadSalesName(row)) });
    kolom.push({
      label: calon ? 'Unit Diminati' : 'Unit Ditempati',
      render: (row) => LP.esc(calon ? leadUnitLabel(row) : leadUnitDitempati(row)),
    });

    // Revisi 41: permintaan tambahan konstruksi ikut tampil pada daftar konsumen.
    if (!calon) kolom.push({ label: 'Pesanan Tambahan', render: (row) => LP.esc(row.pesanan_tambahan || 'Tidak ada') });

    kolom.push(
      { label: 'Chat Awal', render: (row) => LP.date(row.first_chat_date) },
      { label: 'Follow Up Terakhir', render: (row) => LP.date(row.last_follow_up) },
      {
        label: 'Aksi',
        render: (row, index) => '<div class="flex flex-wrap gap-1.5" data-stop>'
          + '<a href="/data-konsumen/' + LP.esc(row.id) + '" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Detail</a>'
          // Revisi 18: pemesanan aktif konsumen dibatalkan dari daftar ini juga.
          + (leadBolehKelola() && row.punya_booking_aktif
            ? '<button type="button" data-lead-action="batal" data-index="' + index + '" class="rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-50">Batal</button>'
            : '')
          + (leadBolehKelola()
            ? '<button type="button" data-lead-action="delete" data-index="' + index + '" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Hapus</button>'
            : '')
          + '</div>',
      },
    );

    return kolom;
  }

  function leadRender() {
    leadView = leadFiltered();

    document.getElementById('lead-count').textContent =
      leadView.length + ' konsumen ditampilkan dari ' + leadAll.length + ' data konsumen.';

    LP.table({
      mount: 'lead-table',
      rows: leadView,
      empty: 'Belum ada konsumen yang cocok. Tambahkan konsumen baru atau ubah kata kunci pencarian.',
      onRow: (row) => { window.location.href = '/data-konsumen/' + row.id; },
      columns: leadKolom(),
    });
  }

  function leadBolehKelola() {
    return LP.can(['super_admin', 'sales']);
  }

  function leadFields() {
    const fields = [
      { name: 'name', label: 'Nama Konsumen', type: 'text', required: true, placeholder: 'Nama lengkap konsumen' },
      { name: 'phone', label: 'Nomor Telepon', type: 'text', required: true, placeholder: '081234567890', hint: 'Maksimal 15 karakter. Satu nomor hanya untuk satu konsumen, termasuk konsumen yang sudah dibatalkan.' },
      { name: 'email', label: 'Email', type: 'email', placeholder: 'Email konsumen (opsional)' },
      { name: 'status', label: 'Status Konsumen', type: 'select', required: true, options: LEAD_STATUS_LIST },
      // Revisi 2: mencatat kapan konsumen pertama kali menghubungi sales.
      { name: 'first_chat_date', label: 'Tanggal Chat Awal', type: 'date', hint: 'Tanggal konsumen pertama kali menghubungi sales.' },
    ];

    if (!leadIsSales()) {
      fields.push({
        name: 'sales_id',
        label: 'Sales Penanganan',
        type: 'select',
        options: leadSalesOptions,
        placeholder: 'Belum ditangani sales',
      });
    }

    fields.push(
      { name: 'unit_id', label: 'Unit Diminati', type: 'select', options: leadUnitList(), placeholder: 'Belum memilih unit' },
      { name: 'interested_unit', label: 'Kode Tipe Unit (teks)', type: 'text', placeholder: 'Modern Tropical 60', hint: 'Opsional, misal catatan minat dari halaman kontak.' },
      { name: 'budget', label: 'Perkiraan Anggaran (Rp)', type: 'number', min: 0, step: 'any' },
      { name: 'last_follow_up', label: 'Follow Up Terakhir', type: 'date' },
      { name: 'source', label: 'Sumber Konsumen', type: 'text', placeholder: 'manual, website, telemarketing, atau event' },
      { name: 'address', label: 'Alamat', type: 'textarea', rows: 2, col: 2 },
      { name: 'note', label: 'Catatan', type: 'textarea', rows: 3, col: 2, placeholder: 'Preferensi, keberatan, atau kesepakatan dengan konsumen.' },
    );

    return fields;
  }

  function leadUnitList() {
    return leadUnitOptions.map((item) => ({ value: item.id, label: item.unit_code + ' — ' + item.unit_type }));
  }

  function leadValues() {
    return { status: 'cold', source: 'manual', first_chat_date: LP.today() };
  }

  function leadBody(values) {
    const body = {
      name: values.name.trim(),
      phone: values.phone.trim(),
      status: values.status,
      email: values.email,
      address: values.address,
      interested_unit: values.interested_unit,
      budget: values.budget,
      note: values.note,
      first_chat_date: values.first_chat_date,
      last_follow_up: values.last_follow_up,
      source: values.source,
      unit_id: values.unit_id,
    };
    if (!leadIsSales()) body.sales_id = values.sales_id;
    return body;
  }

  async function leadSave(values, context) {
    const hasil = await LP.http.post('/customer-leads', leadBody(values));

    LP.toast((hasil.json && hasil.json.message) || 'Data konsumen berhasil disimpan.');
    context.close();
    await leadLoad();
  }

  function leadOpenCreate() {
    LP.formModal({
      title: 'Tambah Konsumen Baru',
      description: 'Catat calon konsumen baru beserta sales yang menanganinya. Ubah data dan status dilakukan pada halaman detail konsumen.',
      fields: leadFields(),
      values: leadValues(),
      submitLabel: 'Simpan Konsumen',
      onSubmit: (values, context) => leadSave(values, context),
    });
  }

  async function leadDelete(row) {
    const setuju = await LP.confirm(
      'Data konsumen ' + row.name + ' beserta catatan follow up-nya akan dikeluarkan dari daftar. Riwayatnya tetap tersimpan di basis data dan dapat dipulihkan administrator.',
      { title: 'Hapus Konsumen', okLabel: 'Ya, Hapus', danger: true },
    );
    if (!setuju) return;

    try {
      const hasil = await LP.http.del('/customer-leads/' + row.id);
      LP.toast((hasil.json && hasil.json.message) || 'Data konsumen berhasil dihapus.');
    } catch (error) {
      LP.toast(error.message || 'Data konsumen gagal dihapus.', 'error');
      return;
    }
    await leadLoad();
  }

  // Revisi 18: pembatalan dicatat dengan alasan dan tanggal oleh sales.
  function leadOpenBatal(row) {
    LP.formModal({
      title: 'Batalkan Pemesanan — ' + row.name,
      description: 'Seluruh pemesanan aktif konsumen ini dibatalkan. Unit kembali tersedia dan konsumen keluar dari daftar Konsumen menjadi kategori Dibatalkan; '
        + 'riwayat pembayaran yang sudah disetujui tetap tersimpan.',
      fields: [
        {
          name: 'reason',
          label: 'Alasan Pembatalan',
          type: 'textarea',
          rows: 3,
          col: 2,
          required: true,
          placeholder: 'Contoh: konsumen mengundurkan diri karena pengajuan KPR tidak disetujui bank.',
          hint: 'Tercatat pada riwayat pemesanan dan dapat dibaca kembali lewat kategori Dibatalkan maupun detail konsumen.',
        },
        { name: 'cancelled_at', label: 'Tanggal Pembatalan', type: 'date', required: true, hint: 'Sesuai tanggal konsumen menyampaikan pembatalan.' },
      ],
      values: { cancelled_at: LP.today() },
      submitLabel: 'Ya, Batalkan Pemesanan',
      width: 'max-w-lg',
      onSubmit: async (values, context) => {
        const alasan = String(values.reason || '').trim();
        if (alasan.length < 5) throw new Error('Alasan pembatalan minimal 5 karakter.');

        const hasil = await LP.http.post('/customer-leads/' + row.id + '/batalkan', {
          reason: alasan,
          cancelled_at: values.cancelled_at,
        });

        LP.toast((hasil.json && hasil.json.message) || 'Pemesanan konsumen berhasil dibatalkan.');
        context.close();
        await leadLoad();
      },
    });
  }

  // Revisi 40: kendali ini diisi seluruh nama sales yang terdaftar, dan isian yang
  // dibiarkan kosong berarti semua baris konsumen tetap ditampilkan.
  function leadPaintFilters() {
    document.getElementById('lead-sales-list').innerHTML = leadSalesNames
      .map((nama) => '<option value="' + LP.esc(nama) + '"></option>')
      .join('');
  }

  // Revisi 26 & 27: status cold/warm/hot hanya dimiliki calon konsumen, dan menu
  // Database Konsumen sudah tetap pada kategorinya, jadi penapis yang tidak
  // relevan disembunyikan beserta nilai yang sedang terpilih.
  function leadPaintFilterVisibility() {
    const prospek = leadCategoryFilter.value === 'prospek';
    const pakaiStatus = !leadIsSales() || prospek;
    const pakaiKategori = !leadIsSales() || !prospek;

    document.getElementById('lead-label-status').classList.toggle('hidden', !pakaiStatus);
    document.getElementById('lead-label-category').classList.toggle('hidden', !pakaiKategori);
    // Revisi 39: role sales hanya menangani konsumennya sendiri, jadi penapis sales tidak tampil.
    document.getElementById('lead-label-sales').classList.toggle('hidden', leadIsSales());

    // Revisi 39: opsi Calon Konsumen dihapus dari penapis role sales, tetapi tetap
    // ada di DOM supaya menu Database Konsumen bisa menyetel ?kategori=prospek.
    const opsiCalon = leadCategoryFilter.querySelector('option[value="prospek"]');
    if (opsiCalon) opsiCalon.hidden = leadIsSales();

    if (!pakaiStatus) leadStatusFilter.value = '';
  }

  async function leadLoad() {
    LP.loading('lead-table', 'Memuat data konsumen...');
    document.getElementById('lead-count').textContent = 'Memuat data konsumen...';

    try {
      const daftar = await LP.fetchJson('/customer-leads');
      leadAll = Array.isArray(daftar) ? daftar : [];
    } catch (error) {
      LP.errorBox('lead-table', error.message || 'Data konsumen gagal dimuat.');
      document.getElementById('lead-count').textContent = 'Data gagal dimuat.';
      return;
    }

    leadRenderStats();
    leadRender();
  }

  // Pilihan formulir hanya dibutuhkan peran yang boleh menulis, sedangkan daftar sales
  // dibaca semua peran internal karena menjadi isi penapis Sales Penanganan (revisi 40).
  async function leadMuatPilihan() {
    try {
      if (leadBolehKelola()) {
        const unit = await LP.fetchJson('/units-available');
        leadUnitOptions = Array.isArray(unit) ? unit : [];
      }

      if (!leadIsSales()) {
        const daftarSales = await LP.fetchJson('/sales');
        const sales = (Array.isArray(daftarSales) ? daftarSales : []).filter((item) => leadProfile(item));

        leadSalesOptions = sales.map((item) => ({
          value: item.sales_profile.id,
          label: item.name + ' (' + item.sales_profile.sales_code + ')',
        }));
        leadSalesNames = [...new Set(sales.map((item) => item.name))];
      }
    } catch (error) {
      LP.toast('Sebagian data pilihan gagal dimuat: ' + error.message, 'error');
    }
  }

  LP.guard(ROLE_INTERNAL, async (user) => {
    leadUser = user;
    leadCategoryFilter.value = leadKategoriUrl();
    leadPaintTitle();

    await leadMuatPilihan();

    leadPaintFilters();
    leadPaintFilterVisibility();
    leadPaintActions();

    if (leadBolehKelola()) {
      document.getElementById('btn-add-lead').addEventListener('click', leadOpenCreate);
    }

    const renderDebounced = LP.debounce(leadRender);
    leadSearch.addEventListener('input', renderDebounced);
    leadStatusFilter.addEventListener('change', leadRender);
    leadCategoryFilter.addEventListener('change', () => { leadPaintTitle(); leadPaintFilterVisibility(); leadPaintActions(); leadRender(); });
    leadSalesFilter.addEventListener('input', renderDebounced);

    document.getElementById('lead-table').addEventListener('click', (event) => {
      const tombol = event.target.closest('button[data-lead-action]');
      if (!tombol) return;
      const baris = leadView[Number(tombol.dataset.index)];
      if (!baris) return;

      if (tombol.dataset.leadAction === 'batal') leadOpenBatal(baris);
      else leadDelete(baris);
    });

    await leadLoad();
  });
</script>
@endpush