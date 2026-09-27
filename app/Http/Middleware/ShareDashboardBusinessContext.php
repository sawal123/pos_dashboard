<?php

namespace App\Http\Middleware;

use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Dashboard\DashboardBusinessContext;
use Closure;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareDashboardBusinessContext
{
    public function __construct(
        protected DashboardBusinessContext $businessContext,
        protected BusinessAuthorizer $authorizer,
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
            // DASH-14 — canonical business type of the active business (null when
            // unknown). The UI adapts to it; permissions still govern access.
            $businessType = $this->businessContext->businessType($currentBusiness);

            $businessRole = null;
            if ($currentBusiness && $currentBusiness->relationLoaded('pivot')) {
                $pivot = $currentBusiness->getRelation('pivot');
                if ($pivot instanceof Pivot) {
                    $businessRole = $pivot->getAttribute('role');
                }
            }

            // DASH-10B2 — the permissions the caller actually holds in the
            // active business. The UI mirrors these; route middleware enforces
            // them server-side so the sidebar is never the only protection.
            $businessPermissions = $this->authorizer->permissionsFor($user, $currentBusiness);

            // Expose to request attributes for downstream controllers
            $request->attributes->set('dashboard_business', $currentBusiness);
            $request->attributes->set('dashboard_businesses', $businesses);
            $request->attributes->set('dashboard_subscription_state', $subscriptionState);
            $request->attributes->set('dashboard_cloud_access', $cloudAccess);
            $request->attributes->set('dashboard_business_role', $businessRole);
            $request->attributes->set('dashboard_business_permissions', $businessPermissions);
            $request->attributes->set('dashboard_business_type', $businessType);

            // Expose to views for Blade components
            View::share('dashboardBusinesses', $businesses);
            View::share('dashboardCurrentBusiness', $currentBusiness);
            View::share('dashboardSubscriptionState', $subscriptionState);
            View::share('dashboardCloudAccess', $cloudAccess);
            View::share('dashboardBusinessRole', $businessRole);
            View::share('dashboardPermissions', $businessPermissions);
            View::share('dashboardBusinessType', $businessType);
        }

        return $next($request);
    }
}
