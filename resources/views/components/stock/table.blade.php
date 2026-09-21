@props([
    'items' => [],
])

@php
    $getStockItemBadge = function ($status) {
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

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopStockTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Produk</th>
                    <th scope="col" class="py-3.5 px-4">SKU</th>
                    <th scope="col" class="py-3.5 px-4">Kategori</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Stok Saat Ini</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Batas Minimum</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Satuan</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($items as $item)
                    @php
                        $st = (float) $item['current_stock'];
                        $mst = (float) $item['min_stock'];
                        if ($st < 0) {
                            $stockStatus = 'negative';
                        } elseif ($st == 0.0) {
                            $stockStatus = 'empty';
                        } elseif ($st <= $mst) {
                            $stockStatus = 'low';
                        } else {
                            $stockStatus = 'safe';
                        }
                        $badge = $getStockItemBadge($stockStatus);
                        $isNegative = $st < 0;
                    @endphp
                    <tr
                        class="stock-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $item['id'] }}"
                        data-name="{{ $item['name'] }}"
                        data-sku="{{ $item['sku'] }}"
                        data-category="{{ $item['category_name'] }}"
                        data-stock-status="{{ $stockStatus }}"
                        data-raw="{{ json_encode(array_merge($item, ['stock_status' => $stockStatus])) }}"
                    >
                        {{-- 1. Produk --}}
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="box" class="w-3.5 h-3.5"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white">{{ $item['name'] }}</p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Inventori Fisik</p>
                                </div>
                            </div>
                        </td>

                        {{-- 2. SKU --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ $item['sku'] }}</span>
                        </td>

                        {{-- 3. Kategori --}}
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ $item['category_name'] }}
                            </span>
                        </td>

                        {{-- 4. Stok Saat Ini --}}
                        <td class="py-3 px-4 text-center">
                            <span class="text-sm font-extrabold tabular-nums {{ $isNegative ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
                                {{ $item['current_stock'] }}
                            </span>
                        </td>

                        {{-- 5. Batas Minimum --}}
                        <td class="py-3 px-4 text-center tabular-nums text-slate-500 dark:text-slate-400">
                            {{ $item['min_stock'] }}
                        </td>

                        {{-- 6. Satuan --}}
                        <td class="py-3 px-4 text-center text-slate-600 dark:text-slate-300 font-semibold">
                            {{ $item['unit'] }}
                        </td>

                        {{-- 7. Status --}}
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold {{ $badge['class'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
                                <span>{{ $badge['label'] }}</span>
                            </span>
                        </td>

                        {{-- 8. Aksi --}}
                        <td class="py-3 px-4 text-right">
                            <button
                                type="button"
                                class="view-movement-btn px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                Lihat Riwayat
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
