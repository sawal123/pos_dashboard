<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                Tren Penjualan
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Aktivitas omzet dan volume transaksi harian.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-[11px] font-semibold border border-indigo-100 dark:border-indigo-800/60">
                <i data-lucide="chart-bar" class="w-3.5 h-3.5"></i>
                <span id="salesTrendPeriodLabel">Periode Terpilih</span>
            </span>
        </div>
    </div>

    {{-- Dynamic Container for Bars --}}
    <div id="salesTrendContainer" class="space-y-3.5 pt-1">
        {{-- Populated via DOM-safe client-side JS --}}
    </div>

    {{-- Empty Message within Card --}}
    <div id="salesTrendEmpty" class="hidden py-8 text-center text-xs text-slate-400 italic">
        Tidak ada data penjualan pada periode ini.
    </div>
</div>
