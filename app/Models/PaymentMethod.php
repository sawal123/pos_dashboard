<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaymentMethod extends Model
{
    public const CATEGORY_CASH = 'cash';

    public const CATEGORY_QRIS = 'qris';

    public const CATEGORY_TRANSFER = 'transfer';

    public const CATEGORY_CARD = 'card';

    public const CATEGORY_EWALLET = 'ewallet';

    public const CATEGORY_ONLINE_FOOD = 'online_food';

    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'category',
        'is_cash',
        'is_system',
        'is_active',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'outlet_payment_method')
            ->withPivot(['business_id', 'is_enabled'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_CASH => 'Tunai (Kas Laci)',
            self::CATEGORY_QRIS => 'QRIS',
            self::CATEGORY_TRANSFER => 'Transfer Bank',
            self::CATEGORY_CARD => 'Kartu Debit / EDC',
            self::CATEGORY_EWALLET => 'e-Wallet (GoPay, OVO, DANA, dll)',
            self::CATEGORY_ONLINE_FOOD => 'Online Food (GoFood, GrabFood, ShopeeFood)',
            self::CATEGORY_OTHER => 'Lainnya',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? 'Lainnya';
    }
}
