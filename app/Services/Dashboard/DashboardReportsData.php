<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DashboardReportsData
{
    /**
     * Fetch accounting reports data scoped to the current business and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $currentFilters = [
            'date' => isset($filters['date']) && $filters['date'] !== '' ? (string) $filters['date'] : 'all',
            'start_date' => isset($filters['start_date']) ? (string) $filters['start_date'] : '',
            'end_date' => isset($filters['end_date']) ? (string) $filters['end_date'] : '',
            'outlet_id' => isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all'
                ? (int) $filters['outlet_id']
                : '',
        ];

        $periodLabel = $this->determinePeriodLabel($currentFilters['date'], $currentFilters);

        // 1. Guard against null business (no tenant)
        if ($currentBusiness === null) {
            return [
                'summary' => [
                    'total_sales' => 0,
                    'total_transactions' => 0,
                    'estimated_gross_profit' => '0.00',
                    'total_expenses' => 0,
                ],
                'salesTrend' => [],
                'paymentBreakdown' => [],
                'expenseBreakdown' => [],
                'filterOptions' => [
                    'outlets' => [],
                ],
                'currentFilters' => $currentFilters,
                'periodLabel' => $periodLabel,
                'hasAnyReportData' => false,
            ];
        }

        $businessId = (int) $currentBusiness->id;

        // 2. Unfiltered tenant presence check
        $hasAnyReportSales = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->exists();
        $hasAnyReportExpenses = Expense::where('business_id', $businessId)
            ->where('status', 'recorded')
            ->exists();
        $hasAnyReportData = $hasAnyReportSales || $hasAnyReportExpenses;

        // 3. Outlets filter options
        $outlets = Outlet::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        // 4. Base accounting scoped queries
        $salesQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid');
        $this->applySalesFilters($salesQuery, $currentFilters);

        $expenseQuery = Expense::where('business_id', $businessId)
            ->where('status', 'recorded');
        $this->applyExpenseFilters($expenseQuery, $currentFilters);

        // 5. Summary metrics
        $totalSales = (int) (clone $salesQuery)->sum('total_amount');
        $totalTransactions = (int) (clone $salesQuery)->count();
        $estimatedGrossProfitSum = (float) (clone $salesQuery)->sum('gross_profit');
        $estimatedGrossProfit = number_format($estimatedGrossProfitSum, 2, '.', '');
        $totalExpenses = (int) (clone $expenseQuery)->sum('amount');

        $summary = [
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'estimated_gross_profit' => $estimatedGrossProfit,
            'total_expenses' => $totalExpenses,
        ];

        // 6. Sales Trend (grouped by calendar date of sold_at)
        $trendRows = (clone $salesQuery)
            ->selectRaw('DATE(sold_at) as date_raw, SUM(total_amount) as total_sales, COUNT(id) as transaction_count')
            ->groupBy('date_raw')
            ->orderByDesc('date_raw')
            ->get();

        $salesTrend = [];
        foreach ($trendRows as $row) {
            $dateRaw = (string) $row->getAttribute('date_raw');
            $salesTrend[] = [
                'date_raw' => $dateRaw,
                'date' => Carbon::parse($dateRaw)->translatedFormat('d M Y'),
                'total_sales' => (int) $row->getAttribute('total_sales'),
                'transaction_count' => (int) $row->getAttribute('transaction_count'),
            ];
        }

        // 7. Payment Methods Breakdown (group by payment_method)
        $paymentRows = (clone $salesQuery)
            ->selectRaw('payment_method, COUNT(id) as transaction_count, SUM(total_amount) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('transaction_count')
            ->get();

        $paymentBreakdown = [];
        foreach ($paymentRows as $row) {
            $raw = $row->payment_method !== null ? (string) $row->payment_method : null;
            $normalized = $raw !== null ? strtolower(trim($raw)) : null;
            $label = match ($normalized) {
                'cash', 'tunai' => 'Tunai',
                'qris' => 'QRIS',
                'transfer' => 'Transfer',
                'card', 'kartu' => 'Kartu',
                null, '' => 'Tidak Diketahui',
                default => ucwords(str_replace('_', ' ', $raw)),
            };

            $count = (int) $row->getAttribute('transaction_count');
            $amount = (int) $row->getAttribute('total_amount');
            $percentage = $totalTransactions > 0
                ? (int) round(($count / $totalTransactions) * 100)
                : 0;

            $paymentBreakdown[] = [
                'payment_method_raw' => $raw,
                'payment_method' => $label,
                'transaction_count' => $count,
                'total_amount' => $amount,
                'percentage' => $percentage,
            ];
        }

        // 8. Expense Categories Breakdown (group by category)
        $expenseCatRows = (clone $expenseQuery)
            ->selectRaw('category, COUNT(id) as expense_count, SUM(amount) as total_amount')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        $expenseBreakdown = [];
        foreach ($expenseCatRows as $row) {
            $raw = $row->category !== null ? (string) $row->category : null;
            $label = $raw !== null && trim($raw) !== '' ? $raw : 'Tanpa Kategori';

            $count = (int) $row->getAttribute('expense_count');
            $amount = (int) $row->getAttribute('total_amount');
            $percentage = $totalExpenses > 0
                ? (int) round(($amount / $totalExpenses) * 100)
                : 0;

            $expenseBreakdown[] = [
                'category_raw' => $raw,
                'category' => $label,
                'expense_count' => $count,
                'total_amount' => $amount,
                'percentage' => $percentage,
            ];
        }

        return [
            'summary' => $summary,
            'salesTrend' => $salesTrend,
            'paymentBreakdown' => $paymentBreakdown,
            'expenseBreakdown' => $expenseBreakdown,
            'filterOptions' => [
                'outlets' => $outlets,
            ],
            'currentFilters' => $currentFilters,
            'periodLabel' => $periodLabel,
            'hasAnyReportData' => $hasAnyReportData,
        ];
    }

    /**
     * Apply date range and outlet filter to Sale query.
     *
     * @param  Builder<Sale>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySalesFilters(Builder $query, array $filters): void
    {
        $date = isset($filters['date']) ? (string) $filters['date'] : 'all';
        if ($date !== 'all') {
            $this->applyDateFilter($query, 'sold_at', $date, $filters);
        }

        if (isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all') {
            $query->where('outlet_id', (int) $filters['outlet_id']);
        }
    }

    /**
     * Apply date range and outlet filter to Expense query.
     *
     * @param  Builder<Expense>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyExpenseFilters(Builder $query, array $filters): void
    {
        $date = isset($filters['date']) ? (string) $filters['date'] : 'all';
        if ($date !== 'all') {
            $this->applyDateFilter($query, 'occurred_at', $date, $filters);
        }

        if (isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== 'all') {
            $query->where('outlet_id', (int) $filters['outlet_id']);
        }
    }

    /**
     * Apply date range filter using the specified date column.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter(Builder $query, string $column, string $date, array $filters): void
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();

        match ($date) {
            'today' => $query->whereBetween($column, [
                $today->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '7d', '7days' => $query->whereBetween($column, [
                $today->copy()->subDays(6)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            '30d', '30days' => $query->whereBetween($column, [
                $today->copy()->subDays(29)->toDateTimeString(),
                $today->copy()->endOfDay()->toDateTimeString(),
            ]),
            'custom' => $this->applyCustomDateFilter($query, $column, $filters, $tz),
            default => null,
        };
    }

    /**
     * Apply custom date range filter with safe start/end swap.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCustomDateFilter(Builder $query, string $column, array $filters, string $tz): void
    {
        $startRaw = isset($filters['start_date']) ? trim((string) $filters['start_date']) : '';
        $endRaw = isset($filters['end_date']) ? trim((string) $filters['end_date']) : '';

        if ($startRaw === '' && $endRaw === '') {
            return;
        }

        $startDate = null;
        if ($startRaw !== '') {
            try {
                $startDate = Carbon::createFromFormat('Y-m-d', $startRaw, $tz)?->startOfDay();
            } catch (\Throwable) {
                $startDate = null;
            }
        }

        $endDate = null;
        if ($endRaw !== '') {
            try {
                $endDate = Carbon::createFromFormat('Y-m-d', $endRaw, $tz)?->endOfDay();
            } catch (\Throwable) {
                $endDate = null;
            }
        }

        if ($startDate === null && $endDate === null) {
            return;
        }

        // Safe swap if start > end
        if ($startDate !== null && $endDate !== null && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        if ($startDate !== null) {
            $query->where($column, '>=', $startDate->toDateTimeString());
        }
        if ($endDate !== null) {
            $query->where($column, '<=', $endDate->toDateTimeString());
        }
    }

    /**
     * Determine user-friendly period label.
     *
     * @param  array<string, mixed>  $filters
     */
    private function determinePeriodLabel(string $date, array $filters): string
    {
        return match ($date) {
            'today' => 'Hari Ini',
            '7d', '7days' => '7 Hari Terakhir',
            '30d', '30days' => '30 Hari Terakhir',
            'custom' => 'Periode Kustom',
            default => 'Semua Waktu',
        };
    }
}
