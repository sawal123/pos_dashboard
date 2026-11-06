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
        $isDirect = $this->input('mode') === 'direct';

        return [
            'mode' => ['nullable', 'string', 'in:invite,direct'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(BusinessInvitation::ROLES)],
            'name' => [$isDirect ? 'required' : 'nullable', 'string', 'max:255'],
            'password' => [$isDirect ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'mode' => $this->input('mode', 'invite'),
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
            'name.required' => 'Nama pengguna wajib diisi untuk pendaftaran langsung.',
            'password.required' => 'Kata sandi wajib diisi untuk pendaftaran langsung.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'role.in' => 'Peran undangan yang didukung adalah Anggota atau Kasir.',
        ];
    }
}
