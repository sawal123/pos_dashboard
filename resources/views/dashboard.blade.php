<x-layouts::app :title="__('Dashboard')">
    <main id="mainContent" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== DASHBOARD HEADER ==================== --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                        <i data-lucide="store" class="w-3.5 h-3.5"></i>
                        <span id="businessNameLabel">Kopi & Resto Nusantara</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span id="outletLabel">Outlet Sudirman (Utama)</span>
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Shift Aktif
                    </span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Halo, {{ auth()->check() ? auth()->user()->name : 'Alex Lee' }} 👋
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Kamis, 17 September 2026 · Shift Pagi (08:00 - 16:00) · Ikhtisar operasional & penjualan hari ini
                </p>
            </div>

            {{-- Action Area --}}
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
                <button type="button" id="exportReportBtn" class="flex-1 sm:flex-none px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-xs md:text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-2 shadow-xs">
                    <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
                    <span>Ekspor Laporan</span>
                </button>
                <button type="button" id="headerNewExpenseBtn" class="flex-1 sm:flex-none px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-xs md:text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-2 shadow-xs">
                    <i data-lucide="minus-circle" class="w-4 h-4 text-rose-500"></i>
                    <span>Catat Kas</span>
                </button>
                <button type="button" id="newTransactionBtn" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs md:text-sm font-semibold transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-indigo-500/20">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Transaksi Baru (POS)</span>
                </button>
            </div>
        </div>

        {{-- ==================== 1. KPI CARDS ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- KPI 1: Penjualan Hari Ini --}}
            <x-dashboard.kpi-card
                title="Penjualan Hari Ini"
                value="Rp 14.850.000"
                icon="trending-up"
                trend="+14.2%"
                :trendUp="true"
                subtitle="Target harian 74% tercapai"
                accent="indigo"
            />

            {{-- KPI 2: Jumlah Transaksi --}}
            <x-dashboard.kpi-card
                title="Jumlah Transaksi"
                value="382 Struk"
                icon="receipt"
                trend="+8.5%"
                :trendUp="true"
                subtitle="Rata-rata Rp 38.874 / struk"
                accent="blue"
            />

            {{-- KPI 3: Estimasi Laba Kotor --}}
            <x-dashboard.kpi-card
                title="Estimasi Laba Kotor"
                value="Rp 6.240.000"
                icon="coins"
                trend="+11.8%"
                :trendUp="true"
                subtitle="Margin kotor 42.0% dari omzet"
                accent="emerald"
            />

            {{-- KPI 4: Status Stok / Pesanan Aktif --}}
            <x-dashboard.kpi-card
                title="Peringatan Operasional"
                value="3 Stok Menipis"
                icon="alert-triangle"
                trend="5 Siap Ambil"
                :trendUp="false"
                subtitle="3 SKU perlu restock · 5 laundry siap"
                accent="amber"
            />
        </div>

        {{-- ==================== 2. SALES OVERVIEW & CLOUD/DEVICE STATUS ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Sales Overview Chart (2 cols on lg) --}}
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h2 class="font-semibold text-slate-900 dark:text-white text-base md:text-lg tracking-tight">Grafik Penjualan & Tren</h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tren omzet pendapatan berdasarkan periode</p>
                            </div>
                        </div>

                        {{-- Period Selector Dropdown --}}
                        <div class="relative" id="periodSelectWrapper">
                            <button type="button" id="periodSelectBtn" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-xs md:text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span id="periodSelectLabel">7 Hari Terakhir</span>
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                            </button>
                            <div id="periodSelectDropdown" class="dropdown-panel dropdown-hidden absolute right-0 mt-2 w-44 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg overflow-hidden z-50">
                                <button type="button" class="select-option selected w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="7 Hari Terakhir">7 Hari Terakhir</button>
                                <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="30 Hari Terakhir">30 Hari Terakhir</button>
                                <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="3 Bulan Terakhir">3 Bulan Terakhir</button>
                                <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="1 Tahun Penuh">1 Tahun Penuh</button>
                            </div>
                        </div>
                    </div>

                    {{-- Chart Canvas --}}
                    <div class="relative h-64 md:h-72 w-full">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                {{-- Trend Insight Footer Strip --}}
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span class="text-slate-500 dark:text-slate-400">Puncak Omzet:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">Sabtu (Rp 18.9M)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-slate-500 dark:text-slate-400">Rata-rata/Hari:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">Rp 10.4M</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span class="text-slate-500 dark:text-slate-400">Metode Favorit:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">QRIS (58%)</span>
                    </div>
                </div>
            </div>

            {{-- Cloud & Device Status (1 col on lg) --}}
            <x-dashboard.cloud-status
                :isOnline="true"
                :syncedCount="348"
                :pendingCount="12"
                lastSyncTime="1 menit yang lalu"
                deviceName="POS-TERMINAL-01"
                deviceType="Android POS Tablet (Kasir 1)"
            />
        </div>

        {{-- ==================== 3. CASH SUMMARY & OPERATIONAL/STOCK INSIGHT ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Cash Drawer Summary --}}
            <x-dashboard.cash-summary
                openingCash="Rp 500.000"
                cashIn="Rp 4.850.000"
                cashOut="Rp 350.000"
                currentBalance="Rp 5.000.000"
                cashierName="{{ auth()->check() ? auth()->user()->name : 'Alex Lee' }}"
                shiftName="Shift Pagi (08:00 - 16:00)"
            />

            {{-- Operational & Stock Insight --}}
            <x-dashboard.stock-alert />
        </div>

        {{-- ==================== 4. RECENT TRANSACTIONS SECTION ==================== --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
            {{-- Section Header & Controls --}}
            <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-100 dark:border-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-white text-base md:text-lg tracking-tight">Transaksi Terbaru</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Daftar transaksi penjualan langsung dari register kasir</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400 hidden sm:inline">Terakhir diperbarui: Baru saja</span>
                    <button type="button" id="refreshTableBtn" class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors" title="Muat Ulang Transaksi" aria-label="Refresh Data">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            {{-- Table Toolbar (Search + Filters + Actions) --}}
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-800/20 flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1">
                    {{-- Search Input --}}
                    <div class="relative flex-1 min-w-[220px] max-w-md">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="tableSearchInput" placeholder="Cari nomor invoice, nama pelanggan, kasir..." class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs md:text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all">
                    </div>

                    {{-- Status Filter --}}
                    <div class="relative" id="statusFilterWrapper">
                        <button type="button" id="statusFilterBtn" class="w-full sm:w-auto flex items-center justify-between sm:justify-start gap-2 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs md:text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                            <span class="text-slate-400 font-normal">Status:</span>
                            <span id="statusFilterLabel">Semua</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                        </button>
                        <div id="statusFilterDropdown" class="dropdown-panel dropdown-hidden absolute left-0 mt-2 w-44 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg overflow-hidden z-50">
                            <button type="button" class="select-option selected w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Semua">Semua</button>
                            <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Synced">Synced</button>
                            <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Pending">Pending</button>
                            <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Failed">Failed</button>
                        </div>
                    </div>

                    {{-- Payment Method Filter --}}
                    <div class="relative" id="paymentFilterWrapper">
                        <button type="button" id="paymentFilterBtn" class="w-full sm:w-auto flex items-center justify-between sm:justify-start gap-2 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs md:text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                            <span class="text-slate-400 font-normal">Bayar:</span>
                            <span id="paymentFilterLabel">Semua</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                        </button>
                        <div id="paymentFilterDropdown" class="dropdown-panel dropdown-hidden absolute left-0 mt-2 w-44 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg overflow-hidden z-50">
                            <button type="button" class="select-option selected w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Semua">Semua</button>
                            <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Tunai">Tunai</button>
                            <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="QRIS">QRIS</button>
                            <button type="button" class="select-option w-full text-left px-4 py-2.5 text-xs md:text-sm text-slate-700 dark:text-slate-200" data-value="Kartu">Kartu</button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end lg:self-auto">
                    <button type="button" id="resetFilterBtn" class="px-3 py-2 rounded-xl text-xs font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors flex items-center gap-1">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        Reset Filter
                    </button>
                    <button type="button" id="tableExportBtn" class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs md:text-sm font-medium transition-colors flex items-center gap-1.5 shadow-xs">
                        <i data-lucide="download" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>Ekspor Data</span>
                    </button>
                </div>
            </div>

            {{-- 1. Desktop & Tablet Table View (hidden on small mobile screens) --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs md:text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/30 text-slate-500 dark:text-slate-400 font-semibold uppercase text-[11px] tracking-wider">
                            <th class="px-4 py-3.5 w-10">
                                <input type="checkbox" id="selectAllCheckbox" class="custom-checkbox" aria-label="Pilih Semua">
                            </th>
                            <th class="px-4 py-3.5">Invoice</th>
                            <th class="px-4 py-3.5">Waktu</th>
                            <th class="px-4 py-3.5">Pelanggan</th>
                            <th class="px-4 py-3.5">Kasir</th>
                            <th class="px-4 py-3.5">Pembayaran</th>
                            <th class="px-4 py-3.5 text-right">Total</th>
                            <th class="px-4 py-3.5">Status Cloud</th>
                            <th class="px-4 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        {{-- Rows rendered by JavaScript --}}
                    </tbody>
                </table>
            </div>

            {{-- 2. Mobile Responsive Card/List View (visible only on mobile) --}}
            <div id="mobileTransactionList" class="block md:hidden divide-y divide-slate-100 dark:divide-slate-800/80 p-2">
                {{-- Mobile card representations rendered by JavaScript --}}
            </div>

            {{-- Empty State (Hidden by default, shown if filter empty) --}}
            <div id="tableEmptyState" class="hidden">
                <x-dashboard.empty-state
                    icon="receipt"
                    title="Tidak ada transaksi yang cocok"
                    description="Coba ubah kata kunci pencarian atau sesuaikan filter status dan metode pembayaran."
                    actionLabel="Reset Semua Filter"
                    actionId="emptyStateResetBtn"
                />
            </div>

            {{-- Pagination & Summary Footer --}}
            <div class="px-5 py-3.5 border-t border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                <p id="paginationInfo">Menampilkan 1 - 8 dari 8 transaksi</p>
                <div class="flex items-center gap-1" id="paginationNav">
                    <button type="button" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-40" disabled>Sebelumnya</button>
                    <button type="button" class="px-2.5 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">1</button>
                    <button type="button" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">2</button>
                    <button type="button" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Selanjutnya</button>
                </div>
            </div>
        </div>

        {{-- ==================== 5. UI FOUNDATION SHOWCASE (ACCORDION) ==================== --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs">
            <button type="button" id="toggleShowcaseBtn" class="w-full flex items-center justify-between text-left group">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center">
                        <i data-lucide="layers" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm md:text-base">Katalog Komponen UI Foundation</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Verifikasi visual token desain, varian badge, skeleton, dan interaksi form</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 group-hover:underline">Buka Showcase</span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform duration-200" id="showcaseChevron"></i>
                </div>
            </button>

            <div id="showcasePanel" class="hidden mt-5 pt-5 border-t border-slate-200/80 dark:border-slate-800 space-y-6">
                {{-- Tab navigation --}}
                <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                    <button type="button" class="component-tab active px-3.5 py-1.5 rounded-xl text-xs font-medium bg-indigo-600 text-white transition-colors" data-tab="badges">Badges & Status</button>
                    <button type="button" class="component-tab px-3.5 py-1.5 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" data-tab="buttons">Tombol & Aksi</button>
                    <button type="button" class="component-tab px-3.5 py-1.5 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" data-tab="skeletons">Skeleton & Loader</button>
                    <button type="button" class="component-tab px-3.5 py-1.5 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" data-tab="feedback">Toast & Feedback</button>
                </div>

                {{-- Tab 1: Badges --}}
                <div id="tab-badges" class="component-tab-content space-y-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Status sinkronisasi & status order yang digunakan pada seluruh modul:</p>
                    <div class="flex flex-wrap gap-2.5">
                        <x-dashboard.status-badge status="synced" label="Synced" />
                        <x-dashboard.status-badge status="pending" label="Pending Sync" />
                        <x-dashboard.status-badge status="failed" label="Sync Failed" />
                        <x-dashboard.status-badge status="offline" label="Offline Register" />
                        <x-dashboard.status-badge status="paid" label="Lunas (Paid)" />
                        <x-dashboard.status-badge status="active" label="Aktif (Active)" />
                    </div>
                </div>

                {{-- Tab 2: Buttons --}}
                <div id="tab-buttons" class="component-tab-content hidden space-y-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Hierarki tombol untuk alur POS yang konsisten:</p>
                    <div class="flex flex-wrap gap-2.5 items-center">
                        <button type="button" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-xs">Primary Indigo</button>
                        <button type="button" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium shadow-xs">Success Emerald</button>
                        <button type="button" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium shadow-xs">Destructive Rose</button>
                        <button type="button" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Secondary Outlined</button>
                        <button type="button" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-700">Subtle / Ghost</button>
                    </div>
                </div>

                {{-- Tab 3: Skeletons --}}
                <div id="tab-skeletons" class="component-tab-content hidden space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Placeholder skeleton untuk perenderan data asinkron:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-dashboard.skeleton type="kpi" :count="2" />
                    </div>
                </div>

                {{-- Tab 4: Feedback & Toast Trigger --}}
                <div id="tab-feedback" class="component-tab-content hidden space-y-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Uji coba notifikasi toast feedback:</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="showToast('success', 'Data transaksi berhasil disimpan.')" class="px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 text-xs font-medium">Test Success Toast</button>
                        <button type="button" onclick="showToast('warning', 'Perhatian: Ada 12 data tertunda di antrean sync.')" class="px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200 text-xs font-medium">Test Warning Toast</button>
                        <button type="button" onclick="showToast('error', 'Koneksi lokal terputus, beralih ke mode offline.')" class="px-3 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200 text-xs font-medium">Test Error Toast</button>
                        <button type="button" onclick="showToast('info', 'Shift kasir aktif dimulai pukul 08:00 WIB.')" class="px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-400 border border-blue-200 text-xs font-medium">Test Info Toast</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    {{-- ==================== DASHBOARD JAVASCRIPT LOGIC ==================== --}}
    @push('scripts')
    <script>
        // ==================== 1. CUSTOM SELECT DROPDOWNS ====================
        periodSelect = initCustomSelect('periodSelectWrapper', 'periodSelectBtn', 'periodSelectDropdown', 'periodSelectLabel', (value) => updateChartPeriod(value));
        statusFilter = initCustomSelect('statusFilterWrapper', 'statusFilterBtn', 'statusFilterDropdown', 'statusFilterLabel', () => filterTable());
        paymentFilter = initCustomSelect('paymentFilterWrapper', 'paymentFilterBtn', 'paymentFilterDropdown', 'paymentFilterLabel', () => filterTable());

        // ==================== 2. MOCK PRESENTATION DATA ====================
        const transactionDataset = [
            { invoice: 'INV-2026-001', customer: 'Budi Santoso', cashier: 'Alex Lee', payment: 'Tunai', total: 'Rp 125.000', sync: 'Synced', time: '14:45 WIB', items: '3 item (Kopi Susu x2, Croissant)' },
            { invoice: 'INV-2026-002', customer: 'Siti Rahma', cashier: 'Alex Lee', payment: 'QRIS', total: 'Rp 245.000', sync: 'Synced', time: '14:32 WIB', items: '4 item (Cold Brew, Toast)' },
            { invoice: 'INV-2026-003', customer: 'Pelanggan Umum', cashier: 'Alex Lee', payment: 'Kartu', total: 'Rp 89.000', sync: 'Pending', time: '14:15 WIB', items: '1 item (Espresso Single)' },
            { invoice: 'INV-2026-004', customer: 'Ibu Citra (Laundry)', cashier: 'Maria', payment: 'Tunai', total: 'Rp 310.000', sync: 'Synced', time: '13:50 WIB', items: '5.4 kg Cuci Komplit' },
            { invoice: 'INV-2026-005', customer: 'Rudi Hartono', cashier: 'Alex Lee', payment: 'QRIS', total: 'Rp 178.500', sync: 'Synced', time: '13:20 WIB', items: '2 item (Matcha Latte x2)' },
            { invoice: 'INV-2026-006', customer: 'Ayu Lestari', cashier: 'Maria', payment: 'Tunai', total: 'Rp 54.000', sync: 'Failed', time: '12:45 WIB', items: '1 item (Americano Ice)' },
            { invoice: 'INV-2026-007', customer: 'Pak Danu (Laundry)', cashier: 'Maria', payment: 'Kartu', total: 'Rp 432.000', sync: 'Synced', time: '11:30 WIB', items: 'Bed Cover King + Setrika' },
            { invoice: 'INV-2026-008', customer: 'Nia Paramita', cashier: 'Alex Lee', payment: 'QRIS', total: 'Rp 156.000', sync: 'Pending', time: '10:15 WIB', items: '3 item (Cappuccino, Bagel)' },
        ];

        const syncBadgeHtml = {
            'Synced': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200/80 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Synced</span>',
            'Pending': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold bg-amber-50 dark:bg-amber-950/50 border-amber-200/80 dark:border-amber-800/60 text-amber-700 dark:text-amber-400"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending</span>',
            'Failed': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 border-rose-200/80 dark:border-rose-800/60 text-rose-700 dark:text-rose-400"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Failed</span>'
        };

        const paymentBadgeHtml = {
            'Tunai': '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/60 dark:border-emerald-800/40 text-emerald-700 dark:text-emerald-400 text-xs font-medium"><i data-lucide="banknote" class="w-3 h-3"></i> Tunai</span>',
            'QRIS': '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/40 text-indigo-700 dark:text-indigo-400 text-xs font-medium"><i data-lucide="qr-code" class="w-3 h-3"></i> QRIS</span>',
            'Kartu': '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200/60 dark:border-blue-800/40 text-blue-700 dark:text-blue-400 text-xs font-medium"><i data-lucide="credit-card" class="w-3 h-3"></i> Kartu</span>'
        };

        // ==================== 3. TABLE & MOBILE RENDERING ====================
        function renderTableRows(data) {
            const tbody = document.getElementById('tableBody');
            const mobileList = document.getElementById('mobileTransactionList');
            const emptyState = document.getElementById('tableEmptyState');
            const paginationInfo = document.getElementById('paginationInfo');

            if (!tbody || !mobileList) return;

            if (data.length === 0) {
                tbody.innerHTML = '';
                mobileList.innerHTML = '';
                emptyState.classList.remove('hidden');
                if (paginationInfo) paginationInfo.textContent = 'Tidak ada transaksi ditemukan';
                return;
            }

            emptyState.classList.add('hidden');
            if (paginationInfo) paginationInfo.textContent = `Menampilkan 1 - ${data.length} dari ${transactionDataset.length} transaksi`;

            // Desktop / Tablet table rows
            tbody.innerHTML = data.map((row, idx) => `
                <tr class="table-row hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="px-4 py-3.5">
                        <input type="checkbox" class="custom-checkbox row-checkbox" aria-label="Pilih ${row.invoice}">
                    </td>
                    <td class="px-4 py-3.5 font-semibold text-slate-900 dark:text-white">
                        <div class="flex items-center gap-1.5">
                            <span>${row.invoice}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                        ${row.time}
                    </td>
                    <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                        <span class="font-medium">${row.customer}</span>
                        <span class="block text-[11px] text-slate-400 truncate max-w-[140px]">${row.items}</span>
                    </td>
                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                        ${row.cashier}
                    </td>
                    <td class="px-4 py-3.5">
                        ${paymentBadgeHtml[row.payment] || `<span class="text-xs">${row.payment}</span>`}
                    </td>
                    <td class="px-4 py-3.5 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                        ${row.total}
                    </td>
                    <td class="px-4 py-3.5 whitespace-nowrap">
                        ${syncBadgeHtml[row.sync] || row.sync}
                    </td>
                    <td class="px-4 py-3.5 text-right relative">
                        <div class="flex items-center justify-end gap-1">
                            <button type="button" class="row-print-btn p-1.5 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors" title="Cetak Struk" data-invoice="${row.invoice}">
                                <i data-lucide="printer" class="w-4 h-4"></i>
                            </button>
                            <button type="button" class="action-menu-btn p-1.5 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors" aria-label="Aksi" data-row="${idx}">
                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="action-dropdown dropdown-panel dropdown-hidden absolute right-4 mt-1 w-36 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg overflow-hidden z-40 text-left" data-row-dropdown="${idx}">
                            <button type="button" onclick="showToast('info', 'Membuka rincian ${row.invoice}')" class="w-full flex items-center gap-2 px-3.5 py-2 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                                <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-400"></i> Lihat Detail
                            </button>
                            <button type="button" onclick="showToast('success', 'Struk ${row.invoice} dikirim ke printer thermal')" class="w-full flex items-center gap-2 px-3.5 py-2 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                                <i data-lucide="printer" class="w-3.5 h-3.5 text-slate-400"></i> Cetak Struk
                            </button>
                            <button type="button" onclick="showToast('warning', 'Void invoice memerlukan otorisasi Supervisor')" class="w-full flex items-center gap-2 px-3.5 py-2 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors">
                                <i data-lucide="slash" class="w-3.5 h-3.5"></i> Batalkan / Void
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            // Mobile card representation
            mobileList.innerHTML = data.map((row, idx) => `
                <div class="p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <div>
                            <span class="text-xs font-bold text-slate-900 dark:text-white">${row.invoice}</span>
                            <span class="text-[11px] text-slate-400 ml-1.5">${row.time}</span>
                        </div>
                        <span class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">${row.total}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 mb-2">
                        <span class="font-medium">${row.customer}</span>
                        <span class="text-[11px] text-slate-400">Kasir: ${row.cashier}</span>
                    </div>

                    <p class="text-[11px] text-slate-400 mb-2 truncate">${row.items}</p>

                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100 dark:border-slate-800/60">
                        <div class="flex items-center gap-1.5">
                            ${paymentBadgeHtml[row.payment] || `<span class="text-xs">${row.payment}</span>`}
                            ${syncBadgeHtml[row.sync] || row.sync}
                        </div>
                        <button type="button" onclick="showToast('success', 'Mencetak struk ${row.invoice}')" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-1">
                            <i data-lucide="printer" class="w-3 h-3"></i> Cetak
                        </button>
                    </div>
                </div>
            `).join('');

            initIcons();

            // Row dropdown toggling
            document.querySelectorAll('.action-menu-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const rowIdx = btn.getAttribute('data-row');
                    const dropdown = document.querySelector(`[data-row-dropdown="${rowIdx}"]`);
                    if (dropdown) {
                        closeAllActionDropdowns(dropdown);
                        dropdown.classList.toggle('dropdown-hidden');
                    }
                });
            });

            // Print button direct action
            document.querySelectorAll('.row-print-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const invoice = btn.getAttribute('data-invoice');
                    showToast('success', `Struk ${invoice} berhasil dikirim ke printer.`);
                });
            });

            // Select all checkbox binding
            const selectAll = document.getElementById('selectAllCheckbox');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
                });
            }
        }

        function filterTable() {
            const searchVal = (document.getElementById('tableSearchInput')?.value || '').toLowerCase().trim();
            const statusVal = document.getElementById('statusFilterLabel')?.textContent || 'Semua';
            const paymentVal = document.getElementById('paymentFilterLabel')?.textContent || 'Semua';

            let filtered = transactionDataset;

            if (searchVal) {
                filtered = filtered.filter(row =>
                    row.invoice.toLowerCase().includes(searchVal) ||
                    row.customer.toLowerCase().includes(searchVal) ||
                    row.cashier.toLowerCase().includes(searchVal) ||
                    row.items.toLowerCase().includes(searchVal) ||
                    row.total.toLowerCase().includes(searchVal)
                );
            }

            if (statusVal !== 'Semua') {
                filtered = filtered.filter(row => row.sync.toLowerCase() === statusVal.toLowerCase());
            }

            if (paymentVal !== 'Semua') {
                filtered = filtered.filter(row => row.payment.toLowerCase() === paymentVal.toLowerCase());
            }

            renderTableRows(filtered);
        }

        // Search and filter listeners
        document.getElementById('tableSearchInput')?.addEventListener('input', filterTable);

        // Reset filter buttons
        document.getElementById('resetFilterBtn')?.addEventListener('click', resetAllFilters);
        document.getElementById('emptyStateResetBtn')?.addEventListener('click', resetAllFilters);

        function resetAllFilters() {
            const searchInput = document.getElementById('tableSearchInput');
            if (searchInput) searchInput.value = '';

            const statusLabel = document.getElementById('statusFilterLabel');
            if (statusLabel) statusLabel.textContent = 'Semua';

            const paymentLabel = document.getElementById('paymentFilterLabel');
            if (paymentLabel) paymentLabel.textContent = 'Semua';

            document.querySelectorAll('#statusFilterDropdown .select-option').forEach(opt => {
                opt.classList.toggle('selected', opt.getAttribute('data-value') === 'Semua');
            });
            document.querySelectorAll('#paymentFilterDropdown .select-option').forEach(opt => {
                opt.classList.toggle('selected', opt.getAttribute('data-value') === 'Semua');
            });

            renderTableRows(transactionDataset);
            showToast('info', 'Filter berhasil direset.');
        }

        // ==================== 4. SALES OVERVIEW CHART ====================
        function getChartData(period) {
            const datasets = {
                '7 Hari Terakhir': [8200000, 9400000, 7800000, 11200000, 13400000, 18900000, 14850000],
                '30 Hari Terakhir': [6200000, 7100000, 6800000, 8400000, 9200000, 11000000, 10500000, 9800000, 10200000, 11400000, 12600000, 13200000, 12800000, 14100000, 13900000, 14500000, 15200000, 16000000, 15800000, 16500000, 17200000, 18000000, 17500000, 18900000, 19200000, 20100000, 19800000, 21000000, 20500000, 14850000],
                '3 Bulan Terakhir': [68000000, 74000000, 81000000, 79000000, 88000000, 94000000, 99000000, 105000000, 112000000, 118000000, 124000000, 132000000],
                '1 Tahun Penuh': [520000000, 580000000, 640000000, 690000000, 730000000, 780000000, 830000000, 870000000, 910000000, 960000000, 1020000000, 1150000000]
            };
            return datasets[period] || datasets['7 Hari Terakhir'];
        }

        function getChartLabels(period) {
            if (period === '7 Hari Terakhir') return ['Jum', 'Sab', 'Min', 'Sen', 'Sel', 'Rab', 'Hari Ini (Kam)'];
            if (period === '30 Hari Terakhir') return Array.from({ length: 30 }, (_, i) => `H-${30 - i}`);
            if (period === '3 Bulan Terakhir') return ['Mgg 1', 'Mgg 2', 'Mgg 3', 'Mgg 4', 'Mgg 5', 'Mgg 6', 'Mgg 7', 'Mgg 8', 'Mgg 9', 'Mgg 10', 'Mgg 11', 'Mgg 12'];
            if (period === '1 Tahun Penuh') return ['Okt', 'Nov', 'Des', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep'];
            return ['Jum', 'Sab', 'Min', 'Sen', 'Sel', 'Rab', 'Hari Ini (Kam)'];
        }

        function initSalesChart() {
            const canvas = document.getElementById('salesChart');
            if (!canvas || typeof Chart === 'undefined') return;

            const isDark = document.documentElement.classList.contains('dark');
            const primaryColor = isDark ? '#818cf8' : '#4f46e5';
            const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(226, 232, 240, 0.8)';
            const textColor = isDark ? '#94a3b8' : '#64748b';

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, isDark ? 'rgba(129, 140, 248, 0.25)' : 'rgba(79, 70, 229, 0.18)');
            gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

            if (window.salesChartInstance) {
                window.salesChartInstance.destroy();
            }

            window.salesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: getChartLabels('7 Hari Terakhir'),
                    datasets: [{
                        label: 'Omzet Penjualan',
                        data: getChartData('7 Hari Terakhir'),
                        borderColor: primaryColor,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3.5,
                        pointBackgroundColor: primaryColor,
                        pointBorderColor: isDark ? '#0f172a' : '#ffffff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#0f172a',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            padding: 10,
                            cornerRadius: 10,
                            callbacks: {
                                label: (ctx) => `Penjualan: Rp ${ctx.parsed.y.toLocaleString('id-ID')}`
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: textColor, font: { family: 'Inter', size: 11 } }
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                font: { family: 'Inter', size: 11 },
                                callback: (v) => `Rp ${(v / 1000000).toFixed(0)}M`
                            }
                        }
                    }
                }
            });
        }

        function updateChartPeriod(period) {
            if (window.salesChartInstance) {
                window.salesChartInstance.data.labels = getChartLabels(period);
                window.salesChartInstance.data.datasets[0].data = getChartData(period);
                window.salesChartInstance.update();
                showToast('info', `Grafik penjualan diperbarui: ${period}`);
            }
        }

        // ==================== 5. SYNC NOW ACTION SIMULATION ====================
        const syncNowBtn = document.getElementById('syncNowBtn');
        const syncBtnText = document.getElementById('syncBtnText');
        const syncIcon = document.getElementById('syncIcon');
        const pendingSyncCount = document.getElementById('pendingSyncCount');
        const lastSyncLabel = document.getElementById('lastSyncLabel');

        if (syncNowBtn) {
            syncNowBtn.addEventListener('click', () => {
                if (syncNowBtn.disabled) return;
                syncNowBtn.disabled = true;
                syncBtnText.textContent = 'Sinkronisasi Outbox...';
                syncIcon.classList.add('animate-spin');

                setTimeout(() => {
                    syncNowBtn.disabled = false;
                    syncBtnText.textContent = 'Sinkronkan Sekarang';
                    syncIcon.classList.remove('animate-spin');

                    if (pendingSyncCount) pendingSyncCount.textContent = '0';
                    if (lastSyncLabel) lastSyncLabel.textContent = 'Baru saja';

                    // Update all pending transactions to Synced
                    transactionDataset.forEach(row => {
                        if (row.sync === 'Pending') row.sync = 'Synced';
                    });
                    renderTableRows(transactionDataset);

                    showToast('success', '12 data outbox berhasil disinkronkan ke Cloud.');
                }, 1600);
            });
        }

        // ==================== 6. OPERATIONAL TABS (STOCK VS LAUNDRY) ====================
        const tabStockAlertBtn = document.getElementById('tabStockAlertBtn');
        const tabLaundryAlertBtn = document.getElementById('tabLaundryAlertBtn');
        const panelStockAlert = document.getElementById('panelStockAlert');
        const panelLaundryAlert = document.getElementById('panelLaundryAlert');
        const insightFooterLabel = document.getElementById('insightFooterLabel');

        if (tabStockAlertBtn && tabLaundryAlertBtn) {
            tabStockAlertBtn.addEventListener('click', () => {
                tabStockAlertBtn.classList.add('bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-xs', 'font-semibold');
                tabStockAlertBtn.classList.remove('text-slate-500', 'dark:text-slate-400');
                tabLaundryAlertBtn.classList.remove('bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-xs', 'font-semibold');
                tabLaundryAlertBtn.classList.add('text-slate-500', 'dark:text-slate-400');

                panelStockAlert.classList.remove('hidden');
                panelLaundryAlert.classList.add('hidden');
                if (insightFooterLabel) insightFooterLabel.textContent = '3 SKU butuh pengadaan ulang';
            });

            tabLaundryAlertBtn.addEventListener('click', () => {
                tabLaundryAlertBtn.classList.add('bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-xs', 'font-semibold');
                tabLaundryAlertBtn.classList.remove('text-slate-500', 'dark:text-slate-400');
                tabStockAlertBtn.classList.remove('bg-white', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-white', 'shadow-xs', 'font-semibold');
                tabStockAlertBtn.classList.add('text-slate-500', 'dark:text-slate-400');

                panelLaundryAlert.classList.remove('hidden');
                panelStockAlert.classList.add('hidden');
                if (insightFooterLabel) insightFooterLabel.textContent = '5 pesanan laundry siap diambil pelanggan';
            });
        }

        // Quick restock / pickup trigger bindings
        document.querySelectorAll('.restock-trigger-btn').forEach(btn => {
            btn.addEventListener('click', () => showToast('info', 'Permintaan purchase order dibuat untuk item ini.'));
        });
        document.querySelectorAll('.pickup-trigger-btn').forEach(btn => {
            btn.addEventListener('click', () => showToast('success', 'Status laundry diubah: Selesai diambil pelanggan.'));
        });

        // ==================== 7. BUTTON ACTIONS & MODALS ====================
        const addProductModal = document.getElementById('addProductModal');
        document.getElementById('newTransactionBtn')?.addEventListener('click', () => {
            if (typeof openModal === 'function' && addProductModal) {
                openModal(addProductModal);
            } else {
                showToast('info', 'Membuka antarmuka kasir / register POS...');
            }
        });

        document.getElementById('exportReportBtn')?.addEventListener('click', () => {
            showToast('success', 'Laporan ringkasan penjualan diekspor ke format Excel/PDF.');
        });
        document.getElementById('tableExportBtn')?.addEventListener('click', () => {
            showToast('success', 'Data tabel diekspor ke file CSV.');
        });
        document.getElementById('headerNewExpenseBtn')?.addEventListener('click', () => {
            showToast('info', 'Form pencatatan kas keluar / pengeluaran operasional dibuka.');
        });
        document.getElementById('recordExpenseBtn')?.addEventListener('click', () => {
            showToast('info', 'Form pencatatan kas keluar / pengeluaran operasional dibuka.');
        });
        document.getElementById('closeShiftBtn')?.addEventListener('click', () => {
            showToast('warning', 'Modal Rekonsiliasi Kas & Tutup Shift ditampilkan.');
        });
        document.getElementById('refreshTableBtn')?.addEventListener('click', () => {
            renderTableRows(transactionDataset);
            showToast('success', 'Daftar transaksi diperbarui.');
        });

        // ==================== 8. SHOWCASE ACCORDION & TABS ====================
        const toggleShowcaseBtn = document.getElementById('toggleShowcaseBtn');
        const showcasePanel = document.getElementById('showcasePanel');
        const showcaseChevron = document.getElementById('showcaseChevron');

        if (toggleShowcaseBtn && showcasePanel) {
            toggleShowcaseBtn.addEventListener('click', () => {
                const isHidden = showcasePanel.classList.contains('hidden');
                showcasePanel.classList.toggle('hidden');
                if (showcaseChevron) {
                    showcaseChevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
        }

        const componentTabs = document.querySelectorAll('.component-tab');
        const tabContents = document.querySelectorAll('.component-tab-content');
        componentTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.getAttribute('data-tab');
                componentTabs.forEach(t => {
                    t.classList.remove('active', 'bg-indigo-600', 'text-white');
                    t.classList.add('text-slate-600', 'dark:text-slate-300');
                });
                tab.classList.add('active', 'bg-indigo-600', 'text-white');
                tab.classList.remove('text-slate-600', 'dark:text-slate-300');
                tabContents.forEach(c => c.classList.add('hidden'));
                document.getElementById(`tab-${tabName}`)?.classList.remove('hidden');
            });
        });

        // ==================== 9. INITIAL LOAD ====================
        renderTableRows(transactionDataset);
        setTimeout(initSalesChart, 250);

        // Theme sync hook for Chart
        const observer = new MutationObserver(() => {
            initSalesChart();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    @endpush
</x-layouts::app>
