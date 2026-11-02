@props([
    'items' => [],
])

@php
    $formatQuantity = function (string $value): string {
        $number = (float) $value;

        return number_format($number, fmod($number, 1.0) === 0.0 ? 0 : 3, ',', '.');
    };
@endphp

<div class="md:hidden space-y-3">
    @foreach($items as $item)
        @php
            $quantity = (float) $item['quantity_change'];
            $quantityClass = $quantity > 0
                ? 'text-emerald-600 dark:text-emerald-400'
                : ($quantity < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500');
            $quantityPrefix = $quantity > 0 ? '+' : '';
        @endphp
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $item['product_name'] }}</p>
                    <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $item['product_sku'] }}</p>
                </div>
                <span class="shrink-0 text-sm font-extrabold tabular-nums {{ $quantityClass }}">
                    {{ $quantityPrefix }}{{ $formatQuantity($item['quantity_change']) }} {{ $item['unit'] }}
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-medium">
                    {{ $item['movement_type'] }}
                </span>
                <span class="tabular-nums">
                    {{ $formatQuantity($item['stock_before']) }} → {{ $formatQuantity($item['stock_after']) }}
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 text-[11px] text-slate-400 dark:text-slate-500">
                <span>{{ $item['occurred_at'] }}</span>
                <span>{{ $item['user_name'] ?? 'Perangkat' }}</span>
            </div>

            @if(!empty($item['note']))
                <p class="text-[11px] italic text-slate-600 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800">
                    {{ $item['note'] }}
                </p>
            @endif
        </div>
    @endforeach
</div>
