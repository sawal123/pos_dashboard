<?php

namespace App\Services\Dashboard\Reporting;

use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orchestrates report exports (DASH-13).
 *
 * The tenant always comes from the caller's active-business context, never from
 * a request parameter, and every format is built from the same
 * {@see ReportDatasetBuilder} dataset so the download matches the screen.
 */
final class ReportExportService
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    public const FORMAT_PDF = 'pdf';

    public function __construct(
        private readonly ReportDatasetBuilder $datasetBuilder,
        private readonly CsvReportFormatter $csvFormatter,
        private readonly XlsxReportFormatter $xlsxFormatter,
        private readonly PdfReportFormatter $pdfFormatter,
    ) {}

    /**
     * @throws InvalidArgumentException on an unknown format
     */
    public function export(Business $business, ReportFilters $filters, string $format): ReportExportResult
    {
        $format = strtolower($format);
        $document = $this->buildDocument($business, $filters, $this->rowLimitFor($format));

        $path = match ($format) {
            self::FORMAT_CSV => $this->csvFormatter->format($document),
            self::FORMAT_XLSX => $this->xlsxFormatter->format($document),
            self::FORMAT_PDF => $this->pdfFormatter->format($document),
            default => throw new InvalidArgumentException("Unsupported report export format [{$format}]."),
        };

        return new ReportExportResult(
            path: $path,
            filename: $this->filename($business, $filters, $format),
            contentType: $this->contentType($format),
        );
    }

    /**
     * Build the format-agnostic export document (also used directly by tests).
     */
    public function buildDocument(Business $business, ReportFilters $filters, int $rowLimit): ReportDocument
    {
        $timezone = (string) config('app.timezone', 'UTC');

        return new ReportDocument(
            businessName: (string) $business->name,
            outletName: $this->outletName($business, $filters),
            periodLabel: $filters->periodLabel(),
            rangeLabel: $filters->rangeLabel(),
            generatedAt: Carbon::now($timezone)->translatedFormat('d M Y H:i:s'),
            timezone: $timezone,
            dataset: $this->datasetBuilder->build($business, $filters, max(1, $rowLimit)),
        );
    }

    public static function contentType(string $format): string
    {
        return match (strtolower($format)) {
            self::FORMAT_CSV => 'text/csv; charset=UTF-8',
            self::FORMAT_XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::FORMAT_PDF => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function rowLimitFor(string $format): int
    {
        return $format === self::FORMAT_PDF
            ? (int) config('reports.pdf_max_rows', 1500)
            : (int) config('reports.export_max_rows', 5000);
    }

    private function outletName(Business $business, ReportFilters $filters): string
    {
        if ($filters->outletId === null) {
            return 'Semua Outlet';
        }

        $name = Outlet::query()
            ->where('business_id', $business->id)
            ->whereKey($filters->outletId)
            ->value('name');

        return is_string($name) && $name !== '' ? $name : 'Outlet tidak ditemukan';
    }

    private function filename(Business $business, ReportFilters $filters, string $format): string
    {
        $businessSlug = Str::slug((string) $business->name) ?: 'bisnis';
        $periodSlug = Str::slug($filters->periodLabel()) ?: 'periode';

        return sprintf(
            'laporan-%s-%s-%s.%s',
            $businessSlug,
            $periodSlug,
            Carbon::now()->format('Ymd-His'),
            $format,
        );
    }
}
