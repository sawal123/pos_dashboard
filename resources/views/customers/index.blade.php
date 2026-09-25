<x-layouts::app :title="'Pelanggan'">
    <main id="mainContent" data-customers-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== HEADER ==================== --}}
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Operasional</p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Pelanggan</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Monitoring pelanggan dan riwayat pembelian berbasis data sinkronisasi, tanpa perubahan data dari dashboard.
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold self-start">
                <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                <span>Monitoring Pelanggan</span>
            </span>
        </section>

        {{-- ==================== 1. SUMMARY ==================== --}}
        <x-customers.summary-cards :summary="$summary" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-customers.filter-bar :filterOptions="$filterOptions" :currentFilters="$currentFilters" />

        {{-- ==================== 3. LIST / EMPTY STATE ==================== --}}
        @if(! $hasAnyCustomers)
            <x-customers.empty-state mode="no-data" />
        @elseif($customers->isEmpty())
            <x-customers.empty-state mode="no-results" />
        @else
            <section class="space-y-4">
                <x-customers.table :customers="$customers" />
                <x-customers.mobile-cards :customers="$customers" />

                {{-- ==================== 4. PAGINATION ==================== --}}
                @if($customers->hasPages())
                    <div class="pt-2">
                        {{ $customers->links() }}
                    </div>
                @endif
            </section>
        @endif

        {{-- ==================== 5. DETAIL DRAWER ==================== --}}
        <x-customers.detail-drawer />

        {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
        <script>
            function initCustomersPage() {
                const root = document.querySelector('main[data-customers-page="true"]');
                if (!root || root.dataset.customersInitialized === 'true') {
                    return;
                }
                root.dataset.customersInitialized = 'true';

                if (typeof window.__customersDrawerCleanup === 'function') {
                    window.__customersDrawerCleanup();
                    window.__customersDrawerCleanup = null;
                }

                const drawerWrapper = document.getElementById('customerDrawerWrapper');
                const drawerBackdrop = document.getElementById('customerDrawerBackdrop');
                const drawerPanel = document.getElementById('customerDrawerPanel');
                const closeButtons = document.querySelectorAll('[data-close-customer-drawer]');
                const detailButtons = document.querySelectorAll('.view-customer-detail-btn');
                const loadingEl = document.getElementById('customerDrawerLoading');
                const contentEl = document.getElementById('customerDrawerContent');
                const errorEl = document.getElementById('customerDrawerError');
                const historyContainer = document.getElementById('customerDrawerHistory');
                const historyEmpty = document.getElementById('customerDrawerHistoryEmpty');

                const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

                // Drawer lifecycle state kept per initialization so wire:navigate
                // starts from a clean slate and never reuses stale closures.
                let isOpen = false;
                let closeTimer = null;
                let activeController = null;
                let requestToken = 0;
                let lastTriggerButton = null;

                const formatRupiah = (value) => {
                    const amount = Number(value || 0);
                    return new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        maximumFractionDigits: 0,
                    }).format(amount).replace('IDR', 'Rp').trim();
                };

                const setText = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = value ?? '-';
                    }
                };

                const customerStatusBadgeClass = (status) => {
                    if (status === 'active') {
                        return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                    }
                    if (status === 'inactive') {
                        return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                    }
                    if (status === 'deleted') {
                        return 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
                    }
                    return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                };

                const saleStatusBadgeClass = (status) => {
                    if (status === 'completed') {
                        return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                    }
                    if (status === 'cancelled' || status === 'canceled') {
                        return 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
                    }
                    return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                };

                const paymentStatusBadgeClass = (status) => {
                    if (status === 'paid') {
                        return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                    }
                    if (status === 'unpaid') {
                        return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                    }
                    return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                };

                const buildBadge = (label, classes) => {
                    const span = document.createElement('span');
                    span.className = 'inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold ' + classes;
                    span.textContent = label || '-';
                    return span;
                };

                const buildHistoryRow = (trx) => {
                    const row = document.createElement('div');
                    row.className = 'p-3 flex items-start justify-between gap-3';

                    const left = document.createElement('div');
                    left.className = 'min-w-0 flex-1 space-y-1';

                    const number = document.createElement('p');
                    number.className = 'font-semibold text-slate-900 dark:text-white truncate';
                    number.textContent = trx.transaction_number || '-';

                    const meta = document.createElement('p');
                    meta.className = 'text-[11px] text-slate-500 dark:text-slate-400';
                    meta.textContent = (trx.sold_at || '-') + ' • ' + (trx.payment_method || '-');

                    const badges = document.createElement('div');
                    badges.className = 'flex items-center gap-1.5 flex-wrap pt-0.5';
                    badges.appendChild(buildBadge(trx.status, saleStatusBadgeClass(trx.status_raw)));
                    badges.appendChild(buildBadge(trx.payment_status, paymentStatusBadgeClass(trx.payment_status_raw)));

                    left.appendChild(number);
                    left.appendChild(meta);
                    left.appendChild(badges);

                    const right = document.createElement('div');
                    right.className = 'text-right shrink-0';
                    const amount = document.createElement('span');
                    amount.className = 'font-bold text-slate-900 dark:text-white tabular-nums';
                    amount.textContent = formatRupiah(trx.total_amount);
                    right.appendChild(amount);

                    row.appendChild(left);
                    row.appendChild(right);

                    return row;
                };

                // Abort the in-flight request and invalidate its response token so
                // a slower response can never paint over a newer selection.
                const abortActiveRequest = () => {
                    requestToken += 1;
                    if (activeController !== null) {
                        activeController.abort();
                        activeController = null;
                    }
                };

                const focusDrawer = () => {
                    if (!drawerPanel) {
                        return;
                    }
                    const focusables = Array.from(drawerPanel.querySelectorAll(focusableSelector));
                    if (focusables.length > 0) {
                        focusables[0].focus({ preventScroll: true });
                    } else {
                        drawerPanel.focus({ preventScroll: true });
                    }
                };

                const restoreTriggerFocus = () => {
                    const target = lastTriggerButton;
                    lastTriggerButton = null;
                    if (target && typeof target.focus === 'function' && document.contains(target)) {
                        target.focus({ preventScroll: true });
                    }
                };

                const openDrawer = (triggerButton) => {
                    if (!drawerWrapper || !drawerBackdrop || !drawerPanel) {
                        return;
                    }

                    // Cancel a pending close so a quick reopen cannot be undone by
                    // the previous close animation timer.
                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                        closeTimer = null;
                    }

                    isOpen = true;
                    if (triggerButton) {
                        lastTriggerButton = triggerButton;
                    }

                    drawerWrapper.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');

                    requestAnimationFrame(() => {
                        drawerBackdrop.classList.remove('opacity-0');
                        drawerBackdrop.classList.add('opacity-100');
                        drawerPanel.classList.remove('translate-x-full');
                        drawerPanel.classList.add('translate-x-0');
                        focusDrawer();
                    });
                };

                const closeDrawer = () => {
                    if (!drawerWrapper || !drawerBackdrop || !drawerPanel) {
                        return;
                    }
                    if (!isOpen) {
                        return;
                    }

                    isOpen = false;

                    // Closing cancels any pending detail request so its response
                    // cannot mutate the DOM after the drawer is gone.
                    abortActiveRequest();

                    drawerBackdrop.classList.remove('opacity-100');
                    drawerBackdrop.classList.add('opacity-0');
                    drawerPanel.classList.remove('translate-x-0');
                    drawerPanel.classList.add('translate-x-full');
                    document.body.classList.remove('overflow-hidden');

                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                    }
                    closeTimer = window.setTimeout(() => {
                        closeTimer = null;
                        drawerWrapper.classList.add('hidden');
                        restoreTriggerFocus();
                    }, 300);
                };

                const onKeydown = (event) => {
                    if (!isOpen) {
                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeDrawer();
                        return;
                    }

                    if (event.key !== 'Tab' || !drawerPanel) {
                        return;
                    }

                    const focusables = Array.from(drawerPanel.querySelectorAll(focusableSelector));
                    if (focusables.length === 0) {
                        event.preventDefault();
                        drawerPanel.focus({ preventScroll: true });
                        return;
                    }

                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    const activeIndex = focusables.indexOf(document.activeElement);

                    if (event.shiftKey) {
                        if (activeIndex <= 0) {
                            event.preventDefault();
                            last.focus({ preventScroll: true });
                        }
                        return;
                    }

                    if (activeIndex === -1 || activeIndex === focusables.length - 1) {
                        event.preventDefault();
                        first.focus({ preventScroll: true });
                    }
                };

                const setLoading = () => {
                    loadingEl?.classList.remove('hidden');
                    contentEl?.classList.add('hidden');
                    errorEl?.classList.add('hidden');
                };

                const setError = () => {
                    loadingEl?.classList.add('hidden');
                    contentEl?.classList.add('hidden');
                    errorEl?.classList.remove('hidden');
                };

                const renderDetail = (data) => {
                    setText('customerDrawerTitle', data.name);
                    setText('customerDrawerSubtitle', data.email || data.phone || 'Detail Pelanggan');

                    const statusBadge = document.getElementById('customerDrawerStatus');
                    if (statusBadge) {
                        statusBadge.textContent = data.status || '-';
                        statusBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ' + customerStatusBadgeClass(data.status_raw);
                    }

                    setText('customerDrawerPhone', data.phone || '-');
                    setText('customerDrawerEmail', data.email || '-');
                    setText('customerDrawerAddress', data.address || '-');
                    setText('customerDrawerNotes', data.notes || '-');

                    const metrics = data.metrics || {};
                    setText('customerDrawerTransactionsCount', String(metrics.transactions_count || 0));
                    setText('customerDrawerPurchaseTotal', formatRupiah(metrics.purchase_total));
                    setText('customerDrawerFirstPurchase', metrics.first_purchase_at || '-');
                    setText('customerDrawerLastPurchase', metrics.last_purchase_at || '-');

                    if (historyContainer) {
                        historyContainer.textContent = '';
                        const transactions = Array.isArray(data.recent_transactions) ? data.recent_transactions : [];
                        if (transactions.length === 0) {
                            historyEmpty?.classList.remove('hidden');
                        } else {
                            historyEmpty?.classList.add('hidden');
                            transactions.forEach((trx) => historyContainer.appendChild(buildHistoryRow(trx)));
                        }
                    }

                    loadingEl?.classList.add('hidden');
                    errorEl?.classList.add('hidden');
                    contentEl?.classList.remove('hidden');

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                };

                const onDetailClick = async (event) => {
                    const button = event.currentTarget;
                    const url = button?.dataset.detailUrl;
                    if (!url) {
                        return;
                    }

                    abortActiveRequest();
                    const token = requestToken;

                    const controller = new AbortController();
                    activeController = controller;

                    openDrawer(button);
                    setLoading();

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: controller.signal,
                        });

                        if (!response.ok) {
                            throw new Error('Failed to load customer detail');
                        }

                        const data = await response.json();

                        // Ignore a response that lost the race (superseded or aborted).
                        if (token !== requestToken || controller.signal.aborted) {
                            return;
                        }

                        renderDetail(data);
                    } catch (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                        if (token !== requestToken) {
                            return;
                        }
                        setError();
                    } finally {
                        if (activeController === controller) {
                            activeController = null;
                        }
                    }
                };

                detailButtons.forEach((button) => button.addEventListener('click', onDetailClick));
                closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));
                drawerBackdrop?.addEventListener('click', closeDrawer);
                document.addEventListener('keydown', onKeydown);

                window.__customersDrawerCleanup = () => {
                    abortActiveRequest();

                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                        closeTimer = null;
                    }

                    isOpen = false;
                    lastTriggerButton = null;

                    detailButtons.forEach((button) => button.removeEventListener('click', onDetailClick));
                    closeButtons.forEach((button) => button.removeEventListener('click', closeDrawer));
                    drawerBackdrop?.removeEventListener('click', closeDrawer);
                    document.removeEventListener('keydown', onKeydown);
                    document.body.classList.remove('overflow-hidden');
                    root.dataset.customersInitialized = 'false';
                };
            }

            if (!window.__customersListenersBound) {
                window.__customersListenersBound = true;
                document.addEventListener('livewire:navigated', initCustomersPage);
                document.addEventListener('livewire:navigating', () => {
                    if (typeof window.__customersDrawerCleanup === 'function') {
                        window.__customersDrawerCleanup();
                        window.__customersDrawerCleanup = null;
                    }
                });
            }

            initCustomersPage();
        </script>
    </main>
</x-layouts::app>
