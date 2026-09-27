<?php

namespace App\Services\Dashboard\Reporting;

use RuntimeException;

/**
 * Shared helpers for the report export formatters (DASH-13).
 */
abstract class ReportFormatter
{
    /** Temp-file prefix; exports never touch the web root. */
    private const TMP_PREFIX = 'nexapos-report-';

    /**
     * Render the document to a temporary file and return its path. The caller
     * streams (and then deletes) it.
     */
    abstract public function format(ReportDocument $document): string;

    protected function tempFile(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), self::TMP_PREFIX);

        if ($base === false) {
            throw new RuntimeException('Unable to allocate a temporary report export file.');
        }

        $target = $base.'.'.$extension;

        if (@rename($base, $target)) {
            return $target;
        }

        return $base;
    }
}
