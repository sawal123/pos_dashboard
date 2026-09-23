<x-layouts::app :title="__('Dashboard')">
    <main id="mainContent" data-dashboard-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== DASHBOARD HEADER ==================== --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                        <i data-lucide="store" class="w-3.5 h-3.5"></i>
                        <span id="businessNameLabel">{{ $overview['business_name'] }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-medium">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span id="outletLabel">Seluruh Outlet</span>
                    </span>
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
                <button type="button" id="newTransactionBtn" onclick="showToast('info', 'Transaksi baru dari dashboard belum tersedia.')" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs md:text-sm font-semibold transition-all flex items-center justify-center gap-2 shadow-sm hover:shadow-indigo-500/20">
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
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tren omzet pendapatan berdasarkan periode</p>
                            </div>
                        </div>

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
                                    href="{{ route('dashboard', ['period' => $periodKey]) }}"
                                    class="px-2.5 py-1.5 rounded-lg transition-all {{ $currentPeriod === $periodKey ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-xs font-semibold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                                >
                                    {{ $periodText }}
                                </a>
                            @endforeach
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
                    <a href="{{ route('dashboard', ['period' => $overview['sales_chart']['period']]) }}" class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition-colors" title="Muat Ulang Transaksi" aria-label="Refresh Data">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>

            {{-- Content --}}
            @if(empty($overview['recent_transactions']))
                <div class="px-5 py-12 text-center flex flex-col items-center justify-center">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3 text-slate-400 dark:text-slate-500 border border-slate-200/60 dark:border-slate-700/60">
                        <i data-lucide="receipt" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 tracking-tight">Belum Ada Transaksi</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-sm mt-1 mb-4 leading-relaxed">
                        Data transaksi penjualan akan muncul di sini setelah register kasir mencatat penjualan.
                    </p>
                    <a href="{{ route('transactions.index') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline text-xs flex items-center gap-1">
                        Buka Halaman Transaksi <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            @else
                {{-- 1. Desktop & Tablet Table View --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs md:text-sm border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/30 text-slate-500 dark:text-slate-400 font-semibold uppercase text-[11px] tracking-wider">
                                <th class="px-4 py-3.5">Invoice</th>
                                <th class="px-4 py-3.5">Waktu</th>
                                <th class="px-4 py-3.5">Pelanggan</th>
                                <th class="px-4 py-3.5">Outlet</th>
                                <th class="px-4 py-3.5">Pembayaran</th>
                                <th class="px-4 py-3.5 text-right">Total</th>
                                <th class="px-4 py-3.5">Status Bayar</th>
                                <th class="px-4 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            @foreach($overview['recent_transactions'] as $tx)
                                <tr class="table-row hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-4 py-3.5 font-semibold text-slate-900 dark:text-white">
                                        {{ $tx['transaction_number'] }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                        {{ $tx['sold_at'] }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300 font-medium">
                                        {{ $tx['customer_name'] }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                        {{ $tx['outlet_name'] }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-medium">
                                            {{ $tx['payment_method'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                                        {{ $tx['total_amount'] }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if($tx['payment_status_raw'] === 'paid')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                                {{ $tx['payment_status'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60">
                                                {{ $tx['payment_status'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        @if($tx['status_raw'] === 'completed')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                                                {{ $tx['status'] }}
                                            </span>
                                        @elseif(in_array($tx['status_raw'], ['cancelled', 'canceled'], true))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/80 dark:border-rose-800/60">
                                                {{ $tx['status'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                {{ $tx['status'] }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- 2. Mobile Responsive Card View --}}
                <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-800/80 p-2">
                    @foreach($overview['recent_transactions'] as $tx)
                        <div class="p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                <div>
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $tx['transaction_number'] }}</span>
                                    <span class="text-[11px] text-slate-400 ml-1.5">{{ $tx['sold_at'] }}</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900 dark:text-white tabular-nums">{{ $tx['total_amount'] }}</span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 mb-2">
                                <span class="font-medium">{{ $tx['customer_name'] }}</span>
                                <span class="text-[11px] text-slate-400">Outlet: {{ $tx['outlet_name'] }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100 dark:border-slate-800/60 text-xs">
                                <span class="text-slate-500">{{ $tx['payment_method'] }}</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tx['payment_status_raw'] === 'paid' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400' }}">
                                        {{ $tx['payment_status'] }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $tx['status_raw'] === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : (in_array($tx['status_raw'], ['cancelled', 'canceled'], true) ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                                        {{ $tx['status'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Footer --}}
                <div class="px-5 py-3.5 border-t border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <p>Menampilkan {{ count($overview['recent_transactions']) }} transaksi terbaru</p>
                    <a href="{{ route('transactions.index') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                        Lihat Semua Transaksi <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            @endif
        </div>

    </main>

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
        }

        function cleanupDashboard() {
            if (window.salesChartInstance) {
                window.salesChartInstance.destroy();
                window.salesChartInstance = null;
            }
            if (window.__dashboardThemeObserver) {
                window.__dashboardThemeObserver.disconnect();
                window.__dashboardThemeObserver = null;
            }
        }

        // Global idempotent listeners
        if (!window.__dashboardListenersBound) {
            window.__dashboardListenersBound = true;

            document.addEventListener('DOMContentLoaded', () => {
                initDashboardSalesChart();
            });

            document.addEventListener('livewire:navigated', () => {
                initDashboardSalesChart();
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
        setTimeout(initDashboardSalesChart, 150);
    </script>
    @endpush
</x-layouts::app>
