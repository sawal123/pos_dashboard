<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardOverviewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardOverviewData $overviewData
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        $hasCloudAccess = (bool) $request->attributes->get('dashboard_cloud_access', false);

        // Keep the dashboard route reachable as a conversion surface, but do
        // not load or expose operational data until the business has an active
        // Cloud subscription. The sidebar and billing path remain available so
        // the owner can review the offer and subscribe.
        if ($currentBusiness !== null && ! $hasCloudAccess) {
            return view('dashboard-locked', [
                'businessName' => $currentBusiness->name,
            ]);
        }

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
