@php
    $loadFixtures = require resource_path('views/sync/fixtures.php');
    $syncData = $loadFixtures();
    $syncCounter = $syncData['sync_counter'] ?? ['business_id' => 1, 'current_sequence' => 0];
    $requests = $syncData['requests'] ?? [];
    $devices = $syncData['devices'] ?? [];
    $outlets = $syncData['outlets'] ?? [];
    $summary = $syncData['summary'] ?? [
        'server_sequence' => 0,
        'total_requests' => 0,
        'devices_with_push' => 0,
        'last_processed' => 'Belum Ada',
    ];
@endphp

<x-layouts::app :title="'Sinkronisasi'">
    <main data-sync-page="true" class="space-y-6 pb-12">
        {{-- Header & Breadcrumb --}}
        <div class="space-y-1">
            <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <a href="{{ route('dashboard') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">Dashboard</a>
                <span>&gt;</span>
                <span class="text-slate-700 dark:text-slate-200 font-medium">Sinkronisasi</span>
            </nav>
            <div class="pt-1">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Sinkronisasi
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pantau sequence server dan push request yang telah diproses.
                </p>
            </div>
        </div>

        {{-- Info Callout --}}
        <x-sync.info-callout />

        {{-- Summary Cards --}}
        <x-sync.summary-cards :summary="$summary" />

        @if(empty($requests))
            {{-- Non-local / Production Empty State --}}
            <x-sync.empty-state type="no-data" />
        @else
            {{-- Filter Bar --}}
            <x-sync.filter-bar :devices="$devices" :outlets="$outlets" />

            {{-- Filter Zero Results --}}
            <x-sync.empty-state type="no-results" />

            {{-- Table (Desktop) --}}
            <x-sync.request-table :requests="$requests" />

            {{-- Cards (Mobile) --}}
            <x-sync.mobile-cards :requests="$requests" />

            {{-- Detail Drawer --}}
            <x-sync.detail-drawer />
        @endif
    </main>

    @push('scripts')
    <script>
        (function() {
            function initSyncPage() {
                const root = document.querySelector('main[data-sync-page="true"]');
                if (!root || root.dataset.syncInitialized === 'true') {
                    return;
                }
                root.dataset.syncInitialized = 'true';

                // DOM Elements
                const searchInput = document.getElementById('sync-search');
                const deviceFilter = document.getElementById('sync-device-filter');
                const outletFilter = document.getElementById('sync-outlet-filter');
                const dateFilter = document.getElementById('sync-date-filter');
                const customDateContainer = document.getElementById('sync-custom-date-container');
                const startDateInput = document.getElementById('sync-start-date');
                const endDateInput = document.getElementById('sync-end-date');
                const resetBtn = document.getElementById('sync-reset-filter');
                const resetEmptyBtn = document.getElementById('sync-reset-empty');

                const tableBody = document.getElementById('sync-table-body');
                const tableWrapper = tableBody ? tableBody.closest('.overflow-x-auto')?.parentElement : null;
                const mobileContainer = document.getElementById('sync-mobile-container');
                const noResults = document.getElementById('sync-no-results');

                const rows = document.querySelectorAll('.sync-row');
                const cards = document.querySelectorAll('.sync-card');

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

                // Helper: Local Calendar YYYY-MM-DD
                function formatLocalYMD(date) {
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                }

                function filterData() {
                    const query = (searchInput?.value || '').toLowerCase().trim();
                    const selectedDevice = (deviceFilter?.value || '').trim();
                    const selectedOutlet = (outletFilter?.value || '').trim();
                    const dateVal = dateFilter?.value || '';

                    const today = new Date();
                    const todayStr = formatLocalYMD(today);

                    let minDateStr = null;
                    let maxDateStr = null;

                    if (dateVal === 'today') {
                        minDateStr = todayStr;
                        maxDateStr = todayStr;
                    } else if (dateVal === '7days') {
                        const past = new Date(today);
                        past.setDate(past.getDate() - 6);
                        minDateStr = formatLocalYMD(past);
                        maxDateStr = todayStr;
                    } else if (dateVal === '30days') {
                        const past = new Date(today);
                        past.setDate(past.getDate() - 29);
                        minDateStr = formatLocalYMD(past);
                        maxDateStr = todayStr;
                    } else if (dateVal === 'custom') {
                        let start = startDateInput?.value || '';
                        let end = endDateInput?.value || '';
                        if (start && end && start > end) {
                            const tmp = start;
                            start = end;
                            end = tmp;
                            if (startDateInput) startDateInput.value = start;
                            if (endDateInput) endDateInput.value = end;
                        }
                        minDateStr = start || null;
                        maxDateStr = end || null;
                    }

                    let visibleCount = 0;

                    const matchItem = (el) => {
                        const reqId = (el.dataset.requestId || '').toLowerCase();
                        const devName = (el.dataset.deviceName || '').toLowerCase();
                        const devIdent = (el.dataset.deviceIdentifier || '').toLowerCase();
                        const devId = (el.dataset.deviceId || '').trim();
                        const outlet = (el.dataset.outletName || '').trim();
                        const processedRaw = (el.dataset.processedAtRaw || '').trim(); // e.g. "2026-09-21 10:42:00"
                        const itemDateStr = processedRaw.split(' ')[0] || '';

                        // Search check
                        if (query && !reqId.includes(query) && !devName.includes(query) && !devIdent.includes(query)) {
                            return false;
                        }

                        // Device filter
                        if (selectedDevice && devId !== selectedDevice && !devName.includes(selectedDevice)) {
                            return false;
                        }

                        // Outlet filter
                        if (selectedOutlet && outlet !== selectedOutlet) {
                            return false;
                        }

                        // Date filter
                        if (minDateStr && itemDateStr < minDateStr) {
                            return false;
                        }
                        if (maxDateStr && itemDateStr > maxDateStr) {
                            return false;
                        }

                        return true;
                    };

                    rows.forEach(row => {
                        const isMatch = matchItem(row);
                        row.classList.toggle('hidden', !isMatch);
                        if (isMatch) visibleCount++;
                    });

                    cards.forEach(card => {
                        const isMatch = matchItem(card);
                        card.classList.toggle('hidden', !isMatch);
                    });

                    // Zero results toggle
                    if (visibleCount === 0) {
                        if (noResults) noResults.classList.remove('hidden');
                        if (tableWrapper) tableWrapper.classList.add('hidden');
                        if (mobileContainer) mobileContainer.classList.add('hidden');
                    } else {
                        if (noResults) noResults.classList.add('hidden');
                        if (tableWrapper) tableWrapper.classList.remove('hidden');
                        if (mobileContainer) mobileContainer.classList.remove('hidden');
                    }
                }

                function resetFilters() {
                    if (searchInput) searchInput.value = '';
                    if (deviceFilter) deviceFilter.value = '';
                    if (outletFilter) outletFilter.value = '';
                    if (dateFilter) dateFilter.value = '';
                    if (startDateInput) startDateInput.value = '';
                    if (endDateInput) endDateInput.value = '';
                    if (customDateContainer) customDateContainer.classList.add('hidden');
                    filterData();
                }

                // Event Listeners for Filters
                searchInput?.addEventListener('input', filterData);
                deviceFilter?.addEventListener('change', filterData);
                outletFilter?.addEventListener('change', filterData);
                dateFilter?.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customDateContainer?.classList.remove('hidden');
                    } else {
                        customDateContainer?.classList.add('hidden');
                    }
                    filterData();
                });
                startDateInput?.addEventListener('change', filterData);
                endDateInput?.addEventListener('change', filterData);
                resetBtn?.addEventListener('click', resetFilters);
                resetEmptyBtn?.addEventListener('click', resetFilters);

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

                    requestAnimationFrame(() => {
                        drawerBackdrop?.classList.remove('opacity-0');
                        drawerPanel?.classList.remove('translate-x-full');
                        drawerCloseBtn?.focus();
                    });
                }

                function closeDrawer() {
                    if (!drawer) return;
                    drawerBackdrop?.classList.add('opacity-0');
                    drawerPanel?.classList.add('translate-x-full');

                    setTimeout(() => {
                        drawer.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                        if (lastFocusedTrigger) {
                            lastFocusedTrigger.focus();
                            lastFocusedTrigger = null;
                        }
                    }, 300);
                }

                // Detail Button Click Handlers
                document.querySelectorAll('.btn-sync-detail').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const parent = this.closest('.sync-row') || this.closest('.sync-card');
                        if (!parent) return;

                        const data = {
                            requestId: parent.dataset.requestId,
                            processedAt: parent.dataset.processedAt,
                            outletName: parent.dataset.outletName,
                            deviceName: parent.dataset.deviceName,
                            deviceIdentifier: parent.dataset.deviceIdentifier
                        };

                        openDrawer(data, this);
                    });
                });

                drawerCloseBtn?.addEventListener('click', closeDrawer);
                drawerFooterClose?.addEventListener('click', closeDrawer);
                drawerBackdrop?.addEventListener('click', closeDrawer);

                // Keyboard handler (ESC and Tab Trap)
                const keyHandler = (e) => {
                    if (drawer && !drawer.classList.contains('hidden')) {
                        if (e.key === 'Escape') {
                            e.preventDefault();
                            closeDrawer();
                        } else if (e.key === 'Tab') {
                            const focusableElements = drawer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                            const firstElement = focusableElements[0];
                            const lastElement = focusableElements[focusableElements.length - 1];

                            if (e.shiftKey) {
                                if (document.activeElement === firstElement) {
                                    lastElement?.focus();
                                    e.preventDefault();
                                }
                            } else {
                                if (document.activeElement === lastElement) {
                                    firstElement?.focus();
                                    e.preventDefault();
                                }
                            }
                        }
                    }
                };
                document.addEventListener('keydown', keyHandler);
            }

            // Global lifecycle guard for wire:navigate
            if (!window.__syncPageScriptInitialized) {
                window.__syncPageScriptInitialized = true;
                document.addEventListener('livewire:navigated', initSyncPage);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initSyncPage);
            } else {
                initSyncPage();
            }
        })();
    </script>
    @endpush
</x-layouts::app>
