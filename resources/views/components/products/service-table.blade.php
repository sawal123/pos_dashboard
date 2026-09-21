@props([
    'services' => [],
])

<div class="hidden md:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table id="desktopServiceTable" class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Layanan</th>
                    <th scope="col" class="py-3.5 px-4">SKU</th>
                    <th scope="col" class="py-3.5 px-4">Kategori</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Harga</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Satuan Harga</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Minimum</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Estimasi Durasi</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach($services as $service)
                    @php
                        $formattedPrice = 'Rp ' . number_format($service['price'], 0, ',', '.');
                        $unitStr = !empty($service['pricing_unit']) ? $service['pricing_unit'] : (!empty($service['unit']) ? $service['unit'] : '');
                        $pricingRateLabel = $unitStr !== '' ? 'Per ' . $unitStr : '-';
                        $priceDisplay = $unitStr !== '' ? $formattedPrice . '/' . $unitStr : $formattedPrice;
                    @endphp
                    <tr
                        class="service-row hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $service['id'] }}"
                        data-name="{{ $service['name'] }}"
                        data-sku="{{ $service['sku'] }}"
                        data-category="{{ $service['category_name'] }}"
                        data-status="{{ $service['status'] }}"
                        data-raw="{{ json_encode($service) }}"
                    >
                        {{-- 1. Nama Layanan --}}
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white truncate">{{ $service['name'] }}</p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Jasa / Layanan</p>
                                </div>
                            </div>
                        </td>

                        {{-- 2. SKU --}}
                        <td class="py-3 px-4">
                            <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ $service['sku'] }}</span>
                        </td>

                        {{-- 3. Kategori --}}
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                {{ $service['category_name'] }}
                            </span>
                        </td>

                        {{-- 4. Harga --}}
                        <td class="py-3 px-4 text-right tabular-nums font-bold text-slate-900 dark:text-white">
                            {{ $formattedPrice }}
                        </td>

                        {{-- 5. Satuan Harga --}}
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                                {{ $pricingRateLabel }}
                            </span>
                        </td>

                        {{-- 6. Minimum Quantity --}}
                        <td class="py-3 px-4 text-center tabular-nums text-slate-700 dark:text-slate-300">
                            {{ $service['min_quantity'] }} {{ $service['unit'] }}
                        </td>

                        {{-- 7. Estimasi Durasi --}}
                        <td class="py-3 px-4 text-center text-slate-600 dark:text-slate-400">
                            <div class="inline-flex items-center gap-1">
                                <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                <span>{{ $service['estimated_duration'] ?? '-' }}</span>
                            </div>
                        </td>

                        {{-- 8. Status --}}
                        <td class="py-3 px-4 text-center">
                            @if($service['status'] === 'active')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Aktif</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Nonaktif</span>
                                </span>
                            @endif
                        </td>

                        {{-- 9. Aksi --}}
                        <td class="py-3 px-4 text-right">
                            <button
                                type="button"
                                class="view-service-detail-btn px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
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
