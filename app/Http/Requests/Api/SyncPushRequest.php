<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer'],
            'device_identifier' => ['required', 'string'],
            'request_id' => ['required', 'uuid'],
            'changes' => ['present', 'array'],
            'changes.categories' => ['sometimes', 'array', 'max:100'],
            'changes.products' => ['sometimes', 'array', 'max:100'],
            'changes.customers' => ['sometimes', 'array', 'max:100'],
            'changes.shifts' => ['sometimes', 'array', 'max:100'],
            'changes.sales' => ['sometimes', 'array', 'max:100'],
            'changes.sale_items' => ['sometimes', 'array', 'max:100'],
            'changes.expenses' => ['sometimes', 'array', 'max:100'],
        ];
    }
}
