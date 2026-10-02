<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformUsageAnalyticsData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsageAnalyticsController extends Controller
{
    public function __construct(
        protected PlatformUsageAnalyticsData $analyticsData,
    ) {}

    /**
     * Display aggregate platform usage analytics across merchants.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['date', 'start_date', 'end_date']);
        $data = $this->analyticsData->get($filters);

        return view('platform.analytics.index', $data);
    }
}
