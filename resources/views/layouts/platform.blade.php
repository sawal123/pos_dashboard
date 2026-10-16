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
                            $isAlerts = request()->routeIs('platform.alerts.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.alerts.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isAlerts ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Alert Operasional"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isAlerts ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="bell" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Alert Operasional</span>
                                @if($isAlerts)
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
                        @php
                            $isSync = request()->routeIs('platform.sync.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.sync.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isSync ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Sinkronisasi"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isSync ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Sinkronisasi</span>
                                @if($isSync)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isBackups = request()->routeIs('platform.backups.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.backups.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isBackups ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Backup Cloud"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isBackups ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="cloud" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Backup Cloud</span>
                                @if($isBackups)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isAnalytics = request()->routeIs('platform.analytics.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.analytics.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isAnalytics ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Analitik"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isAnalytics ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Analitik</span>
                                @if($isAnalytics)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isRevenue = request()->routeIs('platform.revenue.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.revenue.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isRevenue ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Revenue &amp; Billing"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isRevenue ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Revenue &amp; Billing</span>
                                @if($isRevenue)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isAuditLogs = request()->routeIs('platform.audit-logs.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.audit-logs.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isAuditLogs ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Audit Log"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isAuditLogs ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Audit Log</span>
                                @if($isAuditLogs)
                                    <span class="sidebar-active-indicator w-1.5 h-4 rounded-full bg-indigo-600 dark:bg-indigo-400 shrink-0"></span>
                                @endif
                            </a>
                        </li>
                        @php
                            $isSettings = request()->routeIs('platform.settings.*');
                        @endphp
                        <li>
                            <a
                                href="{{ route('platform.settings.index') }}"
                                class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-xs transition-all relative {{ $isSettings ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}"
                                data-tooltip-right="Pengaturan Platform"
                            >
                                <span class="shrink-0 flex items-center justify-center w-5 h-5 {{ $isSettings ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' }}">
                                    <i data-lucide="sliders" class="w-4 h-4"></i>
                                </span>
                                <span class="sidebar-item-label truncate flex-1 tracking-tight">Pengaturan Platform</span>
                                @if($isSettings)
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
                        data-tooltip-placement="bottom"
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
                        data-tooltip-placement="bottom"
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
                            class="dropdown-panel dropdown-hidden absolute right-0 top-full mt-2 w-56 bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 rounded-2xl shadow-xl shadow-slate-200/40 dark:shadow-slate-950/60 overflow-hidden z-50 py-1"
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
            (function() {
                // ========== INITIALIZE LUCIDE ICONS ==========
                function initIcons() {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
                window.initIcons = initIcons;

                // ========== THEME MANAGEMENT ==========
                function applyTheme(theme) {
                    const html = document.documentElement;
                    const moonIcon = document.getElementById('moonIcon');
                    const sunIcon = document.getElementById('sunIcon');
                    if (theme === 'dark') {
                        html.classList.add('dark');
                        html.classList.remove('light');
                        if (moonIcon) moonIcon.style.display = 'inline';
                        if (sunIcon) sunIcon.style.display = 'none';
                    } else {
                        html.classList.remove('dark');
                        html.classList.add('light');
                        if (moonIcon) moonIcon.style.display = 'none';
                        if (sunIcon) sunIcon.style.display = 'inline';
                    }
                    try {
                        localStorage.setItem('nexa-theme', theme);
                    } catch (e) {}
                }
                window.applyTheme = applyTheme;

                function getStoredTheme() {
                    return localStorage.getItem('nexa-theme') || 'light';
                }
                window.getStoredTheme = getStoredTheme;

                // ========== SIDEBAR MANAGEMENT & PERSISTENCE ==========
                function getStoredSidebarCollapse() {
                    return localStorage.getItem('nexa-sidebar-collapsed') === 'true';
                }

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

                // ========== PROFILE DROPDOWN ==========
                function closeProfileDropdown() {
                    const dropdown = document.getElementById('profileDropdown');
                    const btn = document.getElementById('profileDropdownBtn');
                    if (dropdown && !dropdown.classList.contains('dropdown-hidden')) {
                        dropdown.classList.add('dropdown-hidden');
                    }
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                    }
                }

                function toggleProfileDropdown() {
                    const dropdown = document.getElementById('profileDropdown');
                    const btn = document.getElementById('profileDropdownBtn');
                    if (!dropdown) return;
                    const isHidden = dropdown.classList.contains('dropdown-hidden');
                    if (isHidden) {
                        dropdown.classList.remove('dropdown-hidden');
                        if (btn) btn.setAttribute('aria-expanded', 'true');
                    } else {
                        dropdown.classList.add('dropdown-hidden');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    }
                }

                // ========== GLOBAL EVENT DELEGATION (ATTACHED ONCE) ==========
                if (!window.__nexaPlatformListenersAttached) {
                    window.__nexaPlatformListenersAttached = true;

                    document.addEventListener('click', (e) => {
                        // 1. Theme Toggle Button
                        const themeBtn = e.target.closest('#themeToggleBtn');
                        if (themeBtn) {
                            e.preventDefault();
                            const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                            applyTheme(current === 'dark' ? 'light' : 'dark');
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

                        // 6. Profile Dropdown Toggle
                        const profileBtn = e.target.closest('#profileDropdownBtn');
                        if (profileBtn) {
                            e.stopPropagation();
                            toggleProfileDropdown();
                            return;
                        }

                        // 7. Click outside closes profile dropdown
                        const profileDropdown = document.getElementById('profileDropdown');
                        if (profileDropdown && !profileDropdown.contains(e.target)) {
                            closeProfileDropdown();
                        }
                    });

                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            const mobileBackdrop = document.getElementById('mobileBackdrop');
                            if (mobileBackdrop && !mobileBackdrop.classList.contains('hidden')) {
                                closeMobileSidebar();
                            }
                            closeProfileDropdown();
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
                            if (document.querySelector('i[data-lucide]')) {
                                initIcons();
                            }
                        });
                        iconObserver.observe(document.documentElement, { childList: true, subtree: true });
                    }
                }

                // ========== IDEMPOTENT RUN ON EACH PAGE LOAD / NAVIGATION ==========
                function onPageReady() {
                    initIcons();
                    applyTheme(getStoredTheme());
                    applySidebarStateForViewport();
                    closeProfileDropdown();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', onPageReady);
                } else {
                    onPageReady();
                }

                document.addEventListener('livewire:navigated', onPageReady);
                document.addEventListener('livewire:init', () => {
                    if (typeof Livewire !== 'undefined') {
                        Livewire.hook('morph.updated', () => initIcons());
                        Livewire.hook('commit', () => initIcons());
                    }
                });
            })();
        </script>
    </body>
</html>
