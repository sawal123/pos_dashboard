<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Business;
use Illuminate\Http\Request;

/**
 * Resolves the active business from the shared dashboard context.
 *
 * The business always comes from the request attribute set by
 * ShareDashboardBusinessContext (session-backed and membership-validated),
 * never from a query or body parameter.
 */
trait ResolvesActiveBusiness
{
    protected function activeBusiness(Request $request): Business
    {
        $business = $request->attributes->get('dashboard_business');

        if (! $business instanceof Business) {
            abort(403);
        }

        return $business;
    }
}
