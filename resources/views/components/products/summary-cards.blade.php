@props([
    'summary' => [
        'total_products' => 0,
        'total_services' => 0,
        'active_categories' => 0,
        'active_items' => 0,
    ],
])

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    {{-- 1. Total Produk --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Produk</span>
            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <i data-lucide="package" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format($summary['total_products'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Katalog barang fisik</p>
        </div>
    </div>

    {{-- 2. Total Layanan --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Layanan</span>
            <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/60 border border-sky-100 dark:border-sky-900/40 flex items-center justify-center text-sky-600 dark:text-sky-400">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format($summary['total_services'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Item layanan</p>
        </div>
    </div>

    {{-- 3. Kategori Aktif --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Kategori Aktif</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <i data-lucide="tags" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format($summary['active_categories'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Struktur katalog</p>
        </div>
    </div>

    {{-- 4. Item Aktif --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Item Aktif</span>
            <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-100 dark:border-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tabular-nums tracking-tight">
                {{ number_format($summary['active_items'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Siap dijual di kasir</p>
        </div>
    </div>
</div>
