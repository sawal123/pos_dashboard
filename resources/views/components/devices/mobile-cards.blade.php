@props([
    'devices' => [],
])

<div id="mobileDeviceCards" class="md:hidden space-y-3">
    @foreach($devices as $device)
        @php
            $statusRaw = $device['status_raw'] ?? $device['status'] ?? '';
            if ($statusRaw === 'active') {
                $statusLabel = 'Aktif';
                $statusBadgeClass = 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                $statusDotClass = 'bg-emerald-500';
            } elseif ($statusRaw === 'inactive') {
                $statusLabel = 'Nonaktif';
                $statusBadgeClass = 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                $statusDotClass = 'bg-slate-400';
            } else {
                $statusLabel = $device['status'] ?? ucwords(str_replace(['_', '-'], ' ', (string) $statusRaw));
                $statusBadgeClass = 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                $statusDotClass = 'bg-slate-400';
            }
            $lastSeenText = !empty($device['last_seen_at']) ? $device['last_seen_at'] : 'Belum Pernah Terlihat';
        @endphp
        <div
            class="device-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
            data-id="{{ $device['id'] }}"
            data-name="{{ strtolower($device['name']) }}"
            data-identifier="{{ strtolower($device['identifier']) }}"
            data-outlet="{{ $device['outlet_name'] }}"
            data-status="{{ $device['status'] }}"
            data-platform="{{ $device['platform'] ?? '' }}"
            data-registered-at-raw="{{ $device['registered_at_raw'] ?? '' }}"
            data-last-seen-at-raw="{{ $device['last_seen_at_raw'] ?? '' }}"
            data-raw="{{ json_encode($device) }}"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $device['name'] }}</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ $device['platform'] ?? 'Platform Tidak Diketahui' }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $statusBadgeClass }} shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDotClass }}"></span>
                    <span>{{ $statusLabel }}</span>
                </span>
            </div>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1 text-xs">
                <p class="text-slate-700 dark:text-slate-300 font-medium">
                    {{ $device['outlet_name'] }}
                </p>
                <p class="font-mono text-[11px] text-slate-500 dark:text-slate-400 break-all">
                    {{ $device['identifier'] }}
                </p>
            </div>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 block">Terakhir Terlihat</span>
                    @if(!empty($device['last_seen_at']))
                        <span class="font-mono text-[11px] text-slate-800 dark:text-slate-200 font-semibold">{{ $device['last_seen_at'] }}</span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                            Belum Pernah Terlihat
                        </span>
                    @endif
                </div>

                <button
                    type="button"
                    class="view-device-detail-btn py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 shrink-0"
                >
                    Lihat Detail
                </button>
            </div>
        </div>
    @endforeach
</div>
