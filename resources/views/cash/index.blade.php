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
                    @disabled(!($canManageCash ?? false))
                    class="py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="{{ ($canManageCash ?? false) ? 'Catat kas' : 'Hanya pemilik bisnis yang dapat mencatat kas' }}"
                >
                    <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600"></i>
                    <span>Catat Kas</span>
                </button>
                <button
                    type="button"
                    id="addExpenseBtn"
                    @disabled(!($canManageCash ?? false))
                    class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-600/20 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="{{ ($canManageCash ?? false) ? 'Tambah pengeluaran' : 'Hanya pemilik bisnis yang dapat mencatat pengeluaran' }}"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Pengeluaran</span>
                </button>
            </div>
        </div>

        {{-- ==================== FLASH FEEDBACK ==================== --}}
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4 space-y-1" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Tindakan tidak dapat diproses.</p>
                </div>
                <ul class="pl-8 list-disc text-xs text-rose-700 dark:text-rose-300 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
                        tabindex="0"
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
                        tabindex="0"
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
                    <x-cash.ledger-table :ledgers="$ledgers" :canManageCash="$canManageCash ?? false" />
                    <x-cash.mobile-cards :activeTab="'ledgers'" :ledgers="$ledgers" :canManageCash="$canManageCash ?? false" />

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
                    <x-cash.expense-table :expenses="$expenses" :canManageCash="$canManageCash ?? false" />
                    <x-cash.mobile-cards :activeTab="'expenses'" :expenses="$expenses" :canManageCash="$canManageCash ?? false" />

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

        <x-cash.management-modals
            :canManageCash="$canManageCash ?? false"
            :outlets="$filterOptions['outlets'] ?? []"
            :shifts="$filterOptions['shifts'] ?? []"
        />

        {{-- DASH-16 — owner-only confirmation dialog for cash reversal / expense void. --}}
        <x-cash.correction-modal :canManageCash="$canManageCash ?? false" />

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initCashPage() {
            const root = document.querySelector('main[data-cash-page="true"]');
            if (!root || root.dataset.cashInitialized === 'true') {
                return;
            }
            root.dataset.cashInitialized = 'true';

            // Ensure any stale drawer state or overflow lock is cleaned up
            if (typeof window.__cashDrawerCleanup === 'function') {
                window.__cashDrawerCleanup();
                window.__cashDrawerCleanup = null;
            }
            document.body.style.overflow = '';

            // Controls
            const dateSelect = document.getElementById('filterCashDate');
            const customDateContainer = document.getElementById('cashCustomDateContainer');
            const recordCashBtn = document.getElementById('recordCashBtn');
            const addExpenseBtn = document.getElementById('addExpenseBtn');
            const cashModal = document.getElementById('cashLedgerModal');
            const expenseModal = document.getElementById('expenseModal');
            const correctionModal = document.getElementById('cashCorrectionModal');
            const correctionForm = document.getElementById('cashCorrectionForm');

            // Element that opened the correction dialog, so focus returns to it.
            let lastCorrectionTrigger = null;

            function openModal(modal) {
                if (!modal) return;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                const firstField = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
                setTimeout(() => firstField?.focus(), 50);
            }

            function closeModal(modal) {
                if (!modal) return;
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }

            // DASH-16 — correction dialog: focus management + focus trap.
            const correctionFocusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            function correctionFocusables() {
                return correctionModal
                    ? Array.from(correctionModal.querySelectorAll(correctionFocusableSelector))
                    : [];
            }

            function openCorrectionModal(trigger) {
                if (!correctionModal) return;

                // Remember the opener so focus can return to it on close.
                lastCorrectionTrigger = trigger || document.activeElement;

                correctionModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';

                // Focus the least destructive control first.
                const cancel = correctionModal.querySelector('[data-correction-cancel]');
                setTimeout(() => (cancel || correctionFocusables()[0])?.focus({ preventScroll: true }), 50);
            }

            function closeCorrectionModal() {
                if (!correctionModal || correctionModal.classList.contains('hidden')) return;

                closeModal(correctionModal);

                const trigger = lastCorrectionTrigger;
                lastCorrectionTrigger = null;
                if (trigger && typeof trigger.focus === 'function' && document.contains(trigger)) {
                    trigger.focus({ preventScroll: true });
                }
            }

            function trapCorrectionFocus(event) {
                const focusables = correctionFocusables();
                if (focusables.length === 0) return;

                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                const active = document.activeElement;

                if (event.shiftKey && (active === first || !correctionModal.contains(active))) {
                    event.preventDefault();
                    last.focus({ preventScroll: true });
                } else if (!event.shiftKey && (active === last || !correctionModal.contains(active))) {
                    event.preventDefault();
                    first.focus({ preventScroll: true });
                }
            }

            function syncShiftOptions(modal) {
                if (!modal) return;
                const outletSelect = modal.querySelector('[data-cash-outlet-select]');
                const shiftSelect = modal.querySelector('[data-cash-shift-select]');
                if (!outletSelect || !shiftSelect) return;

                const selectedOutlet = outletSelect.value;
                Array.from(shiftSelect.options).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }
                    const matches = option.dataset.outletId === selectedOutlet;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (!matches && option.selected) {
                        shiftSelect.value = '';
                    }
                });
            }

            document.querySelectorAll('[data-cash-modal-close]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const host = btn.closest('[data-cash-modal]');
                    if (host && host.id === 'cashCorrectionModal') {
                        closeCorrectionModal();
                    } else {
                        closeModal(host);
                    }
                });
            });

            document.querySelectorAll('[data-cash-modal]').forEach((modal) => {
                modal.addEventListener('click', (event) => {
                    if (event.target !== modal) {
                        return;
                    }
                    if (modal.id === 'cashCorrectionModal') {
                        closeCorrectionModal();
                    } else {
                        closeModal(modal);
                    }
                });
                modal.querySelectorAll('[data-cash-outlet-select]').forEach((select) => {
                    select.addEventListener('change', () => syncShiftOptions(modal));
                    syncShiftOptions(modal);
                });
            });

            // Clicking the correction dialog backdrop (anywhere outside the panel)
            // closes it and returns focus to the opener.
            if (correctionModal) {
                const correctionPanel = correctionModal.querySelector('section');
                correctionModal.addEventListener('click', (event) => {
                    if (!correctionPanel || !correctionPanel.contains(event.target)) {
                        closeCorrectionModal();
                    }
                });
            }

            // Escape + Tab focus trap. The document listener is re-bound with a
            // remove-then-add guard so Livewire navigation can never stack
            // duplicate document handlers.
            if (window.__cashKeydownHandler) {
                document.removeEventListener('keydown', window.__cashKeydownHandler);
            }
            window.__cashKeydownHandler = (event) => {
                if (correctionModal && !correctionModal.classList.contains('hidden')) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeCorrectionModal();
                    } else if (event.key === 'Tab') {
                        trapCorrectionFocus(event);
                    }

                    return;
                }

                if (event.key === 'Escape') {
                    closeModal(cashModal);
                    closeModal(expenseModal);
                }
            };
            document.addEventListener('keydown', window.__cashKeydownHandler);

            if (recordCashBtn && !recordCashBtn.disabled) {
                recordCashBtn.onclick = () => {
                    openModal(cashModal);
                };
            }

            if (addExpenseBtn && !addExpenseBtn.disabled) {
                addExpenseBtn.onclick = () => {
                    openModal(expenseModal);
                };
            }

            // DASH-16 — cash reversal / expense void confirmation dialog.
            // The dialog only explains the consequence; the server re-validates
            // every rule (reversibility, tenant, permission) on submit.
            const correctionTitle = document.getElementById('cashCorrectionTitle');
            const correctionType = document.getElementById('cashCorrectionType');
            const correctionAmount = document.getElementById('cashCorrectionAmount');
            const correctionRef = document.getElementById('cashCorrectionRef');
            const correctionConsequence = document.getElementById('cashCorrectionConsequence');
            const correctionSubmit = document.getElementById('cashCorrectionSubmit');

            root.addEventListener('click', (event) => {
                const trigger = event.target.closest('.cash-correction-btn');
                if (!trigger || !correctionForm || !correctionModal) {
                    return;
                }

                const kind = trigger.dataset.correctionKind === 'void' ? 'void' : 'reversal';
                correctionForm.setAttribute('action', trigger.dataset.correctionAction || '');

                if (correctionTitle) {
                    correctionTitle.textContent = kind === 'void' ? 'Batalkan Pengeluaran' : 'Koreksi Kas';
                }
                if (correctionType) correctionType.textContent = trigger.dataset.correctionType || '-';
                if (correctionAmount) correctionAmount.textContent = formatRupiah(trigger.dataset.correctionAmount);
                if (correctionRef) correctionRef.textContent = trigger.dataset.correctionReference || '-';
                if (correctionConsequence) {
                    correctionConsequence.textContent = kind === 'void'
                        ? 'Pengeluaran akan ditandai void. Jika dibayar dari kas, kas yang terhubung dikembalikan tepat satu kali.'
                        : 'Sistem menambahkan baris kas berlawanan arah. Baris asli tetap tersimpan dan hanya dapat dikoreksi satu kali.';
                }
                if (correctionSubmit) {
                    correctionSubmit.disabled = false;
                    const label = correctionSubmit.querySelector('[data-correction-label]');
                    if (label) label.textContent = kind === 'void' ? 'Batalkan Pengeluaran' : 'Koreksi Kas';
                }

                openCorrectionModal(trigger);
            });

            if (correctionForm) {
                correctionForm.addEventListener('submit', () => {
                    if (correctionSubmit) {
                        correctionSubmit.disabled = true;
                        const label = correctionSubmit.querySelector('[data-correction-label]');
                        if (label) label.textContent = 'Memproses…';
                    }
                });
            }

            if (cashModal?.dataset.openOnLoad === 'true') {
                openModal(cashModal);
            }
            if (expenseModal?.dataset.openOnLoad === 'true') {
                openModal(expenseModal);
            }

            if (dateSelect && customDateContainer) {
                dateSelect.onchange = () => {
                    if (dateSelect.value === 'custom') {
                        customDateContainer.classList.remove('hidden');
                    } else {
                        customDateContainer.classList.add('hidden');
                    }
                };
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

            function cleanupDrawer() {
                document.removeEventListener('keydown', handleDrawerTrap);
                document.body.style.overflow = '';
                if (drawerWrapper) {
                    drawerWrapper.classList.add('hidden');
                }
                if (drawerBackdrop) {
                    drawerBackdrop.classList.remove('opacity-100');
                    drawerBackdrop.classList.add('opacity-0');
                }
                if (drawerPanel) {
                    drawerPanel.classList.remove('translate-x-0');
                    drawerPanel.classList.add('translate-x-full');
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

                    const statusRaw = String(itemData.status_raw || '');
                    const isRecorded = statusRaw === 'recorded';
                    const statusLabel = isRecorded
                        ? 'Tercatat'
                        : (itemData.status || (statusRaw ? statusRaw.replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'Tanpa Status'));

                    drawerBadgeContainer.textContent = '';
                    const badge = document.createElement('span');
                    const dot = document.createElement('span');

                    if (isRecorded) {
                        badge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                        dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    } else {
                        badge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                        dot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-slate-500';
                    }

                    badge.appendChild(dot);
                    const label = document.createElement('span');
                    label.textContent = statusLabel;
                    badge.appendChild(label);
                    drawerBadgeContainer.appendChild(badge);

                    if (drawerStatusRow) drawerStatusRow.classList.remove('hidden');
                    if (drawerStatus) drawerStatus.textContent = statusLabel;
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
                window.__cashDrawerCleanup = cleanupDrawer;
                setTimeout(() => {
                    closeDrawerBtn?.focus();
                }, 100);
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleDrawerTrap);
                window.__cashDrawerCleanup = null;
                document.body.style.overflow = '';

                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    if (lastTriggerElement) {
                        lastTriggerElement.focus();
                        lastTriggerElement = null;
                    }
                }, 300);
            }

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;

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

        initCashPage();

        if (!window.__cashListenersBound) {
            window.__cashListenersBound = true;
            document.addEventListener('DOMContentLoaded', initCashPage);
            document.addEventListener('livewire:navigated', initCashPage);
            document.addEventListener('livewire:navigating', () => {
                if (typeof window.__cashDrawerCleanup === 'function') {
                    window.__cashDrawerCleanup();
                    window.__cashDrawerCleanup = null;
                }
                document.body.style.overflow = '';
            });
        }
    </script>
</x-layouts::app>
