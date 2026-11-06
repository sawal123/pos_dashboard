<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_enabled' => $this->boolean('tax_enabled'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tax_enabled' => ['required', 'boolean'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tax_rate.required' => 'Persentase tarif pajak wajib diisi.',
            'tax_rate.numeric' => 'Persentase tarif pajak harus berupa angka.',
            'tax_rate.min' => 'Persentase tarif pajak minimal 0%.',
            'tax_rate.max' => 'Persentase tarif pajak maksimal 100%.',
        ];
    }
}

