@php
    $loadFixtures = require resource_path('views/cash/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['ledgers']) || !empty($fixtureData['expenses']);
@endphp

<x-layouts::app :title="'Kas & Pengeluaran'">
    <main id="mainContent" data-cash-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== CASH HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Kas & Pengeluaran</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Kas & Pengeluaran
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pantau pergerakan kas dan pengeluaran operasional bisnis.
                </p>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-2.5 shrink-0">
                <button
                    type="button"
                    id="recordCashBtn"
                    class="py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600"></i>
                    <span>Catat Kas</span>
                </button>
                <button
                    type="button"
                    id="addExpenseBtn"
                    class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-600/20 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Pengeluaran</span>
                </button>
            </div>
        </div>

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-cash.summary-cards :summary="$fixtureData['summary']" />

        {{-- ==================== 2. TABS & FILTER BAR ==================== --}}
        <div class="space-y-4">
            {{-- Tabs --}}
            <div class="border-b border-slate-200/90 dark:border-slate-800">
                <div
                    class="flex items-center gap-2 overflow-x-auto no-scrollbar"
                    role="tablist"
                    aria-label="Kategori Tab Kas dan Pengeluaran"
                >
                    <button
                        type="button"
                        id="tabLedgers"
                        role="tab"
                        aria-selected="true"
                        aria-controls="panelLedgers"
                        tabindex="0"
                        class="cash-tab-btn flex items-center gap-2 py-3 px-4 border-b-2 font-bold text-xs sm:text-sm transition-colors whitespace-nowrap border-indigo-600 text-indigo-600 dark:text-indigo-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
                    >
                        <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                        <span>Pergerakan Kas</span>
                        <span id="badgeCountLedgers" class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                            {{ count($fixtureData['ledgers']) }}
                        </span>
                    </button>

                    <button
                        type="button"
                        id="tabExpenses"
                        role="tab"
                        aria-selected="false"
                        aria-controls="panelExpenses"
                        tabindex="-1"
                        class="cash-tab-btn flex items-center gap-2 py-3 px-4 border-b-2 font-bold text-xs sm:text-sm transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
                    >
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                        <span>Pengeluaran</span>
                        <span id="badgeCountExpenses" class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            {{ count($fixtureData['expenses']) }}
                        </span>
                    </button>
                </div>
            </div>

            {{-- Filter Bar --}}
            <x-cash.filter-bar
                :categories="$fixtureData['categories']"
                :outlets="$fixtureData['outlets']"
            />
        </div>

        {{-- ==================== 3. DATA PANELS / EMPTY STATE ==================== --}}
        @if($hasData)
            {{-- Panel 1: Pergerakan Kas --}}
            <div id="panelLedgers" role="tabpanel" aria-labelledby="tabLedgers" class="space-y-4">
                {{-- Desktop Table View --}}
                <x-cash.ledger-table :ledgers="$fixtureData['ledgers']" />

                {{-- Mobile Cards View --}}
                <x-cash.mobile-cards :ledgers="$fixtureData['ledgers']" :expenses="[]" />

                {{-- Filter Empty State --}}
                <x-cash.empty-state mode="no-results-cash" />
            </div>

            {{-- Panel 2: Pengeluaran --}}
            <div id="panelExpenses" role="tabpanel" aria-labelledby="tabExpenses" class="space-y-4 hidden">
                {{-- Desktop Table View --}}
                <x-cash.expense-table :expenses="$fixtureData['expenses']" />

                {{-- Mobile Cards View --}}
                <x-cash.mobile-cards :ledgers="[]" :expenses="$fixtureData['expenses']" />

                {{-- Filter Empty State --}}
                <x-cash.empty-state mode="no-results-expense" />
            </div>

            {{-- ==================== 4. PAGINATION ==================== --}}
            <div id="cashPagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan <span id="cashVisibleCount" class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['ledgers']) }}</span> data
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Kas">
                    <button
                        type="button"
                        disabled
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs transition-colors"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-current="page"
                    >
                        1
                    </button>
                    <button
                        type="button"
                        disabled
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs transition-colors"
                    >
                        Berikutnya
                    </button>
                </nav>
            </div>
        @else
            {{-- Production / Non-Local Initial Empty State --}}
            <x-cash.empty-state mode="no-data" />
        @endif

        {{-- ==================== 5. DETAIL DRAWER ==================== --}}
        <x-cash.detail-drawer />

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initCashPage() {
            const root = document.querySelector('main[data-cash-page="true"]');
            if (!root || root.dataset.cashInitialized === 'true') {
                return;
            }
            root.dataset.cashInitialized = 'true';

            let currentTab = 'ledgers'; // 'ledgers' or 'expenses'

            // Tab Elements
            const tabLedgers = document.getElementById('tabLedgers');
            const tabExpenses = document.getElementById('tabExpenses');
            const panelLedgers = document.getElementById('panelLedgers');
            const panelExpenses = document.getElementById('panelExpenses');
            const tabs = [tabLedgers, tabExpenses].filter(Boolean);

            // Filter Elements
            const searchInput = document.getElementById('searchCashInput');
            const filterDate = document.getElementById('filterCashDate');
            const filterOutlet = document.getElementById('filterCashOutlet');
            const filterCashType = document.getElementById('filterCashType');
            const filterExpenseCategory = document.getElementById('filterExpenseCategory');
            const wrapperFilterCashType = document.getElementById('wrapperFilterCashType');
            const wrapperFilterExpenseCategory = document.getElementById('wrapperFilterExpenseCategory');
            const resetFilterBtn = document.getElementById('resetCashFilterBtn');

            const customDateContainer = document.getElementById('cashCustomDateContainer');
            const startDateInput = document.getElementById('cashStartDate');
            const endDateInput = document.getElementById('cashEndDate');

            // Pagination & Count
            const visibleCountEl = document.getElementById('cashVisibleCount');
            const paginationEl = document.getElementById('cashPagination');

            // Action Buttons
            const recordCashBtn = document.getElementById('recordCashBtn');
            const addExpenseBtn = document.getElementById('addExpenseBtn');

            // Drawer Elements
            const drawerWrapper = document.getElementById('cashDrawerWrapper');
            const drawerBackdrop = document.getElementById('cashDrawerBackdrop');
            const drawerPanel = document.getElementById('cashDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeCashDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeCashDrawerFooterBtn');

            let lastTriggerElement = null;

            // Currency Formatter Helper
            function formatRupiah(val) {
                const n = Number(val || 0);
                return 'Rp ' + n.toLocaleString('id-ID');
            }

            // Tab Switcher
            function setTab(tabName, focusTab = false) {
                currentTab = tabName;

                if (tabName === 'ledgers') {
                    tabLedgers?.setAttribute('aria-selected', 'true');
                    tabLedgers?.setAttribute('tabindex', '0');
                    tabLedgers?.classList.add('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400');
                    tabLedgers?.classList.remove('border-transparent', 'text-slate-500', 'hover:text-slate-800');

                    tabExpenses?.setAttribute('aria-selected', 'false');
                    tabExpenses?.setAttribute('tabindex', '-1');
                    tabExpenses?.classList.remove('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400');
                    tabExpenses?.classList.add('border-transparent', 'text-slate-500', 'hover:text-slate-800');

                    panelLedgers?.classList.remove('hidden');
                    panelExpenses?.classList.add('hidden');

                    if (wrapperFilterCashType) wrapperFilterCashType.classList.remove('hidden');
                    if (wrapperFilterExpenseCategory) wrapperFilterExpenseCategory.classList.add('hidden');

                    if (searchInput) searchInput.placeholder = 'Cari referensi, kategori, atau catatan...';

                    if (focusTab) tabLedgers?.focus();
                } else {
                    tabExpenses?.setAttribute('aria-selected', 'true');
                    tabExpenses?.setAttribute('tabindex', '0');
                    tabExpenses?.classList.add('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400');
                    tabExpenses?.classList.remove('border-transparent', 'text-slate-500', 'hover:text-slate-800');

                    tabLedgers?.setAttribute('aria-selected', 'false');
                    tabLedgers?.setAttribute('tabindex', '-1');
                    tabLedgers?.classList.remove('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400');
                    tabLedgers?.classList.add('border-transparent', 'text-slate-500', 'hover:text-slate-800');

                    panelExpenses?.classList.remove('hidden');
                    panelLedgers?.classList.add('hidden');

                    if (wrapperFilterCashType) wrapperFilterCashType.classList.add('hidden');
                    if (wrapperFilterExpenseCategory) wrapperFilterExpenseCategory.classList.remove('hidden');

                    if (searchInput) searchInput.placeholder = 'Cari pengeluaran, kategori, atau catatan...';

                    if (focusTab) tabExpenses?.focus();
                }

                applyFilters();
            }

            tabLedgers?.addEventListener('click', () => setTab('ledgers'));
            tabExpenses?.addEventListener('click', () => setTab('expenses'));

            // Accessible Tab Keyboard Navigation
            tabs.forEach((tab, index) => {
                tab.addEventListener('keydown', (e) => {
                    let targetIndex = null;
                    if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        targetIndex = (index + 1) % tabs.length;
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        targetIndex = (index - 1 + tabs.length) % tabs.length;
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        targetIndex = 0;
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        targetIndex = tabs.length - 1;
                    }

                    if (targetIndex !== null) {
                        const targetTab = tabs[targetIndex];
                        const newTabName = targetTab.id === 'tabLedgers' ? 'ledgers' : 'expenses';
                        setTab(newTabName, true);
                    }
                });
            });

            // Date Range Comparison Logic using occurred_at_raw
            function isDateMatch(rawDateStr, dateFilter) {
                if (!rawDateStr || dateFilter === 'all') return true;

                const itemDate = new Date(rawDateStr.replace(/-/g, '/'));
                if (isNaN(itemDate.getTime())) return true;

                // Reference date from fixture context (2026-09-21)
                const refDate = new Date('2026/09/21 23:59:59');
                const diffMs = refDate.getTime() - itemDate.getTime();
                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

                if (dateFilter === 'today') {
                    return diffDays === 0;
                }
                if (dateFilter === '7d') {
                    return diffDays >= 0 && diffDays < 7;
                }
                if (dateFilter === '30d') {
                    return diffDays >= 0 && diffDays < 30;
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

                    const itemDateOnly = rawDateStr.split(' ')[0];
                    if (sVal && itemDateOnly < sVal) return false;
                    if (eVal && itemDateOnly > eVal) return false;
                    return true;
                }
                return true;
            }

            // Client-side Filtering
            function applyFilters() {
                const search = (searchInput?.value || '').trim().toLowerCase();
                const dateFilter = filterDate?.value || 'all';
                const outletFilter = filterOutlet?.value || 'all';
                const typeFilter = filterCashType?.value || 'all';
                const categoryFilter = filterExpenseCategory?.value || 'all';

                if (currentTab === 'ledgers') {
                    const rows = document.querySelectorAll('.cash-ledger-row');
                    const cards = document.querySelectorAll('.cash-ledger-card');
                    const tableContainer = document.getElementById('desktopCashLedgerTable')?.closest('.rounded-2xl');
                    const mobileContainer = document.getElementById('mobileCashLedgerCards');
                    const emptyState = document.getElementById('cashLedgerFilterEmptyState');

                    let matchedCount = 0;

                    const matchItem = (el) => {
                        const sText = el.getAttribute('data-search') || '';
                        const dRaw = el.getAttribute('data-date-raw') || '';
                        const elOutlet = el.getAttribute('data-outlet');
                        const elType = el.getAttribute('data-type');

                        const matchSearch = !search || sText.includes(search);
                        const matchDate = isDateMatch(dRaw, dateFilter);
                        const matchOutlet = outletFilter === 'all' || elOutlet === outletFilter;
                        const matchType = typeFilter === 'all' || elType === typeFilter;

                        return matchSearch && matchDate && matchOutlet && matchType;
                    };

                    rows.forEach(r => {
                        const m = matchItem(r);
                        r.style.display = m ? '' : 'none';
                        if (m) matchedCount++;
                    });

                    cards.forEach(c => {
                        c.style.display = matchItem(c) ? '' : 'none';
                    });

                    if (visibleCountEl) visibleCountEl.textContent = matchedCount;

                    if (matchedCount === 0) {
                        if (emptyState) emptyState.classList.remove('hidden');
                        if (tableContainer) tableContainer.classList.add('hidden');
                        if (mobileContainer) mobileContainer.classList.add('hidden');
                        if (paginationEl) paginationEl.classList.add('hidden');
                    } else {
                        if (emptyState) emptyState.classList.add('hidden');
                        if (tableContainer) tableContainer.classList.remove('hidden');
                        if (mobileContainer) mobileContainer.classList.remove('hidden');
                        if (paginationEl) paginationEl.classList.remove('hidden');
                    }
                } else {
                    const rows = document.querySelectorAll('.expense-row');
                    const cards = document.querySelectorAll('.expense-card');
                    const tableContainer = document.getElementById('desktopExpenseTable')?.closest('.rounded-2xl');
                    const mobileContainer = document.getElementById('mobileExpenseCards');
                    const emptyState = document.getElementById('expenseFilterEmptyState');

                    let matchedCount = 0;

                    const matchExpense = (el) => {
                        const sText = el.getAttribute('data-search') || '';
                        const dRaw = el.getAttribute('data-date-raw') || '';
                        const elOutlet = el.getAttribute('data-outlet');
                        const elCat = el.getAttribute('data-category');

                        const matchSearch = !search || sText.includes(search);
                        const matchDate = isDateMatch(dRaw, dateFilter);
                        const matchOutlet = outletFilter === 'all' || elOutlet === outletFilter;
                        const matchCat = categoryFilter === 'all' || elCat === categoryFilter;

                        return matchSearch && matchDate && matchOutlet && matchCat;
                    };

                    rows.forEach(r => {
                        const m = matchExpense(r);
                        r.style.display = m ? '' : 'none';
                        if (m) matchedCount++;
                    });

                    cards.forEach(c => {
                        c.style.display = matchExpense(c) ? '' : 'none';
                    });

                    if (visibleCountEl) visibleCountEl.textContent = matchedCount;

                    if (matchedCount === 0) {
                        if (emptyState) emptyState.classList.remove('hidden');
                        if (tableContainer) tableContainer.classList.add('hidden');
                        if (mobileContainer) mobileContainer.classList.add('hidden');
                        if (paginationEl) paginationEl.classList.add('hidden');
                    } else {
                        if (emptyState) emptyState.classList.add('hidden');
                        if (tableContainer) tableContainer.classList.remove('hidden');
                        if (mobileContainer) mobileContainer.classList.remove('hidden');
                        if (paginationEl) paginationEl.classList.remove('hidden');
                    }
                }
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);

            if (filterDate) {
                filterDate.addEventListener('change', () => {
                    if (filterDate.value === 'custom') {
                        customDateContainer?.classList.remove('hidden');
                    } else {
                        customDateContainer?.classList.add('hidden');
                    }
                    applyFilters();
                });
            }

            if (startDateInput) startDateInput.addEventListener('change', applyFilters);
            if (endDateInput) endDateInput.addEventListener('change', applyFilters);
            if (filterOutlet) filterOutlet.addEventListener('change', applyFilters);
            if (filterCashType) filterCashType.addEventListener('change', applyFilters);
            if (filterExpenseCategory) filterExpenseCategory.addEventListener('change', applyFilters);

            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (filterDate) filterDate.value = 'all';
                    if (filterOutlet) filterOutlet.value = 'all';
                    if (filterCashType) filterCashType.value = 'all';
                    if (filterExpenseCategory) filterExpenseCategory.value = 'all';
                    if (startDateInput) startDateInput.value = '';
                    if (endDateInput) endDateInput.value = '';
                    if (customDateContainer) customDateContainer.classList.add('hidden');
                    applyFilters();
                });
            }

            // Action Button Placeholders with Neutral Message
            const handleRecordCash = () => {
                alert('Fitur pencatatan kas akan tersedia setelah integrasi data.');
            };
            const handleAddExpense = () => {
                alert('Fitur pengeluaran akan tersedia setelah integrasi data.');
            };

            if (recordCashBtn) recordCashBtn.onclick = handleRecordCash;
            if (addExpenseBtn) addExpenseBtn.onclick = handleAddExpense;

            // Detail Drawer Logic (Safe DOM methods)
            function openDrawer(itemData, isExpense = false) {
                if (!drawerWrapper || !itemData) return;

                const drawerTitle = document.getElementById('cashDrawerTitle');
                const drawerSubtitle = document.getElementById('cashDrawerSubtitle');
                const amountEl = document.getElementById('cashDrawerAmount');
                const badgeContainer = document.getElementById('cashDrawerBadgeContainer');

                const rowOccurredAt = document.getElementById('drawerRowOccurredAt');
                const rowOutlet = document.getElementById('drawerRowOutlet');
                const rowShift = document.getElementById('drawerRowShift');
                const rowCategory = document.getElementById('drawerRowCategory');
                const rowRef = document.getElementById('drawerRowRef');
                const rowSaleSync = document.getElementById('drawerRowSaleSync');
                const rowStatus = document.getElementById('drawerRowStatus');
                const notesContainer = document.getElementById('drawerNotesContainer');

                badgeContainer.textContent = '';

                if (isExpense) {
                    drawerSubtitle.textContent = 'Detail Pengeluaran';
                    drawerTitle.textContent = itemData.description || '-';
                    amountEl.textContent = formatRupiah(itemData.amount);
                    amountEl.className = 'font-extrabold text-xl sm:text-2xl text-slate-900 dark:text-white tabular-nums';

                    const badge = document.createElement('span');
                    badge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    const dot = document.createElement('span');
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    const label = document.createElement('span');
                    label.textContent = itemData.status === 'recorded' ? 'Tercatat' : (itemData.status || '-');
                    badge.appendChild(dot);
                    badge.appendChild(label);
                    badgeContainer.appendChild(badge);

                    document.getElementById('cashDrawerOccurredAt').textContent = itemData.occurred_at || '-';
                    document.getElementById('cashDrawerOutlet').textContent = itemData.outlet_name || '-';

                    if (itemData.shift_number) {
                        rowShift.classList.remove('hidden');
                        document.getElementById('cashDrawerShift').textContent = itemData.shift_number;
                    } else {
                        rowShift.classList.add('hidden');
                    }

                    if (itemData.category) {
                        rowCategory.classList.remove('hidden');
                        document.getElementById('cashDrawerCategory').textContent = itemData.category;
                    } else {
                        rowCategory.classList.add('hidden');
                    }

                    rowRef.classList.add('hidden');
                    rowSaleSync.classList.add('hidden');

                    rowStatus.classList.remove('hidden');
                    document.getElementById('cashDrawerStatus').textContent = itemData.status === 'recorded' ? 'Tercatat' : (itemData.status || '-');

                    if (itemData.notes) {
                        notesContainer.classList.remove('hidden');
                        document.getElementById('cashDrawerNotes').textContent = itemData.notes;
                    } else {
                        notesContainer.classList.add('hidden');
                    }
                } else {
                    const isIn = itemData.type === 'in';
                    drawerSubtitle.textContent = 'Detail Pergerakan Kas';
                    drawerTitle.textContent = itemData.category_label || (isIn ? 'Kas Masuk' : 'Kas Keluar');

                    const sign = isIn ? '+' : '-';
                    amountEl.textContent = `${sign} ${formatRupiah(itemData.amount)}`;
                    amountEl.className = `font-extrabold text-xl sm:text-2xl tabular-nums ${isIn ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`;

                    const badge = document.createElement('span');
                    badge.className = `inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold ${isIn ? 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400'}`;
                    const dot = document.createElement('span');
                    dot.className = `w-1.5 h-1.5 rounded-full ${isIn ? 'bg-emerald-500' : 'bg-rose-500'}`;
                    const label = document.createElement('span');
                    label.textContent = isIn ? 'Kas Masuk' : 'Kas Keluar';
                    badge.appendChild(dot);
                    badge.appendChild(label);
                    badgeContainer.appendChild(badge);

                    document.getElementById('cashDrawerOccurredAt').textContent = itemData.occurred_at || '-';
                    document.getElementById('cashDrawerOutlet').textContent = itemData.outlet_name || '-';

                    if (itemData.shift_number) {
                        rowShift.classList.remove('hidden');
                        document.getElementById('cashDrawerShift').textContent = itemData.shift_number;
                    } else {
                        rowShift.classList.add('hidden');
                    }

                    if (itemData.category || itemData.category_label) {
                        rowCategory.classList.remove('hidden');
                        document.getElementById('cashDrawerCategory').textContent = itemData.category_label || itemData.category;
                    } else {
                        rowCategory.classList.add('hidden');
                    }

                    if (itemData.reference_id) {
                        rowRef.classList.remove('hidden');
                        document.getElementById('cashDrawerRef').textContent = itemData.reference_id;
                    } else {
                        rowRef.classList.add('hidden');
                    }

                    if (itemData.sale_sync_id) {
                        rowSaleSync.classList.remove('hidden');
                        document.getElementById('cashDrawerSaleSync').textContent = itemData.sale_sync_id;
                    } else {
                        rowSaleSync.classList.add('hidden');
                    }

                    rowStatus.classList.add('hidden');

                    if (itemData.note) {
                        notesContainer.classList.remove('hidden');
                        document.getElementById('cashDrawerNotes').textContent = itemData.note;
                    } else {
                        notesContainer.classList.add('hidden');
                    }
                }

                // Show Drawer
                drawerWrapper.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    drawerBackdrop.classList.remove('opacity-0');
                    drawerBackdrop.classList.add('opacity-100');
                    drawerPanel.classList.remove('translate-x-full');
                    drawerPanel.classList.add('translate-x-0');
                    closeDrawerBtn?.focus();
                });

                document.addEventListener('keydown', handleCashDrawerTrap);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleCashDrawerTrap);

                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (lastTriggerElement && typeof lastTriggerElement.focus === 'function') {
                        lastTriggerElement.focus();
                    }
                }, 300);
            }

            function handleCashDrawerTrap(e) {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDrawer();
                    return;
                }

                if (e.key === 'Tab') {
                    const focusables = drawerPanel.querySelectorAll(
                        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    );
                    if (focusables.length === 0) return;

                    const firstEl = focusables[0];
                    const lastEl = focusables[focusables.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === firstEl || !drawerPanel.contains(document.activeElement)) {
                            e.preventDefault();
                            lastEl.focus();
                        }
                    } else {
                        if (document.activeElement === lastEl || !drawerPanel.contains(document.activeElement)) {
                            e.preventDefault();
                            firstEl.focus();
                        }
                    }
                }
            }

            // Click listener for Cash Ledger Details
            document.querySelectorAll('.view-cash-detail-btn').forEach(btn => {
                btn.onclick = () => {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.cash-ledger-row, .cash-ledger-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                openDrawer(JSON.parse(raw), false);
                            } catch (e) {
                                console.error('Failed to parse cash data', e);
                            }
                        }
                    }
                };
            });

            // Click listener for Expense Details
            document.querySelectorAll('.view-expense-detail-btn').forEach(btn => {
                btn.onclick = () => {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.expense-row, .expense-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                openDrawer(JSON.parse(raw), true);
                            } catch (e) {
                                console.error('Failed to parse expense data', e);
                            }
                        }
                    }
                };
            });

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;

            // Initial Filter Run
            setTab('ledgers');
        }

        initCashPage();

        if (!window.__cashListenersBound) {
            window.__cashListenersBound = true;
            document.addEventListener('DOMContentLoaded', initCashPage);
            document.addEventListener('livewire:navigated', initCashPage);
        }
    </script>
</x-layouts::app>
