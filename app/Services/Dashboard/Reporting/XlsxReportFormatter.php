<?php

namespace App\Services\Dashboard\Reporting;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * XLSX report export (DASH-13), written with OpenSpout.
 *
 * Cells are created explicitly: every database/user-controlled value is a
 * {@see StringCell} and every number is a {@see NumericCell}. This is required
 * for formula-injection safety — OpenSpout's `Cell::fromValue()` would treat a
 * string beginning with `=` as a real spreadsheet formula.
 */
final class XlsxReportFormatter extends ReportFormatter
{
    public function format(ReportDocument $document): string
    {
        $path = $this->tempFile('xlsx');

        $titleStyle = (new Style)->setFontBold()->setFontSize(14);
        $sectionStyle = (new Style)->setFontBold()->setFontSize(12)->setBackgroundColor('EEF2FF');
        $headerStyle = (new Style)->setFontBold()->setBackgroundColor('E2E8F0');
        $integerStyle = (new Style)->setFormat('0');
        $currencyStyle = (new Style)->setFormat('#,##0');
        $decimalStyle = (new Style)->setFormat('#,##0.00');

        $writer = new Writer(new Options);
        $writer->openToFile($path);

        // ── Sheet 1: Ringkasan ────────────────────────────────────────────
        $summarySheet = $writer->getCurrentSheet();
        $summarySheet->setName('Ringkasan');
        $summarySheet->setColumnWidth(30, 1);
        $summarySheet->setColumnWidth(28, 2);

        $writer->addRow($this->textRow(['Laporan Penjualan & Pengeluaran'], $titleStyle));

        foreach ($document->meta() as $meta) {
            $writer->addRow(new Row([
                new StringCell($meta['label'], $headerStyle),
                new StringCell($meta['value'], null),
            ]));
        }

        $writer->addRow(new Row([]));
        $writer->addRow($this->textRow(['Ringkasan'], $sectionStyle));
        $writer->addRow($this->textRow(['Metrik', 'Nilai'], $headerStyle));

        foreach ($document->summaryRows() as $summary) {
            $style = match ($summary['type']) {
                'integer' => $integerStyle,
                'decimal' => $decimalStyle,
                default => $currencyStyle,
            };

            $writer->addRow(new Row([
                new StringCell($summary['label'], null),
                new NumericCell($this->summaryNumeric($summary), $style),
            ]));
        }

        $note = $document->note();
        if ($note !== null) {
            $writer->addRow(new Row([]));
            $writer->addRow($this->textRow([$note], $headerStyle));
        }

        // ── Sheet 2: Tren Penjualan ───────────────────────────────────────
        $trendSheet = $writer->addNewSheetAndMakeItCurrent();
        $trendSheet->setName('Tren Penjualan');
        $trendSheet->setColumnWidth(18, 1);
        $trendSheet->setColumnWidth(20, 2);
        $trendSheet->setColumnWidth(18, 3);

        $writer->addRow($this->textRow(['Tanggal', 'Total Penjualan', 'Jumlah Transaksi'], $headerStyle));
        if ($document->dataset->salesTrend === []) {
            $writer->addRow($this->textRow(['Tidak ada data']));
        }
        foreach ($document->dataset->salesTrend as $trend) {
            $writer->addRow(new Row([
                new StringCell((string) $trend['date_raw'], null),
                new NumericCell((float) $trend['total_sales'], $currencyStyle),
                new NumericCell((int) $trend['transaction_count'], $integerStyle),
            ]));
        }

        // ── Sheet 3: Metode Pembayaran ────────────────────────────────────
        $paymentSheet = $writer->addNewSheetAndMakeItCurrent();
        $paymentSheet->setName('Metode Pembayaran');
        $paymentSheet->setColumnWidth(24, 1);
        $paymentSheet->setColumnWidth(18, 2);
        $paymentSheet->setColumnWidth(18, 3);
        $paymentSheet->setColumnWidth(16, 4);

        $writer->addRow($this->textRow(['Metode Pembayaran', 'Jumlah Transaksi', 'Total Nilai', 'Persentase (%)'], $headerStyle));
        if ($document->dataset->paymentBreakdown === []) {
            $writer->addRow($this->textRow(['Tidak ada data']));
        }
        foreach ($document->dataset->paymentBreakdown as $payment) {
            $writer->addRow(new Row([
                new StringCell((string) $payment['payment_method'], null),
                new NumericCell((int) $payment['transaction_count'], $integerStyle),
                new NumericCell((float) $payment['total_amount'], $currencyStyle),
                new NumericCell((int) $payment['percentage'], $integerStyle),
            ]));
        }

        // ── Sheet 4: Pengeluaran ──────────────────────────────────────────
        $expenseSheet = $writer->addNewSheetAndMakeItCurrent();
        $expenseSheet->setName('Pengeluaran');
        $expenseSheet->setColumnWidth(28, 1);
        $expenseSheet->setColumnWidth(18, 2);
        $expenseSheet->setColumnWidth(18, 3);
        $expenseSheet->setColumnWidth(16, 4);

        $writer->addRow($this->textRow(['Kategori', 'Jumlah Pengeluaran', 'Total Nilai', 'Persentase (%)'], $headerStyle));
        if ($document->dataset->expenseBreakdown === []) {
            $writer->addRow($this->textRow(['Tidak ada data']));
        }
        foreach ($document->dataset->expenseBreakdown as $expense) {
            $writer->addRow(new Row([
                new StringCell((string) $expense['category'], null),
                new NumericCell((int) $expense['expense_count'], $integerStyle),
                new NumericCell((float) $expense['total_amount'], $currencyStyle),
                new NumericCell((int) $expense['percentage'], $integerStyle),
            ]));
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<string>  $values
     */
    private function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            static fn (string $value): Cell => new StringCell($value, $style),
            $values,
        ));
    }

    /**
     * @param  array{label: string, value: int|string, type: string}  $summary
     */
    private function summaryNumeric(array $summary): int|float
    {
        return match ($summary['type']) {
            'integer' => (int) $summary['value'],
            default => (float) $summary['value'],
        };
    }
}
