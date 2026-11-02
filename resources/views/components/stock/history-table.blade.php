@props([
    'items' => [],
])

@php
    $formatQuantity = function (string $value): string {
        $number = (float) $value;

        return number_format($number, fmod($number, 1.0) === 0.0 ? 0 : 3, ',', '.');
    };
@endphp

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Waktu</th>
                    <th scope="col" class="py-3.5 px-4">Produk</th>
                    <th scope="col" class="py-3.5 px-4">Tipe</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Perubahan</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Stok</th>
                    <th scope="col" class="py-3.5 px-4">Oleh</th>
                    <th scope="col" class="py-3.5 px-4">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($items as $item)
                    @php
                        $quantity = (float) $item['quantity_change'];
                        $quantityClass = $quantity > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : ($quantity < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500');
                        $quantityPrefix = $quantity > 0 ? '+' : '';
                    @endphp
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $item['occurred_at'] }}
                        </td>
                        <td class="py-3 px-4">
                            <p class="font-bold text-slate-900 dark:text-white">{{ $item['product_name'] }}</p>
                            <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $item['product_sku'] }}</p>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ $item['movement_type'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-extrabold tabular-nums {{ $quantityClass }}">
                            {{ $quantityPrefix }}{{ $formatQuantity($item['quantity_change']) }} {{ $item['unit'] }}
                        </td>
                        <td class="py-3 px-4 text-center tabular-nums text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $formatQuantity($item['stock_before']) }} → {{ $formatQuantity($item['stock_after']) }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                            {{ $item['user_name'] ?? 'Perangkat' }}
                        </td>
                        <td class="py-3 px-4 text-slate-500 dark:text-slate-400 max-w-[220px] truncate" title="{{ $item['note'] ?? '' }}">
                            {{ $item['note'] ?? '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
