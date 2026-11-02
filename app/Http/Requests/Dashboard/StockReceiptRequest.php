<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Product;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a multi-line stock receiving (INV-D01).
 */
class StockReceiptRequest extends FormRequest
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

        $lines = $this->input('lines');
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $productId = is_array($line) ? ($line['product_id'] ?? null) : null;

                if (is_numeric($productId) && ! Product::query()
                    ->where('business_id', $business->id)
                    ->whereKey((int) $productId)
                    ->exists()) {
                    abort(404);
                }
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_id' => $this->normalizeNullable('reference_id'),
            'note' => $this->normalizeNullable('note'),
            'idempotency_key' => $this->normalizeNullable('idempotency_key'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();

        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('business_id', $business?->id)
                    ->where('kind', 'product')
                    ->where('status', '!=', 'deleted')),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_id' => ['nullable', 'string', 'max:255'],
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
            'lines.required' => 'Minimal satu baris penerimaan wajib diisi.',
            'lines.min' => 'Minimal satu baris penerimaan wajib diisi.',
            'lines.max' => 'Maksimal 100 baris per penerimaan.',
            'lines.*.product_id.required' => 'Produk pada setiap baris wajib dipilih.',
            'lines.*.product_id.exists' => 'Produk pada baris penerimaan tidak valid.',
            'lines.*.product_id.distinct' => 'Produk yang sama tidak boleh muncul lebih dari sekali.',
            'lines.*.quantity.required' => 'Jumlah pada setiap baris wajib diisi.',
            'lines.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'lines.*.quantity.gt' => 'Jumlah harus lebih besar dari nol.',
            'lines.*.unit_cost.numeric' => 'Harga satuan harus berupa angka.',
            'lines.*.unit_cost.min' => 'Harga satuan tidak boleh negatif.',
            'reference_id.max' => 'Nomor referensi maksimal 255 karakter.',
            'note.max' => 'Catatan maksimal 500 karakter.',
            'idempotency_key.max' => 'Kunci idempotensi maksimal 64 karakter.',
        ];
    }

    private function normalizeNullable(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return is_scalar($value) ? (string) $value : null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function activeBusiness(): ?Business
    {
        $business = $this->attributes->get('dashboard_business');

        return $business instanceof Business ? $business : null;
    }
}
