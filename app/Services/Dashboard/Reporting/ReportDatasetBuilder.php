<?php

namespace App\Services\Dashboard\Reporting;

use App\Models\Business;
use App\Models\Expense;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the report dataset for a business + filter combination.
 *
 * This is the single source of truth for the accounting rules used by both the
 * on-screen report and the CSV/XLSX/PDF exports:
 *
 *  - Sales revenue: `status = completed` AND `payment_status = paid`, dated by
 *    `sold_at`.
 *  - Gross profit: the historical `sales.gross_profit` snapshot column (never a
 *    lookup of the current product price/HPP).
 *  - Expenses: `status = recorded`, dated by `occurred_at`.
 *
 * Canceled / void / unpaid sales and non-recorded expenses are therefore
 * excluded by construction.
 */
final class ReportDatasetBuilder
{
    /**
     * @param  int|null  $rowLimit  max aggregated rows per section (null = unlimited, used by the screen)
     */
    public function build(Business $business, ReportFilters $filters, ?int $rowLimit = null): ReportDataset
    {
        $businessId = (int) $business->id;
        $window = $filters->window();
        $truncated = false;

        $hasAnyReportData = $this->hasAnyReportData($businessId);

        $salesQuery = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid');
        $this->applyFilters($salesQuery, 'sold_at', $filters, $window);

        $expenseQuery = Expense::query()
            ->where('business_id', $businessId)
            ->where('status', 'recorded');
        $this->applyFilters($expenseQuery, 'occurred_at', $filters, $window);

        // Summary metrics (identical formulas to the on-screen report).
        $totalSales = (int) (clone $salesQuery)->sum('total_amount');
        $totalTransactions = (int) (clone $salesQuery)->count();
        $estimatedGrossProfit = number_format((float) (clone $salesQuery)->sum('gross_profit'), 2, '.', '');
        $totalExpenses = (int) (clone $expenseQuery)->sum('amount');

        $summary = [
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'estimated_gross_profit' => $estimatedGrossProfit,
            'total_expenses' => $totalExpenses,
        ];

        $salesTrend = $this->salesTrend(clone $salesQuery, $rowLimit, $truncated);
        $paymentBreakdown = $this->paymentBreakdown(clone $salesQuery, $totalTransactions, $rowLimit, $truncated);
        $expenseBreakdown = $this->expenseBreakdown(clone $expenseQuery, $totalExpenses, $rowLimit, $truncated);

        return new ReportDataset(
            summary: $summary,
            salesTrend: $salesTrend,
            paymentBreakdown: $paymentBreakdown,
            expenseBreakdown: $expenseBreakdown,
            hasAnyReportData: $hasAnyReportData,
            hasFilteredReportData: $totalTransactions > 0 || $expenseBreakdown !== [],
            truncated: $truncated,
            rowLimit: $rowLimit ?? 0,
        );
    }

    private function hasAnyReportData(int $businessId): bool
    {
        $hasSales = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->exists();

        $hasExpenses = Expense::query()
            ->where('business_id', $businessId)
            ->where('status', 'recorded')
            ->exists();

        return $hasSales || $hasExpenses;
    }

    /**
     * @param  Builder<Sale>  $salesQuery
     * @return list<array<string, mixed>>
     */
    private function salesTrend(Builder $salesQuery, ?int $rowLimit, bool &$truncated): array
    {
        $query = $salesQuery
            ->selectRaw('DATE(sold_at) as date_raw, SUM(total_amount) as total_sales, COUNT(id) as transaction_count')
            ->groupBy('date_raw')
            ->orderByDesc('date_raw');

        $this->limitQuery($query, $rowLimit);
        $rows = $this->trimRows($query->get(), $rowLimit, $truncated);

        $trend = [];
        foreach ($rows as $row) {
            $dateRaw = (string) $row->getAttribute('date_raw');
            $trend[] = [
                'date_raw' => $dateRaw,
                'date' => Carbon::parse($dateRaw)->translatedFormat('d M Y'),
                'total_sales' => (int) $row->getAttribute('total_sales'),
                'transaction_count' => (int) $row->getAttribute('transaction_count'),
            ];
        }

        return $trend;
    }

    /**
     * @param  Builder<Sale>  $salesQuery
     * @return list<array<string, mixed>>
     */
    private function paymentBreakdown(Builder $salesQuery, int $totalTransactions, ?int $rowLimit, bool &$truncated): array
    {
        $query = $salesQuery
            ->selectRaw('payment_method, COUNT(id) as transaction_count, SUM(total_amount) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('transaction_count');

        $this->limitQuery($query, $rowLimit);
        $rows = $this->trimRows($query->get(), $rowLimit, $truncated);

        $breakdown = [];
        foreach ($rows as $row) {
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

            $breakdown[] = [
                'payment_method_raw' => $raw,
                'payment_method' => $label,
                'transaction_count' => $count,
                'total_amount' => $amount,
                'percentage' => $totalTransactions > 0 ? (int) round(($count / $totalTransactions) * 100) : 0,
            ];
        }

        return $breakdown;
    }

    /**
     * @param  Builder<Expense>  $expenseQuery
     * @return list<array<string, mixed>>
     */
    private function expenseBreakdown(Builder $expenseQuery, int $totalExpenses, ?int $rowLimit, bool &$truncated): array
    {
        $query = $expenseQuery
            ->selectRaw('category, COUNT(id) as expense_count, SUM(amount) as total_amount')
            ->groupBy('category')
            ->orderByDesc('total_amount');

        $this->limitQuery($query, $rowLimit);
        $rows = $this->trimRows($query->get(), $rowLimit, $truncated);

        $breakdown = [];
        foreach ($rows as $row) {
            $raw = $row->category !== null ? (string) $row->category : null;
            $amount = (int) $row->getAttribute('total_amount');

            $breakdown[] = [
                'category_raw' => $raw,
                'category' => $raw !== null && trim($raw) !== '' ? $raw : 'Tanpa Kategori',
                'expense_count' => (int) $row->getAttribute('expense_count'),
                'total_amount' => $amount,
                'percentage' => $totalExpenses > 0 ? (int) round(($amount / $totalExpenses) * 100) : 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Fetch one row beyond the limit so truncation can be detected, then drop
     * the extra row and flag the dataset as truncated.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $rows
     * @return Collection<TKey, TValue>
     */
    private function trimRows(Collection $rows, ?int $rowLimit, bool &$truncated): Collection
    {
        if ($rowLimit === null || $rows->count() <= $rowLimit) {
            return $rows;
        }

        $truncated = true;

        return $rows->take($rowLimit);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function limitQuery(Builder $query, ?int $rowLimit): void
    {
        if ($rowLimit !== null) {
            $query->limit($rowLimit + 1);
        }
    }

    /**
     * Apply the resolved date window and outlet filter to a scoped query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{start: Carbon|null, end: Carbon|null}  $window
     */
    private function applyFilters(Builder $query, string $column, ReportFilters $filters, array $window): void
    {
        if ($window['start'] !== null) {
            $query->where($column, '>=', $window['start']->toDateTimeString());
        }

        if ($window['end'] !== null) {
            $query->where($column, '<=', $window['end']->toDateTimeString());
        }

        if ($filters->outletId !== null) {
            $query->where('outlet_id', $filters->outletId);
        }
    }
}
