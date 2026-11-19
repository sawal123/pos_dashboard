<?php

namespace App\Models;

use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $code
 * @property bool $is_primary
 * @property string $status
 * @property string|null $address
 * @property string|null $phone
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Subscription|null $branchSubscription
 * @property-read Collection<int, Sale> $sales
 * @property-read Collection<int, Shift> $shifts
 * @property-read Collection<int, Expense> $expenses
 * @property-read Collection<int, Device> $devices
 */
#[Fillable(['business_id', 'name', 'code', 'is_primary', 'status', 'address', 'phone', 'sort_order'])]
class Outlet extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** A newly opened branch that has not been paid for yet. */
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    /** @use HasFactory<OutletFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_primary' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The first outlet of a business is always its primary outlet, so every
        // existing creation path (registration, seeders, factories) stays valid.
        static::creating(function (Outlet $outlet): void {
            if (! $outlet->is_primary && ! static::query()->where('business_id', $outlet->business_id)->exists()) {
                $outlet->is_primary = true;
            }
        });
    }

    /**
     * The business that owns the outlet.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Branch subscription row (only meaningful for non-primary outlets).
     *
     * @return HasOne<Subscription, $this>
     */
    public function branchSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * The subscription that governs this outlet: the business-level row for the
     * primary outlet, the outlet's own row for every branch.
     */
    public function effectiveSubscription(): ?Subscription
    {
        if ($this->is_primary) {
            return $this->business?->subscription;
        }

        return $this->branchSubscription;
    }

    /**
     * Whether devices of this outlet may use cloud features (sync, devices).
     */
    public function hasCloudAccess(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $subscription = $this->effectiveSubscription();

        return $subscription !== null && $subscription->hasCloudAccess();
    }

    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    /**
     * Per-outlet catalog overrides (price / availability).
     *
     * @return HasMany<OutletProduct, $this>
     */
    public function productOverrides(): HasMany
    {
        return $this->hasMany(OutletProduct::class);
    }

    /**
     * Promos explicitly targeted at this outlet.
     *
     * @return BelongsToMany<Promo, $this>
     */
    public function promos(): BelongsToMany
    {
        return $this->belongsToMany(Promo::class, 'promo_outlets')
            ->withPivot('business_id')
            ->withTimestamps();
    }

    /**
     * The sales associated with the outlet.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * The shifts belonging to the outlet.
     *
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * The expenses belonging to the outlet.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The devices belonging to the outlet.
     *
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * The dining tables belonging to the outlet.
     *
     * @return HasMany<DiningTable, $this>
     */
    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort_order')->orderBy('name');
    }
}
