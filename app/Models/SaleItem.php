<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $sale_id
 * @property int $product_id
 * @property string $product_name
 * @property string $product_sku
 * @property int $unit_price
 * @property string $quantity
 * @property int $line_total
 * @property string $cost_snapshot
 * @property string $unit
 * @property string $kind
 * @property string $pricing_unit
 * @property string $line_cost
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale|null $sale
 * @property-read Product|null $product
 */
#[Fillable(['business_id', 'sale_id', 'product_id', 'product_name', 'product_sku', 'unit_price', 'quantity', 'line_total', 'cost_snapshot', 'unit', 'kind', 'pricing_unit', 'line_cost'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory, HasSyncMetadata;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'decimal:3',
            'line_total' => 'integer',
            'cost_snapshot' => 'decimal:2',
            'line_cost' => 'decimal:2',
        ];
    }

    /**
     * The sale that owns the sale item.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The product referenced by the sale item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
