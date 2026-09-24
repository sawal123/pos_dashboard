<x-layouts::app :title="'Shift'">
    <main id="mainContent" data-shifts-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Operasional
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Shift
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Monitoring shift kasir berbasis data sinkronisasi, tanpa aksi buka atau tutup shift dari dashboard.
                </p>
            </div>
        </section>

        <x-shifts.summary-cards :summary="$summary" />

        <x-shifts.filter-bar :filterOptions="$filterOptions" :currentFilters="$currentFilters" />

        @if(! $hasAnyShifts)
            <x-shifts.empty-state mode="no-data" />
        @elseif($shifts->isEmpty())
            <x-shifts.empty-state mode="no-results" />
        @else
            <section class="space-y-4">
                <x-shifts.table :shifts="$shifts" />
                <x-shifts.mobile-cards :shifts="$shifts" />

                @if($shifts->hasPages())
                    <div class="pt-2">
                        {{ $shifts->links() }}
                    </div>
                @endif
            </section>
        @endif

        <x-shifts.detail-drawer />

        <script>
            function initShiftsPage() {
                const root = document.querySelector('main[data-shifts-page="true"]');
                if (!root || root.dataset.shiftsInitialized === 'true') {
                    return;
                }
                root.dataset.shiftsInitialized = 'true';

                if (typeof window.__shiftsDrawerCleanup === 'function') {
                    window.__shiftsDrawerCleanup();
                    window.__shiftsDrawerCleanup = null;
                }

                const customDateContainer = document.getElementById('shiftsCustomDateContainer');
                const dateSelect = document.getElementById('shiftDateFilter');

                const drawerWrapper = document.getElementById('shiftDrawerWrapper');
                const drawerBackdrop = document.getElementById('shiftDrawerBackdrop');
                const drawerPanel = document.getElementById('shiftDrawerPanel');
                const closeButtons = document.querySelectorAll('[data-close-shift-drawer]');
                const detailButtons = document.querySelectorAll('.view-shift-detail-btn');
                const loadingEl = document.getElementById('shiftDrawerLoading');
                const contentEl = document.getElementById('shiftDrawerContent');
                const errorEl = document.getElementById('shiftDrawerError');

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
                    if (status === 'open') {
                        return 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                    }
                    if (status === 'closed') {
                        return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                    }
                    return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                };

                const updateDateControls = () => {
                    if (!customDateContainer || !dateSelect) {
                        return;
                    }
                    customDateContainer.classList.toggle('hidden', dateSelect.value !== 'custom');
                };

                const openDrawer = () => {
                    if (!drawerWrapper || !drawerBackdrop || !drawerPanel) {
                        return;
                    }
                    drawerWrapper.classList.remove('hidden');
                    requestAnimationFrame(() => {
                        drawerBackdrop.classList.remove('opacity-0');
                        drawerPanel.classList.remove('translate-x-full');
                    });
                    document.body.classList.add('overflow-hidden');
                };

                const closeDrawer = () => {
                    if (!drawerWrapper || !drawerBackdrop || !drawerPanel) {
                        return;
                    }
                    drawerBackdrop.classList.add('opacity-0');
                    drawerPanel.classList.add('translate-x-full');
                    document.body.classList.remove('overflow-hidden');
                    window.setTimeout(() => {
                        drawerWrapper.classList.add('hidden');
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
                    setText('shiftDrawerTitle', data.shift_number);
                    setText('shiftDrawerSubtitle', data.outlet_name);
                    setText('shiftDrawerOpenedAt', data.opened_at);
                    setText('shiftDrawerClosedAt', data.closed_at);
                    setText('shiftDrawerOpeningCash', formatRupiah(data.opening_cash));
                    setText('shiftDrawerClosingCash', data.closing_cash === null ? '-' : formatRupiah(data.closing_cash));
                    setText('shiftDrawerSalesCount', `${data.sales_count || 0} transaksi`);
                    setText('shiftDrawerSalesTotal', formatRupiah(data.sales_total));
                    setText('shiftDrawerCashIn', formatRupiah(data.cash_in));
                    setText('shiftDrawerCashOut', formatRupiah(data.cash_out));
                    setText('shiftDrawerExpenseTotal', formatRupiah(data.expense_total));
                    setText('shiftDrawerEstimatedCash', formatRupiah(data.estimated_cash));
                    setText('shiftDrawerNotes', data.notes || '-');

                    const badge = document.getElementById('shiftDrawerStatus');
                    if (badge) {
                        badge.textContent = data.status || '-';
                        badge.className = `inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ${statusClasses(data.status_raw)}`;
                    }

                    loadingEl?.classList.add('hidden');
                    errorEl?.classList.add('hidden');
                    contentEl?.classList.remove('hidden');
                };

                dateSelect?.addEventListener('change', updateDateControls);
                updateDateControls();

                const onDetailClick = async (event) => {
                    const button = event.currentTarget;
                    const url = button?.dataset.detailUrl;
                    if (!url) {
                        return;
                    }

                    openDrawer();
                    setLoading();

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Failed to load shift detail');
                        }

                        renderDetail(await response.json());
                    } catch (error) {
                        setError();
                    }
                };

                detailButtons.forEach((button) => button.addEventListener('click', onDetailClick));
                closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));
                drawerBackdrop?.addEventListener('click', closeDrawer);

                const onKeydown = (event) => {
                    if (event.key === 'Escape' && drawerWrapper && !drawerWrapper.classList.contains('hidden')) {
                        closeDrawer();
                    }
                };
                document.addEventListener('keydown', onKeydown);

                window.__shiftsDrawerCleanup = () => {
                    dateSelect?.removeEventListener('change', updateDateControls);
                    detailButtons.forEach((button) => button.removeEventListener('click', onDetailClick));
                    closeButtons.forEach((button) => button.removeEventListener('click', closeDrawer));
                    drawerBackdrop?.removeEventListener('click', closeDrawer);
                    document.removeEventListener('keydown', onKeydown);
                    document.body.classList.remove('overflow-hidden');
                    root.dataset.shiftsInitialized = 'false';
                };
            }

            if (!window.__shiftsListenersBound) {
                window.__shiftsListenersBound = true;
                document.addEventListener('livewire:navigated', initShiftsPage);
                document.addEventListener('livewire:navigating', () => {
                    if (typeof window.__shiftsDrawerCleanup === 'function') {
                        window.__shiftsDrawerCleanup();
                        window.__shiftsDrawerCleanup = null;
                    }
                });
            }

            initShiftsPage();
        </script>
    </main>
</x-layouts::app>
