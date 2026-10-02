<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformRevenueReportsData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueReportsController extends Controller
{
    public function __construct(
        protected PlatformRevenueReportsData $revenueData,
    ) {}

    /**
     * Display aggregate platform revenue and subscription billing reports.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['date', 'start_date', 'end_date']);
        $data = $this->revenueData->get($filters);

        return view('platform.revenue.index', $data);
    }
}
