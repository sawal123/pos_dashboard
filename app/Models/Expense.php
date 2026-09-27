<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property int|null $shift_id
 * @property string $description
 * @property string|null $category
 * @property int $amount
 * @property string $status
 * @property Carbon $occurred_at
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read Shift|null $shift
 * @property-read CashLedger|null $cashLedger
 */
#[Fillable(['business_id', 'outlet_id', 'shift_id', 'description', 'category', 'amount', 'status', 'occurred_at', 'notes', 'idempotency_key'])]
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

    /**
     * The linked cash movement when the expense was explicitly paid from cash.
     *
     * The payment ledger is identified deterministically as the single cash-out
     * (`type = out`) row for this expense that is not itself a correction
     * (`reverses_ledger_id` is null). Void refunds and legacy reversals preserve
     * the same `expense_id` but are cash-in corrections, so they can never be
     * mistaken for the original payment.
     *
     * @return HasOne<CashLedger, $this>
     */
    public function cashLedger(): HasOne
    {
        return $this->hasOne(CashLedger::class, 'expense_id')
            ->where('type', 'out')
            ->whereNull('reverses_ledger_id');
    }
}
