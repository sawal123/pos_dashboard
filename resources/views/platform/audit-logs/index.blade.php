<x-layouts::platform :title="'Audit Log'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Platform Audit Log
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Jejak audit permanen (append-only) untuk mutasi administratif berisiko tinggi oleh Platform Admin.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($logs->total()) }}</strong> Catatan</span>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            {{-- Total Audit Records --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Catatan Audit</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="history" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total']) }}
                </div>
            </div>

            {{-- Events Today --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Mutasi Hari Ini</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ number_format($summary['today']) }}
                </div>
            </div>

            {{-- Unique Actors --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aktor Platform Aktif</span>
                    <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-purple-600 dark:text-purple-400">
                    {{ number_format($summary['actors_count']) }}
                </div>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.audit-logs.index') }}" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                    {{-- Search query input --}}
                    <div class="lg:col-span-4 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] }}"
                            placeholder="Cari aktor, email, target, aksi..."
                            class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                    </div>

                    {{-- Action filter --}}
                    <div class="lg:col-span-3">
                        <select
                            name="action"
                            class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                            <option value="">Semua Aksi</option>
                            @foreach($actions as $key => $lbl)
                                <option value="{{ $key }}" {{ $filters['action'] === $key ? 'selected' : '' }}>
                                    {{ $lbl }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Type filter --}}
                    <div class="lg:col-span-2">
                        <select
                            name="target_type"
                            class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                            <option value="">Semua Target</option>
                            @foreach($targetTypes as $key => $lbl)
                                <option value="{{ $key }}" {{ $filters['target_type'] === $key ? 'selected' : '' }}>
                                    {{ $lbl }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Preset --}}
                    <div class="lg:col-span-2">
                        <select
                            name="date"
                            id="dateFilterSelect"
                            onchange="this.value === 'custom' ? document.getElementById('customDateRow').classList.remove('hidden') : document.getElementById('customDateRow').classList.add('hidden')"
                            class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                        >
                            <option value="30days" {{ $filters['date'] === '30days' ? 'selected' : '' }}>30 Hari Terakhir</option>
                            <option value="7days" {{ $filters['date'] === '7days' ? 'selected' : '' }}>7 Hari Terakhir</option>
                            <option value="today" {{ $filters['date'] === 'today' ? 'selected' : '' }}>Hari Ini</option>
                            <option value="custom" {{ $filters['date'] === 'custom' ? 'selected' : '' }}>Rentang Kustom...</option>
                            <option value="all" {{ $filters['date'] === 'all' ? 'selected' : '' }}>Semua Waktu</option>
                        </select>
                    </div>

                    {{-- Actions Button --}}
                    <div class="lg:col-span-1 flex items-center gap-1.5">
                        <button
                            type="submit"
                            class="w-full py-2 px-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center justify-center transition-colors shadow-xs"
                            title="Terapkan Filter"
                        >
                            <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        </button>
                        @if($filters['q'] || $filters['action'] || $filters['target_type'] || $filters['date'] !== '30days' || $filters['start_date'] || $filters['end_date'] || $filters['business_id'])
                            <a
                                href="{{ route('platform.audit-logs.index') }}"
                                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0"
                                title="Reset Filter"
                            >
                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Custom Date Range Row --}}
                <div id="customDateRow" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 {{ $filters['date'] === 'custom' ? '' : 'hidden' }}">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Tanggal Mulai</label>
                        <input
                            type="date"
                            name="start_date"
                            value="{{ $filters['start_date'] }}"
                            class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        >
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Tanggal Akhir</label>
                        <input
                            type="date"
                            name="end_date"
                            value="{{ $filters['end_date'] }}"
                            class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        >
                    </div>
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            @if($logs->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="shield-off" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Tidak ada data audit</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                        Tidak ditemukan catatan audit yang cocok dengan filter pencarian saat ini.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold">
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Aktor Platform</th>
                                <th class="py-3 px-4">Aksi</th>
                                <th class="py-3 px-4">Target</th>
                                <th class="py-3 px-4">Bisnis</th>
                                <th class="py-3 px-4 text-right">Opsi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
                            @foreach($logs as $log)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                    {{-- Waktu --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-bold text-slate-900 dark:text-white">
                                            {{ $log->created_at?->format('d M Y, H:i:s') }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                            {{ $log->created_at?->diffForHumans() }}
                                        </div>
                                    </td>

                                    {{-- Aktor Snapshot --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if($log->actor)
                                            <a
                                                href="{{ route('platform.users.show', $log->actor) }}"
                                                class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1.5"
                                            >
                                                <span>{{ $log->actor_name }}</span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                        @else
                                            <span class="font-bold text-slate-900 dark:text-white">{{ $log->actor_name }}</span>
                                        @endif
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                            {{ $log->actor_email }}
                                        </div>
                                    </td>

                                    {{-- Aksi --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-[11px] font-bold border {{ \App\Support\PlatformAuditAction::badgeClass($log->action) }}">
                                            {{ \App\Support\PlatformAuditAction::label($log->action) }}
                                        </span>
                                        <div class="text-[10px] font-mono text-slate-400 dark:text-slate-500 mt-0.5">
                                            {{ $log->action }}
                                        </div>
                                    </td>

                                    {{-- Target --}}
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 shrink-0">
                                                {{ \App\Support\PlatformAuditAction::targetTypeLabel($log->target_type) }}
                                            </span>
                                            <span class="font-semibold text-slate-900 dark:text-white truncate max-w-[200px]" title="{{ $log->target_label ?? ('ID #' . $log->target_id) }}">
                                                {{ $log->target_label ?? ('ID #' . $log->target_id) }}
                                            </span>
                                        </div>
                                        @if($log->target_id)
                                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">
                                                ID: {{ $log->target_id }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Bisnis Context --}}
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        @if($log->business)
                                            <a
                                                href="{{ route('platform.businesses.show', $log->business) }}"
                                                class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                                            >
                                                <span>{{ $log->business->name }}</span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                        @elseif($log->business_id)
                                            <span class="text-slate-600 dark:text-slate-400">Bisnis #{{ $log->business_id }}</span>
                                        @else
                                            <span class="text-slate-400 italic">Global Platform</span>
                                        @endif
                                    </td>

                                    {{-- Opsi --}}
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <a
                                            href="{{ route('platform.audit-logs.show', $log) }}"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 text-xs font-bold transition-colors shadow-2xs"
                                        >
                                            <i data-lucide="eye" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400"></i>
                                            <span>Detail</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                @if($logs->hasPages())
                    <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                        {{ $logs->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
</x-layouts::platform>
