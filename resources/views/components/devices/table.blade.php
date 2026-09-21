@props([
    'devices' => [],
])

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopDeviceTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Perangkat</th>
                    <th scope="col" class="py-3.5 px-4">Identifier</th>
                    <th scope="col" class="py-3.5 px-4">Platform</th>
                    <th scope="col" class="py-3.5 px-4">Outlet</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4">Terdaftar</th>
                    <th scope="col" class="py-3.5 px-4">Terakhir Terlihat</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($devices as $device)
                    @php
                        $status = $device['status'] ?? '';
                        if ($status === 'active') {
                            $statusLabel = 'Aktif';
                            $statusBadgeClass = 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                            $statusDotClass = 'bg-emerald-500';
                        } elseif ($status === 'inactive') {
                            $statusLabel = 'Nonaktif';
                            $statusBadgeClass = 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                            $statusDotClass = 'bg-slate-400';
                        } else {
                            $statusLabel = ucwords(str_replace(['_', '-'], ' ', (string) $status));
                            $statusBadgeClass = 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                            $statusDotClass = 'bg-slate-400';
                        }
                        $lastSeenText = !empty($device['last_seen_at']) ? $device['last_seen_at'] : 'Belum Pernah Terlihat';
                    @endphp
                    <tr
                        class="device-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
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
                        {{-- 1. Perangkat --}}
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="tablet" class="w-3.5 h-3.5"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white truncate">{{ $device['name'] }}</p>
                                    @if(!empty($device['notes']))
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-xs">{{ $device['notes'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- 2. Identifier --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300 break-all">
                                {{ $device['identifier'] }}
                            </span>
                        </td>

                        {{-- 3. Platform --}}
                        <td class="py-3 px-4">
                            @if(!empty($device['platform']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ $device['platform'] }}
                                </span>
                            @else
                                <span class="text-slate-400 dark:text-slate-500 italic">Tidak Diketahui</span>
                            @endif
                        </td>

                        {{-- 4. Outlet --}}
                        <td class="py-3 px-4">
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ $device['outlet_name'] }}
                            </span>
                        </td>

                        {{-- 5. Status --}}
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $statusBadgeClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusDotClass }}"></span>
                                <span>{{ $statusLabel }}</span>
                            </span>
                        </td>

                        {{-- 6. Terdaftar --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $device['registered_at'] }}
                            </span>
                        </td>

                        {{-- 7. Terakhir Terlihat --}}
                        <td class="py-3 px-4">
                            @if(!empty($device['last_seen_at']))
                                <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                    {{ $device['last_seen_at'] }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                                    Belum Pernah Terlihat
                                </span>
                            @endif
                        </td>

                        {{-- 8. Aksi --}}
                        <td class="py-3 px-4 text-right">
                            <button
                                type="button"
                                class="view-device-detail-btn px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                Lihat Detail
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
