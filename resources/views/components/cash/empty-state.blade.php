@props([
    'mode' => 'no-data-cash', // 'no-data-cash', 'no-data-expense', 'no-results-cash', 'no-results-expense'
    'activeTab' => 'ledgers',
])

@if($mode === 'no-data-cash')
    <div id="cashLedgerNoDataEmpty" class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-10 text-center space-y-4">
        <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 mx-auto flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60 shadow-xs">
            <i data-lucide="wallet-cards" class="w-7 h-7"></i>
        </div>
        <div class="max-w-md mx-auto space-y-1">
            <h3 id="cashEmptyTitle" class="text-base font-bold text-slate-900 dark:text-white">
                Belum Ada Pergerakan Kas
            </h3>
            <p id="cashEmptySubtitle" class="text-xs text-slate-500 dark:text-slate-400">
                Pergerakan kas yang telah tersinkron ke Cloud akan muncul di sini.
            </p>
        </div>
    </div>
@elseif($mode === 'no-data-expense')
    <div id="expenseNoDataEmpty" class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-10 text-center space-y-4">
        <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center border border-amber-100 dark:border-amber-800/60 shadow-xs">
            <i data-lucide="receipt" class="w-7 h-7"></i>
        </div>
        <div class="max-w-md mx-auto space-y-1">
            <h3 id="expenseEmptyTitle" class="text-base font-bold text-slate-900 dark:text-white">
                Belum Ada Pengeluaran
            </h3>
            <p id="expenseEmptySubtitle" class="text-xs text-slate-500 dark:text-slate-400">
                Pengeluaran yang telah tersinkron ke Cloud akan muncul di sini.
            </p>
        </div>
    </div>
@elseif($mode === 'no-results-cash')
    <div id="cashLedgerFilterEmptyState" class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-8 text-center space-y-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
            <i data-lucide="search-x" class="w-6 h-6"></i>
        </div>
        <div class="max-w-xs mx-auto space-y-1">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">
                Pergerakan Kas Tidak Ditemukan
            </h4>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Coba sesuaikan kata kunci pencarian, rentang tanggal, atau filter jenis kas.
            </p>
        </div>
        <div class="pt-2">
            <a
                href="{{ route('cash.index', ['tab' => 'ledgers']) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Reset Filter</span>
            </a>
        </div>
    </div>
@elseif($mode === 'no-results-expense')
    <div id="expenseFilterEmptyState" class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-8 text-center space-y-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
            <i data-lucide="search-x" class="w-6 h-6"></i>
        </div>
        <div class="max-w-xs mx-auto space-y-1">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">
                Pengeluaran Tidak Ditemukan
            </h4>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Coba sesuaikan kata kunci pencarian, rentang tanggal, atau filter kategori.
            </p>
        </div>
        <div class="pt-2">
            <a
                href="{{ route('cash.index', ['tab' => 'expenses']) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Reset Filter</span>
            </a>
        </div>
    </div>
@endif
