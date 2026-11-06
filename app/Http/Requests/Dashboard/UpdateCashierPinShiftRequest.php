<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashierPinShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cashier_pin' => trim((string) $this->input('cashier_pin', '')),
            'auto_open_shift' => $this->boolean('auto_open_shift'),
            'default_opening_cash' => (int) $this->input('default_opening_cash', 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cashier_pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'default_opening_cash' => ['required', 'integer', 'min:0'],
            'auto_open_shift' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cashier_pin.required' => 'PIN kasir wajib diisi.',
            'cashier_pin.regex' => 'PIN kasir harus berupa 6 digit angka numerik (0-9).',
            'default_opening_cash.required' => 'Modal awal kasir default wajib diisi.',
            'default_opening_cash.integer' => 'Modal awal kasir default harus berupa angka bulat.',
            'default_opening_cash.min' => 'Modal awal kasir default tidak boleh kurang dari 0.',
            'auto_open_shift.required' => 'Pengaturan auto-open shift wajib ditentukan.',
        ];
    }
}
