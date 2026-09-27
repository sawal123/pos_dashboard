<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReportExportRequest;
use App\Services\Dashboard\Reporting\ReportExportService;
use App\Services\Dashboard\Reporting\ReportFilters;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Report export endpoints (DASH-13).
 *
 * Every format is guarded by `business.permission:reports.view` (see
 * routes/web.php), so cashiers and unknown roles cannot download reports. The
 * tenant always comes from the shared dashboard context.
 */
class ReportExportController extends Controller
{
    public function __construct(
        private readonly ReportExportService $exports,
    ) {}

    public function csv(ReportExportRequest $request): BinaryFileResponse
    {
        return $this->download($request, ReportExportService::FORMAT_CSV);
    }

    public function xlsx(ReportExportRequest $request): BinaryFileResponse
    {
        return $this->download($request, ReportExportService::FORMAT_XLSX);
    }

    public function pdf(ReportExportRequest $request): BinaryFileResponse
    {
        return $this->download($request, ReportExportService::FORMAT_PDF);
    }

    private function download(ReportExportRequest $request, string $format): BinaryFileResponse
    {
        $business = $request->activeBusiness();

        // `authorize()` already fails closed, but never export without a tenant.
        if ($business === null) {
            abort(403);
        }

        $filters = ReportFilters::fromArray($request->validated());
        $result = $this->exports->export($business, $filters, $format);

        return response()
            ->download($result->path, $result->filename, ['Content-Type' => $result->contentType])
            ->deleteFileAfterSend(true);
    }
}
