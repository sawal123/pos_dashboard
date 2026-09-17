@props([
    'isOnline' => true,
    'syncedCount' => 348,
    'pendingCount' => 12,
    'lastSyncTime' => '1 menit yang lalu',
    'deviceName' => 'POS-TERMINAL-01',
    'deviceType' => 'Android Tablet (Kasir 1)',
])

<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between">
    <div>
        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <i data-lucide="cloud" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white text-base tracking-tight">Status Cloud & Perangkat</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sinkronisasi offline-first otomatis</p>
                </div>
            </div>

            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/80 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400 text-xs font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 {{ $isOnline ? 'animate-pulse' : '' }}"></span>
                {{ $isOnline ? 'Online' : 'Offline' }}
            </span>
        </div>

        {{-- Device Info Pill --}}
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="tablet" class="w-4 h-4 text-slate-500 dark:text-slate-400"></i>
                <div>
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 leading-tight">{{ $deviceName }}</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-tight">{{ $deviceType }}</p>
                </div>
            </div>
            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Terverifikasi</span>
        </div>

        {{-- Sync Metrics --}}
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 block mb-1">Tersinkron</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ $syncedCount }}</span>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Data</span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-amber-50/50 dark:bg-amber-950/30 border border-amber-100/80 dark:border-amber-900/40">
                <span class="text-xs text-amber-600/90 dark:text-amber-400 block mb-1">Antrean Outbox</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-amber-700 dark:text-amber-300 tabular-nums" id="pendingSyncCount">{{ $pendingCount }}</span>
                    <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">Tertunda</span>
                </div>
            </div>
        </div>

        {{-- Sync Progress / Status info --}}
        <div class="space-y-1.5 mb-4">
            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>Integritas Database</span>
                <span class="font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> 98% Sehat
                </span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" style="width: 98%;"></div>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 text-right">
                Sinkron terakhir: <span id="lastSyncLabel" class="font-medium text-slate-600 dark:text-slate-300">{{ $lastSyncTime }}</span>
            </p>
        </div>
    </div>

    {{-- Action Button --}}
    <button type="button" id="syncNowBtn" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium transition-all shadow-xs flex items-center justify-center gap-2">
        <i data-lucide="refresh-cw" class="w-4 h-4" id="syncIcon"></i>
        <span id="syncBtnText">Sinkronkan Sekarang</span>
    </button>
</div>
