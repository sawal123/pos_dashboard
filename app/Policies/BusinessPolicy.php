<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;

/**
 * Business policy (DASH-10B2).
 *
 * `view` stays membership-based (any role) so an existing `member`/`cashier`
 * still passes the coarse "can reach this business" check. Every management
 * capability is delegated to the central {@see BusinessAuthorizer} permission
 * matrix, which is owner-only today and denies unknown roles by default.
 */
class BusinessPolicy
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    /**
     * Determine whether the user can view the business (any membership role).
     */
    public function view(User $user, Business $business): bool
    {
        return $business->hasMember($user);
    }

    /**
     * Determine whether the user can view the business members and their roles.
     */
    public function viewMembers(User $user, Business $business): bool
    {
        return $this->authorizer->allows($user, $business, BusinessPermission::USERS_VIEW);
    }

    /**
     * Determine whether the user can invite new members to the business.
     */
    public function manageInvitations(User $user, Business $business): bool
    {
        return $this->authorizer->allows($user, $business, BusinessPermission::INVITATIONS_MANAGE);
    }

    /**
     * Determine whether the user can manage (e.g. remove) business members.
     */
    public function manageMembers(User $user, Business $business): bool
    {
        return $this->authorizer->allows($user, $business, BusinessPermission::MEMBERS_MANAGE);
    }

    /**
     * Determine whether the user can change the role of a business member.
     */
    public function manageRoles(User $user, Business $business): bool
    {
        return $this->authorizer->allows($user, $business, BusinessPermission::ROLES_MANAGE);
    }

    /**
     * Determine whether the user can update the business.
     */
    public function update(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the business.
     */
    public function delete(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user);
    }
}
