<x-layouts::app :title="__('Dashboard')">
    <main id="mainContent" data-dashboard-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- PREM-D02A — entitlement feedback. A denied Premium module redirects
             here, so the reason is never silent and the path to renewal is clear. --}}
        @if(session('status'))
            <div class="rounded-2xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-4 flex items-start gap-3" role="status">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"></i>
                <p class="text-sm text-amber-800 dark:text-amber-200">{{ session('status') }}</p>
            </div>
        @endif

        {{-- Cashier Mode Notice Banner --}}
        @if(($dashboardBusinessRole ?? null) === \App\Models\Business::ROLE_CASHIER)
            <div class="rounded-2xl border border-indigo-200/80 dark:border-indigo-900/60 bg-gradient-to-r from-indigo-50/80 via-white to-indigo-50/50 dark:from-indigo-950/30 dark:via-slate-900/40 dark:to-indigo-950/20 p-4 md:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-indigo-600/30">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm md:text-base font-bold text-slate-900 dark:text-white">
                                Mode Kasir Aktif
                            </h2>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 text-[10px] font-semibold">
                                <i data-lucide="lock" class="w-3 h-3"></i>
                                Akses Terbatas
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                            Menu administratif dikunci untuk peran Kasir. Silakan buka menu Kasir (POS) untuk melayani transaksi pelanggan.
                        </p>
                    </div>
                </div>
                <a href="{{ route('pos.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs md:text-sm font-semibold transition-all shadow-sm hover:shadow-indigo-500/20 shrink-0">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Buka Kasir (POS)</span>
                </a>
            </div>
        @endif

        {{-- ==================== DASHBOARD HEADER ==================== --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                        <i data-lucide="store" class="w-3.5 h-3.5"></i>
                        <span id="businessNameLabel">{{ $overview['business_name'] }}</span>
                    </span>
                    @if(!empty($overview['selected_outlet_id']))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-indigo-500"></i>
                            <span id="outletLabel">Cabang: {{ $overview['selected_outlet_name'] }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium">
                            <i data-lucide="globe" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span id="outletLabel">{{ $overview['selected_outlet_name'] ?? 'Seluruh Outlet' }}</span>
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg {{ $overview['open_shifts_count'] > 0 ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }} text-xs font-medium">
                        <span class="w-1.5 h-1.5 rounded-full {{ $overview['open_shifts_count'] > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                        {{ $overview['open_shifts_label'] }}
                    </span>
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Halo, {{ auth()->check() ? auth()->user()->name : 'Pengguna' }} 👋
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $overview['current_date_formatted'] }} · {{ $overview['open_shifts_label'] }} · Ikhtisar operasional & penjualan hari ini
                </p>
            </div>

            {{-- Action Area (Placeholders with honest feedback) --}}
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
                <button type="button" id="exportReportBtn" onclick="showToast('info', 'Ekspor laporan dari dashboard belum tersedia.')" class="flex-1 sm:flex-none px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-xs md:text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-2 shadow-xs">
                    <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
                    <span>Ekspor Laporan</span>
                </button>
                <button type="button" id="headerNewExpenseBtn" onclick="showToast('info', 'Catat kas dari dashboard belum tersedia.')" class="flex-1 sm:flex-none px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-xs md:text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-2 shadow-xs">
                    <i data-lucide="minus-circle" class="w-4 h-4 text-rose-500"></i>
                    <span>Catat Kas</span>
                </button>
                <a href="{{ route('pos.index') }}" id="newTransactionBtn" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs md:text-sm font-semibold transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-indigo-500/20">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Transaksi Baru (POS)</span>
                </a>
            </div>
        </div>

        {{-- ==================== 1. KPI CARDS ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- KPI 1: Penjualan Hari Ini --}}
            <x-dashboard.kpi-card
                title="Penjualan Hari Ini"
                :value="$overview['kpi']['today_sales']['value']"
                icon="trending-up"
                :trend="null"
                :subtitle="$overview['kpi']['today_sales']['subtitle']"
                accent="indigo"
            />

            {{-- KPI 2: Jumlah Transaksi --}}
            <x-dashboard.kpi-card
                title="Jumlah Transaksi"
                :value="$overview['kpi']['today_transactions']['value']"
                icon="receipt"
                :trend="null"
                :subtitle="$overview['kpi']['today_transactions']['subtitle']"
                accent="blue"
            />

            {{-- KPI 3: Estimasi Laba Kotor --}}
            <x-dashboard.kpi-card
                title="Estimasi Laba Kotor"
                :value="$overview['kpi']['estimated_gross_profit']['value']"
                icon="coins"
                :trend="null"
                :subtitle="$overview['kpi']['estimated_gross_profit']['subtitle']"
                accent="emerald"
            />

            {{-- KPI 4: Peringatan Stok --}}
            <x-dashboard.kpi-card
                title="Peringatan Stok"
                :value="$overview['kpi']['stock_alerts']['value']"
                icon="alert-triangle"
                :trend="null"
                :subtitle="$overview['kpi']['stock_alerts']['subtitle']"
                accent="amber"
            />
        </div>

        {{-- ==================== 2. SALES OVERVIEW & CLOUD STATUS ==================== --}}
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
                                <h2 class="font-semibold text-slate-900 dark:text-white text-base md:text-lg tracking-tight">Grafik Penjualan</h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Tren omzet pendapatan · <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $overview['selected_outlet_name'] ?? 'Seluruh Cabang' }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- Period Selector (GET Navigation) --}}
                            <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl text-xs font-medium self-start sm:self-auto border border-slate-200/60 dark:border-slate-700/60" role="group" aria-label="Pilih Periode Grafik">
                                @php
                                    $currentPeriod = $overview['sales_chart']['period'];
                                    $periods = [
                                        '7d' => '7 Hari',
                                        '30d' => '30 Hari',
                                        '3m' => '3 Bulan',
                                        '12m' => '1 Tahun',
                                    ];
                                @endphp
                                @foreach($periods as $periodKey => $periodText)
                                    <a
                                        href="{{ route('dashboard', array_filter(['period' => $periodKey, 'outlet_id' => $selectedOutletId])) }}"
                                        class="px-2.5 py-1.5 rounded-lg transition-all {{ $currentPeriod === $periodKey ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                                    >
                                        {{ $periodText }}
                                    </a>
                                @endforeach
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
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $overview['sales_chart']['insight']['peak_sales'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-slate-500 dark:text-slate-400">{{ $overview['sales_chart']['insight']['average_label'] }}:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $overview['sales_chart']['insight']['average_sales'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span class="text-slate-500 dark:text-slate-400">Metode Terbanyak:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $overview['sales_chart']['insight']['top_payment_method'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Cloud & Device Status (1 col on lg) --}}
            <x-dashboard.cloud-status
                :serverSequence="$overview['cloud_panel']['server_sequence']"
                :totalPushRequests="$overview['cloud_panel']['total_push_requests']"
                :devicesWithPush="$overview['cloud_panel']['devices_with_push']"
                :lastProcessedAt="$overview['cloud_panel']['last_processed_at']"
                :totalRegisteredDevices="$overview['cloud_panel']['total_registered_devices']"
                :hasCloudAccess="$overview['cloud_panel']['has_cloud_access']"
                :cloudAccessLabel="$overview['cloud_panel']['cloud_access_label']"
            />
        </div>

        {{-- ==================== 3. CASH SUMMARY & STOCK ALERT ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Cash Movement Summary --}}
            <x-dashboard.cash-summary
                :cashIn="$overview['cash_summary']['cash_in']"
                :cashOut="$overview['cash_summary']['cash_out']"
                :netMovement="$overview['cash_summary']['net_movement']"
                :recordedExpense="$overview['cash_summary']['recorded_expense']"
                :openShiftsCount="$overview['cash_summary']['open_shifts_count']"
                :openShiftsLabel="$overview['cash_summary']['open_shifts_label']"
            />

            {{-- Operational Stock Insight --}}
            <x-dashboard.stock-alert
                :items="$overview['stock_panel']['items']"
                :hasPhysicalProducts="$overview['stock_panel']['has_physical_products']"
                :totalAlertCount="$overview['stock_panel']['total_alert_count']"
            />
        </div>

        {{-- ==================== 4. RECENT TRANSACTIONS SECTION ==================== --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
            {{-- Section Header --}}
            <div class="p-5 border-b border-slate-200/80 dark:border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-100 dark:border-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-white text-base md:text-lg tracking-tight">Transaksi Terbaru</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">8 transaksi terakhir dari register kasir</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="refreshRecentTransactionsBtn"
                        data-refresh-url="{{ route('dashboard.recent-transactions') }}"
                        class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer disabled:opacity-50"
                        title="Muat Ulang Transaksi"
                        aria-label="Refresh Data"
                    >
                        <i data-lucide="refresh-cw" id="refreshRecentTransactionsIcon" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            {{-- Content (Partial-refreshable container) --}}
            <div id="recentTransactionsContainer" class="transition-opacity duration-200">
                <x-dashboard.recent-transactions-content
                    :transactions="$overview['recent_transactions']"
                    :canEdit="$canEdit ?? false"
                    :canAddItems="$canAddItems ?? false"
                />
            </div>
        </div>

    </main>

    {{-- Transaction Detail Drawer & Edit Modal --}}
    <x-transactions.detail-drawer :canEdit="$canEdit ?? false" :canAddItems="false" />
    @if($canEdit ?? false)
        <x-transactions.edit-modal :paymentMethods="$paymentMethods ?? collect()" />
    @endif

    {{-- ==================== DASHBOARD JAVASCRIPT LOGIC ==================== --}}
    @push('scripts')
    <script>
        window.__salesChartLabels = @json($overview['sales_chart']['labels']);
        window.__salesChartData = @json($overview['sales_chart']['data']);

        function initDashboardSalesChart() {
            const pageRoot = document.querySelector('main[data-dashboard-page="true"]');
            if (!pageRoot) return;

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
                window.salesChartInstance = null;
            }

            const chartLabels = Array.isArray(window.__salesChartLabels) ? window.__salesChartLabels : [];
            const chartValues = Array.isArray(window.__salesChartData) ? window.__salesChartData : [];

            window.salesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Omzet Penjualan',
                        data: chartValues,
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
                                label: (context) => `Penjualan: Rp ${Number(context.parsed.y || 0).toLocaleString('id-ID')}`
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
                                callback: (v) => {
                                    const val = Number(v) || 0;
                                    if (val >= 1000000000) return `Rp ${(val / 1000000000).toFixed(1)}M`;
                                    if (val >= 1000000) return `Rp ${(val / 1000000).toFixed(0)}Jt`;
                                    if (val >= 1000) return `Rp ${(val / 1000).toFixed(0)}rb`;
                                    return `Rp ${val}`;
                                }
                            }
                        }
                    }
                }
            });
            window.salesChart = window.salesChartInstance;
        }

        function cleanupDashboard() {
            if (window.salesChartInstance) {
                window.salesChartInstance.destroy();
                window.salesChartInstance = null;
            }
            if (window.salesChart && typeof window.salesChart.destroy === 'function') {
                window.salesChart = null;
            }
            if (window.__dashboardThemeObserver) {
                window.__dashboardThemeObserver.disconnect();
                window.__dashboardThemeObserver = null;
            }
        }

        function initRecentTransactionsRefresh() {
            const btn = document.getElementById('refreshRecentTransactionsBtn');
            const container = document.getElementById('recentTransactionsContainer');
            if (!btn || !container || btn.dataset.refreshBound === 'true') return;
            btn.dataset.refreshBound = 'true';

            btn.addEventListener('click', async () => {
                if (btn.disabled) return;
                const url = btn.getAttribute('data-refresh-url');
                if (!url) return;

                btn.disabled = true;
                const icon = btn.querySelector('svg, [data-lucide="refresh-cw"]');
                if (icon) icon.classList.add('animate-spin');
                container.classList.add('opacity-50');

                try {
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        cache: 'no-store',
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Gagal memuat ulang transaksi terbaru.');
                    }
                    container.innerHTML = data.html || '';
                    if (typeof window.initIcons === 'function') {
                        window.initIcons();
                    } else if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                } catch (err) {
                    if (typeof showToast === 'function') {
                        showToast('error', err.message || 'Gagal memuat ulang tabel transaksi.');
                    }
                } finally {
                    btn.disabled = false;
                    const currentIcon = btn.querySelector('svg, [data-lucide="refresh-cw"]');
                    if (currentIcon) currentIcon.classList.remove('animate-spin');
                    container.classList.remove('opacity-50');
                }
            });
        }

        function initRecentTransactionsActions() {
            const container = document.getElementById('recentTransactionsContainer');
            if (!container || container.dataset.actionsBound === 'true') return;
            container.dataset.actionsBound = 'true';

            const drawerWrapper = document.getElementById('transactionDrawerWrapper');
            const drawerBackdrop = document.getElementById('transactionDrawerBackdrop');
            const drawerPanel = document.getElementById('transactionDrawerPanel');
            const closeDrawerBtn = document.getElementById('closeDrawerBtn');
            const drawerCloseFooterBtn = document.getElementById('drawerCloseFooterBtn');
            const drawerPrintBtn = document.getElementById('drawerPrintBtn');
            const drawerEditBtn = document.getElementById('drawerEditBtn');

            const editModal = document.getElementById('editTransactionModal');
            const editBackdrop = document.getElementById('editTransactionBackdrop');
            const editDialog = document.getElementById('editTransactionDialog');
            const editForm = document.getElementById('editTransactionForm');
            const closeEditModalBtn = document.getElementById('closeEditModalBtn');
            const cancelEditModalBtn = document.getElementById('cancelEditModalBtn');
            const editPaymentMethod = document.getElementById('editPaymentMethod');
            const editCashSection = document.getElementById('editCashSection');
            const editCashReceived = document.getElementById('editCashReceived');
            const editChangeAmountDisplay = document.getElementById('editChangeAmountDisplay');
            const editNote = document.getElementById('editNote');
            const editTrxNumberDisplay = document.getElementById('editTrxNumberDisplay');
            const editTrxTotalDisplay = document.getElementById('editTrxTotalDisplay');
            const editTrxOutletTimeDisplay = document.getElementById('editTrxOutletTimeDisplay');
            const submitEditTrxBtn = document.getElementById('submitEditTrxBtn');

            let currentTrxData = null;
            let activeEditTrxTotal = 0;

            function formatRupiah(num) {
                const val = Number(num || 0);
                const hasFraction = Math.abs(val % 1) > 0.0001;
                return 'Rp ' + new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: hasFraction ? 2 : 0,
                    maximumFractionDigits: 2,
                }).format(val);
            }

            function refreshIcons() {
                if (typeof window.initIcons === 'function') {
                    window.initIcons();
                } else if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            function openDrawer(data) {
                if (!drawerWrapper || !data) return;
                currentTrxData = data;

                document.getElementById('detailTrxNumber').textContent = data.transaction_number || '-';
                document.getElementById('detailSoldAt').textContent = data.sold_at || '-';
                document.getElementById('detailOutlet').textContent = data.outlet_name || '-';
                document.getElementById('detailShift').textContent = data.shift_name || '-';
                document.getElementById('detailCustomer').textContent = data.customer_name || 'Pelanggan Umum';
                const detailTableEl = document.getElementById('detailTableNumber');
                if (detailTableEl) detailTableEl.textContent = data.table_number || '-';

                // Payment status badge
                const payBadge = document.getElementById('detailPaymentStatusBadge');
                payBadge.textContent = '';
                const payDot = document.createElement('span');
                const payText = document.createElement('span');
                const rawPayStatus = String(data.payment_status_raw || data.payment_status || '').toLowerCase();
                if (rawPayStatus === 'paid' || data.payment_status === 'Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    payText.textContent = 'Lunas';
                } else if (rawPayStatus === 'unpaid' || data.payment_status === 'Belum Lunas') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 border border-amber-200/60 dark:border-amber-800/60 text-amber-700 dark:text-amber-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-amber-500';
                    payText.textContent = 'Belum Lunas';
                } else if (rawPayStatus === 'refund' || rawPayStatus === 'refunded' || data.payment_status === 'Refund') {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-purple-50 dark:bg-purple-950/60 border border-purple-200/60 dark:border-purple-800/60 text-purple-700 dark:text-purple-400';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-purple-500';
                    payText.textContent = 'Refund';
                } else {
                    payBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300';
                    payDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                    payText.textContent = data.payment_status || '-';
                }
                payBadge.appendChild(payDot);
                payBadge.appendChild(payText);

                // Order / Transaction status badge
                const txBadge = document.getElementById('detailTransactionStatusBadge');
                txBadge.textContent = '';
                const txDot = document.createElement('span');
                const txText = document.createElement('span');
                const rawTxStatus = String(data.status_raw || data.status || '').toLowerCase();
                const rawOrderStatus = String(data.order_status_raw || data.order_status || '').toLowerCase();
                if (rawTxStatus === 'cancelled' || rawTxStatus === 'canceled' || rawTxStatus === 'batal' || rawOrderStatus === 'batal') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-rose-700 dark:text-rose-400';
                    txDot.className = 'w-1.5 h-1.5 rounded-full bg-rose-500';
                    txText.textContent = 'Batal';
                } else if (rawTxStatus === 'processing' || rawTxStatus === 'proses' || rawOrderStatus === 'proses') {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-blue-50 dark:bg-blue-950/60 border border-blue-200/60 dark:border-blue-800/60 text-blue-700 dark:text-blue-400';
                    txDot.className = 'w-1.5 h-1.5 rounded-full bg-blue-500';
                    txText.textContent = 'Proses';
                } else {
                    txBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400';
                    txDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                    txText.textContent = data.status || 'Selesai';
                }
                txBadge.appendChild(txDot);
                txBadge.appendChild(txText);

                const orderSection = document.getElementById('detailLaundrySection');
                if (orderSection) {
                    if (data.order_status || data.estimated_completed_at) {
                        orderSection.classList.remove('hidden');
                        document.getElementById('detailOrderStatus').textContent = data.order_status || '-';
                        document.getElementById('detailEstimatedCompletedAt').textContent = data.estimated_completed_at || '-';
                    } else {
                        orderSection.classList.add('hidden');
                    }
                }

                // Items list
                const itemsContainer = document.getElementById('detailItemsContainer');
                itemsContainer.textContent = '';
                if (Array.isArray(data.items) && data.items.length > 0) {
                    data.items.forEach(item => {
                        const itemEl = document.createElement('div');
                        itemEl.className = 'p-3 sm:p-3.5 flex items-start justify-between gap-3 text-xs';

                        const infoDiv = document.createElement('div');
                        infoDiv.className = 'min-w-0 flex-1';

                        const nameP = document.createElement('p');
                        nameP.className = 'font-bold text-slate-900 dark:text-white truncate';
                        nameP.textContent = item.product_name || '-';
                        infoDiv.appendChild(nameP);

                        if (item.variant_summary) {
                            const varP = document.createElement('p');
                            varP.className = 'text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 mt-0.5';
                            varP.textContent = item.variant_summary;
                            infoDiv.appendChild(varP);
                        }

                        if (item.product_sku) {
                            const skuP = document.createElement('p');
                            skuP.className = 'text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5';
                            skuP.textContent = item.product_sku;
                            infoDiv.appendChild(skuP);
                        }

                        const metaP = document.createElement('p');
                        metaP.className = 'text-[11px] text-slate-500 dark:text-slate-400 mt-1';
                        const qtySpan = document.createElement('span');
                        qtySpan.className = 'font-semibold text-slate-700 dark:text-slate-300';
                        const qtyNum = Number(item.quantity);
                        const qtyStr = Number.isFinite(qtyNum)
                            ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(Math.abs(qtyNum) < 1e-7 ? 0 : qtyNum)
                            : String(item.quantity ?? '');
                        const unitLabel = item.unit ? ` ${item.unit}` : '';
                        qtySpan.textContent = `${qtyStr}${unitLabel}`;
                        const pricingUnit = item.pricing_unit ? String(item.pricing_unit) : '';
                        const priceRate = pricingUnit !== '' ? `/${pricingUnit}` : '';
                        metaP.appendChild(qtySpan);
                        metaP.appendChild(document.createTextNode(` × ${formatRupiah(item.unit_price)}${priceRate}`));
                        infoDiv.appendChild(metaP);

                        const priceDiv = document.createElement('div');
                        priceDiv.className = 'text-right shrink-0';
                        const totalSpan = document.createElement('span');
                        totalSpan.className = 'font-extrabold text-slate-900 dark:text-white tabular-nums';
                        totalSpan.textContent = formatRupiah(item.line_total);
                        priceDiv.appendChild(totalSpan);

                        itemEl.appendChild(infoDiv);
                        itemEl.appendChild(priceDiv);
                        itemsContainer.appendChild(itemEl);
                    });
                } else {
                    const emptyEl = document.createElement('div');
                    emptyEl.className = 'p-4 text-center text-xs text-slate-400';
                    emptyEl.textContent = 'Tidak ada rincian item.';
                    itemsContainer.appendChild(emptyEl);
                }

                // Price Summary
                document.getElementById('detailSubtotal').textContent = formatRupiah(data.subtotal);
                const discountRow = document.getElementById('detailDiscountRow');
                if (Number(data.discount_amount || 0) > 0) {
                    discountRow.classList.remove('hidden');
                    document.getElementById('detailDiscount').textContent = '- ' + formatRupiah(data.discount_amount);
                } else {
                    discountRow.classList.add('hidden');
                }
                const taxRow = document.getElementById('detailTaxRow');
                if (Number(data.tax_amount || 0) > 0) {
                    taxRow.classList.remove('hidden');
                    document.getElementById('detailTax').textContent = '+ ' + formatRupiah(data.tax_amount);
                } else {
                    taxRow.classList.add('hidden');
                }
                document.getElementById('detailTotal').textContent = formatRupiah(data.total_amount);

                // Payment Details
                document.getElementById('detailPaymentMethod').textContent = data.payment_method || '-';
                document.getElementById('detailPaymentStatusText').textContent = data.payment_status || '-';
                document.getElementById('detailPaidAt').textContent = data.paid_at || 'Belum Dibayar';

                const cashSection = document.getElementById('detailCashOnlySection');
                if (data.payment_method === 'Tunai' && data.cash_received !== null && data.cash_received !== undefined) {
                    cashSection.classList.remove('hidden');
                    document.getElementById('detailCashReceived').textContent = formatRupiah(data.cash_received);
                    document.getElementById('detailChangeAmount').textContent = formatRupiah(data.change_amount);
                } else {
                    cashSection.classList.add('hidden');
                }

                document.getElementById('detailGrossProfit').textContent = formatRupiah(data.gross_profit);

                const noteSection = document.getElementById('detailNoteSection');
                if (data.note) {
                    noteSection.classList.remove('hidden');
                    document.getElementById('detailNote').textContent = `"${data.note}"`;
                } else {
                    noteSection.classList.add('hidden');
                }

                drawerWrapper.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    drawerBackdrop.classList.remove('opacity-0');
                    drawerBackdrop.classList.add('opacity-100');
                    drawerPanel.classList.remove('translate-x-full');
                    drawerPanel.classList.add('translate-x-0');
                    closeDrawerBtn?.focus();
                });
                refreshIcons();
            }

            function closeDrawer() {
                if (!drawerWrapper || drawerWrapper.classList.contains('hidden')) return;
                drawerBackdrop.classList.remove('opacity-100');
                drawerBackdrop.classList.add('opacity-0');
                drawerPanel.classList.remove('translate-x-0');
                drawerPanel.classList.add('translate-x-full');
                setTimeout(() => {
                    drawerWrapper.classList.add('hidden');
                    document.body.style.overflow = '';
                }, 300);
            }

            if (closeDrawerBtn) closeDrawerBtn.onclick = closeDrawer;
            if (drawerCloseFooterBtn) drawerCloseFooterBtn.onclick = closeDrawer;
            if (drawerBackdrop) drawerBackdrop.onclick = closeDrawer;
            if (drawerPrintBtn) {
                drawerPrintBtn.onclick = () => {
                    if (currentTrxData && currentTrxData.id) {
                        window.open(`/pos/receipt/${currentTrxData.id}`, '_blank');
                    }
                };
            }

            // Edit Modal helpers
            function syncCashFields() {
                if (!editCashSection || !editPaymentMethod) return;
                const method = (editPaymentMethod.value || '').toLowerCase();
                if (method === 'tunai' || method === 'cash') {
                    editCashSection.classList.remove('hidden');
                    if (!editCashReceived.value || parseFloat(editCashReceived.value) <= 0) {
                        editCashReceived.value = activeEditTrxTotal > 0 ? activeEditTrxTotal : '';
                    }
                    const received = parseFloat(editCashReceived.value || 0);
                    const change = Math.max(0, received - activeEditTrxTotal);
                    if (editChangeAmountDisplay) editChangeAmountDisplay.textContent = formatRupiah(change);
                } else {
                    editCashSection.classList.add('hidden');
                }
            }

            if (editPaymentMethod) editPaymentMethod.addEventListener('change', syncCashFields);
            if (editCashReceived) editCashReceived.addEventListener('input', syncCashFields);

            function selectEditPaymentStatus(status) {
                const input = document.getElementById('editPaymentStatus');
                if (input) input.value = status;
                document.querySelectorAll('.edit-payment-status-btn').forEach(btn => {
                    const btnStatus = btn.getAttribute('data-status');
                    const box = btn.querySelector('.edit-check-box');
                    const checkIcon = btn.querySelector('.edit-check-box i, .edit-check-box svg');
                    btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 transition-all cursor-pointer';
                    if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 transition-colors';
                    if (checkIcon) checkIcon.classList.add('hidden');
                    if (btnStatus === status) {
                        if (status === 'unpaid') {
                            btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-amber-500 bg-amber-50/70 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-amber-500 border-amber-500 transition-colors';
                        } else if (status === 'paid') {
                            btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-emerald-500 border-emerald-500 transition-colors';
                        } else if (status === 'refund') {
                            btn.className = 'edit-payment-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-purple-500 bg-purple-50/70 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-purple-500 border-purple-500 transition-colors';
                        }
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    }
                });
                refreshIcons();
            }
            window.selectEditPaymentStatus = selectEditPaymentStatus;

            function selectEditOrderStatus(status) {
                const input = document.getElementById('editStatus');
                if (input) input.value = status;
                if (status === 'batal') {
                    const payStatusInput = document.getElementById('editPaymentStatus');
                    if (payStatusInput && payStatusInput.value === 'paid') selectEditPaymentStatus('refund');
                } else if (status === 'siap' || status === 'proses') {
                    const payStatusInput = document.getElementById('editPaymentStatus');
                    if (payStatusInput && payStatusInput.value === 'refund') selectEditPaymentStatus('paid');
                }
                document.querySelectorAll('.edit-order-status-btn').forEach(btn => {
                    const btnStatus = btn.getAttribute('data-status');
                    const box = btn.querySelector('.edit-check-box');
                    const checkIcon = btn.querySelector('.edit-check-box i, .edit-check-box svg');
                    btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 transition-all cursor-pointer';
                    if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 transition-colors';
                    if (checkIcon) checkIcon.classList.add('hidden');
                    if (btnStatus === status) {
                        if (status === 'proses') {
                            btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-blue-500 bg-blue-50/70 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-blue-500 border-blue-500 transition-colors';
                        } else if (status === 'siap') {
                            btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-emerald-500 bg-emerald-50/70 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-emerald-500 border-emerald-500 transition-colors';
                        } else if (status === 'batal') {
                            btn.className = 'edit-order-status-btn flex items-center gap-2 p-2.5 rounded-xl border-2 border-rose-500 bg-rose-50/70 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 transition-all cursor-pointer';
                            if (box) box.className = 'edit-check-box w-4 h-4 rounded border flex items-center justify-center shrink-0 bg-rose-500 border-rose-500 transition-colors';
                        }
                        if (checkIcon) checkIcon.classList.remove('hidden');
                    }
                });
                refreshIcons();
            }
            window.selectEditOrderStatus = selectEditOrderStatus;

            function openEditModal(data) {
                if (!editModal || !editForm || !data) return;
                currentTrxData = data;
                activeEditTrxTotal = Number(data.total_amount || 0);
                editForm.action = `/transactions/${data.id}`;

                if (editTrxNumberDisplay) editTrxNumberDisplay.textContent = data.transaction_number || '';
                if (editTrxTotalDisplay) editTrxTotalDisplay.textContent = formatRupiah(activeEditTrxTotal);
                if (editTrxOutletTimeDisplay) editTrxOutletTimeDisplay.textContent = `${data.outlet_name || '-'} • ${data.sold_at || '-'}`;

                if (editPaymentMethod) {
                    const rawMethod = String(data.payment_method || data.payment_method_raw || 'Tunai').toLowerCase();
                    let targetVal = data.payment_method || 'Tunai';
                    if (rawMethod === 'cash' || rawMethod === 'tunai') targetVal = 'Tunai';
                    else if (rawMethod === 'qris') targetVal = 'QRIS';
                    else if (rawMethod === 'transfer') targetVal = 'Transfer';
                    else if (rawMethod === 'card' || rawMethod === 'kartu') targetVal = 'Kartu';
                    else if (rawMethod === 'debit') targetVal = 'Debit';

                    const hasOpt = Array.from(editPaymentMethod.options).some(opt => opt.value === targetVal);
                    if (!hasOpt && targetVal) {
                        const opt = document.createElement('option');
                        opt.value = targetVal;
                        opt.textContent = targetVal;
                        editPaymentMethod.appendChild(opt);
                    }
                    editPaymentMethod.value = targetVal;
                }

                const payRaw = String(data.payment_status_raw || '').toLowerCase();
                if (payRaw === 'unpaid') selectEditPaymentStatus('unpaid');
                else if (payRaw === 'refund' || payRaw === 'refunded') selectEditPaymentStatus('refund');
                else selectEditPaymentStatus('paid');

                const stRaw = String(data.status_raw || '').toLowerCase();
                const ordRaw = String(data.order_status_raw || '').toLowerCase();
                if (stRaw === 'cancelled' || stRaw === 'canceled' || ordRaw === 'batal') selectEditOrderStatus('batal');
                else if (stRaw === 'processing' || ordRaw === 'proses') selectEditOrderStatus('proses');
                else selectEditOrderStatus('siap');

                if (editCashReceived) editCashReceived.value = data.cash_received !== null && data.cash_received !== undefined ? data.cash_received : '';
                if (editNote) editNote.value = data.note || '';
                syncCashFields();

                editModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    editBackdrop?.classList.remove('opacity-0');
                    editBackdrop?.classList.add('opacity-100');
                    editDialog?.classList.remove('scale-95', 'opacity-0');
                    editDialog?.classList.add('scale-100', 'opacity-100');
                });
                refreshIcons();
            }

            function closeEditModal() {
                if (!editModal || editModal.classList.contains('hidden')) return;
                editBackdrop?.classList.remove('opacity-100');
                editBackdrop?.classList.add('opacity-0');
                editDialog?.classList.remove('scale-100', 'opacity-100');
                editDialog?.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    editModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }, 200);
            }

            if (closeEditModalBtn) closeEditModalBtn.onclick = closeEditModal;
            if (cancelEditModalBtn) cancelEditModalBtn.onclick = closeEditModal;
            if (editBackdrop) editBackdrop.onclick = closeEditModal;

            if (drawerEditBtn) {
                drawerEditBtn.onclick = () => {
                    const data = currentTrxData;
                    closeDrawer();
                    setTimeout(() => openEditModal(data), 220);
                };
            }

            if (editForm && editForm.dataset.dashSubmitBound !== 'true') {
                editForm.dataset.dashSubmitBound = 'true';
                editForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (!editForm.action) return;
                    if (submitEditTrxBtn) submitEditTrxBtn.disabled = true;

                    try {
                        const formData = new FormData(editForm);
                        const res = await fetch(editForm.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || 'Gagal memperbarui transaksi.');
                        }
                        closeEditModal();
                        if (typeof showToast === 'function') {
                            showToast('success', data.message || 'Transaksi berhasil diperbarui.');
                        }
                        // Refresh only the Recent Transactions table
                        const refreshBtn = document.getElementById('refreshRecentTransactionsBtn');
                        if (refreshBtn) refreshBtn.click();
                    } catch (err) {
                        if (typeof showToast === 'function') {
                            showToast('error', err.message || 'Terjadi kesalahan saat menyimpan.');
                        }
                    } finally {
                        if (submitEditTrxBtn) submitEditTrxBtn.disabled = false;
                    }
                });
            }

            // Event delegation on #recentTransactionsContainer (persists across AJAX refreshes!)
            container.addEventListener('click', (e) => {
                const detailBtn = e.target.closest('.dash-tx-detail-btn');
                const editBtn = e.target.closest('.dash-tx-edit-btn');
                if (!detailBtn && !editBtn) return;

                const row = (detailBtn || editBtn).closest('.dash-tx-row');
                if (!row) return;
                const raw = row.getAttribute('data-detail');
                if (!raw) return;

                try {
                    const data = JSON.parse(raw);
                    if (detailBtn) {
                        openDrawer(data);
                    } else if (editBtn) {
                        openEditModal(data);
                    }
                } catch (err) {
                    console.error('Failed to parse transaction detail payload', err);
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (editModal && !editModal.classList.contains('hidden')) {
                        closeEditModal();
                    } else if (drawerWrapper && !drawerWrapper.classList.contains('hidden')) {
                        closeDrawer();
                    }
                }
            });
        }

        // Global idempotent listeners
        if (!window.__dashboardListenersBound) {
            window.__dashboardListenersBound = true;

            document.addEventListener('DOMContentLoaded', () => {
                initDashboardSalesChart();
                initRecentTransactionsRefresh();
                initRecentTransactionsActions();
            });

            document.addEventListener('livewire:navigated', () => {
                initDashboardSalesChart();
                initRecentTransactionsRefresh();
                initRecentTransactionsActions();
            });

            document.addEventListener('livewire:navigating', () => {
                cleanupDashboard();
            });
        }

        // Theme sync hook
        if (window.__dashboardThemeObserver) {
            window.__dashboardThemeObserver.disconnect();
        }
        window.__dashboardThemeObserver = new MutationObserver(() => {
            initDashboardSalesChart();
        });
        window.__dashboardThemeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        // Immediate run
        initRecentTransactionsRefresh();
        initRecentTransactionsActions();
        setTimeout(initDashboardSalesChart, 150);
    </script>
    @endpush
</x-layouts::app>
