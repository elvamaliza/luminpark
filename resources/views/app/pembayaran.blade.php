@extends('layouts.app')

@section('title', 'Data Pembayaran')
@section('page_title', 'Data Pembayaran')
@section('page_desc', 'Catat pembayaran konsumen, pantau bukti transfer, dan verifikasi pembayaran untuk mengubah status pelunasan booking.')

@section('page_actions')
    <a href="/booking" class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Data Booking</a>
    <button id="btn-bayar" type="button" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Catat Pembayaran
    </button>
@endsection

@section('content')
    <div id="payment-stats" class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"></div>

    <section class="mb-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Cari Pembayaran</span>
                <input id="payment-search" type="search" class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10" placeholder="kode booking atau nama konsumen">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status Verifikasi</span>
                <select id="payment-verification" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu Verifikasi</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Jenis Pembayaran</span>
                <select id="payment-type" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                    <option value="">Semua Jenis</option>
                    <option value="KPR">KPR</option>
                    <option value="cash keras">Cash Keras</option>
                    <option value="cash bertahap">Cash Bertahap</option>
                </select>
            </label>
            <p id="payment-count" class="self-end text-sm text-zinc-500">Memuat data pembayaran...</p>
        </div>
    </section>

    <div id="payment-table"></div>

    <p id="payment-note" class="mt-4 text-xs text-zinc-500">
        Verifikasi dan penolakan bukti transfer hanya dapat dilakukan Manajer atau Super Admin.
        Setiap pembayaran yang belum terverifikasi tidak mengubah sisa pembayaran booking.
    </p>
@endsection

@push('scripts')
<script>
  const ROLE_INTERNAL = ['manager', 'admin_sales', 'sales'];
  const ROLE_VERIFIKATOR = ['manager'];

  const PAYMENT_TYPES = [
    { value: 'KPR', label: 'KPR' },
    { value: 'cash keras', label: 'Cash Keras' },
    { value: 'cash bertahap', label: 'Cash Bertahap' },
  ];

  const paymentSearch = document.getElementById('payment-search');
  const paymentVerificationFilter = document.getElementById('payment-verification');
  const paymentTypeFilter = document.getElementById('payment-type');

  let paymentAll = [];
  let paymentView = [];
  let paymentBookings = [];
  let paymentUser = null;

  function paymentBooking(row) {
    return row.booking || null;
  }

  function paymentBookingCode(row) {
    const booking = paymentBooking(row);
    return booking ? booking.booking_code : 'Booking #' + row.booking_id;
  }

  function paymentCustomer(row) {
    const booking = paymentBooking(row);
    const lead = booking ? (booking.customer_lead || booking.customerLead) : null;
    return lead && lead.name ? lead.name : '-';
  }

  function paymentTypeLabel(nilai) {
    const ditemukan = PAYMENT_TYPES.find((item) => item.value === nilai);
    return ditemukan ? ditemukan.label : (nilai || '-');
  }

  function paymentCanVerify() {
    return paymentUser && ROLE_VERIFIKATOR.indexOf(paymentUser.role) !== -1;
  }

  function paymentFiltered() {
    const kata = (paymentSearch.value || '').trim().toLowerCase();
    const verifikasi = paymentVerificationFilter.value;
    const jenis = paymentTypeFilter.value;

    return paymentAll.filter((row) => {
      const haystack = [paymentBookingCode(row), paymentCustomer(row), row.bank_name, row.account_holder].join(' ').toLowerCase();
      if (kata && haystack.indexOf(kata) === -1) return false;
      if (verifikasi && row.verification_status !== verifikasi) return false;
      if (jenis && row.payment_type !== jenis) return false;
      return true;
    });
  }

  function paymentRenderStats() {
    const menunggu = paymentAll.filter((row) => row.verification_status === 'pending');
    const terverifikasi = paymentAll.filter((row) => row.verification_status === 'verified');
    const ditolak = paymentAll.filter((row) => row.verification_status === 'rejected').length;
    const totalTerverifikasi = terverifikasi.reduce((akumulasi, row) => akumulasi + Number(row.amount || 0), 0);

    document.getElementById('payment-stats').innerHTML = [
      LP.statCard({ label: 'Total Pembayaran', value: LP.number(paymentAll.length), tone: 'zinc' }),
      LP.statCard({ label: 'Menunggu Verifikasi', value: LP.number(menunggu.length), hint: 'Perlu tindakan manajer', tone: 'amber' }),
      LP.statCard({ label: 'Dana Terverifikasi', value: LP.shortMoney(totalTerverifikasi), hint: terverifikasi.length + ' pembayaran', tone: 'emerald' }),
      LP.statCard({ label: 'Ditolak', value: LP.number(ditolak), hint: 'Bukti transfer perlu diunggah ulang', tone: 'rose' }),
    ].join('');
  }

  function paymentRender() {
    paymentView = paymentFiltered();

    document.getElementById('payment-count').textContent =
      paymentView.length + ' pembayaran ditampilkan dari ' + paymentAll.length + ' catatan pembayaran.';

    document.getElementById('payment-note').classList.toggle('hidden', paymentCanVerify());

    LP.table({
      mount: 'payment-table',
      rows: paymentView,
      empty: 'Belum ada pembayaran yang cocok dengan kata kunci atau filter.',
      columns: [
        { label: 'Tanggal', render: (row) => LP.date(row.payment_date) },
        { label: 'Kode Booking', render: (row) => '<span class="font-semibold text-zinc-900">' + LP.esc(paymentBookingCode(row)) + '</span>' },
        { label: 'Konsumen', render: (row) => LP.esc(paymentCustomer(row)) },
        { label: 'Jenis', render: (row) => LP.esc(paymentTypeLabel(row.payment_type)) },
        { label: 'Nominal', align: 'right', render: (row) => LP.money(row.amount) },
        { label: 'Bank', render: (row) => LP.esc(row.bank_name || '-') },
        { label: 'Verifikasi', render: (row) => LP.badge(row.verification_status) },
        { label: 'Verifikator', render: (row) => LP.esc(row.verifier ? row.verifier.name : 'Belum diverifikasi') },
        {
          label: 'Aksi',
          render: (row, index) => {
            let tombol = '<div class="flex flex-wrap gap-1.5">'
              + '<button type="button" data-payment-action="detail" data-index="' + index + '"'
              + ' class="rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">Detail</button>';

            if (row.transfer_receipt) {
              tombol += '<a href="/storage/' + LP.esc(row.transfer_receipt) + '" target="_blank" rel="noopener noreferrer"'
                + ' class="rounded-lg border border-sky-200 bg-white px-2.5 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Bukti</a>';
            }

            if (paymentCanVerify()) {
              if (row.verification_status !== 'verified') {
                tombol += '<button type="button" data-payment-action="verify" data-index="' + index + '"'
                  + ' class="rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Verifikasi</button>';
              }
              if (row.verification_status !== 'rejected') {
                tombol += '<button type="button" data-payment-action="reject" data-index="' + index + '"'
                  + ' class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Tolak</button>';
              }
            }

            return tombol + '</div>';
          },
        },
      ],
    });
  }

  function paymentBookingOptions() {
    const terbuka = paymentBookings.filter((row) => row.status !== 'lunas');
    const daftar = terbuka.length ? terbuka : paymentBookings;

    return daftar.map((row) => {
      const lead = row.customer_lead || row.customerLead || {};
      return {
        value: row.id,
        label: row.booking_code + ' — ' + (lead.name || 'tanpa nama') + ' — sisa ' + LP.shortMoney(row.remaining_amount),
      };
    });
  }

  function paymentCreateFields() {
    return [
      { name: 'booking_id', label: 'Booking', type: 'select', required: true, options: paymentBookingOptions(), placeholder: 'Pilih booking' },
      { name: 'payment_type', label: 'Jenis Pembayaran', type: 'select', required: true, options: PAYMENT_TYPES.map((item) => ({ value: item.value, label: item.label })) },
      { name: 'amount', label: 'Nominal Pembayaran (Rp)', type: 'number', required: true, min: 0.01, step: 'any', hint: 'Gunakan nominal uang muka atau angsuran sesuai booking.' },
      { name: 'payment_date', label: 'Tanggal Pembayaran', type: 'date', required: true },
      { name: 'bank_name', label: 'Nama Bank', type: 'text', placeholder: 'Bank Central Asia' },
      { name: 'account_number', label: 'Nomor Rekening', type: 'text', placeholder: '8890122345' },
      { name: 'account_holder', label: 'Atas Nama Rekening', type: 'text', placeholder: 'PT Lumin Park Properti' },
      { name: 'transfer_receipt', label: 'Bukti Transfer', type: 'file', accept: 'image/*,application/pdf', col: 2, hint: 'JPG/PNG/PDF maks 2MB' },
      { name: 'notes', label: 'Catatan Pembayaran', type: 'textarea', rows: 3, col: 2, placeholder: 'Contoh: transfer angsuran ke-2 melalui mobile banking.' },
    ];
  }

  function paymentPreviewBox(form) {
    let box = form.querySelector('[data-payment-guidance]');
    if (box) return box;
    box = document.createElement('div');
    box.className = 'sm:col-span-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600';
    box.setAttribute('data-payment-guidance', 'ya');
    box.innerHTML = 'Pilih booking untuk melihat panduan nominal uang muka, angsuran, dan sisa pembayaran.';
    form.querySelector('.grid').appendChild(box);
    return box;
  }

  function paymentUpdatePreview(form) {
    const box = paymentPreviewBox(form);
    const bookingId = form.querySelector('[name=booking_id]').value;
    const booking = paymentBookings.find((row) => String(row.id) === String(bookingId));

    if (!booking) {
      box.innerHTML = 'Pilih booking untuk melihat panduan nominal uang muka, angsuran, dan sisa pembayaran.';
      return;
    }

    const selectType = form.querySelector('[name=payment_type]');
    const jenis = booking.payment_scheme ? booking.payment_scheme.scheme_type : '';
    if (jenis && PAYMENT_TYPES.some((item) => item.value === jenis)) selectType.value = jenis;

    box.innerHTML = '<p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Panduan Nominal Booking '
      + LP.esc(booking.booking_code) + '</p>'
      + '<div class="mt-3 grid gap-3 sm:grid-cols-4">'
      + '<div><p class="text-xs text-zinc-500">Total Harga</p><p class="mt-1 font-semibold text-zinc-900">' + LP.esc(LP.money(booking.total_price)) + '</p></div>'
      + '<div><p class="text-xs text-zinc-500">Uang Muka</p><p class="mt-1 font-semibold text-zinc-900">' + LP.esc(LP.money(booking.down_payment_amount)) + '</p></div>'
      + '<div><p class="text-xs text-zinc-500">Angsuran per Bulan</p><p class="mt-1 font-semibold text-zinc-900">' + LP.esc(LP.money(booking.installment_amount)) + '</p></div>'
      + '<div><p class="text-xs text-zinc-500">Sisa Pembayaran</p><p class="mt-1 font-semibold text-zinc-900">' + LP.esc(LP.money(booking.remaining_amount)) + '</p></div>'
      + '</div>'
      + '<p class="mt-3 text-xs text-zinc-500">Status booking: ' + LP.esc(booking.status) + '. Nominal akan tercatat menunggu verifikasi manajer.</p>';
  }

  async function paymentCreate(values, context) {
    const body = new FormData();
    body.append('booking_id', Number(values.booking_id));
    body.append('payment_type', values.payment_type);
    body.append('amount', Number(values.amount));
    body.append('payment_date', values.payment_date);
    if (values.bank_name) body.append('bank_name', values.bank_name);
    if (values.account_number) body.append('account_number', values.account_number);
    if (values.account_holder) body.append('account_holder', values.account_holder);
    if (values.notes) body.append('notes', values.notes);

    const bukti = values.__files ? values.__files.transfer_receipt : null;
    if (bukti) body.append('transfer_receipt', bukti);

    const hasil = await LP.http.post('/payments', body);
    LP.toast((hasil.json && hasil.json.message) || 'Pembayaran berhasil dicatat.');
    context.close();
    await paymentLoad();
    LP.loadUnreadBadge();
  }

  function paymentOpenCreate() {
    if (!paymentBookings.length) {
      LP.toast('Belum ada booking yang dapat dibayarkan. Buat booking terlebih dahulu.', 'error');
      return;
    }

    const modal = LP.formModal({
      title: 'Catat Pembayaran Baru',
      description: 'Unggah bukti transfer agar pembayaran dapat diverifikasi manajer.',
      fields: paymentCreateFields(),
      values: { payment_date: LP.today(), payment_type: 'cash bertahap' },
      submitLabel: 'Simpan Pembayaran',
      width: 'max-w-4xl',
      onSubmit: paymentCreate,
    });

    const form = modal.wrap.querySelector('form');
    paymentUpdatePreview(form);
    form.querySelector('[name=booking_id]').addEventListener('change', () => paymentUpdatePreview(form));
  }

  function paymentDialog(options) {
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-zinc-900/40 p-4 pt-16';
    wrap.innerHTML = '<div role="dialog" aria-modal="true" aria-label="' + LP.esc(options.title) + '" class="w-full '
      + (options.width || 'max-w-3xl') + ' rounded-2xl bg-white shadow-xl">'
      + '<div class="flex items-start justify-between gap-4 border-b border-zinc-100 p-5">'
      + '<div><h3 class="text-lg font-semibold text-zinc-900">' + LP.esc(options.title) + '</h3>'
      + (options.description ? '<p class="mt-1 text-sm text-zinc-500">' + LP.esc(options.description) + '</p>' : '')
      + '</div>'
      + '<button type="button" data-close class="rounded-lg border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50">Tutup</button>'
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

  function paymentDetailRow(label, value) {
    return '<div class="flex items-start justify-between gap-4 border-b border-zinc-100 py-2 last:border-b-0">'
      + '<span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + '</span>'
      + '<span class="text-right text-sm text-zinc-800">' + value + '</span></div>';
  }

  function paymentOpenDetail(row) {
    const booking = paymentBooking(row) || {};
    const lead = booking.customer_lead || booking.customerLead || {};
    const dialog = paymentDialog({
      title: 'Detail Pembayaran — ' + paymentBookingCode(row),
      description: 'Data pembayaran beserta bukti transfer dan hasil verifikasi.',
      width: 'max-w-2xl',
    });

    const bukti = row.transfer_receipt
      ? '<a class="mt-4 inline-flex rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white" target="_blank" rel="noopener noreferrer"'
        + ' href="/storage/' + LP.esc(row.transfer_receipt) + '">Buka Bukti Transfer</a>'
      : '<p class="mt-4 text-sm text-zinc-500">Tidak ada berkas bukti transfer terlampir.</p>';

    dialog.body.innerHTML = '<div class="rounded-xl border border-zinc-200 p-4">'
      + paymentDetailRow('Tanggal Pembayaran', LP.esc(LP.date(row.payment_date)))
      + paymentDetailRow('Jenis Pembayaran', LP.esc(paymentTypeLabel(row.payment_type)))
      + paymentDetailRow('Nominal', '<span class="font-semibold">' + LP.esc(LP.money(row.amount)) + '</span>')
      + paymentDetailRow('Konsumen', LP.esc(lead.name || '-'))
      + paymentDetailRow('Unit', LP.esc(booking.unit ? booking.unit.unit_code : '-'))
      + paymentDetailRow('Rekening', LP.esc([row.bank_name, row.account_number, row.account_holder].filter(Boolean).join(' / ') || '-'))
      + paymentDetailRow('Status Verifikasi', LP.badge(row.verification_status))
      + paymentDetailRow('Verifikator', LP.esc(row.verifier ? row.verifier.name + ' — ' + LP.datetime(row.verified_at) : 'Belum diverifikasi'))
      + paymentDetailRow('Catatan', row.notes ? LP.esc(row.notes) : 'Tidak ada catatan')
      + '</div>' + bukti;
  }

  function paymentOpenVerify(row, status) {
    const menolak = status === 'rejected';

    LP.formModal({
      title: menolak ? 'Tolak Pembayaran — ' + paymentBookingCode(row) : 'Verifikasi Pembayaran — ' + paymentBookingCode(row),
      description: 'Nominal ' + LP.money(row.amount) + ' atas nama ' + paymentCustomer(row) + '. '
        + (menolak
          ? 'Pembayaran yang ditolak tidak diperhitungkan sebagai pelunasan.'
          : 'Setelah diverifikasi, sisa pembayaran dan status booking akan dihitung ulang oleh sistem.'),
      fields: [{
        name: 'notes',
        label: 'Catatan Verifikasi',
        type: 'textarea',
        rows: 3,
        col: 2,
        required: menolak,
        placeholder: menolak ? 'Jelaskan alasan penolakan bukti transfer.' : 'Contoh: nominal dan waktu transfer sesuai.',
        hint: menolak ? 'Wajib diisi saat menolak pembayaran.' : 'Opsional, tersimpan pada riwayat pembayaran.',
      }],
      values: { notes: row.notes || '' },
      submitLabel: menolak ? 'Tolak Pembayaran' : 'Verifikasi Pembayaran',
      width: 'max-w-lg',
      onSubmit: async (values, context) => {
        const body = { verification_status: status };
        if (values.notes) body.notes = values.notes;

        const hasil = await LP.http.put('/payments/' + row.id + '/verify', body);
        LP.toast((hasil.json && hasil.json.message) || 'Status verifikasi pembayaran diperbarui.');
        context.close();
        await paymentLoad();
        await bookingRefreshAfterVerify();
        LP.loadUnreadBadge();
      },
    });
  }

  async function bookingRefreshAfterVerify() {
    try {
      const daftar = await LP.fetchJson('/bookings');
      paymentBookings = Array.isArray(daftar) ? daftar : paymentBookings;
    } catch (error) {
      LP.toast('Data booking gagal disinkronkan ulang: ' + error.message, 'info');
    }
  }

  async function paymentLoad() {
    LP.loading('payment-table', 'Memuat data pembayaran...');
    document.getElementById('payment-count').textContent = 'Memuat data pembayaran...';

    try {
      const daftar = await LP.fetchJson('/payments');
      paymentAll = Array.isArray(daftar) ? daftar : [];
    } catch (error) {
      LP.errorBox('payment-table', error.message || 'Data pembayaran gagal dimuat.');
      document.getElementById('payment-count').textContent = 'Data gagal dimuat.';
      return;
    }

    paymentRenderStats();
    paymentRender();
  }

  LP.guard(ROLE_INTERNAL, async (user) => {
    paymentUser = user;

    try {
      const daftar = await LP.fetchJson('/bookings');
      paymentBookings = Array.isArray(daftar) ? daftar : [];
    } catch (error) {
      LP.toast('Daftar booking gagal dimuat: ' + error.message, 'error');
    }

    if (LP.can(['manager', 'sales'])) {
      document.getElementById('btn-bayar').addEventListener('click', paymentOpenCreate);
    } else {
      document.getElementById('btn-bayar').classList.add('hidden');
    }

    const renderDebounced = LP.debounce(paymentRender);
    paymentSearch.addEventListener('input', renderDebounced);
    paymentVerificationFilter.addEventListener('change', paymentRender);
    paymentTypeFilter.addEventListener('change', paymentRender);

    document.getElementById('payment-table').addEventListener('click', (event) => {
      const tombol = event.target.closest('button[data-payment-action]');
      if (!tombol) return;
      const baris = paymentView[Number(tombol.dataset.index)];
      if (!baris) return;

      const aksi = tombol.dataset.paymentAction;
      if (aksi === 'detail') paymentOpenDetail(baris);
      else if (aksi === 'verify') paymentOpenVerify(baris, 'verified');
      else if (aksi === 'reject') paymentOpenVerify(baris, 'rejected');
    });

    await paymentLoad();
  });
</script>
@endpush
