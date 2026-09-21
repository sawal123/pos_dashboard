@props([
    'summary' => [
        'cash_in' => 0,
        'cash_out' => 0,
        'net_cash_flow' => 0,
        'total_expense' => 0,
    ],
])

@php
    $formatRupiah = fn ($val) => 'Rp ' . number_format($val, 0, ',', '.');
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    {{-- 1. Kas Masuk --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Kas Masuk</span>
            <h3 id="summaryCashIn" class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tracking-tight tabular-nums">
                {{ $formatRupiah($summary['cash_in'] ?? 0) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Total penerimaan kas masuk</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-100 dark:border-emerald-800/60 shadow-xs">
            <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 2. Kas Keluar --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Kas Keluar</span>
            <h3 id="summaryCashOut" class="text-xl sm:text-2xl font-extrabold text-rose-600 dark:text-rose-400 tracking-tight tabular-nums">
                {{ $formatRupiah($summary['cash_out'] ?? 0) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Total kas keluar tercatat</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 border border-rose-100 dark:border-rose-800/60 shadow-xs">
            <i data-lucide="arrow-up-right" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 3. Net Pergerakan Kas --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Net Pergerakan Kas</span>
            <h3 id="summaryNetCash" class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight tabular-nums">
                {{ ($summary['net_cash_flow'] ?? 0) < 0 ? '-' : '' }}{{ $formatRupiah(abs($summary['net_cash_flow'] ?? 0)) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Kas masuk minus kas keluar</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="scale" class="w-5 h-5"></i>
        </div>
    </div>

    {{-- 4. Total Pengeluaran --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex items-center justify-between gap-4">
        <div class="space-y-1">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Pengeluaran</span>
            <h3 id="summaryTotalExpense" class="text-xl sm:text-2xl font-extrabold text-amber-600 dark:text-amber-400 tracking-tight tabular-nums">
                {{ $formatRupiah($summary['total_expense'] ?? 0) }}
            </h3>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Biaya operasional tercatat</p>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 border border-amber-100 dark:border-amber-800/60 shadow-xs">
            <i data-lucide="receipt" class="w-5 h-5"></i>
        </div>
    </div>
</div>
