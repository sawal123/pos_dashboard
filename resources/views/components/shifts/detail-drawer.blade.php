<div
    id="shiftDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="shiftDrawerTitle"
>
    <div
        id="shiftDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    <div
        id="shiftDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div class="min-w-0">
                <span id="shiftDrawerSubtitle" class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    -
                </span>
                <h2 id="shiftDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight break-all">
                    -
                </h2>
            </div>
            <button
                type="button"
                data-close-shift-drawer
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail shift"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-5">
            <div id="shiftDrawerLoading" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                Memuat detail shift...
            </div>

            <div id="shiftDrawerError" class="hidden rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30 p-4 text-sm text-rose-700 dark:text-rose-300">
                Detail shift tidak dapat dimuat.
            </div>

            <div id="shiftDrawerContent" class="hidden space-y-5 text-sm">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Status</span>
                        <span id="shiftDrawerStatus" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Opened At</span>
                            <span id="shiftDrawerOpenedAt" class="font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div>
                            <span class="block text-slate-500 dark:text-slate-400">Closed At</span>
                            <span id="shiftDrawerClosedAt" class="font-semibold text-slate-900 dark:text-white">-</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Opening Cash</span>
                        <span id="shiftDrawerOpeningCash" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Closing Cash</span>
                        <span id="shiftDrawerClosingCash" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Transaksi Completed + Paid</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Jumlah</span>
                            <span id="shiftDrawerSalesCount" class="block mt-1 font-extrabold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Nominal</span>
                            <span id="shiftDrawerSalesTotal" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Mutasi CashLedger</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/30">
                            <span class="block text-xs text-emerald-700 dark:text-emerald-300">Masuk</span>
                            <span id="shiftDrawerCashIn" class="block mt-1 font-extrabold text-emerald-800 dark:text-emerald-200 tabular-nums">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30">
                            <span class="block text-xs text-rose-700 dark:text-rose-300">Keluar</span>
                            <span id="shiftDrawerCashOut" class="block mt-1 font-extrabold text-rose-800 dark:text-rose-200 tabular-nums">-</span>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Total Expense Recorded</span>
                    <span id="shiftDrawerExpenseTotal" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                </div>

                <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900">
                    <span class="block text-xs font-semibold text-indigo-700 dark:text-indigo-300">Estimasi Kas</span>
                    <span id="shiftDrawerEstimatedCash" class="block mt-1 text-xl font-extrabold text-indigo-900 dark:text-indigo-100 tabular-nums">-</span>
                    <p class="mt-2 text-xs text-indigo-700/80 dark:text-indigo-300/80">
                        Estimasi ini memakai opening cash + CashLedger masuk - CashLedger keluar. Ini bukan kas fisik terverifikasi.
                    </p>
                </div>

                <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Catatan</span>
                    <p id="shiftDrawerNotes" class="mt-1 text-sm text-slate-700 dark:text-slate-300">-</p>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                data-close-shift-drawer
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
