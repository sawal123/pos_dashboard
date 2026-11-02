<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\PromoStatus;
use App\Models\Business;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromoStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        return $user !== null
            && $business !== null
            && app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::PROMO_MANAGE);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(PromoStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status promo wajib dipilih.',
            'status.in' => 'Status promo tidak dikenal.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
