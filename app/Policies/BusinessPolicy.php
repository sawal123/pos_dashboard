<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Determine whether the user can view the business.
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
        return $business->isOwnedBy($user);
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
