<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ManageBusinessInvitationRequest;
use App\Http\Requests\Dashboard\StoreBusinessInvitationRequest;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Services\Membership\BusinessInvitationService;
use Illuminate\Http\RedirectResponse;

/**
 * Owner-only member invitation endpoints (DASH-10B1).
 */
class BusinessInvitationsController extends Controller
{
    use ResolvesActiveBusiness;

    public function store(
        StoreBusinessInvitationRequest $request,
        BusinessInvitationService $invitations,
    ): RedirectResponse {
        $business = $this->activeBusiness($request);

        /** @var User $actor */
        $actor = $request->user();

        $email = (string) $request->validated('email');
        $role = (string) $request->validated('role');

        $invitations->invite($actor, $business, $email, $role);

        return redirect()
            ->route('users.index')
            ->with('status', 'Undangan untuk '.$email.' dijadwalkan dan akan dikirim melalui email.');
    }

    public function resend(
        ManageBusinessInvitationRequest $request,
        BusinessInvitation $invitation,
        BusinessInvitationService $invitations,
    ): RedirectResponse {
        $business = $this->activeBusiness($request);
        $this->ensureBelongsToBusiness($invitation, $business->id);

        /** @var User $actor */
        $actor = $request->user();

        $invitations->resend($actor, $invitation);

        return redirect()
            ->route('users.index')
            ->with('status', 'Undangan untuk '.$invitation->email.' dijadwalkan ulang dan akan dikirim melalui email.');
    }

    public function revoke(
        ManageBusinessInvitationRequest $request,
        BusinessInvitation $invitation,
        BusinessInvitationService $invitations,
    ): RedirectResponse {
        $business = $this->activeBusiness($request);
        $this->ensureBelongsToBusiness($invitation, $business->id);

        /** @var User $actor */
        $actor = $request->user();

        $invitations->revoke($actor, $invitation);

        return redirect()
            ->route('users.index')
            ->with('status', 'Undangan untuk '.$invitation->email.' dibatalkan.');
    }

    /**
     * Never act on an invitation that belongs to another tenant: behave as if
     * it does not exist so its existence is not leaked.
     */
    private function ensureBelongsToBusiness(BusinessInvitation $invitation, int $businessId): void
    {
        if ((int) $invitation->business_id !== $businessId) {
            abort(404);
        }
    }
}
