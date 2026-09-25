<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\BusinessInvitation;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use App\Services\Membership\BusinessInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Invitation acceptance for both existing accounts and users who register
 * through Fortify after following the invitation link.
 *
 * Acceptance always requires an authenticated user whose email has been
 * verified and matches the invitation exactly.
 */
class InvitationAcceptanceController extends Controller
{
    /** Session key used to resume the invitation after login/registration. */
    public const SESSION_KEY = 'invitations.pending_token';

    public function show(Request $request, string $token): View|RedirectResponse|Response
    {
        $invitation = BusinessInvitation::findByToken($token);

        if ($invitation === null) {
            abort(404);
        }

        // Remember the token so a new user can register, verify and come back.
        $request->session()->put(self::SESSION_KEY, $token);

        $user = $request->user();

        if ($user === null) {
            return view('invitations.show', [
                'invitation' => $invitation,
                'state' => 'guest',
                'token' => $token,
            ]);
        }

        if ($user->email_verified_at === null) {
            return redirect()->route('verification.notice');
        }

        if (! $invitation->isUsable()) {
            return view('invitations.show', [
                'invitation' => $invitation,
                'state' => 'unusable',
                'token' => $token,
            ]);
        }

        if (! hash_equals(Str::lower((string) $invitation->email), Str::lower((string) $user->email))) {
            return response()->view('invitations.show', [
                'invitation' => $invitation,
                'state' => 'email_mismatch',
                'token' => $token,
            ], 403);
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'state' => 'ready',
            'token' => $token,
        ]);
    }

    public function accept(
        Request $request,
        string $token,
        BusinessInvitationService $invitations,
    ): RedirectResponse {
        $invitation = BusinessInvitation::findByToken($token);

        if ($invitation === null) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        $business = $invitations->accept($user, $invitation);

        $request->session()->forget(self::SESSION_KEY);
        // Make the newly joined business the active dashboard context.
        $request->session()->put(DashboardBusinessContext::SESSION_KEY, $business->id);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Undangan diterima. Anda kini menjadi anggota '.$business->name.'.');
    }
}
