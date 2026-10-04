<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformOperationalAlerts;
use App\Services\Platform\PlatformOverviewQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected PlatformOverviewQuery $overviewQuery,
        protected PlatformOperationalAlerts $alertsService
    ) {}

    /**
     * Display the Platform Admin overview dashboard.
     */
    public function index(Request $request): View
    {
        $alertsData = $this->alertsService->topAlerts(5);

        return view('platform.dashboard', array_merge(
            [
                'user' => $request->user(),
                'alertsSummary' => $alertsData['summary'],
                'topAlerts' => $alertsData['top_alerts'],
            ],
            $this->overviewQuery->get()
        ));
    }
}
