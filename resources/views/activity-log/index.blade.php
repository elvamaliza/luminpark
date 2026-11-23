@extends('layouts.dashboard')

@section('title', 'Log Aktivitas')
@section('heading', 'Log Aktivitas')

@section('content')
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Riwayat aktivitas pengguna</h2>
            <p class="mt-1 text-sm text-slate-500">Login, logout, dan reset password tercatat otomatis.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">User</th>
                        <th class="px-5 py-3 font-medium">Action</th>
                        <th class="px-5 py-3 font-medium">Description</th>
                        <th class="px-5 py-3 font-medium">IP Address</th>
                        <th class="px-5 py-3 font-medium">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-900">{{ $log->user->name ?? '—' }}</p>
                                <p class="text-xs text-slate-500">{{ $log->user->email ?? '' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $log->description }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $log->ip_address ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                                {{ optional($log->created_at)->timezone(config('app.timezone'))->format('d M Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500">Belum ada log aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="flex items-center justify-between border-t border-slate-200 px-5 py-4 text-sm text-slate-600">
                <div>
                    Menampilkan {{ $logs->firstItem() }}–{{ $logs->lastItem() }} dari {{ $logs->total() }}
                </div>
                <div class="flex gap-2">
                    @if ($logs->onFirstPage())
                        <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-400">Sebelumnya</span>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}" class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50">Sebelumnya</a>
                    @endif

                    @if ($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}" class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50">Berikutnya</a>
                    @else
                        <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-400">Berikutnya</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
