@props([
    'mode' => 'no-data', // 'no-data' (production/empty) or 'no-results' (filter empty)
])

@if($mode === 'no-data')
    {{-- Initial Empty State (Production / Belum ada transaksi cloud) --}}
    <div id="initialEmptyState" class="py-16 px-4 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center mx-auto mb-3.5">
            <i data-lucide="receipt-text" class="w-7 h-7"></i>
        </div>
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Belum Ada Transaksi</h3>
        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1 leading-normal">
            Transaksi yang telah tersinkron ke Cloud akan muncul di sini.
        </p>
    </div>
@else
    {{-- Filtered Results Empty State (Client-side filtered out) --}}
    <div id="filterEmptyState" class="hidden py-14 px-4 text-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-500 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="search-x" class="w-6 h-6"></i>
        </div>
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Transaksi Tidak Ditemukan</h3>
        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1 mb-4 leading-normal">
            Coba ubah kata pencarian atau filter yang digunakan.
        </p>
        <button
            type="button"
            onclick="document.getElementById('resetFilterBtn')?.click()"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        >
            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
            <span>Reset Filter</span>
        </button>
    </div>
@endif
