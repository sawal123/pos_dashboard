<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /**
     * Update the active outlet context in session.
     */
    public function updateOutlet(Request $request): RedirectResponse
    {
        /** @var Business|null $business */
        $business = $request->attributes->get('dashboard_business');

        if (! $business) {
            abort(403, 'Bisnis aktif tidak ditemukan.');
        }

        $validated = $request->validate([
            'outlet_id' => [
                'required',
                'integer',
                Rule::exists('outlets', 'id')->where(fn ($q) => $q->where('business_id', $business->id)->where('status', 'active')),
            ],
        ]);

        $outletId = (int) $validated['outlet_id'];

        $user = $request->user();
        if ($user) {
            $membership = $business->users()->where('users.id', $user->id)->first();
            $role = $membership?->pivot?->role;
            $cashierOutletId = $membership?->pivot?->outlet_id;

            if ($role === 'cashier' && $cashierOutletId && (int) $cashierOutletId !== $outletId) {
                abort(403, 'Kasir tidak memiliki akses ke cabang toko ini.');
            }
        }

        session([
            'dashboard.active_outlet_id' => $outletId,
            'pos.active_outlet_id' => $outletId,
            'pos.outlet_confirmed' => true,
        ]);

        return redirect()->back();
    }
}

