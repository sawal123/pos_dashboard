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
 * A physical stock count session (INV-D02).
 *
 * Items snapshot the system stock when the session opens. Posting the session
 * writes one `opname` stock movement per non-zero variance, so the ledger
 * remains the single explanation for every stock change.
 *
 * @property int $id
 * @property int $business_id
 * @property string $code
 * @property string $status
 * @property string|null $note
 * @property int $created_by
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Collection<int, StockOpnameItem> $items
 */
#[Fillable(['business_id', 'code', 'status', 'note', 'created_by', 'posted_by', 'posted_at'])]
class StockOpname extends Model
{
    use HasSyncMetadata;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_POSTED, self::STATUS_CANCELLED];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
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
     * @return HasMany<StockOpnameItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
