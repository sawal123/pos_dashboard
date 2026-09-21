<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessContextController extends Controller
{
    /**
     * Update the active business context in session.
     */
    public function update(Request $request, DashboardBusinessContext $businessContext): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        $businessContext->switchTo($user, (int) $validated['business_id']);

        return redirect()->back();
    }
}
