<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class EmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'new_email' => ['required', 'string', 'email', 'max:320'],
            'current_password' => ['nullable', 'string', 'max:255'],
            'totp_code' => ['nullable', 'string', 'regex:/\A[0-9]{6}\z/D'],
            'id_token' => ['nullable', 'string', 'max:8192'],
            'client_platform' => ['nullable', 'string', 'in:web,android'],
        ];
    }
}
