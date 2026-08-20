{{-- ==================== SIDEBAR ==================== --}}
<aside id="sidebar" class="fixed top-0 left-0 z-50 h-full w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 sidebar-transition flex flex-col -translate-x-full md:translate-x-0" aria-label="Sidebar">

    {{-- Sidebar Header --}}
    <div class="flex items-center justify-between h-16 px-5 border-b border-slate-200 dark:border-slate-800 flex-shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                <i data-lucide="zap" class="w-5 h-5"></i>
            </div>
            <span class="sidebar-logo-text font-bold text-lg tracking-tight text-slate-900 dark:text-white">Nexa<span class="text-indigo-600 dark:text-indigo-400">POS</span></span>
        </div>
        <button id="closeSidebarBtn" class="md:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" aria-label="Close sidebar">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- Sidebar Menu --}}
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-6">
        {{-- MAIN --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">Main</p>
            <a href="{{ route('dashboard') }}" wire:navigate class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 transition-all hover:bg-indigo-100 dark:hover:bg-indigo-900/40" data-tooltip-right="Dashboard">
                <i data-lucide="layout-dashboard" class="w-5 h-5 flex-shrink-0"></i>
                <span>Dashboard</span>
            </a>
        </div>

        {{-- MASTER DATA --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">Master Data</p>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Products">
                <i data-lucide="package" class="w-5 h-5 flex-shrink-0"></i>
                <span>Products</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Categories">
                <i data-lucide="tags" class="w-5 h-5 flex-shrink-0"></i>
                <span>Categories</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Customers">
                <i data-lucide="users" class="w-5 h-5 flex-shrink-0"></i>
                <span>Customers</span>
            </a>
        </div>

        {{-- SALES --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">Sales</p>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Transactions">
                <i data-lucide="receipt" class="w-5 h-5 flex-shrink-0"></i>
                <span>Transactions</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Expenses">
                <i data-lucide="wallet" class="w-5 h-5 flex-shrink-0"></i>
                <span>Expenses</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Shifts">
                <i data-lucide="clock" class="w-5 h-5 flex-shrink-0"></i>
                <span>Shifts</span>
            </a>
        </div>

        {{-- INVENTORY --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">Inventory</p>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Stock">
                <i data-lucide="boxes" class="w-5 h-5 flex-shrink-0"></i>
                <span>Stock</span>
            </a>
        </div>

        {{-- STORE --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">Store</p>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Outlets">
                <i data-lucide="store" class="w-5 h-5 flex-shrink-0"></i>
                <span>Outlets</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Cashiers">
                <i data-lucide="user-cog" class="w-5 h-5 flex-shrink-0"></i>
                <span>Cashiers</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Devices">
                <i data-lucide="monitor-smartphone" class="w-5 h-5 flex-shrink-0"></i>
                <span>Devices</span>
            </a>
        </div>

        {{-- CLOUD --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">Cloud</p>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Synchronization">
                <i data-lucide="refresh-cw" class="w-5 h-5 flex-shrink-0"></i>
                <span>Synchronization</span>
            </a>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Subscription">
                <i data-lucide="credit-card" class="w-5 h-5 flex-shrink-0"></i>
                <span>Subscription</span>
            </a>
        </div>

        {{-- SYSTEM --}}
        <div>
            <p class="sidebar-section-label text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 mb-2">System</p>
            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" data-tooltip-right="Settings">
                <i data-lucide="settings" class="w-5 h-5 flex-shrink-0"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    {{-- Cloud Plan Card --}}
    <div class="sidebar-cloud-card px-4 pb-4 flex-shrink-0">
        <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-950/60 dark:to-indigo-900/40 border border-indigo-100 dark:border-indigo-800/50 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">Cloud Plan</span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold uppercase">Active</span>
            </div>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Cloud sync is active</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Next billing: 20 Sep 2026</p>
            <button id="managePlanBtn" class="mt-3 w-full py-1.5 px-3 rounded-lg bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 text-xs font-medium hover:bg-indigo-50 dark:hover:bg-indigo-900/40 transition-colors">Manage Plan</button>
        </div>
    </div>

    {{-- Collapse Button --}}
    <button id="collapseSidebarBtn" class="hidden md:flex items-center justify-center gap-2 py-3 border-t border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors flex-shrink-0" aria-label="Collapse sidebar">
        <i data-lucide="chevrons-left" class="w-5 h-5" id="collapseIcon"></i>
        <span class="sidebar-label text-sm font-medium">Collapse</span>
    </button>
</aside>
