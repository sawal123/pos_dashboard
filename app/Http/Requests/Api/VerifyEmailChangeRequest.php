<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'change_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D'],
        ];
    }
}
