@props([
    'items' => null,
    'activeTab' => 'products',
    'products' => [],
    'services' => [],
    'categories' => [],
])

@php
    $resolvedProducts = $items !== null && $activeTab === 'products' ? $items : $products;
    $resolvedServices = $items !== null && $activeTab === 'services' ? $items : $services;
    $resolvedCategories = $items !== null && $activeTab === 'categories' ? $items : $categories;

    $getMobileStockBadgeInfo = function ($status) {
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

<div class="md:hidden space-y-3">
    {{-- 1. Mobile Product Cards Container --}}
    @if($activeTab === 'products' && count($resolvedProducts) > 0)
        <div id="mobileProductCards" class="space-y-3">
            @foreach($resolvedProducts as $product)
                @php
                    $stockStatusKey = $product['stock_status'] ?? 'safe';
                    $stockBadge = $getMobileStockBadgeInfo($stockStatusKey);
                    $costNum = (float) $product['cost'];
                    $formattedCost = (fmod($costNum, 1.0) !== 0.0)
                        ? 'Rp ' . number_format($costNum, 2, ',', '.')
                        : 'Rp ' . number_format($costNum, 0, ',', '.');
                    $formattedPrice = 'Rp ' . number_format($product['price'], 0, ',', '.');
                    $rawStatus = $product['status_raw'] ?? $product['status'];
                @endphp
                <div
                    class="product-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
                    data-id="{{ $product['id'] }}"
                    data-name="{{ $product['name'] }}"
                    data-sku="{{ $product['sku'] }}"
                    data-barcode="{{ $product['barcode'] ?? '' }}"
                    data-category="{{ $product['category_name'] }}"
                    data-status="{{ $rawStatus }}"
                    data-stock-status="{{ $stockStatusKey }}"
                    data-raw="{{ json_encode($product) }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $product['name'] }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                <span class="font-mono">{{ $product['sku'] }}</span> · {{ $product['category_name'] }}
                            </p>
                        </div>
                        <span class="font-extrabold text-sm text-slate-900 dark:text-white tabular-nums shrink-0">
                            {{ $formattedPrice }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <div>
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 block">HPP</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">{{ $formattedCost }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Stok Saat Ini</span>
                            <div class="flex items-center justify-end gap-1.5 mt-0.5">
                                <span class="font-extrabold tabular-nums {{ $stockStatusKey === 'negative' ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ $product['stock'] }} {{ $product['unit'] }}
                                </span>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] {{ $stockBadge['class'] }}">
                                    <span>{{ $stockBadge['label'] }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div>
                            @if($rawStatus === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Aktif</span>
                                </span>
                            @elseif($rawStatus === 'inactive')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>{{ $product['status'] ?? $rawStatus }}</span>
                                </span>
                            @endif
                        </div>
                        <button
                            type="button"
                            class="view-product-detail-btn px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors"
                        >
                            Lihat Detail
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- 2. Mobile Service Cards Container --}}
    @if($activeTab === 'services' && count($resolvedServices) > 0)
        <div id="mobileServiceCards" class="space-y-3">
            @foreach($resolvedServices as $service)
                @php
                    $formattedPrice = 'Rp ' . number_format($service['price'], 0, ',', '.');
                    $unitStr = !empty($service['pricing_unit']) ? $service['pricing_unit'] : (!empty($service['unit']) ? $service['unit'] : '');
                    $priceSuffix = $unitStr !== '' ? '/' . $unitStr : '';
                    $rawStatus = $service['status_raw'] ?? $service['status'];
                @endphp
                <div
                    class="service-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
                    data-id="{{ $service['id'] }}"
                    data-name="{{ $service['name'] }}"
                    data-sku="{{ $service['sku'] }}"
                    data-category="{{ $service['category_name'] }}"
                    data-status="{{ $rawStatus }}"
                    data-raw="{{ json_encode($service) }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $service['name'] }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                <span class="font-mono">{{ $service['sku'] }}</span> · {{ $service['category_name'] }}
                            </p>
                        </div>
                        <span class="font-extrabold text-sm text-indigo-600 dark:text-indigo-400 tabular-nums shrink-0">
                            {{ $formattedPrice }}{{ $priceSuffix }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <div>
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Minimum Order</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">{{ $service['min_quantity'] }}{{ !empty($service['unit']) ? ' ' . $service['unit'] : '' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Estimasi Durasi</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $service['estimated_duration'] ?? '-' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div>
                            @if($rawStatus === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Aktif</span>
                                </span>
                            @elseif($rawStatus === 'inactive')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>{{ $service['status'] ?? $rawStatus }}</span>
                                </span>
                            @endif
                        </div>
                        <button
                            type="button"
                            class="view-service-detail-btn px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors"
                        >
                            Lihat Detail
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- 3. Mobile Category Cards Container --}}
    @if($activeTab === 'categories' && count($resolvedCategories) > 0)
        <div id="mobileCategoryCards" class="space-y-3">
            @foreach($resolvedCategories as $cat)
                @php
                    $rawStatus = $cat['status_raw'] ?? $cat['status'];
                @endphp
                <div
                    class="category-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
                    data-id="{{ $cat['id'] }}"
                    data-name="{{ $cat['name'] }}"
                    data-status="{{ $rawStatus }}"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-sm">{{ $cat['name'] }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $cat['items_count'] }} item terdaftar
                            </p>
                        </div>
                        @if($rawStatus === 'active')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span>Aktif</span>
                            </span>
                        @elseif($rawStatus === 'inactive')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                <span>Nonaktif</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                <span>{{ $cat['status'] ?? $rawStatus }}</span>
                            </span>
                        @endif
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                        <button
                            type="button"
                            onclick="showToast('info', 'Pengelolaan kategori dari dashboard belum tersedia.')"
                            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors"
                        >
                            Kelola
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
