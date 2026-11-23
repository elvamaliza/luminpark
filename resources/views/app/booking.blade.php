@extends('layouts.app')

@section('title', 'Booking Unit')
@section('page_title', 'Booking Unit')
@section('page_desc', 'Pantau pemesanan unit rumah dan buat booking baru lengkap dengan simulasi uang muka serta angsuran.')

@section('page_actions')
    <a href="/pembayaran" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all shadow-sm">Data Pembayaran</a>
    <button id="btn-buat-booking" type="button" class="inline-flex items-center gap-2 rounded-xl bg-[#1b3225] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#12241a] transition-all">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Buat Booking
    </button>
@endsection

@section('content')
    <!-- Kotak statistik dengan variasi warna lembut -->
    <div id="booking-stats" class="mb-4"></div>

    <section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cari Booking</span>
                <input id="booking-search" type="search" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20" placeholder="kode booking, nama konsumen, atau unit">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status Booking</span>
                <select id="booking-status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                    <option value="">Semua Status</option>
                    <option value="menunggu dp">Menunggu DP</option>
                    <option value="dp lunas">DP Lunas</option>
                    <option value="cicilan">Cicilan Berjalan</option>
                    <option value="lunas">Lunas</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status Serah Terima</span>
                <select id="booking-delivery" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-[#5c7361] focus:ring-2 focus:ring-[#5c7361]/20">
                    <option value="">Semua</option>
                    <option value="belum proses">Belum Proses</option>
                    <option value="sedang proses">Sedang Proses</option>
                    <option value="selesai">Selesai</option>
                </select>
            </label>
            <p id="booking-count" class="self-end text-sm text-slate-500">Memuat data booking...</p>
        </div>
    </section>

    <div id="booking-table"></div>

    <p class="mt-4 text-xs text-slate-500">
        Uang muka, angsuran, dan sisa pembayaran dihitung oleh sistem dari harga unit dan skema pembayaran terpilih,
        kemudian diperbarui otomatis setiap pembayaran diverifikasi super admin.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_INTERNAL = ['super_admin', 'admin_sales', 'sales'];

  const DELIVERY_LIST = [
    { value: 'belum proses', label: 'Belum Proses' },
    { value: 'sedang proses', label: 'Sedang Proses' },
    { value: 'selesai', label: 'Selesai' },
  ];

  // Revisi 43: kategori pembayaran boleh dicatat sekaligus saat booking dibuat.
  const PAYMENT_CATEGORY_LIST = [
    { value: 'dp', label: 'DP' },
    { value: 'cicilan', label: 'Cicilan' },
    { value: 'biaya tambahan', label: 'Biaya Tambahan' },
  ];

  const PAYMENT_METHOD_LIST = [
    { value: 'transfer bank', label: 'Transfer Bank' },
    { value: 'tunai', label: 'Tunai' },
    { value: 'qris', label: 'QRIS' },
    { value: 'virtual account', label: 'Virtual Account' },
    { value: 'cek/giro', label: 'Cek / Giro' },
  ];

  // Revisi 42: opsi unit baru terisi setelah proyek dipilih, jadi kotak simulasi
  // kosong menyebut proyek lebih dahulu.
  const SIMULASI_KOSONG = 'Pilih proyek, unit, dan skema pembayaran untuk melihat simulasi uang muka, angsuran, serta tenor.';

  function bookingCategoryLabel(nilai) {
    const ditemukan = PAYMENT_CATEGORY_LIST.find((item) => item.value === nilai);
    return ditemukan ? ditemukan.label : (nilai || 'Pembayaran unit');
  }

  const bookingSearch = document.getElementById('booking-search');
  const bookingStatusFilter = document.getElementById('booking-status');
  const bookingDeliveryFilter = document.getElementById('booking-delivery');

  let bookingAll = [];
  let bookingView = [];
  let bookingLeads = [];
  let bookingUnits = [];
  let bookingSchemes = [];
  let bookingSalesOptions = [];
  let bookingSimulasi = null;
  let bookingUser = null;

  function bookingLead(row) {
    return row.customer_lead || row.customerLead || null;
  }

  function bookingUnit(row) {
    return row.unit || null;
  }

  function bookingCustomerName(row) {
    const lead = bookingLead(row);
    return lead && lead.name ? lead.name : 'Konsumen #' + row.lead_id;
  }

  function bookingUnitLabel(row) {
    const unit = bookingUnit(row);
    if (!unit) return '-';
    const cluster = unit.cluster ? unit.cluster.name : '';
    return unit.unit_code + (cluster ? ' — ' + cluster : '');
  }

  function bookingSchemeName(row) {
    return row.payment_scheme ? row.payment_scheme.scheme_name : '-';
  }

  function bookingFiltered() {
    const kata = (bookingSearch.value || '').trim().toLowerCase();
    const status = bookingStatusFilter.value;
    const kirim = bookingDeliveryFilter.value;

    return bookingAll.filter((row) => {
      const haystack = [row.booking_code, bookingCustomerName(row), bookingUnitLabel(row), bookingSchemeName(row)].join(' ').toLowerCase();
      if (kata && haystack.indexOf(kata) === -1) return false;
      if (status && row.status !== status) return false;
      if (kirim && row.delivery_status !== kirim) return false;
      return true;
    });
  }

  // KOTAK STATISTIK DENGAN VARIASI WARNA LEMBUT (PUTIH, HIJAU, KUNING, BIRU)
  function bookingRenderStats() {
    const host = document.getElementById('booking-stats');
    const jumlah = (status) => bookingAll.filter((row) => row.status === status).length;
    const nilai = (key) => bookingAll.reduce((akumulasi, row) => akumulasi + Number(row[key] || 0), 0);

    const items = [
      { label: 'Total Booking', value: LP.number(bookingAll.length), bg: 'linear-gradient(135deg, #ffffff 0%, #fafafa 100%)', border: '#e2e8f0' },
      { label: 'Menunggu DP', value: LP.number(jumlah('menunggu dp')), hint: 'Perlu pembayaran awal', bg: 'linear-gradient(135deg, #fefce8 0%, #fef9c3 100%)', border: '#fde047' },
      { label: 'Nilai Booking', value: LP.shortMoney(nilai('total_price')), hint: 'Akumulasi harga unit dipesan', bg: 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)', border: '#bbf7d0' },
      { label: 'Sisa Pembayaran', value: LP.shortMoney(nilai('remaining_amount')), hint: 'Belum terverifikasi', bg: 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)', border: '#bae6fd' },
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

  function bookingRender() {
    bookingView = bookingFiltered();

    document.getElementById('booking-count').textContent =
      bookingView.length + ' booking ditampilkan dari ' + bookingAll.length + ' data booking.';

    const bolehKelola = LP.can(['super_admin', 'sales']);

    LP.table({
      mount: 'booking-table',
      rows: bookingView,
      empty: 'Belum ada booking yang cocok dengan kata kunci atau filter status.',
      onRow: (row) => bookingOpenDetail(row.id),
      columns: [
        { label: 'Kode Booking', render: (row) => '<span class="font-semibold text-slate-900">' + LP.esc(row.booking_code) + '</span>' },
        { label: 'Tanggal Booking', render: (row) => LP.date(row.booking_date) },
        {
          label: 'Konsumen',
          render: (row) => '<span class="font-medium text-slate-900">' + LP.esc(bookingCustomerName(row)) + '</span>'
            + '<span class="block text-xs text-slate-500">' + LP.esc(row.sales_profile ? row.sales_profile.sales_code : '-') + '</span>',
        },
        { label: 'Unit', render: (row) => LP.esc(bookingUnitLabel(row)) },
        { label: 'Skema', render: (row) => LP.esc(bookingSchemeName(row)) },
        { label: 'Total Harga', align: 'right', render: (row) => LP.money(row.total_price) },
        { label: 'Biaya Tambahan', align: 'right', render: (row) => LP.money(row.addition_cost || 0) },
        { label: 'Uang Muka', align: 'right', render: (row) => LP.money(row.down_payment_amount) },
        { label: 'Sisa', align: 'right', render: (row) => LP.money(row.remaining_amount) },
        { label: 'Status', render: (row) => LP.badge(row.status) },
        { label: 'Serah Terima', render: (row) => LP.badge(row.delivery_status) },
        {
          label: 'Aksi',
          render: (row, index) => '<div class="flex flex-wrap gap-1.5" data-stop>'
            + '<button type="button" data-booking-action="detail" data-index="' + index + '" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Detail</button>'
            + (bolehKelola
              ? '<button type="button" data-booking-action="delivery" data-index="' + index + '" class="rounded-lg border border-sky-200 bg-white px-2.5 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Serah Terima</button>'
              : '')
            + (bolehKelola && bookingBolehBatalkan(row)
              ? '<button type="button" data-booking-action="cancel" data-index="' + index + '" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Batalkan</button>'
              : '')
            + '</div>',
        },
      ],
    });
  }

  // Revisi 5: pemesanan yang masih berjalan dapat dibatalkan; yang sudah lunas
  // atau selesai serah terima tetap dapat dibatalkan dan konsumen berpindah ke
  // kategori Dibatalkan beserta riwayat pembayarannya.
  function bookingBolehBatalkan(row) {
    return row.status !== 'dibatalkan';
  }

  // Revisi 18: hanya calon konsumen berstatus hot yang dapat dibooking, sesuai
  // alur follow up sales di Database Konsumen.
  function bookingLeadsHot() {
    return bookingLeads.filter((item) => item.status === 'hot');
  }

  function bookingLeadOptions() {
    return bookingLeadsHot().map((item) => ({
      value: item.id,
      label: item.name + ' — ' + (item.phone || 'tanpa telepon'),
    }));
  }

  // Revisi 42: pilihan booking berjenjang — proyek (cluster) lebih dahulu, baru unit.
  function bookingProjectOptions() {
    const nama = new Map();
    bookingUnits.forEach((item) => {
      if (item.cluster) nama.set(String(item.cluster_id), item.cluster.name);
    });

    return [...nama.entries()]
      .map(([value, label]) => ({ value, label }))
      .sort((a, b) => a.label.localeCompare(b.label, 'id'));
  }

  function bookingUnitSelectOptions(clusterId) {
    return bookingUnits
      .filter((item) => String(item.cluster_id) === String(clusterId))
      .map((item) => ({
        value: item.id,
        label: 'Blok ' + item.unit_code + ' — ' + item.unit_type + ' — ' + LP.money(item.price),
      }));
  }

  // Opsi unit ditulis ulang setiap proyek berganti, jadi hanya unit pada proyek
  // terpilih — dan hanya yang belum dibooking — yang dapat dipilih.
  function bookingPaintUnits(form, clusterId) {
    const select = form.querySelector('[name=unit_id]');
    const pilihan = clusterId ? bookingUnitSelectOptions(clusterId) : [];
    const kosong = clusterId ? 'Semua unit pada proyek ini sudah dibooking' : 'Pilih proyek lebih dahulu';

    select.innerHTML = '<option value="">' + LP.esc(pilihan.length ? 'Pilih unit dan bloknya' : kosong) + '</option>'
      + pilihan.map((item) => '<option value="' + LP.esc(item.value) + '">' + LP.esc(item.label) + '</option>').join('');
  }

  function bookingSchemeOptions() {
    return bookingSchemes.map((item) => {
      const label = item.scheme_name + ' (DP ' + LP.number(item.down_payment_percent) + ' persen, '
        + LP.number(item.installment_months) + ' bulan)';
      return { value: item.id, label: item.is_active === false ? label + ' — nonaktif' : label };
    });
  }

  function bookingIsSalesRole() {
    return bookingUser.role === 'sales';
  }

  function bookingOwnSalesId() {
    const profil = bookingUser.sales_profile || bookingUser.salesProfile;
    return profil ? profil.id : null;
  }

  function bookingCreateFields() {
    const fields = [
      { name: 'lead_id', label: 'Konsumen', type: 'select', required: true, options: bookingLeadOptions(), placeholder: 'Pilih konsumen berstatus Hot' },
      // Revisi 42: proyek dipilih lebih dahulu, opsi unit mengikuti proyek tersebut.
      { name: 'cluster_id', label: 'Proyek', type: 'select', required: true, options: bookingProjectOptions(), placeholder: 'Pilih proyek' },
      { name: 'unit_id', label: 'Unit Yang Dipesan', type: 'select', required: true, options: [], placeholder: 'Pilih proyek lebih dahulu' },
      { name: 'payment_scheme_id', label: 'Skema Pembayaran', type: 'select', required: true, options: bookingSchemeOptions(), placeholder: 'Pilih skema' },
      { name: 'booking_date', label: 'Tanggal Booking', type: 'date', required: true },
      // Revisi 18: sales memilih dahulu ada/tidaknya permintaan tambahan bangunan.
      {
        name: 'addition_requested',
        label: 'Permintaan Tambahan Bangunan',
        type: 'select',
        required: true,
        col: 2,
        options: [
          { value: 'tidak', label: 'Tidak ada permintaan tambahan' },
          { value: 'ya', label: 'Ada permintaan tambahan bangunan' },
        ],
        hint: 'Pilih "ada" untuk mengisi keterangan pekerjaan tambahan beserta biayanya.',
      },
      // Revisi 15: permintaan pekerjaan tambahan dicatat terpisah dari harga utama unit.
      { name: 'addition_description', label: 'Tambahan Unit (Keterangan)', type: 'text', col: 2, placeholder: 'Contoh: kanopi carport, upgrade granit, satu sekat ruangan' },
      { name: 'addition_cost', label: 'Biaya Tambahan Konstruksi (Rp)', type: 'number', min: 0, step: 'any', placeholder: '0', hint: 'Di luar harga utama perumahan; tidak mengubah uang muka maupun angsuran.' },
      // Revisi 43: pembayaran pertama boleh langsung dicatat; nominalnya diambil dari
      // simulasi uang muka/angsuran atau biaya tambahan dan tetap boleh diubah.
      {
        name: 'initial_payment_requested',
        label: 'Catat Pembayaran Sekarang',
        type: 'select',
        required: true,
        col: 2,
        options: [
          { value: 'tidak', label: 'Belum ada pembayaran saat booking dibuat' },
          { value: 'ya', label: 'Ada pembayaran saat booking dibuat' },
        ],
        hint: 'Pilih "ada" untuk mengisi kategori DP, cicilan, atau biaya tambahan beserta nominalnya.',
      },
      { name: 'initial_category', label: 'Kategori Pembayaran', type: 'select', options: PAYMENT_CATEGORY_LIST, placeholder: 'Pilih kategori' },
      { name: 'initial_amount', label: 'Nominal Pembayaran (Rp)', type: 'number', min: 0.01, step: 'any', placeholder: '0', hint: 'Terisi otomatis dari simulasi, boleh diubah.' },
      { name: 'initial_date', label: 'Tanggal Pembayaran', type: 'date' },
      { name: 'initial_method', label: 'Metode Pembayaran', type: 'select', options: PAYMENT_METHOD_LIST },
    ];

    if (!bookingIsSalesRole()) {
      fields.push({
        name: 'sales_id',
        label: 'Sales Pemegang Booking',
        type: 'select',
        required: true,
        options: bookingSalesOptions,
        placeholder: 'Pilih sales',
        hint: 'Terisi otomatis mengikuti sales penanganan konsumen.',
      });
    }

    fields.push({ name: 'notes', label: 'Catatan Booking', type: 'textarea', rows: 3, col: 2, placeholder: 'Kesepakatan dengan konsumen, contoh: DP dibayar setelah pencairan kredit.' });

    return fields;
  }

  function bookingPreviewBox(form) {
    let box = form.querySelector('[data-booking-simulasi]');
    if (box) return box;
    box = document.createElement('div');
    box.className = 'sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600';
    box.setAttribute('data-booking-simulasi', 'ya');
    box.innerHTML = SIMULASI_KOSONG;
    form.querySelector('.grid').appendChild(box);
    return box;
  }

  async function bookingSimulate(form) {
    const unitId = form.querySelector('[name=unit_id]').value;
    const schemeId = form.querySelector('[name=payment_scheme_id]').value;
    const box = bookingPreviewBox(form);

    bookingSimulasi = null;

    if (!unitId || !schemeId) {
      box.innerHTML = SIMULASI_KOSONG;
      return;
    }

    box.innerHTML = '<span class="inline-flex items-center gap-2 text-slate-500">'
      + '<span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-800"></span> Menghitung simulasi pembayaran...</span>';

    try {
      const hasil = await LP.http.post('/payment-schemes/' + schemeId + '/simulate', { unit_id: Number(unitId) });
      const data = (hasil.json && hasil.json.data) || {};
      const unit = bookingUnits.find((item) => String(item.id) === String(unitId));

      bookingSimulasi = data;
      bookingSuggestPembayaran(form);

      box.innerHTML = '<p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Simulasi '
        + LP.esc(data.scheme_name || 'skema pembayaran') + (unit ? ' — unit ' + LP.esc(unit.unit_code) : '') + '</p>'
        + '<div class="mt-3 grid gap-3 sm:grid-cols-4">'
        + '<div><p class="text-xs text-slate-500">Uang Muka</p><p class="mt-1 font-semibold text-slate-900">' + LP.esc(LP.money(data.down_payment_amount)) + '</p></div>'
        + '<div><p class="text-xs text-slate-500">Angsuran per Bulan</p><p class="mt-1 font-semibold text-slate-900">' + LP.esc(LP.money(data.installment_amount)) + '</p></div>'
        + '<div><p class="text-xs text-slate-500">Tenor</p><p class="mt-1 font-semibold text-slate-900">' + LP.esc(LP.number(data.installment_months)) + ' bulan</p></div>'
        + '<div><p class="text-xs text-slate-500">Total Bunga</p><p class="mt-1 font-semibold text-slate-900">' + LP.esc(LP.money(data.interest_total)) + '</p></div>'
        + '</div>'
        + '<p class="mt-3 text-xs text-slate-500">Nilai di atas dihitung sistem dari harga unit dan akan menjadi dasar uang muka serta angsuran booking.</p>'
        + bookingAdditionNote(form);
    } catch (error) {
      box.innerHTML = '<p class="text-rose-700">Simulasi gagal dihitung: ' + LP.esc(error.message || 'terjadi kesalahan.') + '</p>';
    }
  }

  // Revisi 15: biaya tambahan konstruksi ditampilkan sebagai catatan terpisah karena
  // berada di luar harga utama unit dan tidak ikut menghitung uang muka atau angsuran.
  function bookingAdditionNote(form) {
    const biaya = Number((form.querySelector('[name=addition_cost]') || {}).value || 0);
    if (!biaya) return '';

    const permintaan = ((form.querySelector('[name=addition_description]') || {}).value || '').trim();

    return '<p data-catatan-tambahan class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Biaya tambahan konstruksi '
      + LP.esc(LP.money(biaya)) + (permintaan ? ' untuk ' + LP.esc(permintaan) : '')
      + ' berada di luar harga utama unit dan tidak mengubah simulasi di atas.</p>';
  }

  // Revisi 18: isian permintaan tambahan hanya tampil bila sales memilih "ada".
  function bookingToggleTambahan(form) {
    const select = form.querySelector('[name=addition_requested]');
    const ada = select && select.value === 'ya';

    ['addition_description', 'addition_cost'].forEach((nama) => {
      const input = form.querySelector('[name=' + nama + ']');
      if (!input) return;
      input.closest('label').classList.toggle('hidden', !ada);
      if (!ada) input.value = '';
    });

    const catatan = form.querySelector('[data-catatan-tambahan]');
    if (catatan) catatan.outerHTML = bookingAdditionNote(form);
  }

  // Revisi 43: blok pembayaran hanya tampil bila pembayaran dicatat bersama booking.
  function bookingTogglePembayaran(form) {
    const select = form.querySelector('[name=initial_payment_requested]');
    const ada = select && select.value === 'ya';

    ['initial_category', 'initial_amount', 'initial_date', 'initial_method'].forEach((nama) => {
      const input = form.querySelector('[name=' + nama + ']');
      if (!input) return;
      input.closest('label').classList.toggle('hidden', !ada);
    });

    if (ada) bookingSuggestPembayaran(form);
  }

  // Nominal disarankan dari simulasi uang muka/angsuran atau dari biaya tambahan,
  // tergantung kategori yang dipilih; nilai yang muncul masih boleh diubah sales.
  function bookingSuggestPembayaran(form) {
    const kategori = form.querySelector('[name=initial_category]').value;
    const input = form.querySelector('[name=initial_amount]');

    if (!kategori) return;

    const saran = kategori === 'biaya tambahan'
      ? Number(form.querySelector('[name=addition_cost]').value || 0)
      : Number((bookingSimulasi || {})[kategori === 'dp' ? 'down_payment_amount' : 'installment_amount'] || 0);

    if (saran > 0) {
      input.value = saran;
    } else {
      // Kategori tanpa saran nominal (misalnya biaya tambahan yang belum diisi)
      // mengosongkan isian supaya nominal lama dari kategori lain tidak ikut tercatat.
      input.value = '';
    }
  }

  function bookingSyncSales(form) {
    const selectSales = form.querySelector('[name=sales_id]');
    if (!selectSales) return;
    const leadId = form.querySelector('[name=lead_id]').value;
    const lead = bookingLeads.find((item) => String(item.id) === String(leadId));
    if (lead && lead.sales_id) selectSales.value = String(lead.sales_id);
  }

  async function bookingCreate(values, context) {
    const adaTambahan = values.addition_requested === 'ya';
    const keterangan = String(values.addition_description || '').trim();

    if (adaTambahan && !keterangan) {
      throw new Error('Keterangan tambahan bangunan wajib diisi bila ada permintaan tambahan.');
    }

    const body = {
      lead_id: Number(values.lead_id),
      unit_id: Number(values.unit_id),
      payment_scheme_id: Number(values.payment_scheme_id),
      booking_date: values.booking_date,
      addition_cost: adaTambahan ? Number(values.addition_cost || 0) : 0,
      addition_description: adaTambahan ? keterangan : null,
      notes: values.notes,
      sales_id: bookingIsSalesRole() ? bookingOwnSalesId() : Number(values.sales_id),
    };

    if (!body.sales_id) throw new Error('Akun Anda belum memiliki profil sales. Hubungi admin untuk melengkapi data sales.');

    // Revisi 43: blok pembayaran ikut terkirim bila sales memilih mencatat pembayaran.
    if (values.initial_payment_requested === 'ya') {
      const nominal = Number(values.initial_amount || 0);

      if (!values.initial_category) throw new Error('Kategori pembayaran wajib dipilih bila mencatat pembayaran saat booking.');
      if (!(nominal > 0)) throw new Error('Nominal pembayaran harus lebih besar dari nol.');
      if (!values.initial_date) throw new Error('Tanggal pembayaran wajib diisi.');

      body.initial_payment = {
        payment_category: values.initial_category,
        amount: nominal,
        payment_date: values.initial_date,
        payment_method: values.initial_method,
      };
    }

    const hasil = await LP.http.post('/bookings', body);
    const kode = hasil.json && hasil.json.data ? hasil.json.data.booking_code : '';
    LP.toast((hasil.json && hasil.json.message) || 'Booking berhasil dibuat.' + (kode ? ' Nomor ' + kode : ''));
    context.close();
    await bookingLoad();
  }

  // Revisi 42: konsumen dan unit dimuat ulang setiap form dibuka supaya unit yang baru
  // saja dibooking otomatis hilang dari pilihan.
  async function bookingRefreshChoices() {
    try {
      const [leads, units] = await Promise.all([LP.fetchJson('/customer-leads'), LP.fetchJson('/units-available')]);
      bookingLeads = Array.isArray(leads) ? leads : [];
      bookingUnits = Array.isArray(units) ? units : [];
    } catch (error) {
      LP.toast('Pilihan booking gagal dimuat ulang: ' + error.message, 'error');
    }
  }

  async function bookingOpenCreate() {
    await bookingRefreshChoices();

    if (!bookingLeadsHot().length) {
      LP.toast('Belum ada calon konsumen berstatus Hot. Ubah status calon konsumen menjadi Hot lebih dahulu pada menu Database Konsumen.', 'error');
      return;
    }

    if (!bookingUnits.length || !bookingSchemes.length) {
      LP.toast('Unit tersedia atau skema pembayaran belum lengkap untuk membuat booking.', 'error');
      return;
    }

    const modal = LP.formModal({
      title: 'Buat Booking Unit',
      description: 'Pilih konsumen berstatus Hot, lalu proyek dan unit yang masih tersedia, kemudian skema pembayaran. Simulasi muncul otomatis sebelum disimpan.',
      fields: bookingCreateFields(),
      values: { booking_date: LP.today(), initial_date: LP.today(), initial_method: 'transfer bank', addition_requested: 'tidak', initial_payment_requested: 'tidak' },
      submitLabel: 'Simpan Booking',
      width: 'max-w-4xl',
      onSubmit: bookingCreate,
    });

    const form = modal.wrap.querySelector('form');
    bookingPreviewBox(form);
    bookingPaintUnits(form, '');
    bookingToggleTambahan(form);
    bookingTogglePembayaran(form);

    const selectProyek = form.querySelector('[name=cluster_id]');
    selectProyek.addEventListener('change', () => {
      bookingPaintUnits(form, selectProyek.value);
      bookingSimulate(form);
    });

    form.querySelector('[name=unit_id]').addEventListener('change', () => bookingSimulate(form));
    form.querySelector('[name=payment_scheme_id]').addEventListener('change', () => bookingSimulate(form));

    const pilihTambahan = form.querySelector('[name=addition_requested]');
    if (pilihTambahan) pilihTambahan.addEventListener('change', () => bookingToggleTambahan(form));

    const inputBiayaTambahan = form.querySelector('[name=addition_cost]');
    if (inputBiayaTambahan) inputBiayaTambahan.addEventListener('change', () => bookingToggleTambahan(form));

    const pilihBayar = form.querySelector('[name=initial_payment_requested]');
    if (pilihBayar) pilihBayar.addEventListener('change', () => bookingTogglePembayaran(form));

    form.querySelector('[name=initial_category]').addEventListener('change', () => bookingSuggestPembayaran(form));

    const selectLead = form.querySelector('[name=lead_id]');
    if (selectLead) selectLead.addEventListener('change', () => bookingSyncSales(form));
    bookingSyncSales(form);
  }

  function bookingDialog(options) {
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-16';
    wrap.innerHTML = '<div role="dialog" aria-modal="true" aria-label="' + LP.esc(options.title) + '" class="w-full '
      + (options.width || 'max-w-3xl') + ' rounded-2xl bg-white shadow-xl">'
      + '<div class="flex items-start justify-between gap-4 border-b border-slate-100 p-5">'
      + '<div><h3 class="text-lg font-semibold text-slate-900">' + LP.esc(options.title) + '</h3>'
      + (options.description ? '<p class="mt-1 text-sm text-slate-500">' + LP.esc(options.description) + '</p>' : '')
      + '</div>'
      + '<button type="button" data-close class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Tutup</button>'
      + '</div><div data-body class="p-5"></div></div>';
    document.body.appendChild(wrap);

    const close = () => wrap.remove();
    wrap.addEventListener('click', (event) => {
      if (event.target === wrap || event.target.closest('[data-close]')) close();
    });
    document.addEventListener('keydown', function tutupEscape(event) {
      if (event.key === 'Escape') {
        close();
        document.removeEventListener('keydown', tutupEscape);
      }
    });

    return { close, body: wrap.querySelector('[data-body]') };
  }

  function bookingDetailRow(label, value) {
    return '<div class="flex items-start justify-between gap-4 border-b border-slate-100 py-2 last:border-b-0">'
      + '<span class="text-xs font-semibold uppercase tracking-wider text-slate-500">' + LP.esc(label) + '</span>'
      + '<span class="text-right text-sm text-slate-800">' + value + '</span></div>';
  }

  async function bookingOpenDetail(id) {
    const dialog = bookingDialog({ title: 'Detail Booking', description: 'Rincian pemesanan beserta catatan pembayaran terkait.' });
    LP.loading(dialog.body, 'Memuat detail booking...');

    let data;
    try {
      data = await LP.fetchJson('/bookings/' + id);
    } catch (error) {
      LP.errorBox(dialog.body, error.message || 'Detail booking gagal dimuat.');
      return;
    }

    const lead = bookingLead(data) || {};
    const unit = bookingUnit(data) || {};
    const profil = data.sales_profile || {};
    const riwayat = Array.isArray(data.payments) ? data.payments : [];

    const ringkasan = '<div class="grid gap-4 lg:grid-cols-2">'
      + '<div class="rounded-xl border border-slate-200 p-4">'
      + bookingDetailRow('Kode Booking', '<span class="font-semibold">' + LP.esc(data.booking_code) + '</span>')
      + bookingDetailRow('Tanggal Booking', LP.esc(LP.date(data.booking_date)))
      + bookingDetailRow('Status', LP.badge(data.status))
      + bookingDetailRow('Serah Terima', LP.badge(data.delivery_status))
      + bookingDetailRow('Status Pelunasan', LP.esc(data.settlement_status || data.status))
      + (data.status === 'dibatalkan'
        ? bookingDetailRow('Waktu Pembatalan', LP.esc(LP.datetime(data.cancelled_at)))
          + bookingDetailRow('Alasan Pembatalan', LP.esc(data.cancellation_reason || 'Tanpa alasan tercatat'))
        : '')
      + '</div>'
      + '<div class="rounded-xl border border-slate-200 p-4">'
      + bookingDetailRow('Konsumen', LP.esc(lead.name || '-') + '<span class="block text-xs text-slate-500">' + LP.esc(lead.phone || '') + '</span>')
      + bookingDetailRow('Unit', LP.esc(unit.unit_code || '-') + '<span class="block text-xs text-slate-500">' + LP.esc(unit.unit_type || '') + '</span>')
      + bookingDetailRow('Proyek', LP.esc(unit.cluster ? unit.cluster.name : '-'))
      + bookingDetailRow('Skema', LP.esc(data.payment_scheme ? data.payment_scheme.scheme_name : '-'))
      + bookingDetailRow('Sales', LP.esc(profil.user ? profil.user.name : '-') + ' (' + LP.esc(profil.sales_code || '-') + ')')
      + '</div></div>';

    const nominal = '<div class="mt-4 grid gap-3 sm:grid-cols-5">'
      + LP.statCard({ label: 'Total Harga', value: LP.money(data.total_price), tone: 'zinc' })
      + LP.statCard({ label: 'Uang Muka', value: LP.money(data.down_payment_amount), tone: 'emerald' })
      + LP.statCard({ label: 'Angsuran', value: LP.money(data.installment_amount), tone: 'violet' })
      + LP.statCard({ label: 'Sisa Pembayaran', value: LP.money(data.remaining_amount), tone: 'amber' })
      + LP.statCard({ label: 'Biaya Tambahan', value: LP.money(data.addition_cost || 0), tone: 'sky', hint: 'Di luar harga utama unit' })
      + '</div>'
      + (Number(data.addition_cost || 0) || data.addition_description
        ? '<p class="mt-3 rounded-xl bg-sky-50 p-4 text-sm text-sky-900">Request tambahan bangunan: '
          + LP.esc(data.addition_description || 'tanpa keterangan') + ' — ' + LP.esc(LP.money(data.addition_cost || 0))
          + '. Biaya ini dicatat terpisah sehingga uang muka, angsuran, dan sisa pembayaran tetap mengikuti harga utama unit.</p>'
        : '');

    const catatan = data.notes
      ? '<p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">' + LP.esc(data.notes) + '</p>'
      : '';

    const tabelBayar = riwayat.length ? '<p class="mt-2 text-xs text-slate-500">Nominal di bawah adalah pembayaran yang sudah tercatat pada booking ini.</p>' : '';

    dialog.body.innerHTML = '<div>' + ringkasan + nominal + catatan + tabelBayar
      + '<h4 class="mt-5 text-sm font-semibold text-slate-900">Riwayat Pembayaran (' + riwayat.length + ')</h4>'
      + (riwayat.length
        ? '<div id="booking-detail-payments" class="mt-3"></div>'
        : '<p class="mt-2 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada pembayaran tercatat untuk booking ini.</p>')
      + '</div>';

    if (riwayat.length) {
      LP.table({
        mount: 'booking-detail-payments',
        rows: riwayat,
        empty: 'Belum ada pembayaran.',
        columns: [
          { label: 'Tanggal', render: (row) => LP.date(row.payment_date) },
          { label: 'Jenis', render: (row) => LP.esc(row.payment_type) },
          // Revisi 43: kategori menunjukkan uang ini dibayarkan untuk apa.
          { label: 'Kategori', render: (row) => LP.esc(bookingCategoryLabel(row.payment_category)) },
          { label: 'Metode', render: (row) => LP.esc(row.payment_method || '-') },
          { label: 'Nominal', align: 'right', render: (row) => LP.money(row.amount) },
          { label: 'Bank', render: (row) => LP.esc(row.bank_name || '-') },
          { label: 'Verifikasi', render: (row) => LP.badge(row.verification_status) },
          { label: 'Verifikator', render: (row) => LP.esc(row.verifier ? row.verifier.name : 'Belum diverifikasi') },
        ],
      });
    }
  }

  function bookingOpenDelivery(row) {
    LP.formModal({
      title: 'Status Serah Terima — ' + row.booking_code,
      description: 'Perbarui proses serah terima unit setelah pembayaran lunas.',
      fields: [{
        name: 'delivery_status',
        label: 'Status Serah Terima',
        type: 'select',
        required: true,
        options: DELIVERY_LIST,
        col: 2,
      }],
      values: { delivery_status: row.delivery_status || 'belum proses' },
      submitLabel: 'Simpan Status',
      width: 'max-w-lg',
      onSubmit: async (values, context) => {
        const hasil = await LP.http.put('/bookings/' + row.id, { delivery_status: values.delivery_status });
        LP.toast((hasil.json && hasil.json.message) || 'Status serah terima booking diperbarui.');
        context.close();
        await bookingLoad();
      },
    });
  }

  function bookingOpenCancel(row) {
    LP.formModal({
      title: 'Batalkan Booking — ' + row.booking_code,
      description: 'Unit kembali tersedia dan konsumen keluar dari daftar Konsumen menjadi kategori Dibatalkan. '
        + 'Pembatalan juga diterima untuk booking yang sudah lunas atau selesai serah terima, dan pembayaran yang sudah tercatat tetap tersimpan sebagai riwayat pembelian.',
      fields: [{
        name: 'reason',
        label: 'Alasan Pembatalan',
        type: 'textarea',
        rows: 3,
        col: 2,
        required: true,
        placeholder: 'Contoh: konsumen membatalkan karena pengajuan KPR tidak disetujui bank.',
        hint: 'Tercatat pada riwayat booking dan dapat dilihat kembali di halaman detail konsumen.',
      }, {
        name: 'cancelled_at',
        label: 'Tanggal Pembatalan',
        type: 'date',
        required: true,
        hint: 'Tanggal konsumen menyampaikan pembatalan.',
      }],
      values: { cancelled_at: LP.today() },
      submitLabel: 'Ya, Batalkan Booking',
      width: 'max-w-lg',
      onSubmit: async (values, context) => {
        const alasan = String(values.reason || '').trim();
        if (alasan.length < 5) throw new Error('Alasan pembatalan minimal 5 karakter.');

        const hasil = await LP.http.patch('/bookings/' + row.id + '/cancel', {
          reason: alasan,
          cancelled_at: values.cancelled_at,
        });
        LP.toast((hasil.json && hasil.json.message) || 'Booking berhasil dibatalkan.');
        context.close();
        await bookingLoad();
      },
    });
  }

  async function bookingLoad() {
    LP.loading('booking-table', 'Memuat data booking...');
    document.getElementById('booking-count').textContent = 'Memuat data booking...';

    try {
      const daftar = await LP.fetchJson('/bookings');
      bookingAll = Array.isArray(daftar) ? daftar : [];
    } catch (error) {
      LP.errorBox('booking-table', error.message || 'Data booking gagal dimuat.');
      document.getElementById('booking-count').textContent = 'Data gagal dimuat.';
      return;
    }

    bookingRenderStats();
    bookingRender();
  }

  LP.guard(ROLE_INTERNAL, async (user) => {
    bookingUser = user;

    const permintaan = [LP.fetchJson('/customer-leads'), LP.fetchJson('/units-available'), LP.fetchJson('/payment-schemes')];
    if (!bookingIsSalesRole()) permintaan.push(LP.fetchJson('/sales'));

    try {
      const [leads, units, schemes, daftarSales] = await Promise.all(permintaan);
      bookingLeads = Array.isArray(leads) ? leads : [];
      bookingUnits = Array.isArray(units) ? units : [];
      bookingSchemes = Array.isArray(schemes) ? schemes : [];
      if (daftarSales) {
        bookingSalesOptions = (Array.isArray(daftarSales) ? daftarSales : [])
          .filter((item) => item.sales_profile)
          .map((item) => ({ value: item.sales_profile.id, label: item.name + ' (' + item.sales_profile.sales_code + ')' }));
      }
    } catch (error) {
      LP.toast('Sebagian data pilihan booking gagal dimuat: ' + error.message, 'error');
    }

    if (LP.can(['super_admin', 'sales'])) {
      document.getElementById('btn-buat-booking').addEventListener('click', bookingOpenCreate);
    } else {
      document.getElementById('btn-buat-booking').classList.add('hidden');
    }

    const renderDebounced = LP.debounce(bookingRender);
    bookingSearch.addEventListener('input', renderDebounced);
    bookingStatusFilter.addEventListener('change', bookingRender);
    bookingDeliveryFilter.addEventListener('change', bookingRender);

    document.getElementById('booking-table').addEventListener('click', (event) => {
      const tombol = event.target.closest('button[data-booking-action]');
      if (!tombol) return;
      const baris = bookingView[Number(tombol.dataset.index)];
      if (!baris) return;
      if (tombol.dataset.bookingAction === 'detail') bookingOpenDetail(baris.id);
      else if (tombol.dataset.bookingAction === 'cancel') bookingOpenCancel(baris);
      else bookingOpenDelivery(baris);
    });

    await bookingLoad();
  });
</script>
@endpush