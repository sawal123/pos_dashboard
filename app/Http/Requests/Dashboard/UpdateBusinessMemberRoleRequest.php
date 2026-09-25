<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorization + validation for changing a member's role inside the active
 * business (DASH-10B2).
 *
 * Only an owner of the active business may act, the target role is restricted
 * to the managed allowlist (`member`, `cashier`), and the business always comes
 * from the session-backed context — never from a request parameter.
 */
class UpdateBusinessMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $business = app(DashboardBusinessContext::class)->current($user);

        return $business !== null && $user->can('manageRoles', $business);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Business::MANAGED_ROLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Pilih peran baru terlebih dahulu.',
            'role.in' => 'Peran yang dapat ditetapkan hanya Anggota atau Kasir.',
        ];
    }
}
