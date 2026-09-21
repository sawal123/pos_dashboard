@props([
    'summary' => [
        'total_sales' => 0,
        'total_transactions' => 0,
        'estimated_gross_profit' => 0,
        'total_expenses' => 0,
    ],
])

@php
    $formatRupiahWithDecimal = function ($val) {
        $num = (float) $val;
        if (floor($num) == $num) {
            return 'Rp ' . number_format($num, 0, ',', '.');
        }
        return 'Rp ' . number_format($num, 2, ',', '.');
    };
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    {{-- 1. Total Penjualan --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Penjualan</span>
            <h3 id="reportSummarySales" class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight tabular-nums">
                {{ $formatRupiahWithDecimal($summary['total_sales'] ?? 0) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Nilai kotor transaksi selesai</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="badge-dollar-sign" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 2. Total Transaksi --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Transaksi</span>
            <h3 id="reportSummaryTransactions" class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight tabular-nums">
                {{ number_format($summary['total_transactions'] ?? 0, 0, ',', '.') }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Jumlah pesanan terbayar</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-800/60 shadow-xs">
            <i data-lucide="receipt" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 3. Estimasi Laba Kotor --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Estimasi Laba Kotor</span>
            <h3 id="reportSummaryGrossProfit" class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tracking-tight tabular-nums">
                {{ $formatRupiahWithDecimal($summary['estimated_gross_profit'] ?? 0) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Total penjualan minus HPP</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-100 dark:border-emerald-800/60 shadow-xs">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 4. Total Pengeluaran --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Pengeluaran</span>
            <h3 id="reportSummaryExpenses" class="text-xl sm:text-2xl font-extrabold text-amber-600 dark:text-amber-400 tracking-tight tabular-nums">
                {{ $formatRupiahWithDecimal($summary['total_expenses'] ?? 0) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Biaya operasional tercatat</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 border border-amber-100 dark:border-amber-800/60 shadow-xs">
            <i data-lucide="arrow-up-right" class="w-5 h-5"></i>
        </div>
    </div>
</div>
