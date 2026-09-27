<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardReportsData;
use App\Services\Dashboard\Reporting\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(
        protected DashboardReportsData $reportsData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        $filters = $request->only([
            'date',
            'start_date',
            'end_date',
            'outlet_id',
        ]);

        // Shared with the export endpoints via ReportFilters (DASH-13); the
        // page keeps its "drop invalid filters, never 500" behaviour.
        $validator = Validator::make($filters, ReportFilters::rules());

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->reportsData->get($currentBusiness, $filters);

        return view('reports.index', $data);
    }
}
