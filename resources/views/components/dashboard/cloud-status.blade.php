@props([
    'serverSequence' => 0,
    'totalPushRequests' => 0,
    'devicesWithPush' => 0,
    'lastProcessedAt' => 'Belum ada push yang tercatat.',
    'totalRegisteredDevices' => 0,
    'hasCloudAccess' => false,
    'cloudAccessLabel' => 'Tidak ada akses cloud',
])

@php
    $badgeClass = $hasCloudAccess
        ? 'bg-indigo-50 dark:bg-indigo-950/50 border-indigo-200/80 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-400'
        : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
    $dotClass = $hasCloudAccess ? 'bg-indigo-500' : 'bg-slate-400';
    // DASH-10B2 — only show the sync link when the role may actually open it.
    $syncPermissions = $dashboardPermissions ?? [];
    $canViewSync = in_array('*', $syncPermissions, true)
        || in_array(\App\Services\Authorization\BusinessPermission::SYNC_VIEW, $syncPermissions, true);
@endphp

<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between">
    <div>
        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <i data-lucide="cloud" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white text-base tracking-tight">Status Cloud</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan urutan server & telemetry push</p>
                </div>
            </div>

            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold {{ $badgeClass }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                {{ $cloudAccessLabel }}
            </span>
        </div>

        {{-- Metrics Grid --}}
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 block mb-1">Server Sequence</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($serverSequence, 0, ',', '.') }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Seq</span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 block mb-1">Push Request Tercatat</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($totalPushRequests, 0, ',', '.') }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Request</span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 block mb-1">Perangkat Terdaftar</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($totalRegisteredDevices, 0, ',', '.') }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Unit</span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                <span class="text-xs text-slate-500 dark:text-slate-400 block mb-1">Perangkat Pernah Push</span>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-xl font-bold text-slate-900 dark:text-white tabular-nums">{{ number_format($devicesWithPush, 0, ',', '.') }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Device</span>
                </div>
            </div>
        </div>

        {{-- Operational info --}}
        <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2 mb-4">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">Push Terakhir Diproses</span>
                <span class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-1">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-indigo-500"></i>
                    <span>{{ $lastProcessedAt }}</span>
                </span>
            </div>
        </div>
    </div>

    {{-- Action Link --}}
    @if($canViewSync)
        <a href="{{ route('sync.index') }}" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium transition-all shadow-xs flex items-center justify-center gap-2">
            <i data-lucide="history" class="w-4 h-4"></i>
            <span>Lihat Riwayat Sinkronisasi</span>
        </a>
    @endif
</div>
