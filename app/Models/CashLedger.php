<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property int|null $expense_id
 * @property int|null $reverses_ledger_id
 * @property string|null $idempotency_key
 * @property Carbon $occurred_at
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read Shift|null $shift
 * @property-read Expense|null $expense
 * @property-read CashLedger|null $reverses
 * @property-read CashLedger|null $reversal
 */
#[Fillable(['business_id', 'outlet_id', 'shift_id', 'type', 'amount', 'category', 'note', 'reference_id', 'sale_sync_id', 'expense_id', 'reverses_ledger_id', 'idempotency_key', 'occurred_at'])]
class CashLedger extends Model
{
    /**
     * Category of the cash-out row created when an expense is paid from cash.
     */
    public const CATEGORY_EXPENSE = 'expense';

    /**
     * Category of a manual reversal appended by the dashboard.
     */
    public const CATEGORY_REVERSAL = 'reversal';

    /**
     * Category of the cash-in row appended when an expense is voided.
     */
    public const CATEGORY_EXPENSE_VOID = 'expense_void';

    /**
     * Reference prefix stamped on manual cash entries created by the dashboard.
     */
    public const REFERENCE_PREFIX_MANUAL = 'DASH-CASH-';

    /** @var string */
    protected $table = 'cash_ledger';

    use HasSyncMetadata;

    /**
     * Build the deterministic dashboard reference for a manual cash entry.
     *
     * The dashboard stamps both `reference_id` and `idempotency_key` on manual
     * entries, and the reference is derived from the key, so the two together
     * are a verifiable origin identity that POS Mobile sync cannot produce.
     */
    public static function manualReferenceId(string $idempotencyKey): string
    {
        return self::REFERENCE_PREFIX_MANUAL.strtoupper(substr(str_replace('-', '', $idempotencyKey), 0, 12));
    }

    /**
     * Whether this row is verifiably a manual entry created by the dashboard.
     *
     * POS Mobile rows arrive through sync, which never supplies
     * `idempotency_key` and stamps a device-local `reference_id`. Requiring the
     * deterministic dashboard reference/idempotency-key pair keeps those rows
     * out of the dashboard reversal flow.
     */
    public function hasDashboardManualOrigin(): bool
    {
        $idempotencyKey = $this->idempotency_key;

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return false;
        }

        return $this->reference_id === self::manualReferenceId($idempotencyKey);
    }

    /**
     * Correction rows always carry a `reverses_ledger_id`, so a row is only
     * reversible when it is a plain manual dashboard entry: never a
     * sale-synced settlement, never an expense payment, never a correction of
     * an earlier row, and never a manual cash entry pushed from POS Mobile.
     */
    public function isManuallyReversible(): bool
    {
        return $this->sale_sync_id === null
            && $this->expense_id === null
            && $this->reverses_ledger_id === null
            && $this->category !== self::CATEGORY_REVERSAL
            && $this->category !== self::CATEGORY_EXPENSE_VOID
            && $this->hasDashboardManualOrigin();
    }

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

    /**
     * The expense this cash movement paid for or reverses, when applicable.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * The ledger row this row corrects, when this row is a correction.
     *
     * @return BelongsTo<CashLedger, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(CashLedger::class, 'reverses_ledger_id');
    }

    /**
     * The correction appended for this ledger row, when one exists.
     *
     * @return HasOne<CashLedger, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(CashLedger::class, 'reverses_ledger_id');
    }
}
