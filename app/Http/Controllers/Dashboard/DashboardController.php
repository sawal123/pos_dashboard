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
        $role = $request->attributes->get('dashboard_business_role');

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

        // Branch / Outlet scoping for dashboard & sales chart
        $activeOutlet = $request->attributes->get('dashboard_active_outlet');
        $rawOutlet = $request->query('outlet_id');
        $selectedOutletId = null;

        if ($rawOutlet !== null) {
            if ($rawOutlet === 'all' || $rawOutlet === '0' || $rawOutlet === '') {
                $selectedOutletId = null;
                session(['dashboard.active_outlet_id' => 'all', 'dashboard.outlet_user_selected' => true]);
                $activeOutlet = null;
            } else {
                $outletMatch = $currentBusiness?->outlets()->where('id', (int) $rawOutlet)->where('status', 'active')->first();
                if ($outletMatch) {
                    $selectedOutletId = (int) $outletMatch->id;
                    session(['dashboard.active_outlet_id' => $selectedOutletId, 'dashboard.outlet_user_selected' => true]);
                    $activeOutlet = $outletMatch;
                }
            }
        } elseif (session()->get('dashboard.outlet_user_selected') === true && session('dashboard.active_outlet_id') !== 'all') {
            $selectedOutletId = (int) session('dashboard.active_outlet_id');
        } elseif ($role === \App\Models\Business::ROLE_CASHIER && $activeOutlet) {
            $selectedOutletId = (int) $activeOutlet->id;
        }

        $overview = $this->overviewData->get($currentBusiness, $period, $hasCloudAccess, $selectedOutletId);

        $outlets = $currentBusiness
            ? $currentBusiness->outlets()->where('status', 'active')->orderByDesc('is_primary')->orderBy('id')->get()
            : collect();

        return view('dashboard', [
            'overview' => $overview,
            'selectedOutletId' => $selectedOutletId,
            'activeOutlet' => $activeOutlet,
            'outlets' => $outlets,
        ]);
    }
}
