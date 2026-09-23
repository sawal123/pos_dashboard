<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardOverviewData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardOverviewData $overviewData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        $hasCloudAccess = (bool) $request->attributes->get('dashboard_cloud_access', false);

        $rawPeriod = $request->query('period');
        $period = '7d';
        if (is_string($rawPeriod) && in_array($rawPeriod, ['7d', '30d', '3m', '12m'], true)) {
            $period = $rawPeriod;
        }

        $overview = $this->overviewData->get($currentBusiness, $period, $hasCloudAccess);

        return view('dashboard', [
            'overview' => $overview,
        ]);
    }
}
