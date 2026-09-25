<?php

namespace App\Services\Dashboard\Reporting;

/**
 * Immutable report dataset shared by the on-screen report and every export
 * format. Both paths therefore read the exact same numbers (DASH-13).
 */
final class ReportDataset
{
    /**
     * @param  array<string, int|string>  $summary
     * @param  list<array<string, mixed>>  $salesTrend
     * @param  list<array<string, mixed>>  $paymentBreakdown
     * @param  list<array<string, mixed>>  $expenseBreakdown
     */
    public function __construct(
        public readonly array $summary,
        public readonly array $salesTrend,
        public readonly array $paymentBreakdown,
        public readonly array $expenseBreakdown,
        public readonly bool $hasAnyReportData,
        public readonly bool $hasFilteredReportData,
        public readonly bool $truncated = false,
        public readonly int $rowLimit = 0,
    ) {}

    /**
     * Screen-compatible array shape (used by DashboardReportsData).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'salesTrend' => $this->salesTrend,
            'paymentBreakdown' => $this->paymentBreakdown,
            'expenseBreakdown' => $this->expenseBreakdown,
            'hasAnyReportData' => $this->hasAnyReportData,
            'hasFilteredReportData' => $this->hasFilteredReportData,
        ];
    }
}
