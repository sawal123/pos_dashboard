<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for setting the active business type.
 *
 * The business is resolved from the shared, session-backed active-business
 * context — never from a request parameter — so a forged `business_id` cannot
 * target another tenant. Only an owner (the sole role holding
 * `business.settings.manage`) may proceed.
 */
class UpdateBusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        return $user !== null
            && $business !== null
            && app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::BUSINESS_SETTINGS_MANAGE);
    }

    /**
     * Only canonical values are accepted — arbitrary or legacy alias strings
     * are rejected here (legacy values are normalized internally instead).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'business_type' => ['required', 'string', Rule::in(BusinessType::values())],
        ];

        // Confirm explicitly when changing a business that already has a type.
        $business = $this->activeBusiness();
        if ($business !== null && $business->business_type !== null) {
            $rules['confirm_change'] = ['required', 'accepted'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_type.required' => 'Pilih salah satu tipe bisnis.',
            'business_type.in' => 'Tipe bisnis tidak dikenal.',
            'confirm_change.accepted' => 'Konfirmasi perubahan tipe bisnis wajib dicentang.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return app(DashboardBusinessContext::class)->current($user);
    }
}
