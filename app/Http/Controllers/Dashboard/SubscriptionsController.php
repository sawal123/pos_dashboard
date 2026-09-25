<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardSubscriptionData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * DASH-12A — read-only subscription overview.
 *
 * Owner-only, active-business scoped and strictly read-only: no plan, status or
 * expiry mutation endpoint is exposed. The subscription comes from the shared
 * dashboard context, never from a request parameter, so a forged `business_id`
 * cannot change what is displayed.
 */
class SubscriptionsController extends Controller
{
    public function __construct(
        protected DashboardSubscriptionData $subscriptionData
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(403);
        }

        // Reuse the existing owner-only rule without modifying BusinessPolicy.
        Gate::forUser($request->user())->authorize('update', $currentBusiness);

        return view('subscriptions.index', [
            'subscription' => $this->subscriptionData->get($currentBusiness),
        ]);
    }
}
