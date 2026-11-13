<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/images/logo-mark.svg">
    <title>@yield('title', 'Dashboard') &middot; Luminpark</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        brand: { 
                            50: '#f8fafc',   
                            100: '#f1f5f9',  
                            500: '#5c7361',  
                            600: '#1b3225',  
                            900: '#12241a'   
                        } 
                    },
                    fontFamily: { sans: ['ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'] },
                },
            },
        };
    </script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        
        body {
            background-color: #ffffff !important; /* Latar belakang diubah jadi putih bersih */
            color: #1e293b;
        }

        #lp-aside, aside {
            background-color: #1b3225 !important;
            border-right: none !important;
            margin-top: -1.5rem !important;
            margin-bottom: -1.5rem !important;
            margin-left: -1.5rem !important;
            padding: 2rem 1rem !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        #lp-aside a, #lp-aside span, #lp-aside div, #lp-aside svg {
            color: #b5c4b8 !important;
        }

        .menu-link.active, nav a.active, .bg-zinc-900, #lp-aside a.active {
            background-color: #5c7361 !important;
            color: #ffffff !important;
            border-radius: 0.85rem !important;
            font-weight: 600 !important;
            padding: 0.8rem 1rem !important;
            margin-bottom: 0.5rem !important;
        }
        
        .menu-link.active svg, nav a.active svg {
            color: #ffffff !important;
        }

        #lp-aside nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem 1rem !important;
            margin-bottom: 0.5rem !important;
            border-radius: 0.85rem !important;
            transition: all 0.2s ease;
        }

        .menu-link:hover, nav a:hover, #lp-aside a:hover {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
        }

        header.sticky {
            background-color: rgba(255, 255, 255, 0.95) !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }

        /* ========================================================== */
        /* KOTAK STATISTIK PUTIH BERSIH & RAPI */
        /* ========================================================== */
        #dash-stats > div, #unit-stats > div {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 10px !important;
        }
        @media (max-width: 1280px) {
            #dash-stats > div, #unit-stats > div {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        #dash-stats > div > div, #unit-stats > div > div {
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03) !important;
            padding: 12px 14px !important;
            border-radius: 10px !important;
            min-height: 65px !important;
            height: auto !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            transition: all 0.2s ease;
        }
        #dash-stats > div > div:hover, #unit-stats > div > div:hover {
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
        }
        #dash-stats > div > div > p:nth-child(1), #unit-stats > div > div > p:nth-child(1) {
            font-size: 9px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            color: #64748b !important;
            margin-bottom: 1px !important;
        }
        #dash-stats > div > div > p:nth-child(2), #unit-stats > div > div > p:nth-child(2) {
            font-size: 15px !important;
            font-weight: 700 !important;
            line-height: 1.1 !important;
            color: #0f172a !important;
            margin-top: 0px !important;
        }
        #dash-stats > div > div > p:nth-child(3), #unit-stats > div > div > p:nth-child(3) {
            font-size: 9px !important;
            color: #64748b !important;
            margin-top: 1px !important;
        }

        /* ========================================================== */
        /* TABEL DENGAN GARIS PEMBATAS JELAS & RAPI */
        /* ========================================================== */
        table {
            border-collapse: collapse !important;
            width: 100% !important;
            background-color: #ffffff !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            border: 2px solid #cbd5e1 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
        }
        table th {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 10px 14px !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-right: 1px solid #cbd5e1 !important;
            white-space: nowrap !important;
            text-align: left !important;
        }
        table th:last-child, table td:last-child {
            border-right: none !important;
        }
        table td {
            padding: 10px 14px !important;
            font-size: 13px !important;
            color: #1e293b !important;
            border-bottom: 1px solid #cbd5e1 !important;
            border-right: 1px solid #cbd5e1 !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
        }
        table tr:hover td {
            background-color: #f8fafc !important;
        }
        table tr:last-child td {
            border-bottom: none !important;
        }
    </style>
</head>
<body class="h-full antialiased">
<div class="min-h-full">
    <header class="sticky top-0 z-40 border-b">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
            <button id="lp-mobile-nav" class="rounded-lg p-2 text-[#1e293b] hover:bg-slate-100 lg:hidden" aria-label="Buka menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="/dashboard" class="flex items-center gap-2">
                <img src="/images/logo-mark.svg" alt="Logo Luminpark" class="h-9 w-9 shrink-0">
                <span class="leading-tight">
                    <span class="block text-sm font-bold text-[#1b3225]">Luminpark</span>
                    <span class="block text-[11px] text-[#6b7c70]">Sistem Manajemen Perumahan</span>
                </span>
            </a>

            <div class="ml-auto flex items-center gap-2">
                <div class="relative">
                    <button id="lp-notif-btn" type="button" class="relative rounded-xl p-2 text-[#1e293b] hover:bg-slate-100" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 17h4l-1.2-2A6.5 6.5 0 1 0 6 15l-1 2zm-3 3a3 3 0 0 1-4 0"/></svg>
                        <span id="lp-unread" class="absolute -right-1 -top-1 hidden h-5 min-w-[20px] rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-5 text-white">0</span>
                    </button>
                    <div id="lp-notif-panel" class="absolute right-0 mt-2 hidden w-80 rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                            <p class="text-sm font-semibold text-[#1b3225]">Notifikasi</p>
                            <button id="lp-notif-all" type="button" class="text-xs font-medium text-[#5c7361] hover:text-[#1b3225]">Tandai semua dibaca</button>
                        </div>
                        <div id="lp-notif-list" class="max-h-96 overflow-y-auto p-2"></div>
                        <a href="/notifikasi" class="block border-t border-slate-100 px-4 py-3 text-center text-xs font-semibold text-[#1e293b] hover:bg-slate-50">Lihat semua notifikasi</a>
                    </div>
                </div>

                <div class="relative">
                    <button id="lp-user-menu" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 text-sm hover:bg-slate-50">
                        <span class="relative grid h-8 w-8 place-items-center overflow-hidden rounded-full bg-[#1b3225] text-xs font-bold text-white">
                            <span id="who-initial" class="grid place-items-center">?</span>
                            <img id="who-avatar" alt="" class="hidden h-full w-full object-cover">
                        </span>
                        <span class="hidden text-left leading-tight sm:block">
                            <span id="who-name" class="block text-xs font-semibold text-[#1b3225]">-</span>
                            <span id="who-role" class="block text-[11px] text-[#6b7c70]">-</span>
                        </span>
                    </button>
                    <div id="lp-user-panel" class="absolute right-0 mt-2 hidden w-60 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                        <a href="/profil" class="block rounded-lg px-3 py-2 text-sm text-[#1e293b] hover:bg-slate-50">Profil Saya</a>
                        <a href="/sesi" data-menu-role="super_admin" class="hidden block rounded-lg px-3 py-2 text-sm text-[#1e293b] hover:bg-slate-50">Sesi Login</a>
                        <a href="/pengaturan" data-menu-role="super_admin" class="hidden block rounded-lg px-3 py-2 text-sm text-[#1e293b] hover:bg-slate-50">Pengaturan Sistem</a>
                        <a href="/pengaturan-publik" data-menu-role="super_admin" class="hidden block rounded-lg px-3 py-2 text-sm text-[#1e293b] hover:bg-slate-50">Pengaturan Halaman Publik</a>
                        <button id="lp-logout" class="mt-1 block w-full rounded-lg bg-[#1b3225] px-3 py-2 text-left text-sm font-medium text-white hover:bg-[#12241a]">Keluar</button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-[1600px] gap-6 px-4 py-6 sm:px-6">
        <aside id="lp-aside" class="hidden w-64 shrink-0 lg:block max-lg:absolute max-lg:inset-x-4 max-lg:top-20 max-lg:z-30 max-lg:rounded-2xl max-lg:border max-lg:border-slate-200 max-lg:bg-white max-lg:p-3 max-lg:shadow-xl">
            <nav id="lp-nav" class="sticky top-24 flex flex-col"></nav>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-[#1b3225] sm:text-2xl">@yield('page_title', 'Dashboard')</h1>
                    <p class="mt-1 text-sm text-[#6b7c70]">@yield('page_desc')</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">@yield('page_actions')</div>
            </div>

            <div id="app-shell">@yield('content')</div>
        </main>
    </div>
</div>

<script src="/js/luminpark.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const observer = new MutationObserver(function() {
            const statsContainers = document.querySelectorAll('#dash-stats > div, #unit-stats > div');
            statsContainers.forEach(container => {
                container.style.setProperty('display', 'grid', 'important');
                container.style.setProperty('grid-template-columns', 'repeat(4, minmax(0, 1fr))', 'important');
                container.style.setProperty('gap', '10px', 'important');
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
</script>
@stack('scripts')
</body>
</html>