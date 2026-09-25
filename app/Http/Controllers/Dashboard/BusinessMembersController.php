<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RemoveBusinessMemberRequest;
use App\Models\User;
use App\Services\Membership\BusinessMembershipService;
use Illuminate\Http\RedirectResponse;

/**
 * Owner-only membership removal endpoint (DASH-10B1).
 */
class BusinessMembersController extends Controller
{
    use ResolvesActiveBusiness;

    public function destroy(
        RemoveBusinessMemberRequest $request,
        int $userId,
        BusinessMembershipService $members,
    ): RedirectResponse {
        $business = $this->activeBusiness($request);

        /** @var User $actor */
        $actor = $request->user();

        $target = User::query()
            ->whereKey($userId)
            ->whereHas('businesses', fn ($query) => $query->where('businesses.id', $business->id))
            ->first();

        if ($target === null) {
            abort(404);
        }

        $members->removeMember($actor, $business, $target);

        return redirect()
            ->route('users.index')
            ->with('status', $target->name.' telah dihapus dari bisnis aktif.');
    }
}
