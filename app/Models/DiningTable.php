<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $outlet_id
 * @property string $name
 * @property int|null $capacity
 * @property string $status
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 */
#[Fillable(['business_id', 'outlet_id', 'name', 'capacity', 'status', 'sort_order'])]
class DiningTable extends Model
{
    use HasFactory;

    protected $casts = [
        'capacity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
