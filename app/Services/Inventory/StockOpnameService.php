<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock opname sessions (INV-D02).
 *
 * Opening a session snapshots the system stock of every physical product.
 * Posting writes one `opname` ledger movement per non-zero variance, so
 * `products.stock` and `stock_movements` stay consistent.
 */
final class StockOpnameService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function open(Business $business, User $user, array $data = []): StockOpname
    {
        return DB::transaction(function () use ($business, $user, $data): StockOpname {
            $opname = StockOpname::create([
                'business_id' => $business->id,
                'code' => $this->nextCode($business),
                'status' => StockOpname::STATUS_DRAFT,
                'note' => $this->nullableString($data['note'] ?? null),
                'created_by' => $user->id,
            ]);

            Product::query()
                ->where('business_id', $business->id)
                ->where('status', '!=', 'deleted')
                ->where('kind', 'product')
                ->orderBy('id')
                ->chunkById(500, function ($products) use ($opname, $business): void {
                    $now = now();
                    $rows = $products->map(fn (Product $product): array => [
                        'business_id' => $business->id,
                        'stock_opname_id' => $opname->id,
                        'product_id' => $product->id,
                        'system_stock' => number_format((float) $product->stock, 3, '.', ''),
                        'counted_stock' => null,
                        'variance' => '0.000',
                        'unit_cost' => number_format((float) $product->cost, 2, '.', ''),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    if ($rows !== []) {
                        StockOpnameItem::insert($rows);
                    }
                });

            return $opname;
        });
    }

    /**
     * @param  list<array{id: int|string, counted_stock?: int|float|string|null}>  $lines
     */
    public function saveCounts(StockOpname $opname, array $lines): void
    {
        DB::transaction(function () use ($opname, $lines): void {
            $locked = StockOpname::query()->whereKey($opname->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockOpname::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Hitungan hanya dapat diubah saat opname berstatus draft.',
                ]);
            }

            foreach ($lines as $line) {
                /** @var StockOpnameItem|null $item */
                $item = StockOpnameItem::query()
                    ->where('business_id', $locked->business_id)
                    ->where('stock_opname_id', $locked->id)
                    ->whereKey((int) $line['id'])
                    ->first();

                if (! $item instanceof StockOpnameItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Baris opname tidak valid.',
                    ]);
                }

                $counted = $line['counted_stock'] ?? null;

                if ($counted === null || $counted === '') {
                    $item->counted_stock = null;
                    $item->variance = '0.000';
                } else {
                    $item->counted_stock = number_format((float) $counted, 3, '.', '');
                    $item->variance = number_format((float) $counted - (float) $item->system_stock, 3, '.', '');
                }

                $item->save();
            }
        });
    }

    public function post(StockOpname $opname, User $user): int
    {
        return DB::transaction(function () use ($opname, $user): int {
            $locked = StockOpname::query()->whereKey($opname->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockOpname::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Stock opname ini sudah tidak berstatus draft.',
                ]);
            }

            $items = StockOpnameItem::query()
                ->where('business_id', $locked->business_id)
                ->where('stock_opname_id', $locked->id)
                ->whereNotNull('counted_stock')
                ->with('product')
                ->get();

            $movements = 0;

            foreach ($items as $item) {
                $variance = (float) $item->variance;

                if (abs($variance) < 0.0001) {
                    continue;
                }

                if (! $item->product instanceof Product) {
                    continue;
                }

                $this->ledger->record(
                    $locked->business,
                    $item->product,
                    StockMovementType::Opname,
                    $variance,
                    [
                        'note' => 'Opname '.$locked->code,
                        'reference_id' => $locked->code,
                    ],
                    $user,
                );

                $movements++;
            }

            $locked->status = StockOpname::STATUS_POSTED;
            $locked->posted_by = $user->id;
            $locked->posted_at = Carbon::now();
            $locked->save();

            return $movements;
        });
    }

    public function cancel(StockOpname $opname, User $user): void
    {
        DB::transaction(function () use ($opname, $user): void {
            $locked = StockOpname::query()->whereKey($opname->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockOpname::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya opname draft yang dapat dibatalkan.',
                ]);
            }

            $locked->status = StockOpname::STATUS_CANCELLED;
            $locked->posted_by = $user->id;
            $locked->save();
        });
    }

    private function nextCode(Business $business): string
    {
        $prefix = 'OPN-'.now()->format('Ymd').'-';

        $lastCode = StockOpname::query()
            ->where('business_id', $business->id)
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $next = $lastCode !== null ? ((int) substr((string) $lastCode, -4)) + 1 : 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $exists = StockOpname::query()
                ->where('business_id', $business->id)
                ->where('code', $code)
                ->exists();
            $next++;
        } while ($exists);

        return $code;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
