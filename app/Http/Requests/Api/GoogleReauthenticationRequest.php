<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class GoogleReauthenticationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
            'totp_code' => ['nullable', 'string', 'regex:/\A[0-9]{6}\z/D'],
        ];
    }
}
