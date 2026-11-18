<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * MULTI-OUTLET — a per-branch override of a master product.
 *
 * NULL price/promo_price means "follow the master product".
 *
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property int $product_id
 * @property int|null $price
 * @property int|null $promo_price
 * @property bool $is_available
 * @property float|null $stock
 * @property float|null $min_stock
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Outlet|null $outlet
 * @property-read Product|null $product
 */
#[Fillable(['business_id', 'outlet_id', 'product_id', 'price', 'promo_price', 'is_available', 'stock', 'min_stock'])]
class OutletProduct extends Model
{
    protected $table = 'outlet_product';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_available' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'promo_price' => 'integer',
            'is_available' => 'boolean',
            'stock' => 'float',
            'min_stock' => 'float',
        ];
    }

    /**
     * Whether the row carries no information and can be deleted.
     */
    public function isNeutral(): bool
    {
        return $this->price === null && $this->promo_price === null && $this->is_available && $this->stock === null;
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
