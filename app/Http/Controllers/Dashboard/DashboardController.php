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

        $period = (string) $request->query('period', '7d');
        if (! in_array($period, ['7d', '30d', '3m', '12m'], true)) {
            $period = '7d';
        }

        $overview = $this->overviewData->get($currentBusiness, $period, $hasCloudAccess);

        return view('dashboard', [
            'overview' => $overview,
        ]);
    }
}
