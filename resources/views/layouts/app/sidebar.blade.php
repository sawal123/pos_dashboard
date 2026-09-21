<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300 min-h-screen">

        {{-- ==================== PAGE LOADER ==================== --}}
        <x-ui.page-loader />

        {{-- ==================== SIDEBAR ==================== --}}
        <x-ui.sidebar />

        {{-- ==================== MOBILE BACKDROP ==================== --}}
        <div id="mobileBackdrop" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm hidden md:hidden"></div>

        {{-- ==================== MAIN WRAPPER ==================== --}}
        <div id="mainWrapper" class="md:ml-72 transition-all duration-300 min-h-screen">

            {{-- ==================== NAVBAR ==================== --}}
            <x-ui.navbar :title="$title ?? null" />

            {{-- ==================== PAGE CONTENT ==================== --}}
            {{ $slot }}

        </div>

        {{-- ==================== MODALS ==================== --}}
        <x-ui.modal-add-product />
        <x-ui.modal-delete />

        {{-- ==================== TOAST CONTAINER ==================== --}}
        <x-ui.toast-container />

        {{-- ==================== JAVASCRIPT ==================== --}}
        <script>
            // ========== INITIALIZE LUCIDE ICONS ==========
            function initIcons() {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
            initIcons();
            document.addEventListener('DOMContentLoaded', initIcons);

            // ========== THEME MANAGEMENT ==========
            const htmlEl = document.documentElement;
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const moonIcon = document.getElementById('moonIcon');
            const sunIcon = document.getElementById('sunIcon');

            let salesChart = null; // global declaration

            function applyTheme(theme) {
                if (theme === 'dark') {
                    htmlEl.classList.add('dark');
                    htmlEl.classList.remove('light');
                    if (moonIcon) moonIcon.style.display = 'inline';
                    if (sunIcon) sunIcon.style.display = 'none';
                } else {
                    htmlEl.classList.remove('dark');
                    htmlEl.classList.add('light');
                    if (moonIcon) moonIcon.style.display = 'none';
                    if (sunIcon) sunIcon.style.display = 'inline';
                }
                localStorage.setItem('nexa-theme', theme);
                if (typeof salesChart !== 'undefined' && salesChart) updateChartColors();
            }

            function getStoredTheme() {
                return localStorage.getItem('nexa-theme') || 'light';
            }

            applyTheme(getStoredTheme());

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    const current = htmlEl.classList.contains('dark') ? 'dark' : 'light';
                    applyTheme(current === 'dark' ? 'light' : 'dark');
                });
            }

            // ========== SIDEBAR MANAGEMENT & PERSISTENCE ==========
            const sidebar = document.getElementById('sidebar');
            const mobileBackdrop = document.getElementById('mobileBackdrop');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');
            const desktopCollapseBtn = document.getElementById('desktopCollapseBtn');
            const collapseSidebarBtn = document.getElementById('collapseSidebarBtn');
            const collapseIcon = document.getElementById('collapseIcon');
            const collapseLabel = document.getElementById('collapseLabel');
            const mainWrapper = document.getElementById('mainWrapper');

            function getStoredSidebarCollapse() {
                return localStorage.getItem('nexa-sidebar-collapsed') === 'true';
            }

            function applyDesktopCollapseUI(collapsed) {
                if (!sidebar || !mainWrapper) return;
                if (collapsed) {
                    sidebar.classList.add('sidebar-collapsed', 'w-20');
                    sidebar.classList.remove('w-72');
                    mainWrapper.classList.add('md:ml-20');
                    mainWrapper.classList.remove('md:ml-72');
                    if (collapseIcon) collapseIcon.setAttribute('data-lucide', 'chevrons-right');
                    if (collapseLabel) collapseLabel.textContent = 'Bentangkan';
                    if (collapseSidebarBtn) {
                        collapseSidebarBtn.setAttribute('data-tooltip-right', 'Bentangkan Sidebar');
                        collapseSidebarBtn.setAttribute('aria-expanded', 'false');
                    }
                    if (desktopCollapseBtn) {
                        desktopCollapseBtn.setAttribute('data-tooltip', 'Bentangkan Sidebar');
                        desktopCollapseBtn.setAttribute('aria-expanded', 'false');
                    }
                } else {
                    sidebar.classList.remove('sidebar-collapsed', 'w-20');
                    sidebar.classList.add('w-72');
                    mainWrapper.classList.remove('md:ml-20');
                    mainWrapper.classList.add('md:ml-72');
                    if (collapseIcon) collapseIcon.setAttribute('data-lucide', 'chevrons-left');
                    if (collapseLabel) collapseLabel.textContent = 'Ciutkan';
                    if (collapseSidebarBtn) {
                        collapseSidebarBtn.setAttribute('data-tooltip-right', 'Ciutkan Sidebar');
                        collapseSidebarBtn.setAttribute('aria-expanded', 'true');
                    }
                    if (desktopCollapseBtn) {
                        desktopCollapseBtn.setAttribute('data-tooltip', 'Ciutkan Sidebar');
                        desktopCollapseBtn.setAttribute('aria-expanded', 'true');
                    }
                }
                initIcons();
            }

            function applySidebarStateForViewport() {
                if (!sidebar || !mainWrapper) return;
                if (window.innerWidth < 768) {
                    // Mobile: Always full width, never collapsed in drawer, DO NOT touch localStorage
                    sidebar.classList.remove('sidebar-collapsed', 'w-20');
                    sidebar.classList.add('w-72');
                    mainWrapper.classList.remove('md:ml-20');
                    mainWrapper.classList.add('md:ml-72');
                } else {
                    // Desktop: Restore preference from localStorage
                    const shouldCollapse = getStoredSidebarCollapse();
                    applyDesktopCollapseUI(shouldCollapse);
                }
            }

            function toggleDesktopCollapse() {
                if (window.innerWidth < 768) return;
                const willCollapse = !sidebar.classList.contains('sidebar-collapsed');
                localStorage.setItem('nexa-sidebar-collapsed', willCollapse ? 'true' : 'false');
                applyDesktopCollapseUI(willCollapse);
            }

            function openMobileSidebar() {
                // Ensure mobile drawer is always w-72 and never collapsed
                sidebar.classList.remove('sidebar-collapsed', 'w-20');
                sidebar.classList.add('w-72');
                sidebar.classList.remove('-translate-x-full');
                mobileBackdrop.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'true');
            }

            function closeMobileSidebar() {
                sidebar.classList.add('-translate-x-full');
                mobileBackdrop.classList.add('hidden');
                document.body.style.overflow = '';
                if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
            }

            if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMobileSidebar);
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeMobileSidebar);
            if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobileSidebar);
            if (desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleDesktopCollapse);
            if (collapseSidebarBtn) collapseSidebarBtn.addEventListener('click', toggleDesktopCollapse);

            // Apply initial viewport state
            applySidebarStateForViewport();

            // Window resize adjustment
            window.addEventListener('resize', () => {
                applySidebarStateForViewport();
                if (window.innerWidth >= 768) {
                    if (mobileBackdrop && !mobileBackdrop.classList.contains('hidden')) {
                        closeMobileSidebar();
                    }
                }
            });

            // ========== FLOATING TOOLTIP FOR COLLAPSED SIDEBAR ==========
            let floatingTooltip = document.getElementById('sidebarFloatingTooltip');
            if (!floatingTooltip) {
                floatingTooltip = document.createElement('div');
                floatingTooltip.id = 'sidebarFloatingTooltip';
                floatingTooltip.className = 'fixed z-[100] pointer-events-none px-2.5 py-1.5 rounded-lg text-xs font-medium text-white bg-slate-900 dark:bg-slate-800 border border-slate-700/80 shadow-xl opacity-0 transition-opacity duration-150 whitespace-nowrap';
                document.body.appendChild(floatingTooltip);
            }

            function showFloatingTooltip(el) {
                if (window.innerWidth < 768) return;
                const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
                // Only show floating tooltip when sidebar is collapsed (or for collapse button itself)
                if (!isCollapsed && el.id !== 'collapseSidebarBtn') return;
                const text = el.getAttribute('data-tooltip-right');
                if (!text) return;
                const rect = el.getBoundingClientRect();
                floatingTooltip.textContent = text;
                floatingTooltip.style.left = `${rect.right + 12}px`;
                floatingTooltip.style.top = `${rect.top + rect.height / 2}px`;
                floatingTooltip.style.transform = 'translateY(-50%)';
                floatingTooltip.classList.remove('opacity-0');
                floatingTooltip.classList.add('opacity-100');
            }

            function hideFloatingTooltip() {
                if (floatingTooltip) {
                    floatingTooltip.classList.remove('opacity-100');
                    floatingTooltip.classList.add('opacity-0');
                }
            }

            document.querySelectorAll('#sidebar [data-tooltip-right]').forEach(el => {
                el.addEventListener('mouseenter', () => showFloatingTooltip(el));
                el.addEventListener('mouseleave', hideFloatingTooltip);
                el.addEventListener('focus', () => showFloatingTooltip(el));
                el.addEventListener('blur', hideFloatingTooltip);
            });

            // ========== SIDEBAR ROADMAP ITEM PLACEHOLDER ==========
            document.querySelectorAll('.sidebar-item').forEach(item => {
                const hasRoute = item.getAttribute('data-has-route') === 'true';
                const label = item.getAttribute('data-nav-label') || 'Modul';

                item.addEventListener('click', (e) => {
                    if (window.innerWidth < 768) {
                        closeMobileSidebar();
                    }
                    if (!hasRoute) {
                        e.preventDefault();
                        showToast('info', `Modul ${label} akan tersedia pada pembaruan berikutnya.`);
                    }
                });
            });

            // ========== DROPDOWN MANAGEMENT & ARIA STATE ==========
            const profileDropdownBtn = document.getElementById('profileDropdownBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');

            function closeDropdown(dropdown, btn) {
                if (dropdown && !dropdown.classList.contains('dropdown-hidden')) {
                    dropdown.classList.add('dropdown-hidden');
                }
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
            }

            function openDropdown(dropdown, btn) {
                if (dropdown) {
                    dropdown.classList.remove('dropdown-hidden');
                }
                if (btn) {
                    btn.setAttribute('aria-expanded', 'true');
                }
            }

            function closeAllDropdowns(exceptDropdown) {
                if (profileDropdown !== exceptDropdown) {
                    closeDropdown(profileDropdown, profileDropdownBtn);
                }
                if (notificationDropdown !== exceptDropdown) {
                    closeDropdown(notificationDropdown, notificationBtn);
                }
            }

            if (profileDropdownBtn && profileDropdown) {
                profileDropdownBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const willOpen = profileDropdown.classList.contains('dropdown-hidden');
                    closeAllDropdowns(profileDropdown);
                    if (willOpen) {
                        openDropdown(profileDropdown, profileDropdownBtn);
                    } else {
                        closeDropdown(profileDropdown, profileDropdownBtn);
                    }
                });
            }

            if (notificationBtn && notificationDropdown) {
                notificationBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const willOpen = notificationDropdown.classList.contains('dropdown-hidden');
                    closeAllDropdowns(notificationDropdown);
                    if (willOpen) {
                        openDropdown(notificationDropdown, notificationBtn);
                    } else {
                        closeDropdown(notificationDropdown, notificationBtn);
                    }
                });
            }

            document.addEventListener('click', (e) => {
                closeAllDropdowns(null);
                closeAllCustomSelects(null);
                closeAllActionDropdowns();
            });

            // ========== CUSTOM SELECT SYSTEM ==========
            function initCustomSelect(wrapperId, btnId, dropdownId, labelId, onSelect) {
                const wrapper = document.getElementById(wrapperId);
                const btn = document.getElementById(btnId);
                const dropdown = document.getElementById(dropdownId);
                const label = document.getElementById(labelId);

                if (!wrapper || !btn || !dropdown || !label) return null;

                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeAllDropdowns(null);
                    closeAllCustomSelects(dropdown);
                    dropdown.classList.toggle('dropdown-hidden');
                    btn.setAttribute('aria-expanded', !dropdown.classList.contains('dropdown-hidden'));
                });

                dropdown.querySelectorAll('.select-option').forEach(option => {
                    option.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const value = option.getAttribute('data-value');
                        if (label) label.textContent = value;
                        dropdown.querySelectorAll('.select-option').forEach(o => o.classList.remove('selected'));
                        option.classList.add('selected');
                        dropdown.classList.add('dropdown-hidden');
                        btn.setAttribute('aria-expanded', 'false');
                        if (onSelect) onSelect(value);
                    });
                });

                return { wrapper, btn, dropdown, label };
            }

            function closeAllCustomSelects(exceptDropdown) {
                document.querySelectorAll('.select-dropdown, [id$="Dropdown"]').forEach(d => {
                    if (d !== exceptDropdown && d.classList.contains('dropdown-hidden') === false && d.id !== 'notificationDropdown' && d.id !== 'profileDropdown') {
                        d.classList.add('dropdown-hidden');
                    }
                });
            }

            // Initialize selects (these are initialized per-page after DOM is ready)
            let periodSelect, statusFilter, paymentFilter, modalCategorySelect;

            // ========== MODAL SYSTEM ==========
            function openModal(modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.classList.remove('modal-hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeModal(modal) {
                modal.classList.add('modal-hidden');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                }, 200);
            }

            document.querySelectorAll('[data-modal-close]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const modal = btn.closest('.fixed.inset-0');
                    if (modal) closeModal(modal);
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (mobileBackdrop && !mobileBackdrop.classList.contains('hidden')) {
                        closeMobileSidebar();
                    }
                    hideFloatingTooltip();
                    const addProductModal = document.getElementById('addProductModal');
                    const deleteModal = document.getElementById('deleteModal');
                    if (addProductModal && !addProductModal.classList.contains('hidden')) closeModal(addProductModal);
                    if (deleteModal && !deleteModal.classList.contains('hidden')) closeModal(deleteModal);
                    closeAllDropdowns(null);
                    closeAllCustomSelects(null);
                    closeAllActionDropdowns();
                }
            });

            // ========== TOAST SYSTEM ==========
            function showToast(type, message) {
                const container = document.getElementById('toastContainer');
                const toast = document.createElement('div');
                const icons = { success: 'check-circle', error: 'x-circle', warning: 'alert-triangle', info: 'info' };
                const colors = {
                    success: 'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200',
                    error: 'bg-red-50 dark:bg-red-900/30 border-red-200 dark:border-red-700 text-red-800 dark:text-red-200',
                    warning: 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-700 text-amber-800 dark:text-amber-200',
                    info: 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200'
                };
                const iconColors = {
                    success: 'text-green-600 dark:text-green-400',
                    error: 'text-red-600 dark:text-red-400',
                    warning: 'text-amber-600 dark:text-amber-400',
                    info: 'text-blue-600 dark:text-blue-400'
                };
                const iconName = icons[type] || 'info';
                toast.className = `pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-lg shadow-slate-200/50 dark:shadow-slate-900/50 ${colors[type]} toast-enter`;
                toast.innerHTML = `
                    <i data-lucide="${iconName}" class="w-5 h-5 flex-shrink-0 ${iconColors[type]}"></i>
                    <p class="text-sm font-medium flex-1">${message}</p>
                    <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors flex-shrink-0" aria-label="Dismiss toast"><i data-lucide="x" class="w-4 h-4"></i></button>
                `;
                container.appendChild(toast);
                initIcons();
                toast.querySelector('button').addEventListener('click', () => {
                    toast.classList.add('toast-exit');
                    setTimeout(() => toast.remove(), 300);
                });
                setTimeout(() => {
                    toast.classList.add('toast-exit');
                    setTimeout(() => toast.remove(), 300);
                }, 5000);
            }

            // ========== LOADER SYSTEM ==========
            const pageLoader = document.getElementById('pageLoader');

            function showLoader() {
                pageLoader.classList.remove('hidden');
                pageLoader.classList.add('flex');
            }

            function hideLoader() {
                pageLoader.classList.add('hidden');
                pageLoader.classList.remove('flex');
            }

            // Initial brief loader
            showLoader();
            setTimeout(hideLoader, 1200);

            // ========== TOGGLE SWITCHES ==========
            document.querySelectorAll('.toggle-switch').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    toggle.classList.toggle('active');
                    const isActive = toggle.classList.contains('active');
                    toggle.setAttribute('aria-checked', isActive);
                });
                toggle.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle.click();
                    }
                });
            });

            // ========== MANAGE PLAN BUTTON ==========
            const managePlanHandler = () => showToast('info', 'Halaman kelola paket langganan akan segera tersedia.');
            const managePlanBtn = document.getElementById('managePlanBtn');
            const managePlanMiniBtn = document.getElementById('managePlanMiniBtn');
            if (managePlanBtn) {
                managePlanBtn.addEventListener('click', managePlanHandler);
            }
            if (managePlanMiniBtn) {
                managePlanMiniBtn.addEventListener('click', managePlanHandler);
            }

            // ========== ACTION DROPDOWNS ==========
            function closeAllActionDropdowns(except) {
                document.querySelectorAll('.action-dropdown').forEach(d => {
                    if (d !== except && !d.classList.contains('dropdown-hidden')) {
                        d.classList.add('dropdown-hidden');
                    }
                });
            }

            // ========== CHART HELPERS (global) ==========
            function getChartColors() {
                const isDark = htmlEl.classList.contains('dark');
                return {
                    primary: isDark ? '#6366f1' : '#4f46e5',
                    primaryAlpha: isDark ? 'rgba(99,102,241,0.15)' : 'rgba(79,70,229,0.1)',
                    grid: isDark ? 'rgba(148,163,184,0.15)' : 'rgba(148,163,184,0.3)',
                    ticks: isDark ? '#94a3b8' : '#64748b'
                };
            }

            function updateChartColors() {
                if (salesChart) {
                    const colors = getChartColors();
                    salesChart.options.scales.x.ticks.color = colors.ticks;
                    salesChart.options.scales.y.ticks.color = colors.ticks;
                    salesChart.options.scales.y.grid.color = colors.grid;
                    salesChart.options.plugins.tooltip.backgroundColor = htmlEl.classList.contains('dark') ? '#334155' : '#1e293b';
                    salesChart.data.datasets[0].borderColor = colors.primary;
                    salesChart.data.datasets[0].pointBackgroundColor = colors.primary;
                    salesChart.update();
                }
            }

            // ========== INITIALIZATION AFTER DOM ==========
            document.addEventListener('DOMContentLoaded', () => {
                initIcons();
            });
        </script>

        @stack('scripts')

    </body>
</html>
