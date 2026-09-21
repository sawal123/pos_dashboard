<?php

namespace App\Models;

use App\Models\Concerns\HasSyncMetadata;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $outlet_id
 * @property int|null $customer_id
 * @property int|null $shift_id
 * @property string $transaction_number
 * @property string $status
 * @property int $subtotal
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $total_amount
 * @property string|null $payment_method
 * @property string $payment_status
 * @property Carbon|null $paid_at
 * @property int|null $cash_received
 * @property int|null $change_amount
 * @property string $gross_profit
 * @property string|null $order_status
 * @property Carbon|null $estimated_completed_at
 * @property string|null $note
 * @property array<string, mixed>|null $customer_snapshot
 * @property array<string, mixed>|null $business_snapshot
 * @property Carbon $sold_at
 * @property string $sync_id
 * @property int $sync_version
 * @property int $sync_sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Outlet|null $outlet
 * @property-read Customer|null $customer
 * @property-read Shift|null $shift
 * @property-read Collection<int, SaleItem> $items
 */
#[Fillable(['business_id', 'outlet_id', 'customer_id', 'shift_id', 'transaction_number', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'payment_method', 'payment_status', 'paid_at', 'cash_received', 'change_amount', 'gross_profit', 'order_status', 'estimated_completed_at', 'note', 'customer_snapshot', 'business_snapshot', 'sold_at'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, HasSyncMetadata;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'completed',
        'discount_amount' => 0,
        'tax_amount' => 0,
        'payment_status' => 'paid',
        'gross_profit' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'cash_received' => 'integer',
            'change_amount' => 'integer',
            'paid_at' => 'datetime',
            'gross_profit' => 'decimal:2',
            'estimated_completed_at' => 'datetime',
            'customer_snapshot' => 'array',
            'business_snapshot' => 'array',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * The business that owns the sale.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The outlet where the sale took place.
     *
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * The customer associated with the sale.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The shift during which the sale took place.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * The items belonging to the sale.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
