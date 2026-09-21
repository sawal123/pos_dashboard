{{-- ==================== CASH / EXPENSE DETAIL DRAWER ==================== --}}
<div
    id="cashDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cashDrawerTitle"
>
    {{-- Backdrop --}}
    <div
        id="cashDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        id="cashDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div>
                <span id="cashDrawerSubtitle" class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    Detail Transaksi Kas
                </span>
                <h2 id="cashDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                    -
                </h2>
            </div>
            <button
                type="button"
                id="closeCashDrawerBtn"
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail transaksi"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Drawer Scrollable Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5 text-xs sm:text-sm">

            {{-- Nominal Hero Card --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 flex items-center justify-between gap-3">
                <div>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Nominal</span>
                    <h3 id="cashDrawerAmount" class="font-extrabold text-xl sm:text-2xl text-slate-900 dark:text-white tabular-nums">-</h3>
                </div>
                <div id="cashDrawerBadgeContainer">
                    {{-- Dynamically populated badge --}}
                </div>
            </div>

            {{-- Main Attributes Table / Key-Values --}}
            <div class="space-y-3">
                <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                    Informasi Transaksi
                </h4>
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800 overflow-hidden text-xs">
                    <div id="drawerRowOccurredAt" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Tanggal & Waktu</span>
                        <span id="cashDrawerOccurredAt" class="font-medium text-slate-900 dark:text-white font-mono">-</span>
                    </div>
                    <div id="drawerRowOutlet" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Outlet</span>
                        <span id="cashDrawerOutlet" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div id="drawerRowShift" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Shift</span>
                        <span id="cashDrawerShift" class="font-mono text-slate-700 dark:text-slate-300">-</span>
                    </div>
                    <div id="drawerRowCategory" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Kategori</span>
                        <span id="cashDrawerCategory" class="font-medium text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div id="drawerRowRef" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">ID Referensi</span>
                        <span id="cashDrawerRef" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div id="drawerRowSaleSync" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">ID Sinkronisasi Penjualan</span>
                        <span id="cashDrawerSaleSync" class="font-mono text-[11px] text-slate-600 dark:text-slate-400">-</span>
                    </div>
                    <div id="drawerRowStatus" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900">
                        <span class="text-slate-500 dark:text-slate-400">Status</span>
                        <span id="cashDrawerStatus" class="font-medium text-slate-800 dark:text-slate-200">-</span>
                    </div>
                </div>
            </div>

            {{-- Notes / Description Section (only if present) --}}
            <div id="drawerNotesContainer" class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-1">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Catatan</span>
                <p id="cashDrawerNotes" class="text-xs text-slate-700 dark:text-slate-300 italic">-</p>
            </div>

        </div>

        {{-- Drawer Footer --}}
        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                id="closeCashDrawerFooterBtn"
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
