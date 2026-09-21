{{-- ==================== STOCK MOVEMENT DRAWER ==================== --}}
<div
    id="stockDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="stockDrawerTitle"
>
    {{-- Backdrop --}}
    <div
        id="stockDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        id="stockDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div>
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    Riwayat Pergerakan Stok
                </span>
                <h2 id="stockDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                    -
                </h2>
            </div>
            <button
                type="button"
                id="closeStockDrawerBtn"
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup riwayat stok"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Drawer Scrollable Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5 text-xs sm:text-sm">

            {{-- 1. Product Stock Summary Card --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-200/60 dark:border-slate-700/60">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">SKU</span>
                        <span id="stockDetailSku" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <span id="stockDetailBadge" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold">
                        -
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs pt-1">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Stok Saat Ini</span>
                        <span id="stockDetailCurrent" class="font-extrabold text-base text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Batas Minimum</span>
                        <span id="stockDetailMin" class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">-</span>
                    </div>
                </div>
            </div>

            {{-- 2. Movement History Timeline Section --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Log Perubahan Stok
                </h3>
                <div id="stockMovementsContainer" class="space-y-2.5">
                    {{-- Dynamically populated with safe DOM methods --}}
                </div>
                <div id="stockMovementsHasMoreInfo" class="hidden text-[11px] text-slate-500 dark:text-slate-400 text-center py-2 font-medium">
                    Menampilkan 50 pergerakan terbaru.
                </div>
            </div>

        </div>

        {{-- Drawer Footer --}}
        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                id="closeStockDrawerFooterBtn"
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
