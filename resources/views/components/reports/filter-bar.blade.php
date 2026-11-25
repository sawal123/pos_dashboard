@props([
    'outlets' => [],
    'currentFilters' => [],
])

@php
    // Server-rendered fallback links keep the currently applied filters, and
    // work even with JavaScript disabled.
    $exportQuery = array_filter([
        'date' => $currentFilters['date'] ?? 'all',
        'start_date' => $currentFilters['start_date'] ?? '',
        'end_date' => $currentFilters['end_date'] ?? '',
        'outlet_id' => ($currentFilters['outlet_id'] ?? '') === '' ? null : $currentFilters['outlet_id'],
    ], static fn ($value): bool => $value !== null && $value !== '');

    $selectedDate = $currentFilters['date'] ?? 'all';
    $isCustomDate = $selectedDate === 'custom';
    $todayYmd = \Carbon\Carbon::now(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d');
@endphp

<form
    method="GET"
    action="{{ route('reports.index') }}"
    id="reportFilterForm"
    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
        {{-- Date Preset Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <label for="filterReportDate" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Periode</label>
            <select
                name="date"
                id="filterReportDate"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                aria-label="Filter periode laporan"
            >
                <option value="all" {{ $selectedDate === 'all' ? 'selected' : '' }}>Semua Tanggal</option>
                <option value="today" {{ $selectedDate === 'today' ? 'selected' : '' }}>Hari Ini</option>
                <option value="7d" {{ $selectedDate === '7d' ? 'selected' : '' }}>7 Hari Terakhir</option>
                <option value="30d" {{ $selectedDate === '30d' ? 'selected' : '' }}>30 Hari Terakhir</option>
                <option value="custom" {{ $isCustomDate ? 'selected' : '' }}>Periode Kustom</option>
            </select>
        </div>

        {{-- Flatpickr Date / Range Picker (lg:col-span-3) --}}
        <div id="reportCustomDateContainer" class="lg:col-span-3">
            <label for="reportFlatpickrDateRange" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Pilih Tanggal</label>
            <div class="relative">
                <i data-lucide="calendar-days" class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input
                    type="text"
                    id="reportFlatpickrDateRange"
                    data-today="{{ $todayYmd }}"
                    placeholder="Pilih tanggal / rentang..."
                    readonly
                    class="w-full pl-8 pr-7 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 text-xs placeholder:text-slate-400 dark:placeholder:text-slate-500 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
                    aria-label="Pilih rentang tanggal laporan"
                />
                <button
                    type="button"
                    id="clearReportFlatpickrBtn"
                    title="Reset ke Semua Tanggal"
                    class="{{ $isCustomDate ? '' : 'hidden' }} absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer"
                >
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </div>
            <input type="hidden" name="start_date" id="reportStartDate" value="{{ $currentFilters['start_date'] ?? '' }}" />
            <input type="hidden" name="end_date" id="reportEndDate" value="{{ $currentFilters['end_date'] ?? '' }}" />
        </div>

        {{-- Outlet Filter (lg:col-span-2) --}}
        <div class="lg:col-span-2">
            <label for="filterReportOutlet" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Outlet</label>
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
        <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-end gap-2">
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

            {{-- DASH-13 — export dropdown (CSV / XLSX / PDF) --}}
            <div class="relative" id="exportReportWrapper">
                <button
                    type="button"
                    id="exportReportBtn"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    aria-controls="exportReportMenu"
                    class="py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400"></i>
                    <span id="exportReportBtnLabel">Ekspor Laporan</span>
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                </button>

                <div
                    id="exportReportMenu"
                    role="menu"
                    aria-labelledby="exportReportBtn"
                    class="hidden absolute right-0 mt-2 w-52 z-30 rounded-2xl border border-slate-200/90 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl shadow-slate-200/40 dark:shadow-slate-950/60 py-1"
                >
                    <a
                        role="menuitem"
                        href="{{ route('reports.export.csv', $exportQuery) }}"
                        data-export-format="csv"
                        class="export-report-option flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:bg-slate-50 dark:focus-visible:bg-slate-800"
                    >
                        <i data-lucide="file-spreadsheet" class="w-4 h-4 text-slate-400"></i>
                        <span>Download CSV</span>
                    </a>
                    <a
                        role="menuitem"
                        href="{{ route('reports.export.xlsx', $exportQuery) }}"
                        data-export-format="xlsx"
                        class="export-report-option flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:bg-slate-50 dark:focus-visible:bg-slate-800"
                    >
                        <i data-lucide="table-2" class="w-4 h-4 text-slate-400"></i>
                        <span>Download Excel (XLSX)</span>
                    </a>
                    <a
                        role="menuitem"
                        href="{{ route('reports.export.pdf', $exportQuery) }}"
                        data-export-format="pdf"
                        class="export-report-option flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:bg-slate-50 dark:focus-visible:bg-slate-800"
                    >
                        <i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i>
                        <span>Download PDF</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
