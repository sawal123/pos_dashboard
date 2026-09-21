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
        @php
            $isFilterEmpty = ($summary['total_sales'] ?? 0) === 0
                && ($summary['total_transactions'] ?? 0) === 0
                && ($summary['total_expenses'] ?? 0) === 0;
        @endphp

        @if(!$hasAnyReportData)
            <x-reports.empty-state mode="no-data" />
        @elseif($isFilterEmpty)
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

        {{-- Toast notification for placeholder actions --}}
        <div id="reportActionToast" class="fixed bottom-6 right-6 z-50 transform transition-all duration-300 translate-y-20 opacity-0 pointer-events-none">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow-xl text-xs font-semibold">
                <i data-lucide="info" class="w-4 h-4 text-indigo-400 dark:text-indigo-600"></i>
                <span id="reportActionToastText">Aksi belum tersedia</span>
            </div>
        </div>

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initReportsPage() {
            const root = document.querySelector('main[data-reports-page="true"]');
            if (!root || root.dataset.reportsInitialized === 'true') {
                return;
            }
            root.dataset.reportsInitialized = 'true';

            // Date select toggle
            const dateSelect = document.getElementById('filterReportDate');
            const customDateContainer = document.getElementById('reportCustomDateContainer');
            const exportBtn = document.getElementById('exportReportBtn');
            const toastEl = document.getElementById('reportActionToast');
            const toastTextEl = document.getElementById('reportActionToastText');
            let toastTimer = null;

            function showToast(message) {
                if (!toastEl || !toastTextEl) return;
                toastTextEl.textContent = message;
                toastEl.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
                toastEl.classList.add('translate-y-0', 'opacity-100');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(() => {
                    toastEl.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
                    toastEl.classList.remove('translate-y-0', 'opacity-100');
                }, 3000);
            }

            if (exportBtn) {
                exportBtn.addEventListener('click', () => {
                    showToast('Ekspor laporan dari dashboard belum tersedia.');
                });
            }

            if (dateSelect && customDateContainer) {
                dateSelect.addEventListener('change', () => {
                    if (dateSelect.value === 'custom') {
                        customDateContainer.classList.remove('hidden');
                    } else {
                        customDateContainer.classList.add('hidden');
                    }
                });
            }

            if (window.lucide) {
                window.lucide.createIcons();
            }
        }

        document.addEventListener('DOMContentLoaded', initReportsPage);
        document.addEventListener('livewire:navigated', () => {
            const root = document.querySelector('main[data-reports-page="true"]');
            if (root) {
                root.dataset.reportsInitialized = 'false';
                initReportsPage();
            }
        });
    </script>
</x-layouts::app>
