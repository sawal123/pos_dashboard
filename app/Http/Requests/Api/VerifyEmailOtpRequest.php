<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D'],
        ];
    }
}
