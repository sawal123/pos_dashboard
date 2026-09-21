<?php

namespace App\Http\Middleware;

use App\Services\Dashboard\DashboardBusinessContext;
use Closure;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareDashboardBusinessContext
{
    public function __construct(
        protected DashboardBusinessContext $businessContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $businesses = $this->businessContext->businesses($user);
            $currentBusiness = $this->businessContext->current($user);
            $subscriptionState = $this->businessContext->subscriptionState($currentBusiness);
            $cloudAccess = $this->businessContext->hasCloudAccess($currentBusiness);

            $businessRole = null;
            if ($currentBusiness && $currentBusiness->relationLoaded('pivot')) {
                $pivot = $currentBusiness->getRelation('pivot');
                if ($pivot instanceof Pivot) {
                    $businessRole = $pivot->getAttribute('role');
                }
            }

            // Expose to request attributes for downstream controllers
            $request->attributes->set('dashboard_business', $currentBusiness);
            $request->attributes->set('dashboard_businesses', $businesses);
            $request->attributes->set('dashboard_subscription_state', $subscriptionState);
            $request->attributes->set('dashboard_cloud_access', $cloudAccess);
            $request->attributes->set('dashboard_business_role', $businessRole);

            // Expose to views for Blade components
            View::share('dashboardBusinesses', $businesses);
            View::share('dashboardCurrentBusiness', $currentBusiness);
            View::share('dashboardSubscriptionState', $subscriptionState);
            View::share('dashboardCloudAccess', $cloudAccess);
            View::share('dashboardBusinessRole', $businessRole);
        }

        return $next($request);
    }
}
