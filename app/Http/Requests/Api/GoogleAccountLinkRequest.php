<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class GoogleAccountLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8192'],
            'client_platform' => ['required', 'string', 'in:web,android'],
            'reauthentication_token' => ['required', 'string', 'max:128'],
        ];
    }
}
