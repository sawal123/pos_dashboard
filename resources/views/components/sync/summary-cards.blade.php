@props([
    'summary' => [
        'server_sequence' => 0,
        'total_requests' => 0,
        'devices_with_push' => 0,
        'last_processed' => 'Belum Ada',
    ],
])

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    {{-- 1. Server Sync Sequence --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Server Sync Sequence</span>
            <h3 id="syncSummarySequence" class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight tabular-nums">
                {{ number_format($summary['server_sequence'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Nomor urut global server</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="hash" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 2. Push Request Tercatat --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Push Request Tercatat</span>
            <h3 id="syncSummaryRequests" class="text-xl sm:text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 tracking-tight tabular-nums">
                {{ number_format($summary['total_requests'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Total batch push diproses</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="arrow-up-circle" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 3. Perangkat dengan Push Tercatat --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Perangkat dengan Push Tercatat</span>
            <h3 id="syncSummaryDevices" class="text-xl sm:text-2xl font-extrabold text-slate-800 dark:text-slate-200 tracking-tight tabular-nums">
                {{ number_format($summary['devices_with_push'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Terminal pengirim batch</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700 shadow-xs">
            <i data-lucide="devices" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 4. Push Terakhir Diproses --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Push Terakhir Diproses</span>
            <h3 id="syncSummaryLastProcessed" class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white tracking-tight font-mono truncate">
                {{ $summary['last_processed'] ?? 'Belum Ada' }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Waktu proses batch terbaru</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-100 dark:border-emerald-800/60 shadow-xs">
            <i data-lucide="check-check" class="w-5 h-5"></i>
        </div>
    </div>
</div>
