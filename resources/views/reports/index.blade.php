<x-layouts::app :title="'Laporan'">
    <main id="mainContent" data-reports-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== REPORT HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Laporan</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Laporan
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Analisis ringkas penjualan dan pengeluaran bisnis.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                    <i data-lucide="line-chart" class="w-3.5 h-3.5"></i>
                    <span>Analisis Performa</span>
                </span>
            </div>
        </div>

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-reports.summary-cards :summary="$summary" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-reports.filter-bar :outlets="$filterOptions['outlets'] ?? []" :currentFilters="$currentFilters" />

        {{-- ==================== 3. REPORT CONTENT / EMPTY STATE ==================== --}}
        @if(!$hasAnyReportData)
            <x-reports.empty-state mode="no-data" />
        @elseif(!($hasFilteredReportData ?? false))
            <x-reports.empty-state mode="no-results" />
        @else
            <div id="reportContentArea" class="space-y-6">
                {{-- Section 1: Sales Trend (Horizontal CSS Bars) --}}
                <x-reports.sales-trend :trend="$salesTrend" :periodLabel="$periodLabel" />

                {{-- Section 2: Breakdown Grid (Payment Methods & Expense Categories) --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Left: Payment Methods Breakdown --}}
                    <x-reports.payment-breakdown :breakdown="$paymentBreakdown" />

                    {{-- Right: Expense Categories Breakdown --}}
                    <x-reports.expense-breakdown :breakdown="$expenseBreakdown" />
                </div>

                {{-- Section 3: Per-promo performance (PROMO-D01) --}}
                <x-reports.promo-breakdown :breakdown="$promoSummary ?? []" />
            </div>
        @endif

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initReportsPage() {
            const root = document.querySelector('main[data-reports-page="true"]');
            if (!root || root.dataset.reportsInitialized === 'true') {
                return;
            }
            root.dataset.reportsInitialized = 'true';

            // Clean up any stale cash drawer state or overflow if navigated here
            if (typeof window.__cashDrawerCleanup === 'function') {
                window.__cashDrawerCleanup();
                window.__cashDrawerCleanup = null;
            }
            document.body.style.overflow = '';

            // DASH-13 — export dropdown (CSV / XLSX / PDF).
            const dateSelect = document.getElementById('filterReportDate');
            const customDateContainer = document.getElementById('reportCustomDateContainer');
            const exportBtn = document.getElementById('exportReportBtn');
            const exportMenu = document.getElementById('exportReportMenu');
            const exportLabel = document.getElementById('exportReportBtnLabel');
            let exportBusy = false;

            const setExportMenuOpen = (open) => {
                if (!exportMenu || !exportBtn) return;
                exportMenu.classList.toggle('hidden', !open);
                exportBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            // Each option is a server-rendered link whose query string already
            // carries the filters that were *applied* to the report currently
            // on screen (normalised server-side). Downloading that exact URL
            // keeps the export in lockstep with the displayed report, so an
            // unsaved change in the filter form can never leak into the file.
            if (exportBtn && exportMenu) {
                exportBtn.onclick = (event) => {
                    event.stopPropagation();
                    setExportMenuOpen(exportMenu.classList.contains('hidden'));
                };

                exportMenu.onclick = (event) => {
                    event.stopPropagation();
                    const option = event.target instanceof Element
                        ? event.target.closest('.export-report-option')
                        : null;

                    if (!option || exportBusy) return;

                    event.preventDefault();
                    exportBusy = true;

                    if (exportLabel) exportLabel.textContent = 'Menyiapkan…';
                    exportBtn.setAttribute('aria-busy', 'true');
                    exportBtn.classList.add('opacity-60', 'pointer-events-none');

                    window.location.href = option.href;

                    // A file download does not unload the page, so restore the
                    // trigger shortly after.
                    window.setTimeout(() => {
                        exportBusy = false;
                        if (exportLabel) exportLabel.textContent = 'Ekspor Laporan';
                        exportBtn.removeAttribute('aria-busy');
                        exportBtn.classList.remove('opacity-60', 'pointer-events-none');
                    }, 2500);
                };
            }

            // Flatpickr Date / Range Picker Sync
            const flatpickrInput = document.getElementById('reportFlatpickrDateRange');
            const filterStartDate = document.getElementById('reportStartDate');
            const filterEndDate = document.getElementById('reportEndDate');
            const clearFlatpickrBtn = document.getElementById('clearReportFlatpickrBtn');

            function parseYmdLocal(ymd) {
                if (!ymd || typeof ymd !== 'string') return null;
                const parts = ymd.split('-').map(Number);
                if (parts.length !== 3 || parts.some(isNaN)) return null;
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            function formatYmdLocal(dateObj) {
                if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) return '';
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                const d = String(dateObj.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function updateClearBtnVisibility() {
                if (!clearFlatpickrBtn || !dateSelect) return;
                clearFlatpickrBtn.classList.toggle('hidden', dateSelect.value !== 'custom');
            }

            if (window.__reportsFlatpickr) {
                try {
                    window.__reportsFlatpickr.destroy();
                } catch (_) {}
                window.__reportsFlatpickr = null;
            }

            let fpInstance = null;
            let isProgrammaticDateSync = false;

            if (flatpickrInput && typeof flatpickr !== 'undefined') {
                const idLocale = (flatpickr.l10ns && flatpickr.l10ns.id)
                    ? Object.assign({}, flatpickr.l10ns.id, { rangeSeparator: ' - ' })
                    : { rangeSeparator: ' - ' };

                fpInstance = flatpickr(flatpickrInput, {
                    mode: 'range',
                    dateFormat: 'd M Y',
                    locale: idLocale,
                    allowInput: false,
                    disableMobile: true,
                    onChange: function(selectedDates) {
                        if (isProgrammaticDateSync) return;
                        if (selectedDates.length >= 1) {
                            if (dateSelect) {
                                dateSelect.value = 'custom';
                            }
                            const startStr = formatYmdLocal(selectedDates[0]);
                            const endStr = formatYmdLocal(selectedDates[1] || selectedDates[0]);
                            if (filterStartDate) filterStartDate.value = startStr;
                            if (filterEndDate) filterEndDate.value = endStr;
                            updateClearBtnVisibility();
                        }
                    },
                    onClose: function(selectedDates, _dateStr, instance) {
                        if (isProgrammaticDateSync) return;
                        if (selectedDates.length === 1 && dateSelect && dateSelect.value === 'custom') {
                            const singleYmd = formatYmdLocal(selectedDates[0]);
                            if (filterStartDate) filterStartDate.value = singleYmd;
                            if (filterEndDate) filterEndDate.value = singleYmd;
                            isProgrammaticDateSync = true;
                            instance.setDate([selectedDates[0]], false);
                            isProgrammaticDateSync = false;
                        }
                    }
                });

                window.__reportsFlatpickr = fpInstance;
            }

            function syncPresetToFlatpickr(preset, openOnCustomEmpty = false) {
                if (!fpInstance || !flatpickrInput) return;
                const todayBase = parseYmdLocal(flatpickrInput.dataset.today) || new Date();
                isProgrammaticDateSync = true;

                if (preset === 'today') {
                    fpInstance.setDate([todayBase], false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === '7d') {
                    const start = new Date(todayBase);
                    start.setDate(start.getDate() - 6);
                    fpInstance.setDate([start, todayBase], false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === '30d') {
                    const start = new Date(todayBase);
                    start.setDate(start.getDate() - 29);
                    fpInstance.setDate([start, todayBase], false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === 'all') {
                    fpInstance.clear(false);
                    if (filterStartDate) filterStartDate.value = '';
                    if (filterEndDate) filterEndDate.value = '';
                } else if (preset === 'custom') {
                    const sDate = filterStartDate ? parseYmdLocal(filterStartDate.value) : null;
                    const eDate = filterEndDate ? parseYmdLocal(filterEndDate.value) : null;
                    if (sDate && eDate) {
                        if (formatYmdLocal(sDate) === formatYmdLocal(eDate)) {
                            fpInstance.setDate([sDate], false);
                        } else {
                            fpInstance.setDate([sDate, eDate], false);
                        }
                    } else if (sDate) {
                        fpInstance.setDate([sDate], false);
                    } else if (eDate) {
                        fpInstance.setDate([eDate], false);
                    } else {
                        fpInstance.clear(false);
                        if (openOnCustomEmpty) {
                            setTimeout(() => fpInstance.open(), 20);
                        }
                    }
                }

                isProgrammaticDateSync = false;
                updateClearBtnVisibility();
            }

            if (dateSelect) {
                dateSelect.onchange = () => {
                    syncPresetToFlatpickr(dateSelect.value, true);
                };
                syncPresetToFlatpickr(dateSelect.value, false);
            }

            if (clearFlatpickrBtn) {
                clearFlatpickrBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (dateSelect) {
                        dateSelect.value = 'all';
                    }
                    syncPresetToFlatpickr('all', false);
                };
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        }

        initReportsPage();

        if (!window.__reportsListenersBound) {
            window.__reportsListenersBound = true;
            document.addEventListener('DOMContentLoaded', initReportsPage);
            document.addEventListener('livewire:navigated', initReportsPage);

            const closeExportMenu = () => {
                const menu = document.getElementById('exportReportMenu');
                const btn = document.getElementById('exportReportBtn');
                if (!menu || menu.classList.contains('hidden')) return;
                menu.classList.add('hidden');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            };

            // Registered once — closing the menu must not stack listeners on
            // repeated Livewire navigations.
            document.addEventListener('click', (event) => {
                const wrapper = document.getElementById('exportReportWrapper');
                if (!wrapper || (event.target instanceof Node && wrapper.contains(event.target))) return;
                closeExportMenu();
            });

            document.addEventListener('keydown', (event) => {
                const menu = document.getElementById('exportReportMenu');
                const btn = document.getElementById('exportReportBtn');
                if (!menu || menu.classList.contains('hidden')) return;

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeExportMenu();
                    if (btn) btn.focus();
                    return;
                }

                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;

                const options = Array.from(menu.querySelectorAll('.export-report-option'));
                if (options.length === 0) return;

                event.preventDefault();
                const index = options.indexOf(document.activeElement);
                const nextIndex = event.key === 'ArrowDown'
                    ? (index + 1 + options.length) % options.length
                    : (index - 1 + options.length) % options.length;
                options[nextIndex].focus();
            });

            document.addEventListener('livewire:navigating', closeExportMenu);
        }
    </script>
</x-layouts::app>
