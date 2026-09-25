<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization guard for resending or revoking an existing invitation.
 *
 * Only an owner of the active business may act; the target invitation is
 * additionally scoped to that business by the controller.
 */
class ManageBusinessInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $business = app(DashboardBusinessContext::class)->current($user);

        return $business !== null && $user->can('manageInvitations', $business);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
