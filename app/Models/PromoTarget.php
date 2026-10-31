<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A product/category a promo applies to.
 *
 * @property int $id
 * @property int $business_id
 * @property int $promo_id
 * @property string $target_type
 * @property int $target_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Promo|null $promo
 */
#[Fillable(['business_id', 'promo_id', 'target_type', 'target_id'])]
class PromoTarget extends Model
{
    /**
     * @return BelongsTo<Promo, $this>
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_id');
    }
}
