<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Device-ledgered physical cash movements (cash in / cash out), synced with a
 * stable sync identity. A retry of the same logical entry reuses the same
 * sync_id and unique business reference, so cash entries are never doubled.
 *
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property int|null $shift_id
 * @property string $type
 * @property int $amount
 * @property string|null $category
 * @property string|null $note
 * @property string|null $reference_id
 * @property string|null $sale_sync_id
 * @property Carbon $occurred_at
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read Shift|null $shift
 */
#[Fillable(['business_id', 'outlet_id', 'shift_id', 'type', 'amount', 'category', 'note', 'reference_id', 'sale_sync_id', 'occurred_at'])]
class CashLedger extends Model
{
    /** @var string */
    protected $table = 'cash_ledger';

    use HasSyncMetadata;

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
     * The business that owns the cash entry.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The outlet where the cash movement occurred.
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * The shift during which the cash movement occurred.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
