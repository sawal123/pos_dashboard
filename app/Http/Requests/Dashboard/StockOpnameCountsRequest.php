<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;

class StockOpnameCountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        return $user !== null
            && $business !== null
            && app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::STOCK_MANAGE);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.counted_stock' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Tidak ada baris hitungan yang dikirim.',
            'items.*.id.required' => 'Baris opname tidak valid.',
            'items.*.id.integer' => 'Baris opname tidak valid.',
            'items.*.counted_stock.numeric' => 'Jumlah hitungan harus berupa angka.',
            'items.*.counted_stock.min' => 'Jumlah hitungan tidak boleh negatif.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
