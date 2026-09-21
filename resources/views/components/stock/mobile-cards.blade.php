@props([
    'items' => [],
])

@php
    $getStockMobileBadge = function ($status) {
        return match ($status) {
            'negative' => [
                'label' => 'Minus',
                'class' => 'bg-rose-100 dark:bg-rose-950/80 border border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-300 font-bold',
                'dot' => 'bg-rose-600',
            ],
            'empty' => [
                'label' => 'Habis',
                'class' => 'bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/60 text-rose-700 dark:text-rose-400',
                'dot' => 'bg-rose-500',
            ],
            'low' => [
                'label' => 'Menipis',
                'class' => 'bg-amber-50 dark:bg-amber-950/60 border border-amber-200/70 dark:border-amber-800/60 text-amber-700 dark:text-amber-400',
                'dot' => 'bg-amber-500',
            ],
            default => [
                'label' => 'Aman',
                'class' => 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400',
                'dot' => 'bg-emerald-500',
            ],
        };
    };
@endphp

<div id="mobileStockCards" class="md:hidden space-y-3">
    @foreach($items as $item)
        @php
            $stockStatus = $item['stock_status'] ?? 'safe';
            $badge = $getStockMobileBadge($stockStatus);
            $isNegative = $stockStatus === 'negative';
        @endphp
        <div
            class="stock-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
            data-id="{{ $item['id'] }}"
            data-name="{{ $item['name'] }}"
            data-sku="{{ $item['sku'] }}"
            data-category="{{ $item['category_name'] }}"
            data-stock-status="{{ $stockStatus }}"
            data-raw="{{ json_encode($item) }}"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $item['name'] }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        <span class="font-mono">{{ $item['sku'] }}</span> · {{ $item['category_name'] }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold {{ $badge['class'] }} shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
                    <span>{{ $badge['label'] }}</span>
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                <div>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Stok Saat Ini</span>
                    <span class="text-sm font-extrabold tabular-nums {{ $isNegative ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
                        {{ $item['current_stock'] }} {{ $item['unit'] }}
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Batas Minimum</span>
                    <span class="font-semibold text-slate-600 dark:text-slate-400 tabular-nums">
                        {{ $item['min_stock'] }} {{ $item['unit'] }}
                    </span>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                <button
                    type="button"
                    class="view-movement-btn px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors"
                >
                    Lihat Riwayat
                </button>
            </div>
        </div>
    @endforeach
</div>
