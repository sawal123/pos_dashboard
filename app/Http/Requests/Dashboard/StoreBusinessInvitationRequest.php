<?php

namespace App\Http\Requests\Dashboard;

use App\Models\BusinessInvitation;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for creating a member invitation.
 *
 * The business is resolved from the shared, session-backed active-business
 * context — never from a request parameter — so a caller cannot target another
 * tenant by forging a business id.
 */
class StoreBusinessInvitationRequest extends FormRequest
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
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([BusinessInvitation::ROLE_MEMBER])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'role' => $this->input('role', BusinessInvitation::ROLE_MEMBER),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Peran undangan yang didukung saat ini hanya Anggota.',
        ];
    }
}
