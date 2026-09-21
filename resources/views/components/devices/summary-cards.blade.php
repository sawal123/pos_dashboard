@props([
    'summary' => [
        'total_devices' => 0,
        'active_devices' => 0,
        'inactive_devices' => 0,
        'never_seen_devices' => 0,
    ],
])

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    {{-- 1. Total Perangkat --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Perangkat</span>
            <h3 id="deviceSummaryTotal" class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight tabular-nums">
                {{ number_format($summary['total_devices'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Semua terminal terdaftar</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="monitor-smartphone" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 2. Perangkat Aktif --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Perangkat Aktif</span>
            <h3 id="deviceSummaryActive" class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tracking-tight tabular-nums">
                {{ number_format($summary['active_devices'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Status operasional aktif</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-100 dark:border-emerald-800/60 shadow-xs">
            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 3. Perangkat Nonaktif --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Perangkat Nonaktif</span>
            <h3 id="deviceSummaryInactive" class="text-xl sm:text-2xl font-extrabold text-slate-600 dark:text-slate-300 tracking-tight tabular-nums">
                {{ number_format($summary['inactive_devices'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Dinonaktifkan dari sistem</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700 shadow-xs">
            <i data-lucide="shield-alert" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 4. Belum Pernah Terlihat --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Belum Pernah Terlihat</span>
            <h3 id="deviceSummaryNeverSeen" class="text-xl sm:text-2xl font-extrabold text-amber-600 dark:text-amber-400 tracking-tight tabular-nums">
                {{ number_format($summary['never_seen_devices'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Belum ada aktivitas tercatat</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 border border-amber-100 dark:border-amber-800/60 shadow-xs">
            <i data-lucide="clock-alert" class="w-5 h-5"></i>
        </div>
    </div>
</div>
