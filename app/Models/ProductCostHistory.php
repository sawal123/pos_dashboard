<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property string $field
 * @property string|null $old_value
 * @property string $new_value
 * @property string $source
 * @property string|null $reference_id
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property-read Product|null $product
 * @property-read User|null $user
 */
#[Fillable(['business_id', 'product_id', 'field', 'old_value', 'new_value', 'source', 'reference_id', 'user_id', 'created_at'])]
class ProductCostHistory extends Model
{
    public $timestamps = false;

    protected $casts = [
        'old_value' => 'decimal:2',
        'new_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
