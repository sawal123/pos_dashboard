@props([
    'items' => [],
    'hasPhysicalProducts' => false,
    'totalAlertCount' => 0,
])

<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between">
    <div>
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-100 dark:border-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white text-base tracking-tight">
                        Perhatian Stok
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Item inventori di bawah batas minimum
                    </p>
                </div>
            </div>

            @if($totalAlertCount > 0)
                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/70 dark:border-amber-800/50 self-start sm:self-auto">
                    {{ $totalAlertCount }} Perlu Perhatian
                </span>
            @endif
        </div>

        {{-- Panel Body --}}
        @if(! $hasPhysicalProducts)
            <div class="py-8 text-center flex flex-col items-center justify-center">
                <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-2.5 text-slate-400 dark:text-slate-500">
                    <i data-lucide="package-x" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Belum Ada Data Stok</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Belum ada item produk fisik terdaftar pada bisnis ini.</p>
            </div>
        @elseif(empty($items))
            <div class="py-8 text-center flex flex-col items-center justify-center">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center mb-2.5 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Semua item inventori berada di atas batas minimum.</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Tidak ada produk dengan stok menipis, habis, atau minus.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($items as $item)
                    @php
                        $badgeClasses = match($item['status_key']) {
                            'negative' => 'text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 border-rose-200 dark:border-rose-800/60',
                            'empty' => 'text-rose-600 dark:text-rose-400 bg-rose-50/60 dark:bg-rose-950/40 border-rose-200/80 dark:border-rose-800/40',
                            default => 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 border-amber-200/80 dark:border-amber-800/50',
                        };
                        $barColor = match($item['status_key']) {
                            'negative' => 'bg-rose-600',
                            'empty' => 'bg-rose-500',
                            default => 'bg-amber-500',
                        };
                    @endphp
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</p>
                                <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full border {{ $badgeClasses }}">
                                    {{ $item['status'] }}
                                </span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mb-1 overflow-hidden">
                                <div class="{{ $barColor }} h-1.5 rounded-full" style="width: {{ $item['percentage'] }}%;"></div>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                <span>SKU: {{ $item['sku'] }}</span>
                                <span class="tabular-nums">Stok: {{ number_format((float) $item['stock'], 3, ',', '.') }} {{ $item['unit'] }} / Min: {{ number_format((float) $item['min_stock'], 3, ',', '.') }} {{ $item['unit'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Footer Info --}}
    <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
        <span>
            @if(! $hasPhysicalProducts)
                Belum ada data stok
            @elseif(empty($items))
                Stok terpantau aman
            @else
                Menampilkan {{ count($items) }} item prioritas tertinggi
            @endif
        </span>
        <a href="{{ route('stock.index') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
            Lihat Inventori <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>
</div>
