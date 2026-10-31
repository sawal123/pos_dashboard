<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One counted product inside a stock opname session.
 *
 * @property int $id
 * @property int $business_id
 * @property int $stock_opname_id
 * @property int $product_id
 * @property string $system_stock
 * @property string|null $counted_stock
 * @property string $variance
 * @property string|null $unit_cost
 * @property-read Product|null $product
 */
#[Fillable(['business_id', 'stock_opname_id', 'product_id', 'system_stock', 'counted_stock', 'variance', 'unit_cost'])]
class StockOpnameItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_stock' => 'decimal:3',
            'counted_stock' => 'decimal:3',
            'variance' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockOpname, $this>
     */
    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
