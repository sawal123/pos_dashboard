<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The dashboard write path for stock (INV-D01).
 *
 * Every mutation locks the product row first, writes exactly one movement and
 * then updates `products.stock` to the movement's `stock_after`. That keeps the
 * ledger and the authoritative stock column consistent, and matches the lock
 * order used by the sync pipeline (Product -> StockMovement).
 */
final class StockLedgerService
{
    /**
     * @param  array<string, mixed>  $meta  note, reference_id, category, unit_cost,
     *                                      idempotency_key, occurred_at
     */
    public function record(
        Business $business,
        Product $product,
        StockMovementType $type,
        float $delta,
        array $meta = [],
        ?User $user = null,
    ): StockMovement {
        if (abs($delta) < 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah stok tidak boleh nol.',
            ]);
        }

        $idempotencyKey = $this->nullableString($meta['idempotency_key'] ?? null);

        try {
            return DB::transaction(function () use ($business, $product, $type, $delta, $meta, $user, $idempotencyKey): StockMovement {
                /** @var Product $locked */
                $locked = Product::query()
                    ->where('business_id', $business->id)
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertTrackable($locked);

                if ($idempotencyKey !== null) {
                    $existing = StockMovement::query()
                        ->where('business_id', $business->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing instanceof StockMovement) {
                        return $existing;
                    }
                }

                $stockBefore = (float) $locked->stock;
                $stockAfter = $stockBefore + $delta;

                $movement = new StockMovement([
                    'business_id' => $business->id,
                    'product_id' => $locked->id,
                    'movement_type' => $type->value,
                    'quantity_change' => $this->quantity($delta),
                    'stock_before' => $this->quantity($stockBefore),
                    'stock_after' => $this->quantity($stockAfter),
                    'reference_id' => $this->nullableString($meta['reference_id'] ?? null),
                    'category' => $this->nullableString($meta['category'] ?? null),
                    'note' => $this->nullableString($meta['note'] ?? null),
                    'idempotency_key' => $idempotencyKey,
                    'unit_cost' => $this->unitCost($meta['unit_cost'] ?? null),
                    'occurred_at' => $this->occurredAt($meta['occurred_at'] ?? null),
                ]);
                $movement->user_id = $user?->id;
                $movement->save();

                $locked->stock = $this->quantity($stockAfter);

                $unitCost = $this->unitCost($meta['unit_cost'] ?? null);
                if ($type === StockMovementType::Receipt && $unitCost !== null) {
                    $locked->cost = $this->weightedAverageCost(
                        $stockBefore,
                        (float) $locked->cost,
                        $delta,
                        (float) $unitCost,
                    );
                }

                $locked->save();

                return $movement;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($idempotencyKey !== null) {
                $existing = StockMovement::query()
                    ->where('business_id', $business->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof StockMovement) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    /**
     * Record a multi-line receiving. The whole batch is atomic: one invalid
     * line rolls back every movement.
     *
     * @param  list<array{product_id: int|string, quantity: int|float|string, unit_cost?: int|float|string|null}>  $lines
     * @param  array<string, mixed>  $meta
     * @return list<StockMovement>
     */
    public function receive(Business $business, User $user, array $lines, array $meta = []): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Minimal satu baris penerimaan wajib diisi.',
            ]);
        }

        $baseKey = $this->nullableString($meta['idempotency_key'] ?? null);

        return DB::transaction(function () use ($business, $user, $lines, $meta, $baseKey): array {
            $movements = [];

            foreach ($lines as $index => $line) {
                /** @var Product $product */
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->whereKey((int) $line['product_id'])
                    ->firstOrFail();

                $movements[] = $this->record(
                    $business,
                    $product,
                    StockMovementType::Receipt,
                    (float) $line['quantity'],
                    array_merge($meta, [
                        'unit_cost' => $line['unit_cost'] ?? null,
                        'idempotency_key' => $baseKey !== null
                            ? substr($baseKey.'-'.$index, 0, 64)
                            : null,
                    ]),
                    $user,
                );
            }

            return $movements;
        });
    }

    private function assertTrackable(Product $product): void
    {
        if ($product->status === 'deleted') {
            throw ValidationException::withMessages([
                'product_id' => 'Produk sudah dihapus dan stoknya tidak dapat diubah.',
            ]);
        }

        if ($product->kind === 'service') {
            throw ValidationException::withMessages([
                'product_id' => 'Layanan tidak memiliki stok fisik.',
            ]);
        }
    }

    private function weightedAverageCost(float $stockBefore, float $oldCost, float $quantity, float $unitCost): string
    {
        $newStock = $stockBefore + $quantity;

        if ($newStock <= 0) {
            return number_format($unitCost, 2, '.', '');
        }

        $value = ($stockBefore * $oldCost) + ($quantity * $unitCost);

        return number_format($value / $newStock, 2, '.', '');
    }

    private function quantity(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function unitCost(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function occurredAt(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return now();
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
