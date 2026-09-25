<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization guard for removing a member from the active business.
 */
class RemoveBusinessMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $business = app(DashboardBusinessContext::class)->current($user);

        return $business !== null && $user->can('manageMembers', $business);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
