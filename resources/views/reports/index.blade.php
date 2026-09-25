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
            const filterForm = document.getElementById('reportFilterForm');
            let exportBusy = false;

            const setExportMenuOpen = (open) => {
                if (!exportMenu || !exportBtn) return;
                exportMenu.classList.toggle('hidden', !open);
                exportBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            // Build the download URL from the form's *current* values so the
            // export always carries the filters the user is looking at.
            const exportUrl = (baseUrl) => {
                const url = new URL(baseUrl, window.location.origin);
                const params = new URLSearchParams();

                if (filterForm) {
                    ['date', 'start_date', 'end_date', 'outlet_id'].forEach((name) => {
                        const field = filterForm.elements.namedItem(name);
                        if (!field) return;
                        const value = field.value;
                        if (value === '' || value === 'all') return;
                        params.set(name, value);
                    });
                }

                url.search = params.toString();

                return url.toString();
            };

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

                    window.location.href = exportUrl(option.getAttribute('data-export-base') || option.href);

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

            if (dateSelect && customDateContainer) {
                dateSelect.onchange = () => {
                    customDateContainer.classList.toggle('hidden', dateSelect.value !== 'custom');
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
