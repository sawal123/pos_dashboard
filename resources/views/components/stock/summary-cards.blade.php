@props([
    'summary' => [
        'total_items' => 0,
        'safe_stock' => 0,
        'low_stock' => 0,
        'critical_stock' => 0,
    ],
])

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    {{-- 1. Total Item --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Item</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <i data-lucide="boxes" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format($summary['total_items'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Item terdaftar inventori</p>
        </div>
    </div>

    {{-- 2. Stok Aman --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Stok Aman</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums tracking-tight">
                {{ number_format($summary['safe_stock'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Di atas batas minimum</p>
        </div>
    </div>

    {{-- 3. Stok Menipis --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Stok Menipis</span>
            <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-100 dark:border-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-amber-600 dark:text-amber-400 tabular-nums tracking-tight">
                {{ number_format($summary['low_stock'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Mendekati atau di bawah batas</p>
        </div>
    </div>

    {{-- 4. Habis / Minus --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Habis / Minus</span>
            <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-100 dark:border-rose-900/40 flex items-center justify-center text-rose-600 dark:text-rose-400">
                <i data-lucide="alert-octagon" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-rose-600 dark:text-rose-400 tabular-nums tracking-tight">
                {{ number_format($summary['critical_stock'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Perlu segera restock</p>
        </div>
    </div>
</div>
