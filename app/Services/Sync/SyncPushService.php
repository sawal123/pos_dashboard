<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncPushService
{
    /**
     * Process a push sync payload from mobile device.
     *
     * @param  array{user: User, business: Business, device: Device, outlet_id: int}  $context
     * @param  array<string, mixed>  $payload
     */
    public function process(array $context, array $payload): JsonResponse
    {
        $business = $context['business'];
        $device = $context['device'];
        $outletId = $context['outlet_id'];
        $requestId = (string) $payload['request_id'];
        /** @var array<string, list<array<string, mixed>>> $changes */
        $changes = $payload['changes'] ?? [];

        // 1. Idempotency Check
        $existingRequest = SyncRequest::where('business_id', $business->id)
            ->where('device_id', $device->id)
            ->where('request_id', $requestId)
            ->first();

        if ($existingRequest) {
            $device->update(['last_seen_at' => now()]);

            return response()->json([
                'data' => [
                    'request_id' => $requestId,
                    'duplicate' => true,
                ],
            ]);
        }

        // 2. Transactional processing of changes
        try {
            DB::transaction(function () use ($business, $outletId, $device, $requestId, $changes): void {
                $this->processCategories($business, $changes['categories'] ?? []);
                $this->processProducts($business, $changes['products'] ?? []);
                $this->processCustomers($business, $changes['customers'] ?? []);
                $this->processShifts($business, $outletId, $changes['shifts'] ?? []);
                $this->processSales($business, $outletId, $changes['sales'] ?? []);
                $this->processSaleItems($business, $outletId, $changes['sale_items'] ?? []);
                $this->processExpenses($business, $outletId, $changes['expenses'] ?? []);
                $this->processCashLedger($business, $outletId, $changes['cash_ledger'] ?? []);
                $this->processStockMovements($business, $changes['stock_movements'] ?? []);
                $this->processDeletions($business, $outletId, $changes['deletions'] ?? []);

                SyncRequest::create([
                    'business_id' => $business->id,
                    'device_id' => $device->id,
                    'request_id' => $requestId,
                    'processed_at' => now(),
                ]);
            });
        } catch (SyncConflictException $e) {
            return response()->json([
                'message' => 'Sync conflict.',
                'code' => 'SYNC_CONFLICT',
                'conflicts' => [
                    [
                        'entity' => $e->entity,
                        'sync_id' => $e->syncId,
                        'server_sync_version' => $e->serverVersion,
                    ],
                ],
            ], 409);
        } catch (SyncStateRequiredException $e) {
            return response()->json([
                'message' => 'Sync requires explicit recovery.',
                'code' => $e->stateCode,
                'state' => $e->stateCode,
                'details' => array_merge([
                    'entity' => $e->entity,
                    'sync_id' => $e->syncId,
                ], $e->details),
            ], 409);
        } catch (QueryException $e) {
            // Check if duplicate request_id race occurred and was committed by concurrent request
            $duplicate = SyncRequest::where('business_id', $business->id)
                ->where('device_id', $device->id)
                ->where('request_id', $requestId)
                ->exists();

            if ($duplicate) {
                $device->update(['last_seen_at' => now()]);

                return response()->json([
                    'data' => [
                        'request_id' => $requestId,
                        'duplicate' => true,
                    ],
                ]);
            }

            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $driverCode = (int) ($e->errorInfo[1] ?? 0);

            // Pure concurrency contention (deadlock, lock wait timeout, or a
            // row that changed between a snapshot read and the locking read).
            // Nothing was applied and nothing is lost: the device keeps its
            // durable outbox and retries the same request for convergence.
            if ($sqlState === '40001' || in_array($driverCode, [1213, 1205, 1020], true)) {
                return response()->json([
                    'message' => 'Transient concurrency contention; retry the same request.',
                    'code' => 'SYNC_RETRYABLE_CONFLICT',
                    'retryable' => true,
                    'request_id' => $requestId,
                ], 409);
            }

            return response()->json([
                'message' => 'Sync data conflict.',
                'code' => 'SYNC_DATA_CONFLICT',
            ], 409);
        }

        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'data' => [
                'request_id' => $requestId,
                'duplicate' => false,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processCategories(Business $business, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = Category::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'name' => (string) $item['name'],
                'status' => (string) ($item['status'] ?? 'active'),
            ];

            if ($record) {
                // Idempotent auto-resolution: the server already holds exactly
                // this mutation, so no version conflict needs to be raised.
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('categories', $record, $syncId, $baseVersion);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('categories', null, $syncId, $baseVersion);

                $category = new Category($attributes);
                $category->business_id = $business->id;
                $category->sync_id = $syncId;
                $category->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processProducts(Business $business, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = Product::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $categoryId = null;
            if (! empty($item['category_sync_id'])) {
                $categoryId = Category::where('business_id', $business->id)
                    ->where('sync_id', (string) $item['category_sync_id'])
                    ->value('id');

                if (! $categoryId) {
                    throw new SyncConflictException('products', $syncId, $record instanceof Product ? (int) $record->sync_version : 0);
                }
            }

            $semantic = $this->productSemanticFields($item);

            if ($record instanceof Product) {
                // Server delta authority: current stock is derived from accepted
                // stock movements, never from a client absolute snapshot. A
                // concurrent multi-device snapshot would otherwise be a lost
                // update (10 -> 7 -> 6 instead of 10 - 3 - 4 = 3).
                unset($semantic['stock']);
            }

            $attributes = array_merge([
                'category_id' => $categoryId,
                'name' => (string) $item['name'],
                'sku' => (string) $item['sku'],
                'barcode' => isset($item['barcode']) ? (string) $item['barcode'] : null,
                'price' => (int) $item['price'],
                'status' => (string) ($item['status'] ?? 'active'),
            ], $semantic);

            if ($record instanceof Product) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('products', $record, $syncId, $baseVersion);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('products', null, $syncId, $baseVersion);

                $product = new Product($attributes);
                $product->business_id = $business->id;
                $product->sync_id = $syncId;
                $product->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processCustomers(Business $business, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = Customer::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'name' => (string) $item['name'],
                'phone' => isset($item['phone']) ? (string) $item['phone'] : null,
                'email' => isset($item['email']) ? (string) $item['email'] : null,
                'address' => isset($item['address']) ? (string) $item['address'] : null,
                'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
                'status' => (string) ($item['status'] ?? 'active'),
            ];

            if ($record) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('customers', $record, $syncId, $baseVersion);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('customers', null, $syncId, $baseVersion);

                $customer = new Customer($attributes);
                $customer->business_id = $business->id;
                $customer->sync_id = $syncId;
                $customer->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processShifts(Business $business, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = Shift::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'shift_number' => (string) $item['shift_number'],
                'status' => (string) ($item['status'] ?? 'open'),
                'opening_cash' => (int) ($item['opening_cash'] ?? 0),
                'closing_cash' => isset($item['closing_cash']) ? (int) $item['closing_cash'] : null,
                'opened_at' => Carbon::parse((string) $item['opened_at']),
                'closed_at' => isset($item['closed_at']) ? Carbon::parse((string) $item['closed_at']) : null,
                'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
            ];

            if ($record) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('shifts', $record, $syncId, $baseVersion, $outletId);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('shifts', null, $syncId, $baseVersion, $outletId);

                $shift = new Shift($attributes);
                $shift->business_id = $business->id;
                $shift->outlet_id = $outletId;
                $shift->sync_id = $syncId;
                $shift->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processSales(Business $business, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = Sale::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $customerId = null;
            if (! empty($item['customer_sync_id'])) {
                $customerId = Customer::where('business_id', $business->id)
                    ->where('sync_id', (string) $item['customer_sync_id'])
                    ->value('id');

                if (! $customerId) {
                    throw new SyncConflictException('sales', $syncId, $record instanceof Sale ? (int) $record->sync_version : 0);
                }
            }

            $shiftId = null;
            if (! empty($item['shift_sync_id'])) {
                $shiftId = Shift::where('business_id', $business->id)
                    ->where('outlet_id', $outletId)
                    ->where('sync_id', (string) $item['shift_sync_id'])
                    ->value('id');

                if (! $shiftId) {
                    throw new SyncConflictException('sales', $syncId, $record instanceof Sale ? (int) $record->sync_version : 0);
                }
            }

            $payment = $this->salePaymentFields($item);
            $snapshots = $this->saleSnapshotFields($item, $customerId);

            $attributes = array_merge([
                'customer_id' => $customerId,
                'shift_id' => $shiftId,
                'transaction_number' => (string) $item['transaction_number'],
                'status' => (string) ($item['status'] ?? 'completed'),
                'subtotal' => (int) $item['subtotal'],
                'discount_amount' => (int) ($item['discount_amount'] ?? 0),
                'tax_amount' => (int) ($item['tax_amount'] ?? 0),
                'total_amount' => (int) $item['total_amount'],
                'sold_at' => Carbon::parse((string) $item['sold_at']),
            ], $payment, $snapshots);

            if ($record instanceof Sale) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                // Laundry lifecycle regression guard: a stale device must never
                // move an order backwards (Selesai -> Siap Diambil, and so on).
                if (array_key_exists('order_status', $attributes)) {
                    $incomingRank = $this->lifecycleRank($attributes['order_status']);
                    $serverRank = $this->lifecycleRank($record->order_status);

                    if ($incomingRank !== null && $serverRank !== null && $incomingRank < $serverRank) {
                        throw new SyncConflictException('sales', $syncId, (int) $record->sync_version);
                    }
                }

                $this->validateConcurrency('sales', $record, $syncId, $baseVersion, $outletId);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('sales', null, $syncId, $baseVersion, $outletId);

                $sale = new Sale($attributes);
                $sale->business_id = $business->id;
                $sale->outlet_id = $outletId;
                $sale->sync_id = $syncId;
                $sale->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processSaleItems(Business $business, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = SaleItem::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            if ($record) {
                // Ensure sale belongs to device outlet
                $saleOutletId = Sale::where('id', $record->sale_id)->value('outlet_id');
                if ((int) $saleOutletId !== $outletId) {
                    throw new SyncConflictException('sale_items', $syncId, (int) $record->sync_version);
                }
            }

            $saleId = Sale::where('business_id', $business->id)
                ->where('outlet_id', $outletId)
                ->where('sync_id', (string) $item['sale_sync_id'])
                ->value('id');

            if (! $saleId) {
                throw new SyncConflictException('sale_items', $syncId, $record instanceof SaleItem ? (int) $record->sync_version : 0);
            }

            $productId = Product::where('business_id', $business->id)
                ->where('sync_id', (string) $item['product_sync_id'])
                ->value('id');

            if (! $productId) {
                throw new SyncConflictException('sale_items', $syncId, $record instanceof SaleItem ? (int) $record->sync_version : 0);
            }

            $snapshots = $this->saleItemSnapshotFields($item);

            $attributes = array_merge([
                'sale_id' => $saleId,
                'product_id' => $productId,
                'product_name' => (string) $item['product_name'],
                'product_sku' => (string) $item['product_sku'],
                'unit_price' => (int) $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_total' => (int) $item['line_total'],
            ], $snapshots);

            if ($record instanceof SaleItem) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('sale_items', $record, $syncId, $baseVersion);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('sale_items', null, $syncId, $baseVersion);

                $saleItem = new SaleItem($attributes);
                $saleItem->business_id = $business->id;
                $saleItem->sync_id = $syncId;
                $saleItem->save();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processExpenses(Business $business, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $record = Expense::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $shiftId = null;
            if (! empty($item['shift_sync_id'])) {
                $shiftId = Shift::where('business_id', $business->id)
                    ->where('outlet_id', $outletId)
                    ->where('sync_id', (string) $item['shift_sync_id'])
                    ->value('id');

                if (! $shiftId) {
                    throw new SyncConflictException('expenses', $syncId, $record instanceof Expense ? (int) $record->sync_version : 0);
                }
            }

            $category = isset($item['category']) && $item['category'] !== ''
                ? (string) $item['category']
                : null;

            $attributes = [
                'shift_id' => $shiftId,
                'description' => (string) $item['description'],
                'amount' => (int) $item['amount'],
                'status' => (string) ($item['status'] ?? 'recorded'),
                'occurred_at' => Carbon::parse((string) $item['occurred_at']),
                'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
            ];

            // An absent/empty category is not a real edit: it must not clear
            // the stored value on update.
            if ($category !== null) {
                $attributes['category'] = $category;
            }

            if ($record instanceof Expense) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('expenses', $record, $syncId, $baseVersion, $outletId);

                $record->fill($attributes);
                $record->save();
            } else {
                $this->validateConcurrency('expenses', null, $syncId, $baseVersion, $outletId);

                $expense = new Expense($attributes);
                $expense->business_id = $business->id;
                $expense->outlet_id = $outletId;
                $expense->sync_id = $syncId;
                $expense->save();
            }
        }
    }

    /**
     * Extract optional product semantic fields present in the payload.
     * Fields absent from the payload are left untouched on update.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function productSemanticFields(array $item): array
    {
        $fields = [];
        $semanticKeys = ['kind', 'cost', 'stock', 'unit', 'min_stock', 'pricing_unit', 'min_quantity', 'estimated_duration'];

        foreach ($semanticKeys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                $fields[$key] = $item[$key];
            }
        }

        return $fields;
    }

    /**
     * Extract optional sale payment snapshot fields present in the payload.
     * Fields absent from the payload are left untouched on update.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function salePaymentFields(array $item): array
    {
        $fields = [];

        if (array_key_exists('payment_method', $item)) {
            $fields['payment_method'] = $item['payment_method'] !== null ? (string) $item['payment_method'] : null;
        }

        if (array_key_exists('payment_status', $item)) {
            $fields['payment_status'] = (string) $item['payment_status'];
        }

        foreach (['paid_at', 'cash_received', 'change_amount'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                $fields[$key] = $item[$key];
            }
        }

        return $fields;
    }

    /**
     * Extract the immutable historical sale snapshot fields.
     * Fields absent from the payload are left untouched on update; the
     * customer/business snapshots are stored as-is and never recomputed.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function saleSnapshotFields(array $item, ?int $resolvedCustomerId): array
    {
        $fields = [];

        if (array_key_exists('gross_profit', $item) && $item['gross_profit'] !== null) {
            $fields['gross_profit'] = $item['gross_profit'];
        }

        foreach (['order_status', 'estimated_completed_at', 'note'] as $key) {
            if (array_key_exists($key, $item)) {
                $fields[$key] = $item[$key] !== null ? $item[$key] : null;
            }
        }

        if (array_key_exists('customer_snapshot', $item)) {
            $fields['customer_snapshot'] = $this->normalizeCustomerSnapshot($item['customer_snapshot'], $resolvedCustomerId);
        }

        if (array_key_exists('business_snapshot', $item)) {
            $fields['business_snapshot'] = $this->normalizeBusinessSnapshot($item['business_snapshot']);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalizeCustomerSnapshot(mixed $snapshot, ?int $resolvedCustomerId): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        if (! is_array($snapshot)) {
            return null;
        }

        return [
            'id' => $snapshot['id'] ?? $resolvedCustomerId,
            'name' => isset($snapshot['name']) ? (string) $snapshot['name'] : '',
            'phone' => isset($snapshot['phone']) ? (string) $snapshot['phone'] : '',
            'email' => isset($snapshot['email']) ? (string) $snapshot['email'] : '',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    protected function normalizeBusinessSnapshot(mixed $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        if (! is_array($snapshot)) {
            return null;
        }

        return [
            'name' => isset($snapshot['name']) ? (string) $snapshot['name'] : '',
            'outlet' => isset($snapshot['outlet']) ? (string) $snapshot['outlet'] : '',
            'phone' => isset($snapshot['phone']) ? (string) $snapshot['phone'] : '',
        ];
    }

    /**
     * Extract the historical per-item snapshot fields.
     * Fields absent from the payload are left untouched on update; historical
     * HPP is always the pushed snapshot, never recomputed from Product.cost.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function saleItemSnapshotFields(array $item): array
    {
        $fields = [];

        foreach (['cost_snapshot', 'line_cost'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                $fields[$key] = $item[$key];
            }
        }

        foreach (['unit', 'pricing_unit'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                $fields[$key] = (string) $item[$key];
            }
        }

        if (array_key_exists('kind', $item) && in_array($item['kind'], ['product', 'service'], true)) {
            $fields['kind'] = $item['kind'];
        }

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processCashLedger(Business $business, int $outletId, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            $itemSaleSyncId = isset($item['sale_sync_id']) && $item['sale_sync_id'] !== ''
                ? (string) $item['sale_sync_id']
                : null;

            if ($itemSaleSyncId !== null) {
                // Serialize concurrent settlements of one logical order on the
                // order row itself, before any cash_ledger row lock is taken.
                Sale::where('business_id', $business->id)
                    ->where('sync_id', $itemSaleSyncId)
                    ->lockForUpdate()
                    ->first();
            }

            // Stable idempotency is the sync_id: a retry of the same request
            // replays the same sync_id through request_id dedupe or the update
            // path below, and is applied exactly once.
            $record = CashLedger::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $shiftId = null;
            if (! empty($item['shift_sync_id'])) {
                $shiftId = Shift::where('business_id', $business->id)
                    ->where('outlet_id', $outletId)
                    ->where('sync_id', (string) $item['shift_sync_id'])
                    ->value('id');

                if (! $shiftId) {
                    throw new SyncConflictException('cash_ledger', $syncId, $record instanceof CashLedger ? (int) $record->sync_version : 0);
                }
            }

            $attributes = [
                'type' => (string) $item['type'],
                'amount' => (int) $item['amount'],
                'category' => isset($item['category']) ? (string) $item['category'] : null,
                'note' => isset($item['note']) ? (string) $item['note'] : null,
                'reference_id' => isset($item['reference_id']) ? (string) $item['reference_id'] : null,
                'sale_sync_id' => isset($item['sale_sync_id']) ? (string) $item['sale_sync_id'] : null,
                'occurred_at' => Carbon::parse((string) $item['occurred_at']),
                'shift_id' => $shiftId,
            ];

            if ($record instanceof CashLedger) {
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                $this->validateConcurrency('cash_ledger', $record, $syncId, $baseVersion, $outletId);

                $record->fill($attributes);
                $record->save();

                continue;
            }

            // Cash exactly-once: a sale payment is deduped by its logical
            // sale/payment identity (sale_sync_id + direction), never by note,
            // reference string or timestamp. Two devices that settled the same
            // offline order therefore produce exactly one logical settlement.
            if (! empty($attributes['sale_sync_id'])) {
                $logical = CashLedger::where('business_id', $business->id)
                    ->where('sale_sync_id', $attributes['sale_sync_id'])
                    ->where('type', $attributes['type'])
                    ->lockForUpdate()
                    ->first();

                if ($logical instanceof CashLedger) {
                    // Identical logical settlement: idempotent equivalent, no
                    // new row. Note, reference string and timestamp are
                    // device-local and never part of the payment identity.
                    $sameSettlement = $this->attributesMatch($logical, [
                        'type' => $attributes['type'],
                        'amount' => $attributes['amount'],
                        'category' => $attributes['category'],
                    ]);

                    if ($sameSettlement) {
                        continue;
                    }

                    // Different amount/method for the same logical payment:
                    // a deterministic conflict, never a silent second cash row.
                    throw new SyncConflictException('cash_ledger', $syncId, (int) $logical->sync_version);
                }
            }

            $this->validateConcurrency('cash_ledger', null, $syncId, $baseVersion, $outletId);

            $entry = new CashLedger($attributes);
            $entry->business_id = $business->id;
            $entry->outlet_id = $outletId;
            $entry->sync_id = $syncId;
            $entry->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function processStockMovements(Business $business, array $items): void
    {
        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            // Lock the product row FIRST, as a single locking read: the
            // authoritative delta is computed from this locked stock, and no
            // snapshot read of the row may precede the lock (that would trip
            // MariaDB's "record has changed since last read" under contention).
            // Holding the lock before the movement dedupe also keeps
            // concurrent pushes for one product strictly serialized.
            $lockedProduct = Product::where('business_id', $business->id)
                ->where('sync_id', (string) $item['product_sync_id'])
                ->lockForUpdate()
                ->first();

            if (! $lockedProduct instanceof Product) {
                throw new SyncConflictException('stock_movements', $syncId, 0);
            }

            $productId = (int) $lockedProduct->id;

            // Stable idempotency is the sync_id: one sale may emit several
            // movements sharing the same business reference (one per product),
            // so the reference alone is never a uniqueness key. A retry keeps
            // the same sync_id and resolves to the same record below.
            $record = StockMovement::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'product_id' => $productId,
                'movement_type' => (string) $item['movement_type'],
                'quantity_change' => $item['quantity_change'],
                'stock_before' => $item['stock_before'] ?? 0,
                'stock_after' => $item['stock_after'] ?? 0,
                'reference_id' => isset($item['reference_id']) ? (string) $item['reference_id'] : null,
                'category' => isset($item['category']) ? (string) $item['category'] : null,
                'note' => isset($item['note']) ? (string) $item['note'] : null,
                'sale_sync_id' => isset($item['sale_sync_id']) ? (string) $item['sale_sync_id'] : null,
                'occurred_at' => Carbon::parse((string) $item['occurred_at']),
            ];

            if ($record instanceof StockMovement) {
                // Same sync_id => the delta was already applied exactly once.
                if ($this->attributesMatch($record, $attributes)) {
                    continue;
                }

                // Only the historical evidence may be updated; the current
                // stock effect is never re-applied on a retry.
                $this->validateConcurrency('stock_movements', $record, $syncId, $baseVersion);

                $record->fill($attributes);
                $record->save();

                continue;
            }

            $this->validateConcurrency('stock_movements', null, $syncId, $baseVersion);

            // Server delta authority: the accepted mutation is quantity_change,
            // and the authoritative current stock is the locked server stock
            // plus that delta. The device stock_before/stock_after values are
            // kept as historical evidence only.
            $delta = (float) $attributes['quantity_change'];
            $serverStockBefore = (float) $lockedProduct->stock;
            $serverStockAfter = $serverStockBefore + $delta;

            if ($serverStockAfter < 0) {
                // Explicit recoverable state: never a silent server-wins,
                // client-wins, or fabricated stock_after.
                throw new SyncStateRequiredException('STOCK_RECONCILIATION_REQUIRED', 'stock_movements', $syncId, [
                    'product_sync_id' => (string) $item['product_sync_id'],
                    'quantity_change' => $delta,
                    'server_stock_before' => $serverStockBefore,
                    'server_stock_after' => $serverStockAfter,
                    'device_stock_before' => (float) ($item['stock_before'] ?? 0),
                    'device_stock_after' => (float) ($item['stock_after'] ?? 0),
                    'reason' => 'NEGATIVE_STOCK_NOT_ALLOWED',
                ]);
            }

            $movement = new StockMovement($attributes);
            $movement->business_id = $business->id;
            $movement->sync_id = $syncId;
            $movement->save();

            $lockedProduct->stock = (string) $serverStockAfter;
            $lockedProduct->save();
        }
    }

    /**
     * Apply non-destructive tombstone deletions for mutable master entities.
     * Rows are marked deleted/inactive, never hard-deleted, so historical
     * sales, items, cash and stock rows keep their references intact.
     * Immutable history entities are always rejected.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function processDeletions(Business $business, int $outletId, array $items): void
    {
        $tombstoneTargets = [
            'categories' => Category::class,
            'products' => Product::class,
            'customers' => Customer::class,
            'expenses' => Expense::class,
        ];

        foreach ($items as $item) {
            $entity = (string) ($item['entity'] ?? '');
            $syncId = (string) ($item['sync_id'] ?? '');
            $baseVersion = isset($item['base_sync_version']) ? (int) $item['base_sync_version'] : null;

            if (! isset($tombstoneTargets[$entity])) {
                throw new SyncConflictException('deletions', $syncId !== '' ? $syncId : 'unknown', 0);
            }

            $modelClass = $tombstoneTargets[$entity];

            /** @var ?Model $record */
            $record = $modelClass::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            // Missing record: idempotent success, nothing to tombstone.
            if (! $record) {
                continue;
            }

            // Categories, products and customers are business-wide; only
            // expenses are outlet-scoped and need the outlet isolation check.
            $expectedOutlet = $entity === 'expenses' ? $outletId : null;
            $targetStatus = $entity === 'expenses' ? 'void' : 'deleted';

            // Already tombstoned: the mutation is already fully reflected on
            // the server, so it auto-resolves instead of raising a conflict.
            if ((string) $record->getAttribute('status') === $targetStatus) {
                continue;
            }

            $this->validateConcurrency($entity, $record, $syncId, $baseVersion, $expectedOutlet);

            $record->setAttribute('status', $targetStatus);
            $record->save();
        }
    }

    /**
     * Validate optimistic concurrency version and outlet isolation.
     */
    protected function validateConcurrency(string $entity, ?Model $record, string $syncId, ?int $baseVersion, ?int $expectedOutletId = null): void
    {
        if ($record) {
            $outletId = $record->getAttribute('outlet_id');
            if ($expectedOutletId !== null && $outletId !== null && (int) $outletId !== $expectedOutletId) {
                throw new SyncConflictException($entity, $syncId, (int) $record->getAttribute('sync_version'));
            }

            $serverVersion = (int) $record->getAttribute('sync_version');
            if ($baseVersion === null || $baseVersion !== $serverVersion) {
                throw new SyncConflictException($entity, $syncId, $serverVersion);
            }
        } else {
            if ($baseVersion !== null) {
                throw new SyncConflictException($entity, $syncId, 0);
            }
        }
    }

    /**
     * Deterministic equivalent-mutation check used for safe auto-resolution.
     *
     * Returns true only when every field carried by the incoming mutation is
     * already identical on the server record: the local mutation is fully
     * reflected on the server, so it can be acknowledged without a version
     * conflict and without any further write. Genuinely different user edits
     * always return false and stay unresolved.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function attributesMatch(Model $record, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            $current = $record->getAttribute($key);

            if ($current instanceof \DateTimeInterface && $value instanceof \DateTimeInterface) {
                if ($current->getTimestamp() !== $value->getTimestamp()) {
                    return false;
                }

                continue;
            }

            if (is_numeric($current) && is_numeric($value)) {
                if (abs((float) $current - (float) $value) > 0.0001) {
                    return false;
                }

                continue;
            }

            if (is_array($current) || is_array($value)) {
                if ($current != $value) {
                    return false;
                }

                continue;
            }

            if ((string) $current !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rank a laundry order lifecycle status so a stale device can never move
     * an order backwards. Null for non-laundry sales (order_status null).
     */
    protected function lifecycleRank(mixed $status): ?int
    {
        $ranks = [
            'Masuk' => 0,
            'Diproses' => 1,
            'Siap Diambil' => 2,
            'Selesai' => 3,
        ];

        if (! is_string($status) || ! array_key_exists($status, $ranks)) {
            return null;
        }

        return $ranks[$status];
    }
}
