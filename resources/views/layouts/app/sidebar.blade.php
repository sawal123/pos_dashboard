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

            // ========== SIDEBAR MANAGEMENT ==========
            const sidebar = document.getElementById('sidebar');
            const mobileBackdrop = document.getElementById('mobileBackdrop');
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const closeSidebarBtn = document.getElementById('closeSidebarBtn');
            const desktopCollapseBtn = document.getElementById('desktopCollapseBtn');
            const collapseSidebarBtn = document.getElementById('collapseSidebarBtn');
            const collapseIcon = document.getElementById('collapseIcon');
            const mainWrapper = document.getElementById('mainWrapper');

            function openMobileSidebar() {
                sidebar.classList.remove('-translate-x-full');
                mobileBackdrop.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileSidebar() {
                sidebar.classList.add('-translate-x-full');
                mobileBackdrop.classList.add('hidden');
                document.body.style.overflow = '';
            }

            function toggleDesktopCollapse() {
                sidebar.classList.toggle('sidebar-collapsed');
                sidebar.classList.toggle('w-72');
                sidebar.classList.toggle('w-20');
                mainWrapper.classList.toggle('md:ml-72');
                mainWrapper.classList.toggle('md:ml-20');
                if (collapseIcon) {
                    if (sidebar.classList.contains('sidebar-collapsed')) {
                        collapseIcon.setAttribute('data-lucide', 'chevrons-right');
                    } else {
                        collapseIcon.setAttribute('data-lucide', 'chevrons-left');
                    }
                    initIcons();
                }
            }

            if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMobileSidebar);
            if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeMobileSidebar);
            if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobileSidebar);
            if (desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleDesktopCollapse);
            if (collapseSidebarBtn) collapseSidebarBtn.addEventListener('click', toggleDesktopCollapse);

            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth < 768) closeMobileSidebar();
                });
            });

            // ========== DROPDOWN MANAGEMENT ==========
            const profileDropdownBtn = document.getElementById('profileDropdownBtn');
            const profileDropdown = document.getElementById('profileDropdown');
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');

            function toggleDropdown(dropdown) {
                dropdown.classList.toggle('dropdown-hidden');
            }

            function closeAllDropdowns(exceptDropdown) {
                const allDropdowns = [profileDropdown, notificationDropdown];
                allDropdowns.forEach(d => {
                    if (d && d !== exceptDropdown && !d.classList.contains('dropdown-hidden')) {
                        d.classList.add('dropdown-hidden');
                    }
                });
            }

            if (profileDropdownBtn) {
                profileDropdownBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeAllDropdowns(profileDropdown);
                    toggleDropdown(profileDropdown);
                    profileDropdownBtn.setAttribute('aria-expanded', !profileDropdown.classList.contains('dropdown-hidden'));
                });
            }

            if (notificationBtn) {
                notificationBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeAllDropdowns(notificationDropdown);
                    toggleDropdown(notificationDropdown);
                    notificationBtn.setAttribute('aria-expanded', !notificationDropdown.classList.contains('dropdown-hidden'));
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
            const managePlanBtn = document.getElementById('managePlanBtn');
            if (managePlanBtn) {
                managePlanBtn.addEventListener('click', () => showToast('info', 'Subscription plan management is not available yet.'));
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
