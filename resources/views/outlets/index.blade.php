<x-layouts::app :title="'Outlet'">
    <main id="mainContent" data-outlets-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Bisnis
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Outlet
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Monitoring outlet berbasis data sinkronisasi, tanpa aksi tambah, ubah, atau hapus dari dashboard.
                </p>
            </div>
        </section>

        <x-outlets.summary-cards :summary="$summary" />

        <x-outlets.filter-bar :filterOptions="$filterOptions" :currentFilters="$currentFilters" />

        @if(! $hasAnyOutlets)
            <x-outlets.empty-state mode="no-data" />
        @elseif($outlets->isEmpty())
            <x-outlets.empty-state mode="no-results" />
        @else
            <section class="space-y-4">
                <x-outlets.table :outlets="$outlets" />
                <x-outlets.mobile-cards :outlets="$outlets" />

                @if($outlets->hasPages())
                    <div class="pt-2">
                        {{ $outlets->links() }}
                    </div>
                @endif
            </section>
        @endif

        <x-outlets.detail-drawer />

        <script>
            function initOutletsPage() {
                const root = document.querySelector('main[data-outlets-page="true"]');
                if (!root || root.dataset.outletsInitialized === 'true') {
                    return;
                }
                root.dataset.outletsInitialized = 'true';

                if (typeof window.__outletsDrawerCleanup === 'function') {
                    window.__outletsDrawerCleanup();
                    window.__outletsDrawerCleanup = null;
                }

                const drawerWrapper = document.getElementById('outletDrawerWrapper');
                const drawerBackdrop = document.getElementById('outletDrawerBackdrop');
                const drawerPanel = document.getElementById('outletDrawerPanel');
                const closeButtons = document.querySelectorAll('[data-close-outlet-drawer]');
                const detailButtons = document.querySelectorAll('.view-outlet-detail-btn');
                const loadingEl = document.getElementById('outletDrawerLoading');
                const contentEl = document.getElementById('outletDrawerContent');
                const errorEl = document.getElementById('outletDrawerError');

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

                const statusClasses = (status) => {
                    if (status === 'active') {
                        return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                    }
                    if (status === 'inactive') {
                        return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                    }
                    return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
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
                        drawerPanel.classList.remove('translate-x-full');
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

                    drawerBackdrop.classList.add('opacity-0');
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
                    setText('outletDrawerTitle', data.name);
                    setText('outletDrawerSubtitle', data.code);
                    setText('outletDrawerAddress', data.address || 'Alamat belum tersedia');
                    setText('outletDrawerLastTransaction', data.last_transaction_at || 'Belum ada transaksi');
                    setText('outletDrawerDevices', data.device_count || 0);
                    setText('outletDrawerTotalShifts', data.total_shifts || 0);
                    setText('outletDrawerOpenShifts', data.open_shifts || 0);
                    setText('outletDrawerSalesCount', `${data.sales_count || 0} transaksi`);
                    setText('outletDrawerSalesTotal', formatRupiah(data.sales_total));
                    setText('outletDrawerCashIn', formatRupiah(data.cash_in));
                    setText('outletDrawerCashOut', formatRupiah(data.cash_out));
                    setText('outletDrawerExpenseTotal', formatRupiah(data.expense_total));

                    const badge = document.getElementById('outletDrawerStatus');
                    if (badge) {
                        badge.textContent = data.status || '-';
                        badge.className = `inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ${statusClasses(data.status_raw)}`;
                    }

                    loadingEl?.classList.add('hidden');
                    errorEl?.classList.add('hidden');
                    contentEl?.classList.remove('hidden');
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
                            throw new Error('Failed to load outlet detail');
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

                detailButtons.forEach((button) => button.addEventListener('click', onDetailClick));
                closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));
                drawerBackdrop?.addEventListener('click', closeDrawer);
                document.addEventListener('keydown', onKeydown);

                window.__outletsDrawerCleanup = () => {
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
                    root.dataset.outletsInitialized = 'false';
                };
            }

            if (!window.__outletsListenersBound) {
                window.__outletsListenersBound = true;
                document.addEventListener('livewire:navigated', initOutletsPage);
                document.addEventListener('livewire:navigating', () => {
                    if (typeof window.__outletsDrawerCleanup === 'function') {
                        window.__outletsDrawerCleanup();
                        window.__outletsDrawerCleanup = null;
                    }
                });
            }

            initOutletsPage();
        </script>
    </main>
</x-layouts::app>
