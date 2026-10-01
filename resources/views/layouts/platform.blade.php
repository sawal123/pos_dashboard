<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300 min-h-screen">

        {{-- ==================== PAGE LOADER ==================== --}}
        <x-ui.page-loader />

        {{-- ==================== PLATFORM ADMIN SIDEBAR ==================== --}}
        <aside
            id="sidebar"
            class="fixed top-0 left-0 z-50 h-full w-72 bg-white dark:bg-slate-900 border-r border-slate-200/90 dark:border-slate-800 sidebar-transition flex flex-col -translate-x-full md:translate-x-0 select-none shadow-sm"
            aria-label="Navigasi Platform Admin"
        >
            {{-- Top Branding Header --}}
            <div class="h-16 px-5 border-b border-slate-200/90 dark:border-slate-800 flex items-center justify-between shrink-0">
                <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3 group focus:outline-none">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white flex items-center justify-center font-black text-sm shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform shrink-0">
                        P
                    </div>
                    <div class="sidebar-label sidebar-logo-text flex flex-col leading-tight">
                        <span class="font-extrabold text-sm tracking-tight text-slate-900 dark:text-white">
                            Nexa<span class="text-indigo-600 dark:text-indigo-400">POS</span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                            Platform Admin
                        </span>
                    </div>
                </a>

                {{-- Mobile Close Button --}}
                <button
                    type="button"
                    id="closeSidebarBtn"
                    class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    aria-label="Tutup navigasi"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Platform Navigation Menu --}}
            <nav class="flex-1 overflow-y-auto px-3.5 py-4 space-y-6" aria-label="Menu Utama Platform Admin">
                <div>
                    <div class="sidebar-section-label px-3 mb-2 text-[10px] font-bold tracking-wider text-slate-400 dark:text-slate-500 uppercase">
                        Platform Admin
                    </div>
                    <ul class="space-y-1">
                        @php
                            $isOverview = request()->routeIs('platform.dashboard') || request()->routeIs('platform.dashboard.alias');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.dashboard') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isOverview ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Overview"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isOverview ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Overview</span>
                                @if($isOverview)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isBusinesses = request()->routeIs('platform.businesses.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.businesses.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isBusinesses ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Bisnis"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isBusinesses ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="building-2" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Bisnis</span>
                                @if($isBusinesses)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isUsers = request()->routeIs('platform.users.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.users.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isUsers ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Pengguna"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isUsers ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Pengguna</span>
                                @if($isUsers)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isSubscriptions = request()->routeIs('platform.subscriptions.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.subscriptions.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isSubscriptions ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Langganan"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isSubscriptions ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Langganan</span>
                                @if($isSubscriptions)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isSubscriptionPlans = request()->routeIs('platform.subscription-plans.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.subscription-plans.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isSubscriptionPlans ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Paket &amp; Harga"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isSubscriptionPlans ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="tags" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Paket &amp; Harga</span>
                                @if($isSubscriptionPlans)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isPayments = request()->routeIs('platform.payments.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.payments.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isPayments ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Pembayaran"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isPayments ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Pembayaran</span>
                                @if($isPayments)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isDevices = request()->routeIs('platform.devices.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.devices.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isDevices ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Perangkat"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isDevices ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="smartphone" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Perangkat</span>
                                @if($isDevices)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            {{-- Sidebar Footer / Desktop Collapse Toggle --}}
            <div class="p-3 border-t border-slate-200/90 dark:border-slate-800 shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
                <button
                    type="button"
                    id="collapseSidebarBtn"
                    class="hidden md:flex w-full items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors"
                    data-tooltip-right="Ciutkan Sidebar"
                    aria-expanded="true"
                >
                    <i id="collapseIcon" data-lucide="chevrons-left" class="w-4 h-4 shrink-0"></i>
                    <span id="collapseLabel" class="sidebar-item-label">Ciutkan</span>
                </button>
            </div>
        </aside>

        {{-- ==================== MOBILE BACKDROP ==================== --}}
        <div id="mobileBackdrop" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm hidden md:hidden"></div>

        {{-- ==================== MAIN WRAPPER ==================== --}}
        <div id="mainWrapper" class="md:ml-72 transition-all duration-300 min-h-screen flex flex-col">

            {{-- ==================== TOPBAR ==================== --}}
            <header id="navbar" class="sticky top-0 z-30 h-16 bg-white/85 dark:bg-slate-900/85 backdrop-blur-xl border-b border-slate-200/90 dark:border-slate-800 flex items-center justify-between px-4 md:px-6 transition-colors duration-200">
                {{-- Left: Toggle & Breadcrumb --}}
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
                    <button
                        type="button"
                        id="hamburgerBtn"
                        class="md:hidden p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-label="Buka navigasi sidebar"
                        aria-expanded="false"
                    >
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>

                    <button
                        type="button"
                        id="desktopCollapseBtn"
                        class="hidden md:flex p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-label="Ciutkan atau bentangkan sidebar"
                        data-tooltip="Ciutkan Sidebar"
                        aria-expanded="true"
                    >
                        <i data-lucide="panel-left" class="w-5 h-5"></i>
                    </button>

                    {{-- Breadcrumb --}}
                    <nav class="flex items-center gap-1.5 text-xs md:text-sm font-medium" aria-label="Breadcrumb">
                        <span class="text-slate-400 dark:text-slate-500">Platform</span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-600"></i>
                        <span class="text-slate-800 dark:text-slate-100 font-semibold" aria-current="page">
                            {{ $title ?? 'Overview' }}
                        </span>
                    </nav>
                </div>

                {{-- Right: Status & Actions --}}
                <div class="flex items-center gap-2 shrink-0">
                    {{-- Platform Admin Mode Badge --}}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl border text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/50 border-indigo-200/70 dark:border-indigo-800/50 text-indigo-700 dark:text-indigo-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                        <span class="hidden sm:inline">Platform Admin</span>
                    </span>

                    {{-- Theme Toggle --}}
                    <button
                        type="button"
                        id="themeToggleBtn"
                        class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        aria-label="Ganti mode tema"
                        data-tooltip="Mode Tampilan"
                    >
                        <i data-lucide="moon" class="w-4 h-4 sm:w-5 sm:h-5 hidden dark:inline" id="moonIcon"></i>
                        <i data-lucide="sun" class="w-4 h-4 sm:w-5 sm:h-5 inline dark:hidden" id="sunIcon"></i>
                    </button>

                    {{-- User Profile Dropdown --}}
                    @php
                        $user = auth()->user();
                        $userName = $user ? $user->name : 'Platform Admin';
                        $userEmail = $user ? $user->email : 'admin@platform';
                        $initials = ($user && method_exists($user, 'initials')) ? $user->initials() : 'PA';
                    @endphp
                    <div class="relative">
                        <button
                            type="button"
                            id="profileDropdownBtn"
                            class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-label="Menu Pengguna: {{ $userName }}"
                        >
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-700 text-white flex items-center justify-center text-xs font-bold shadow-xs shrink-0">
                                {{ $initials }}
                            </div>
                            <div class="hidden lg:block text-left">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 leading-tight truncate max-w-[120px]">
                                    {{ $userName }}
                                </p>
                                <p class="text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold leading-tight">
                                    Super Admin
                                </p>
                            </div>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 hidden lg:inline shrink-0"></i>
                        </button>

                        {{-- Dropdown Menu --}}
                        <div
                            id="profileDropdown"
                            class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 rounded-2xl shadow-xl shadow-slate-200/40 dark:shadow-slate-950/60 overflow-hidden z-50 py-1"
                            role="menu"
                            aria-orientation="vertical"
                        >
                            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/80">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $userName }}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $userEmail }}</p>
                                <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 text-[10px] font-semibold">
                                    Platform Super Admin
                                </span>
                            </div>

                            <div class="p-1 border-t border-slate-100 dark:border-slate-700/80">
                                @if(Route::has('logout'))
                                    <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-xl transition-colors font-medium text-left"
                                            role="menuitem"
                                        >
                                            <i data-lucide="log-out" class="w-3.5 h-3.5 text-rose-500 dark:text-rose-400"></i>
                                            Keluar (Logout)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {{-- ==================== PAGE CONTENT ==================== --}}
            <main class="flex-1 p-4 md:p-8 max-w-7xl w-full mx-auto">
                {{ $slot }}
            </main>
        </div>

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
                    sidebar.classList.remove('sidebar-collapsed', 'w-20');
                    sidebar.classList.add('w-72');
                    mainWrapper.classList.remove('md:ml-20');
                    mainWrapper.classList.add('md:ml-72');
                } else {
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

            applySidebarStateForViewport();
            window.addEventListener('resize', () => {
                applySidebarStateForViewport();
                if (window.innerWidth >= 768 && mobileBackdrop && !mobileBackdrop.classList.contains('hidden')) {
                    closeMobileSidebar();
                }
            });

            // ========== PROFILE DROPDOWN ==========
            const profileDropdownBtn = document.getElementById('profileDropdownBtn');
            const profileDropdown = document.getElementById('profileDropdown');

            if (profileDropdownBtn && profileDropdown) {
                profileDropdownBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = profileDropdown.classList.contains('dropdown-hidden');
                    if (isHidden) {
                        profileDropdown.classList.remove('dropdown-hidden');
                        profileDropdownBtn.setAttribute('aria-expanded', 'true');
                    } else {
                        profileDropdown.classList.add('dropdown-hidden');
                        profileDropdownBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!profileDropdown.contains(e.target) && !profileDropdownBtn.contains(e.target)) {
                        profileDropdown.classList.add('dropdown-hidden');
                        profileDropdownBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        </script>
    </body>
</html>
