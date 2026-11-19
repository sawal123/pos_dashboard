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

            {{-- Action Button (owner-only) — PAIR-01: perangkat kasir dipasangkan
                 dengan kode 4 digit; identitas perangkat tidak lagi diketik manual. --}}
            @if($canManageDevices)
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <button
                        type="button"
                        id="openDeviceLimitModalBtn"
                        onclick="document.getElementById('deviceLimitModal')?.classList.remove('hidden')"
                        class="py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        <i data-lucide="sliders" class="w-4 h-4 text-indigo-500"></i>
                        <span>Batas: {{ $summary['device_limit'] ?? 5 }} Unit</span>
                    </button>
                    @if($canPairCashierDevices)
                        <button
                            type="button"
                            id="pairCashierBtn"
                            data-open-pairing-modal
                            class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-600/20 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            <i data-lucide="key-round" class="w-4 h-4"></i>
                            <span>Kode Kasir</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- ==================== FLASH / VALIDATION FEEDBACK ==================== --}}
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4 space-y-1" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Tindakan tidak dapat diproses.</p>
                </div>
                <ul class="pl-8 list-disc text-xs text-rose-700 dark:text-rose-300 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ==================== 1. SUMMARY METRICS ==================== --}}
        <x-devices.summary-cards :summary="$summary" />

        {{-- ==================== 1b. KODE PAIRING KASIR (PAIR-01) ==================== --}}
        @if($canPairCashierDevices)
            <x-devices.pairing-panel :codes="$activePairingCodes" :can-manage="$canManageDevices" />
        @endif

        {{-- ==================== 2. FILTER BAR ==================== --}}
        <x-devices.filter-bar
            :outlets="$outlets"
            :platforms="$platforms"
            :currentFilters="$currentFilters"
        />

        {{-- ==================== 3. DATA LIST / EMPTY STATES ==================== --}}
        @if(!$hasAnyDevices)
            <x-devices.empty-state mode="no-data" :can-manage="$canManageDevices" :can-pair="$canPairCashierDevices" />
        @elseif($devices->isEmpty())
            <x-devices.empty-state mode="no-results" :can-manage="$canManageDevices" />
        @else
            <div id="deviceDataContainer" class="space-y-4">
                {{-- Desktop Table View --}}
                <x-devices.table :devices="$devices" :can-manage="$canManageDevices" />

                {{-- Mobile Cards View --}}
                <x-devices.mobile-cards :devices="$devices" :can-manage="$canManageDevices" />
            </div>
            @if($devices->hasPages())
                <div class="pt-2">
                    {{ $devices->links() }}
                </div>
            @endif
        @endif

        {{-- ==================== 4. DETAIL DRAWER ==================== --}}
        <x-devices.detail-drawer :can-manage="$canManageDevices" />

        @if($canManageDevices)
            {{-- ==================== 5. REGISTRATION / EDIT MODAL (DASH-17) ==================== --}}
            <x-devices.device-modal :outlets="$outlets" />

            @if($canPairCashierDevices)
                {{-- ==================== 6. KODE PAIRING KASIR MODAL (PAIR-01) ==================== --}}
                <x-devices.pairing-modal :outlets="$outlets" />
            @endif

            {{-- ==================== 7. MODAL ATUR BATAS KUOTA PERANGKAT ==================== --}}
            <div
                id="deviceLimitModal"
                class="fixed inset-0 z-50 hidden"
                role="dialog"
                aria-modal="true"
            >
                <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" onclick="document.getElementById('deviceLimitModal').classList.add('hidden')"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-5 relative z-10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="sliders" class="w-4 h-4"></i>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">Batas Perangkat Terhubung</h3>
                            </div>
                            <button type="button" onclick="document.getElementById('deviceLimitModal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('devices.limit.update') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="modal_max_devices" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Jumlah Maksimal Perangkat (Minimal 5)
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        id="modal_max_devices"
                                        name="max_devices"
                                        min="5"
                                        max="500"
                                        step="1"
                                        value="{{ $summary['device_limit'] ?? 5 }}"
                                        required
                                        class="w-full h-11 px-3.5 pr-20 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-base font-extrabold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 tabular-nums"
                                    >
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-xs text-slate-400">
                                        perangkat
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                                    Minimal default otomatis 5 perangkat. Perangkat aktif saat ini: <strong>{{ $summary['active_devices'] ?? 0 }}</strong> unit. Sisa kuota: <strong>{{ $summary['remaining_slots'] ?? 0 }}</strong> unit.
                                </p>
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-2">
                                <button
                                    type="button"
                                    onclick="document.getElementById('deviceLimitModal').classList.add('hidden')"
                                    class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-semibold text-white transition-colors shadow-xs"
                                >
                                    Simpan Batas Kuota
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

    </main>

    {{-- ==================== PAIR-01 — PAIRING MODAL, COUNTDOWN & COPY ==================== --}}
    <script>
        (function () {
            if (window.__pairingBound) {
                return;
            }
            window.__pairingBound = true;

            const modal = () => document.getElementById('pairingModal');

            const closeModal = () => {
                const el = modal();
                if (!el || el.classList.contains('hidden')) return;
                el.classList.add('hidden');
                document.body.style.overflow = '';
            };

            const openModal = () => {
                const el = modal();
                if (!el) return;
                el.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                const first = el.querySelector('select, input, button');
                if (first && typeof first.focus === 'function') {
                    first.focus({ preventScroll: true });
                }
            };

            const formatRemaining = (ms) => {
                if (ms <= 0) return 'kedaluwarsa';
                const totalSeconds = Math.floor(ms / 1000);
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                return minutes + 'm ' + String(seconds).padStart(2, '0') + 's';
            };

            const tickCountdowns = () => {
                const nowMs = Date.now();

                document.querySelectorAll('[data-pairing-countdown]').forEach((el) => {
                    const result = el.closest('[data-expires-at]');
                    const expiresAt = result ? Date.parse(result.getAttribute('data-expires-at')) : NaN;
                    if (!Number.isFinite(expiresAt)) return;
                    el.textContent = formatRemaining(expiresAt - nowMs);
                });

                document.querySelectorAll('[data-pairing-remaining]').forEach((el) => {
                    const row = el.closest('[data-expires-at]');
                    const expiresAt = row ? Date.parse(row.getAttribute('data-expires-at')) : NaN;
                    if (!Number.isFinite(expiresAt)) return;
                    el.textContent = formatRemaining(expiresAt - nowMs);
                });
            };

            tickCountdowns();
            window.setInterval(tickCountdowns, 1000);

            // Clipboard with a no-permission fallback (older WebViews).
            const copyCode = async (code) => {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(code);
                        return true;
                    }
                } catch (error) {
                    // fall through to the legacy path
                }

                try {
                    const input = document.createElement('textarea');
                    input.value = code;
                    input.setAttribute('readonly', 'readonly');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    const copied = document.execCommand('copy');
                    document.body.removeChild(input);
                    return copied;
                } catch (error) {
                    return false;
                }
            };

            document.addEventListener('click', async (event) => {
                const target = event.target;
                if (!(target instanceof Element)) return;

                if (target.closest('[data-close-pairing-modal]')) {
                    event.preventDefault();
                    closeModal();
                    return;
                }

                if (target.closest('[data-open-pairing-modal]')) {
                    event.preventDefault();
                    openModal();
                    return;
                }

                const copyBtn = target.closest('[data-copy-pairing-code]');
                if (copyBtn) {
                    event.preventDefault();
                    const code = copyBtn.getAttribute('data-copy-pairing-code') || '';
                    const copied = await copyCode(code);
                    const label = copyBtn.querySelector('i');
                    if (label) {
                        label.setAttribute('data-lucide', copied ? 'check' : 'alert-circle');
                    }
                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                        window.lucide.createIcons();
                    }
                }
            });

            document.addEventListener('keydown', (event) => {
                const el = modal();
                if (!el || el.classList.contains('hidden')) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeModal();
                }
            });

            // Server-side validation errors reopen the modal (full reload path).
            const restore = () => {
                const el = modal();
                if (el && el.getAttribute('data-restore-mode') === 'create') {
                    openModal();
                }
            };

            restore();
            document.addEventListener('livewire:navigated', restore);
        })();
    </script>

    {{-- ==================== CLIENT-SIDE SCRIPTS ==================== --}}
    <script>
        function initDevicesPage() {
            const root = document.querySelector('main[data-devices-page="true"]');
            if (!root || root.dataset.devicesInitialized === 'true') {
                return;
            }
            root.dataset.devicesInitialized = 'true';

            // Drawer Elements
            const drawerWrapper = document.getElementById('deviceDrawerWrapper');
            const drawerBackdrop = document.getElementById('deviceDrawerBackdrop');
            const drawerPanel = document.getElementById('deviceDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeDeviceDrawerBtn');
            const closeDrawerFooterBtn = document.getElementById('closeDeviceDrawerFooterBtn');

            let lastTriggerElement = null;

            // Safe DOM Rendering for Device Detail Drawer
            function openDrawer(itemData) {
                if (!drawerWrapper || !itemData) return;

                // Hand the row data to the DASH-17 edit modal via the drawer
                // button so both features share one source without coupling.
                const drawerEditBtn = document.getElementById('deviceDrawerEditBtn');
                if (drawerEditBtn) {
                    drawerEditBtn.dataset.deviceRaw = JSON.stringify(itemData);
                }

                document.getElementById('deviceDrawerTitle').textContent = itemData.name || '-';
                document.getElementById('deviceDrawerNameHeading').textContent = itemData.name || '-';
                document.getElementById('deviceDrawerIdentifier').textContent = itemData.identifier || '-';
                document.getElementById('deviceDrawerOutlet').textContent = itemData.outlet_name || '-';
                document.getElementById('deviceDrawerPlatform').textContent = itemData.platform || 'Tidak Diketahui';
                document.getElementById('deviceDrawerRegisteredAt').textContent = itemData.registered_at || '-';
                document.getElementById('deviceDrawerLastSeenAt').textContent = itemData.last_seen_at || 'Belum Pernah Akses API';

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

    @if($canManageDevices)
        {{-- DASH-17 — registration/edit modal wiring (bound once, delegated). --}}
        <script>
            (function () {
                if (window.__deviceModalBound) {
                    return;
                }
                window.__deviceModalBound = true;

                const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

                let lastTrigger = null;

                const modalEl = () => document.getElementById('deviceModal');
                const formEl = () => document.getElementById('deviceForm');

                const setValue = (form, name, value) => {
                    const field = form.elements.namedItem(name);
                    if (field) {
                        field.value = value === null || value === undefined ? '' : value;
                    }
                };

                const setHeading = (subtitle, title) => {
                    const subEl = document.getElementById('deviceModalSubtitle');
                    const titleEl = document.getElementById('deviceModalTitle');
                    if (subEl) subEl.textContent = subtitle;
                    if (titleEl) titleEl.textContent = title;
                };

                const open = (trigger) => {
                    const el = modalEl();
                    if (!el) return;

                    lastTrigger = trigger || null;
                    el.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';

                    requestAnimationFrame(() => {
                        const focusables = Array.from(el.querySelectorAll(focusableSelector));
                        if (focusables.length > 0) {
                            focusables[0].focus({ preventScroll: true });
                        } else {
                            el.focus({ preventScroll: true });
                        }
                    });
                };

                const close = () => {
                    const el = modalEl();
                    if (!el || el.classList.contains('hidden')) return;

                    el.classList.add('hidden');
                    document.body.style.overflow = '';

                    if (lastTrigger && document.contains(lastTrigger) && typeof lastTrigger.focus === 'function') {
                        lastTrigger.focus({ preventScroll: true });
                    }
                    lastTrigger = null;
                };

                // Identifier and outlet are immutable from the dashboard. The
                // visible outlet select is disabled in edit mode while a hidden
                // mirror keeps the id in the payload.
                const applyMode = (mode, raw) => {
                    const form = formEl();
                    if (!form) return;

                    const identifier = document.getElementById('deviceFormIdentifier');
                    const outlet = document.getElementById('deviceFormOutlet');
                    const mirror = document.getElementById('deviceFormOutletMirror');

                    if (mode === 'edit' && raw) {
                        form.setAttribute('action', form.dataset.updateUrlTemplate.replace('__ID__', raw.id));
                        setValue(form, '_method', 'PATCH');
                        setValue(form, 'device_form', 'edit');
                        setValue(form, 'device_id', raw.id ?? '');
                        if (identifier) identifier.setAttribute('readonly', 'readonly');
                        if (outlet) outlet.setAttribute('disabled', 'disabled');
                        if (mirror) {
                            mirror.disabled = false;
                            mirror.value = raw.outlet_id ?? '';
                        }
                    } else {
                        form.setAttribute('action', form.dataset.storeUrl);
                        setValue(form, '_method', 'POST');
                        setValue(form, 'device_form', 'create');
                        setValue(form, 'device_id', '');
                        if (identifier) identifier.removeAttribute('readonly');
                        if (outlet) outlet.removeAttribute('disabled');
                        if (mirror) {
                            mirror.disabled = true;
                            mirror.value = '';
                        }
                    }
                };

                const openCreate = (trigger) => {
                    const form = formEl();
                    if (!form) return;

                    form.reset();
                    ['name', 'identifier', 'platform', 'notes', 'outlet_id'].forEach((name) => setValue(form, name, ''));
                    applyMode('create');
                    setHeading('Registrasi', 'Daftarkan Perangkat');
                    open(trigger);
                };

                const openEdit = (raw, trigger) => {
                    if (!raw) return;
                    const form = formEl();
                    if (!form) return;

                    form.reset();
                    setValue(form, 'name', raw.name || '');
                    setValue(form, 'identifier', raw.identifier || '');
                    setValue(form, 'platform', raw.platform_raw || '');
                    setValue(form, 'notes', raw.notes || '');
                    const outlet = document.getElementById('deviceFormOutlet');
                    if (outlet) outlet.value = raw.outlet_id ?? '';

                    applyMode('edit', raw);
                    setHeading('Edit', 'Edit Perangkat');
                    open(trigger);
                };

                // After a validation redirect the server has already rendered the
                // form with the correct action, method, values and immutable
                // fields; we only reopen the modal for the mode it recorded.
                const restoreFromServerState = () => {
                    const el = modalEl();
                    if (!el) return;
                    const mode = el.getAttribute('data-restore-mode');
                    if (mode === 'create' || mode === 'edit') {
                        open(null);
                    }
                };

                const parseRaw = (el) => {
                    const raw = el.getAttribute('data-device-raw');
                    if (!raw) return null;
                    try {
                        return JSON.parse(raw);
                    } catch (error) {
                        return null;
                    }
                };

                document.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof Element)) return;

                    if (target.closest('[data-close-device-modal]')) {
                        close();
                        return;
                    }

                    const trigger = target.closest('[data-open-device-modal]');
                    if (!trigger) return;

                    event.preventDefault();
                    const mode = trigger.getAttribute('data-device-mode') || 'create';

                    if (mode === 'edit') {
                        openEdit(parseRaw(trigger), trigger);
                    } else {
                        openCreate(trigger);
                    }
                });

                document.addEventListener('keydown', (event) => {
                    const el = modalEl();
                    if (!el || el.classList.contains('hidden')) return;

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        close();
                        return;
                    }

                    if (event.key !== 'Tab') return;

                    const focusables = Array.from(el.querySelectorAll(focusableSelector));
                    if (focusables.length === 0) return;

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
                });

                // Prevent double submits; the identifier's uniqueness is also
                // enforced server-side (idempotent resolution).
                document.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement) || form.id !== 'deviceForm') return;

                    form.querySelectorAll('button[type="submit"]').forEach((button) => {
                        button.disabled = true;
                        const label = button.querySelector('[data-submit-label]');
                        if (label) label.textContent = 'Menyimpan…';
                    });
                });

                // Reopen the modal when the server recorded a failed modal
                // submission (full-page reload and Livewire navigation alike).
                restoreFromServerState();
                document.addEventListener('livewire:navigated', restoreFromServerState);
            })();
        </script>
    @endif
</x-layouts::app>
