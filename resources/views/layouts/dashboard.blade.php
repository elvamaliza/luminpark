<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Lumin Park</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Figtree', 'ui-sans-serif', 'system-ui'] },
                    colors: {
                        lumin: {
                            50: '#f0fdfa',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">
    @php($appBase = rtrim(request()->getBaseUrl(), '/'))
    <script>
        (function () {
            if (!localStorage.getItem('access_token')) {
                window.location.replace(@json($appBase . '/login'));
            }
        })();
    </script>

    <div class="min-h-screen lg:flex">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full transform bg-slate-900 text-slate-200 transition-transform duration-200 lg:static lg:translate-x-0">
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-lumin-500 font-bold text-white">LP</div>
                <div>
                    <p class="text-sm font-semibold text-white">Lumin Park</p>
                    <p class="text-xs text-slate-400">Property Dashboard</p>
                </div>
            </div>

            <nav class="space-y-1 p-4">
                <a href="{{ $appBase }}/dashboard" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->is('dashboard') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Dashboard
                </a>
                <a href="{{ $appBase }}/reset-password" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->is('reset-password') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Reset Password
                </a>
                <a href="{{ $appBase }}/activity-log" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->is('activity-log') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                    Log Aktivitas
                </a>
            </nav>

            <div class="absolute bottom-0 left-0 right-0 border-t border-white/10 p-4">
                <p id="sidebar-user-name" class="truncate text-sm font-medium text-white">—</p>
                <p id="sidebar-user-role" class="text-xs uppercase tracking-wide text-slate-400">—</p>
            </div>
        </aside>

        <div class="flex min-h-screen flex-1 flex-col lg:pl-0">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button id="sidebar-toggle" type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka menu">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-900">@yield('heading', 'Dashboard')</h1>
                </div>
                <div class="flex items-center gap-3">
                    <span id="navbar-user-email" class="hidden text-sm text-slate-500 sm:inline"></span>
                    <button id="logout-btn" type="button" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        Logout
                    </button>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @yield('content')
            </main>
        </div>
    </div>

    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

    <script>
        const API_BASE = @json($appBase . '/api');
        const LOGIN_URL = @json($appBase . '/login');

        function getToken() {
            return localStorage.getItem('access_token');
        }

        function getUser() {
            try {
                return JSON.parse(localStorage.getItem('user') || 'null');
            } catch (e) {
                return null;
            }
        }

        function authHeaders() {
            return {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getToken(),
            };
        }

        const user = getUser();
        if (user) {
            document.getElementById('sidebar-user-name').textContent = user.name || user.email;
            document.getElementById('sidebar-user-role').textContent = user.role || '';
            document.getElementById('navbar-user-email').textContent = user.email || '';
        }

        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        document.getElementById('sidebar-toggle').addEventListener('click', function () {
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        });
        backdrop.addEventListener('click', function () {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        });

        document.getElementById('logout-btn').addEventListener('click', async function () {
            const token = getToken();
            try {
                if (token) {
                    await fetch(API_BASE + '/logout', {
                        method: 'POST',
                        headers: authHeaders(),
                    });
                }
            } catch (e) {
                // tetap logout di klien meski API gagal
            } finally {
                localStorage.removeItem('access_token');
                localStorage.removeItem('user');
                window.location.replace(LOGIN_URL);
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
