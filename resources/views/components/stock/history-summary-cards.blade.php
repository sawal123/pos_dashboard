@props([
    'summary' => [
        'total_in' => 0,
        'total_out' => 0,
        'movement_count' => 0,
        'adjustment_count' => 0,
        'stock_value' => 0,
    ],
])

@php
    $formatNumber = fn ($value) => number_format((float) $value, 3, ',', '.');
    $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    {{-- 1. Total Masuk --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Masuk</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <i data-lucide="arrow-down-to-line" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums tracking-tight">
                {{ $formatNumber($summary['total_in'] ?? 0) }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Penambahan stok</p>
        </div>
    </div>

    {{-- 2. Total Keluar --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Keluar</span>
            <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-100 dark:border-rose-900/40 flex items-center justify-center text-rose-600 dark:text-rose-400">
                <i data-lucide="arrow-up-from-line" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-rose-600 dark:text-rose-400 tabular-nums tracking-tight">
                {{ $formatNumber($summary['total_out'] ?? 0) }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Pengurangan stok</p>
        </div>
    </div>

    {{-- 3. Jumlah Pergerakan --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pergerakan</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format((int) ($summary['movement_count'] ?? 0), 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                {{ number_format((int) ($summary['adjustment_count'] ?? 0), 0, ',', '.') }} penyesuaian manual
            </p>
        </div>
    </div>

    {{-- 4. Nilai Persediaan --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Nilai Persediaan</span>
            <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/60 border border-sky-100 dark:border-sky-900/40 flex items-center justify-center text-sky-600 dark:text-sky-400">
                <i data-lucide="coins" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ $formatRupiah($summary['stock_value'] ?? 0) }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Stok × HPP saat ini</p>
        </div>
    </div>
</div>
