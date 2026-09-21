@props([
    'mode' => 'cafe', // 'cafe', 'laundry', 'all'
    'showTabs' => false,
])

@php
    $isLaundryOnly = $mode === 'laundry';
    $isCafeOnly = $mode === 'cafe';
    $showSegmentSwitcher = $showTabs || $mode === 'all';
@endphp

<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between">
    <div>
        {{-- Header & Mode Tabs --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-100 dark:border-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <i data-lucide="{{ $isLaundryOnly ? 'washing-machine' : 'alert-triangle' }}" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white text-base tracking-tight">
                        {{ $isLaundryOnly ? 'Wawasan Operasional Laundry' : 'Peringatan Stok Bahan Baku' }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $isLaundryOnly ? 'Pesanan aktif & siap diambil' : 'Inventaris stok minimum perlu pengadaan' }}
                    </p>
                </div>
            </div>

            {{-- Segment Switcher (Only visible if showTabs=true or mode='all') --}}
            @if($showSegmentSwitcher)
                <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs font-medium self-start sm:self-auto">
                    <button type="button" id="tabStockAlertBtn" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs font-semibold transition-all">
                        Stok Rendah
                    </button>
                    <button type="button" id="tabLaundryAlertBtn" class="px-2.5 py-1 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all">
                        Pesanan Laundry
                    </button>
                </div>
            @endif
        </div>

        {{-- Panel 1: Low Stock Alert (Cafe / Retail) --}}
        @if(!$isLaundryOnly)
            <div id="panelStockAlert" class="space-y-3">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">Biji Kopi Arabika 1kg</p>
                            <span class="text-xs font-bold text-rose-600 dark:text-rose-400 tabular-nums">Sisa 3 pack</span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mb-1 overflow-hidden">
                            <div class="bg-rose-500 h-1.5 rounded-full" style="width: 25%;"></div>
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Min. stok: 12 pack · Kategori: Bahan Baku Kopi</p>
                    </div>
                    <button type="button" class="restock-trigger-btn px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium transition-colors shrink-0">
                        Restock
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">Susu UHT Full Cream 1L</p>
                            <span class="text-xs font-bold text-amber-600 dark:text-amber-400 tabular-nums">Sisa 4 kotak</span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mb-1 overflow-hidden">
                            <div class="bg-amber-500 h-1.5 rounded-full" style="width: 33%;"></div>
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Min. stok: 12 kotak · Kategori: Susu & Dairy</p>
                    </div>
                    <button type="button" class="restock-trigger-btn px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium transition-colors shrink-0">
                        Restock
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">Sirup Karamel 750ml</p>
                            <span class="text-xs font-bold text-rose-600 dark:text-rose-400 tabular-nums">Sisa 2 botol</span>
                        </div>
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mb-1 overflow-hidden">
                            <div class="bg-rose-500 h-1.5 rounded-full" style="width: 25%;"></div>
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Min. stok: 8 botol · Kategori: Bahan Baku Sirup</p>
                    </div>
                    <button type="button" class="restock-trigger-btn px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium transition-colors shrink-0">
                        Restock
                    </button>
                </div>
            </div>
        @endif

        {{-- Panel 2: Laundry Operational Orders (Only when laundry mode or switcher enabled) --}}
        @if($isLaundryOnly || $showSegmentSwitcher)
            <div id="panelLaundryAlert" class="space-y-3 {{ !$isLaundryOnly ? 'hidden' : '' }}">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">LND-2609-012 · 5.4 kg Kiloan</p>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                Siap Diambil
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Pelanggan: Ibu Citra · Rak: B-04 · Tag: Wangi Lavender</p>
                    </div>
                    <button type="button" class="pickup-trigger-btn px-2.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition-colors shrink-0">
                        Ambil
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">LND-2609-015 · Bed Cover King</p>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                                Proses Setrika
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Pelanggan: Pak Hendra · Target: 17:00 Hari ini</p>
                    </div>
                    <span class="text-xs text-slate-400 dark:text-slate-500 shrink-0 font-medium">Pengering 02</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">LND-2609-018 · 3.2 kg Express</p>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                                Antrean Cuci
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Pelanggan: Dian S. · Target 3 Jam · Prioritas</p>
                    </div>
                    <span class="text-xs text-amber-600 dark:text-amber-400 shrink-0 font-medium">Mesin 01</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Footer Info --}}
    <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
        <span id="insightFooterLabel">
            {{ $isLaundryOnly ? '5 pesanan aktif dalam pengerjaan' : '3 SKU bahan baku butuh pengadaan ulang' }}
        </span>
        <a href="#inventory" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
            Lihat Semua <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>
</div>
