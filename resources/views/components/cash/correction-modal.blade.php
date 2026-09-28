{{-- DASH-16 — owner-only confirmation dialog for cash reversal / expense void.
     The dialog only presents the consequence; the server re-validates every
     rule (reversibility, tenant, permission) on submit. --}}
@props([
    'canManageCash' => false,
])

@if($canManageCash)
    <div
        id="cashCorrectionModal"
        data-cash-modal
        class="hidden fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-sm p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cashCorrectionTitle"
        aria-describedby="cashCorrectionDescription"
    >
        <div class="min-h-full flex items-center justify-center">
            <section class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 id="cashCorrectionTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Koreksi Kas</h2>
                        <p id="cashCorrectionDescription" class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Koreksi bersifat append-only: catatan asli tidak dihapus, sistem hanya menambahkan baris koreksi.
                        </p>
                    </div>
                    <button
                        type="button"
                        data-cash-modal-close
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-label="Tutup dialog koreksi"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form id="cashCorrectionForm" method="POST" action="" class="px-5 py-5 space-y-4">
                    @csrf

                    <dl class="rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                        <div class="flex items-center justify-between px-3 py-2">
                            <dt class="text-slate-500 dark:text-slate-400">Jenis</dt>
                            <dd id="cashCorrectionType" class="font-semibold text-slate-800 dark:text-slate-200">-</dd>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <dt class="text-slate-500 dark:text-slate-400">Nominal</dt>
                            <dd id="cashCorrectionAmount" class="font-extrabold tabular-nums text-slate-900 dark:text-white">-</dd>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2 gap-3">
                            <dt class="text-slate-500 dark:text-slate-400 shrink-0">Referensi</dt>
                            <dd id="cashCorrectionRef" class="font-mono text-[11px] text-slate-700 dark:text-slate-300 truncate">-</dd>
                        </div>
                    </dl>

                    <div id="cashCorrectionConsequence" class="rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 px-3 py-2.5 text-[11px] leading-relaxed text-amber-800 dark:text-amber-200">
                        Tindakan ini tidak dapat diurungkan.
                    </div>

                    <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-1">
                        <button
                            type="button"
                            data-cash-modal-close
                            data-correction-cancel
                            class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            id="cashCorrectionSubmit"
                            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-600/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:pointer-events-none"
                        >
                            <i data-lucide="shield-alert" class="w-4 h-4"></i>
                            <span data-correction-label>Konfirmasi</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endif
