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
 * @property int $outlet_id
 * @property int|null $shift_id
 * @property string $description
 * @property int $amount
 * @property string $status
 * @property Carbon $occurred_at
 * @property string|null $notes
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read Shift|null $shift
 */
#[Fillable(['business_id', 'outlet_id', 'shift_id', 'description', 'amount', 'status', 'occurred_at', 'notes'])]
class Expense extends Model
{
    use HasSyncMetadata;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'recorded',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The business that owns the expense.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The outlet where the expense occurred.
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * The shift during which the expense occurred.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
