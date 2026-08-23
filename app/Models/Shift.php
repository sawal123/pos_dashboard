<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property string $shift_number
 * @property string $status
 * @property int $opening_cash
 * @property int|null $closing_cash
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read Collection<int, Sale> $sales
 * @property-read Collection<int, Expense> $expenses
 */
#[Fillable(['business_id', 'outlet_id', 'shift_number', 'status', 'opening_cash', 'closing_cash', 'opened_at', 'closed_at', 'notes'])]
class Shift extends Model
{
    use HasSyncMetadata;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'opening_cash' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_cash' => 'integer',
            'closing_cash' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The business that owns the shift.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The outlet where the shift took place.
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * The sales recorded during this shift.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * The expenses recorded during this shift.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
