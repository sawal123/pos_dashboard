{{-- ==================== EDIT TRANSACTION MODAL ==================== --}}
<div
    id="editTransactionModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="editTransactionTitle"
>
    {{-- Backdrop --}}
    <div
        id="editTransactionBackdrop"
        class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs transition-opacity duration-200 opacity-0"
    ></div>

    {{-- Dialog Box --}}
    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-8 flex items-center justify-center">
        <div
            id="editTransactionDialog"
            class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-200"
        >
            <form id="editTransactionForm" method="POST" action="">
                @csrf
                @method('PATCH')

                {{-- Header --}}
                <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">
                            Akses Pemilik (Owner)
                        </span>
                        <h2 id="editTransactionTitle" class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
                            Edit Transaksi <span id="editTrxNumberDisplay" class="text-indigo-600 dark:text-indigo-400"></span>
                        </h2>
                    </div>
                    <button
                        type="button"
                        id="closeEditModalBtn"
                        class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-label="Tutup form edit"
                    >
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-5 sm:p-6 space-y-4 max-h-[70vh] overflow-y-auto text-xs sm:text-sm">
                    {{-- Summary Pill --}}
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-slate-400 dark:text-slate-500 block">Total Tagihan Transaksi</span>
                            <span id="editTrxTotalDisplay" class="text-base font-extrabold text-slate-900 dark:text-white tabular-nums">Rp 0</span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 dark:text-slate-500 block">Outlet & Waktu</span>
                            <span id="editTrxOutletTimeDisplay" class="font-semibold text-slate-700 dark:text-slate-300">-</span>
                        </div>
                    </div>

                    {{-- Metode Transaksi / Pembayaran --}}
                    <div>
                        <label for="editPaymentMethod" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            Metode Pembayaran
                        </label>
                        <select
                            name="payment_method"
                            id="editPaymentMethod"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        >
                            <option value="Tunai">Tunai (Cash)</option>
                            <option value="QRIS">QRIS</option>
                            <option value="Transfer">Transfer Bank</option>
                            <option value="Kartu">Kartu Debit / Kredit</option>
                        </select>
                    </div>

                    {{-- Status Pembayaran --}}
                    <div>
                        <label for="editPaymentStatus" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            Status Pembayaran
                        </label>
                        <select
                            name="payment_status"
                            id="editPaymentStatus"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        >
                            <option value="paid">Lunas (Paid)</option>
                            <option value="unpaid">Belum Lunas (Unpaid)</option>
                        </select>
                    </div>

                    {{-- Status Transaksi --}}
                    <div>
                        <label for="editStatus" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            Status Transaksi
                        </label>
                        <select
                            name="status"
                            id="editStatus"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        >
                            <option value="completed">Selesai (Completed)</option>
                            <option value="cancelled">Dibatalkan (Cancelled)</option>
                        </select>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                            Pilih "Dibatalkan" jika transaksi dibatalkan atau refund.
                        </p>
                    </div>

                    {{-- Cash Only Section: Tunai Diterima & Kembalian --}}
                    <div id="editCashSection" class="p-3.5 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/60 space-y-3">
                        <div>
                            <label for="editCashReceived" class="block font-semibold text-emerald-900 dark:text-emerald-200 mb-1">
                                Nominal Uang Diterima (Rp)
                            </label>
                            <input
                                type="number"
                                name="cash_received"
                                id="editCashReceived"
                                min="0"
                                step="1"
                                placeholder="0"
                                class="w-full px-3.5 py-2 rounded-xl border border-emerald-300 dark:border-emerald-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-bold tabular-nums focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-emerald-200/50 dark:border-emerald-800/50">
                            <span class="text-emerald-800 dark:text-emerald-300">Estimasi Kembalian:</span>
                            <span id="editChangeAmountDisplay" class="font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">Rp 0</span>
                        </div>
                    </div>

                    {{-- Catatan --}}
                    <div>
                        <label for="editNote" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            Catatan / Alasan Perubahan (Opsional)
                        </label>
                        <textarea
                            name="note"
                            id="editNote"
                            rows="2"
                            maxlength="500"
                            placeholder="Tuliskan catatan atau alasan perubahan data..."
                            class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none"
                        ></textarea>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-5 sm:px-6 py-4 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        id="cancelEditModalBtn"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="submitEditTrxBtn"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold text-xs transition-all shadow-sm hover:shadow-indigo-500/20 flex items-center gap-1.5"
                    >
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
