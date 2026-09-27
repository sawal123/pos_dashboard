<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report export limits (DASH-13)
    |--------------------------------------------------------------------------
    |
    | Hard caps on how many *aggregated* rows a single export section may
    | contain. Report exports are grouped (per day / payment method / expense
    | category), so these limits are only reachable with a pathologically long
    | date range. When a section reaches its cap the export still completes,
    | but it carries an explicit "truncated" notice instead of silently
    | returning a partial file.
    |
    | CSV/XLSX are streamed, so they can carry more rows than the PDF, which
    | DomPDF renders in memory.
    |
    */

    'export_max_rows' => (int) env('REPORT_EXPORT_MAX_ROWS', 5000),

    'pdf_max_rows' => (int) env('REPORT_PDF_MAX_ROWS', 1500),

];
