{{-- ==================== CUSTOMER DETAIL DRAWER (READ-ONLY) ==================== --}}
<div
    id="customerDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="customerDrawerTitle"
>
    {{-- Backdrop --}}
    <div
        id="customerDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        id="customerDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div class="min-w-0">
                <span id="customerDrawerSubtitle" class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    Detail Pelanggan
                </span>
                <h2 id="customerDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight break-words">
                    -
                </h2>
            </div>
            <button
                type="button"
                data-close-customer-drawer
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail pelanggan"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Scrollable Body --}}
        <div class="flex-1 overflow-y-auto p-5">
            <div id="customerDrawerLoading" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                Memuat detail pelanggan...
            </div>

            <div id="customerDrawerError" class="hidden rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30 p-4 text-sm text-rose-700 dark:text-rose-300">
                Detail pelanggan tidak dapat dimuat. Silakan coba lagi.
            </div>

            <div id="customerDrawerContent" class="hidden space-y-5 text-sm">
                {{-- Identity + contact --}}
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                    <div class="flex items-center justify-between gap-3 pb-3 border-b border-slate-200/60 dark:border-slate-700/60">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Status Pelanggan</span>
                        <span id="customerDrawerStatus" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 text-xs">
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Telepon</span>
                            <span id="customerDrawerPhone" class="font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Email</span>
                            <span id="customerDrawerEmail" class="font-semibold text-slate-900 dark:text-white break-all">-</span>
                        </div>
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Alamat</span>
                            <span id="customerDrawerAddress" class="font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Catatan</span>
                            <span id="customerDrawerNotes" class="font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                    </div>
                </div>

                {{-- Purchase metrics (completed + paid only) --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                        Pembelian Selesai &amp; Lunas
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Jumlah Transaksi</span>
                            <span id="customerDrawerTransactionsCount" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Total Belanja</span>
                            <span id="customerDrawerPurchaseTotal" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Pembelian Pertama</span>
                            <span id="customerDrawerFirstPurchase" class="block mt-1 font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Pembelian Terakhir</span>
                            <span id="customerDrawerLastPurchase" class="block mt-1 font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                    </div>
                </div>

                {{-- Recent transaction history (all statuses, max 10) --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                            Riwayat Transaksi Terbaru
                        </h3>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">Maks. 10 terakhir</span>
                    </div>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-snug">
                        Transaksi batal, void atau belum lunas ditampilkan apa adanya dan tidak dihitung dalam metrik pembelian di atas.
                    </p>
                    <div
                        id="customerDrawerHistory"
                        class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900"
                    ></div>
                    <div id="customerDrawerHistoryEmpty" class="hidden py-8 text-center text-xs text-slate-400 dark:text-slate-500">
                        Belum ada transaksi untuk pelanggan ini.
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                data-close-customer-drawer
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
