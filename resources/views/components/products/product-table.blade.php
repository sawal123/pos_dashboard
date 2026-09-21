@props([
    'products' => [],
])

@php
    $getStockBadgeInfo = function ($status) {
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
        <table id="desktopProductTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Produk</th>
                    <th scope="col" class="py-3.5 px-4">SKU / Barcode</th>
                    <th scope="col" class="py-3.5 px-4">Kategori</th>
                    <th scope="col" class="py-3.5 px-4 text-right">HPP</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Harga Jual</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Stok</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($products as $product)
                    @php
                        $stockStatusKey = $product['stock_status'] ?? 'safe';
                        $stockBadge = $getStockBadgeInfo($stockStatusKey);
                        $costNum = (float) $product['cost'];
                        $formattedCost = (fmod($costNum, 1.0) !== 0.0)
                            ? 'Rp ' . number_format($costNum, 2, ',', '.')
                            : 'Rp ' . number_format($costNum, 0, ',', '.');
                        $formattedPrice = 'Rp ' . number_format($product['price'], 0, ',', '.');
                        $rawStatus = $product['status_raw'] ?? $product['status'];
                    @endphp
                    <tr
                        class="product-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $product['id'] }}"
                        data-name="{{ $product['name'] }}"
                        data-sku="{{ $product['sku'] }}"
                        data-barcode="{{ $product['barcode'] ?? '' }}"
                        data-category="{{ $product['category_name'] }}"
                        data-status="{{ $rawStatus }}"
                        data-stock-status="{{ $stockStatusKey }}"
                        data-raw="{{ json_encode($product) }}"
                    >
                        {{-- 1. Nama Produk --}}
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="package" class="w-3.5 h-3.5"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white truncate">{{ $product['name'] }}</p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Katalog Fisik</p>
                                </div>
                            </div>
                        </td>

                        {{-- 2. SKU / Barcode --}}
                        <td class="py-3 px-4">
                            <div class="font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                {{ $product['sku'] }}
                            </div>
                            @if(!empty($product['barcode']))
                                <div class="font-mono text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 flex items-center gap-1">
                                    <i data-lucide="barcode" class="w-3 h-3 text-slate-400"></i>
                                    <span>{{ $product['barcode'] }}</span>
                                </div>
                            @endif
                        </td>

                        {{-- 3. Kategori --}}
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ $product['category_name'] }}
                            </span>
                        </td>

                        {{-- 4. HPP --}}
                        <td class="py-3 px-4 text-right tabular-nums text-slate-500 dark:text-slate-400">
                            {{ $formattedCost }}
                        </td>

                        {{-- 5. Harga Jual --}}
                        <td class="py-3 px-4 text-right tabular-nums font-bold text-slate-900 dark:text-white">
                            {{ $formattedPrice }}
                        </td>

                        {{-- 6. Stok & Badge --}}
                        <td class="py-3 px-4 text-center">
                            <div class="inline-flex flex-col items-center">
                                <span class="font-extrabold tabular-nums {{ $stockStatusKey === 'negative' ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ $product['stock'] }} {{ $product['unit'] }}
                                </span>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] {{ $stockBadge['class'] }} mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $stockBadge['dot'] }}"></span>
                                    <span>{{ $stockBadge['label'] }}</span>
                                </span>
                            </div>
                        </td>

                        {{-- 7. Status Produk --}}
                        <td class="py-3 px-4 text-center">
                            @if($rawStatus === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Aktif</span>
                                </span>
                            @elseif($rawStatus === 'inactive')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>{{ $product['status'] ?? $rawStatus }}</span>
                                </span>
                            @endif
                        </td>

                        {{-- 8. Aksi --}}
                        <td class="py-3 px-4 text-right">
                            <button
                                type="button"
                                class="view-product-detail-btn px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
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
