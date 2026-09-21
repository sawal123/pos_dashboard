@php
    $loadFixtures = require resource_path('views/reports/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['sales']) || !empty($fixtureData['expenses']);
@endphp

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
        <x-reports.summary-cards :summary="$fixtureData['summary']" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-reports.filter-bar :outlets="$fixtureData['outlets']" />

        {{-- ==================== 3. REPORT CONTENT / EMPTY STATE ==================== --}}
        @if($hasData)
            <div id="reportContentArea" class="space-y-6">
                {{-- Section 1: Sales Trend (Horizontal CSS Bars) --}}
                <x-reports.sales-trend />

                {{-- Section 2: Breakdown Grid (Payment Methods & Expense Categories) --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Left: Payment Methods Breakdown --}}
                    <x-reports.payment-breakdown />

                    {{-- Right: Expense Categories Breakdown --}}
                    <x-reports.expense-breakdown />
                </div>
            </div>

            {{-- Filter No Results Empty State --}}
            <x-reports.empty-state mode="no-results" />
        @else
            {{-- Production / Non-Local Initial Empty State --}}
            <x-reports.empty-state mode="no-data" />
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

            // Raw Source Fixtures
            const allSales = @json($fixtureData['sales'] ?? []);
            const allExpenses = @json($fixtureData['expenses'] ?? []);

            // Controls
            const filterDate = document.getElementById('filterReportDate');
            const filterOutlet = document.getElementById('filterReportOutlet');
            const resetBtn = document.getElementById('resetReportFilterBtn');
            const exportBtn = document.getElementById('exportReportBtn');
            const customDateContainer = document.getElementById('reportCustomDateContainer');
            const startDateInput = document.getElementById('reportStartDate');
            const endDateInput = document.getElementById('reportEndDate');

            // Summary Elements
            const summarySalesEl = document.getElementById('reportSummarySales');
            const summaryTrxEl = document.getElementById('reportSummaryTransactions');
            const summaryProfitEl = document.getElementById('reportSummaryGrossProfit');
            const summaryExpensesEl = document.getElementById('reportSummaryExpenses');

            // Visual Containers
            const contentArea = document.getElementById('reportContentArea');
            const emptyState = document.getElementById('reportFilterEmptyState');
            const salesTrendContainer = document.getElementById('salesTrendContainer');
            const salesTrendEmpty = document.getElementById('salesTrendEmpty');
            const paymentContainer = document.getElementById('paymentBreakdownContainer');
            const paymentEmpty = document.getElementById('paymentBreakdownEmpty');
            const expenseContainer = document.getElementById('expenseBreakdownContainer');
            const expenseEmpty = document.getElementById('expenseBreakdownEmpty');
            const periodLabel = document.getElementById('salesTrendPeriodLabel');

            // Currency Formatter Helper (with decimal support if non-zero fractional)
            function formatRupiah(val) {
                const num = Number(val || 0);
                if (Math.floor(num) === num) {
                    return 'Rp ' .concat(num.toLocaleString('id-ID'));
                }
                return 'Rp ' .concat(num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }

            // Helper for local calendar date formatting YYYY-MM-DD
            function getLocalDateString(d) {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            // Date match evaluator using browser local calendar
            function isDateMatch(rawDateStr, dateFilter) {
                if (!rawDateStr || dateFilter === 'all') return true;

                const itemDateOnly = rawDateStr.split(' ')[0];
                if (!itemDateOnly) return true;

                const now = new Date();
                const todayStr = getLocalDateString(now);

                if (dateFilter === 'today') {
                    return itemDateOnly === todayStr;
                }
                if (dateFilter === '7d') {
                    const d7 = new Date(now);
                    d7.setDate(d7.getDate() - 6);
                    const d7Str = getLocalDateString(d7);
                    return itemDateOnly >= d7Str && itemDateOnly <= todayStr;
                }
                if (dateFilter === '30d') {
                    const d30 = new Date(now);
                    d30.setDate(d30.getDate() - 29);
                    const d30Str = getLocalDateString(d30);
                    return itemDateOnly >= d30Str && itemDateOnly <= todayStr;
                }
                if (dateFilter === 'custom') {
                    let sVal = startDateInput?.value;
                    let eVal = endDateInput?.value;
                    if (!sVal && !eVal) return true;

                    if (sVal && eVal && sVal > eVal) {
                        const tmp = sVal;
                        sVal = eVal;
                        eVal = tmp;
                    }

                    if (sVal && itemDateOnly < sVal) return false;
                    if (eVal && itemDateOnly > eVal) return false;
                    return true;
                }
                return true;
            }

            // Master Update Function
            function updateReports() {
                const dFilter = filterDate?.value || 'all';
                const oFilter = filterOutlet?.value || 'all';

                if (periodLabel) {
                    periodLabel.textContent = matchPeriodLabel(dFilter);
                }

                // Filter Sales
                const filteredSales = allSales.filter(s => {
                    const matchDate = isDateMatch(s.sold_at_raw, dFilter);
                    const matchOutlet = oFilter === 'all' || s.outlet_name === oFilter;
                    return matchDate && matchOutlet;
                });

                // Filter Expenses
                const filteredExpenses = allExpenses.filter(e => {
                    const matchDate = isDateMatch(e.occurred_at_raw, dFilter);
                    const matchOutlet = oFilter === 'all' || e.outlet_name === oFilter;
                    return matchDate && matchOutlet;
                });

                const totalItemsCount = filteredSales.length + filteredExpenses.length;

                // Show/hide overall empty state
                if (totalItemsCount === 0) {
                    if (contentArea) contentArea.classList.add('hidden');
                    if (emptyState) emptyState.classList.remove('hidden');
                } else {
                    if (contentArea) contentArea.classList.remove('hidden');
                    if (emptyState) emptyState.classList.add('hidden');
                }

                // 1. Calculate & Render Summary Metrics
                let totalSalesAmount = 0;
                let totalGrossProfit = 0;
                filteredSales.forEach(s => {
                    totalSalesAmount += Number(s.total_amount || 0);
                    totalGrossProfit += Number(s.gross_profit || 0);
                });

                let totalExpenseAmount = 0;
                filteredExpenses.forEach(e => {
                    totalExpenseAmount += Number(e.amount || 0);
                });

                if (summarySalesEl) summarySalesEl.textContent = formatRupiah(totalSalesAmount);
                if (summaryTrxEl) summaryTrxEl.textContent = filteredSales.length.toLocaleString('id-ID');
                if (summaryProfitEl) summaryProfitEl.textContent = formatRupiah(totalGrossProfit);
                if (summaryExpensesEl) summaryExpensesEl.textContent = formatRupiah(totalExpenseAmount);

                // 2. Render Sales Trend (Horizontal Bars)
                renderSalesTrend(filteredSales);

                // 3. Render Payment Breakdown
                renderPaymentBreakdown(filteredSales);

                // 4. Render Expense Breakdown
                renderExpenseBreakdown(filteredExpenses, totalExpenseAmount);
            }

            function matchPeriodLabel(dFilter) {
                switch (dFilter) {
                    case 'today': return 'Hari Ini';
                    case '7d': return '7 Hari Terakhir';
                    case '30d': return '30 Hari Terakhir';
                    case 'custom': return 'Periode Kustom';
                    default: return 'Semua Waktu';
                }
            }

            // Safe DOM Rendering for Sales Trend
            function renderSalesTrend(sales) {
                if (!salesTrendContainer) return;
                salesTrendContainer.textContent = '';

                if (sales.length === 0) {
                    if (salesTrendEmpty) salesTrendEmpty.classList.remove('hidden');
                    return;
                }
                if (salesTrendEmpty) salesTrendEmpty.classList.add('hidden');

                // Group by Date string (e.g. 21 Sep 2026)
                const dayMap = {};
                sales.forEach(s => {
                    const dateDisplay = s.sold_at.split('·')[0].trim();
                    const rawDate = s.sold_at_raw.split(' ')[0];
                    if (!dayMap[rawDate]) {
                        dayMap[rawDate] = {
                            dateDisplay: dateDisplay,
                            totalAmount: 0,
                            count: 0
                        };
                    }
                    dayMap[rawDate].totalAmount += Number(s.total_amount || 0);
                    dayMap[rawDate].count += 1;
                });

                const sortedDays = Object.keys(dayMap).sort().reverse();
                let maxAmount = 0;
                sortedDays.forEach(d => {
                    if (dayMap[d].totalAmount > maxAmount) {
                        maxAmount = dayMap[d].totalAmount;
                    }
                });

                sortedDays.forEach(d => {
                    const data = dayMap[d];
                    const pct = maxAmount > 0 ? Math.max(8, Math.round((data.totalAmount / maxAmount) * 100)) : 0;

                    const row = document.createElement('div');
                    row.className = 'space-y-1 text-xs';

                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between text-slate-700 dark:text-slate-300';

                    const leftSpan = document.createElement('span');
                    leftSpan.className = 'font-bold text-slate-900 dark:text-white';
                    leftSpan.textContent = data.dateDisplay;

                    const rightContainer = document.createElement('div');
                    rightContainer.className = 'flex items-center gap-3';

                    const countSpan = document.createElement('span');
                    countSpan.className = 'text-[11px] text-slate-400 dark:text-slate-500';
                    countSpan.textContent = `${data.count} transaksi`;

                    const amountSpan = document.createElement('span');
                    amountSpan.className = 'font-extrabold text-slate-900 dark:text-white tabular-nums';
                    amountSpan.textContent = formatRupiah(data.totalAmount);

                    rightContainer.appendChild(countSpan);
                    rightContainer.appendChild(amountSpan);
                    header.appendChild(leftSpan);
                    header.appendChild(rightContainer);

                    const track = document.createElement('div');
                    track.className = 'h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden';

                    const bar = document.createElement('div');
                    bar.className = 'h-full rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all duration-300';
                    bar.style.width = `${pct}%`;

                    track.appendChild(bar);
                    row.appendChild(header);
                    row.appendChild(track);
                    salesTrendContainer.appendChild(row);
                });
            }

            // Safe DOM Rendering for Payment Breakdown
            function renderPaymentBreakdown(sales) {
                if (!paymentContainer) return;
                paymentContainer.textContent = '';

                if (sales.length === 0) {
                    if (paymentEmpty) paymentEmpty.classList.remove('hidden');
                    return;
                }
                if (paymentEmpty) paymentEmpty.classList.add('hidden');

                const methodMap = {};
                sales.forEach(s => {
                    const m = s.payment_method || 'Lainnya';
                    if (!methodMap[m]) {
                        methodMap[m] = { count: 0, amount: 0 };
                    }
                    methodMap[m].count += 1;
                    methodMap[m].amount += Number(s.total_amount || 0);
                });

                const totalTrx = sales.length;
                const sortedMethods = Object.keys(methodMap).sort((a, b) => methodMap[b].count - methodMap[a].count);

                sortedMethods.forEach(method => {
                    const item = methodMap[method];
                    const pct = totalTrx > 0 ? Math.round((item.count / totalTrx) * 100) : 0;

                    const row = document.createElement('div');
                    row.className = 'space-y-1 text-xs';

                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between text-slate-700 dark:text-slate-300';

                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'font-bold text-slate-900 dark:text-white';
                    titleSpan.textContent = method;

                    const statsSpan = document.createElement('span');
                    statsSpan.className = 'tabular-nums text-slate-600 dark:text-slate-400';
                    statsSpan.textContent = `${item.count} transaksi (${pct}%) · ${formatRupiah(item.amount)}`;

                    header.appendChild(titleSpan);
                    header.appendChild(statsSpan);

                    const track = document.createElement('div');
                    track.className = 'h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden';

                    const bar = document.createElement('div');
                    bar.className = 'h-full rounded-full bg-indigo-500 transition-all duration-300';
                    bar.style.width = `${pct}%`;

                    track.appendChild(bar);
                    row.appendChild(header);
                    row.appendChild(track);
                    paymentContainer.appendChild(row);
                });
            }

            // Safe DOM Rendering for Expense Breakdown
            function renderExpenseBreakdown(expenses, totalExpenseAmount) {
                if (!expenseContainer) return;
                expenseContainer.textContent = '';

                if (expenses.length === 0) {
                    if (expenseEmpty) expenseEmpty.classList.remove('hidden');
                    return;
                }
                if (expenseEmpty) expenseEmpty.classList.add('hidden');

                const catMap = {};
                expenses.forEach(e => {
                    const c = e.category || 'Tanpa Kategori';
                    if (!catMap[c]) {
                        catMap[c] = { count: 0, amount: 0 };
                    }
                    catMap[c].count += 1;
                    catMap[c].amount += Number(e.amount || 0);
                });

                const sortedCats = Object.keys(catMap).sort((a, b) => catMap[b].amount - catMap[a].amount);

                sortedCats.forEach(cat => {
                    const item = catMap[cat];
                    const pct = totalExpenseAmount > 0 ? Math.round((item.amount / totalExpenseAmount) * 100) : 0;

                    const row = document.createElement('div');
                    row.className = 'space-y-1 text-xs';

                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between text-slate-700 dark:text-slate-300';

                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'font-bold text-slate-900 dark:text-white';
                    titleSpan.textContent = cat;

                    const statsSpan = document.createElement('span');
                    statsSpan.className = 'tabular-nums text-slate-600 dark:text-slate-400';
                    statsSpan.textContent = `${item.count} pengeluaran (${pct}%) · ${formatRupiah(item.amount)}`;

                    header.appendChild(titleSpan);
                    header.appendChild(statsSpan);

                    const track = document.createElement('div');
                    track.className = 'h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden';

                    const bar = document.createElement('div');
                    bar.className = 'h-full rounded-full bg-amber-500 transition-all duration-300';
                    bar.style.width = `${pct}%`;

                    track.appendChild(bar);
                    row.appendChild(header);
                    row.appendChild(track);
                    expenseContainer.appendChild(row);
                });
            }

            // Event Listeners
            if (filterDate) {
                filterDate.addEventListener('change', () => {
                    if (filterDate.value === 'custom') {
                        customDateContainer?.classList.remove('hidden');
                    } else {
                        customDateContainer?.classList.add('hidden');
                    }
                    updateReports();
                });
            }

            if (startDateInput) startDateInput.addEventListener('change', updateReports);
            if (endDateInput) endDateInput.addEventListener('change', updateReports);
            if (filterOutlet) filterOutlet.addEventListener('change', updateReports);

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (filterDate) filterDate.value = 'all';
                    if (filterOutlet) filterOutlet.value = 'all';
                    if (startDateInput) startDateInput.value = '';
                    if (endDateInput) endDateInput.value = '';
                    if (customDateContainer) customDateContainer.classList.add('hidden');
                    updateReports();
                });
            }

            if (exportBtn) {
                exportBtn.onclick = () => {
                    alert('Ekspor laporan akan tersedia setelah integrasi data.');
                };
            }

            // Run initial update
            updateReports();
        }

        initReportsPage();

        if (!window.__reportsListenersBound) {
            window.__reportsListenersBound = true;
            document.addEventListener('DOMContentLoaded', initReportsPage);
            document.addEventListener('livewire:navigated', initReportsPage);
        }
    </script>
</x-layouts::app>
