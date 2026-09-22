<x-layouts::app :title="'Sinkronisasi'">
    <main data-sync-page="true" class="space-y-6 pb-12 max-w-7xl mx-auto p-4 md:p-6 lg:p-8">
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
            function openDrawer(data, triggerEl) {
                if (!drawer) return;
                lastFocusedTrigger = triggerEl;

                // DOM-safe insertion using textContent
                if (drawerRequestId) drawerRequestId.textContent = data.requestId || '-';
                if (drawerProcessedAt) drawerProcessedAt.textContent = data.processedAt || '-';
                if (drawerOutlet) drawerOutlet.textContent = data.outletName || '-';
                if (drawerDeviceName) drawerDeviceName.textContent = data.deviceName || '-';
                if (drawerDeviceIdentifier) drawerDeviceIdentifier.textContent = data.deviceIdentifier || '-';

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
