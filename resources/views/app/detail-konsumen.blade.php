@extends('layouts.app')

@section('title', 'Detail Konsumen')
@section('page_title', 'Detail Konsumen')
@section('page_desc', 'Identitas konsumen, status penanganan, riwayat interaksi, dan pemesanan yang sudah dibuat.')

@section('page_actions')
    <a href="/data-konsumen" class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        Kembali ke Data Konsumen
    </a>
@endsection

@section('content')
    <div id="lead-detail" class="space-y-4"></div>
@endsection

@push('scripts')
<script>
  const ROLE_INTERNAL = ['super_admin', 'admin_sales', 'sales'];
  const LEAD_STATUS_LIST = [
    { value: 'cold', label: 'Cold' },
    { value: 'warm', label: 'Warm' },
    { value: 'hot', label: 'Hot' },
  ];
  const METHOD_LABEL = {
    whatsapp: 'WhatsApp',
    phone: 'Telepon',
    visit: 'Kunjungan lokasi',
    email: 'Surel atau email',
  };

  const detailId = (location.pathname.split('/').filter(Boolean).pop() || '').replace(/[^0-9]/g, '');

  let detailLead = null;
  let detailUser = null;
  let detailSalesOptions = [];
  let detailUnitOptions = [];

  function bolehUbah() {
    return LP.can(['super_admin', 'sales']);
  }

  function detailProfile(row) {
    return row.sales_profile || row.salesProfile || null;
  }

  function detailNow() {
    const now = new Date();
    const pad = (angka) => String(angka).padStart(2, '0');
    return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
      + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
  }

  function controlClass() {
    return 'w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10 disabled:bg-zinc-100 disabled:text-zinc-500';
  }

  function fieldHtml(name, label, value, meta) {
    const mati = bolehUbah() ? '' : ' disabled';
    const jenis = meta.type || 'text';
    let kontrol;

    if (jenis === 'select') {
      kontrol = '<select id="dc-' + name + '" class="' + controlClass() + '"' + mati + '>'
        + (meta.placeholder ? '<option value="">' + LP.esc(meta.placeholder) + '</option>' : '')
        + (meta.options || []).map((item) => '<option value="' + LP.esc(item.value) + '"'
          + (String(item.value) === String(value ?? '') ? ' selected' : '') + '>' + LP.esc(item.label) + '</option>').join('')
        + '</select>';
    } else if (jenis === 'textarea') {
      kontrol = '<textarea id="dc-' + name + '" rows="' + (meta.rows || 2) + '" class="' + controlClass() + '"' + mati
        + '>' + LP.esc(value ?? '') + '</textarea>';
    } else {
      kontrol = '<input id="dc-' + name + '" type="' + jenis + '" class="' + controlClass() + '" value="' + LP.esc(value ?? '') + '"'
        + (meta.min !== undefined ? ' min="' + meta.min + '"' : '')
        + (meta.step !== undefined ? ' step="' + meta.step + '"' : '') + mati + '>';
    }

    return '<label class="block ' + (meta.col === 2 ? 'sm:col-span-2' : '') + '">'
      + '<span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + (meta.required ? ' *' : '') + '</span>'
      + kontrol
      + (meta.hint ? '<span class="mt-1 block text-xs text-zinc-500">' + LP.esc(meta.hint) + '</span>' : '')
      + '<span data-err="' + LP.esc(name) + '" class="mt-1 hidden text-xs font-medium text-rose-600"></span>'
      + '</label>';
  }

  function detailRow(label, value) {
    return '<div class="border-t border-zinc-100 py-2.5 first:border-t-0 sm:grid sm:grid-cols-3 sm:gap-4">'
      + '<dt class="text-xs font-semibold uppercase tracking-wide text-zinc-500">' + LP.esc(label) + '</dt>'
      + '<dd class="mt-1 text-sm text-zinc-800 sm:col-span-2 sm:mt-0">' + value + '</dd></div>';
  }

  function detailIdentity(lead) {
    const profil = detailProfile(lead);
    const pengguna = profil && profil.user ? profil.user : null;
    const sales = pengguna && pengguna.name ? pengguna.name : 'Belum ditangani sales';
    // Revisi 1: foto sales yang bertanggung jawab atas konsumen ini.
    const salesFoto = pengguna && pengguna.avatar_url
      ? LP.fotoSales(pengguna.avatar_url, pengguna.name, 'h-10 w-10')
      : '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-bold text-slate-500">'
        + LP.esc(String(pengguna ? pengguna.name || sales : '?').charAt(0)) + '</span>';
    const unitHtml = lead.unit
      ? '<a class="font-medium text-brand-600 hover:underline" href="/data-unit/' + LP.esc(lead.unit.id) + '">'
        + LP.esc(lead.unit.unit_code + ' — ' + lead.unit.unit_type) + '</a>'
        + '<span class="block text-xs text-zinc-500">' + LP.esc(LP.money(lead.unit.price)) + '</span>'
      : (lead.interested_unit
        ? LP.esc(lead.interested_unit)
        : '<span class="text-zinc-500">Belum menentukan unit</span>');

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-start justify-between gap-3">'
      + '<div><h2 class="text-lg font-semibold text-zinc-900">' + LP.esc(lead.name) + '</h2>'
      + '<p class="mt-0.5 text-sm text-zinc-500">Terdaftar sejak ' + LP.esc(LP.date(lead.created_at)) + '</p></div>'
      + '<div class="flex flex-wrap items-center justify-end gap-2">'
      + LP.badge(lead.category || 'prospek') + LP.badge(lead.status) + '</div></div>'
      + '<dl class="mt-4">'
      + detailRow('Nomor Telepon', '<a class="text-brand-600 hover:underline" href="tel:' + LP.esc(lead.phone) + '">' + LP.esc(lead.phone || '-') + '</a>')
      + detailRow('Sales Penanganan', '<span class="flex items-center gap-3">' + salesFoto
        + '<span>' + LP.esc(sales)
        + (profil && profil.sales_code ? ' <span class="text-xs text-zinc-500">(' + LP.esc(profil.sales_code) + ')</span>' : '')
        + '</span></span>')
      + detailRow('Unit Diminati', unitHtml)
      + detailRow('Sumber', lead.source ? LP.esc(lead.source) : '<span class="text-zinc-500">Manual</span>')
      + detailRow('Tanggal Chat Awal', lead.first_chat_date ? LP.esc(LP.date(lead.first_chat_date)) : '<span class="text-zinc-500">Belum tercatat</span>')
      + detailRow('Follow Up Terakhir', lead.last_follow_up ? LP.esc(LP.datetime(lead.last_follow_up)) : '<span class="text-rose-600">Belum pernah diikuti</span>')
      + '</dl></section>';
  }

  function detailUnitChoices(lead) {
    const daftar = detailUnitOptions.map((item) => ({ value: item.id, label: item.unit_code + ' — ' + item.unit_type }));
    if (lead.unit && !daftar.some((item) => String(item.value) === String(lead.unit.id))) {
      daftar.unshift({ value: lead.unit.id, label: lead.unit.unit_code + ' — ' + lead.unit.unit_type });
    }
    return daftar;
  }

  function detailForm(lead) {
    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-center justify-between gap-3">'
      + '<div><h2 class="text-base font-semibold text-zinc-900">Ubah Data Konsumen</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">Kategori berubah menjadi Konsumen begitu pemesanan dibuat dan tetap tercatat sebagai riwayat bila dibatalkan.</p></div>'
      + (bolehUbah() ? '<button id="dc-save" type="button" class="rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60">Simpan Perubahan</button>' : '')
      + '</div>'
      + '<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">'
      + fieldHtml('name', 'Nama Konsumen', lead.name, { required: true })
      + fieldHtml('phone', 'Nomor Telepon', lead.phone, { required: true, hint: 'Maksimal 15 karakter.' })
      + fieldHtml('email', 'Email', lead.email || '', { type: 'email' })
      + fieldHtml('status', 'Status Konsumen', lead.status, { type: 'select', required: true, options: LEAD_STATUS_LIST })
      + fieldHtml('first_chat_date', 'Tanggal Chat Awal', lead.first_chat_date ? LP.isoDate(lead.first_chat_date) : '', {
        type: 'date', hint: 'Tanggal konsumen pertama kali menghubungi sales.',
      })
      + (LP.currentUserRole() === 'sales' ? '' : fieldHtml('sales_id', 'Sales Penanganan', lead.sales_id || '', {
        type: 'select', options: detailSalesOptions, placeholder: 'Belum ditangani sales',
      }))
      + fieldHtml('unit_id', 'Unit Diminati', lead.unit_id || '', {
        type: 'select', options: detailUnitChoices(lead), placeholder: 'Belum memilih unit',
      })
      + fieldHtml('budget', 'Perkiraan Anggaran (Rp)', lead.budget ? Math.round(Number(lead.budget)) : '', { type: 'number', min: 0, step: 'any' })
      + fieldHtml('last_follow_up', 'Follow Up Terakhir', lead.last_follow_up ? LP.isoDate(lead.last_follow_up) : '', { type: 'date' })
      + fieldHtml('source', 'Sumber Konsumen', lead.source || '', { placeholder: 'website, event, telemarketing' })
      + fieldHtml('address', 'Alamat', lead.address || '', { type: 'textarea', rows: 2, col: 2 })
      + fieldHtml('note', 'Catatan', lead.note || '', { type: 'textarea', rows: 3, col: 2, hint: 'Preferensi, keberatan, atau kesepakatan dengan konsumen.' })
      + '</div>'
      + (bolehUbah() ? '' : '<p class="mt-4 rounded-xl bg-zinc-50 p-3 text-xs text-zinc-600">Halaman ini hanya dibaca. Data konsumen dikelola oleh Super Admin dan sales yang menanganinya.</p>')
      + '</section>';
  }

  function detailActions(lead) {
    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Aksi Cepat</h2>'
      + '<div class="mt-4 grid gap-2">'
      + (bolehUbah() ? '<button type="button" id="btn-tambah-followup" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">Catat Follow Up Baru</button>' : '')
      + '<a href="/booking" class="rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-50">Buka Modul Booking</a>'
      + '</div>'
      + (lead.status === 'hot'
        ? '<p class="mt-3 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-800">Status Hot: konsumen sudah dapat dibuatkan booking unit.</p>'
        : '<p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Booking hanya dapat dibuat untuk konsumen berstatus Hot.</p>')
      + '</section>';
  }

  function detailFollowUps(lead) {
    const riwayat = Array.isArray(lead.follow_up_histories) ? lead.follow_up_histories : [];

    const isi = riwayat.length
      ? '<ol class="mt-4 space-y-3">' + riwayat.map((item) => {
          const penanggungJawab = item.sales_profile && item.sales_profile.user ? item.sales_profile.user.name : 'Sistem';
          return '<li class="rounded-xl border border-zinc-200 bg-white p-4">'
            + '<div class="flex flex-wrap items-center justify-between gap-2">'
            + '<p class="text-sm font-semibold text-zinc-900">' + LP.esc(METHOD_LABEL[item.method] || item.method) + '</p>'
            + '<p class="text-xs text-zinc-500">' + LP.esc(LP.datetime(item.follow_up_date)) + '</p></div>'
            + '<p class="mt-2 text-sm text-zinc-700">' + (item.note ? LP.esc(item.note) : '<span class="text-zinc-400">Tidak ada keterangan kegiatan.</span>') + '</p>'
            + '<p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500">'
            + '<span>Oleh: ' + LP.esc(penanggungJawab) + '</span>'
            + '<span>' + LP.badge(item.status_before || 'cold') + ' menjadi ' + LP.badge(item.status_after || 'warm') + '</span>'
            + '</p></li>';
        }).join('') + '</ol>'
      : '<p class="mt-4 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-6 text-center text-sm text-zinc-500">'
        + 'Belum ada riwayat interaksi. Catat follow up pertama agar perkembangan konsumen terpantau.</p>';

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<div class="flex flex-wrap items-center justify-between gap-3">'
      + '<div><h2 class="text-base font-semibold text-zinc-900">Riwayat Interaksi</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">' + riwayat.length + ' catatan follow up tersimpan.</p></div>'
      + '</div>' + isi + '</section>';
  }

  function detailBookingSection(lead) {
    const daftar = Array.isArray(lead.bookings) ? lead.bookings : [];

    return '<section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">'
      + '<h2 class="text-base font-semibold text-zinc-900">Booking Konsumen</h2>'
      + '<p class="mt-1 text-sm text-zinc-500">'
      + (daftar.length ? daftar.length + ' pemesanan tercatat untuk konsumen ini.' : 'Belum ada pemesanan dari konsumen ini.')
      + '</p><div id="lead-booking-table" class="mt-4"></div></section>';
  }

  function detailRenderBookings(lead) {
    const daftar = Array.isArray(lead.bookings) ? lead.bookings : [];
    if (!daftar.length) {
      document.getElementById('lead-booking-table').innerHTML = '';
      return;
    }

    LP.table({
      mount: 'lead-booking-table',
      rows: daftar,
      empty: 'Belum ada booking.',
      columns: [
        { label: 'Kode Booking', render: (row) => '<a class="font-medium text-brand-600 hover:underline" href="/booking">' + LP.esc(row.booking_code) + '</a>' },
        { label: 'Tanggal', render: (row) => LP.date(row.booking_date) },
        { label: 'Total Harga', align: 'right', render: (row) => LP.money(row.total_price) },
        { label: 'Biaya Tambahan', align: 'right', render: (row) => LP.money(row.addition_cost || 0) },
        { label: 'Uang Muka', align: 'right', render: (row) => LP.money(row.down_payment_amount) },
        { label: 'Sisa', align: 'right', render: (row) => LP.money(row.remaining_amount) },
        { label: 'Status', render: (row) => LP.badge(row.status) },
        { label: 'Serah Terima', render: (row) => LP.badge(row.delivery_status) },
        {
          label: 'Pembatalan',
          render: (row) => (row.status === 'dibatalkan'
            ? '<span class="block text-xs font-medium text-rose-700">' + LP.esc(row.cancellation_reason || 'Tanpa alasan tercatat') + '</span>'
              + '<span class="block text-xs text-zinc-500">' + LP.esc(LP.datetime(row.cancelled_at)) + '</span>'
            : '<span class="text-xs text-zinc-500">-</span>'),
        },
      ],
    });
  }

  function detailShowErrors(errors) {
    document.querySelectorAll('#lead-detail [data-err]').forEach((slot) => {
      slot.classList.add('hidden');
      slot.textContent = '';
    });
    Object.entries(errors || {}).forEach(([key, messages]) => {
      const slot = document.querySelector('#lead-detail [data-err="' + key + '"]');
      if (slot) {
        slot.textContent = messages.join(', ');
        slot.classList.remove('hidden');
      }
    });
  }

  async function detailSave() {
    const nilai = (key) => {
      const input = document.getElementById('dc-' + key);
      return input ? input.value : '';
    };

    const body = {
      name: String(nilai('name')).trim(),
      phone: String(nilai('phone')).trim(),
      status: nilai('status'),
      email: nilai('email'),
      address: nilai('address'),
      note: nilai('note'),
      budget: nilai('budget'),
      first_chat_date: nilai('first_chat_date'),
      last_follow_up: nilai('last_follow_up'),
      source: nilai('source'),
      unit_id: nilai('unit_id'),
    };

    if (!body.name) throw new Error('Nama konsumen wajib diisi.');
    if (!body.phone) throw new Error('Nomor telepon wajib diisi.');
    if (LP.currentUserRole() !== 'sales') body.sales_id = nilai('sales_id');

    detailShowErrors({});
    const hasil = await LP.http.put('/customer-leads/' + detailLead.id, body);
    LP.toast((hasil.json && hasil.json.message) || 'Data konsumen berhasil diperbarui.');
    await detailLoad();
  }

  function detailFollowUpFields() {
    const fields = [
      { name: 'follow_up_date', label: 'Waktu Interaksi', type: 'datetime-local', required: true },
      { name: 'method', label: 'Cara Interaksi', type: 'select', required: true, options: [
        { value: 'whatsapp', label: 'WhatsApp' },
        { value: 'phone', label: 'Telepon' },
        { value: 'visit', label: 'Kunjungan lokasi' },
        { value: 'email', label: 'Surel atau email' },
      ] },
      { name: 'status_before', label: 'Status Sebelum', type: 'select', required: true, options: LEAD_STATUS_LIST },
      { name: 'status_after', label: 'Status Sesudah', type: 'select', required: true, options: LEAD_STATUS_LIST, hint: 'Status konsumen akan ikut berubah saat catatan disimpan.' },
    ];

    if (detailUser && detailUser.role !== 'sales') {
      fields.push({
        name: 'sales_id',
        label: 'Sales Pencatat',
        type: 'select',
        required: true,
        options: detailSalesOptions,
        placeholder: 'Pilih sales',
        col: 2,
      });
    }

    fields.push({ name: 'note', label: 'Keterangan Kegiatan', type: 'textarea', rows: 3, col: 2, placeholder: 'Contoh: menjelaskan simulasi cicilan 36 bulan, konsumen meminta penawaran ulang.' });

    return fields;
  }

  function detailOpenFollowUp(lead) {
    const nilai = {
      follow_up_date: detailNow(),
      method: 'whatsapp',
      status_before: lead.status,
      status_after: lead.status,
      sales_id: lead.sales_id || '',
      note: '',
    };

    LP.formModal({
      title: 'Catat Follow Up — ' + lead.name,
      description: 'Riwayat interaksi tersimpan otomatis beserta status sebelum dan sesudah kontak.',
      fields: detailFollowUpFields(),
      values: nilai,
      submitLabel: 'Simpan Follow Up',
      onSubmit: async (values, context) => {
        const tanggal = (values.follow_up_date || '').replace('T', ' ');
        if (!tanggal) throw new Error('Waktu interaksi wajib diisi.');
        const body = {
          lead_id: lead.id,
          follow_up_date: tanggal.length === 16 ? tanggal + ':00' : tanggal,
          method: values.method,
          status_before: values.status_before,
          status_after: values.status_after,
          note: values.note,
        };
        if (values.sales_id) body.sales_id = Number(values.sales_id);

        const hasil = await LP.http.post('/follow-ups', body);
        LP.toast((hasil.json && hasil.json.message) || 'Riwayat follow up berhasil dicatat.');
        context.close();
        await detailLoad();
      },
    });
  }

  function detailRender(lead) {
    detailLead = lead;

    const judul = document.querySelector('main h1');
    if (judul) judul.textContent = 'Detail Konsumen — ' + lead.name;
    document.title = 'Detail ' + lead.name + ' · Lumin Park';

    document.getElementById('lead-detail').innerHTML =
      '<div class="grid gap-4 lg:grid-cols-3">'
      + '<div class="lg:col-span-2">' + detailForm(lead) + '</div>'
      + '<div class="space-y-4">' + detailIdentity(lead) + detailActions(lead) + '</div></div>'
      + detailFollowUps(lead)
      + detailBookingSection(lead);

    detailRenderBookings(lead);

    const simpan = document.getElementById('dc-save');
    if (simpan) {
      simpan.addEventListener('click', (event) => {
        LP.withBusy(event.currentTarget, async () => {
          try {
            await detailSave();
          } catch (error) {
            if (error && error.errors) detailShowErrors(error.errors);
            LP.toast(error.message || 'Data konsumen gagal disimpan.', 'error');
          }
        });
      });
    }

    const followup = document.getElementById('btn-tambah-followup');
    if (followup) followup.addEventListener('click', () => detailOpenFollowUp(lead));
  }

  async function detailLoad() {
    LP.loading('lead-detail', 'Memuat detail konsumen...');

    try {
      const lead = await LP.fetchJson('/customer-leads/' + detailId);
      if (!lead || !lead.id) {
        LP.errorBox('lead-detail', 'Data konsumen tidak ditemukan pada sistem.');
        return;
      }
      detailRender(lead);
    } catch (error) {
      LP.errorBox('lead-detail', error.status === 403
        ? 'Anda tidak berhak mengakses data konsumen ini. Setiap sales hanya dapat membuka konsumennya sendiri.'
        : (error.message || 'Detail konsumen gagal dimuat.'));
    }
  }

  LP.guard(ROLE_INTERNAL, async (user) => {
    detailUser = user;

    if (!detailId) {
      LP.errorBox('lead-detail', 'Alamat halaman tidak menyertakan nomor id konsumen.');
      return;
    }

    if (bolehUbah()) {
      try {
        const tugas = [LP.fetchJson('/units-available')];
        if (user.role !== 'sales') tugas.push(LP.fetchJson('/sales'));

        const [unit, daftarSales] = await Promise.all(tugas);
        detailUnitOptions = Array.isArray(unit) ? unit : [];
        if (user.role !== 'sales') {
          detailSalesOptions = (Array.isArray(daftarSales) ? daftarSales : [])
            .filter((item) => detailProfile(item))
            .map((item) => ({ value: item.sales_profile.id, label: item.name + ' (' + item.sales_profile.sales_code + ')' }));
        }
      } catch (error) {
        LP.toast('Sebagian data pilihan gagal dimuat: ' + (error.message || 'periksa koneksi Anda.'), 'error');
      }
    }

    await detailLoad();
  });
</script>
@endpush
