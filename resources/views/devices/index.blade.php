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
        <x-devices.summary-cards :summary="$summary" />

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-devices.filter-bar
            :outlets="$outlets"
            :platforms="$platforms"
            :currentFilters="$currentFilters"
        />

        {{-- ==================== 3. DATA LIST / EMPTY STATES ==================== --}}
        @if(!$hasAnyDevices)
            <x-devices.empty-state mode="no-data" />
        @elseif($devices->isEmpty())
            <x-devices.empty-state mode="no-results" />
        @else
            <div id="deviceDataContainer" class="space-y-4">
                {{-- Desktop Table View --}}
                <x-devices.table :devices="$devices" />

                {{-- Mobile Cards View --}}
                <x-devices.mobile-cards :devices="$devices" />
            </div>

            {{-- Pagination --}}
            @if($devices->hasPages())
                <div class="pt-2">
                    {{ $devices->links() }}
                </div>
            @endif
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

            const registerBtn = document.getElementById('registerDeviceBtn');

            // Drawer Elements
            const drawerWrapper = document.getElementById('deviceDrawerWrapper');
            const drawerBackdrop = document.getElementById('deviceDrawerBackdrop');
            const drawerPanel = document.getElementById('deviceDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeDeviceDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeDeviceDrawerFooterBtn');

            let lastTriggerElement = null;

            // Register Action Placeholder
            if (registerBtn) {
                registerBtn.onclick = () => {
                    alert('Registrasi perangkat dari dashboard belum tersedia.');
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

                const rawStatus = (itemData.status_raw || itemData.status || '').toLowerCase();
                let statusLabel = '';
                let isEmerald = false;

                if (rawStatus === 'active') {
                    statusLabel = 'Aktif';
                    isEmerald = true;
                } else if (rawStatus === 'inactive') {
                    statusLabel = 'Nonaktif';
                    isEmerald = false;
                } else {
                    statusLabel = (itemData.status || '')
                        .replace(/[_-]+/g, ' ')
                        .replace(/\b\w/g, c => c.toUpperCase());
                    isEmerald = false;
                }

                badge.className = isEmerald
                    ? 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400'
                    : 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                dot.className = isEmerald ? 'w-1.5 h-1.5 rounded-full bg-emerald-500' : 'w-1.5 h-1.5 rounded-full bg-slate-400';
                label.textContent = statusLabel;

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
                    drawerBackdrop?.classList.remove('opacity-0');
                    drawerBackdrop?.classList.add('opacity-100');
                    drawerPanel?.classList.remove('translate-x-full');
                    drawerPanel?.classList.add('translate-x-0');
                    closeDrawerBtn?.focus();
                });

                document.removeEventListener('keydown', handleDeviceDrawerTrap);
                document.addEventListener('keydown', handleDeviceDrawerTrap);

                window.__devicesDrawerCleanup = () => {
                    document.removeEventListener('keydown', handleDeviceDrawerTrap);
                    if (drawerWrapper) drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                };

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;

                document.removeEventListener('keydown', handleDeviceDrawerTrap);
                window.__devicesDrawerCleanup = null;

                drawerBackdrop?.classList.remove('opacity-100');
                drawerBackdrop?.classList.add('opacity-0');
                drawerPanel?.classList.remove('translate-x-0');
                drawerPanel?.classList.add('translate-x-full');

                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (lastTriggerElement && typeof lastTriggerElement.focus === 'function') {
                        lastTriggerElement.focus();
                        lastTriggerElement = null;
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

            // Delegated click handler for view detail buttons
            root.addEventListener('click', (e) => {
                const btn = e.target.closest('.view-device-detail-btn');
                if (btn) {
                    lastTriggerElement = btn;
                    const rowOrCard = btn.closest('.device-row, .device-card');
                    if (rowOrCard) {
                        const raw = rowOrCard.getAttribute('data-raw');
                        if (raw) {
                            try {
                                openDrawer(JSON.parse(raw));
                            } catch (err) {
                                console.error('Failed to parse device data', err);
                            }
                        }
                    }
                }
            });

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (closeDrawerFooterBtn) closeDrawerFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        initDevicesPage();

        if (!window.__devicesListenersBound) {
            window.__devicesListenersBound = true;
            document.addEventListener('DOMContentLoaded', initDevicesPage);
            document.addEventListener('livewire:navigated', initDevicesPage);
            document.addEventListener('livewire:navigating', () => {
                if (typeof window.__devicesDrawerCleanup === 'function') {
                    window.__devicesDrawerCleanup();
                    window.__devicesDrawerCleanup = null;
                }
                document.body.style.overflow = '';
            });
        }
    </script>
</x-layouts::app>
