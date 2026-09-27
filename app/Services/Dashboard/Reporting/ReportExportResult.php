<?php

namespace App\Services\Dashboard\Reporting;

/**
 * A generated export file on disk, ready to be streamed to the client.
 *
 * The file always lives in the system temp directory — never under `public/` —
 * so a failed or cancelled download cannot leave report data web-accessible.
 */
final class ReportExportResult
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly string $contentType,
    ) {}

    public function size(): int
    {
        return (int) (@filesize($this->path) ?: 0);
    }
}
