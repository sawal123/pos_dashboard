<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\PromoDiscountType;
use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\Promo;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        if ($user === null || $business === null) {
            return false;
        }

        if (! app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::PROMO_MANAGE)) {
            return false;
        }

        $promoId = $this->route('promoId');
        if ($promoId !== null && ! Promo::query()
            ->where('business_id', $business->id)
            ->whereKey($promoId)
            ->exists()) {
            abort(404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? trim((string) $this->input('code')) : $this->input('code'),
            'max_discount' => $this->input('max_discount') === '' ? null : $this->input('max_discount'),
            'usage_limit_total' => $this->input('usage_limit_total') === '' ? null : $this->input('usage_limit_total'),
            'usage_limit_per_customer' => $this->input('usage_limit_per_customer') === '' ? null : $this->input('usage_limit_per_customer'),
            'is_stackable' => $this->boolean('is_stackable'),
        ]);

        $scope = (string) $this->input('scope');
        $targetIds = $this->input('target_ids');

        if (! is_array($this->input('targets')) && is_array($targetIds) && $scope !== PromoScope::All->value) {
            $targets = [];

            foreach ($targetIds as $targetId) {
                if (is_numeric($targetId)) {
                    $targets[] = ['type' => $scope, 'id' => (int) $targetId];
                }
            }

            $this->merge(['targets' => $targets]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();
        $businessId = $business?->id;
        $promoId = $this->route('promoId') !== null ? (int) $this->route('promoId') : null;
        $discountType = (string) $this->input('discount_type');

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('promos', 'code')->where('business_id', $businessId)->ignore($promoId),
            ],
            'discount_type' => ['required', 'string', Rule::in(PromoDiscountType::values())],
            'discount_value' => ['required', 'integer', 'min:0'],
            'scope' => ['required', 'string', Rule::in(PromoScope::values())],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_stackable' => ['boolean'],
            'status' => ['required', 'string', Rule::in(PromoStatus::values())],
            'targets' => ['nullable', 'array'],
            'targets.*.type' => ['required_with:targets', 'string', Rule::in([PromoScope::Product->value, PromoScope::Category->value])],
            'targets.*.id' => ['required_with:targets', 'integer'],
        ];

        if ($discountType === PromoDiscountType::Percent->value) {
            $rules['discount_value'][] = 'max:10000';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama promo wajib diisi.',
            'name.max' => 'Nama promo maksimal 120 karakter.',
            'code.unique' => 'Kode promo sudah digunakan pada bisnis ini.',
            'code.max' => 'Kode promo maksimal 40 karakter.',
            'discount_type.required' => 'Jenis diskon wajib dipilih.',
            'discount_type.in' => 'Jenis diskon tidak dikenal.',
            'discount_value.required' => 'Nilai diskon wajib diisi.',
            'discount_value.integer' => 'Nilai diskon harus berupa angka bulat.',
            'discount_value.max' => 'Diskon persentase tidak boleh melebihi 100%.',
            'scope.required' => 'Cakupan promo wajib dipilih.',
            'scope.in' => 'Cakupan promo tidak dikenal.',
            'min_purchase.integer' => 'Minimal belanja harus berupa angka.',
            'max_discount.integer' => 'Batas diskon harus berupa angka.',
            'weekdays.*.between' => 'Hari promo harus antara Senin dan Minggu.',
            'ends_at.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'start_time.date_format' => 'Jam mulai harus berformat HH:MM.',
            'end_time.date_format' => 'Jam selesai harus berformat HH:MM.',
            'usage_limit_total.min' => 'Batas pemakaian minimal 1.',
            'usage_limit_per_customer.min' => 'Batas pemakaian per pelanggan minimal 1.',
            'status.required' => 'Status promo wajib dipilih.',
            'status.in' => 'Status promo tidak dikenal.',
            'targets.*.id.required_with' => 'Target promo tidak valid.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
