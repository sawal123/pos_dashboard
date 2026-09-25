<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Outlet;
use App\Services\Dashboard\Reporting\ReportDatasetBuilder;
use App\Services\Dashboard\Reporting\ReportFilters;

/**
 * On-screen accounting report data (DASH-13).
 *
 * Filter normalisation and the accounting queries live in the shared
 * {@see ReportFilters} / {@see ReportDatasetBuilder} pair so the page and the
 * CSV/XLSX/PDF exports can never drift apart.
 */
class DashboardReportsData
{
    public function __construct(
        protected ReportDatasetBuilder $datasetBuilder,
    ) {}

    /**
     * Fetch accounting reports data scoped to the current business and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $reportFilters = ReportFilters::fromArray($filters);
        $currentFilters = $reportFilters->toArray();
        $periodLabel = $reportFilters->periodLabel();

        // Guard against null business (no tenant).
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
                'hasFilteredReportData' => false,
            ];
        }

        // Unlimited rows on the screen: the page renders a fixed summary plus
        // the same aggregated sections the exports use.
        $dataset = $this->datasetBuilder->build($currentBusiness, $reportFilters);

        $outlets = Outlet::where('business_id', (int) $currentBusiness->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        return [
            'summary' => $dataset->summary,
            'salesTrend' => $dataset->salesTrend,
            'paymentBreakdown' => $dataset->paymentBreakdown,
            'expenseBreakdown' => $dataset->expenseBreakdown,
            'filterOptions' => [
                'outlets' => $outlets,
            ],
            'currentFilters' => $currentFilters,
            'periodLabel' => $periodLabel,
            'hasAnyReportData' => $dataset->hasAnyReportData,
            'hasFilteredReportData' => $dataset->hasFilteredReportData,
        ];
    }
}
