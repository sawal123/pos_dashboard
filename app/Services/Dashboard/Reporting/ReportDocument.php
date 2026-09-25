<?php

namespace App\Services\Dashboard\Reporting;

/**
 * Format-agnostic export document (DASH-13).
 *
 * Carries the report identity, the summary and the aggregated sections exactly
 * as they are shown on the reports page, so CSV, XLSX and PDF cannot diverge.
 */
final class ReportDocument
{
    public function __construct(
        public readonly string $businessName,
        public readonly string $outletName,
        public readonly string $periodLabel,
        public readonly string $rangeLabel,
        public readonly string $generatedAt,
        public readonly string $timezone,
        public readonly ReportDataset $dataset,
    ) {}

    /**
     * Report identity block.
     *
     * @return list<array{label: string, value: string}>
     */
    public function meta(): array
    {
        return [
            ['label' => 'Bisnis', 'value' => $this->businessName],
            ['label' => 'Outlet', 'value' => $this->outletName],
            ['label' => 'Periode', 'value' => $this->periodLabel],
            ['label' => 'Rentang Tanggal', 'value' => $this->rangeLabel],
            ['label' => 'Dibuat', 'value' => $this->generatedAt],
            ['label' => 'Timezone', 'value' => $this->timezone],
        ];
    }

    /**
     * Summary metrics with an explicit value type so numeric formats can be
     * applied in spreadsheets without re-parsing display strings.
     *
     * @return list<array{label: string, value: int|string, type: 'integer'|'currency'|'decimal'}>
     */
    public function summaryRows(): array
    {
        return [
            ['label' => 'Total Penjualan', 'value' => $this->dataset->summary['total_sales'], 'type' => 'currency'],
            ['label' => 'Total Transaksi', 'value' => $this->dataset->summary['total_transactions'], 'type' => 'integer'],
            ['label' => 'Estimasi Laba Kotor', 'value' => $this->dataset->summary['estimated_gross_profit'], 'type' => 'decimal'],
            ['label' => 'Total Pengeluaran', 'value' => $this->dataset->summary['total_expenses'], 'type' => 'currency'],
        ];
    }

    /**
     * Truncation notice, or null when the dataset was exported in full.
     */
    public function note(): ?string
    {
        if (! $this->dataset->truncated) {
            return null;
        }

        return 'Data dipotong pada batas '.number_format($this->dataset->rowLimit, 0, ',', '.')
            .' baris per bagian. Persempit periode atau outlet untuk laporan lengkap.';
    }

    public function isEmpty(): bool
    {
        return ! $this->dataset->hasFilteredReportData;
    }
}
