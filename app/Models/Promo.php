<?php

namespace App\Models;

use App\Enums\PromoDiscountType;
use App\Enums\PromoScope;
use App\Enums\PromoStatus;
use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A server-defined promotion (PROMO-D01).
 *
 * `discount_value` is in basis points for percent promos (1000 = 10%) and in
 * rupiah for fixed/price-override promos. `PromoEvaluator` is the single
 * implementation of the discount math, used by both the dashboard preview and
 * the sync validation path.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $code
 * @property string $discount_type
 * @property int $discount_value
 * @property string $scope
 * @property string $outlet_scope
 * @property int $min_purchase
 * @property int|null $max_discount
 * @property array<int, int>|null $weekdays
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int|null $usage_limit_total
 * @property int|null $usage_limit_per_customer
 * @property int $priority
 * @property bool $is_stackable
 * @property string $status
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property-read Business|null $business
 * @property-read Collection<int, PromoTarget> $targets
 */
#[Fillable([
    'business_id', 'name', 'code', 'discount_type', 'discount_value', 'scope',
    'min_purchase', 'max_discount', 'weekdays', 'starts_at', 'ends_at',
    'start_time', 'end_time', 'usage_limit_total', 'usage_limit_per_customer',
    'priority', 'is_stackable', 'status', 'outlet_scope',
])]
class Promo extends Model
{
    use HasSyncMetadata;

    public const OUTLET_SCOPE_ALL = 'all';

    public const OUTLET_SCOPE_SELECTED = 'selected';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => PromoScope::All->value,
        'outlet_scope' => self::OUTLET_SCOPE_ALL,
        'min_purchase' => 0,
        'priority' => 0,
        'is_stackable' => false,
        'status' => PromoStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'min_purchase' => 'integer',
            'max_discount' => 'integer',
            'weekdays' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit_total' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'priority' => 'integer',
            'is_stackable' => 'boolean',
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
     * @return HasMany<PromoTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(PromoTarget::class);
    }

    /**
     * @return HasMany<SalePromotion, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(SalePromotion::class);
    }

    /**
     * Outlets a `selected`-scope promo is limited to.
     *
     * @return BelongsToMany<Outlet, $this>
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'promo_outlets')
            ->withPivot('business_id')
            ->withTimestamps();
    }

    /**
     * Whether the promo applies at the given outlet.
     */
    public function appliesToOutlet(?int $outletId): bool
    {
        if ($this->outlet_scope !== self::OUTLET_SCOPE_SELECTED || $outletId === null) {
            return true;
        }

        return $this->outlets->contains('id', $outletId);
    }

    /**
     * Restrict a promo query to promos valid at the given outlet.
     *
     * @param  Builder<Promo>  $query
     * @return Builder<Promo>
     */
    public function scopeForOutlet(Builder $query, ?int $outletId): Builder
    {
        if ($outletId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($outletId): void {
            $q->where('outlet_scope', '!=', self::OUTLET_SCOPE_SELECTED)
                ->orWhereHas('outlets', fn (Builder $o) => $o->where('outlets.id', $outletId));
        });
    }

    public function discountType(): ?PromoDiscountType
    {
        return PromoDiscountType::tryFrom((string) $this->discount_type);
    }
}
