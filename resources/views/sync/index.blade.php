<x-layouts::app :title="'Sinkronisasi'">
    <main id="mainContent" data-sync-page="true" data-sync-detail-url-template="{{ route('sync.show', ['syncRequest' => '__ID__']) }}" class="space-y-6 pb-12 max-w-7xl mx-auto p-4 md:p-6 lg:p-8">
        {{-- Header & Breadcrumb --}}
        <div class="space-y-1">
            <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Sinkronisasi</span>
            </nav>
            <div class="pt-1">
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Sinkronisasi
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pantau sequence server dan push request yang telah diproses.
                </p>
            </div>
        </div>

        {{-- Info Callout --}}
        <x-sync.info-callout />

        {{-- Summary Cards --}}
        <x-sync.summary-cards :summary="$summary" />

        {{-- Filter Bar & Data List / Empty States --}}
        @if(!$hasAnyRequests)
            {{-- Initial Empty State (no requests in database for this business) --}}
            <x-sync.empty-state type="no-data" />
        @else
            {{-- Filter Bar --}}
            <x-sync.filter-bar :devices="$devices" :outlets="$outlets" :currentFilters="$currentFilters" />

            @if($requests->isEmpty())
                {{-- Filter Zero Results --}}
                <x-sync.empty-state type="no-results" />
            @else
                <div id="syncDataContainer" class="space-y-4">
                    {{-- Table (Desktop) --}}
                    <x-sync.request-table :requests="$requests" />

                    {{-- Cards (Mobile) --}}
                    <x-sync.mobile-cards :requests="$requests" />
                </div>

                {{-- Pagination --}}
                @if($requests->hasPages())
                    <div class="pt-2">
                        {{ $requests->links() }}
                    </div>
                @endif
            @endif
        @endif

        {{-- Detail Drawer --}}
        <x-sync.detail-drawer />
    </main>

    <script>
        function initSyncPage() {
            const root = document.querySelector('main[data-sync-page="true"]');
            if (!root || root.dataset.syncInitialized === 'true') {
                return;
            }
            root.dataset.syncInitialized = 'true';

            // Filter date preset toggle for custom date fields
            const dateFilter = document.getElementById('sync-date-filter');
            const customDateContainer = document.getElementById('sync-custom-date-container');

            if (dateFilter && customDateContainer) {
                dateFilter.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customDateContainer.classList.remove('hidden');
                    } else {
                        customDateContainer.classList.add('hidden');
                    }
                });
            }

            // Drawer Elements
            const drawer = document.getElementById('sync-detail-drawer');
            const drawerBackdrop = document.getElementById('sync-drawer-backdrop');
            const drawerPanel = document.getElementById('sync-drawer-panel');
            const drawerCloseBtn = document.getElementById('sync-drawer-close');
            const drawerFooterClose = document.getElementById('sync-drawer-footer-close');

            const drawerRequestId = document.getElementById('drawer-sync-request-id');
            const drawerProcessedAt = document.getElementById('drawer-sync-processed-at');
            const drawerOutlet = document.getElementById('drawer-sync-outlet');
            const drawerDeviceName = document.getElementById('drawer-sync-device-name');
            const drawerDeviceIdentifier = document.getElementById('drawer-sync-device-identifier');
            const drawerContent = document.getElementById('drawer-sync-content');
            const detailUrlTemplate = root.dataset.syncDetailUrlTemplate || '';

            let lastFocusedTrigger = null;

            // Keyboard handler (ESC and Tab Trap)
            const keyHandler = (e) => {
                if (!drawer || drawer.classList.contains('hidden')) return;

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDrawer();
                } else if (e.key === 'Tab') {
                    const focusableElements = drawer.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                    if (focusableElements.length === 0) return;
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === firstElement || !drawer.contains(document.activeElement)) {
                            lastElement?.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastElement || !drawer.contains(document.activeElement)) {
                            firstElement?.focus();
                            e.preventDefault();
                        }
                    }
                }
            };

            // Drawer Controls
            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function formatRupiah(value) {
                const amount = Number(value) || 0;
                return 'Rp ' + amount.toLocaleString('id-ID');
            }

            function buildSyncContentHtml(data) {
                const invoices = data && Array.isArray(data.invoices) ? data.invoices : [];

                if (invoices.length === 0) {
                    return '<p class="text-sm text-slate-400 dark:text-slate-500">Tidak ada invoice pada push request ini.</p>';
                }

                let html = '<div class="space-y-3">';
                html += '<p class="text-xs text-slate-500 dark:text-slate-400">' + invoices.length + ' invoice dalam push request ini.</p>';

                invoices.forEach(function (invoice) {
                    const itemCount = invoice.items && invoice.items.length ? invoice.items.length : 0;
                    const meta = [invoice.status, invoice.payment_method, invoice.payment_status]
                        .filter(Boolean)
                        .map(escapeHtml)
                        .join(' · ');

                    html += '<div class="rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-800/40 p-3 space-y-2">';
                    html += '<div class="flex items-start justify-between gap-3">';
                    html += '<div class="min-w-0">';
                    html += '<p class="text-sm font-semibold text-slate-900 dark:text-slate-100 break-all">' + escapeHtml(invoice.transaction_number) + '</p>';
                    if (meta) {
                        html += '<p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">' + meta + '</p>';
                    }
                    html += '</div>';
                    html += '<p class="text-sm font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">' + escapeHtml(formatRupiah(invoice.total_amount)) + '</p>';
                    html += '</div>';

                    if (itemCount > 0) {
                        html += '<ul class="space-y-1.5 pt-1 border-t border-slate-200/70 dark:border-slate-700/60">';
                        invoice.items.forEach(function (item) {
                            const label = item.product_name + ' × ' + item.quantity;
                            html += '<li class="flex items-start justify-between gap-3 text-xs text-slate-600 dark:text-slate-300">';
                            html += '<span class="min-w-0">' + escapeHtml(label) + '</span>';
                            html += '<span class="whitespace-nowrap font-medium">' + escapeHtml(formatRupiah(item.line_total)) + '</span>';
                            html += '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                });

                html += '</div>';
                return html;
            }

            function loadSyncContent(id) {
                if (!drawerContent) return;

                drawerContent.innerHTML = '<p class="text-sm text-slate-400 dark:text-slate-500">Memuat isi sinkronisasi...</p>';

                if (!detailUrlTemplate || !id) {
                    drawerContent.innerHTML = '<p class="text-sm text-slate-400 dark:text-slate-500">Tidak ada detail isi sinkronisasi.</p>';
                    return;
                }

                const url = detailUrlTemplate.replace('__ID__', encodeURIComponent(id));

                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Request failed with status ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function (json) {
                        drawerContent.innerHTML = buildSyncContentHtml(json.data || {});
                    })
                    .catch(function () {
                        drawerContent.innerHTML = '<p class="text-sm text-slate-400 dark:text-slate-500">Gagal memuat isi sinkronisasi.</p>';
                    });
            }

            function openDrawer(data, triggerEl) {
                if (!drawer) return;
                lastFocusedTrigger = triggerEl;

                // DOM-safe insertion using textContent
                if (drawerRequestId) drawerRequestId.textContent = data.requestId || '-';
                if (drawerProcessedAt) drawerProcessedAt.textContent = data.processedAt || '-';
                if (drawerOutlet) drawerOutlet.textContent = data.outletName || '-';
                if (drawerDeviceName) drawerDeviceName.textContent = data.deviceName || '-';
                if (drawerDeviceIdentifier) drawerDeviceIdentifier.textContent = data.deviceIdentifier || '-';

                loadSyncContent(data.id);

                drawer.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                document.body.style.overflow = 'hidden';

                document.removeEventListener('keydown', keyHandler);
                document.addEventListener('keydown', keyHandler);

                window.__syncDrawerCleanup = () => {
                    document.removeEventListener('keydown', keyHandler);
                    if (drawer) drawer.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    document.body.style.overflow = '';
                };

                requestAnimationFrame(() => {
                    drawerBackdrop?.classList.remove('opacity-0');
                    drawerPanel?.classList.remove('translate-x-full');
                    drawerCloseBtn?.focus();
                });
            }

            function closeDrawer() {
                if (!drawer) return;
                document.removeEventListener('keydown', keyHandler);
                window.__syncDrawerCleanup = null;

                drawerBackdrop?.classList.add('opacity-0');
                drawerPanel?.classList.add('translate-x-full');

                setTimeout(() => {
                    drawer.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    document.body.style.overflow = '';
                    if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
                        lastFocusedTrigger.focus();
                        lastFocusedTrigger = null;
                    }
                }, 300);
            }

            // Delegated click handler for detail buttons
            root.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-sync-detail');
                if (btn) {
                    const parent = btn.closest('.sync-row, .sync-card');
                    if (parent) {
                        const data = {
                            id: parent.dataset.id,
                            requestId: parent.dataset.requestId,
                            processedAt: parent.dataset.processedAt,
                            outletName: parent.dataset.outletName,
                            deviceName: parent.dataset.deviceName,
                            deviceIdentifier: parent.dataset.deviceIdentifier,
                        };
                        openDrawer(data, btn);
                    }
                }
            });

            drawerCloseBtn?.addEventListener('click', closeDrawer);
            drawerFooterClose?.addEventListener('click', closeDrawer);
            drawerBackdrop?.addEventListener('click', closeDrawer);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        initSyncPage();

        if (!window.__syncListenersBound) {
            window.__syncListenersBound = true;
            document.addEventListener('DOMContentLoaded', initSyncPage);
            document.addEventListener('livewire:navigated', initSyncPage);
            document.addEventListener('livewire:navigating', () => {
                if (typeof window.__syncDrawerCleanup === 'function') {
                    window.__syncDrawerCleanup();
                    window.__syncDrawerCleanup = null;
                }
                document.body.classList.remove('overflow-hidden');
                document.body.style.overflow = '';
            });
        }
    </script>
</x-layouts::app>
