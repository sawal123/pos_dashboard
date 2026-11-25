<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen">

        {{-- ==================== PAGE LOADER ==================== --}}
        <x-ui.page-loader />

        {{-- ==================== SIDEBAR ==================== --}}
        <x-ui.sidebar :subscription="$dashboardSubscriptionState ?? 'unknown'" />

        {{-- ==================== MOBILE BACKDROP ==================== --}}
        <div id="mobileBackdrop" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm hidden md:hidden"></div>

        {{-- ==================== MAIN WRAPPER ==================== --}}
        <div id="mainWrapper" class="md:ml-72 transition-all duration-300 min-h-screen">

            {{-- ==================== NAVBAR ==================== --}}
            <x-ui.navbar :title="$title ?? null" />

            {{-- ==================== PAGE CONTENT ==================== --}}
            {{ $slot }}

        </div>

        {{-- ==================== TOAST CONTAINER ==================== --}}
        <x-ui.toast-container />

        {{-- ==================== GLOBAL CONFIRMATION MODAL ==================== --}}
        <x-ui.confirmation-modal />

        {{-- ==================== NEXABOT AI GUIDE CHATBOT ==================== --}}
        <x-ui.guide-chatbot />

        {{-- ==================== PWA STATUS & INSTALL BANNER ==================== --}}
        <x-ui.pwa-banner />

        {{-- ==================== JAVASCRIPT ==================== --}}
        <script data-navigate-once>
            (function() {
                // ========== INITIALIZE LUCIDE ICONS ==========
                let isUpdatingIcons = false;
                let iconDebounceTimer = null;

                function initIcons() {
                    if (typeof lucide === 'undefined' || isUpdatingIcons) return;
                    try {
                        isUpdatingIcons = true;
                        lucide.createIcons();
                        // Mark any unrecognized <i> icons to prevent infinite observer retries
                        document.querySelectorAll('i[data-lucide]').forEach((el) => {
                            if (!el.hasAttribute('data-lucide-rendered')) {
                                el.setAttribute('data-lucide-rendered', 'failed');
                            }
                        });
                    } catch (e) {
                        console.warn('Lucide icons error:', e);
                    } finally {
                        setTimeout(() => {
                            isUpdatingIcons = false;
                        }, 50);
                    }
                }
                window.initIcons = initIcons;

                // ========== THEME MANAGEMENT ==========
                function applyTheme(theme) {
                    const isDark = theme === 'dark';
                    const targetTheme = isDark ? 'dark' : 'light';
                    const html = document.documentElement;

                    html.classList.toggle('dark', isDark);
                    html.classList.toggle('light', !isDark);

                    try {
                        localStorage.setItem('nexa-theme', targetTheme);
                        localStorage.setItem('flux.appearance', targetTheme);
                    } catch (e) {}

                    if (window.Flux) {
                        try {
                            if (typeof window.Flux.applyAppearance === 'function') {
                                window.Flux.applyAppearance(targetTheme);
                            }
                            if ('appearance' in window.Flux) {
                                window.Flux.appearance = targetTheme;
                            }
                            if ('dark' in window.Flux) {
                                window.Flux.dark = isDark;
                            }
                        } catch (e) {}
                    }

                    // Reset any stale inline display style on toggle icons so Tailwind classes take priority
                    const moonIcon = document.getElementById('moonIcon');
                    const sunIcon = document.getElementById('sunIcon');
                    if (moonIcon) moonIcon.style.display = '';
                    if (sunIcon) sunIcon.style.display = '';

                    if (typeof updateChartColors === 'function') {
                        updateChartColors();
                    }
                }
                window.applyTheme = applyTheme;

                function getStoredTheme() {
                    try {
                        const nexa = localStorage.getItem('nexa-theme');
                        if (nexa === 'dark' || nexa === 'light') return nexa;
                        const flux = localStorage.getItem('flux.appearance');
                        if (flux === 'dark' || flux === 'light') return flux;
                    } catch (e) {}
                    return 'light';
                }
                window.getStoredTheme = getStoredTheme;

                // ========== SIDEBAR MANAGEMENT & PERSISTENCE ==========
                function getStoredSidebarCollapse() {
                    return localStorage.getItem('nexa-sidebar-collapsed') === 'true';
                }
                window.getStoredSidebarCollapse = getStoredSidebarCollapse;

                function applyDesktopCollapseUI(collapsed) {
                    const sidebar = document.getElementById('sidebar');
                    const mainWrapper = document.getElementById('mainWrapper');
                    const collapseIcon = document.getElementById('collapseIcon');
                    const collapseLabel = document.getElementById('collapseLabel');
                    const collapseSidebarBtn = document.getElementById('collapseSidebarBtn');
                    const desktopCollapseBtn = document.getElementById('desktopCollapseBtn');

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
                window.applyDesktopCollapseUI = applyDesktopCollapseUI;

                function applySidebarStateForViewport() {
                    const sidebar = document.getElementById('sidebar');
                    const mainWrapper = document.getElementById('mainWrapper');
                    if (!sidebar || !mainWrapper) return;
                    if (window.innerWidth < 768) {
                        sidebar.classList.remove('sidebar-collapsed', 'w-20');
                        sidebar.classList.add('w-72');
                        mainWrapper.classList.remove('md:ml-20');
                        mainWrapper.classList.add('md:ml-72');
                    } else {
                        const shouldCollapse = getStoredSidebarCollapse();
                        applyDesktopCollapseUI(shouldCollapse);
                    }
                }
                window.applySidebarStateForViewport = applySidebarStateForViewport;

                function toggleDesktopCollapse() {
                    if (window.innerWidth < 768) return;
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar) return;
                    const willCollapse = !sidebar.classList.contains('sidebar-collapsed');
                    try {
                        localStorage.setItem('nexa-sidebar-collapsed', willCollapse ? 'true' : 'false');
                    } catch (e) {}
                    applyDesktopCollapseUI(willCollapse);
                }
                window.toggleDesktopCollapse = toggleDesktopCollapse;

                function openMobileSidebar() {
                    const sidebar = document.getElementById('sidebar');
                    const mobileBackdrop = document.getElementById('mobileBackdrop');
                    const hamburgerBtn = document.getElementById('hamburgerBtn');
                    if (!sidebar) return;
                    sidebar.classList.remove('sidebar-collapsed', 'w-20');
                    sidebar.classList.add('w-72');
                    sidebar.classList.remove('-translate-x-full');
                    if (mobileBackdrop) mobileBackdrop.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'true');
                }
                window.openMobileSidebar = openMobileSidebar;

                function closeMobileSidebar() {
                    const sidebar = document.getElementById('sidebar');
                    const mobileBackdrop = document.getElementById('mobileBackdrop');
                    const hamburgerBtn = document.getElementById('hamburgerBtn');
                    if (!sidebar) return;
                    sidebar.classList.add('-translate-x-full');
                    if (mobileBackdrop) mobileBackdrop.classList.add('hidden');
                    document.body.style.overflow = '';
                    if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
                }
                window.closeMobileSidebar = closeMobileSidebar;

                // ========== DROPDOWN MANAGEMENT ==========
                function closeDropdown(dropdown, btn) {
                    if (dropdown && !dropdown.classList.contains('dropdown-hidden')) {
                        dropdown.classList.add('dropdown-hidden');
                    }
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                    }
                }
                window.closeDropdown = closeDropdown;

                function openDropdown(dropdown, btn) {
                    if (dropdown) {
                        dropdown.classList.remove('dropdown-hidden');
                    }
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'true');
                    }
                }
                window.openDropdown = openDropdown;

                function closeAllDropdowns(exceptDropdown) {
                    const profileDropdown = document.getElementById('profileDropdown');
                    const profileDropdownBtn = document.getElementById('profileDropdownBtn');
                    const notificationDropdown = document.getElementById('notificationDropdown');
                    const notificationBtn = document.getElementById('notificationBtn');

                    if (profileDropdown && profileDropdown !== exceptDropdown) {
                        closeDropdown(profileDropdown, profileDropdownBtn);
                    }
                    if (notificationDropdown && notificationDropdown !== exceptDropdown) {
                        closeDropdown(notificationDropdown, notificationBtn);
                    }
                }
                window.closeAllDropdowns = closeAllDropdowns;

                // ========== MODAL SYSTEM ==========
                function openModal(modal) {
                    if (!modal) return;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.classList.remove('modal-hidden');
                    document.body.style.overflow = 'hidden';
                }
                window.openModal = openModal;

                function closeModal(modal) {
                    if (!modal) return;
                    modal.classList.add('modal-hidden');
                    setTimeout(() => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        document.body.style.overflow = '';
                    }, 200);
                }
                window.closeModal = closeModal;

                // ========== TOAST SYSTEM ==========
                function showToast(type, message) {
                    const container = document.getElementById('toastContainer');
                    if (!container) return;
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
                window.showToast = showToast;

                // ========== LOADER SYSTEM ==========
                function showLoader() {
                    const pageLoader = document.getElementById('pageLoader');
                    if (pageLoader) {
                        pageLoader.classList.remove('hidden');
                        pageLoader.classList.add('flex');
                    }
                }
                window.showLoader = showLoader;

                function hideLoader() {
                    const pageLoader = document.getElementById('pageLoader');
                    if (pageLoader) {
                        pageLoader.classList.add('hidden');
                        pageLoader.classList.remove('flex');
                    }
                }
                window.hideLoader = hideLoader;

                // ========== ACTION DROPDOWNS & SELECTS ==========
                function closeAllActionDropdowns(except) {
                    document.querySelectorAll('.action-dropdown').forEach(d => {
                        if (d !== except && !d.classList.contains('dropdown-hidden')) {
                            d.classList.add('dropdown-hidden');
                        }
                    });
                }
                window.closeAllActionDropdowns = closeAllActionDropdowns;

                function closeAllCustomSelects(exceptDropdown) {
                    document.querySelectorAll('.select-dropdown, [id$="Dropdown"]').forEach(d => {
                        if (d !== exceptDropdown && !d.classList.contains('dropdown-hidden') && d.id !== 'notificationDropdown' && d.id !== 'profileDropdown' && !d.id.startsWith('globalMenuSearch')) {
                            d.classList.add('dropdown-hidden');
                        }
                    });
                }
                window.closeAllCustomSelects = closeAllCustomSelects;

                // ========== CHART HELPERS (global) ==========
                function getSalesChartInstance() {
                    if (window.salesChartInstance && typeof window.salesChartInstance.update === 'function') {
                        return window.salesChartInstance;
                    }
                    if (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function') {
                        const canvas = document.getElementById('salesChart');
                        if (canvas) {
                            const chart = Chart.getChart(canvas);
                            if (chart && typeof chart.update === 'function') {
                                return chart;
                            }
                        }
                    }
                    if (window.salesChart && typeof window.salesChart.update === 'function') {
                        return window.salesChart;
                    }
                    return null;
                }
                window.getSalesChartInstance = getSalesChartInstance;

                function getChartColors() {
                    const isDark = document.documentElement.classList.contains('dark');
                    return {
                        primary: isDark ? '#6366f1' : '#4f46e5',
                        primaryAlpha: isDark ? 'rgba(99,102,241,0.15)' : 'rgba(79,70,229,0.1)',
                        grid: isDark ? 'rgba(148,163,184,0.15)' : 'rgba(148,163,184,0.3)',
                        ticks: isDark ? '#94a3b8' : '#64748b'
                    };
                }
                window.getChartColors = getChartColors;

                function updateChartColors() {
                    const chart = getSalesChartInstance();
                    if (!chart) return;

                    try {
                        const colors = getChartColors();
                        if (chart.options && chart.options.scales) {
                            if (chart.options.scales.x && chart.options.scales.x.ticks) {
                                chart.options.scales.x.ticks.color = colors.ticks;
                            }
                            if (chart.options.scales.y) {
                                if (chart.options.scales.y.ticks) chart.options.scales.y.ticks.color = colors.ticks;
                                if (chart.options.scales.y.grid) chart.options.scales.y.grid.color = colors.grid;
                            }
                        }
                        if (chart.options && chart.options.plugins && chart.options.plugins.tooltip) {
                            chart.options.plugins.tooltip.backgroundColor = document.documentElement.classList.contains('dark') ? '#334155' : '#1e293b';
                        }
                        if (chart.data && chart.data.datasets && chart.data.datasets[0]) {
                            chart.data.datasets[0].borderColor = colors.primary;
                            chart.data.datasets[0].pointBackgroundColor = colors.primary;
                        }
                        if (typeof chart.update === 'function') {
                            chart.update();
                        }
                    } catch (e) {
                        console.warn('Could not update chart colors:', e);
                    }
                }
                window.updateChartColors = updateChartColors;

                // ========== AJAX PAGINATION & SCROLL PRESERVATION ==========
                let isAjaxPaginating = false;

                function loadPaginationPage(targetUrl, pushState = true, targetScrollY = null) {
                    if (isAjaxPaginating) return;
                    const currentMain = document.getElementById('mainContent') || document.querySelector('main');
                    if (!currentMain) {
                        window.location.href = targetUrl;
                        return;
                    }

                    const savedScrollY = targetScrollY !== null ? targetScrollY : window.scrollY;
                    isAjaxPaginating = true;
                    currentMain.style.transition = 'opacity 0.15s ease';
                    currentMain.style.opacity = '0.55';
                    currentMain.style.pointerEvents = 'none';

                    fetch(targetUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html, application/xhtml+xml, */*'
                        }
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Network error: ' + res.status);
                        return res.text();
                    })
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newMain = doc.getElementById('mainContent') || doc.querySelector('main');

                        if (!newMain) {
                            window.location.href = targetUrl;
                            return;
                        }

                        // 1. Dispatch navigating for cleanup
                        document.dispatchEvent(new CustomEvent('livewire:navigating'));

                        // 2. Replace main content
                        currentMain.replaceWith(newMain);

                        // 3. Re-execute script tags if present
                        newMain.querySelectorAll('script').forEach(oldScript => {
                            if (oldScript.type && oldScript.type !== 'text/javascript' && oldScript.type !== 'application/javascript') {
                                return;
                            }
                            const newScript = document.createElement('script');
                            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });

                        // 4. Update browser URL without reload
                        if (pushState) {
                            try {
                                history.pushState({ url: targetUrl, scrollY: savedScrollY }, '', targetUrl);
                            } catch (e) {}
                        }

                        if (doc.title) {
                            document.title = doc.title;
                        }

                        // 5. PRESERVE SCROLL POSITION: Do not jump to top ("tidak naik keatas")
                        window.scrollTo({ top: savedScrollY, behavior: 'instant' });
                        requestAnimationFrame(() => {
                            window.scrollTo({ top: savedScrollY, behavior: 'instant' });
                        });

                        // 6. Reinitialize icons
                        if (typeof window.initIcons === 'function') {
                            window.initIcons();
                        }

                        // 7. Reinitialize Alpine.js if present
                        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                            try {
                                window.Alpine.initTree(newMain);
                            } catch (e) {}
                        }

                        // 8. Dispatch events to re-bind page functions (modals, drawers, tables)
                        document.dispatchEvent(new CustomEvent('livewire:navigated'));
                        document.dispatchEvent(new CustomEvent('ajax:paginated', { detail: { url: targetUrl, scrollY: savedScrollY } }));
                    })
                    .catch(err => {
                        console.warn('Ajax pagination failed, falling back to full navigation:', err);
                        window.location.href = targetUrl;
                    })
                    .finally(() => {
                        isAjaxPaginating = false;
                        const activeMain = document.getElementById('mainContent') || document.querySelector('main');
                        if (activeMain) {
                            activeMain.style.opacity = '';
                            activeMain.style.pointerEvents = '';
                        }
                    });
                }
                window.loadPaginationPage = loadPaginationPage;

                // ========== GLOBAL EVENT DELEGATION (ATTACHED ONCE) ==========
                if (!window.__nexaAppListenersAttached) {
                    window.__nexaAppListenersAttached = true;

                    document.addEventListener('click', (e) => {
                        // 0. Intercept pagination links (No full reload & preserve scroll position)
                        const pageLink = e.target.closest('a[href*="page="]');
                        if (pageLink && !pageLink.hasAttribute('download') && !pageLink.target) {
                            const href = pageLink.getAttribute('href');
                            if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
                                try {
                                    const urlObj = new URL(pageLink.href, window.location.origin);
                                    if (urlObj.origin === window.location.origin) {
                                        const isPaginationElement = pageLink.closest(
                                            '#transactionsPagination, #catalogPagination, #stockPagination, [id*="Pagination"], [id*="pagination"], nav[aria-label], nav[role="navigation"], .pagination'
                                        );
                                        const isInsideMain = pageLink.closest('#mainContent, main');
                                        if (isPaginationElement || isInsideMain) {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            loadPaginationPage(urlObj.href, true);
                                            return;
                                        }
                                    }
                                } catch (err) {}
                            }
                        }
                        // 1. Theme Toggle Button
                        const themeBtn = e.target.closest('#themeToggleBtn');
                        if (themeBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            const isDark = document.documentElement.classList.contains('dark');
                            applyTheme(isDark ? 'light' : 'dark');
                            return;
                        }

                        // 2. Hamburger Button
                        const burgerBtn = e.target.closest('#hamburgerBtn');
                        if (burgerBtn) {
                            e.preventDefault();
                            openMobileSidebar();
                            return;
                        }

                        // 3. Close Sidebar Button
                        const closeBtn = e.target.closest('#closeSidebarBtn');
                        if (closeBtn) {
                            e.preventDefault();
                            closeMobileSidebar();
                            return;
                        }

                        // 4. Mobile Backdrop
                        if (e.target.id === 'mobileBackdrop') {
                            closeMobileSidebar();
                            return;
                        }

                        // 5. Desktop Collapse Sidebar Toggle
                        const collapseBtn = e.target.closest('#desktopCollapseBtn, #collapseSidebarBtn');
                        if (collapseBtn) {
                            e.preventDefault();
                            toggleDesktopCollapse();
                            return;
                        }

                        // 5b. Save sidebar scroll position on sidebar link clicks
                        if (e.target.closest('#sidebar a')) {
                            saveSidebarScroll();
                        }

                        // 6. Profile Dropdown Toggle
                        const profileBtn = e.target.closest('#profileDropdownBtn');
                        if (profileBtn) {
                            e.stopPropagation();
                            const profileDropdown = document.getElementById('profileDropdown');
                            if (profileDropdown) {
                                const willOpen = profileDropdown.classList.contains('dropdown-hidden');
                                closeAllDropdowns(profileDropdown);
                                if (willOpen) {
                                    openDropdown(profileDropdown, profileBtn);
                                } else {
                                    closeDropdown(profileDropdown, profileBtn);
                                }
                            }
                            return;
                        }

                        // 7. Notification Dropdown Toggle
                        const notifBtn = e.target.closest('#notificationBtn');
                        if (notifBtn) {
                            e.stopPropagation();
                            const notificationDropdown = document.getElementById('notificationDropdown');
                            if (notificationDropdown) {
                                const willOpen = notificationDropdown.classList.contains('dropdown-hidden');
                                closeAllDropdowns(notificationDropdown);
                                if (willOpen) {
                                    openDropdown(notificationDropdown, notifBtn);
                                } else {
                                    closeDropdown(notificationDropdown, notifBtn);
                                }
                            }
                            return;
                        }

                        // 8. Close Modal Buttons
                        const modalCloseBtn = e.target.closest('[data-modal-close]');
                        if (modalCloseBtn) {
                            const modal = modalCloseBtn.closest('.fixed.inset-0');
                            if (modal) closeModal(modal);
                            return;
                        }

                        // 9. Cloud subscription gate for visible-but-locked menu items.
                        // The server still enforces the same rule if a URL is opened directly.
                        const lockedSubscriptionItem = e.target.closest('[data-subscription-locked="true"]');
                        if (lockedSubscriptionItem) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (window.innerWidth < 768) {
                                closeMobileSidebar();
                            }
                            if (typeof window.openSubscriptionGate === 'function') {
                                window.openSubscriptionGate();
                            }
                            return;
                        }

                        // 10. Sidebar item roadmap check
                        const sidebarItem = e.target.closest('.sidebar-item');
                        if (sidebarItem) {
                            if (window.innerWidth < 768) {
                                closeMobileSidebar();
                            }
                            const hasRoute = sidebarItem.getAttribute('data-has-route');
                            if (hasRoute === 'false') {
                                e.preventDefault();
                                const label = sidebarItem.getAttribute('data-nav-label') || 'Modul';
                                showToast('info', `Modul ${label} akan tersedia pada pembaruan berikutnya.`);
                                return;
                            }
                        }

                        // 11. Toggle switches
                        const toggleSwitch = e.target.closest('.toggle-switch');
                        if (toggleSwitch) {
                            toggleSwitch.classList.toggle('active');
                            toggleSwitch.setAttribute('aria-checked', toggleSwitch.classList.contains('active'));
                            return;
                        }

                        // 12. Click outside closes dropdowns
                        const profileDropdown = document.getElementById('profileDropdown');
                        const notificationDropdown = document.getElementById('notificationDropdown');
                        if (profileDropdown && !profileDropdown.contains(e.target)) {
                            closeDropdown(profileDropdown, document.getElementById('profileDropdownBtn'));
                        }
                        if (notificationDropdown && !notificationDropdown.contains(e.target)) {
                            closeDropdown(notificationDropdown, document.getElementById('notificationBtn'));
                        }
                        closeAllCustomSelects(null);
                        closeAllActionDropdowns();
                    });

                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            const mobileBackdrop = document.getElementById('mobileBackdrop');
                            if (mobileBackdrop && !mobileBackdrop.classList.contains('hidden')) {
                                closeMobileSidebar();
                            }
                            closeAllDropdowns(null);
                            closeAllCustomSelects(null);
                            closeAllActionDropdowns();
                        }
                    });

                    window.addEventListener('resize', () => {
                        applySidebarStateForViewport();
                        const mobileBackdrop = document.getElementById('mobileBackdrop');
                        if (window.innerWidth >= 768 && mobileBackdrop && !mobileBackdrop.classList.contains('hidden')) {
                            closeMobileSidebar();
                        }
                    });

                    // MutationObserver for Lucide Icons
                    if (typeof MutationObserver !== 'undefined') {
                        const iconObserver = new MutationObserver(() => {
                            if (isUpdatingIcons) return;
                            if (document.querySelector('i[data-lucide]:not([data-lucide-rendered="failed"])')) {
                                if (iconDebounceTimer) clearTimeout(iconDebounceTimer);
                                iconDebounceTimer = setTimeout(() => {
                                    initIcons();
                                }, 80);
                            }
                        });
                        iconObserver.observe(document.documentElement, { childList: true, subtree: true });
                    }

                    window.addEventListener('popstate', (e) => {
                        const currentUrl = window.location.href;
                        if (currentUrl.includes('page=') || (e.state && e.state.url)) {
                            loadPaginationPage(currentUrl, false, e.state?.scrollY ?? null);
                        }
                    });
                }

                // ========== PRESERVE SIDEBAR SCROLL POSITION ==========
                function getSidebarNav() {
                    return document.getElementById('sidebarNav');
                }

                function saveSidebarScroll() {
                    const nav = getSidebarNav();
                    if (nav) {
                        try {
                            sessionStorage.setItem('sidebar_nav_scroll', String(nav.scrollTop));
                        } catch (e) {}
                    }
                }

                function restoreSidebarScroll() {
                    const nav = getSidebarNav();
                    if (!nav) return;

                    // Bind scroll event once per container
                    if (!nav.dataset.scrollBound) {
                        nav.dataset.scrollBound = '1';
                        let scrollTimer = null;
                        nav.addEventListener('scroll', () => {
                            if (scrollTimer) clearTimeout(scrollTimer);
                            scrollTimer = setTimeout(saveSidebarScroll, 60);
                        }, { passive: true });
                    }

                    const savedScroll = sessionStorage.getItem('sidebar_nav_scroll');
                    if (savedScroll !== null) {
                        const top = parseInt(savedScroll, 10);
                        if (!isNaN(top)) {
                            nav.scrollTop = top;
                            requestAnimationFrame(() => {
                                nav.scrollTop = top;
                            });
                        }
                    } else {
                        // Fallback: If no saved scroll, keep active item visible
                        const activeItem = nav.querySelector('[aria-current="page"], .sidebar-active-indicator');
                        if (activeItem) {
                            activeItem.scrollIntoView({ block: 'nearest' });
                        }
                    }
                }

                // ========== IDEMPOTENT RUN ON EACH PAGE LOAD / NAVIGATION ==========
                function onPageReady() {
                    initIcons();
                    applyTheme(getStoredTheme());
                    applySidebarStateForViewport();
                    closeAllDropdowns(null);
                    restoreSidebarScroll();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', onPageReady);
                } else {
                    onPageReady();
                }

                document.addEventListener('livewire:navigated', onPageReady);

                // ========== LIVEWIRE INIT & HOOKS ==========
                function registerLivewireHooks() {
                    if (typeof Livewire !== 'undefined' && !window.__livewireHooksRegistered) {
                        window.__livewireHooksRegistered = true;
                        Livewire.hook('morph.updating', ({ el, toEl }) => {
                            if (el === document.documentElement) {
                                const isDark = getStoredTheme() === 'dark';
                                toEl.classList.toggle('dark', isDark);
                                toEl.classList.toggle('light', !isDark);
                            }
                        });
                        Livewire.hook('morph.updated', () => initIcons());
                        Livewire.hook('commit', () => initIcons());
                    }
                }

                if (typeof Livewire !== 'undefined') {
                    registerLivewireHooks();
                } else {
                    document.addEventListener('livewire:init', registerLivewireHooks);
                }

                document.addEventListener('livewire:navigating', () => {
                    window.__activeTheme = getStoredTheme();
                    saveSidebarScroll();
                });

                window.addEventListener('beforeunload', saveSidebarScroll);
            })();
        </script>

        @stack('scripts')

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
