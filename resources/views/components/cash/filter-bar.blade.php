@props([
    'activeTab' => 'ledgers',
    'categories' => [],
    'outlets' => [],
    'currentFilters' => [],
])

<form
    method="GET"
    action="{{ route('cash.index') }}"
    id="cashFilterForm"
    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
>
    <input type="hidden" name="tab" value="{{ $activeTab }}">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
        {{-- Search Input (lg:col-span-4) --}}
        <div class="sm:col-span-2 lg:col-span-4 relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </div>
            <input
                type="text"
                name="q"
                id="searchCashInput"
                value="{{ $currentFilters['q'] ?? '' }}"
                placeholder="{{ $activeTab === 'expenses' ? 'Cari pengeluaran, kategori, atau catatan...' : 'Cari referensi, kategori, atau catatan...' }}"
                class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-900 dark:text-white text-xs placeholder-slate-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="{{ $activeTab === 'expenses' ? 'Cari pengeluaran' : 'Cari transaksi kas' }}"
            >
        </div>

        {{-- Date Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                name="date"
                id="filterCashDate"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter berdasarkan rentang waktu"
            >
                <option value="all" {{ ($currentFilters['date'] ?? 'all') === 'all' ? 'selected' : '' }}>Semua Tanggal</option>
                <option value="today" {{ ($currentFilters['date'] ?? '') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                <option value="7d" {{ ($currentFilters['date'] ?? '') === '7d' ? 'selected' : '' }}>7 Hari</option>
                <option value="30d" {{ ($currentFilters['date'] ?? '') === '30d' ? 'selected' : '' }}>30 Hari</option>
                <option value="custom" {{ ($currentFilters['date'] ?? '') === 'custom' ? 'selected' : '' }}>Periode Kustom</option>
            </select>
        </div>

        {{-- Outlet Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <select
                name="outlet_id"
                id="filterCashOutlet"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter berdasarkan outlet"
            >
                <option value="all">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet['id'] }}" {{ (string)($currentFilters['outlet_id'] ?? '') === (string)$outlet['id'] ? 'selected' : '' }}>
                        {{ $outlet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        @if($activeTab === 'ledgers')
            {{-- Cash Ledger Type Filter (Only on Ledger Tab) (lg:col-span-2) --}}
            <div id="wrapperFilterCashType" class="lg:col-span-2">
                <select
                    name="type"
                    id="filterCashType"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                    aria-label="Filter jenis kas"
                >
                    <option value="all">Semua Jenis</option>
                    <option value="in" {{ ($currentFilters['type'] ?? '') === 'in' ? 'selected' : '' }}>Kas Masuk</option>
                    <option value="out" {{ ($currentFilters['type'] ?? '') === 'out' ? 'selected' : '' }}>Kas Keluar</option>
                </select>
            </div>
        @else
            {{-- Expense Category Filter (Only on Expense Tab) (lg:col-span-2) --}}
            <div id="wrapperFilterExpenseCategory" class="lg:col-span-2">
                <select
                    name="category"
                    id="filterExpenseCategory"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                    aria-label="Filter kategori pengeluaran"
                >
                    <option value="all">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" {{ ($currentFilters['category'] ?? '') === $category ? 'selected' : '' }}>
                            {{ $category }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Action Buttons: Filter & Reset (lg:col-span-2) --}}
        <div class="lg:col-span-2 flex items-center justify-end gap-2">
            <button
                type="submit"
                class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors flex items-center justify-center gap-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <span>Filter</span>
            </button>
            <a
                href="{{ route('cash.index', ['tab' => $activeTab]) }}"
                id="resetCashFilterBtn"
                class="py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Reset</span>
            </a>
        </div>
    </div>

    {{-- Custom Date Range Picker Container (shown only when custom is selected) --}}
    <div id="cashCustomDateContainer" class="{{ ($currentFilters['date'] ?? '') === 'custom' ? '' : 'hidden' }} pt-3 border-t border-slate-100 dark:border-slate-800">
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="w-full sm:w-auto flex items-center gap-2">
                <label for="cashStartDate" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">Dari:</label>
                <input
                    type="date"
                    name="start_date"
                    id="cashStartDate"
                    value="{{ $currentFilters['start_date'] ?? '' }}"
                    class="w-full sm:w-auto px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tanggal mulai kustom"
                >
            </div>
            <div class="w-full sm:w-auto flex items-center gap-2">
                <label for="cashEndDate" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">Sampai:</label>
                <input
                    type="date"
                    name="end_date"
                    id="cashEndDate"
                    value="{{ $currentFilters['end_date'] ?? '' }}"
                    class="w-full sm:w-auto px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tanggal selesai kustom"
                >
            </div>
            <span class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                * Periode kustom akan menyaring data tanggal transaksi.
            </span>
        </div>
    </div>
</form>
