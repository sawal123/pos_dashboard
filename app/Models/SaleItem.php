<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property int $quantity
 * @property int $line_total
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Sale|null $sale
 * @property-read Product|null $product
 */
#[Fillable(['business_id', 'sale_id', 'product_id', 'product_name', 'product_sku', 'unit_price', 'quantity', 'line_total'])]
class SaleItem extends Model
{
    use HasSyncMetadata;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
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
