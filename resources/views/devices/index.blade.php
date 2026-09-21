@php
    $loadFixtures = require resource_path('views/devices/fixtures.php');
    $fixtureData = $loadFixtures();
    $hasData = !empty($fixtureData['devices']);
@endphp

<x-layouts::app :title="'Perangkat'">
    <main id="mainContent" data-devices-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== DEVICE HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Perangkat</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Perangkat
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Pantau perangkat POS yang terdaftar pada bisnis dan outlet.
                </p>
            </div>

            {{-- Action Button --}}
            <div class="flex items-center gap-2.5 shrink-0">
                <button
                    type="button"
                    id="registerDeviceBtn"
                    class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-600/20 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Daftarkan Perangkat</span>
                </button>
            </div>
        </div>

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-devices.summary-cards :summary="$fixtureData['summary']" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-devices.filter-bar
            :outlets="$fixtureData['outlets']"
            :platforms="$fixtureData['platforms']"
        />

        {{-- ==================== 3. DATA LIST / EMPTY STATES ==================== --}}
        @if($hasData)
            <div id="deviceDataContainer" class="space-y-4">
                {{-- Desktop Table View --}}
                <x-devices.table :devices="$fixtureData['devices']" />

                {{-- Mobile Cards View --}}
                <x-devices.mobile-cards :devices="$fixtureData['devices']" />

                {{-- Filter Empty State --}}
                <x-devices.empty-state mode="no-results" />
            </div>

            {{-- Pagination --}}
            <div id="devicePagination" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan <span id="deviceVisibleCount" class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ count($fixtureData['devices']) }}</span> perangkat
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Perangkat">
                    <button
                        type="button"
                        disabled
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs transition-colors"
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-current="page"
                    >
                        1
                    </button>
                    <button
                        type="button"
                        disabled
                        class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs transition-colors"
                    >
                        Berikutnya
                    </button>
                </nav>
            </div>
        @else
            {{-- Production / Non-Local Initial Empty State --}}
            <x-devices.empty-state mode="no-data" />
        @endif

        {{-- ==================== 4. DETAIL DRAWER ==================== --}}
        <x-devices.detail-drawer />

    </main>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initDevicesPage() {
            const root = document.querySelector('main[data-devices-page="true"]');
            if (!root || root.dataset.devicesInitialized === 'true') {
                return;
            }
            root.dataset.devicesInitialized = 'true';

            const searchInput = document.getElementById('searchDeviceInput');
            const filterOutlet = document.getElementById('filterDeviceOutlet');
            const filterStatus = document.getElementById('filterDeviceStatus');
            const filterPlatform = document.getElementById('filterDevicePlatform');
            const resetBtn = document.getElementById('resetDeviceFilterBtn');
            const registerBtn = document.getElementById('registerDeviceBtn');

            const rows = document.querySelectorAll('.device-row');
            const cards = document.querySelectorAll('.device-card');
            const tableContainer = document.getElementById('desktopDeviceTable')?.closest('.rounded-2xl');
            const mobileContainer = document.getElementById('mobileDeviceCards');
            const emptyState = document.getElementById('deviceFilterEmptyState');
            const paginationEl = document.getElementById('devicePagination');
            const visibleCountEl = document.getElementById('deviceVisibleCount');

            // Drawer Elements
            const drawerWrapper = document.getElementById('deviceDrawerWrapper');
            const drawerBackdrop = document.getElementById('deviceDrawerBackdrop');
            const drawerPanel = document.getElementById('deviceDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeDeviceDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeDeviceDrawerFooterBtn');

            let lastTriggerElement = null;

            // Client-side Filtering
            function applyFilters() {
                const search = (searchInput?.value || '').trim().toLowerCase();
                const outlet = filterOutlet?.value || 'all';
                const status = filterStatus?.value || 'all';
                const platform = filterPlatform?.value || 'all';

                let matchedCount = 0;

                const checkMatch = (el) => {
                    const name = el.getAttribute('data-name') || '';
                    const iden = el.getAttribute('data-identifier') || '';
                    const elOutlet = el.getAttribute('data-outlet');
                    const elStatus = el.getAttribute('data-status');
                    const elPlatform = el.getAttribute('data-platform');

                    const matchSearch = !search || name.includes(search) || iden.includes(search);
                    const matchOutlet = outlet === 'all' || elOutlet === outlet;
                    const matchStatus = status === 'all' || elStatus === status;
                    const matchPlatform = platform === 'all' || elPlatform === platform;

                    return matchSearch && matchOutlet && matchStatus && matchPlatform;
                };

                rows.forEach(r => {
                    const m = checkMatch(r);
                    r.style.display = m ? '' : 'none';
                    if (m) matchedCount++;
                });

                cards.forEach(c => {
                    c.style.display = checkMatch(c) ? '' : 'none';
                });

                if (visibleCountEl) visibleCountEl.textContent = matchedCount;

                if (matchedCount === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                    if (tableContainer) tableContainer.classList.add('hidden');
                    if (mobileContainer) mobileContainer.classList.add('hidden');
                    if (paginationEl) paginationEl.classList.add('hidden');
                } else {
                    if (emptyState) emptyState.classList.add('hidden');
                    if (tableContainer) tableContainer.classList.remove('hidden');
                    if (mobileContainer) mobileContainer.classList.remove('hidden');
                    if (paginationEl) paginationEl.classList.remove('hidden');
                }
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (filterOutlet) filterOutlet.addEventListener('change', applyFilters);
            if (filterStatus) filterStatus.addEventListener('change', applyFilters);
            if (filterPlatform) filterPlatform.addEventListener('change', applyFilters);

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (filterOutlet) filterOutlet.value = 'all';
                    if (filterStatus) filterStatus.value = 'all';
                    if (filterPlatform) filterPlatform.value = 'all';
                    applyFilters();
                });
            }

            // Register Action Placeholder
            if (registerBtn) {
                registerBtn.onclick = () => {
                    alert('Registrasi perangkat dari dashboard akan tersedia setelah integrasi data.');
                };
            }

            // Safe DOM Rendering for Device Detail Drawer
            function openDrawer(itemData) {
                if (!drawerWrapper || !itemData) return;

                document.getElementById('deviceDrawerTitle').textContent = itemData.name || '-';
                document.getElementById('deviceDrawerNameHeading').textContent = itemData.name || '-';
                document.getElementById('deviceDrawerIdentifier').textContent = itemData.identifier || '-';
                document.getElementById('deviceDrawerOutlet').textContent = itemData.outlet_name || '-';
                document.getElementById('deviceDrawerPlatform').textContent = itemData.platform || 'Tidak Diketahui';
                document.getElementById('deviceDrawerRegisteredAt').textContent = itemData.registered_at || '-';
                document.getElementById('deviceDrawerLastSeenAt').textContent = itemData.last_seen_at || 'Belum Pernah Terlihat';

                // Status Badge
                const badgeContainer = document.getElementById('deviceDrawerStatusBadge');
                badgeContainer.textContent = '';
                const badge = document.createElement('span');
                const dot = document.createElement('span');
                const label = document.createElement('span');

                const isActive = itemData.status === 'active';
                badge.className = isActive
                    ? 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400'
                    : 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                dot.className = isActive ? 'w-1.5 h-1.5 rounded-full bg-emerald-500' : 'w-1.5 h-1.5 rounded-full bg-slate-400';
                label.textContent = isActive ? 'Aktif' : 'Nonaktif';

                badge.appendChild(dot);
                badge.appendChild(label);
                badgeContainer.appendChild(badge);

                // Notes Section
                const notesContainer = document.getElementById('deviceDrawerNotesContainer');
                if (itemData.notes) {
                    notesContainer.classList.remove('hidden');
                    document.getElementById('deviceDrawerNotes').textContent = itemData.notes;
                } else {
                    notesContainer.classList.add('hidden');
                }

                // Show Drawer
                drawerWrapper.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    drawerBackdrop.classList.remove('opacity-0');
                    drawerBackdrop.classList.add('opacity-100');
                    drawerPanel.classList.remove('translate-x-full');
                    drawerPanel.classList.add('translate-x-0');
                    closeDrawerBtn?.focus();
                });

                document.addEventListener('keydown', handleDeviceDrawerTrap);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleDeviceDrawerTrap);

                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (lastTriggerElement && typeof lastTriggerElement.focus === 'function') {
                        lastTriggerElement.focus();
                    }
                }, 300);
            }

            function handleDeviceDrawerTrap(e) {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDrawer();
                    return;
                }

                if (e.key === 'Tab') {
                    const focusables = drawerPanel.querySelectorAll(
                        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    );
                    if (focusables.length === 0) return;

                    const firstEl = focusables[0];
                    const lastEl = focusables[focusables.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === firstEl || !drawerPanel.contains(document.activeElement)) {
                            e.preventDefault();
                            lastEl.focus();
                        }
                    } else {
                        if (document.activeElement === lastEl || !drawerPanel.contains(document.activeElement)) {
                            e.preventDefault();
                            firstEl.focus();
                        }
                    }
                }
            }

            document.querySelectorAll('.view-device-detail-btn').forEach(btn => {
                btn.onclick = () => {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.device-row, .device-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                openDrawer(JSON.parse(raw));
                            } catch (e) {
                                console.error('Failed to parse device data', e);
                            }
                        }
                    }
                };
            });

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;
        }

        initDevicesPage();

        if (!window.__devicesListenersBound) {
            window.__devicesListenersBound = true;
            document.addEventListener('DOMContentLoaded', initDevicesPage);
            document.addEventListener('livewire:navigated', initDevicesPage);
        }
    </script>
</x-layouts::app>
