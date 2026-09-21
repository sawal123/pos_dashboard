@props([
    'mode' => 'no-data', // 'no-data' | 'no-results'
])

@if($mode === 'no-data')
    {{-- Initial Empty State --}}
    <div class="py-16 px-6 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col items-center justify-center">
        <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mb-4 shadow-xs">
            <i data-lucide="boxes" class="w-7 h-7"></i>
        </div>
        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
            Belum Ada Data Stok
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto leading-relaxed">
            Data inventori yang telah tersinkron ke Cloud akan muncul di sini.
        </p>
    </div>
@else
    {{-- Filter Zero-results Empty State --}}
    <div id="stockFilterEmptyState" class="py-12 px-6 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col items-center justify-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 dark:text-slate-500 mb-3">
            <i data-lucide="filter-x" class="w-6 h-6"></i>
        </div>
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">
            Data Stok Tidak Ditemukan
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
            Tidak ada item inventori yang cocok dengan kata kunci atau filter saat ini.
        </p>
        <a
            href="{{ route('stock.index') }}"
            class="mt-4 inline-flex items-center px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            Reset Filter
        </a>
    </div>
@endif
