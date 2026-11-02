<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Traceability row linking a sale to the promo that discounted it.
 *
 * @property int $id
 * @property int $business_id
 * @property int $sale_id
 * @property int $promo_id
 * @property string $promo_sync_id
 * @property int $discount_amount
 * @property Carbon|null $created_at
 */
#[Fillable(['business_id', 'sale_id', 'promo_id', 'promo_sync_id', 'discount_amount', 'created_at'])]
class SalePromotion extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Promo, $this>
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }
}
