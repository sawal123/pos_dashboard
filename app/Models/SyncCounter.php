<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $current_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 */
#[Fillable(['business_id', 'current_sequence'])]
class SyncCounter extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_sequence' => 'integer',
        ];
    }

    /**
     * The business that owns the sync counter.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
