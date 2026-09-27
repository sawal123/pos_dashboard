<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateBusinessTypeRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DASH-14 — owner-only business profile / business type setup.
 *
 * Strictly scoped to the active business from the shared dashboard context
 * (never a request parameter) and limited to the `business_type` column: no
 * product, sale, stock, laundry, subscription or other-tenant data is touched.
 * Route access is enforced by the `business.settings.manage` permission
 * (owner-only today).
 */
class BusinessSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $business = $this->activeBusiness($request);

        return view('business-settings.index', [
            'business' => $business,
            'currentType' => $business->normalizedBusinessType(),
            'currentTypeLabel' => $business->businessTypeLabel(),
            'hasType' => $business->business_type !== null,
            'typeOptions' => BusinessType::options(),
        ]);
    }

    public function update(UpdateBusinessTypeRequest $request): RedirectResponse
    {
        $business = $this->activeBusiness($request);

        $type = BusinessType::from((string) $request->validated('business_type'));

        // Only the active business row is updated, and only its `business_type`.
        $business->update(['business_type' => $type->value]);

        return redirect()
            ->route('business-settings.edit')
            ->with('status', 'Tipe bisnis disimpan sebagai '.$type->label().'.');
    }

    /**
     * Resolve the active business from the shared context; a user without an
     * active business must never reach this owner-only surface.
     */
    private function activeBusiness(Request $request): Business
    {
        $business = $request->attributes->get('dashboard_business');

        if (! $business instanceof Business) {
            abort(403);
        }

        return $business;
    }
}
