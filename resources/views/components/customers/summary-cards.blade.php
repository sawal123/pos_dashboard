@props([
    'summary' => [
        'total_customers' => 0,
        'active_customers' => 0,
        'customers_with_purchases' => 0,
        'customers_without_purchases' => 0,
    ],
])

@php
    $cards = [
        [
            'id' => 'customerSummaryTotal',
            'label' => 'Total Pelanggan',
            'value' => $summary['total_customers'] ?? 0,
            'icon' => 'users',
            'iconClass' => 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400',
            'hint' => 'Tidak termasuk pelanggan dihapus',
        ],
        [
            'id' => 'customerSummaryActive',
            'label' => 'Pelanggan Aktif',
            'value' => $summary['active_customers'] ?? 0,
            'icon' => 'user-check',
            'iconClass' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400',
            'hint' => 'Berstatus aktif',
        ],
        [
            'id' => 'customerSummaryWithPurchases',
            'label' => 'Sudah Bertransaksi',
            'value' => $summary['customers_with_purchases'] ?? 0,
            'icon' => 'shopping-bag',
            'iconClass' => 'bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400',
            'hint' => 'Punya transaksi selesai & lunas',
        ],
        [
            'id' => 'customerSummaryWithoutPurchases',
            'label' => 'Belum Bertransaksi',
            'value' => $summary['customers_without_purchases'] ?? 0,
            'icon' => 'user-x',
            'iconClass' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400',
            'hint' => 'Belum ada transaksi selesai & lunas',
        ],
    ];
@endphp

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
    @foreach($cards as $card)
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
            <div class="flex items-center justify-between gap-2 mb-2">
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">{{ $card['label'] }}</span>
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 {{ $card['iconClass'] }}">
                    <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4"></i>
                </div>
            </div>
            <div id="{{ $card['id'] }}" class="text-lg sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format((int) $card['value'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ $card['hint'] }}</p>
        </div>
    @endforeach
</section>
