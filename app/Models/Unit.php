<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $symbol
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 */
#[Fillable([
    'business_id',
    'name',
    'symbol',
    'status',
])]
class Unit extends Model
{
    /** @var list<string> */
    public const DEFAULT_UNITS = [
        'pcs',
        'gram',
        'cup',
        'ml',
        'kg',
        'liter',
        'box',
        'botol',
        'pouch',
        'sachet',
    ];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Ensure the business has initial default units seeded and return active units.
     *
     * @return Collection<int, static>
     */
    public static function forBusiness(Business $business): Collection
    {
        $existing = static::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($existing->isEmpty()) {
            $now = now();
            $inserts = array_map(fn ($name) => [
                'business_id' => $business->id,
                'name' => $name,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ], self::DEFAULT_UNITS);

            static::insertOrIgnore($inserts);

            return static::query()
                ->where('business_id', $business->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        return $existing;
    }
}
