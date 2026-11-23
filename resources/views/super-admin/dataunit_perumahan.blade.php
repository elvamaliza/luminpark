
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Unit Perumahan | Lumin Park</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            color: #303846;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 25px 20px;
            background: #fff;
            border-right: 1px solid #e0e4e8;
            overflow-y: auto;
        }

        .brand {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 32px;
        }

        .brand span {
            color: #64748b;
        }

        .profile {
            background: #f4f5f7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 22px;
        }

        .profile small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
        }

        .menu-link {
            display: block;
            padding: 12px 14px;
            margin-bottom: 5px;
            border-radius: 7px;
            color: #344256;
            text-decoration: none;
            font-size: 15px;
        }

        .menu-link:hover,
        .menu-link.active {
            background: #e9ecef;
            color: #212529;
            font-weight: bold;
        }

        .main-content {
            margin-left: 250px;
            padding: 32px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 27px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .muted {
            color: #6c757d;
            font-size: 14px;
        }

        .panel {
            padding: 24px;
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
        }

        .panel-title {
            font-size: 19px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            min-height: 42px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
        }

        .table thead th {
            background: #eef1f4;
            color: #344256;
            font-size: 13px;
            white-space: nowrap;
            padding: 13px;
        }

        .table tbody td {
            font-size: 13px;
            padding: 13px;
            vertical-align: middle;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .tersedia {
            background: #d1e7dd;
            color: #146c43;
        }

        .booking {
            background: #fff3cd;
            color: #856404;
        }

        .terjual {
            background: #f8d7da;
            color: #842029;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .panel {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">LUMIN <span>PARK</span></div>

    <div class="profile">
        <strong>Super Admin</strong>
        <small>Manajemen Perumahan</small>
    </div>

    <nav>
        <a href="/super-admin/dashboard" class="menu-link">
            Dashboard
        </a>

        <a href="/super-admin/data-unit" class="menu-link active">
            Data Unit Perumahan
        </a>

        <a href="#" class="menu-link">Data Sales</a>
        <a href="#" class="menu-link">Data Konsumen</a>
        <a href="#" class="menu-link">Data Booking</a>
        <a href="#" class="menu-link">Data Penjualan</a>
        <a href="#" class="menu-link">Data Pembayaran</a>
        <a href="#" class="menu-link">Pengaturan</a>
    </nav>
</aside>

<!-- KONTEN UTAMA -->
<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Data Unit Perumahan</h1>
            <div class="muted">
                Pengelolaan data unit perumahan Lumin Park
            </div>
        </div>

        <span class="muted">Panel Manajemen</span>
    </header>

    <section class="panel">

        <div class="d-flex justify-content-between align-items-center
                    flex-wrap gap-3 mb-4">
            <div>
                <div class="panel-title">Daftar Unit Perumahan</div>
                <div class="muted">
                    Informasi blok, tipe, luas, harga, dan status unit
                </div>
            </div>

            <button type="button" class="btn btn-primary"
                    onclick="bukaForm()">
                + Tambah Unit
            </button>
        </div>

        <div id="pesanUnit" class="mb-3" role="status"></div>

        <!-- PENCARIAN DAN FILTER -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-7">
                <input id="cariUnit" type="text" class="form-control"
                       placeholder="Cari kode unit atau tipe...">
            </div>

            <div class="col-12 col-md-5">
                <select id="filterStatus" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="available">Tersedia</option>
                    <option value="booking">Booking</option>
                    <option value="sold_out">Sold Out</option>
                    <option value="delivery_process">Proses Delivery</option>
                </select>
            </div>
        </div>

        <!-- TABEL UNIT -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Unit</th>
                        <th>Tipe Unit</th>
                        <th>Luas Tanah</th>
                        <th>Luas Bangunan</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody id="daftarUnit">
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            Memuat data unit...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="jumlahUnit" class="muted"></div>

    </section>
</main>

<!-- MODAL FORM UNIT -->
<div class="modal fade" id="modalUnit" tabindex="-1"
     aria-labelledby="judulModal" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="judulModal">
                        Tambah Unit Perumahan
                    </h5>
                    <div class="muted mt-1">
                        Lengkapi informasi unit rumah
                    </div>
                </div>

                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Tutup">
                </button>
            </div>

            <form id="formUnit">
                <div class="modal-body">

                    <div id="pesanForm" class="alert d-none"
                         role="alert"></div>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Kode Unit</label>
                            <input type="text" name="unit_code"
                                   class="form-control"
                                   placeholder="Contoh: LT-001" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Cluster</label>
                            <select name="cluster_id" id="clusterSelect"
                                    class="form-select" required>
                                <option value="">Memuat cluster...</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipe Unit</label>
                            <input type="text" name="unit_type"
                                   class="form-control"
                                   placeholder="Contoh: Type 36" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Luas Tanah (m²)</label>
                            <input type="number" name="land_size"
                                   class="form-control" min="1" step="1"
                                   placeholder="Masukkan luas tanah" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Luas Bangunan (m²)</label>
                            <input type="number" name="building_size"
                                   class="form-control" min="1" step="1"
                                   placeholder="Masukkan luas bangunan" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Harga Unit (Rp)</label>
                            <input type="number" name="price"
                                   class="form-control" min="0" step="any"
                                   placeholder="Masukkan harga unit" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status Unit</label>
                            <select name="status" class="form-select" required>
                                <option value="available">Tersedia</option>
                                <option value="booking">Booking</option>
                                <option value="sold_out">Sold Out</option>
                                <option value="delivery_process">Proses Delivery</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="description" class="form-control"
                                      rows="3"
                                      placeholder="Keterangan tambahan (opsional)"></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                            data-bs-dismiss="modal">
                        Batal
                    </button>

                    <button type="submit" id="tombolSimpan"
                            class="btn btn-primary">
                        Simpan Unit
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- MODAL BOOTSTRAP -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const apiUrl = '/api/units';

    // Halaman ini berbicara ke API yang dilindungi Sanctum, jadi token
    // dari hasil login harus ikut dikirim.
    function authHeaders(tambahan = {}) {
        return {
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + (localStorage.getItem('access_token') || ''),
            ...tambahan,
        };
    }

    function tangani401(response) {
        if (response.status === 401) {
            window.location.href = '/login';
            return true;
        }

        return false;
    }

    const daftarUnit = document.getElementById('daftarUnit');
    const cariUnit = document.getElementById('cariUnit');
    const filterStatus = document.getElementById('filterStatus');
    const pesanUnit = document.getElementById('pesanUnit');
    const jumlahUnit = document.getElementById('jumlahUnit');
    const formUnit = document.getElementById('formUnit');
    const pesanForm = document.getElementById('pesanForm');
    const tombolSimpan = document.getElementById('tombolSimpan');

    const modalElement = document.getElementById('modalUnit');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    let dataUnit = [];

    // Mengamankan teks sebelum ditampilkan di tabel.
    function aman(nilai) {
        return String(nilai ?? '').replace(/[&<>"']/g, karakter => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[karakter]);
    }

    // Mengubah angka menjadi format rupiah.
    function formatRupiah(nilai) {
        if (nilai === null || nilai === undefined || nilai === '') {
            return '-';
        }

        return 'Rp ' + Number(nilai).toLocaleString('id-ID');
    }

    // Mengubah status database menjadi tulisan yang mudah dibaca.
    function namaStatus(status) {
        const daftarStatus = {
            available: 'Tersedia',
            booking: 'Booking',
            sold_out: 'Sold Out',
            delivery_process: 'Proses Delivery'
        };

        return daftarStatus[status] || status || '-';
    }

    // Menampilkan pesan pada halaman.
    function tampilkanPesan(teks, tipe = 'success') {
        pesanUnit.innerHTML =
            `<div class="alert alert-${tipe} mb-0">${aman(teks)}</div>`;
    }

    function tampilkanPesanForm(teks, tipe = 'danger') {
        pesanForm.textContent = teks;
        pesanForm.className = 'alert alert-' + tipe;
    }

    // Membuka formulir tambah unit.
    function bukaForm() {
        formUnit.reset();
        pesanForm.className = 'alert d-none';

        document.getElementById('judulModal').textContent =
            'Tambah Unit Perumahan';

        modal.show();
    }

    // Menampilkan data unit pada tabel.
    function tampilkanUnit() {
        const kata = cariUnit.value.trim().toLowerCase();
        const statusPilihan = filterStatus.value;

        const hasil = dataUnit.filter(unit => {
            const teks = [
                unit.unit_code,
                unit.unit_type,
                unit.cluster?.name,
                unit.description
            ].filter(Boolean).join(' ').toLowerCase();

            return teks.includes(kata) &&
                (!statusPilihan || unit.status === statusPilihan);
        });

        if (hasil.length === 0) {
            daftarUnit.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        Belum ada data unit perumahan.
                    </td>
                </tr>`;

            jumlahUnit.textContent = 'Total data unit: 0';
            return;
        }

        daftarUnit.innerHTML = hasil.map((unit, index) => {
            const kelasStatus = {
                available: 'tersedia',
                booking: 'booking',
                sold_out: 'terjual',
                delivery_process: 'terjual'
            }[unit.status] || '';

            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${aman(unit.unit_code)}</td>
                    <td>${aman(unit.unit_type)}</td>
                    <td>${aman(unit.land_size)} m²</td>
                    <td>${aman(unit.building_size)} m²</td>
                    <td>${aman(formatRupiah(unit.price))}</td>
                    <td>
                        <span class="status ${kelasStatus}">
                            ${aman(namaStatus(unit.status))}
                        </span>
                    </td>
                    <td>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-detail="${aman(unit.id)}">
                            Detail
                        </button>
                    </td>
                </tr>`;
        }).join('');

        jumlahUnit.textContent =
            'Total data unit: ' + hasil.length;
    }

    // Mengambil data unit dari Laravel.
    async function muatUnit() {
        daftarUnit.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    Memuat data unit...
                </td>
            </tr>`;

        try {
            const response = await fetch(apiUrl, { headers: authHeaders() });

            if (tangani401(response)) {
                return;
            }

            const hasil = await response.json();

            if (!response.ok) {
                throw new Error(
                    hasil.message || 'Gagal mengambil data unit.'
                );
            }

            // UnitController index() mengirim daftar unit langsung.
            dataUnit = Array.isArray(hasil) ? hasil : [];

            tampilkanUnit();
            pesanUnit.innerHTML = '';

        } catch (error) {
            daftarUnit.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-danger py-4">
                        Data unit gagal dimuat.
                    </td>
                </tr>`;

            jumlahUnit.textContent = '';
            tampilkanPesan(error.message, 'danger');
            console.error('Kesalahan mengambil data:', error);
        }
    }

    // Menyimpan unit baru ke database.
    formUnit.addEventListener('submit', async function(event) {
        event.preventDefault();

        const formData = new FormData(formUnit);

        // Nama field harus sama dengan UnitController.
        const data = {
            unit_code: formData.get('unit_code').trim(),
            cluster_id: Number(formData.get('cluster_id')),
            unit_type: formData.get('unit_type').trim(),
            land_size: Number(formData.get('land_size')),
            building_size: Number(formData.get('building_size')),
            price: Number(formData.get('price')),
            status: formData.get('status'),
            description: formData.get('description').trim() || null
        };

        tombolSimpan.disabled = true;
        tombolSimpan.textContent = 'Menyimpan...';
        pesanForm.className = 'alert d-none';

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(data)
            });

            if (tangani401(response)) {
                return;
            }

            const hasil = await response.json();

            if (!response.ok) {
                const daftarError = hasil.errors
                    ? Object.values(hasil.errors).flat().join(' ')
                    : '';

                throw new Error(
                    daftarError ||
                    hasil.message ||
                    'Data unit gagal disimpan.'
                );
            }

            tampilkanPesanForm(
                hasil.message || 'Data unit berhasil disimpan.',
                'success'
            );

            formUnit.reset();

            // Memuat ulang data dari database.
            await muatUnit();

            setTimeout(() => {
                modal.hide();
                pesanForm.className = 'alert d-none';
            }, 700);

        } catch (error) {
            tampilkanPesanForm(
                'Data belum berhasil disimpan: ' + error.message,
                'danger'
            );

            console.error('Kesalahan simpan unit:', error);

        } finally {
            tombolSimpan.disabled = false;
            tombolSimpan.textContent = 'Simpan Unit';
        }
    });

    // Menampilkan detail unit.
    daftarUnit.addEventListener('click', async function(event) {
        const tombol = event.target.closest('[data-detail]');

        if (!tombol) {
            return;
        }

        const id = tombol.dataset.detail;

        try {
            const response = await fetch(
                apiUrl + '/' + encodeURIComponent(id),
                {
                    headers: authHeaders()
                }
            );

            if (tangani401(response)) {
                return;
            }

            const hasil = await response.json();

            if (!response.ok) {
                throw new Error(
                    hasil.message || 'Detail unit gagal diambil.'
                );
            }

            // UnitController show() mengirim objek unit dalam data.
            const unit = hasil.data;

            alert(
                'DETAIL UNIT\n\n' +
                'Kode Unit: ' + (unit.unit_code ?? '-') + '\n' +
                'Cluster: ' + (unit.cluster?.name ?? '-') + '\n' +
                'Tipe: ' + (unit.unit_type ?? '-') + '\n' +
                'Luas Tanah: ' + (unit.land_size ?? '-') + ' m²\n' +
                'Luas Bangunan: ' + (unit.building_size ?? '-') + ' m²\n' +
                'Harga: ' + formatRupiah(unit.price) + '\n' +
                'Status: ' + namaStatus(unit.status) + '\n' +
                'Keterangan: ' + (unit.description ?? '-')
            );

        } catch (error) {
            alert(error.message);
            console.error('Kesalahan detail unit:', error);
        }
    });

    // Fitur pencarian dan filter.
    cariUnit.addEventListener('input', tampilkanUnit);
    filterStatus.addEventListener('change', tampilkanUnit);

    // Mengisi pilihan cluster pada formulir unit.
    async function muatCluster() {
        const select = document.getElementById('clusterSelect');

        try {
            const response = await fetch(apiUrl + '/clusters', { headers: authHeaders() });

            if (!response.ok) {
                throw new Error('Gagal mengambil data cluster.');
            }

            const hasil = await response.json();

            select.innerHTML = '<option value="">Pilih cluster</option>' +
                (hasil.data || []).map(cluster =>
                    `<option value="${cluster.id}">${aman(cluster.name)}</option>`
                ).join('');

        } catch (error) {
            select.innerHTML = '<option value="">Cluster belum tersedia</option>';
            console.error('Kesalahan memuat cluster:', error);
        }
    }

    // Memuat data saat halaman pertama kali dibuka.
    muatUnit();
    muatCluster();
</script>

</body>
</html>
