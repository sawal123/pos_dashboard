<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Category;
use App\Models\Concerns\HasSyncMetadata;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * DASH-15 — owner-only catalog mutations (products, services, categories).
 *
 * Every write goes through Eloquent `create()` / `save()` so the
 * {@see HasSyncMetadata} trait mints or advances
 * `sync_id`, `sync_version` and `sync_sequence`. Raw/bulk updates are never
 * used: they would bypass the trait and strand the change from the mobile sync
 * pipeline (and break optimistic concurrency for every device).
 *
 * Stock authority: the product `stock` column is only written on **create**
 * (the initial stock, mirroring the sync product-create contract). Subsequent
 * stock changes belong to stock movements, never to catalog edits.
 */
class CatalogManagementService
{
    /** @var list<string> */
    public const KINDS = ['product', 'service'];

    /** @var list<string> */
    public const STATUSES = ['active', 'inactive'];

    /**
     * Create a product or a service.
     *
     * @param  array<string, mixed>  $data  Validated request input.
     */
    public function createProduct(Business $business, array $data, string $kind): Product
    {
        $attributes = [
            'business_id' => $business->id,
            'kind' => $kind,
            'status' => $this->status($data['status'] ?? null),
            'name' => trim((string) ($data['name'] ?? '')),
            'category_id' => $data['category_id'] ?? null,
            'price' => (int) ($data['price'] ?? 0),
            'sku' => $this->resolveSku($business, $kind, $data['sku'] ?? null),
        ];

        if ($kind === 'service') {
            $attributes['barcode'] = null;
            $attributes['cost'] = $this->money(0);
            $attributes['stock'] = $this->quantity(0);
            $attributes['min_stock'] = $this->quantity(0);
            $attributes['unit'] = $this->text($data['unit'] ?? null) ?? 'pcs';
            $attributes['pricing_unit'] = $this->text($data['pricing_unit'] ?? null) ?? 'pcs';
            $attributes['min_quantity'] = $this->quantity($data['min_quantity'] ?? 0);
            $attributes['estimated_duration'] = $this->text($data['estimated_duration'] ?? null);
        } else {
            $attributes['barcode'] = $this->text($data['barcode'] ?? null);
            $attributes['cost'] = $this->money($data['cost'] ?? 0);
            // Initial stock is an absolute snapshot and is allowed to be
            // negative; it is the only place stock is set from the dashboard.
            $attributes['stock'] = $this->quantity($data['stock'] ?? 0);
            $attributes['unit'] = $this->text($data['unit'] ?? null) ?? 'pcs';
            $attributes['min_stock'] = $this->quantity($data['min_stock'] ?? 0);
            $attributes['pricing_unit'] = 'pcs';
            $attributes['min_quantity'] = $this->quantity(0);
            $attributes['estimated_duration'] = null;
        }

        return Product::create($attributes);
    }

    /**
     * Update catalog metadata. `stock` is intentionally never touched: it is
     * owned by stock movements, not by this form.
     *
     * @param  array<string, mixed>  $data  Validated request input.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product->name = trim((string) ($data['name'] ?? $product->name));
        $product->category_id = $data['category_id'] ?? null;
        $product->price = (int) ($data['price'] ?? $product->price);

        if (isset($data['status'])) {
            $product->status = $this->status($data['status']);
        }

        if ($product->kind === 'service') {
            $product->pricing_unit = $this->text($data['pricing_unit'] ?? null) ?? 'pcs';
            $product->unit = $this->text($data['unit'] ?? null) ?? 'pcs';
            $product->min_quantity = $this->quantity($data['min_quantity'] ?? 0);
            $product->estimated_duration = $this->text($data['estimated_duration'] ?? null);

            $sku = $this->text($data['sku'] ?? null);
            if ($sku !== null) {
                $product->sku = $sku;
            }
        } else {
            $product->barcode = $this->text($data['barcode'] ?? null);
            $product->cost = $this->money($data['cost'] ?? 0);
            $product->unit = $this->text($data['unit'] ?? null) ?? 'pcs';
            $product->min_stock = $this->quantity($data['min_stock'] ?? 0);

            $sku = $this->text($data['sku'] ?? null);
            if ($sku !== null) {
                $product->sku = $sku;
            }
        }

        $product->save();

        return $product;
    }

    /**
     * Activate or deactivate a catalog record. This is the only "removal"
     * exposed by the dashboard — records are never hard-deleted (sync
     * tombstones travel through the `status` column).
     */
    public function updateStatus(Product|Category $model, string $status): void
    {
        if ($model->status === $status) {
            return;
        }

        $model->status = $status;
        $model->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(Business $business, array $data): Category
    {
        $category = new Category([
            'name' => trim((string) ($data['name'] ?? '')),
            'status' => $this->status($data['status'] ?? null),
        ]);
        $category->business_id = $business->id;
        $category->save();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->name = trim((string) ($data['name'] ?? $category->name));

        if (isset($data['status'])) {
            $category->status = $this->status($data['status']);
        }

        $category->save();

        return $category;
    }

    /**
     * Products need an explicit SKU; services may omit it and receive a stable,
     * business-unique generated one (the column is NOT NULL).
     */
    private function resolveSku(Business $business, string $kind, mixed $sku): string
    {
        $candidate = $this->text($sku);

        if ($candidate !== null) {
            return $candidate;
        }

        do {
            $candidate = ($kind === 'service' ? 'SRV-' : 'SKU-').strtoupper(Str::random(8));
        } while (Product::query()->where('business_id', $business->id)->where('sku', $candidate)->exists());

        return $candidate;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Only the two catalog lifecycle states are accepted; anything else falls
     * back to `active`. `deleted` (the sync tombstone) is never settable here.
     */
    private function status(mixed $value): string
    {
        $status = is_string($value) ? $value : '';

        return in_array($status, self::STATUSES, true) ? $status : 'active';
    }
}
