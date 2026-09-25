<x-layouts::app :title="'Pesanan Laundry'">
    <main id="mainContent" data-laundry-orders-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Operasional
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Pesanan Laundry
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Monitoring pesanan laundry berbasis data sinkronisasi, tanpa aksi ubah status atau pembayaran dari dashboard.
                </p>
            </div>
        </section>

        <x-laundry-orders.summary-cards :summary="$summary" />

        <x-laundry-orders.filter-bar :filterOptions="$filterOptions" :currentFilters="$currentFilters" />

        @if(! $hasAnyOrders)
            <x-laundry-orders.empty-state mode="no-data" />
        @elseif($orders->isEmpty())
            <x-laundry-orders.empty-state mode="no-results" />
        @else
            <section class="space-y-4">
                <x-laundry-orders.table :orders="$orders" />
                <x-laundry-orders.mobile-cards :orders="$orders" />

                @if($orders->hasPages())
                    <div class="pt-2">
                        {{ $orders->links() }}
                    </div>
                @endif
            </section>
        @endif

        <x-laundry-orders.detail-drawer />

        <script>
            function initLaundryOrdersPage() {
                const root = document.querySelector('main[data-laundry-orders-page="true"]');
                if (!root || root.dataset.laundryOrdersInitialized === 'true') {
                    return;
                }
                root.dataset.laundryOrdersInitialized = 'true';

                if (typeof window.__laundryDrawerCleanup === 'function') {
                    window.__laundryDrawerCleanup();
                    window.__laundryDrawerCleanup = null;
                }

                const customDateContainer = document.getElementById('laundryCustomDateContainer');
                const dateSelect = document.getElementById('laundryDateFilter');

                const drawerWrapper = document.getElementById('laundryDrawerWrapper');
                const drawerBackdrop = document.getElementById('laundryDrawerBackdrop');
                const drawerPanel = document.getElementById('laundryDrawerPanel');
                const closeButtons = document.querySelectorAll('[data-close-laundry-drawer]');
                const detailButtons = document.querySelectorAll('.view-laundry-order-detail-btn');
                const loadingEl = document.getElementById('laundryDrawerLoading');
                const contentEl = document.getElementById('laundryDrawerContent');
                const errorEl = document.getElementById('laundryDrawerError');
                const itemsContainer = document.getElementById('laundryDrawerItems');
                const itemsEmpty = document.getElementById('laundryDrawerItemsEmpty');

                const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

                // Drawer lifecycle state is scoped per initialization so wire:navigate
                // always starts from a clean slate and never reuses stale closures.
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

                const orderStatusClasses = (category) => {
                    if (category === 'incoming') {
                        return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                    }
                    if (category === 'processing') {
                        return 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800';
                    }
                    if (category === 'ready') {
                        return 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800';
                    }
                    if (category === 'done') {
                        return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                    }
                    return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                };

                const paymentStatusClasses = (raw) => {
                    if (raw === 'paid') {
                        return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                    }
                    if (raw === 'unpaid') {
                        return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                    }
                    return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                };

                // Abort the in-flight request and invalidate its response token so a
                // slower response can never paint over a newer selection.
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

                const buildItemRow = (item) => {
                    const row = document.createElement('div');
                    row.className = 'p-3 flex items-start justify-between gap-3';

                    const left = document.createElement('div');
                    left.className = 'min-w-0 flex-1 space-y-1';

                    const name = document.createElement('p');
                    name.className = 'font-semibold text-slate-900 dark:text-white break-words';
                    name.textContent = item.product_name || '-';

                    const meta = document.createElement('p');
                    meta.className = 'text-[11px] text-slate-500 dark:text-slate-400';
                    const qty = item.quantity_display || '0';
                    const unit = item.unit || 'pcs';
                    meta.textContent = qty + ' ' + unit + ' \u00d7 ' + formatRupiah(item.unit_price);

                    left.appendChild(name);
                    left.appendChild(meta);

                    const right = document.createElement('div');
                    right.className = 'text-right shrink-0';
                    const total = document.createElement('span');
                    total.className = 'font-bold text-slate-900 dark:text-white tabular-nums';
                    total.textContent = formatRupiah(item.line_total);
                    right.appendChild(total);

                    row.appendChild(left);
                    row.appendChild(right);

                    return row;
                };

                const renderItems = (items) => {
                    if (!itemsContainer) {
                        return;
                    }

                    itemsContainer.textContent = '';
                    const list = Array.isArray(items) ? items : [];

                    if (list.length === 0) {
                        itemsEmpty?.classList.remove('hidden');
                        return;
                    }

                    itemsEmpty?.classList.add('hidden');
                    list.forEach((item) => itemsContainer.appendChild(buildItemRow(item)));
                };

                const renderDetail = (data) => {
                    setText('laundryDrawerTitle', data.transaction_number);
                    setText('laundryDrawerSubtitle', 'Detail Pesanan');
                    setText('laundryDrawerCustomer', data.customer_name);
                    setText('laundryDrawerPhone', data.customer_phone || '-');
                    setText('laundryDrawerOutlet', data.outlet_name);
                    setText('laundryDrawerSoldAt', data.sold_at || '-');
                    setText('laundryDrawerEstimated', data.estimated_completed_at || '-');
                    setText('laundryDrawerPaymentMethod', data.payment_method || '-');
                    setText('laundryDrawerTotal', formatRupiah(data.total_amount));
                    setText('laundryDrawerNote', data.note || '-');

                    const orderBadge = document.getElementById('laundryDrawerStatusBadge');
                    if (orderBadge) {
                        orderBadge.textContent = data.order_status || '-';
                        orderBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ' + orderStatusClasses(data.order_status_category);
                    }

                    const paymentBadge = document.getElementById('laundryDrawerPaymentBadge');
                    if (paymentBadge) {
                        paymentBadge.textContent = data.payment_status || '-';
                        paymentBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ' + paymentStatusClasses(data.payment_status_raw);
                    }

                    const overdueBadge = document.getElementById('laundryDrawerOverdueBadge');
                    if (overdueBadge) {
                        overdueBadge.classList.toggle('hidden', ! data.is_overdue);
                    }

                    renderItems(data.items);

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
                            throw new Error('Failed to load laundry order detail');
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

                const updateDateControls = () => {
                    if (!customDateContainer || !dateSelect) {
                        return;
                    }
                    customDateContainer.classList.toggle('hidden', dateSelect.value !== 'custom');
                };

                dateSelect?.addEventListener('change', updateDateControls);
                updateDateControls();

                detailButtons.forEach((button) => button.addEventListener('click', onDetailClick));
                closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));
                drawerBackdrop?.addEventListener('click', closeDrawer);
                document.addEventListener('keydown', onKeydown);

                window.__laundryDrawerCleanup = () => {
                    abortActiveRequest();

                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                        closeTimer = null;
                    }

                    isOpen = false;
                    lastTriggerButton = null;

                    dateSelect?.removeEventListener('change', updateDateControls);
                    detailButtons.forEach((button) => button.removeEventListener('click', onDetailClick));
                    closeButtons.forEach((button) => button.removeEventListener('click', closeDrawer));
                    drawerBackdrop?.removeEventListener('click', closeDrawer);
                    document.removeEventListener('keydown', onKeydown);
                    document.body.classList.remove('overflow-hidden');
                    root.dataset.laundryOrdersInitialized = 'false';
                };
            }

            if (!window.__laundryListenersBound) {
                window.__laundryListenersBound = true;
                document.addEventListener('livewire:navigated', initLaundryOrdersPage);
                document.addEventListener('livewire:navigating', () => {
                    if (typeof window.__laundryDrawerCleanup === 'function') {
                        window.__laundryDrawerCleanup();
                        window.__laundryDrawerCleanup = null;
                    }
                });
            }

            initLaundryOrdersPage();
        </script>
    </main>
</x-layouts::app>
