<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * INT-03 — validation for the read-only sync request status endpoint.
 *
 * The request id is a route parameter; it is merged into the validated input so
 * it is checked as a UUID alongside the business/device context.
 */
class SyncRequestStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the route parameter together with the query string.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_id' => $this->route('request_id'),
        ]);
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
        ];
    }
}
