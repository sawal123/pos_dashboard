<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $category_id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property int $price
 * @property string $kind
 * @property string $cost
 * @property string $stock
 * @property string $unit
 * @property string $min_stock
 * @property string $pricing_unit
 * @property string $min_quantity
 * @property string|null $estimated_duration
 * @property string $status
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Category|null $category
 * @property-read Collection<int, SaleItem> $saleItems
 */
#[Fillable(['business_id', 'category_id', 'name', 'sku', 'barcode', 'price', 'kind', 'cost', 'stock', 'unit', 'min_stock', 'pricing_unit', 'min_quantity', 'estimated_duration', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSyncMetadata;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'product',
        'cost' => 0,
        'stock' => 0,
        'unit' => 'pcs',
        'min_stock' => 0,
        'pricing_unit' => 'pcs',
        'min_quantity' => 0,
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'min_quantity' => 'decimal:3',
        ];
    }

    /**
     * The business that owns the product.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The category that the product belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The sale items referencing the product.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
