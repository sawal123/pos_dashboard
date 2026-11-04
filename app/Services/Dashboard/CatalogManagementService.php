<?php

namespace App\Services\Dashboard;

use App\Enums\CostSource;
use App\Enums\HppMethod;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Category;
use App\Models\Concerns\HasSyncMetadata;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\HppService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
            $attributes['is_sellable'] = true;
            $attributes['cost'] = $this->money(0);
            $attributes['stock'] = $this->quantity(0);
            $attributes['min_stock'] = $this->quantity(0);
            $attributes['unit'] = $this->text($data['unit'] ?? null) ?? 'pcs';
            $attributes['pricing_unit'] = $this->text($data['pricing_unit'] ?? null) ?? 'pcs';
            $attributes['min_quantity'] = $this->quantity($data['min_quantity'] ?? 0);
            $attributes['estimated_duration'] = $this->text($data['estimated_duration'] ?? null);
        } else {
            $attributes['barcode'] = $this->text($data['barcode'] ?? null);
            $attributes['is_sellable'] = $this->sellable($data['is_sellable'] ?? true);
            if (! $attributes['is_sellable']) {
                $attributes['price'] = 0;
            }
            $attributes['cost'] = $this->money($data['cost'] ?? 0);
            $attributes['hpp'] = $this->money($data['cost'] ?? 0);
            $attributes['cost_source'] = $data['cost_source'] ?? CostSource::Manual->value;
            $attributes['hpp_method'] = $data['hpp_method'] ?? HppMethod::Average->value;
            $attributes['hpp_overhead_fixed'] = $this->money($data['hpp_overhead_fixed'] ?? 0);
            $attributes['hpp_overhead_percent'] = number_format((float) ($data['hpp_overhead_percent'] ?? 0), 2, '.', '');
            $attributes['hpp_calculated_at'] = now();
            // Initial stock is an absolute snapshot and is allowed to be
            // negative; it is the only place stock is set from the dashboard.
            $attributes['stock'] = $this->quantity($data['stock'] ?? 0);
            $attributes['unit'] = $this->text($data['unit'] ?? null) ?? 'pcs';
            $attributes['min_stock'] = $this->quantity($data['min_stock'] ?? 0);
            $attributes['pricing_unit'] = 'pcs';
            $attributes['min_quantity'] = $this->quantity(0);
            $attributes['estimated_duration'] = null;
        }

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $storedPath = $data['image']->store('products', 'public');
            if (is_string($storedPath)) {
                $attributes['image_path'] = $storedPath;
            }
        }

        $product = Product::create($attributes);

        if (! empty($product->unit)) {
            Unit::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => strtolower(trim((string) $product->unit))],
                ['status' => 'active']
            );
        }

        if (! empty($data['recipe_items']) && is_array($data['recipe_items'])) {
            /** @var list<array{component_product_id: int|string, quantity: float|string, note?: string|null}> $recipeItems */
            $recipeItems = array_values($data['recipe_items']);
            app(HppService::class)->syncRecipe($product, $recipeItems);
        }

        if (array_key_exists('variant_group_ids', $data) && is_array($data['variant_group_ids'])) {
            $this->syncVariantGroups($product, $data['variant_group_ids']);
        }

        return $product;
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
            $product->is_sellable = array_key_exists('is_sellable', $data)
                ? $this->sellable($data['is_sellable'])
                : (bool) $product->is_sellable;

            if (! $product->is_sellable) {
                $product->price = 0;
            }

            if (array_key_exists('cost_source', $data) && in_array($data['cost_source'], CostSource::values(), true)) {
                $product->cost_source = $data['cost_source'];
            }

            if (array_key_exists('hpp_method', $data) && in_array($data['hpp_method'], HppMethod::values(), true)) {
                $product->hpp_method = $data['hpp_method'];
            }

            if (array_key_exists('hpp_overhead_fixed', $data)) {
                $product->hpp_overhead_fixed = $this->money($data['hpp_overhead_fixed']);
            }

            if (array_key_exists('hpp_overhead_percent', $data)) {
                $product->hpp_overhead_percent = number_format((float) $data['hpp_overhead_percent'], 2, '.', '');
            }

            if (array_key_exists('cost', $data)) {
                $product->cost = $this->money($data['cost']);
                if ($product->hpp_method !== HppMethod::Recipe->value) {
                    $product->hpp = $product->cost;
                    $product->hpp_calculated_at = Carbon::now();
                }
            }

            $product->unit = $this->text($data['unit'] ?? null) ?? 'pcs';
            $product->min_stock = $this->quantity($data['min_stock'] ?? 0);

            if (array_key_exists('stock', $data) && $data['stock'] !== null && $data['stock'] !== '') {
                $newStock = (float) $data['stock'];
                $oldStock = (float) $product->stock;
                if (abs($newStock - $oldStock) >= 0.001) {
                    $product->stock = $this->quantity($newStock);

                    $movement = new StockMovement([
                        'business_id' => $product->business_id,
                        'product_id' => $product->id,
                        'movement_type' => StockMovementType::Adjustment->value,
                        'quantity_change' => $this->quantity($newStock - $oldStock),
                        'stock_before' => $this->quantity($oldStock),
                        'stock_after' => $this->quantity($newStock),
                        'note' => 'Penyesuaian stok via edit produk',
                        'occurred_at' => Carbon::now(),
                    ]);
                    $userId = auth()->id();
                    $movement->user_id = is_numeric($userId) ? (int) $userId : null;
                    $movement->save();
                }
            }

            $sku = $this->text($data['sku'] ?? null);
            if ($sku !== null) {
                $product->sku = $sku;
            }
        }

        if (! empty($data['remove_image'])) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }
            $product->image_path = null;
        } elseif (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }
            $storedPath = $data['image']->store('products', 'public');
            if (is_string($storedPath)) {
                $product->image_path = $storedPath;
            }
        }

        $product->save();

        if (! empty($product->unit)) {
            Unit::query()->firstOrCreate(
                ['business_id' => $product->business_id, 'name' => strtolower(trim((string) $product->unit))],
                ['status' => 'active']
            );
        }

        if (array_key_exists('recipe_items', $data) && is_array($data['recipe_items'])) {
            /** @var list<array{component_product_id: int|string, quantity: float|string, note?: string|null}> $recipeItems */
            $recipeItems = array_values($data['recipe_items']);
            app(HppService::class)->syncRecipe($product, $recipeItems);
        } elseif ($product->hpp_method === HppMethod::Recipe->value) {
            app(HppService::class)->recalculateRecipe($product);
        }

        if (array_key_exists('variant_group_ids', $data) && is_array($data['variant_group_ids'])) {
            $this->syncVariantGroups($product, $data['variant_group_ids']);
        }

        return $product;
    }

    /**
     * @param  array<array-key, mixed>  $variantGroupIds
     */
    public function syncVariantGroups(Product $product, array $variantGroupIds): void
    {
        $syncData = [];
        foreach ($variantGroupIds as $idx => $groupId) {
            $gid = (int) $groupId;
            if ($gid > 0) {
                $syncData[$gid] = [
                    'business_id' => $product->business_id,
                    'sort_order' => $idx + 1,
                ];
            }
        }
        $product->variantGroups()->sync($syncData);
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

    private function sellable(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
