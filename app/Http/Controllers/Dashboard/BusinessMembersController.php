<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesActiveBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RemoveBusinessMemberRequest;
use App\Http\Requests\Dashboard\UpdateBusinessMemberRoleRequest;
use App\Models\Business;
use App\Models\User;
use App\Services\Membership\BusinessMembershipService;
use Illuminate\Http\RedirectResponse;

/**
 * Owner-only membership management endpoints (DASH-10B1 / DASH-10B2).
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

        $target = $this->targetMember($business, $userId);

        $members->removeMember($actor, $business, $target);

        return redirect()
            ->route('users.index')
            ->with('status', $target->name.' telah dihapus dari bisnis aktif.');
    }

    /**
     * Change a member's role between the managed roles (member <-> cashier).
     */
    public function updateRole(
        UpdateBusinessMemberRoleRequest $request,
        int $userId,
        BusinessMembershipService $members,
    ): RedirectResponse {
        $business = $this->activeBusiness($request);

        /** @var User $actor */
        $actor = $request->user();

        $target = $this->targetMember($business, $userId);

        $newRole = $members->changeRole($actor, $business, $target, (string) $request->validated('role'));

        return redirect()
            ->route('users.index')
            ->with('status', 'Peran '.$target->name.' berhasil diubah menjadi '.Business::roleLabel($newRole).'.');
    }

    /**
     * Resolve a member of the active business, or behave as if the user does
     * not exist so another tenant's membership is never leaked (404).
     */
    private function targetMember(Business $business, int $userId): User
    {
        $target = User::query()
            ->whereKey($userId)
            ->whereHas('businesses', fn ($query) => $query->where('businesses.id', $business->id))
            ->first();

        if ($target === null) {
            abort(404);
        }

        return $target;
    }
}
