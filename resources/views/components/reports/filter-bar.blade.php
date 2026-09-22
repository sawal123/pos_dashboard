@props([
    'outlets' => [],
    'currentFilters' => [],
])

<form
    method="GET"
    action="{{ route('reports.index') }}"
    id="reportFilterForm"
    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
        {{-- Date Filter (lg:col-span-4) --}}
        <div class="sm:col-span-2 lg:col-span-4">
            <select
                name="date"
                id="filterReportDate"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter periode laporan"
            >
                <option value="all" {{ ($currentFilters['date'] ?? 'all') === 'all' ? 'selected' : '' }}>Semua Tanggal</option>
                <option value="today" {{ ($currentFilters['date'] ?? '') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                <option value="7d" {{ ($currentFilters['date'] ?? '') === '7d' ? 'selected' : '' }}>7 Hari Terakhir</option>
                <option value="30d" {{ ($currentFilters['date'] ?? '') === '30d' ? 'selected' : '' }}>30 Hari Terakhir</option>
                <option value="custom" {{ ($currentFilters['date'] ?? '') === 'custom' ? 'selected' : '' }}>Periode Kustom</option>
            </select>
        </div>

        {{-- Outlet Filter (lg:col-span-3) --}}
        <div class="lg:col-span-3">
            <select
                name="outlet_id"
                id="filterReportOutlet"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter outlet laporan"
            >
                <option value="all">Semua Outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet['id'] }}" {{ (string)($currentFilters['outlet_id'] ?? '') === (string)$outlet['id'] ? 'selected' : '' }}>
                        {{ $outlet['name'] }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Action Buttons: Terapkan Filter & Reset & Export (lg:col-span-5) --}}
        <div class="lg:col-span-5 flex items-center justify-end gap-2">
            <button
                type="submit"
                id="applyReportFilterBtn"
                class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors flex items-center justify-center gap-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <span>Terapkan Filter</span>
            </button>

            <a
                href="{{ route('reports.index') }}"
                id="resetReportFilterBtn"
                class="py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                <span>Reset</span>
            </a>

            <button
                type="button"
                id="exportReportBtn"
                class="py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="download" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400"></i>
                <span>Ekspor Laporan</span>
            </button>
        </div>
    </div>

    {{-- Custom Date Range Picker Container (shown only when custom is selected) --}}
    <div id="reportCustomDateContainer" class="{{ ($currentFilters['date'] ?? '') === 'custom' ? '' : 'hidden' }} pt-3 border-t border-slate-100 dark:border-slate-800">
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="w-full sm:w-auto flex items-center gap-2">
                <label for="reportStartDate" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">Dari:</label>
                <input
                    type="date"
                    name="start_date"
                    id="reportStartDate"
                    value="{{ $currentFilters['start_date'] ?? '' }}"
                    class="w-full sm:w-auto px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tanggal mulai laporan kustom"
                >
            </div>
            <div class="w-full sm:w-auto flex items-center gap-2">
                <label for="reportEndDate" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">Sampai:</label>
                <input
                    type="date"
                    name="end_date"
                    id="reportEndDate"
                    value="{{ $currentFilters['end_date'] ?? '' }}"
                    class="w-full sm:w-auto px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tanggal selesai laporan kustom"
                >
            </div>
            <span class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                * Data laporan akan menyaring transaksi dan pengeluaran pada rentang tanggal tersebut.
            </span>
        </div>
    </div>
</form>
