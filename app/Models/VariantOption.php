<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An individual option in a variant group (e.g. 'Less Sugar', 'Large').
 *
 * @property int $id
 * @property int $business_id
 * @property int $variant_group_id
 * @property string $name
 * @property int $price_adjustment
 * @property int $cost_adjustment
 * @property int|null $ingredient_product_id
 * @property string $ingredient_quantity
 * @property bool $is_default
 * @property int $sort_order
 * @property string $status 'active' | 'inactive'
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read VariantGroup|null $group
 * @property-read Product|null $ingredientProduct
 */
#[Fillable([
    'business_id',
    'variant_group_id',
    'name',
    'price_adjustment',
    'cost_adjustment',
    'ingredient_product_id',
    'ingredient_quantity',
    'is_default',
    'sort_order',
    'status',
])]
class VariantOption extends Model
{
    use HasSyncMetadata;

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'integer',
            'cost_adjustment' => 'integer',
            'ingredient_quantity' => 'decimal:4',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'sync_version' => 'integer',
            'sync_sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<VariantGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(VariantGroup::class, 'variant_group_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function ingredientProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
