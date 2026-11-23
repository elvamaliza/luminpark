
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin - Lumin Park</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f5f7;
            color: #343a40;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 245px;
            min-height: 100vh;
            flex-shrink: 0;
            background: #fff;
            border-right: 1px solid #e3e5e8;
            padding: 24px 16px;
        }

        .brand {
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .brand span {
            color: #64748b;
        }

        .profile {
            padding: 14px 10px;
            margin-bottom: 20px;
            background: #f4f5f7;
            border-radius: 8px;
        }

        .profile small {
            color: #6c757d;
        }

        .menu-link {
            display: block;
            padding: 11px 12px;
            margin-bottom: 5px;
            border-radius: 6px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
        }

        .menu-link:hover,
        .menu-link.active {
            background: #e9ecef;
            color: #212529;
            font-weight: bold;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .topbar h1 {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .muted {
            color: #6c757d;
            font-size: 13px;
        }

        .stat-card {
            height: 100%;
            background: #fff;
            border: 1px solid #e3e5e8;
            border-radius: 10px;
            padding: 20px;
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-card .number {
            font-size: 29px;
            font-weight: bold;
        }

        .panel {
            background: #fff;
            border: 1px solid #e3e5e8;
            border-radius: 10px;
            padding: 22px;
        }

        .panel-title {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .chart {
            height: 230px;
            display: flex;
            align-items: end;
            justify-content: space-around;
            gap: 10px;
            padding: 20px 5px 0;
            border-bottom: 1px solid #dee2e6;
            background: repeating-linear-gradient(
                to bottom,
                transparent 0,
                transparent 44px,
                #edf0f2 45px,
                transparent 46px
            );
        }

        .chart-column {
            height: 100%;
            display: flex;
            flex: 1;
            flex-direction: column;
            justify-content: end;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .bar {
            width: min(65%, 32px);
            min-height: 3px;
            background: #aeb5bd;
            border-radius: 4px 4px 0 0;
        }

        .month {
            font-size: 11px;
            color: #6c757d;
        }

        .table thead th {
            background: #f1f3f5;
            color: #495057;
            font-size: 12px;
            white-space: nowrap;
        }

        .table tbody td {
            font-size: 13px;
            vertical-align: middle;
        }

        .empty-state {
            text-align: center;
            color: #6c757d;
            padding: 28px 10px !important;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                min-height: auto;
            }

            .main-content {
                padding: 18px;
            }

            .topbar h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>
<div class="d-flex flex-column flex-md-row">

    <!-- SIDEBAR SUPER ADMIN -->
    <aside class="sidebar">
        <div class="brand">LUMIN <span>PARK</span></div>

        <div class="profile">
            <strong>Super Admin</strong><br>
            <small>Manajemen Perumahan</small>
        </div>

        <nav>
            <a href="/super-admin/dashboard"
               class="menu-link active">Dashboard</a>

            <a href="/super-admin/data-unit"
               class="menu-link">Data Unit Perumahan</a>

            <a href="#" class="menu-link">Data Sales</a>
            <a href="#" class="menu-link">Data Konsumen</a>
            <a href="#" class="menu-link">Data Booking</a>
            <a href="#" class="menu-link">Data Penjualan</a>
            <a href="#" class="menu-link">Data Pembayaran</a>
            <a href="#" class="menu-link">Pengaturan</a>
        </nav>
    </aside>

    <!-- KONTEN DASHBOARD -->
    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Dashboard Super Admin</h1>
                <div class="muted">
                    Ringkasan pengelolaan perumahan Lumin Park
                </div>
            </div>

            <div class="muted">Panel Manajemen</div>
        </header>

        <!-- RINGKASAN INFORMASI -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="label">Total Unit</div>
                    <div class="number">{{ $totalUnit }}</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="label">Unit Tersedia</div>
                    <div class="number">{{ $unitTersedia }}</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="label">Jumlah Sales</div>
                    <div class="number">—</div>
                    <div class="muted">Belum terhubung ke data sales</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="label">Jumlah Konsumen</div>
                    <div class="number">—</div>
                    <div class="muted">Belum terhubung ke data konsumen</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="label">Booking Aktif</div>
                    <div class="number">—</div>
                    <div class="muted">Belum terhubung ke data booking</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card">
                    <div class="label">Pembayaran Pending</div>
                    <div class="number">—</div>
                    <div class="muted">Belum terhubung ke data pembayaran</div>
                </div>
            </div>

        </div>

        <!-- GRAFIK PENJUALAN -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <section class="panel">
                    <div class="panel-title">
                        Grafik Penjualan Per Bulan
                    </div>

                    <div class="muted mb-3">
                        Grafik sementara. Data penjualan belum dihubungkan.
                    </div>

                    <div class="chart">
                        @foreach ([
                            'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
                        ] as $bulan)
                            <div class="chart-column">
                                <div class="bar" style="height: 3px;"></div>
                                <span class="month">{{ $bulan }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        <!-- BOOKING TERBARU -->
        <section class="panel">
            <div class="panel-title">Booking Terbaru</div>

            <div class="muted mb-3">
                Daftar pemesanan unit yang tercatat dalam sistem.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Konsumen</th>
                            <th>Unit</th>
                            <th>Tanggal Booking</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td colspan="5" class="empty-state">
                                Data booking belum dihubungkan ke database.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>
</body>
</html>
