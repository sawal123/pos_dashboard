<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncPullRequest extends FormRequest
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
            'after' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
