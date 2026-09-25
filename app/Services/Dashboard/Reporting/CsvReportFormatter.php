<?php

namespace App\Services\Dashboard\Reporting;

use RuntimeException;

/**
 * Streamed CSV report export (DASH-13).
 *
 * Rows are written one at a time with `fputcsv`, so memory use stays flat
 * regardless of dataset size. Output is UTF-8 with a BOM.
 *
 * Formula-injection safety: only *text* fields (business/outlet names, payment
 * labels, expense categories, notes) are neutralised, by prefixing a single
 * quote when they start with a spreadsheet formula trigger. Numeric values are
 * written as numbers and never touched, so a legitimate negative amount keeps
 * its sign.
 */
final class CsvReportFormatter extends ReportFormatter
{
    public function format(ReportDocument $document): string
    {
        $path = $this->tempFile('csv');

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the temporary CSV export file.');
        }

        try {
            // UTF-8 BOM so Excel/LibreOffice detect the encoding.
            fwrite($handle, "\xEF\xBB\xBF");

            $this->write($handle, ['Laporan Penjualan & Pengeluaran']);
            foreach ($document->meta() as $meta) {
                $this->write($handle, [$meta['label'], $this->text($meta['value'])]);
            }

            $this->write($handle, []);
            $this->write($handle, ['Ringkasan']);
            $this->write($handle, ['Metrik', 'Nilai']);
            foreach ($document->summaryRows() as $summary) {
                $this->write($handle, [$summary['label'], $this->summaryValue($summary)]);
            }

            $this->write($handle, []);
            $this->write($handle, ['Tren Penjualan']);
            $this->write($handle, ['Tanggal', 'Total Penjualan', 'Jumlah Transaksi']);
            if ($document->dataset->salesTrend === []) {
                $this->write($handle, ['Tidak ada data', 0, 0]);
            }
            foreach ($document->dataset->salesTrend as $trend) {
                $this->write($handle, [
                    $this->text((string) $trend['date_raw']),
                    (int) $trend['total_sales'],
                    (int) $trend['transaction_count'],
                ]);
            }

            $this->write($handle, []);
            $this->write($handle, ['Breakdown Metode Pembayaran']);
            $this->write($handle, ['Metode Pembayaran', 'Jumlah Transaksi', 'Total Nilai', 'Persentase (%)']);
            if ($document->dataset->paymentBreakdown === []) {
                $this->write($handle, ['Tidak ada data', 0, 0, 0]);
            }
            foreach ($document->dataset->paymentBreakdown as $payment) {
                $this->write($handle, [
                    $this->text((string) $payment['payment_method']),
                    (int) $payment['transaction_count'],
                    (int) $payment['total_amount'],
                    (int) $payment['percentage'],
                ]);
            }

            $this->write($handle, []);
            $this->write($handle, ['Breakdown Kategori Pengeluaran']);
            $this->write($handle, ['Kategori', 'Jumlah Pengeluaran', 'Total Nilai', 'Persentase (%)']);
            if ($document->dataset->expenseBreakdown === []) {
                $this->write($handle, ['Tidak ada data', 0, 0, 0]);
            }
            foreach ($document->dataset->expenseBreakdown as $expense) {
                $this->write($handle, [
                    $this->text((string) $expense['category']),
                    (int) $expense['expense_count'],
                    (int) $expense['total_amount'],
                    (int) $expense['percentage'],
                ]);
            }

            $note = $document->note();
            if ($note !== null) {
                $this->write($handle, []);
                $this->write($handle, ['Catatan', $this->text($note)]);
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    /**
     * Neutralise text against spreadsheet formula injection.
     */
    private function text(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r\n]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @param  array{label: string, value: int|string, type: string}  $summary
     */
    private function summaryValue(array $summary): int|string
    {
        return $summary['type'] === 'integer' ? (int) $summary['value'] : (string) $summary['value'];
    }

    /**
     * @param  resource  $handle
     * @param  list<int|float|string>  $cells
     */
    private function write($handle, array $cells): void
    {
        // Explicit escape="" keeps RFC-4180 quoting (PHP's default "\" escape
        // produces output some spreadsheets misparse).
        fputcsv($handle, $cells, ',', '"', '');
    }
}
