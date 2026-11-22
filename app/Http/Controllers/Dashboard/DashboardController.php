<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardOverviewData;
use App\Services\Payment\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardOverviewData $overviewData,
        protected PaymentMethodService $paymentMethodService
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

        [$canEdit, $canAddItems] = $this->resolveTransactionPermissions($request, $currentBusiness);

        $paymentMethods = $currentBusiness
            ? $this->paymentMethodService->ensureDefaults($currentBusiness)
            : collect();

        return view('dashboard', [
            'overview' => $overview,
            'selectedOutletId' => $selectedOutletId,
            'activeOutlet' => $activeOutlet,
            'outlets' => $outlets,
            'canEdit' => $canEdit,
            'canAddItems' => $canAddItems,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * AJAX partial refresh for the Recent Transactions table only (without reloading the whole Dashboard page).
     */
    public function recentTransactions(Request $request): JsonResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');
        $hasCloudAccess = (bool) $request->attributes->get('dashboard_cloud_access', false);
        $role = $request->attributes->get('dashboard_business_role');
        $activeOutlet = $request->attributes->get('dashboard_active_outlet');

        $selectedOutletId = null;
        if (session()->get('dashboard.outlet_user_selected') === true && session('dashboard.active_outlet_id') !== 'all') {
            $selectedOutletId = (int) session('dashboard.active_outlet_id');
        } elseif ($role === Business::ROLE_CASHIER && $activeOutlet) {
            $selectedOutletId = (int) $activeOutlet->id;
        }

        $overview = $this->overviewData->get($currentBusiness, '7d', $hasCloudAccess, $selectedOutletId);
        [$canEdit, $canAddItems] = $this->resolveTransactionPermissions($request, $currentBusiness);

        $html = view('components.dashboard.recent-transactions-content', [
            'transactions' => $overview['recent_transactions'] ?? [],
            'canEdit' => $canEdit,
            'canAddItems' => $canAddItems,
        ])->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'count' => count($overview['recent_transactions'] ?? []),
        ]);
    }

    /**
     * @return array{0: bool, 1: bool}
     */
    private function resolveTransactionPermissions(Request $request, ?Business $currentBusiness): array
    {
        $role = $request->attributes->get('dashboard_business_role');
        $permissions = $request->attributes->get('dashboard_business_permissions', []);

        $canEdit = $currentBusiness !== null && (
            $role === Business::ROLE_OWNER
            || in_array('*', $permissions, true)
            || in_array(BusinessPermission::TRANSACTIONS_MANAGE, $permissions, true)
        );

        $canAddItems = $canEdit || (
            $currentBusiness !== null && in_array(BusinessPermission::POS_SELL, $permissions, true)
        );

        return [$canEdit, $canAddItems];
    }
}
