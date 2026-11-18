@props(['outlets', 'canManageOutlets' => false, 'plans' => [], 'checkoutAvailable' => false])

@php
    $formatRupiah = fn ($amount) => 'Rp '.number_format((int) $amount, 0, ',', '.');
    $badgeClass = function (string $status): string {
        return match ($status) {
            'active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'inactive' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
    $subBadgeClass = function (string $state): string {
        return match ($state) {
            'cloud_active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'cloud_expired' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
@endphp

<div class="hidden lg:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
        <thead class="bg-slate-50 dark:bg-slate-800/60">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Alamat</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status & Langganan</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Perangkat</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Shift Open</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Transaksi</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Terakhir</th>
                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($outlets as $outlet)
                @php($sub = $outlet['subscription'] ?? ['state' => 'free', 'label' => '-', 'expires_at' => null, 'cloud_access' => false])
                <tr class="outlet-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $outlet['name'] }}</span>
                            @if(! empty($outlet['is_primary']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-[10px] font-bold uppercase">
                                    Utama
                                </span>
                            @endif
                        </div>
                        <div class="text-xs font-mono text-slate-500 dark:text-slate-400">
                            {{ $outlet['code'] }}
                            @if(! empty($outlet['phone']))
                                · {{ $outlet['phone'] }}
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300 max-w-[16rem]">
                        {{ $outlet['address'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3 space-y-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($outlet['status_raw']) }}">
                                {{ $outlet['status'] }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[11px] font-semibold {{ $subBadgeClass($sub['state']) }}">
                                {{ $sub['label'] }}
                            </span>
                        </div>
                        @if(! empty($sub['expires_at']))
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                Berlaku s/d {{ $sub['expires_at'] }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                        {{ number_format((int) $outlet['device_count'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                        {{ number_format((int) $outlet['open_shift_count'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="font-bold text-slate-900 dark:text-white tabular-nums">{{ $formatRupiah($outlet['sales_total']) }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $outlet['sales_count'] }} transaksi</div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                        {{ $outlet['last_transaction_at'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                            @if($canManageOutlets && empty($outlet['is_primary']) && empty($sub['cloud_access']))
                                <button
                                    type="button"
                                    class="open-outlet-checkout-btn inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors"
                                    data-outlet-id="{{ $outlet['id'] }}"
                                    data-outlet-name="{{ $outlet['name'] }}"
                                >
                                    <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                                    Bayar Cabang
                                </button>
                            @elseif($canManageOutlets && empty($outlet['is_primary']) && ! empty($sub['cloud_access']))
                                <button
                                    type="button"
                                    class="open-outlet-checkout-btn inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-xl border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 font-semibold text-xs transition-colors"
                                    data-outlet-id="{{ $outlet['id'] }}"
                                    data-outlet-name="{{ $outlet['name'] }}"
                                    data-allow-renewal="1"
                                >
                                    Perpanjang
                                </button>
                            @endif

                            @if($canManageOutlets)
                                <button
                                    type="button"
                                    class="edit-outlet-btn inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors"
                                    data-update-url="{{ route('outlets.update', $outlet['id']) }}"
                                    data-outlet-name="{{ $outlet['name'] }}"
                                    data-outlet-code="{{ $outlet['code'] }}"
                                    data-outlet-phone="{{ $outlet['phone'] ?? '' }}"
                                    data-outlet-address="{{ $outlet['address'] ?? '' }}"
                                >
                                    Edit
                                </button>

                                @if(empty($outlet['is_primary']) && $outlet['status_raw'] === 'active')
                                    <form method="POST" action="{{ route('outlets.status', $outlet['id']) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="inactive">
                                        <button type="submit" class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-600 hover:text-rose-700 dark:text-slate-300 font-semibold text-xs transition-colors">
                                            Nonaktifkan
                                        </button>
                                    </form>
                                @elseif(empty($outlet['is_primary']) && $outlet['status_raw'] === 'inactive' && ! empty($sub['cloud_access']))
                                    <form method="POST" action="{{ route('outlets.status', $outlet['id']) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-xl border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 text-emerald-700 dark:text-emerald-300 font-semibold text-xs transition-colors">
                                            Aktifkan
                                        </button>
                                    </form>
                                @endif
                            @endif

                            <button
                                type="button"
                                class="view-outlet-detail-btn inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                data-detail-url="{{ route('outlets.detail', $outlet['id']) }}"
                            >
                                <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                                Detail
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
