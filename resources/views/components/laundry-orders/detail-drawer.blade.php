<div
    id="laundryDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="laundryDrawerTitle"
>
    <div
        id="laundryDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    <div
        id="laundryDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-md md:max-w-lg bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10"
        tabindex="-1"
    >
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div class="min-w-0">
                <span id="laundryDrawerSubtitle" class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider truncate">
                    Detail Pesanan
                </span>
                <h2 id="laundryDrawerTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight break-all">
                    -
                </h2>
            </div>
            <button
                type="button"
                data-close-laundry-drawer
                class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup detail pesanan laundry"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-5">
            <div id="laundryDrawerLoading" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                Memuat detail pesanan...
            </div>

            <div id="laundryDrawerError" class="hidden rounded-2xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/30 p-4 text-sm text-rose-700 dark:text-rose-300">
                Detail pesanan tidak dapat dimuat.
            </div>

            <div id="laundryDrawerContent" class="hidden space-y-5 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span id="laundryDrawerStatusBadge" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                    <span id="laundryDrawerPaymentBadge" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold">-</span>
                    <span id="laundryDrawerOverdueBadge" class="hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-md border text-[11px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800">
                        <i data-lucide="alarm-clock" class="w-3 h-3"></i>
                        Terlambat
                    </span>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Pelanggan</span>
                        <span id="laundryDrawerCustomer" class="font-semibold text-slate-900 dark:text-white break-words">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Telepon</span>
                        <span id="laundryDrawerPhone" class="font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Outlet</span>
                        <span id="laundryDrawerOutlet" class="font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Tanggal Pesanan</span>
                        <span id="laundryDrawerSoldAt" class="block mt-1 font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Estimasi Selesai</span>
                        <span id="laundryDrawerEstimated" class="block mt-1 font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Metode Pembayaran</span>
                        <span id="laundryDrawerPaymentMethod" class="block mt-1 font-semibold text-slate-900 dark:text-white">-</span>
                    </div>
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Total Transaksi</span>
                        <span id="laundryDrawerTotal" class="block mt-1 font-extrabold text-slate-900 dark:text-white tabular-nums">-</span>
                    </div>
                </div>

                <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                    <span class="block text-xs text-slate-500 dark:text-slate-400">Catatan Pesanan</span>
                    <p id="laundryDrawerNote" class="mt-1 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">-</p>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Item Layanan</h3>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">Harga historis transaksi</span>
                    </div>
                    <div
                        id="laundryDrawerItems"
                        class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900"
                    ></div>
                    <div id="laundryDrawerItemsEmpty" class="hidden py-8 text-center text-xs text-slate-400 dark:text-slate-500">
                        Tidak ada item pada pesanan ini.
                    </div>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Halaman ini hanya membaca data tersinkronisasi. Riwayat perubahan status tidak tersedia karena
                    tabel audit lifecycle laundry belum ada.
                </p>
            </div>
        </div>

        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center justify-end bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                data-close-laundry-drawer
                class="w-full sm:w-auto py-2.5 px-5 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
