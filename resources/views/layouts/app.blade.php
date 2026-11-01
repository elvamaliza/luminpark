<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/images/logo-mark.svg">
    <title>@yield('title', 'Dashboard') &middot; Luminpark</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 50: '#f2f7f4', 100: '#dcebe2', 500: '#2f7d5b', 600: '#256349', 900: '#123024' } },
                    fontFamily: { sans: ['ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'] },
                },
            },
        };
    </script>
    <style>
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-thumb { background: #d4d4d4; border-radius: 9999px; }
    </style>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased">
<div class="min-h-full">
    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
            <button id="lp-mobile-nav" class="rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden" aria-label="Buka menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="/dashboard" class="flex items-center gap-2">
                <img src="/images/logo-mark.svg" alt="Logo Luminpark" class="h-9 w-9 shrink-0">
                <span class="leading-tight">
                    <span class="block text-sm font-bold">Luminpark</span>
                    <span class="block text-[11px] text-zinc-500">Sistem Manajemen Perumahan</span>
                </span>
            </a>

            <div class="ml-auto flex items-center gap-2">
                <div class="relative">
                    <button id="lp-notif-btn" type="button" class="relative rounded-xl p-2 text-zinc-600 hover:bg-zinc-100" aria-label="Notifikasi" aria-haspopup="true" aria-expanded="false">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 17h4l-1.2-2A6.5 6.5 0 1 0 6 15l-1 2zm-3 3a3 3 0 0 1-4 0"/></svg>
                        <span id="lp-unread" class="absolute -right-1 -top-1 hidden h-5 min-w-[20px] rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-5 text-white">0</span>
                    </button>
                    <div id="lp-notif-panel" class="absolute right-0 mt-2 hidden w-80 rounded-2xl border border-zinc-200 bg-white shadow-xl">
                        <div class="flex items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3">
                            <p class="text-sm font-semibold text-zinc-900">Notifikasi</p>
                            <button id="lp-notif-all" type="button" class="text-xs font-medium text-brand-600 hover:text-brand-900">Tandai semua dibaca</button>
                        </div>
                        <div id="lp-notif-list" class="max-h-96 overflow-y-auto p-2"></div>
                        <a href="/notifikasi" class="block border-t border-zinc-100 px-4 py-3 text-center text-xs font-semibold text-zinc-700 hover:bg-zinc-50">Lihat semua notifikasi</a>
                    </div>
                </div>

                <div class="relative">
                    <button id="lp-user-menu" class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white py-1.5 pl-1.5 pr-3 text-sm hover:bg-zinc-50">
                        <span class="relative grid h-8 w-8 place-items-center overflow-hidden rounded-full bg-zinc-900 text-xs font-bold text-white">
                            <span id="who-initial" class="grid place-items-center">?</span>
                            <img id="who-avatar" alt="" class="hidden h-full w-full object-cover">
                        </span>
                        <span class="hidden text-left leading-tight sm:block">
                            <span id="who-name" class="block text-xs font-semibold">-</span>
                            <span id="who-role" class="block text-[11px] text-zinc-500">-</span>
                        </span>
                    </button>
                    <div id="lp-user-panel" class="absolute right-0 mt-2 hidden w-60 rounded-xl border border-zinc-200 bg-white p-1.5 shadow-lg">
                        <a href="/profil" class="block rounded-lg px-3 py-2 text-sm hover:bg-zinc-50">Profil Saya</a>
                        <a href="/sesi" data-menu-role="manager" class="hidden block rounded-lg px-3 py-2 text-sm hover:bg-zinc-50">Sesi Login</a>
                        <a href="/pengaturan" data-menu-role="manager" class="hidden block rounded-lg px-3 py-2 text-sm hover:bg-zinc-50">Pengaturan Sistem</a>
                        <a href="/pengaturan-publik" data-menu-role="manager" class="hidden block rounded-lg px-3 py-2 text-sm hover:bg-zinc-50">Pengaturan Halaman Publik</a>
                        <button id="lp-logout" class="mt-1 block w-full rounded-lg bg-zinc-900 px-3 py-2 text-left text-sm font-medium text-white hover:bg-zinc-800">Keluar</button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-[1600px] gap-6 px-4 py-6 sm:px-6">
        <aside id="lp-aside" class="hidden w-64 shrink-0 lg:block max-lg:absolute max-lg:inset-x-4 max-lg:top-20 max-lg:z-30 max-lg:rounded-2xl max-lg:border max-lg:border-zinc-200 max-lg:bg-white max-lg:p-3 max-lg:shadow-xl">
            <nav id="lp-nav" class="sticky top-24 flex flex-col gap-1"></nav>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">@yield('page_title', 'Dashboard')</h1>
                    <p class="mt-1 text-sm text-zinc-500">@yield('page_desc')</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">@yield('page_actions')</div>
            </div>

            <div id="app-shell">@yield('content')</div>
        </main>
    </div>
</div>

<script src="/js/luminpark.js"></script>
@stack('scripts')
</body>
</html>
