<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Product stock movement history with a stable sync identity. Each logical
 * movement (sale deduction, adjustment) carries a business-unique sync_id and
 * reference, so retries and pulls can never apply the same deduction twice.
 *
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property string $movement_type
 * @property string $quantity_change
 * @property string $stock_before
 * @property string $stock_after
 * @property string|null $reference_id
 * @property string|null $category
 * @property string|null $note
 * @property string|null $sale_sync_id
 * @property int|null $user_id
 * @property string|null $unit_cost
 * @property string|null $idempotency_key
 * @property Carbon $occurred_at
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Product|null $product
 * @property-read User|null $user
 */
#[Fillable(['business_id', 'product_id', 'user_id', 'movement_type', 'quantity_change', 'stock_before', 'stock_after', 'reference_id', 'category', 'note', 'sale_sync_id', 'unit_cost', 'idempotency_key', 'occurred_at'])]
class StockMovement extends Model
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
            'quantity_change' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The business that owns the stock movement.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The product the movement applies to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The dashboard user who recorded the movement (null for device sync).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
