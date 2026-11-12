<?php

namespace App\Models;

use App\Enums\BusinessType;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $business_type
 * @property bool $tax_enabled
 * @property float $tax_rate
 * @property string $cashier_pin
 * @property int $default_opening_cash
 * @property bool $auto_open_shift
 * @property string|null $receipt_logo
 * @property string|null $receipt_address
 * @property string|null $receipt_footer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, User> $owners
 * @property-read Collection<int, Outlet> $outlets
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, Customer> $customers
 * @property-read Collection<int, Sale> $sales
 * @property-read Collection<int, Shift> $shifts
 * @property-read Collection<int, Expense> $expenses
 * @property-read Collection<int, CashLedger> $cashLedger
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read Collection<int, Device> $devices
 * @property-read SyncCounter|null $syncCounter
 * @property-read Collection<int, SyncRequest> $syncRequests
 * @property-read Subscription|null $subscription
 * @property-read Collection<int, SubscriptionPayment> $subscriptionPayments
 * @property-read Collection<int, BusinessInvitation> $invitations
 * @property-read Collection<int, MembershipAuditLog> $membershipAuditLogs
 */
#[Fillable(['name', 'slug', 'status', 'business_type', 'tax_enabled', 'tax_rate', 'cashier_pin', 'default_opening_cash', 'auto_open_shift', 'receipt_logo', 'receipt_address', 'receipt_footer'])]
class Business extends Model
{
    /** Membership role values that have a defined authorization contract. */
    public const ROLE_OWNER = 'owner';

    public const ROLE_MEMBER = 'member';

    public const ROLE_CASHIER = 'cashier';

    /**
     * Every role with an explicit authorization contract (DASH-10B2). Any role
     * outside this list is treated as unknown and denied by default.
     *
     * @var list<string>
     */
    public const ROLES = [self::ROLE_OWNER, self::ROLE_MEMBER, self::ROLE_CASHIER];

    /**
     * Roles an owner may assign to, or remove from, an existing membership.
     *
     * `owner` is deliberately excluded: ownership hand-over is out of scope and
     * a business must always keep at least one owner.
     *
     * @var list<string>
     */
    public const MANAGED_ROLES = [self::ROLE_MEMBER, self::ROLE_CASHIER];

    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'inactive',
        'tax_enabled' => false,
        'tax_rate' => 11.00,
        'cashier_pin' => '123456',
        'default_opening_cash' => 0,
        'auto_open_shift' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'tax_rate' => 'float',
            'default_opening_cash' => 'integer',
            'auto_open_shift' => 'boolean',
            'receipt_logo' => 'string',
            'receipt_address' => 'string',
            'receipt_footer' => 'string',
        ];
    }

    /**
     * Get the dynamic URL or data URI for the store's receipt logo.
     */
    public function receiptLogoUrl(): ?string
    {
        if (empty($this->receipt_logo)) {
            return null;
        }

        if (str_starts_with($this->receipt_logo, '{')) {
            $decoded = json_decode($this->receipt_logo, true);
            if (is_array($decoded) && isset($decoded['width'], $decoded['height'], $decoded['data'])) {
                return $this->rasterToSvgDataUrl((int) $decoded['width'], (int) $decoded['height'], (string) $decoded['data']);
            }
        }

        if (str_starts_with($this->receipt_logo, 'data:')) {
            return $this->receipt_logo;
        }

        return asset('storage/' . $this->receipt_logo);
    }

    /**
     * Payload for sync with mobile context.
     */
    public function receiptLogoPayload(): array|string|null
    {
        if (empty($this->receipt_logo)) {
            return null;
        }

        if (str_starts_with($this->receipt_logo, '{')) {
            $decoded = json_decode($this->receipt_logo, true);
            if (is_array($decoded) && isset($decoded['width'], $decoded['height'], $decoded['data'])) {
                return $decoded;
            }
        }

        return [
            'url' => $this->receiptLogoUrl(),
        ];
    }

    /**
     * Convert mobile 1-bit monochrome raster to standard SVG data URI.
     */
    private function rasterToSvgDataUrl(int $width, int $height, string $base64Data): ?string
    {
        $bytes = base64_decode($base64Data);
        if (! $bytes || strlen($bytes) !== (int) (($width / 8) * $height)) {
            return null;
        }

        $path = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $byteIndex = (int) (($y * $width) / 8 + ($x >> 3));
                $bit = 0x80 >> ($x % 8);
                if ((ord($bytes[$byteIndex]) & $bit) !== 0) {
                    $path .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\"><path fill=\"white\" d=\"M0 0h{$width}v{$height}H0z\"/><path fill=\"black\" d=\"{$path}\"/></svg>";

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (Business $business): void {
            SyncCounter::firstOrCreate([
                'business_id' => $business->id,
            ], [
                'current_sequence' => 0,
            ]);
        });

        static::deleting(function (Business $business): void {
            StockMovement::where('business_id', $business->id)->delete();
            CashLedger::where('business_id', $business->id)->delete();
            SaleItem::where('business_id', $business->id)->delete();
            Sale::where('business_id', $business->id)->delete();
            Expense::where('business_id', $business->id)->delete();
            Shift::where('business_id', $business->id)->delete();
            SyncRequest::where('business_id', $business->id)->delete();
            Device::where('business_id', $business->id)->delete();
            Product::where('business_id', $business->id)->delete();
            Category::where('business_id', $business->id)->delete();
            SyncCounter::where('business_id', $business->id)->delete();
        });
    }

    /**
     * The users that belong to the business.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The owners of the business.
     *
     * @return BelongsToMany<User, $this>
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', self::ROLE_OWNER);
    }

    /**
     * The outlets belonging to the business.
     *
     * @return HasMany<Outlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    /**
     * The categories belonging to the business.
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * The products belonging to the business.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The measurement units belonging to the business.
     *
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * The customers belonging to the business.
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * The sales belonging to the business.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * The shifts belonging to the business.
     *
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * The expenses belonging to the business.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The cash ledger entries belonging to the business.
     *
     * @return HasMany<CashLedger, $this>
     */
    public function cashLedger(): HasMany
    {
        return $this->hasMany(CashLedger::class);
    }

    /**
     * The stock movements belonging to the business.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * The devices belonging to the business.
     *
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * The sync counter associated with the business.
     *
     * @return HasOne<SyncCounter, $this>
     */
    public function syncCounter(): HasOne
    {
        return $this->hasOne(SyncCounter::class);
    }

    /**
     * The sync requests associated with the business.
     *
     * @return HasMany<SyncRequest, $this>
     */
    public function syncRequests(): HasMany
    {
        return $this->hasMany(SyncRequest::class);
    }

    /**
     * The subscription associated with the business.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * The subscription payment attempts associated with the business.
     *
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * The invitations issued for this business.
     *
     * @return HasMany<BusinessInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(BusinessInvitation::class);
    }

    /**
     * The append-only membership audit log entries for this business.
     *
     * @return HasMany<MembershipAuditLog, $this>
     */
    public function membershipAuditLogs(): HasMany
    {
        return $this->hasMany(MembershipAuditLog::class);
    }

    /**
     * Human-readable label for a membership role. Unknown roles are surfaced
     * verbatim (title-cased) and never silently mapped to a known role.
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            self::ROLE_OWNER => 'Pemilik',
            self::ROLE_MEMBER => 'Anggota',
            self::ROLE_CASHIER => 'Kasir',
            '' => 'Tanpa Peran',
            default => ucwords(str_replace(['_', '-'], ' ', $role)),
        };
    }

    /**
     * Determine if the business has the given user as a member.
     */
    public function hasMember(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine if the business is owned by the given user.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->owners()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine if the business has cloud access via subscription.
     */
    public function hasCloudAccess(): bool
    {
        return $this->subscription !== null && $this->subscription->hasCloudAccess();
    }

    /**
     * The persisted business type, normalized from any explicit legacy value.
     * Returns null when the type is not determined — never a guessed default.
     */
    public function businessType(): ?BusinessType
    {
        return BusinessType::tryFromInput($this->business_type);
    }

    /**
     * Canonical stored value (`cafe` / `laundry` / `grosir`) or null (unknown).
     */
    public function normalizedBusinessType(): ?string
    {
        return $this->businessType()?->value;
    }

    /**
     * Display label for the business type, or an explicit "not set" label.
     */
    public function businessTypeLabel(): string
    {
        return $this->businessType()?->label() ?? 'Belum ditentukan';
    }
}
