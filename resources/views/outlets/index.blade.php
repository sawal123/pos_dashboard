<x-layouts::app :title="'Outlet'">
    <main id="mainContent" data-outlets-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Bisnis
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Outlet & Cabang
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Kelola cabang toko, pantau transaksi dan kas per lokasi, serta aktifkan langganan Cloud untuk setiap cabang tambahan.
                </p>
            </div>

            @if(! empty($canManageOutlets))
                <button
                    type="button"
                    id="openCreateOutletModalBtn"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow-xs transition-colors"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Tambah Cabang Toko
                </button>
            @endif
        </section>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-rose-800 dark:text-rose-200 space-y-1">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <x-outlets.summary-cards :summary="$summary" />

        <x-outlets.filter-bar :filterOptions="$filterOptions" :currentFilters="$currentFilters" />

        @if(! $hasAnyOutlets)
            <x-outlets.empty-state mode="no-data" />
        @elseif($outlets->isEmpty())
            <x-outlets.empty-state mode="no-results" />
        @else
            <section class="space-y-4">
                <x-outlets.table :outlets="$outlets" :canManageOutlets="$canManageOutlets ?? false" :plans="$plans ?? []" :checkoutAvailable="$checkoutAvailable ?? false" />
                <x-outlets.mobile-cards :outlets="$outlets" :canManageOutlets="$canManageOutlets ?? false" />

                @if($outlets->hasPages())
                    <div class="pt-2">
                        {{ $outlets->links() }}
                    </div>
                @endif
            </section>
        @endif

        @if(! empty($canManageOutlets))
            {{-- Create / Edit Outlet Modal --}}
            <div id="outletFormModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                        <div>
                            <h2 id="outletFormModalTitle" class="text-lg font-extrabold text-slate-900 dark:text-white">Tambah Cabang Toko</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Menu/produk memakai data master toko, sedangkan transaksi & keuangan terpisah per cabang.</p>
                        </div>
                        <button type="button" data-close-outlet-modal class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <form id="outletForm" method="POST" action="{{ route('outlets.store') }}" class="p-6 space-y-4">
                        @csrf
                        <input type="hidden" name="_method" id="outletFormMethod" value="POST">
                        <div>
                            <label for="outletFormName" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Nama Cabang *</label>
                            <input id="outletFormName" name="name" type="text" required maxlength="120" placeholder="Contoh: Cabang Sudirman" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="outletFormCode" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Kode Cabang (opsional)</label>
                                <input id="outletFormCode" name="code" type="text" maxlength="40" placeholder="Otomatis jika dikosongkan" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-sm font-mono text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label for="outletFormPhone" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">No. Telepon</label>
                                <input id="outletFormPhone" name="phone" type="text" maxlength="40" placeholder="08xxxxxxxxxx" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label for="outletFormAddress" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Alamat Cabang</label>
                            <textarea id="outletFormAddress" name="address" rows="2" maxlength="500" placeholder="Alamat lengkap cabang..." class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white"></textarea>
                        </div>
                        <div id="outletCreateNotice" class="rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-3 text-xs text-amber-800 dark:text-amber-200">
                            Setiap cabang tambahan memerlukan langganan Paket Cloud tersendiri. Setelah cabang dibuat, Anda dapat langsung melanjutkan ke pembayaran.
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" data-close-outlet-modal class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300">Batal</button>
                            <button type="submit" id="outletFormSubmitBtn" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white">Simpan Cabang</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Branch Subscription Checkout Modal --}}
            <div id="outletCheckoutModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Langganan Cabang</h2>
                            <p id="outletCheckoutSubtitle" class="text-xs text-slate-500 dark:text-slate-400">-</p>
                        </div>
                        <button type="button" data-close-outlet-checkout class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('subscriptions.checkout') }}" class="p-6 space-y-4">
                        @csrf
                        <input type="hidden" name="outlet_id" id="outletCheckoutOutletId" value="">
                        <input type="hidden" name="allow_renewal" id="outletCheckoutAllowRenewal" value="1">
                        @php($cloudPlan = collect($plans ?? [])->firstWhere('code', 'cloud') ?? ($plans[0] ?? null))
                        @php($prices = $cloudPlan['billing_periods'] ?? $cloudPlan['prices'] ?? [])
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Pilih Periode Langganan</label>
                            @forelse($prices as $idx => $price)
                                @php($period = (string) ($price['period'] ?? $price['billing_period'] ?? 'monthly'))
                                <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-indigo-500">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" name="billing_period" value="{{ $period }}" @checked($idx === 0) required>
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ $period === 'yearly' ? 'Tahunan (12 Bulan)' : 'Bulanan (1 Bulan)' }}
                                        </span>
                                    </span>
                                    <span class="text-sm font-extrabold text-indigo-600 dark:text-indigo-400">
                                        Rp {{ number_format((int) ($price['price_minor'] ?? 0), 0, ',', '.') }}
                                    </span>
                                </label>
                            @empty
                                <p class="text-xs text-amber-600">Harga paket belum dikonfigurasi oleh operator platform.</p>
                            @endforelse
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" data-close-outlet-checkout class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300">Batal</button>
                            <button type="submit" @disabled(empty($checkoutAvailable) || empty($prices)) class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-xs font-semibold text-white">Lanjut ke Pembayaran</button>
                        </div>
                    </form>
                </div>
            </div>
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

                // Create / Edit Outlet Modal
                const formModal = document.getElementById('outletFormModal');
                const formEl = document.getElementById('outletForm');
                const formTitle = document.getElementById('outletFormModalTitle');
                const formMethod = document.getElementById('outletFormMethod');
                const formName = document.getElementById('outletFormName');
                const formCode = document.getElementById('outletFormCode');
                const formPhone = document.getElementById('outletFormPhone');
                const formAddress = document.getElementById('outletFormAddress');
                const createNotice = document.getElementById('outletCreateNotice');
                const openCreateBtn = document.getElementById('openCreateOutletModalBtn');
                const storeUrl = @json(route('outlets.store'));

                const openCreateModal = () => {
                    if (!formModal || !formEl) return;
                    formEl.action = storeUrl;
                    if (formMethod) formMethod.value = 'POST';
                    if (formTitle) formTitle.textContent = 'Tambah Cabang Toko';
                    if (formName) formName.value = '';
                    if (formCode) formCode.value = '';
                    if (formPhone) formPhone.value = '';
                    if (formAddress) formAddress.value = '';
                    createNotice?.classList.remove('hidden');
                    formModal.classList.remove('hidden');
                };

                const openEditModal = (btn) => {
                    if (!formModal || !formEl) return;
                    formEl.action = btn.dataset.updateUrl || storeUrl;
                    if (formMethod) formMethod.value = 'PATCH';
                    if (formTitle) formTitle.textContent = 'Edit Cabang';
                    if (formName) formName.value = btn.dataset.outletName || '';
                    if (formCode) formCode.value = btn.dataset.outletCode || '';
                    if (formPhone) formPhone.value = btn.dataset.outletPhone || '';
                    if (formAddress) formAddress.value = btn.dataset.outletAddress || '';
                    createNotice?.classList.add('hidden');
                    formModal.classList.remove('hidden');
                };

                openCreateBtn?.addEventListener('click', openCreateModal);
                document.querySelectorAll('.edit-outlet-btn').forEach((btn) => {
                    btn.addEventListener('click', () => openEditModal(btn));
                });
                document.querySelectorAll('[data-close-outlet-modal]').forEach((btn) => {
                    btn.addEventListener('click', () => formModal?.classList.add('hidden'));
                });

                // Branch Checkout Modal
                const checkoutModal = document.getElementById('outletCheckoutModal');
                const checkoutOutletId = document.getElementById('outletCheckoutOutletId');
                const checkoutSubtitle = document.getElementById('outletCheckoutSubtitle');
                document.querySelectorAll('.open-outlet-checkout-btn').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!checkoutModal) return;
                        if (checkoutOutletId) checkoutOutletId.value = btn.dataset.outletId || '';
                        if (checkoutSubtitle) checkoutSubtitle.textContent = btn.dataset.outletName || '';
                        checkoutModal.classList.remove('hidden');
                    });
                });
                document.querySelectorAll('[data-close-outlet-checkout]').forEach((btn) => {
                    btn.addEventListener('click', () => checkoutModal?.classList.add('hidden'));
                });

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
