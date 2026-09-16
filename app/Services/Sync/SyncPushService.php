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
        } catch (QueryException) {
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

            $this->validateConcurrency('categories', $record, $syncId, $baseVersion);

            if ($record) {
                $record->name = (string) $item['name'];
                $record->status = (string) ($item['status'] ?? 'active');
                $record->save();
            } else {
                $category = new Category([
                    'name' => (string) $item['name'],
                    'status' => (string) ($item['status'] ?? 'active'),
                ]);
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

            $this->validateConcurrency('products', $record, $syncId, $baseVersion);

            $categoryId = null;
            if (! empty($item['category_sync_id'])) {
                $categoryId = Category::where('business_id', $business->id)
                    ->where('sync_id', (string) $item['category_sync_id'])
                    ->value('id');

                if (! $categoryId) {
                    throw new SyncConflictException('products', $syncId, 0);
                }
            }

            $semantic = $this->productSemanticFields($item);

            if ($record) {
                $record->category_id = $categoryId;
                $record->name = (string) $item['name'];
                $record->sku = (string) $item['sku'];
                $record->barcode = isset($item['barcode']) ? (string) $item['barcode'] : null;
                $record->price = (int) $item['price'];
                $record->fill($semantic);
                $record->status = (string) ($item['status'] ?? 'active');
                $record->save();
            } else {
                $product = new Product(array_merge([
                    'category_id' => $categoryId,
                    'name' => (string) $item['name'],
                    'sku' => (string) $item['sku'],
                    'barcode' => isset($item['barcode']) ? (string) $item['barcode'] : null,
                    'price' => (int) $item['price'],
                    'status' => (string) ($item['status'] ?? 'active'),
                ], $semantic));
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

            $this->validateConcurrency('customers', $record, $syncId, $baseVersion);

            if ($record) {
                $record->name = (string) $item['name'];
                $record->phone = isset($item['phone']) ? (string) $item['phone'] : null;
                $record->email = isset($item['email']) ? (string) $item['email'] : null;
                $record->address = isset($item['address']) ? (string) $item['address'] : null;
                $record->notes = isset($item['notes']) ? (string) $item['notes'] : null;
                $record->status = (string) ($item['status'] ?? 'active');
                $record->save();
            } else {
                $customer = new Customer([
                    'name' => (string) $item['name'],
                    'phone' => isset($item['phone']) ? (string) $item['phone'] : null,
                    'email' => isset($item['email']) ? (string) $item['email'] : null,
                    'address' => isset($item['address']) ? (string) $item['address'] : null,
                    'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
                    'status' => (string) ($item['status'] ?? 'active'),
                ]);
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

            $this->validateConcurrency('shifts', $record, $syncId, $baseVersion, $outletId);

            if ($record) {
                $record->shift_number = (string) $item['shift_number'];
                $record->status = (string) ($item['status'] ?? 'open');
                $record->opening_cash = (int) ($item['opening_cash'] ?? 0);
                $record->closing_cash = isset($item['closing_cash']) ? (int) $item['closing_cash'] : null;
                $record->opened_at = Carbon::parse((string) $item['opened_at']);
                $record->closed_at = isset($item['closed_at']) ? Carbon::parse((string) $item['closed_at']) : null;
                $record->notes = isset($item['notes']) ? (string) $item['notes'] : null;
                $record->save();
            } else {
                $shift = new Shift([
                    'shift_number' => (string) $item['shift_number'],
                    'status' => (string) ($item['status'] ?? 'open'),
                    'opening_cash' => (int) ($item['opening_cash'] ?? 0),
                    'closing_cash' => isset($item['closing_cash']) ? (int) $item['closing_cash'] : null,
                    'opened_at' => (string) $item['opened_at'],
                    'closed_at' => isset($item['closed_at']) ? (string) $item['closed_at'] : null,
                    'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
                ]);
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

            $this->validateConcurrency('sales', $record, $syncId, $baseVersion, $outletId);

            $customerId = null;
            if (! empty($item['customer_sync_id'])) {
                $customerId = Customer::where('business_id', $business->id)
                    ->where('sync_id', (string) $item['customer_sync_id'])
                    ->value('id');

                if (! $customerId) {
                    throw new SyncConflictException('sales', $syncId, 0);
                }
            }

            $shiftId = null;
            if (! empty($item['shift_sync_id'])) {
                $shiftId = Shift::where('business_id', $business->id)
                    ->where('outlet_id', $outletId)
                    ->where('sync_id', (string) $item['shift_sync_id'])
                    ->value('id');

                if (! $shiftId) {
                    throw new SyncConflictException('sales', $syncId, 0);
                }
            }

            $payment = $this->salePaymentFields($item);
            $snapshots = $this->saleSnapshotFields($item, $customerId);

            if ($record) {
                $record->customer_id = $customerId;
                $record->shift_id = $shiftId;
                $record->transaction_number = (string) $item['transaction_number'];
                $record->status = (string) ($item['status'] ?? 'completed');
                $record->subtotal = (int) $item['subtotal'];
                $record->discount_amount = (int) ($item['discount_amount'] ?? 0);
                $record->tax_amount = (int) ($item['tax_amount'] ?? 0);
                $record->total_amount = (int) $item['total_amount'];
                $record->fill($payment);
                $record->fill($snapshots);
                $record->sold_at = Carbon::parse((string) $item['sold_at']);
                $record->save();
            } else {
                $sale = new Sale(array_merge([
                    'customer_id' => $customerId,
                    'shift_id' => $shiftId,
                    'transaction_number' => (string) $item['transaction_number'],
                    'status' => (string) ($item['status'] ?? 'completed'),
                    'subtotal' => (int) $item['subtotal'],
                    'discount_amount' => (int) ($item['discount_amount'] ?? 0),
                    'tax_amount' => (int) ($item['tax_amount'] ?? 0),
                    'total_amount' => (int) $item['total_amount'],
                    'sold_at' => (string) $item['sold_at'],
                ], $payment, $snapshots));
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
                if ($saleOutletId !== $outletId) {
                    throw new SyncConflictException('sale_items', $syncId, (int) $record->sync_version);
                }
            }

            $this->validateConcurrency('sale_items', $record, $syncId, $baseVersion);

            $saleId = Sale::where('business_id', $business->id)
                ->where('outlet_id', $outletId)
                ->where('sync_id', (string) $item['sale_sync_id'])
                ->value('id');

            if (! $saleId) {
                throw new SyncConflictException('sale_items', $syncId, 0);
            }

            $productId = Product::where('business_id', $business->id)
                ->where('sync_id', (string) $item['product_sync_id'])
                ->value('id');

            if (! $productId) {
                throw new SyncConflictException('sale_items', $syncId, 0);
            }

            $snapshots = $this->saleItemSnapshotFields($item);

            if ($record) {
                $record->sale_id = $saleId;
                $record->product_id = $productId;
                $record->product_name = (string) $item['product_name'];
                $record->product_sku = (string) $item['product_sku'];
                $record->unit_price = (int) $item['unit_price'];
                $record->quantity = $item['quantity'];
                $record->line_total = (int) $item['line_total'];
                $record->fill($snapshots);
                $record->save();
            } else {
                $saleItem = new SaleItem(array_merge([
                    'sale_id' => $saleId,
                    'product_id' => $productId,
                    'product_name' => (string) $item['product_name'],
                    'product_sku' => (string) $item['product_sku'],
                    'unit_price' => (int) $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'line_total' => (int) $item['line_total'],
                ], $snapshots));
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

            $this->validateConcurrency('expenses', $record, $syncId, $baseVersion, $outletId);

            $shiftId = null;
            if (! empty($item['shift_sync_id'])) {
                $shiftId = Shift::where('business_id', $business->id)
                    ->where('outlet_id', $outletId)
                    ->where('sync_id', (string) $item['shift_sync_id'])
                    ->value('id');

                if (! $shiftId) {
                    throw new SyncConflictException('expenses', $syncId, 0);
                }
            }

            $category = isset($item['category']) && $item['category'] !== null && $item['category'] !== ''
                ? (string) $item['category']
                : null;

            if ($record) {
                $record->shift_id = $shiftId;
                $record->description = (string) $item['description'];
                $record->category = $category ?? $record->category;
                $record->amount = (int) $item['amount'];
                $record->status = (string) ($item['status'] ?? 'recorded');
                $record->occurred_at = Carbon::parse((string) $item['occurred_at']);
                $record->notes = isset($item['notes']) ? (string) $item['notes'] : null;
                $record->save();
            } else {
                $expense = new Expense([
                    'shift_id' => $shiftId,
                    'description' => (string) $item['description'],
                    'category' => $category,
                    'amount' => (int) $item['amount'],
                    'status' => (string) ($item['status'] ?? 'recorded'),
                    'occurred_at' => (string) $item['occurred_at'],
                    'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
                ]);
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
            'phone' => isset($snapshot['phone']) && $snapshot['phone'] !== null ? (string) $snapshot['phone'] : '',
            'email' => isset($snapshot['email']) && $snapshot['email'] !== null ? (string) $snapshot['email'] : '',
        ];
    }

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
            'phone' => isset($snapshot['phone']) && $snapshot['phone'] !== null ? (string) $snapshot['phone'] : '',
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

            // Stable idempotency is the sync_id: a retry of the same request
            // replays the same sync_id through request_id dedupe or the update
            // path below, and is applied exactly once.
            $record = CashLedger::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $this->validateConcurrency('cash_ledger', $record, $syncId, $baseVersion, $outletId);

            $shiftId = null;
            if (! empty($item['shift_sync_id'])) {
                $shiftId = Shift::where('business_id', $business->id)
                    ->where('outlet_id', $outletId)
                    ->where('sync_id', (string) $item['shift_sync_id'])
                    ->value('id');

                if (! $shiftId) {
                    throw new SyncConflictException('cash_ledger', $syncId, 0);
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

            if ($record) {
                $record->fill($attributes);
                $record->save();
            } else {
                $entry = new CashLedger($attributes);
                $entry->business_id = $business->id;
                $entry->outlet_id = $outletId;
                $entry->sync_id = $syncId;
                $entry->save();
            }
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

            // Stable idempotency is the sync_id: one sale may emit several
            // movements sharing the same business reference (one per product),
            // so the reference alone is never a uniqueness key. A retry keeps
            // the same sync_id and resolves to the same record below.
            $record = StockMovement::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->lockForUpdate()
                ->first();

            $this->validateConcurrency('stock_movements', $record, $syncId, $baseVersion);

            $productId = Product::where('business_id', $business->id)
                ->where('sync_id', (string) $item['product_sync_id'])
                ->value('id');

            if (! $productId) {
                throw new SyncConflictException('stock_movements', $syncId, 0);
            }

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

            if ($record) {
                $record->fill($attributes);
                $record->save();
            } else {
                $movement = new StockMovement($attributes);
                $movement->business_id = $business->id;
                $movement->sync_id = $syncId;
                $movement->save();

                // Keep the server current stock consistent with the accepted
                // movement. The row is locked above, stock converges to the
                // accepted stock_after, and retries resolve to the same record
                // above so the effect is applied exactly once.
                $lockedProduct = Product::where('business_id', $business->id)
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedProduct) {
                    $lockedProduct->stock = $attributes['stock_after'];
                    $lockedProduct->save();
                }
            }
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

            $this->validateConcurrency($entity, $record, $syncId, $baseVersion, $expectedOutlet);

            $record->status = $entity === 'expenses' ? 'void' : 'deleted';
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
}
