<?php

namespace App\Services\Authorization;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Central evaluator for the DASH-10B2 RBAC contract.
 *
 * It always resolves the caller's role *inside the given business* — there is
 * no global role. A user can be owner of one business, member of another and
 * cashier of a third, and each request is evaluated against the business it
 * actually acts on.
 */
final class BusinessAuthorizer
{
    /**
     * The caller's role inside the given business, or null when they are not a
     * member. Uses the eager-loaded pivot when available to avoid an extra query.
     */
    public function roleIn(?User $user, ?Business $business): ?string
    {
        if ($user === null || $business === null) {
            return null;
        }

        if ($business->relationLoaded('pivot')) {
            $pivot = $business->getRelation('pivot');

            if ($pivot instanceof Pivot) {
                $role = $pivot->getAttribute('role');

                return is_string($role) ? $role : null;
            }
        }

        $role = $user->businesses()
            ->where('businesses.id', $business->id)
            ->first()
            ?->pivot
            ?->getAttribute('role');

        return is_string($role) ? $role : null;
    }

    /**
     * Permissions the caller holds in the given business (empty when not a
     * member). Unknown roles resolve to an empty list.
     *
     * @return list<string>
     */
    public function permissionsFor(?User $user, ?Business $business): array
    {
        return BusinessPermission::forRole($this->roleIn($user, $business));
    }

    /**
     * Whether the caller holds the permission in the given business.
     */
    public function allows(?User $user, ?Business $business, string $permission): bool
    {
        return BusinessPermission::roleAllows($this->roleIn($user, $business), $permission);
    }

    /**
     * Abort with 403 unless the caller holds the permission in the business.
     */
    public function authorize(?User $user, ?Business $business, string $permission): void
    {
        if (! $this->allows($user, $business, $permission)) {
            abort(403);
        }
    }
}
