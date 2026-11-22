<?php

namespace App\Services\Payment;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentMethodService
{
    /**
     * Ensure a business has the default core payment methods seeded.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function ensureDefaults(Business $business): Collection
    {
        $existing = PaymentMethod::query()
            ->where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $defaults = [
            [
                'name' => 'Tunai',
                'code' => 'cash',
                'category' => PaymentMethod::CATEGORY_CASH,
                'is_cash' => true,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Pembayaran uang tunai di laci kasir',
            ],
            [
                'name' => 'QRIS',
                'code' => 'qris',
                'category' => PaymentMethod::CATEGORY_QRIS,
                'is_cash' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Pembayaran scan QRIS',
            ],
            [
                'name' => 'Transfer Bank',
                'code' => 'transfer',
                'category' => PaymentMethod::CATEGORY_TRANSFER,
                'is_cash' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'Transfer antar bank / rekening toko',
            ],
            [
                'name' => 'Kartu / Debit',
                'code' => 'debit',
                'category' => PaymentMethod::CATEGORY_CARD,
                'is_cash' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 4,
                'description' => 'Kartu debit atau kredit via mesin EDC',
            ],
        ];

        $now = now();
        foreach ($defaults as $row) {
            PaymentMethod::firstOrCreate(
                [
                    'business_id' => $business->id,
                    'code' => $row['code'],
                ],
                array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        return PaymentMethod::query()
            ->where('business_id', $business->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Return active payment methods enabled for a specific outlet (or all active methods if outlet is null).
     *
     * @return Collection<int, PaymentMethod>
     */
    public function forOutlet(Business $business, ?Outlet $outlet = null): Collection
    {
        $all = $this->ensureDefaults($business)
            ->where('is_active', true)
            ->values();

        if (! $outlet instanceof Outlet) {
            return $all;
        }

        $overrides = DB::table('outlet_payment_method')
            ->where('business_id', $business->id)
            ->where('outlet_id', $outlet->id)
            ->pluck('is_enabled', 'payment_method_id');

        return $all->filter(function (PaymentMethod $method) use ($overrides): bool {
            if (! $overrides->has($method->id)) {
                return true; // Default enabled unless explicitly unchecked for this outlet
            }

            return (bool) $overrides->get($method->id);
        })->values();
    }

    /**
     * Return all payment methods + active outlets + [method_id][outlet_id] => bool matrix.
     *
     * @return array{
     *     methods: Collection<int, PaymentMethod>,
     *     outlets: Collection<int, Outlet>,
     *     matrix: array<int, array<int, bool>>
     * }
     */
    public function allWithOutletMatrix(Business $business): array
    {
        $methods = $this->ensureDefaults($business);
        $outlets = $business->outlets()
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        $pivotRows = DB::table('outlet_payment_method')
            ->where('business_id', $business->id)
            ->get();

        $pivotMap = [];
        foreach ($pivotRows as $row) {
            $pivotMap[(int) $row->payment_method_id][(int) $row->outlet_id] = (bool) $row->is_enabled;
        }

        $matrix = [];
        foreach ($methods as $method) {
            foreach ($outlets as $outlet) {
                $matrix[$method->id][$outlet->id] = $pivotMap[$method->id][$outlet->id] ?? true;
            }
        }

        return [
            'methods' => $methods,
            'outlets' => $outlets,
            'matrix' => $matrix,
        ];
    }

    /**
     * Generate a unique slug code for a new payment method within a business.
     */
    public function generateUniqueCode(Business $business, string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'method';
        }
        $base = Str::limit($base, 40, '');

        $code = $base;
        $suffix = 2;
        while (PaymentMethod::where('business_id', $business->id)->where('code', $code)->exists()) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * Resolve a PaymentMethod by code or name (case-insensitive) for a business.
     */
    public function resolveMethod(Business $business, string $codeOrName): ?PaymentMethod
    {
        $needle = strtolower(trim($codeOrName));
        if ($needle === '') {
            return null;
        }

        $methods = $this->ensureDefaults($business);

        // Aliases for legacy values
        $aliasCode = match ($needle) {
            'tunai', 'cash' => 'cash',
            'kartu', 'card', 'debit', 'edc' => 'debit',
            default => $needle,
        };

        return $methods->first(function (PaymentMethod $m) use ($needle, $aliasCode): bool {
            return strtolower($m->code) === $needle
                || strtolower($m->code) === $aliasCode
                || strtolower($m->name) === $needle;
        });
    }

    /**
     * Determine whether a payment method code/name counts as physical cash drawer money.
     */
    public function isCashMethod(Business $business, string $codeOrName): bool
    {
        $method = $this->resolveMethod($business, $codeOrName);
        if ($method instanceof PaymentMethod) {
            return (bool) $method->is_cash;
        }

        return in_array(strtolower(trim($codeOrName)), ['cash', 'tunai'], true);
    }

    /**
     * Build payload for GET /api/mobile/context.
     *
     * @return list<array<string, mixed>>
     */
    public function forMobileContext(Business $business, ?int $cashierOutletId = null): array
    {
        $data = $this->allWithOutletMatrix($business);
        $methods = $data['methods']->where('is_active', true)->values();
        $outlets = $data['outlets'];
        $matrix = $data['matrix'];

        $result = [];
        foreach ($methods as $method) {
            $enabledOutletIds = [];
            foreach ($outlets as $outlet) {
                if ($matrix[$method->id][$outlet->id] ?? true) {
                    $enabledOutletIds[] = (int) $outlet->id;
                }
            }

            if ($cashierOutletId !== null && ! in_array($cashierOutletId, $enabledOutletIds, true)) {
                continue;
            }

            $result[] = [
                'id' => (int) $method->id,
                'name' => (string) $method->name,
                'code' => (string) $method->code,
                'category' => (string) $method->category,
                'is_cash' => (bool) $method->is_cash,
                'sort_order' => (int) $method->sort_order,
                'description' => $method->description,
                'enabled_outlet_ids' => $enabledOutletIds,
            ];
        }

        return $result;
    }
}
