@props([
    'categories' => [],
])

<div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">

        {{-- 1. Search Bar --}}
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 dark:text-slate-500">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input
                type="text"
                id="searchStockInput"
                placeholder="Cari produk atau SKU..."
                class="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                autocomplete="off"
            >
        </div>

        {{-- 2. Filter Dropdowns Group --}}
        <div class="flex items-center gap-2 flex-wrap">

            {{-- Kategori Filter --}}
            <div class="min-w-[130px] flex-1 sm:flex-initial">
                <select
                    id="filterStockCategory"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                >
                    <option value="all" selected>Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Stok Filter (Aman / Menipis / Habis / Minus) --}}
            <div class="min-w-[130px] flex-1 sm:flex-initial">
                <select
                    id="filterStockStatus"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                >
                    <option value="all" selected>Semua Status</option>
                    <option value="safe">Aman</option>
                    <option value="low">Menipis</option>
                    <option value="empty">Habis</option>
                    <option value="negative">Minus</option>
                </select>
            </div>

            {{-- Reset Filter Button --}}
            <button
                type="button"
                id="resetStockFilterBtn"
                class="px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                title="Reset filter inventori"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-400"></i>
                <span class="hidden sm:inline">Reset</span>
            </button>

            {{-- Stock Adjustment Action (Neutral non-fake placeholder) --}}
            <button
                type="button"
                id="adjustStockBtn"
                onclick="showToast('info', 'Fitur penyesuaian stok akan tersedia setelah integrasi data.')"
                class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="sliders" class="w-4 h-4"></i>
                <span>Penyesuaian Stok</span>
            </button>
        </div>
    </div>
</div>
