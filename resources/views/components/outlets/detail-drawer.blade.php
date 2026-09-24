<div
    id="outletDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="outletDrawerTitle"
>
    <div
        id="outletDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    <div
        id="outletDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div class="min-w-0">
                <span id="outletDrawerSubtitle" class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider font-mono">
                    -
                </span>
                <h2 id="outletDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight break-words">
                    -
                </h2>
            </div>
            <button
                type="button"
                data-close-outlet-drawer
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail outlet"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-5">
            <div id="outletDrawerLoading" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                Memuat detail outlet...
            </div>

            <div id="outletDrawerError" class="hidden rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30 p-4 text-sm text-rose-700 dark:text-rose-300">
                Detail outlet tidak dapat dimuat.
            </div>

            <div id="outletDrawerContent" class="hidden space-y-5 text-sm">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Status</span>
                        <span id="outletDrawerStatus" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Alamat</span>
                        <span id="outletDrawerAddress" class="font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Terakhir Transaksi</span>
                        <span id="outletDrawerLastTransaction" class="font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Perangkat</span>
                        <span id="outletDrawerDevices" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Total Shift</span>
                        <span id="outletDrawerTotalShifts" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Shift Open</span>
                        <span id="outletDrawerOpenShifts" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Omzet Transaksi Completed + Paid</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Jumlah</span>
                            <span id="outletDrawerSalesCount" class="block mt-1 font-extrabold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Omzet</span>
                            <span id="outletDrawerSalesTotal" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Mutasi CashLedger</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/30">
                            <span class="block text-xs text-emerald-700 dark:text-emerald-300">Masuk</span>
                            <span id="outletDrawerCashIn" class="block mt-1 font-extrabold text-emerald-800 dark:text-emerald-200 tabular-nums">-</span>
                        </div>
                        <div class="p-4 rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30">
                            <span class="block text-xs text-rose-700 dark:text-rose-300">Keluar</span>
                            <span id="outletDrawerCashOut" class="block mt-1 font-extrabold text-rose-800 dark:text-rose-200 tabular-nums">-</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Mutasi kas ditampilkan terpisah dan tidak digabung dengan omzet transaksi untuk menghindari perhitungan ganda.
                    </p>
                </div>

                <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Total Expense Recorded</span>
                    <span id="outletDrawerExpenseTotal" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                data-close-outlet-drawer
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
