<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardOverviewData
{
    /**
     * Build all aggregated dashboard overview data scoped to current business.
     *
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, string $period = '7d', bool $hasCloudAccess = false): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $currentDateFormatted = $this->formatIndonesianDate($now, 'l, d F Y');

        if (! in_array($period, ['7d', '30d', '3m', '12m'], true)) {
            $period = '7d';
        }

        // 1. Guard against null business (no tenant context)
        // No queries executed against any business models
        if ($currentBusiness === null) {
            return $this->emptyOverview($period, $currentDateFormatted, $hasCloudAccess);
        }

        $businessId = (int) $currentBusiness->id;

        // 2. Dashboard Header Data
        $openShiftsCount = Shift::where('business_id', $businessId)
            ->where('status', 'open')
            ->count();
        $openShiftsLabel = $openShiftsCount > 0
            ? "{$openShiftsCount} Shift Terbuka"
            : 'Tidak ada shift terbuka';

        // 3. KPI Penjualan Hari Ini (accounting scope: completed + paid)
        $todayStart = $now->copy()->startOfDay()->toDateTimeString();
        $todayEnd = $now->copy()->endOfDay()->toDateTimeString();

        $todaySalesQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('sold_at', [$todayStart, $todayEnd]);

        $todaySalesTotal = (int) (clone $todaySalesQuery)->sum('total_amount');
        $todayTransactionsCount = (int) (clone $todaySalesQuery)->count();
        $todayGrossProfitSum = (float) (clone $todaySalesQuery)->sum('gross_profit');
        $todayGrossProfitRaw = number_format($todayGrossProfitSum, 2, '.', '');

        $averageTransaction = $todayTransactionsCount > 0
            ? (int) round($todaySalesTotal / $todayTransactionsCount)
            : 0;

        // KPI Peringatan Stok
        $stockAlertsKpiCount = Product::where('business_id', $businessId)
            ->where('kind', 'product')
            ->where('status', '!=', 'deleted')
            ->where('stock', '<=', DB::raw('min_stock'))
            ->count();

        // 4. Sales Overview Chart & Insight Footer
        $chartData = $this->buildSalesChart($businessId, $period, $now);

        // 5. Stock Alert Panel (Top 5 items)
        $stockPanel = $this->buildStockPanel($businessId, $stockAlertsKpiCount);

        // 6. Ringkasan Pergerakan Kas Hari Ini
        $cashSummary = $this->buildCashSummary($businessId, $todayStart, $todayEnd, $openShiftsCount, $openShiftsLabel);

        // 7. Status Cloud & Sinkronisasi
        $cloudPanel = $this->buildCloudPanel($businessId, $hasCloudAccess);

        // 8. Transaksi Terbaru (Preview 8)
        $recentTransactions = $this->buildRecentTransactions($businessId);

        return [
            'business_name' => $currentBusiness->name,
            'current_date_formatted' => $currentDateFormatted,
            'open_shifts_count' => $openShiftsCount,
            'open_shifts_label' => $openShiftsLabel,

            'kpi' => [
                'today_sales' => [
                    'value' => 'Rp '.number_format($todaySalesTotal, 0, ',', '.'),
                    'raw' => $todaySalesTotal,
                    'subtitle' => 'Transaksi selesai dan terbayar hari ini.',
                ],
                'today_transactions' => [
                    'value' => number_format($todayTransactionsCount, 0, ',', '.').' Transaksi',
                    'raw' => $todayTransactionsCount,
                    'subtitle' => 'Rata-rata per transaksi: Rp '.number_format($averageTransaction, 0, ',', '.'),
                ],
                'estimated_gross_profit' => [
                    'value' => 'Rp '.number_format($todayGrossProfitSum, $todayGrossProfitSum == (int) $todayGrossProfitSum ? 0 : 2, ',', '.'),
                    'raw' => $todayGrossProfitRaw,
                    'subtitle' => 'Diambil dari laba kotor historis transaksi.',
                ],
                'stock_alerts' => [
                    'value' => "{$stockAlertsKpiCount} Item",
                    'raw' => $stockAlertsKpiCount,
                    'subtitle' => $stockAlertsKpiCount > 0
                        ? "{$stockAlertsKpiCount} SKU inventori di bawah batas minimum"
                        : 'Semua item inventori berada di atas batas minimum.',
                ],
            ],

            'sales_chart' => $chartData,
            'stock_panel' => $stockPanel,
            'cash_summary' => $cashSummary,
            'cloud_panel' => $cloudPanel,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Build chart intervals, labels, and footer insight for given period.
     *
     * @return array<string, mixed>
     */
    private function buildSalesChart(int $businessId, string $period, Carbon $now): array
    {
        switch ($period) {
            case '30d':
                $startDate = $now->copy()->subDays(29)->startOfDay();
                $endDate = $now->copy()->endOfDay();
                $periodLabel = '30 Hari Terakhir';
                $granularity = 'daily';
                $averageLabel = 'Rata-rata per Hari';
                break;
            case '3m':
                $startDate = $now->copy()->subMonthsNoOverflow(2)->startOfMonth()->startOfDay();
                $endDate = $now->copy()->endOfMonth()->endOfDay();
                $periodLabel = '3 Bulan Terakhir';
                $granularity = 'monthly';
                $averageLabel = 'Rata-rata per Bulan';
                break;
            case '12m':
                $startDate = $now->copy()->subMonthsNoOverflow(11)->startOfMonth()->startOfDay();
                $endDate = $now->copy()->endOfMonth()->endOfDay();
                $periodLabel = '1 Tahun Penuh';
                $granularity = 'monthly';
                $averageLabel = 'Rata-rata per Bulan';
                break;
            case '7d':
            default:
                $period = '7d';
                $startDate = $now->copy()->subDays(6)->startOfDay();
                $endDate = $now->copy()->endOfDay();
                $periodLabel = '7 Hari Terakhir';
                $granularity = 'daily';
                $averageLabel = 'Rata-rata per Hari';
                break;
        }

        // Query daily aggregates from database (SQLite and MySQL compatible)
        $rawRows = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('sold_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->selectRaw('DATE(sold_at) as date_raw, SUM(total_amount) as total_sales, COUNT(id) as transaction_count')
            ->groupByRaw('DATE(sold_at)')
            ->get()
            ->keyBy('date_raw');

        // Zero-fill all calendar intervals
        $intervals = [];

        if ($granularity === 'daily') {
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dateRaw = $cursor->format('Y-m-d');
                $label = $this->formatIndonesianDate($cursor, 'd M');
                $totalSales = isset($rawRows[$dateRaw]) ? (int) $rawRows[$dateRaw]->getAttribute('total_sales') : 0;
                $txCount = isset($rawRows[$dateRaw]) ? (int) $rawRows[$dateRaw]->getAttribute('transaction_count') : 0;

                $intervals[] = [
                    'date_raw' => $dateRaw,
                    'label' => $label,
                    'total_sales' => $totalSales,
                    'transaction_count' => $txCount,
                ];

                $cursor->addDay();
            }
        } else {
            $cursor = $startDate->copy()->startOfMonth();
            $targetEnd = $endDate->copy()->startOfMonth();
            while ($cursor->lte($targetEnd)) {
                $monthKey = $cursor->format('Y-m');
                $dateRaw = $cursor->format('Y-m-01');
                $label = $this->formatIndonesianDate($cursor, 'M Y');

                $monthSales = 0;
                $monthTxCount = 0;
                foreach ($rawRows as $rowDate => $row) {
                    if (str_starts_with((string) $rowDate, $monthKey)) {
                        $monthSales += (int) $row->getAttribute('total_sales');
                        $monthTxCount += (int) $row->getAttribute('transaction_count');
                    }
                }

                $intervals[] = [
                    'date_raw' => $dateRaw,
                    'label' => $label,
                    'total_sales' => $monthSales,
                    'transaction_count' => $monthTxCount,
                ];

                $cursor->addMonthNoOverflow();
            }
        }

        // Puncak Omzet
        $peakInterval = null;
        $maxSales = 0;
        foreach ($intervals as $interval) {
            if ($interval['total_sales'] > $maxSales) {
                $maxSales = $interval['total_sales'];
                $peakInterval = $interval;
            }
        }

        $peakSales = ($peakInterval !== null && $maxSales > 0)
            ? "{$peakInterval['label']} (Rp ".number_format($maxSales, 0, ',', '.').')'
            : '-';

        // Rata-rata per Hari / Bulan
        $totalPeriodSales = array_sum(array_column($intervals, 'total_sales'));
        $intervalCount = count($intervals);
        $averageSales = $intervalCount > 0 ? (int) round($totalPeriodSales / $intervalCount) : 0;
        $averageSalesFormatted = 'Rp '.number_format($averageSales, 0, ',', '.');

        // Metode Pembayaran Terbanyak (berdasarkan COUNT Sale)
        $paymentMethodRows = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('sold_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->selectRaw('payment_method, COUNT(id) as count')
            ->groupBy('payment_method')
            ->orderByDesc('count')
            ->get();

        $totalPeriodTransactions = (int) $paymentMethodRows->sum('count');
        $topPaymentMethod = '-';

        if ($totalPeriodTransactions > 0 && $paymentMethodRows->isNotEmpty()) {
            $topRow = $paymentMethodRows->first();
            $topCount = (int) $topRow->getAttribute('count');
            $pct = (int) round(($topCount / $totalPeriodTransactions) * 100);
            $topLabel = self::presentPaymentMethod($topRow->payment_method);
            $topPaymentMethod = "{$topLabel} ({$pct}%)";
        }

        return [
            'period' => $period,
            'period_label' => $periodLabel,
            'labels' => array_column($intervals, 'label'),
            'data' => array_column($intervals, 'total_sales'),
            'intervals' => $intervals,
            'insight' => [
                'peak_sales' => $peakSales,
                'average_sales' => $averageSalesFormatted,
                'average_label' => $averageLabel,
                'top_payment_method' => $topPaymentMethod,
            ],
        ];
    }

    /**
     * Build top 5 stock alert items.
     *
     * @return array<string, mixed>
     */
    private function buildStockPanel(int $businessId, int $totalAlertCount): array
    {
        $hasPhysicalProducts = Product::where('business_id', $businessId)
            ->where('kind', 'product')
            ->where('status', '!=', 'deleted')
            ->exists();

        $rawProducts = Product::where('business_id', $businessId)
            ->where('kind', 'product')
            ->where('status', '!=', 'deleted')
            ->where('stock', '<=', DB::raw('min_stock'))
            ->orderByRaw('CASE WHEN stock < 0 THEN 1 WHEN stock = 0 THEN 2 ELSE 3 END ASC')
            ->orderBy('stock', 'asc')
            ->orderBy('id', 'asc')
            ->limit(5)
            ->get();

        $items = $rawProducts->map(function (Product $product) {
            $stock = (float) $product->stock;
            $minStock = (float) $product->min_stock;

            if ($stock < 0) {
                $status = 'Minus';
                $statusKey = 'negative';
            } elseif ($stock == 0) {
                $status = 'Habis';
                $statusKey = 'empty';
            } else {
                $status = 'Menipis';
                $statusKey = 'low';
            }

            $percentage = $minStock > 0 ? (int) min(100, max(0, round(($stock / $minStock) * 100))) : 0;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => (string) $product->stock,
                'min_stock' => (string) $product->min_stock,
                'unit' => $product->unit ?? 'pcs',
                'status' => $status,
                'status_key' => $statusKey,
                'percentage' => $percentage,
            ];
        })->toArray();

        return [
            'items' => $items,
            'total_alert_count' => $totalAlertCount,
            'has_physical_products' => $hasPhysicalProducts,
        ];
    }

    /**
     * Build cash movement summary for today.
     *
     * @return array<string, mixed>
     */
    private function buildCashSummary(int $businessId, string $todayStart, string $todayEnd, int $openShiftsCount, string $openShiftsLabel): array
    {
        $cashIn = (int) CashLedger::where('business_id', $businessId)
            ->where('type', 'in')
            ->whereBetween('occurred_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $cashOut = (int) CashLedger::where('business_id', $businessId)
            ->where('type', 'out')
            ->whereBetween('occurred_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $netMovement = $cashIn - $cashOut;

        $recordedExpense = (int) Expense::where('business_id', $businessId)
            ->where('status', 'recorded')
            ->whereBetween('occurred_at', [$todayStart, $todayEnd])
            ->sum('amount');

        return [
            'cash_in' => 'Rp '.number_format($cashIn, 0, ',', '.'),
            'cash_in_raw' => $cashIn,
            'cash_out' => 'Rp '.number_format($cashOut, 0, ',', '.'),
            'cash_out_raw' => $cashOut,
            'net_movement' => ($netMovement < 0 ? '-Rp ' : 'Rp ').number_format(abs($netMovement), 0, ',', '.'),
            'net_movement_raw' => $netMovement,
            'recorded_expense' => 'Rp '.number_format($recordedExpense, 0, ',', '.'),
            'recorded_expense_raw' => $recordedExpense,
            'open_shifts_count' => $openShiftsCount,
            'open_shifts_label' => $openShiftsLabel,
        ];
    }

    /**
     * Build cloud and device monitoring metrics.
     *
     * @return array<string, mixed>
     */
    private function buildCloudPanel(int $businessId, bool $hasCloudAccess): array
    {
        $serverSequence = (int) (SyncCounter::where('business_id', $businessId)->value('current_sequence') ?? 0);
        $totalPushRequests = SyncRequest::where('business_id', $businessId)->count();
        $devicesWithPush = SyncRequest::where('business_id', $businessId)->distinct('device_id')->count('device_id');
        $maxProcessedAt = SyncRequest::where('business_id', $businessId)->max('processed_at');
        $totalRegisteredDevices = Device::where('business_id', $businessId)->count();

        $lastProcessedFormatted = 'Belum ada push yang tercatat.';
        if ($maxProcessedAt !== null) {
            $lastProcessedFormatted = $this->formatIndonesianDate(Carbon::parse($maxProcessedAt), 'd M Y · H:i');
        }

        return [
            'server_sequence' => $serverSequence,
            'total_push_requests' => $totalPushRequests,
            'devices_with_push' => $devicesWithPush,
            'last_processed_at' => $lastProcessedFormatted,
            'total_registered_devices' => $totalRegisteredDevices,
            'has_cloud_access' => $hasCloudAccess,
            'cloud_access_label' => $hasCloudAccess ? 'Akses Cloud aktif' : 'Tidak ada akses cloud',
        ];
    }

    /**
     * Build latest 8 transactions preview.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentTransactions(int $businessId): array
    {
        $sales = Sale::where('business_id', $businessId)
            ->with(['customer', 'outlet'])
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        return $sales->map(function (Sale $sale) {
            // Customer name: snapshot first, then relation, then 'Pelanggan Umum'
            $customerName = 'Pelanggan Umum';
            if (is_array($sale->customer_snapshot) && ! empty($sale->customer_snapshot['name'])) {
                $customerName = (string) $sale->customer_snapshot['name'];
            } elseif ($sale->customer?->name !== null && trim($sale->customer->name) !== '') {
                $customerName = $sale->customer->name;
            }

            // Outlet name: snapshot first, then relation, then '-'
            $outletName = '-';
            if (is_array($sale->business_snapshot) && ! empty($sale->business_snapshot['outlet'])) {
                $outletName = (string) $sale->business_snapshot['outlet'];
            } elseif ($sale->outlet?->name !== null && trim($sale->outlet->name) !== '') {
                $outletName = $sale->outlet->name;
            }

            $soldAtCarbon = Carbon::parse($sale->sold_at);

            return [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'sold_at' => $this->formatIndonesianDate($soldAtCarbon, 'd M Y H:i'),
                'sold_at_time' => $soldAtCarbon->format('H:i').' WIB',
                'sold_at_raw' => $sale->sold_at,
                'customer_name' => $customerName,
                'outlet_name' => $outletName,
                'payment_method' => self::presentPaymentMethod($sale->payment_method),
                'payment_method_raw' => $sale->payment_method,
                'payment_status' => self::presentPaymentStatus($sale->payment_status),
                'payment_status_raw' => $sale->payment_status,
                'status' => self::presentStatus($sale->status),
                'status_raw' => $sale->status,
                'total_amount' => 'Rp '.number_format($sale->total_amount, 0, ',', '.'),
                'total_amount_raw' => $sale->total_amount,
            ];
        })->toArray();
    }

    /**
     * Map raw payment method to human-readable label.
     */
    public static function presentPaymentMethod(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'Tidak Diketahui';
        }

        return match (strtolower(trim($raw))) {
            'cash', 'tunai' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card', 'kartu' => 'Kartu',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Map raw payment status to human-readable label.
     */
    public static function presentPaymentStatus(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'Tidak Diketahui';
        }

        return match (strtolower(trim($raw))) {
            'paid' => 'Lunas',
            'unpaid' => 'Belum Lunas',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Map raw sale status to human-readable label.
     */
    public static function presentStatus(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'Tidak Diketahui';
        }

        return match (strtolower(trim($raw))) {
            'completed' => 'Selesai',
            'cancelled', 'canceled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    /**
     * Helper to safely format Indonesian date on Carbon instance.
     */
    private function formatIndonesianDate(Carbon $carbon, string $format): string
    {
        $c = Carbon::instance($carbon);
        $c->setLocale('id');

        return $c->translatedFormat($format);
    }

    /**
     * Null-safe empty overview when no business context is selected.
     *
     * @return array<string, mixed>
     */
    private function emptyOverview(string $period, string $currentDateFormatted, bool $hasCloudAccess): array
    {
        $periodLabel = match ($period) {
            '30d' => '30 Hari Terakhir',
            '3m' => '3 Bulan Terakhir',
            '12m' => '1 Tahun Penuh',
            default => '7 Hari Terakhir',
        };

        $averageLabel = in_array($period, ['3m', '12m'], true)
            ? 'Rata-rata per Bulan'
            : 'Rata-rata per Hari';

        return [
            'business_name' => 'Belum Ada Bisnis',
            'current_date_formatted' => $currentDateFormatted,
            'open_shifts_count' => 0,
            'open_shifts_label' => 'Tidak ada shift terbuka',

            'kpi' => [
                'today_sales' => [
                    'value' => 'Rp 0',
                    'raw' => 0,
                    'subtitle' => 'Transaksi selesai dan terbayar hari ini.',
                ],
                'today_transactions' => [
                    'value' => '0 Transaksi',
                    'raw' => 0,
                    'subtitle' => 'Rata-rata per transaksi: Rp 0',
                ],
                'estimated_gross_profit' => [
                    'value' => 'Rp 0',
                    'raw' => '0.00',
                    'subtitle' => 'Diambil dari laba kotor historis transaksi.',
                ],
                'stock_alerts' => [
                    'value' => '0 Item',
                    'raw' => 0,
                    'subtitle' => 'Semua item inventori berada di atas batas minimum.',
                ],
            ],

            'sales_chart' => [
                'period' => $period,
                'period_label' => $periodLabel,
                'labels' => [],
                'data' => [],
                'intervals' => [],
                'insight' => [
                    'peak_sales' => '-',
                    'average_sales' => 'Rp 0',
                    'average_label' => $averageLabel,
                    'top_payment_method' => '-',
                ],
            ],

            'stock_panel' => [
                'items' => [],
                'total_alert_count' => 0,
                'has_physical_products' => false,
            ],

            'cash_summary' => [
                'cash_in' => 'Rp 0',
                'cash_in_raw' => 0,
                'cash_out' => 'Rp 0',
                'cash_out_raw' => 0,
                'net_movement' => 'Rp 0',
                'net_movement_raw' => 0,
                'recorded_expense' => 'Rp 0',
                'recorded_expense_raw' => 0,
                'open_shifts_count' => 0,
                'open_shifts_label' => 'Tidak ada shift terbuka',
            ],

            'cloud_panel' => [
                'server_sequence' => 0,
                'total_push_requests' => 0,
                'devices_with_push' => 0,
                'last_processed_at' => 'Belum ada push yang tercatat.',
                'total_registered_devices' => 0,
                'has_cloud_access' => $hasCloudAccess,
                'cloud_access_label' => $hasCloudAccess ? 'Akses Cloud aktif' : 'Tidak ada akses cloud',
            ],

            'recent_transactions' => [],
        ];
    }
}
