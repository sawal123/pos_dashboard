<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Business;
use App\Models\Product;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use App\Services\Dashboard\CatalogManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + authorization for creating and updating a catalog item
 * (product or service) — DASH-15.
 *
 * The active business always comes from the shared dashboard context, never a
 * request parameter. Only a role holding `products.manage` (owner today) may
 * mutate; a `category_id` or `product_id` that does not belong to the active
 * business is rejected, never silently swapped.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $business = $this->activeBusiness();

        if ($user === null || $business === null) {
            return false;
        }

        if (! app(BusinessAuthorizer::class)->allows($user, $business, BusinessPermission::PRODUCTS_MANAGE)) {
            return false;
        }

        // A cross-tenant product id must behave as if it does not exist.
        if ($this->isUpdate()) {
            $owned = Product::query()
                ->where('business_id', $business->id)
                ->whereKey($this->route('productId'))
                ->exists();

            if (! $owned) {
                abort(404);
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'sku' => $this->normalizeNullable('sku'),
            'barcode' => $this->normalizeNullable('barcode'),
            'unit' => $this->normalizeNullable('unit'),
            'pricing_unit' => $this->normalizeNullable('pricing_unit'),
            'estimated_duration' => $this->normalizeNullable('estimated_duration'),
            'category_id' => in_array($this->input('category_id'), ['', 'all', null], true)
                ? null
                : $this->input('category_id'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = $this->activeBusiness();
        $businessId = $business?->id;
        $productId = $this->isUpdate() ? (int) $this->route('productId') : null;
        $kind = $this->resolveKind();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(CatalogManagementService::STATUSES)],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)->where('status', '!=', 'deleted')
                ),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')->where('business_id', $businessId)->ignore($productId),
            ],
        ];

        if (! $this->isUpdate()) {
            $rules['kind'] = ['required', 'string', Rule::in(CatalogManagementService::KINDS)];
            // Initial stock is optional and may be negative.
            $rules['stock'] = ['nullable', 'numeric'];
        }

        if ($kind === 'service') {
            // SKU is optional for services; the server generates a unique one.
            $rules['sku'] = ['nullable', 'string', 'max:255'];
            $rules['pricing_unit'] = ['nullable', 'string', 'max:20'];
            $rules['unit'] = ['nullable', 'string', 'max:20'];
            $rules['min_quantity'] = ['nullable', 'numeric', 'min:0'];
            $rules['estimated_duration'] = ['nullable', 'string', 'max:50'];
        } else {
            $rules['sku'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->where('business_id', $businessId)->ignore($productId),
            ];
            $rules['cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['unit'] = ['nullable', 'string', 'max:20'];
            // Minimum stock may be negative — no `min:0` here on purpose.
            $rules['min_stock'] = ['nullable', 'numeric'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama item wajib diisi.',
            'name.max' => 'Nama item maksimal 255 karakter.',
            'price.required' => 'Harga jual wajib diisi.',
            'price.integer' => 'Harga jual harus berupa angka bulat.',
            'price.min' => 'Harga jual tidak boleh negatif.',
            'sku.required' => 'SKU wajib diisi untuk produk.',
            'sku.unique' => 'SKU sudah digunakan pada bisnis ini.',
            'barcode.unique' => 'Barcode sudah digunakan pada bisnis ini.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'cost.min' => 'HPP tidak boleh negatif.',
            'min_quantity.min' => 'Minimum quantity tidak boleh negatif.',
            'stock.numeric' => 'Stok awal harus berupa angka.',
        ];
    }

    private function isUpdate(): bool
    {
        return $this->route('productId') !== null;
    }

    private function resolveKind(): string
    {
        if (! $this->isUpdate()) {
            $kind = (string) $this->input('kind', 'product');

            return in_array($kind, CatalogManagementService::KINDS, true) ? $kind : 'product';
        }

        $business = $this->activeBusiness();

        $kind = Product::query()
            ->where('business_id', $business?->id)
            ->whereKey($this->route('productId'))
            ->value('kind');

        return is_string($kind) ? $kind : 'product';
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
