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
        <x-cash.summary-cards :summary="$summary" />

        {{-- ==================== 2. TABS & FILTER BAR ==================== --}}
        <div class="space-y-4">
            {{-- Tabs --}}
            <div class="border-b border-slate-200/90 dark:border-slate-800">
                <div
                    class="flex items-center gap-2 overflow-x-auto no-scrollbar"
                    role="tablist"
                    aria-label="Kategori Tab Kas dan Pengeluaran"
                >
                    <a
                        href="{{ route('cash.index', array_filter(array_merge($currentFilters, ['tab' => 'ledgers', 'page' => 1]))) }}"
                        id="tabLedgers"
                        role="tab"
                        aria-selected="{{ $activeTab === 'ledgers' ? 'true' : 'false' }}"
                        aria-controls="panelLedgers"
                        tabindex="{{ $activeTab === 'ledgers' ? '0' : '-1' }}"
                        class="cash-tab-btn flex items-center gap-2 py-3 px-4 border-b-2 font-bold text-xs sm:text-sm transition-colors whitespace-nowrap {{ $activeTab === 'ledgers' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
                    >
                        <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                        <span>Pergerakan Kas</span>
                        <span id="badgeCountLedgers" class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $activeTab === 'ledgers' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                            {{ number_format($tabCounts['ledgers'] ?? 0, 0, ',', '.') }}
                        </span>
                    </a>

                    <a
                        href="{{ route('cash.index', array_filter(array_merge($currentFilters, ['tab' => 'expenses', 'page' => 1]))) }}"
                        id="tabExpenses"
                        role="tab"
                        aria-selected="{{ $activeTab === 'expenses' ? 'true' : 'false' }}"
                        aria-controls="panelExpenses"
                        tabindex="{{ $activeTab === 'expenses' ? '0' : '-1' }}"
                        class="cash-tab-btn flex items-center gap-2 py-3 px-4 border-b-2 font-bold text-xs sm:text-sm transition-colors whitespace-nowrap {{ $activeTab === 'expenses' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t-lg"
                    >
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                        <span>Pengeluaran</span>
                        <span id="badgeCountExpenses" class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $activeTab === 'expenses' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                            {{ number_format($tabCounts['expenses'] ?? 0, 0, ',', '.') }}
                        </span>
                    </a>
                </div>
            </div>

            {{-- Filter Bar --}}
            <x-cash.filter-bar
                :activeTab="$activeTab"
                :categories="$filterOptions['categories'] ?? []"
                :outlets="$filterOptions['outlets'] ?? []"
                :currentFilters="$currentFilters"
            />
        </div>

        {{-- ==================== 3. DATA TABLES & CARDS ==================== --}}
        @if($activeTab === 'ledgers')
            <div id="panelLedgers" role="tabpanel" aria-labelledby="tabLedgers" class="space-y-4">
                @if(!$hasAnyLedgers)
                    <x-cash.empty-state mode="no-data-cash" />
                @elseif($ledgers->isEmpty())
                    <x-cash.empty-state mode="no-results-cash" />
                @else
                    <x-cash.ledger-table :ledgers="$ledgers" />
                    <x-cash.mobile-cards :activeTab="'ledgers'" :ledgers="$ledgers" />

                    @if($ledgers->hasPages())
                        <div class="pt-2">
                            {{ $ledgers->links() }}
                        </div>
                    @endif
                @endif
            </div>
        @else
            <div id="panelExpenses" role="tabpanel" aria-labelledby="tabExpenses" class="space-y-4">
                @if(!$hasAnyExpenses)
                    <x-cash.empty-state mode="no-data-expense" />
                @elseif($expenses->isEmpty())
                    <x-cash.empty-state mode="no-results-expense" />
                @else
                    <x-cash.expense-table :expenses="$expenses" />
                    <x-cash.mobile-cards :activeTab="'expenses'" :expenses="$expenses" />

                    @if($expenses->hasPages())
                        <div class="pt-2">
                            {{ $expenses->links() }}
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- ==================== 4. DETAIL DRAWER ==================== --}}
        <x-cash.detail-drawer />

        {{-- Toast container for placeholder messages --}}
        <div id="cashActionToast" class="fixed bottom-6 right-6 z-50 transform transition-all duration-300 translate-y-20 opacity-0 pointer-events-none">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow-xl text-xs font-semibold">
                <i data-lucide="info" class="w-4 h-4 text-indigo-400 dark:text-indigo-600"></i>
                <span id="cashActionToastText">Aksi belum tersedia</span>
            </div>
        </div>

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initCashPage() {
            const root = document.querySelector('main[data-cash-page="true"]');
            if (!root || root.dataset.cashInitialized === 'true') {
                return;
            }
            root.dataset.cashInitialized = 'true';

            // Controls
            const dateSelect = document.getElementById('filterCashDate');
            const customDateContainer = document.getElementById('cashCustomDateContainer');
            const recordCashBtn = document.getElementById('recordCashBtn');
            const addExpenseBtn = document.getElementById('addExpenseBtn');
            const toastEl = document.getElementById('cashActionToast');
            const toastTextEl = document.getElementById('cashActionToastText');
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

            if (recordCashBtn) {
                recordCashBtn.addEventListener('click', () => {
                    showToast('Catat kas dari dashboard belum tersedia.');
                });
            }

            if (addExpenseBtn) {
                addExpenseBtn.addEventListener('click', () => {
                    showToast('Tambah pengeluaran dari dashboard belum tersedia.');
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

            // Drawer elements
            const drawerWrapper = document.getElementById('cashDrawerWrapper');
            const drawerBackdrop = document.getElementById('cashDrawerBackdrop');
            const drawerPanel = document.getElementById('cashDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeCashDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeCashDrawerFooterBtn');

            const drawerTitle = document.getElementById('cashDrawerTitle');
            const drawerSubtitle = document.getElementById('cashDrawerSubtitle');
            const drawerAmount = document.getElementById('cashDrawerAmount');
            const drawerBadgeContainer = document.getElementById('cashDrawerBadgeContainer');
            const drawerOccurredAt = document.getElementById('cashDrawerOccurredAt');
            const drawerOutlet = document.getElementById('cashDrawerOutlet');
            const drawerShift = document.getElementById('cashDrawerShift');
            const drawerCategory = document.getElementById('cashDrawerCategory');
            const drawerRef = document.getElementById('cashDrawerRef');
            const drawerStatusRow = document.getElementById('drawerRowStatus');
            const drawerStatus = document.getElementById('cashDrawerStatus');
            const drawerNotesContainer = document.getElementById('drawerNotesContainer');
            const drawerNotes = document.getElementById('cashDrawerNotes');

            let lastTriggerElement = null;

            function formatRupiah(val) {
                const num = Number(val || 0);
                return 'Rp ' + num.toLocaleString('id-ID');
            }

            function handleDrawerTrap(e) {
                if (e.key === 'Escape') {
                    closeDrawer();
                    return;
                }
                if (e.key === 'Tab' && drawerPanel) {
                    const focusables = drawerPanel.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (focusables.length === 0) return;
                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }

            function openDrawer(itemData) {
                if (!drawerWrapper || !itemData) return;

                const isExpense = 'description' in itemData;

                if (isExpense) {
                    drawerSubtitle.textContent = 'Detail Pengeluaran';
                    drawerTitle.textContent = itemData.description || 'Pengeluaran';
                    drawerAmount.textContent = formatRupiah(itemData.amount);
                    drawerAmount.className = 'font-extrabold text-xl sm:text-2xl text-slate-900 dark:text-white tabular-nums';

                    drawerBadgeContainer.textContent = '';
                    const badge = document.createElement('span');
                    badge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    const dot = document.createElement('span');
                    dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    badge.appendChild(dot);
                    const label = document.createElement('span');
                    label.textContent = itemData.status || 'Tercatat';
                    badge.appendChild(label);
                    drawerBadgeContainer.appendChild(badge);

                    if (drawerStatusRow) drawerStatusRow.classList.remove('hidden');
                    if (drawerStatus) drawerStatus.textContent = itemData.status || 'Tercatat';
                    if (drawerRef) drawerRef.textContent = '-';
                    const noteText = itemData.notes || '';
                    if (noteText.trim()) {
                        if (drawerNotesContainer) drawerNotesContainer.classList.remove('hidden');
                        if (drawerNotes) drawerNotes.textContent = noteText;
                    } else {
                        if (drawerNotesContainer) drawerNotesContainer.classList.add('hidden');
                    }
                } else {
                    drawerSubtitle.textContent = 'Detail Pergerakan Kas';
                    drawerTitle.textContent = itemData.reference_id ? itemData.reference_id : 'Pergerakan Kas';

                    const isIn = itemData.type_raw === 'in';
                    const isOut = itemData.type_raw === 'out';
                    const sign = isIn ? '+ ' : (isOut ? '- ' : '');
                    drawerAmount.textContent = sign + formatRupiah(itemData.amount);
                    drawerAmount.className = 'font-extrabold text-xl sm:text-2xl tabular-nums ' +
                        (isIn ? 'text-emerald-600 dark:text-emerald-400' : (isOut ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'));

                    drawerBadgeContainer.textContent = '';
                    const badge = document.createElement('span');
                    badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold ' +
                        (isIn ? 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400' :
                        (isOut ? 'bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400' :
                        'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'));
                    const dot = document.createElement('span');
                    dot.className = 'w-1.5 h-1.5 rounded-full ' + (isIn ? 'bg-emerald-500' : (isOut ? 'bg-rose-500' : 'bg-slate-400'));
                    badge.appendChild(dot);
                    const label = document.createElement('span');
                    label.textContent = itemData.type || (isIn ? 'Kas Masuk' : (isOut ? 'Kas Keluar' : itemData.type_raw));
                    badge.appendChild(label);
                    drawerBadgeContainer.appendChild(badge);

                    if (drawerStatusRow) drawerStatusRow.classList.add('hidden');
                    if (drawerRef) drawerRef.textContent = itemData.reference_id || '-';
                    const noteText = itemData.note || '';
                    if (noteText.trim()) {
                        if (drawerNotesContainer) drawerNotesContainer.classList.remove('hidden');
                        if (drawerNotes) drawerNotes.textContent = noteText;
                    } else {
                        if (drawerNotesContainer) drawerNotesContainer.classList.add('hidden');
                    }
                }

                drawerOccurredAt.textContent = itemData.occurred_at || '-';
                drawerOutlet.textContent = itemData.outlet_name || '-';
                drawerShift.textContent = itemData.shift_number || 'Tanpa Shift';
                drawerCategory.textContent = itemData.category || 'Tanpa Kategori';

                // Display
                drawerWrapper.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    drawerBackdrop.classList.remove('opacity-0');
                    drawerBackdrop.classList.add('opacity-100');
                    drawerPanel.classList.remove('translate-x-full');
                    drawerPanel.classList.add('translate-x-0');
                });

                document.addEventListener('keydown', handleDrawerTrap);
                setTimeout(() => {
                    closeDrawerBtn?.focus();
                }, 100);
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleDrawerTrap);
                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (lastTriggerElement) {
                        lastTriggerElement.focus();
                        lastTriggerElement = null;
                    }
                }, 300);
            }

            if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', closeDrawer);
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.addEventListener('click', closeDrawer);
            if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);

            // Delegate detail button clicks
            root.addEventListener('click', (e) => {
                const btn = e.target.closest('.view-cash-detail-btn');
                if (btn) {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.cash-ledger-row, .cash-ledger-card, .expense-row, .expense-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                const data = JSON.parse(raw);
                                openDrawer(data);
                            } catch (err) {
                                console.error('Failed to parse cash/expense data', err);
                            }
                        }
                    }
                }
            });

            if (window.lucide) {
                window.lucide.createIcons();
            }
        }

        document.addEventListener('DOMContentLoaded', initCashPage);
        document.addEventListener('livewire:navigated', () => {
            const root = document.querySelector('main[data-cash-page="true"]');
            if (root) {
                root.dataset.cashInitialized = 'false';
                initCashPage();
            }
        });
    </script>
</x-layouts::app>
