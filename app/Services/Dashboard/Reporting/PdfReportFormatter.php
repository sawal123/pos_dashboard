<?php

namespace App\Services\Dashboard\Reporting;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * PDF report export (DASH-13), rendered server-side with DomPDF.
 *
 * No headless browser is involved: DomPDF is pure PHP, works on shared hosting
 * and bundles the UTF-8 "DejaVu Sans" font. Remote resource loading is disabled
 * so user-controlled strings can never make the renderer fetch a URL.
 *
 * PDF is rendered in memory, so the dataset is capped more tightly than
 * CSV/XLSX (see config/reports.php).
 */
final class PdfReportFormatter extends ReportFormatter
{
    public function format(ReportDocument $document): string
    {
        $path = $this->tempFile('pdf');

        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setChroot(base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.pdf', ['document' => $document])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if (file_put_contents($path, (string) $dompdf->output()) === false) {
            throw new RuntimeException('Unable to write the temporary PDF export file.');
        }

        return $path;
    }
}
