<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                Metode Pembayaran
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Porsi transaksi berdasarkan kanal pembayaran.
            </p>
        </div>
        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <i data-lucide="wallet" class="w-4 h-4"></i>
        </div>
    </div>

    {{-- Dynamic Container --}}
    <div id="paymentBreakdownContainer" class="space-y-3.5 pt-1">
        {{-- Populated via DOM-safe client-side JS --}}
    </div>

    {{-- Empty Message --}}
    <div id="paymentBreakdownEmpty" class="hidden py-8 text-center text-xs text-slate-400 italic">
        Tidak ada data metode pembayaran pada periode ini.
    </div>
</div>
