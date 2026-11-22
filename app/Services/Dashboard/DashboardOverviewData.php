<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
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
    public function get(?Business $currentBusiness, string $period = '7d', bool $hasCloudAccess = false, int|string|null $outletId = null): array
    {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');
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
        $numericOutletId = (is_numeric($outletId) && (int) $outletId > 0) ? (int) $outletId : null;

        $selectedOutletName = 'Seluruh Cabang (Konsolidasi)';
        if ($numericOutletId !== null) {
            $outlet = Outlet::where('business_id', $businessId)->where('id', $numericOutletId)->first();
            if ($outlet) {
                $selectedOutletName = $outlet->name . ($outlet->is_primary ? ' (Utama)' : '');
            }
        }

        // 2. Dashboard Header Data
        $openShiftsQuery = Shift::where('business_id', $businessId)
            ->where('status', 'open');
        if ($numericOutletId !== null) {
            $openShiftsQuery->where('outlet_id', $numericOutletId);
        }
        $openShiftsCount = $openShiftsQuery->count();

        $openShiftsLabel = $openShiftsCount > 0
            ? "{$openShiftsCount} Shift Terbuka"
            : 'Tidak ada shift terbuka';

        // 3. KPI Penjualan Hari Ini (accounting scope: completed + paid)
        $todayStart = $now->copy()->startOfDay()->toDateTimeString();
        $todayEnd = $now->copy()->endOfDay()->toDateTimeString();

        $todaySalesQuery = Sale::where('business_id', $businessId)
            ->whereIn(DB::raw('LOWER(status)'), ['completed', 'paid', 'siap'])
            ->where(function ($q) {
                $q->whereNull('order_status')
                    ->orWhereNotIn(DB::raw('LOWER(order_status)'), ['batal', 'cancelled', 'canceled', 'dibatalkan']);
            })
            ->whereIn(DB::raw('LOWER(payment_status)'), ['paid', 'lunas'])
            ->whereBetween('sold_at', [$todayStart, $todayEnd]);
        if ($numericOutletId !== null) {
            $todaySalesQuery->where('outlet_id', $numericOutletId);
        }

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

        // 4. Sales Overview Chart & Insight Footer (scoped to selected outlet)
        $chartData = $this->buildSalesChart($businessId, $period, $now, $numericOutletId);

        // 5. Stock Alert Panel (Top 5 items)
        $stockPanel = $this->buildStockPanel($businessId, $stockAlertsKpiCount);

        // 6. Ringkasan Pergerakan Kas Hari Ini (scoped to selected outlet)
        $cashSummary = $this->buildCashSummary($businessId, $todayStart, $todayEnd, $openShiftsCount, $openShiftsLabel, $numericOutletId);

        // 7. Status Cloud & Sinkronisasi
        $cloudPanel = $this->buildCloudPanel($businessId, $hasCloudAccess);

        // 8. Transaksi Terbaru (Preview 8, scoped to selected outlet)
        $recentTransactions = $this->buildRecentTransactions($businessId, $numericOutletId);

        return [
            'business_name' => $currentBusiness->name,
            'current_date_formatted' => $currentDateFormatted,
            'open_shifts_count' => $openShiftsCount,
            'open_shifts_label' => $openShiftsLabel,
            'selected_outlet_id' => $numericOutletId,
            'selected_outlet_name' => $selectedOutletName,

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
    private function buildSalesChart(int $businessId, string $period, Carbon $now, ?int $outletId = null): array
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
        $salesQuery = Sale::where('business_id', $businessId)
            ->whereIn(DB::raw('LOWER(status)'), ['completed', 'paid', 'siap'])
            ->where(function ($q) {
                $q->whereNull('order_status')
                    ->orWhereNotIn(DB::raw('LOWER(order_status)'), ['batal', 'cancelled', 'canceled', 'dibatalkan']);
            })
            ->whereIn(DB::raw('LOWER(payment_status)'), ['paid', 'lunas'])
            ->whereBetween('sold_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()]);
        if ($outletId !== null) {
            $salesQuery->where('outlet_id', $outletId);
        }

        $rawRows = $salesQuery
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
        $paymentQuery = Sale::where('business_id', $businessId)
            ->whereIn(DB::raw('LOWER(status)'), ['completed', 'paid', 'siap'])
            ->where(function ($q) {
                $q->whereNull('order_status')
                    ->orWhereNotIn(DB::raw('LOWER(order_status)'), ['batal', 'cancelled', 'canceled', 'dibatalkan']);
            })
            ->whereIn(DB::raw('LOWER(payment_status)'), ['paid', 'lunas'])
            ->whereBetween('sold_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()]);
        if ($outletId !== null) {
            $paymentQuery->where('outlet_id', $outletId);
        }
        $paymentMethodRows = $paymentQuery
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
    private function buildCashSummary(int $businessId, string $todayStart, string $todayEnd, int $openShiftsCount, string $openShiftsLabel, ?int $outletId = null): array
    {
        $cashInQuery = CashLedger::where('business_id', $businessId)
            ->where('type', 'in')
            ->whereBetween('occurred_at', [$todayStart, $todayEnd]);

        $cashOutQuery = CashLedger::where('business_id', $businessId)
            ->where('type', 'out')
            ->whereBetween('occurred_at', [$todayStart, $todayEnd]);

        $expenseQuery = Expense::where('business_id', $businessId)
            ->where('status', 'recorded')
            ->whereBetween('occurred_at', [$todayStart, $todayEnd]);

        if ($outletId !== null) {
            $cashInQuery->where('outlet_id', $outletId);
            $cashOutQuery->where('outlet_id', $outletId);
            $expenseQuery->where('outlet_id', $outletId);
        }

        $cashIn = (int) $cashInQuery->sum('amount');
        $cashOut = (int) $cashOutQuery->sum('amount');
        $netMovement = $cashIn - $cashOut;
        $recordedExpense = (int) $expenseQuery->sum('amount');

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
    private function buildRecentTransactions(int $businessId, ?int $outletId = null): array
    {
        $salesQuery = Sale::where('business_id', $businessId)
            ->with(['customer', 'outlet', 'shift', 'items'])
            ->orderBy('sold_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(8);

        if ($outletId !== null) {
            $salesQuery->where('outlet_id', $outletId);
        }

        $sales = $salesQuery->get();

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

            $shiftName = '-';
            if ($sale->shift !== null) {
                $shiftLabel = trim((string) ($sale->shift->cashier_name ?? ''));
                $shiftName = $shiftLabel !== '' ? $shiftLabel : 'Shift #'.$sale->shift->id;
            }

            $tz = (string) config('app.timezone', 'Asia/Jakarta');
            $soldAtCarbon = Carbon::parse($sale->sold_at)->setTimezone($tz);
            $soldAtFormatted = $this->formatIndonesianDate($soldAtCarbon, 'd M Y H:i');
            $paidAtFormatted = $sale->paid_at
                ? $this->formatIndonesianDate(Carbon::parse($sale->paid_at)->setTimezone($tz), 'd M Y H:i')
                : null;
            $estimatedCompletedAt = $sale->estimated_completed_at
                ? $this->formatIndonesianDate(Carbon::parse($sale->estimated_completed_at)->setTimezone($tz), 'd M Y H:i')
                : null;

            $paymentMethodLabel = self::presentPaymentMethod($sale->payment_method);
            $paymentStatusLabel = self::presentPaymentStatus($sale->payment_status);
            $statusLabel = self::presentStatus($sale->status);

            $statusLower = strtolower(trim((string) ($sale->status ?? '')));
            $orderStatusLower = strtolower(trim((string) ($sale->order_status ?? '')));
            $isCancelled = in_array($statusLower, ['cancelled', 'canceled', 'batal', 'dibatalkan'], true)
                || in_array($orderStatusLower, ['cancelled', 'canceled', 'batal', 'dibatalkan'], true);
            $isProses = ! $isCancelled && (
                in_array($orderStatusLower, ['proses', 'diproses', 'masuk', 'processing'], true)
                || in_array($statusLower, ['processing', 'proses'], true)
            );

            $items = $sale->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'variant_summary' => $item->variant_summary,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'kind' => $item->kind,
                'pricing_unit' => $item->pricing_unit,
                'line_total' => $item->line_total,
                'cost_snapshot' => $item->cost_snapshot,
                'line_cost' => $item->line_cost,
            ])->values()->toArray();

            $detailPayload = [
                'id' => $sale->id,
                'outlet_id' => $sale->outlet_id,
                'transaction_number' => $sale->transaction_number,
                'sold_at' => $soldAtFormatted,
                'sold_at_raw' => $sale->sold_at,
                'outlet_name' => $outletName,
                'shift_name' => $shiftName,
                'customer_name' => $customerName,
                'table_number' => $sale->table_number,
                'payment_method_raw' => $sale->payment_method,
                'payment_method' => $paymentMethodLabel,
                'payment_status_raw' => $sale->payment_status,
                'payment_status' => $paymentStatusLabel,
                'status_raw' => $sale->status,
                'status' => $statusLabel,
                'subtotal' => $sale->subtotal,
                'discount_amount' => $sale->discount_amount,
                'tax_amount' => $sale->tax_amount,
                'total_amount' => $sale->total_amount,
                'paid_at' => $paidAtFormatted,
                'cash_received' => $sale->cash_received,
                'change_amount' => $sale->change_amount,
                'gross_profit' => $sale->gross_profit,
                'note' => $sale->note,
                'order_status' => $sale->order_status,
                'order_status_raw' => $sale->order_status,
                'estimated_completed_at' => $estimatedCompletedAt,
                'items' => $items,
            ];

            return [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'sold_at' => $soldAtFormatted,
                'sold_at_time' => $soldAtCarbon->format('H:i').' WIB',
                'sold_at_raw' => $sale->sold_at,
                'customer_name' => $customerName,
                'table_number' => $sale->table_number,
                'outlet_name' => $outletName,
                'payment_method' => $paymentMethodLabel,
                'payment_method_raw' => $sale->payment_method,
                'payment_status' => $paymentStatusLabel,
                'payment_status_raw' => $sale->payment_status,
                'status' => $statusLabel,
                'status_raw' => $sale->status,
                'order_status_raw' => $sale->order_status,
                'is_proses' => $isProses,
                'total_amount' => 'Rp '.number_format($sale->total_amount, 0, ',', '.'),
                'total_amount_raw' => $sale->total_amount,
                'detail' => $detailPayload,
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
