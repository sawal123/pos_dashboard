<x-layouts::app :title="__('Dashboard')">
    <main id="mainContent" class="p-4 md:p-6 lg:p-8 space-y-6">

        {{-- ==================== DASHBOARD HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Dashboard</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Welcome back, {{ auth()->check() ? auth()->user()->name : 'Alex' }}. Here is what's happening with your business today.</p>
            </div>
            <div class="flex gap-2">
                <button id="exportReportBtn" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Export Report
                </button>
                <button id="newTransactionBtn" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium transition-colors flex items-center gap-2 shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> New Transaction
                </button>
            </div>
        </div>

        {{-- ==================== STATS CARDS ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Today's Sales --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-200 dark:hover:border-indigo-700 transition-all duration-200 shadow-sm hover:shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Today's Sales</span>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center"><i data-lucide="trending-up" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i></div>
                </div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Rp 12.450.000</p>
                <p class="text-sm text-green-600 dark:text-green-400 font-medium mt-1 flex items-center gap-1"><i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i> +12.5%</p>
            </div>

            {{-- Transactions --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-200 dark:hover:border-indigo-700 transition-all duration-200 shadow-sm hover:shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Transactions</span>
                    <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center"><i data-lucide="receipt" class="w-4 h-4 text-blue-600 dark:text-blue-400"></i></div>
                </div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">324</p>
                <p class="text-sm text-green-600 dark:text-green-400 font-medium mt-1 flex items-center gap-1"><i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i> +8.2%</p>
            </div>

            {{-- Products --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-200 dark:hover:border-indigo-700 transition-all duration-200 shadow-sm hover:shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Products</span>
                    <div class="w-9 h-9 rounded-xl bg-green-50 dark:bg-green-950/50 flex items-center justify-center"><i data-lucide="package" class="w-4 h-4 text-green-600 dark:text-green-400"></i></div>
                </div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">1,248</p>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">All categories</p>
            </div>

            {{-- Pending Sync --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 hover:border-indigo-200 dark:hover:border-indigo-700 transition-all duration-200 shadow-sm hover:shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pending Sync</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center"><i data-lucide="refresh-cw" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i></div>
                </div>
                <p class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">12</p>
                <p class="text-sm text-amber-600 dark:text-amber-400 font-medium mt-1 flex items-center gap-1"><i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Needs attention</p>
            </div>
        </div>

        {{-- ==================== CHART + CLOUD STATUS ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Sales Overview Chart --}}
            <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-white text-lg">Sales Overview</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Revenue performance over time</p>
                    </div>
                    {{-- Period Select --}}
                    <div class="relative" id="periodSelectWrapper">
                        <button id="periodSelectBtn" class="flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/70 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                            <span id="periodSelectLabel">7 Days</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </button>
                        <div id="periodSelectDropdown" class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-40 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg overflow-hidden z-50">
                            <button class="select-option selected w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="7 Days">7 Days</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="30 Days">30 Days</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="3 Months">3 Months</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="1 Year">1 Year</button>
                        </div>
                    </div>
                </div>
                <div class="relative h-64">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            {{-- Cloud Status --}}
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm flex flex-col">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center"><i data-lucide="cloud" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i></div>
                    <h2 class="font-semibold text-slate-900 dark:text-white">Cloud Status</h2>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">Online</span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">· Last sync: 2 minutes ago</span>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600 p-3 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Pending records</span>
                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400">12</span>
                    </div>
                    <div class="mt-2 w-full bg-slate-200 dark:bg-slate-600 rounded-full h-1.5"><div class="bg-amber-500 h-1.5 rounded-full" style="width: 15%;"></div></div>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600 p-3 mb-4 flex-1">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Badge</span>
                    <div class="mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 text-xs font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Synced
                    </div>
                </div>
                <button id="syncNowBtn" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium transition-colors flex items-center justify-center gap-2 shadow-sm">
                    <i data-lucide="refresh-cw" class="w-4 h-4" id="syncIcon"></i>
                    <span id="syncBtnText">Sync Now</span>
                </button>
            </div>
        </div>

        {{-- ==================== RECENT TRANSACTIONS TABLE ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-semibold text-slate-900 dark:text-white text-lg">Recent Transactions</h2>
            </div>

            {{-- Table Toolbar --}}
            <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/50 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="relative flex-1 min-w-[200px] max-w-xs">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="tableSearchInput" placeholder="Search transactions..." class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                    </div>
                    <div class="relative" id="statusFilterWrapper">
                        <button id="statusFilterBtn" class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/70 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                            <span id="statusFilterLabel">Status</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </button>
                        <div id="statusFilterDropdown" class="dropdown-panel dropdown-hidden absolute left-0 mt-2 w-40 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg overflow-hidden z-50">
                            <button class="select-option selected w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="All">All</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Synced">Synced</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Pending">Pending</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Failed">Failed</button>
                        </div>
                    </div>
                    <div class="relative" id="paymentFilterWrapper">
                        <button id="paymentFilterBtn" class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/70 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                            <span id="paymentFilterLabel">Payment</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </button>
                        <div id="paymentFilterDropdown" class="dropdown-panel dropdown-hidden absolute left-0 mt-2 w-40 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg overflow-hidden z-50">
                            <button class="select-option selected w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="All">All</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Cash">Cash</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="QRIS">QRIS</button>
                            <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Card">Card</button>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button id="filterBtn" class="px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors flex items-center gap-1.5"><i data-lucide="filter" class="w-4 h-4"></i> Filter</button>
                    <button id="tableExportBtn" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-1.5"><i data-lucide="download" class="w-4 h-4"></i> Export</button>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 text-left">
                            <th class="px-4 py-3 w-10"><input type="checkbox" id="selectAllCheckbox" class="custom-checkbox" aria-label="Select all"></th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Invoice</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Customer</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Cashier</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Payment</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">Total</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Sync Status</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300">Date</th>
                            <th class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        {{-- Rows injected via JS --}}
                    </tbody>
                </table>
            </div>

            {{-- Empty State --}}
            <div id="tableEmptyState" class="hidden px-5 py-16 text-center">
                <div class="mx-auto w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-4"><i data-lucide="receipt" class="w-7 h-7 text-slate-400 dark:text-slate-500"></i></div>
                <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-200">No transactions yet</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Your transactions will appear here once you start selling.</p>
                <button class="mt-4 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors">Create Transaction</button>
            </div>

            {{-- Pagination --}}
            <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-slate-500 dark:text-slate-400" id="paginationInfo">Showing 1 to 8 of 128 results</p>
                <div class="flex items-center gap-1" id="paginationNav">
                    <button class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Previous</button>
                    <button class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm font-medium">1</button>
                    <button class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">2</button>
                    <button class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">3</button>
                    <span class="px-1 text-slate-400">...</span>
                    <button class="px-3 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">13</button>
                    <button class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Next</button>
                </div>
            </div>
        </div>

        {{-- ==================== BUSINESS OVERVIEW ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-white text-lg">Business Overview</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Summary of your store performance</p>
                </div>
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-medium"><i data-lucide="bar-chart-3" class="w-4 h-4"></i> Last updated today</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600"><p class="text-xs text-slate-500 dark:text-slate-400 font-medium uppercase">Gross Revenue</p><p class="text-xl font-bold text-slate-900 dark:text-white mt-1">Rp 89.2M</p></div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600"><p class="text-xs text-slate-500 dark:text-slate-400 font-medium uppercase">Net Profit</p><p class="text-xl font-bold text-slate-900 dark:text-white mt-1">Rp 32.7M</p></div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600"><p class="text-xs text-slate-500 dark:text-slate-400 font-medium uppercase">Avg Order Value</p><p class="text-xl font-bold text-slate-900 dark:text-white mt-1">Rp 42.500</p></div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-600"><p class="text-xs text-slate-500 dark:text-slate-400 font-medium uppercase">Active Outlets</p><p class="text-xl font-bold text-slate-900 dark:text-white mt-1">6</p></div>
            </div>
        </div>

        {{-- ==================== UI COMPONENTS SECTION ==================== --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
            <h2 class="font-semibold text-slate-900 dark:text-white text-lg mb-4">UI Components</h2>
            {{-- Tab Navigation --}}
            <div class="flex flex-wrap gap-2 mb-5 border-b border-slate-200 dark:border-slate-700 pb-4">
                <button class="component-tab active px-4 py-2 rounded-xl text-sm font-medium bg-indigo-600 text-white transition-colors" data-tab="buttons">Buttons</button>
                <button class="component-tab px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-tab="forms">Forms</button>
                <button class="component-tab px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-tab="badges">Badges</button>
                <button class="component-tab px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-tab="loaders">Loaders</button>
                <button class="component-tab px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-tab="upload">Upload</button>
                <button class="component-tab px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-tab="modals">Modals</button>
            </div>

            {{-- Tab: Buttons --}}
            <div id="tab-buttons" class="component-tab-content space-y-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <button class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-medium transition-all shadow-sm">Save Changes</button>
                    <button class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium transition-all">Cancel</button>
                    <button class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition-all">View Details</button>
                    <button class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-sm font-medium transition-all shadow-sm">Delete</button>
                    <button class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 active:bg-green-800 text-white text-sm font-medium transition-all shadow-sm">Approve</button>
                </div>
                <div class="flex flex-wrap gap-3 items-center">
                    <button class="p-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all" aria-label="Edit" data-tooltip="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                    <button class="p-2 rounded-xl border border-slate-300 dark:border-slate-600 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-all" aria-label="Delete" data-tooltip="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    <button class="p-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all" aria-label="More menu" data-tooltip="More"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                    <button class="p-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all" aria-label="Refresh" data-tooltip="Refresh"><i data-lucide="refresh-cw" class="w-4 h-4"></i></button>
                </div>
                <div class="flex flex-wrap gap-3 items-center">
                    <button id="loadingDemoBtn" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-all flex items-center gap-2 shadow-sm"><span id="loadingDemoText">Save Changes</span></button>
                    <button disabled class="px-4 py-2 rounded-xl bg-slate-300 dark:bg-slate-600 text-slate-500 dark:text-slate-400 text-sm font-medium cursor-not-allowed">Disabled</button>
                </div>
            </div>

            {{-- Tab: Forms --}}
            <div id="tab-forms" class="component-tab-content hidden space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product Name</label>
                        <input type="text" placeholder="e.g. Coffee Latte" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Help text for product name.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product Name</label>
                        <input type="text" value="" placeholder="Required" class="w-full px-3 py-2 rounded-xl border border-red-400 dark:border-red-500 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-400 transition-all">
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1 flex items-center gap-1"><i data-lucide="alert-circle" class="w-3 h-3"></i> Product name is required.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Selling Price</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500 dark:text-slate-400">Rp</span>
                            <input type="text" placeholder="0" class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Search</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" placeholder="Search..." class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                        <div class="relative">
                            <input type="password" id="passwordInput" placeholder="••••••••" class="w-full px-3 py-2 pr-10 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                            <button id="passwordToggleBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors" aria-label="Toggle password visibility"><i data-lucide="eye" class="w-4 h-4" id="eyeIcon"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                        <textarea rows="3" placeholder="Product description..." class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Date</label>
                        <input type="date" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><input type="checkbox" class="custom-checkbox" checked> Include in stock</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><input type="checkbox" class="custom-checkbox"> Track inventory</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><input type="checkbox" class="custom-checkbox" disabled> Disabled option</label>
                    </div>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><input type="radio" name="demoRadio" class="custom-radio" checked> Active</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><input type="radio" name="demoRadio" class="custom-radio"> Inactive</label>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-700 dark:text-slate-300">Active</span>
                            <div class="toggle-switch active" id="demoToggle" role="switch" aria-checked="true" tabindex="0"></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-700 dark:text-slate-300">Inactive</span>
                            <div class="toggle-switch" id="demoToggle2" role="switch" aria-checked="false" tabindex="0"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tab: Badges --}}
            <div id="tab-badges" class="component-tab-content hidden">
                <div class="flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Success</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/50 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-red-100 dark:bg-red-900/50 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Failed</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-100 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Cloud</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Free</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-100 dark:bg-amber-900/50 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Draft</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Paid</span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-100 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-400 text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Refunded</span>
                </div>
            </div>

            {{-- Tab: Loaders --}}
            <div id="tab-loaders" class="component-tab-content hidden space-y-6">
                <div class="flex flex-wrap gap-4 items-center">
                    <div class="w-8 h-8 border-4 border-indigo-200 dark:border-indigo-900 border-t-indigo-600 dark:border-t-indigo-400 rounded-full animate-spin"></div>
                    <div class="w-6 h-6 border-2 border-slate-200 dark:border-slate-600 border-t-slate-500 rounded-full animate-spin"></div>
                    <div class="w-10 h-10 border-4 border-green-200 dark:border-green-900 border-t-green-600 dark:border-t-green-400 rounded-full animate-spin"></div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-3">Table Skeleton</h3>
                    <div class="space-y-2">
                        <div class="flex gap-3"><div class="skeleton h-4 w-8 rounded"></div><div class="skeleton h-4 w-32 rounded"></div><div class="skeleton h-4 w-24 rounded"></div><div class="skeleton h-4 w-20 rounded"></div><div class="skeleton h-4 w-16 rounded"></div><div class="skeleton h-4 w-24 rounded"></div></div>
                        <div class="flex gap-3"><div class="skeleton h-4 w-8 rounded"></div><div class="skeleton h-4 w-28 rounded"></div><div class="skeleton h-4 w-20 rounded"></div><div class="skeleton h-4 w-24 rounded"></div><div class="skeleton h-4 w-14 rounded"></div><div class="skeleton h-4 w-20 rounded"></div></div>
                        <div class="flex gap-3"><div class="skeleton h-4 w-8 rounded"></div><div class="skeleton h-4 w-36 rounded"></div><div class="skeleton h-4 w-20 rounded"></div><div class="skeleton h-4 w-18 rounded"></div><div class="skeleton h-4 w-14 rounded"></div><div class="skeleton h-4 w-24 rounded"></div></div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-3">Card Skeleton</h3>
                    <div class="grid grid-cols-2 gap-4 max-w-md">
                        <div class="skeleton h-24 rounded-xl"></div>
                        <div class="skeleton h-24 rounded-xl"></div>
                    </div>
                </div>
                <button id="showLoaderBtn" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-all flex items-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Show Loader</button>
            </div>

            {{-- Tab: Upload --}}
            <div id="tab-upload" class="component-tab-content hidden">
                <div id="dropZone" class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-2xl p-8 text-center hover:border-indigo-400 dark:hover:border-indigo-500 transition-all cursor-pointer bg-slate-50 dark:bg-slate-700/30">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center mb-3"><i data-lucide="upload-cloud" class="w-6 h-6 text-indigo-600 dark:text-indigo-400"></i></div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Drag & drop your image here</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">or</p>
                    <button id="browseBtn" class="mt-3 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-all">Browse File</button>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-3">JPG / PNG / WebP · Max 5 MB</p>
                    <input type="file" id="fileInput" class="hidden" accept="image/jpeg,image/png,image/webp" aria-label="Upload image">
                </div>
                <div id="imagePreviewContainer" class="hidden mt-4 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/40">
                    <div class="flex items-center gap-4">
                        <img id="imagePreview" src="" alt="Preview" class="w-20 h-20 object-cover rounded-xl border border-slate-200 dark:border-slate-600">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200" id="fileName">filename.jpg</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400" id="fileSize">1.2 MB</p>
                        </div>
                        <button id="removeImageBtn" class="p-2 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" aria-label="Remove image"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                </div>
            </div>

            {{-- Tab: Modals --}}
            <div id="tab-modals" class="component-tab-content hidden space-y-4">
                <div class="flex flex-wrap gap-3">
                    <button id="openAddProductBtn" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-all flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Add Product</button>
                    <button id="openDeleteBtn" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-all flex items-center gap-2"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete Confirmation</button>
                </div>
            </div>
        </div>

    </main>

    {{-- ==================== DASHBOARD-SPECIFIC JAVASCRIPT ==================== --}}
    @push('scripts')
    <script>
        // Initialize selects for dashboard
        periodSelect = initCustomSelect('periodSelectWrapper', 'periodSelectBtn', 'periodSelectDropdown', 'periodSelectLabel',
            (value) => updateChartPeriod(value));
        statusFilter = initCustomSelect('statusFilterWrapper', 'statusFilterBtn', 'statusFilterDropdown', 'statusFilterLabel');
        paymentFilter = initCustomSelect('paymentFilterWrapper', 'paymentFilterBtn', 'paymentFilterDropdown', 'paymentFilterLabel');
        modalCategorySelect = initCustomSelect('modalCategorySelectWrapper', 'modalCategorySelectBtn', 'modalCategoryDropdown', 'modalCategoryLabel');

        // ========== MODAL BUTTONS ==========
        const addProductModal = document.getElementById('addProductModal');
        const deleteModal = document.getElementById('deleteModal');

        document.getElementById('openAddProductBtn').addEventListener('click', () => openModal(addProductModal));
        document.getElementById('openDeleteBtn').addEventListener('click', () => openModal(deleteModal));
        document.getElementById('newTransactionBtn').addEventListener('click', () => openModal(addProductModal));
        document.getElementById('saveProductBtn').addEventListener('click', () => { closeModal(addProductModal); showToast('success', 'Product saved successfully.'); });
        document.getElementById('confirmDeleteBtn').addEventListener('click', () => { closeModal(deleteModal); showToast('success', 'Product deleted successfully.'); });

        // ========== EXPORT / FILTER BUTTONS ==========
        document.getElementById('exportReportBtn').addEventListener('click', () => showToast('success', 'Report exported successfully.'));
        document.getElementById('filterBtn').addEventListener('click', filterTable);
        document.getElementById('tableExportBtn').addEventListener('click', () => showToast('success', 'Table data exported.'));

        // ========== SHOW LOADER BUTTON ==========
        document.getElementById('showLoaderBtn').addEventListener('click', () => {
            showLoader();
            setTimeout(hideLoader, 1800);
        });

        // ========== IMAGE UPLOAD ==========
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const browseBtn = document.getElementById('browseBtn');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeImageBtn = document.getElementById('removeImageBtn');

        function handleFile(file) {
            if (!file || !file.type.startsWith('image/')) { showToast('error', 'Please upload a valid image file.'); return; }
            if (file.size > 5 * 1024 * 1024) { showToast('error', 'File size must be less than 5 MB.'); return; }
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                imagePreviewContainer.classList.remove('hidden');
                showToast('success', 'Image uploaded successfully.');
            };
            reader.readAsDataURL(file);
        }

        browseBtn.addEventListener('click', (e) => { e.stopPropagation(); fileInput.click(); });
        dropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => { if (e.target.files[0]) handleFile(e.target.files[0]); });
        removeImageBtn.addEventListener('click', () => { imagePreviewContainer.classList.add('hidden'); fileInput.value = ''; imagePreview.src = ''; showToast('info', 'Image removed.'); });
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-950/50'); });
        dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-950/50'); });
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-950/50'); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });

        // Modal image upload
        const modalDropZone = document.getElementById('modalDropZone');
        const modalFileInput = document.getElementById('modalFileInput');
        const modalImagePreviewContainer = document.getElementById('modalImagePreviewContainer');
        const modalImagePreview = document.getElementById('modalImagePreview');
        const modalFileName = document.getElementById('modalFileName');
        const modalRemoveImageBtn = document.getElementById('modalRemoveImageBtn');

        modalDropZone.addEventListener('click', () => modalFileInput.click());
        modalFileInput.addEventListener('change', (e) => {
            if (e.target.files[0]) {
                const file = e.target.files[0];
                const reader = new FileReader();
                reader.onload = (ev) => { modalImagePreview.src = ev.target.result; modalFileName.textContent = file.name; modalImagePreviewContainer.classList.remove('hidden'); };
                reader.readAsDataURL(file);
            }
        });
        modalRemoveImageBtn.addEventListener('click', () => { modalImagePreviewContainer.classList.add('hidden'); modalFileInput.value = ''; modalImagePreview.src = ''; });

        // ========== CHART ==========
        function getChartData(period) {
            const datasets = {
                '7 Days': [5200000, 6800000, 5100000, 7200000, 8700000, 9400000, 12450000],
                '30 Days': [4200000, 4800000, 5600000, 6100000, 5800000, 7200000, 8300000, 7800000, 9200000, 10500000, 11000000, 9800000, 11200000, 12400000, 11800000, 13500000, 12800000, 14200000, 13800000, 15100000, 14600000, 15800000, 16400000, 17200000, 16800000, 18100000, 18900000, 19600000, 20400000, 21000000],
                '3 Months': [58000000, 62000000, 54000000, 68000000, 73000000, 79000000, 84000000, 81000000, 92000000, 98000000, 104000000, 110000000],
                '1 Year': [420000000, 480000000, 520000000, 560000000, 610000000, 650000000, 690000000, 730000000, 780000000, 820000000, 860000000, 910000000]
            };
            return datasets[period] || datasets['7 Days'];
        }

        function getChartLabels(period) {
            if (period === '7 Days') return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            if (period === '30 Days') return Array.from({ length: 30 }, (_, i) => `Day ${i + 1}`);
            if (period === '3 Months') return ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8', 'Week 9', 'Week 10', 'Week 11', 'Week 12'];
            if (period === '1 Year') return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }

        function initChart() {
            const canvas = document.getElementById('salesChart');
            if (!canvas) return;
            const colors = getChartColors();
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, colors.primaryAlpha);
            gradient.addColorStop(1, 'rgba(255,255,255,0)');
            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: getChartLabels('7 Days'),
                    datasets: [{ label: 'Revenue', data: getChartData('7 Days'), borderColor: colors.primary, backgroundColor: gradient, fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: colors.primary, pointBorderColor: '#fff', pointBorderWidth: 1.5, pointHoverRadius: 6 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: htmlEl.classList.contains('dark') ? '#334155' : '#1e293b', titleFont: { family: 'Inter', size: 12, weight: '600' }, bodyFont: { family: 'Inter', size: 11 }, padding: 10, cornerRadius: 8, callbacks: { label: (ctx) => `Rp ${ctx.parsed.y.toLocaleString('id-ID')}` } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: colors.ticks, font: { family: 'Inter', size: 10 } } },
                        y: { grid: { color: colors.grid }, ticks: { color: colors.ticks, font: { family: 'Inter', size: 10 }, callback: (v) => `Rp ${(v / 1000000).toFixed(0)}M` } }
                    }
                }
            });
        }

        function updateChartPeriod(period) {
            if (salesChart) {
                salesChart.data.labels = getChartLabels(period);
                salesChart.data.datasets[0].data = getChartData(period);
                salesChart.update();
            }
        }

        setTimeout(() => { if (typeof Chart !== 'undefined') initChart(); }, 300);

        // ========== COMPONENT TABS ==========
        const componentTabs = document.querySelectorAll('.component-tab');
        const tabContents = document.querySelectorAll('.component-tab-content');
        componentTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.getAttribute('data-tab');
                componentTabs.forEach(t => { t.classList.remove('active', 'bg-indigo-600', 'text-white'); t.classList.add('text-slate-600', 'dark:text-slate-300'); });
                tab.classList.add('active', 'bg-indigo-600', 'text-white');
                tab.classList.remove('text-slate-600', 'dark:text-slate-300');
                tabContents.forEach(c => c.classList.add('hidden'));
                document.getElementById(`tab-${tabName}`).classList.remove('hidden');
            });
        });

        // ========== TABLE DATA ==========
        const tableData = [
            { invoice: 'INV-2026-001', customer: 'John Doe', cashier: 'Alex', payment: 'Cash', total: 'Rp 125.000', sync: 'Synced', date: '20 Aug 2026' },
            { invoice: 'INV-2026-002', customer: 'Sarah Smith', cashier: 'Maria', payment: 'QRIS', total: 'Rp 245.000', sync: 'Synced', date: '20 Aug 2026' },
            { invoice: 'INV-2026-003', customer: 'Budi Santoso', cashier: 'Alex', payment: 'Card', total: 'Rp 89.000', sync: 'Pending', date: '20 Aug 2026' },
            { invoice: 'INV-2026-004', customer: 'Jane Cooper', cashier: 'Rudi', payment: 'Cash', total: 'Rp 310.000', sync: 'Synced', date: '19 Aug 2026' },
            { invoice: 'INV-2026-005', customer: 'Michael Lee', cashier: 'Maria', payment: 'QRIS', total: 'Rp 178.500', sync: 'Synced', date: '19 Aug 2026' },
            { invoice: 'INV-2026-006', customer: 'Ayu Lestari', cashier: 'Alex', payment: 'Cash', total: 'Rp 54.000', sync: 'Failed', date: '19 Aug 2026' },
            { invoice: 'INV-2026-007', customer: 'David Kim', cashier: 'Rudi', payment: 'Card', total: 'Rp 432.000', sync: 'Synced', date: '18 Aug 2026' },
            { invoice: 'INV-2026-008', customer: 'Nia Paramita', cashier: 'Maria', payment: 'QRIS', total: 'Rp 156.000', sync: 'Pending', date: '18 Aug 2026' },
        ];

        const syncBadgeClasses = {
            'Synced': 'bg-green-100 dark:bg-green-900/50 border-green-200 dark:border-green-700 text-green-700 dark:text-green-400',
            'Pending': 'bg-amber-100 dark:bg-amber-900/50 border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-400',
            'Failed': 'bg-red-100 dark:bg-red-900/50 border-red-200 dark:border-red-700 text-red-700 dark:text-red-400'
        };
        const syncDotClasses = { 'Synced': 'bg-green-500', 'Pending': 'bg-amber-500', 'Failed': 'bg-red-500' };

        function renderTable(data) {
            const tbody = document.getElementById('tableBody');
            const emptyState = document.getElementById('tableEmptyState');
            if (!tbody) return;
            if (data.length === 0) { tbody.innerHTML = ''; emptyState.classList.remove('hidden'); return; }
            emptyState.classList.add('hidden');
            tbody.innerHTML = data.map((row, idx) => `
                <tr class="table-row border-b border-slate-100 dark:border-slate-700/50 transition-colors">
                    <td class="px-4 py-3"><input type="checkbox" class="custom-checkbox row-checkbox" aria-label="Select row ${idx+1}"></td>
                    <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">${row.invoice}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">${row.customer}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">${row.cashier}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-600 border border-slate-200 dark:border-slate-500 text-slate-700 dark:text-slate-200 text-xs font-medium">${row.payment}</span></td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800 dark:text-slate-200">${row.total}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold ${syncBadgeClasses[row.sync]}"><span class="w-1.5 h-1.5 rounded-full ${syncDotClasses[row.sync]}"></span> ${row.sync}</span></td>
                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">${row.date}</td>
                    <td class="px-4 py-3 text-right relative">
                        <button class="action-menu-btn p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors" aria-label="Actions for ${row.invoice}" data-row="${idx}"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                        <div class="action-dropdown dropdown-panel dropdown-hidden absolute right-4 mt-1 w-40 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg overflow-hidden z-40" data-row-dropdown="${idx}">
                            <button class="w-full flex items-center gap-2 px-3.5 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/60 transition-colors"><i data-lucide="eye" class="w-3.5 h-3.5 text-slate-400"></i> View</button>
                            <button class="w-full flex items-center gap-2 px-3.5 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/60 transition-colors"><i data-lucide="pencil" class="w-3.5 h-3.5 text-slate-400"></i> Edit</button>
                            <button class="w-full flex items-center gap-2 px-3.5 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/60 transition-colors"><i data-lucide="printer" class="w-3.5 h-3.5 text-slate-400"></i> Print</button>
                            <button class="w-full flex items-center gap-2 px-3.5 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
            initIcons();
            document.querySelectorAll('.action-menu-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const rowIdx = btn.getAttribute('data-row');
                    const dropdown = document.querySelector(`[data-row-dropdown="${rowIdx}"]`);
                    if (dropdown) { closeAllActionDropdowns(dropdown); dropdown.classList.toggle('dropdown-hidden'); }
                });
            });
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectAllState);
            });
        }

        function updateSelectAllState() {
            const selectAll = document.getElementById('selectAllCheckbox');
            const rows = document.querySelectorAll('.row-checkbox');
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (selectAll) selectAll.checked = rows.length > 0 && checked.length === rows.length;
        }

        document.getElementById('selectAllCheckbox').addEventListener('change', (e) => {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
        });

        renderTable(tableData);

        function filterTable() {
            const searchTerm = document.getElementById('tableSearchInput').value.toLowerCase();
            const status = document.getElementById('statusFilterLabel').textContent;
            const payment = document.getElementById('paymentFilterLabel').textContent;
            let filtered = tableData;
            if (searchTerm) {
                filtered = filtered.filter(row =>
                    row.invoice.toLowerCase().includes(searchTerm) ||
                    row.customer.toLowerCase().includes(searchTerm) ||
                    row.cashier.toLowerCase().includes(searchTerm) ||
                    row.total.toLowerCase().includes(searchTerm)
                );
            }
            if (status !== 'All' && status !== 'Status') filtered = filtered.filter(row => row.sync === status);
            if (payment !== 'All' && payment !== 'Payment') filtered = filtered.filter(row => row.payment === payment);
            renderTable(filtered);
            document.getElementById('paginationInfo').textContent = `Showing 1 to ${filtered.length} of ${tableData.length} results`;
        }

        document.getElementById('tableSearchInput').addEventListener('input', filterTable);
        document.getElementById('statusFilterDropdown').querySelectorAll('.select-option').forEach(opt => opt.addEventListener('click', () => filterTable()));
        document.getElementById('paymentFilterDropdown').querySelectorAll('.select-option').forEach(opt => opt.addEventListener('click', () => filterTable()));

        // ========== SYNC SIMULATION ==========
        const syncNowBtn = document.getElementById('syncNowBtn');
        const syncBtnText = document.getElementById('syncBtnText');
        const syncIcon = document.getElementById('syncIcon');

        syncNowBtn.addEventListener('click', () => {
            if (syncNowBtn.disabled) return;
            syncNowBtn.disabled = true;
            syncBtnText.textContent = 'Syncing...';
            syncIcon.classList.add('animate-spin');
            setTimeout(() => {
                syncNowBtn.disabled = false;
                syncBtnText.textContent = 'Sync Now';
                syncIcon.classList.remove('animate-spin');
                showToast('success', 'Data synchronized successfully.');
            }, 2000);
        });

        // ========== PASSWORD TOGGLE ==========
        const passwordInput = document.getElementById('passwordInput');
        const passwordToggleBtn = document.getElementById('passwordToggleBtn');
        const eyeIcon = document.getElementById('eyeIcon');

        if (passwordToggleBtn) {
            passwordToggleBtn.addEventListener('click', () => {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    if (eyeIcon) eyeIcon.setAttribute('data-lucide', 'eye-off');
                } else {
                    passwordInput.type = 'password';
                    if (eyeIcon) eyeIcon.setAttribute('data-lucide', 'eye');
                }
                initIcons();
            });
        }

        // ========== LOADING BUTTON DEMO ==========
        const loadingDemoBtn = document.getElementById('loadingDemoBtn');
        const loadingDemoText = document.getElementById('loadingDemoText');
        loadingDemoBtn.addEventListener('click', () => {
            if (loadingDemoBtn.disabled) return;
            loadingDemoBtn.disabled = true;
            loadingDemoText.innerHTML = '<span class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin inline-block"></span> Saving...';
            setTimeout(() => {
                loadingDemoBtn.disabled = false;
                loadingDemoText.textContent = 'Save Changes';
                showToast('success', 'Changes saved successfully.');
            }, 2000);
        });
    </script>
    @endpush
</x-layouts::app>
