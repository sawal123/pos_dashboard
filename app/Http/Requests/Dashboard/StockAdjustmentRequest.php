<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Product;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a manual stock adjustment (INV-D01).
 *
 * The active business always comes from the shared dashboard context, never
 * from the payload, and the product must belong to that tenant.
 */
class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        if ($user === null || $business === null) {
            return false;
        }

        if (! app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::STOCK_MANAGE)) {
            return false;
        }

        $productId = $this->input('product_id');
        if (is_numeric($productId) && ! Product::query()
            ->where('business_id', $business->id)
            ->whereKey((int) $productId)
            ->exists()) {
            abort(404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'note' => is_string($this->input('note')) ? trim((string) $this->input('note')) : $this->input('note'),
            'idempotency_key' => is_string($this->input('idempotency_key'))
                ? trim((string) $this->input('idempotency_key'))
                : $this->input('idempotency_key'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('business_id', $business?->id)
                    ->where('kind', 'product')
                    ->where('status', '!=', 'deleted')),
            ],
            'type' => [
                'required',
                'string',
                Rule::in([
                    StockMovementType::Adjustment->value,
                    StockMovementType::Usage->value,
                    StockMovementType::Return->value,
                ]),
            ],
            'direction' => ['required', 'string', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk yang dipilih tidak valid.',
            'type.required' => 'Jenis penyesuaian wajib dipilih.',
            'type.in' => 'Jenis penyesuaian tidak dikenal.',
            'direction.required' => 'Arah penyesuaian wajib dipilih.',
            'direction.in' => 'Arah penyesuaian tidak dikenal.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.numeric' => 'Jumlah harus berupa angka.',
            'quantity.gt' => 'Jumlah harus lebih besar dari nol.',
            'note.max' => 'Catatan maksimal 500 karakter.',
            'idempotency_key.max' => 'Kunci idempotensi maksimal 64 karakter.',
        ];
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
