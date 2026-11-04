<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\CostSource;
use App\Enums\HppMethod;
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
        $isSellable = $this->has('is_sellable') ? $this->boolean('is_sellable') : true;

        $rawRecipeItems = $this->input('recipe_items');
        $cleanRecipeItems = null;
        if (is_array($rawRecipeItems)) {
            $filtered = array_values(array_filter($rawRecipeItems, function ($item) {
                return is_array($item) && ! empty($item['component_product_id']) && (float) ($item['quantity'] ?? 0) > 0;
            }));
            $cleanRecipeItems = ! empty($filtered) ? $filtered : null;
        }

        $this->merge([
            'is_sellable' => $isSellable,
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'sku' => $this->normalizeNullable('sku'),
            'barcode' => $this->normalizeNullable('barcode'),
            'unit' => $this->normalizeNullable('unit') ?? ($isSellable ? 'pcs' : 'gram'),
            'pricing_unit' => $this->normalizeNullable('pricing_unit'),
            'estimated_duration' => $this->normalizeNullable('estimated_duration'),
            'category_id' => in_array($this->input('category_id'), ['', 'all', null], true)
                ? null
                : $this->input('category_id'),
            'price' => ! $isSellable ? 0 : ($this->input('price') ?? 0),
            'recipe_items' => $cleanRecipeItems,
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
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];

        if ($business && ! $business->hasCloudAccess()) {
            $rules['variant_group_ids'] = ['prohibited'];
        } else {
            $rules['variant_group_ids'] = ['nullable', 'array'];
            $rules['variant_group_ids.*'] = [
                'integer',
                Rule::exists('variant_groups', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)->where('status', '!=', 'deleted')
                ),
            ];
        }

        if (! $this->isUpdate()) {
            $rules['kind'] = ['required', 'string', Rule::in(CatalogManagementService::KINDS)];
        }

        if ($kind === 'service') {
            // SKU is optional for services; when omitted the server generates a
            // unique one. When supplied it must still be unique per business —
            // including collisions with a product SKU (one shared column).
            $rules['sku'] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->where('business_id', $businessId)->ignore($productId),
            ];
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
            $rules['cost_source'] = ['nullable', 'string', Rule::in(CostSource::values())];
            $rules['hpp_method'] = ['nullable', 'string', Rule::in(HppMethod::values())];
            $rules['hpp_overhead_fixed'] = ['nullable', 'numeric', 'min:0'];
            $rules['hpp_overhead_percent'] = ['nullable', 'numeric', 'min:0', 'max:1000'];
            $rules['unit'] = ['nullable', 'string', 'max:20'];
            $rules['stock'] = ['nullable', 'numeric'];
            // Minimum stock may be negative — no `min:0` here on purpose.
            $rules['min_stock'] = ['nullable', 'numeric'];
            // INV-D02 — raw materials / supplies are tracked but not sold.
            $rules['is_sellable'] = ['required', 'boolean'];

            $rules['recipe_items'] = ['nullable', 'array'];
            $rules['recipe_items.*.component_product_id'] = [
                'required_with:recipe_items',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ];
            $rules['recipe_items.*.quantity'] = ['required_with:recipe_items', 'numeric', 'min:0.0001'];
            $rules['recipe_items.*.note'] = ['nullable', 'string', 'max:255'];
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
            'is_sellable.boolean' => 'Penanda bahan baku tidak valid.',
            'variant_group_ids.prohibited' => 'Fitur varian menu khusus untuk bisnis yang berlangganan paket Cloud.',
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
