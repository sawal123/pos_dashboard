{{-- ==================== TRANSACTION DETAIL DRAWER ==================== --}}
<div
    id="transactionDrawerWrapper"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="transactionDetailTitle"
>
    {{-- Backdrop --}}
    <div
        id="transactionDrawerBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-300 opacity-0"
    ></div>

    {{-- Slide-over Panel --}}
    <div
        id="transactionDrawerPanel"
        class="fixed right-0 top-0 h-full w-full sm:max-w-lg md:max-w-xl bg-white dark:bg-slate-900 border-l border-slate-200/90 dark:border-slate-800 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-out z-10 select-none sm:select-auto"
        tabindex="-1"
    >
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 shrink-0">
            <div>
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                    Detail Transaksi
                </span>
                <h2 id="transactionDetailTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span id="detailTrxNumber">-</span>
                </h2>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    id="closeDrawerBtn"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup detail transaksi"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        {{-- Drawer Scrollable Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5 text-xs sm:text-sm">

            {{-- 1. Status & Basic Metadata Grid --}}
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-3">
                <div class="flex items-center justify-between gap-2 flex-wrap pb-3 border-b border-slate-200/60 dark:border-slate-700/60">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Waktu Transaksi</span>
                        <span id="detailSoldAt" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span id="detailPaymentStatusBadge" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold">
                            -
                        </span>
                        <span id="detailTransactionStatusBadge" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold">
                            -
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Outlet</span>
                        <span id="detailOutlet" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Shift</span>
                        <span id="detailShift" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[11px] text-slate-400 dark:text-slate-500 block">Pelanggan</span>
                        <span id="detailCustomer" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                </div>
            </div>

            {{-- 2. Laundry Lifecycle Section (Conditional: Only visible if order_status exists) --}}
            <div id="detailLaundrySection" class="hidden p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200/70 dark:border-indigo-800/60 space-y-2">
                <div class="flex items-center gap-1.5 text-indigo-700 dark:text-indigo-300 font-bold text-xs">
                    <i data-lucide="washing-machine" class="w-4 h-4"></i>
                    <span>Informasi Layanan Laundry</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                    <div>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 block">Status Pesanan</span>
                        <span id="detailOrderStatus" class="font-bold text-indigo-700 dark:text-indigo-300">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 block">Estimasi Selesai</span>
                        <span id="detailEstimatedCompletedAt" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                    </div>
                </div>
            </div>

            {{-- 3. Items List --}}
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-2.5">
                    Daftar Item
                </h3>
                <div id="detailItemsContainer" class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900">
                    {{-- Dynamically populated --}}
                </div>
            </div>

            {{-- 4. Price Summary --}}
            <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800/80 space-y-2 text-xs">
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Subtotal</span>
                    <span id="detailSubtotal" class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">Rp 0</span>
                </div>
                <div id="detailDiscountRow" class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Diskon</span>
                    <span id="detailDiscount" class="font-semibold text-rose-600 dark:text-rose-400 tabular-nums">- Rp 0</span>
                </div>
                <div id="detailTaxRow" class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>Pajak</span>
                    <span id="detailTax" class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">Rp 0</span>
                </div>
                <div class="pt-2 border-t border-slate-200/80 dark:border-slate-700 flex items-center justify-between text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                    <span>Total Pembayaran</span>
                    <span id="detailTotal" class="text-indigo-600 dark:text-indigo-400 tabular-nums">Rp 0</span>
                </div>
            </div>

            {{-- 5. Payment Details Section --}}
            <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2.5 text-xs">
                <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-1">
                    Detail Pembayaran
                </h3>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Metode</span>
                    <span id="detailPaymentMethod" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Status Pembayaran</span>
                    <span id="detailPaymentStatusText" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Waktu Bayar</span>
                    <span id="detailPaidAt" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                </div>

                {{-- Cash specific details (only when method is Tunai) --}}
                <div id="detailCashOnlySection" class="hidden pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Diterima</span>
                        <span id="detailCashReceived" class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">-</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Kembalian</span>
                        <span id="detailChangeAmount" class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">-</span>
                    </div>
                </div>
            </div>

            {{-- 6. Secondary Gross Profit --}}
            <div class="p-3 rounded-xl bg-slate-50/60 dark:bg-slate-800/30 border border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs">
                <span>Estimasi Laba Kotor</span>
                <span id="detailGrossProfit" class="font-semibold text-slate-700 dark:text-slate-300 tabular-nums">Rp 0</span>
            </div>

            {{-- 7. Catatan Section (Hidden if null) --}}
            <div id="detailNoteSection" class="hidden p-3.5 rounded-xl bg-amber-50/50 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/50 space-y-1">
                <span class="text-[11px] font-bold text-amber-800 dark:text-amber-400 uppercase tracking-wider block">Catatan</span>
                <p id="detailNote" class="text-slate-700 dark:text-slate-300 text-xs leading-relaxed italic"></p>
            </div>

        </div>

        {{-- Drawer Footer: Non-destructive Placeholder Actions --}}
        <div class="p-4 border-t border-slate-200/80 dark:border-slate-800 flex items-center gap-2.5 bg-slate-50/50 dark:bg-slate-900/60 shrink-0">
            <button
                type="button"
                id="drawerPrintBtn"
                onclick="showToast('info', 'Fitur cetak struk akan tersedia setelah integrasi data.')"
                class="flex-1 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                <i data-lucide="printer" class="w-4 h-4 text-slate-500 dark:text-slate-400"></i>
                <span>Cetak Struk</span>
            </button>
            <button
                type="button"
                id="drawerCloseFooterBtn"
                class="py-2.5 px-4 rounded-xl border border-transparent bg-slate-200/80 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 min-h-[42px]"
            >
                Tutup
            </button>
        </div>
    </div>
</div>
