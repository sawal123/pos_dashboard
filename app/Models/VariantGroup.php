<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reusable variant group (e.g. 'Level Gula', 'Ukuran Cup', 'Topping').
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $selection_type 'single' | 'multiple'
 * @property bool $is_required
 * @property int $min_selection
 * @property int|null $max_selection
 * @property int $sort_order
 * @property string $status 'active' | 'inactive'
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Collection<int, VariantOption> $options
 * @property-read Collection<int, Product> $products
 */
#[Fillable([
    'business_id',
    'name',
    'selection_type',
    'is_required',
    'min_selection',
    'max_selection',
    'sort_order',
    'status',
])]
class VariantGroup extends Model
{
    use HasSyncMetadata;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'min_selection' => 'integer',
            'max_selection' => 'integer',
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
     * @return HasMany<VariantOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(VariantOption::class, 'variant_group_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<VariantOption, $this>
     */
    public function activeOptions(): HasMany
    {
        return $this->options()->where('status', 'active');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_variant_group')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
