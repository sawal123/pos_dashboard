{{-- ==================== ADD ITEMS TO PROCESSING TRANSACTION MODAL ==================== --}}
<div
    id="addItemsModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="addItemsModalTitle"
>
    {{-- Backdrop --}}
    <div
        id="addItemsBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/75 backdrop-blur-xs transition-opacity duration-200 opacity-0"
    ></div>

    {{-- Dialog Box --}}
    <div class="fixed inset-0 z-10 overflow-y-auto p-3 sm:p-6 flex items-center justify-center">
        <div
            id="addItemsDialog"
            class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-200 flex flex-col max-h-[90vh]"
        >
            {{-- Header --}}
            <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between shrink-0">
                <div>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Pesanan Masih Proses
                    </span>
                    <h2 id="addItemsModalTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                        Tambah Pesanan <span id="addItemsTrxNumberDisplay" class="text-indigo-600 dark:text-indigo-400"></span>
                    </h2>
                    <p id="addItemsTrxMetaDisplay" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">-</p>
                </div>
                <button
                    type="button"
                    id="closeAddItemsModalBtn"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup modal tambah pesanan"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4 text-xs sm:text-sm">
                {{-- Error Alert --}}
                <div id="addItemsErrorAlert" class="hidden p-3 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-semibold"></div>

                {{-- Search Bar --}}
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input
                        type="text"
                        id="addItemsSearchInput"
                        placeholder="Cari nama menu atau SKU untuk ditambahkan..."
                        autocomplete="off"
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                {{-- Catalog List --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pilih Menu Tambahan</span>
                        <span id="addItemsCatalogCount" class="text-[11px] text-slate-400 dark:text-slate-500">Memuat katalog...</span>
                    </div>
                    <div
                        id="addItemsCatalogList"
                        class="max-h-56 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200/80 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900"
                    >
                        <div class="p-6 text-center text-xs text-slate-400">Memuat daftar menu...</div>
                    </div>
                </div>

                {{-- Selected Additional Items Cart --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Item Tambahan Dipilih</span>
                        <span id="addItemsSelectedCount" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">0 item</span>
                    </div>
                    <div
                        id="addItemsSelectedList"
                        class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200/80 dark:border-slate-800 rounded-2xl bg-slate-50/60 dark:bg-slate-800/30 min-h-[60px]"
                    >
                        <div class="p-4 text-center text-xs text-slate-400">Belum ada menu tambahan dipilih. Klik menu di atas untuk menambahkan.</div>
                    </div>
                </div>

                {{-- Optional Mark Unpaid Toggle (shown only if order is currently paid) --}}
                <div id="addItemsMarkUnpaidWrapper" class="hidden p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-800/60">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            id="addItemsMarkUnpaidCheckbox"
                            class="mt-0.5 w-4 h-4 rounded border-amber-400 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                            <strong>Tandai Belum Lunas (Bayar Nanti)</strong> — centang jika pelanggan akan membayar tambahan pesanan di akhir. Jika tidak dicentang, tambahan langsung dianggap <strong>Lunas</strong> (dan masuk kas bila metode Tunai).
                        </span>
                    </label>
                </div>

                {{-- Summary --}}
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span>Total Pesanan Saat Ini</span>
                        <span id="addItemsCurrentTotalDisplay" class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">Rp 0</span>
                    </div>
                    <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                        <span>Tambahan Item Baru</span>
                        <span id="addItemsAddedSubtotalDisplay" class="font-bold tabular-nums">+ Rp 0</span>
                    </div>
                    <div id="addItemsTaxRow" class="hidden flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span id="addItemsTaxLabel">Estimasi Pajak Tambahan</span>
                        <span id="addItemsAddedTaxDisplay" class="font-semibold tabular-nums">+ Rp 0</span>
                    </div>
                    <div class="pt-2 mt-1 border-t border-slate-200/80 dark:border-slate-700 flex items-center justify-between text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                        <span>Estimasi Total Baru</span>
                        <span id="addItemsNewTotalDisplay" class="text-indigo-600 dark:text-indigo-400 tabular-nums">Rp 0</span>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-5 sm:px-6 py-4 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 flex items-center justify-end gap-2.5 shrink-0">
                <button
                    type="button"
                    id="cancelAddItemsModalBtn"
                    class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors"
                >
                    Batal
                </button>
                <button
                    type="button"
                    id="submitAddItemsBtn"
                    disabled
                    class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-40 disabled:pointer-events-none text-white font-semibold text-xs transition-all shadow-sm flex items-center gap-1.5"
                >
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span id="submitAddItemsBtnText">Simpan Tambahan Pesanan</span>
                </button>
            </div>
        </div>
    </div>
</div>
