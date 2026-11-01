<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Sales - Luminpark</title>
    <!-- Menggunakan Bootstrap CDN agar tampilan tabel langsung rapi -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">
    <div class="container bg-white p-4 rounded shadow-sm">
        <h2 class="mb-4">Daftar Data Sales</h2>
        
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody id="sales-data">
                <tr>
                    <td colspan="4" class="text-center">Memuat data...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Script Axios & Fetch Data dari API -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Panggil API saat halaman selesai di-load
        document.addEventListener('DOMContentLoaded', function() {
            axios.get('/api/sales', {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + (localStorage.getItem('access_token') || '')
                    }
                })
                .then(function (response) {
                    const sales = response.data.data;
                    let html = '';
                    
                    if (sales.length === 0) {
                        html = '<tr><td colspan="4" class="text-center">Belum ada data sales.</td></tr>';
                    } else {
                        sales.forEach((item, index) => {
                            html += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${item.name}</td>
                                    <td>${item.email}</td>
                                    <td><span class="badge bg-info">${item.role}</span></td>
                                </tr>
                            `;
                        });
                    }
                    
                    document.getElementById('sales-data').innerHTML = html;
                })
                .catch(function (error) {
                    console.error('Error:', error);
                    document.getElementById('sales-data').innerHTML = 
                        '<tr><td colspan="4" class="text-center text-danger">Gagal mengambil data sales.</td></tr>';
                });
        });
    </script>
</body>
</html>