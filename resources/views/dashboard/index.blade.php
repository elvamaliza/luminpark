@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('heading', 'Ringkasan')

@section('content')
    <div class="mb-8">
        <p class="text-sm text-slate-500">Selamat datang kembali</p>
        <h2 id="welcome-name" class="text-2xl font-semibold text-slate-900">...</h2>
        <p id="welcome-role" class="mt-1 text-sm text-slate-500"></p>
    </div>

    <div id="alert-box" class="mb-6 hidden rounded-xl border px-4 py-3 text-sm"></div>

    <div id="stats-grid" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Memuat data...</p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const statsGrid = document.getElementById('stats-grid');
    const alertBox = document.getElementById('alert-box');
    const currentUser = getUser() || {};

    document.getElementById('welcome-name').textContent = currentUser.name || 'Pengguna';
    document.getElementById('welcome-role').textContent = currentUser.role
        ? 'Role: ' + currentUser.role
        : '';

    function renderCards(items) {
        statsGrid.innerHTML = items.map(function (item) {
            return `
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-500">${item.label}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">${item.value}</p>
                </div>
            `;
        }).join('');
    }

    function showAlert(message, type) {
        alertBox.classList.remove('hidden');
        alertBox.textContent = message;
        alertBox.className = type === 'error'
            ? 'mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700'
            : 'mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800';
    }

    async function loadDashboard() {
        try {
            const meRes = await fetch(API_BASE + '/me', { headers: authHeaders() });
            if (meRes.status === 401) {
                localStorage.removeItem('access_token');
                localStorage.removeItem('user');
                window.location.replace(LOGIN_URL);
                return;
            }

            const meData = await meRes.json();
            const profile = meData.data || currentUser;
            document.getElementById('welcome-name').textContent = profile.name || 'Pengguna';
            document.getElementById('welcome-role').textContent = profile.role ? 'Role: ' + profile.role : '';

            const role = profile.role;
            let endpoint = null;
            if (role === 'manager' || role === 'superadmin') {
                endpoint = '/dashboard/manager';
            } else if (role === 'sales') {
                endpoint = '/dashboard/sales?sales_id=' + encodeURIComponent(profile.id);
            }

            if (!endpoint) {
                renderCards([
                    { label: 'Status', value: 'Login aktif' },
                    { label: 'Role', value: role || '-' },
                    { label: 'Email', value: profile.email || '-' },
                ]);
                return;
            }

            const dashRes = await fetch(API_BASE + endpoint, { headers: authHeaders() });
            const dashData = await dashRes.json().catch(function () { return {}; });

            if (!dashRes.ok) {
                renderCards([
                    { label: 'Status', value: 'Login aktif' },
                    { label: 'Role', value: role || '-' },
                    { label: 'Email', value: profile.email || '-' },
                ]);
                showAlert(dashData.message || 'Data ringkasan dashboard belum tersedia.', 'warn');
                return;
            }

            const data = dashData.data || {};
            const cards = Object.keys(data).map(function (key) {
                return {
                    label: key.replace(/_/g, ' '),
                    value: data[key] === null || data[key] === undefined || data[key] === '' ? '-' : data[key],
                };
            });
            renderCards(cards.length ? cards : [{ label: 'Status', value: 'Tidak ada data' }]);
        } catch (error) {
            renderCards([{ label: 'Status', value: 'Gagal memuat' }]);
            showAlert('Tidak dapat memuat data dashboard.', 'error');
        }
    }

    loadDashboard();
</script>
@endpush
