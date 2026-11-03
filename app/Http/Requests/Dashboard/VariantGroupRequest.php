<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        if ($user === null || $business === null) {
            return false;
        }

        return app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::PRODUCTS_MANAGE);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();
        $businessId = $business?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'selection_type' => ['required', 'string', Rule::in(['single', 'multiple'])],
            'is_required' => ['nullable', 'boolean'],
            'min_selection' => ['nullable', 'integer', 'min:0'],
            'max_selection' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'options' => ['required', 'array', 'min:1'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['required', 'string', 'max:100'],
            'options.*.price_adjustment' => ['nullable', 'integer', 'min:0'],
            'options.*.cost_adjustment' => ['nullable', 'integer', 'min:0'],
            'options.*.ingredient_product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)->where('status', '!=', 'deleted')
                ),
            ],
            'options.*.ingredient_quantity' => ['nullable', 'numeric', 'min:0'],
            'options.*.is_default' => ['nullable', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
