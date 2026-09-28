<?php

namespace App\Services\Sync;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizer;
use App\Services\Authorization\BusinessPermission;
use Illuminate\Support\Carbon;

final class SyncAuthorizationPolicy
{
    public const PUSH_MODE_FULL = 'full';

    public const PUSH_MODE_CASHIER_SAFE = 'cashier_safe';

    public const PUSH_MODE_NONE = 'none';

    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    /**
     * @return array{
     *     pull: bool,
     *     push: bool,
     *     push_mode: string,
     *     allowed_entities: array<string, list<string>>,
     *     denied_entities: list<string>,
     *     contract_version: string
     * }
     */
    public function capabilities(?User $user, ?Business $business): array
    {
        $pushMode = $this->pushMode($user, $business);

        return [
            'pull' => $this->authorizer->allows($user, $business, BusinessPermission::SYNC_PULL),
            'push' => $pushMode !== self::PUSH_MODE_NONE,
            'push_mode' => $pushMode,
            'allowed_entities' => $this->allowedEntities($pushMode),
            'denied_entities' => $pushMode === self::PUSH_MODE_CASHIER_SAFE
                ? ['categories', 'products', 'expenses', 'deletions']
                : [],
            'contract_version' => 'cashier_sync_v1',
        ];
    }

    public function pushMode(?User $user, ?Business $business): string
    {
        if ($this->authorizer->allows($user, $business, BusinessPermission::SYNC_PUSH)) {
            return self::PUSH_MODE_FULL;
        }

        if ($this->authorizer->allows($user, $business, BusinessPermission::SYNC_PUSH_CASHIER_SAFE)) {
            return self::PUSH_MODE_CASHIER_SAFE;
        }

        return self::PUSH_MODE_NONE;
    }

    /**
     * @param  array{user: User, business: Business, outlet_id: int}  $context
     * @param  array<string, mixed>  $payload
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    public function pushViolations(array $context, array $payload): array
    {
        $mode = $this->pushMode($context['user'], $context['business']);

        if ($mode === self::PUSH_MODE_FULL) {
            return [];
        }

        if ($mode !== self::PUSH_MODE_CASHIER_SAFE) {
            return [[
                'entity' => 'sync',
                'operation' => 'push',
                'reason' => 'role_not_supported',
            ]];
        }

        /** @var array<string, list<array<string, mixed>>> $changes */
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        $business = $context['business'];
        $outletId = (int) $context['outlet_id'];
        $violations = [];

        $knownEntities = ['categories', 'products', 'customers', 'shifts', 'sales', 'sale_items', 'expenses', 'cash_ledger', 'stock_movements', 'deletions'];
        foreach ($changes as $entity => $items) {
            if (! in_array($entity, $knownEntities, true)) {
                $violations[] = $this->violation((string) $entity, 'upsert', 'unknown_entity');
            }
        }

        foreach (['categories', 'products', 'expenses'] as $entity) {
            if (! empty($changes[$entity])) {
                $violations[] = $this->violation($entity, 'upsert', 'cashier_entity_not_allowed');
            }
        }

        if (! empty($changes['deletions'])) {
            foreach ($changes['deletions'] as $item) {
                $violations[] = $this->violation((string) ($item['entity'] ?? 'deletions'), 'delete', 'cashier_delete_not_allowed');
            }
        }

        $incomingCustomers = $this->syncIdSet($changes['customers'] ?? []);
        $incomingShifts = $this->syncIdSet($changes['shifts'] ?? []);
        $incomingSales = $this->salesBySyncId($changes['sales'] ?? []);

        $violations = array_merge(
            $violations,
            $this->authorizeCustomers($business, $changes['customers'] ?? []),
            $this->authorizeShifts($business, $outletId, $changes['shifts'] ?? []),
            $this->authorizeSales($business, $outletId, $changes['sales'] ?? [], $incomingCustomers, $incomingShifts),
            $this->authorizeSaleItems($business, $outletId, $changes['sale_items'] ?? [], $incomingSales),
            $this->authorizeCashLedger($business, $outletId, $changes['cash_ledger'] ?? [], $incomingSales),
            $this->authorizeStockMovements($business, $outletId, $changes['stock_movements'] ?? [], $incomingSales, $changes['sale_items'] ?? []),
        );

        return $violations;
    }

    /**
     * @return array<string, list<string>>
     */
    private function allowedEntities(string $pushMode): array
    {
        if ($pushMode === self::PUSH_MODE_FULL) {
            return [
                'categories' => ['upsert', 'delete'],
                'products' => ['upsert', 'delete'],
                'customers' => ['upsert', 'delete'],
                'shifts' => ['upsert'],
                'sales' => ['upsert'],
                'sale_items' => ['upsert'],
                'expenses' => ['upsert', 'delete'],
                'cash_ledger' => ['upsert'],
                'stock_movements' => ['upsert'],
            ];
        }

        if ($pushMode === self::PUSH_MODE_CASHIER_SAFE) {
            return [
                'customers' => ['upsert'],
                'shifts' => ['upsert'],
                'sales' => ['upsert'],
                'sale_items' => ['upsert'],
                'cash_ledger' => ['sale_payment'],
                'stock_movements' => ['sale'],
            ];
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeCustomers(Business $business, array $items): array
    {
        $violations = [];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'active');
            if (in_array($status, ['deleted', 'void'], true)) {
                $violations[] = $this->violation('customers', 'upsert', 'cashier_customer_delete_not_allowed');
            }

            $record = Customer::where('business_id', $business->id)
                ->where('sync_id', (string) $item['sync_id'])
                ->first();

            if ($record instanceof Customer && (int) $record->business_id !== (int) $business->id) {
                $violations[] = $this->violation('customers', 'upsert', 'foreign_business_relation');
            }
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeShifts(Business $business, int $outletId, array $items): array
    {
        $violations = [];

        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $status = (string) ($item['status'] ?? 'open');

            if (! in_array($status, ['open', 'closed'], true)) {
                $violations[] = $this->violation('shifts', 'upsert', 'unsupported_shift_status');
            }

            $record = Shift::where('business_id', $business->id)
                ->where('sync_id', $syncId)
                ->first();

            if (! $record instanceof Shift) {
                continue;
            }

            if ((int) $record->outlet_id !== $outletId) {
                $violations[] = $this->violation('shifts', 'upsert', 'foreign_outlet_relation');
            }

            if ((string) $record->status === 'closed' && $status === 'open') {
                $violations[] = $this->violation('shifts', 'upsert', 'shift_reopen_not_allowed');
            }

            // Historical financial snapshots of an existing shift are never
            // rewritten by cashier sync. A closed shift is fully immutable, so
            // its opening/closing cash can never be re-derived after the fact.
            if ((string) $record->status === 'closed') {
                if ($this->shiftMutatesClosedRecord($record, $item)) {
                    $violations[] = $this->violation('shifts', 'upsert', 'shift_closed_immutable');
                }
            } else {
                if (array_key_exists('opening_cash', $item)
                    && ! $this->numericSame($record->opening_cash, $item['opening_cash'] ?? 0)) {
                    $violations[] = $this->violation('shifts', 'upsert', 'shift_opening_cash_immutable');
                }

                $incomingClosing = array_key_exists('closing_cash', $item) && $item['closing_cash'] !== null
                    ? (int) $item['closing_cash']
                    : null;

                if ($record->closing_cash !== null && $incomingClosing !== (int) $record->closing_cash) {
                    $violations[] = $this->violation('shifts', 'upsert', 'shift_closing_cash_immutable');
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, true>  $incomingCustomers
     * @param  array<string, true>  $incomingShifts
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeSales(Business $business, int $outletId, array $items, array $incomingCustomers, array $incomingShifts): array
    {
        $violations = [];

        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $record = Sale::where('business_id', $business->id)->where('sync_id', $syncId)->first();

            if ($record instanceof Sale && (int) $record->outlet_id !== $outletId) {
                $violations[] = $this->violation('sales', 'upsert', 'foreign_outlet_relation');
            }

            // A formed transaction's financial snapshot is immutable. Payment
            // status transitions (unpaid -> paid) and the laundry lifecycle stay
            // mutable, but totals, discount, tax and HPP can never be re-derived
            // by a later cashier push.
            if ($record instanceof Sale && $this->saleFinancialSnapshotChanged($record, $item)) {
                $violations[] = $this->violation('sales', 'upsert', 'sale_financial_snapshot_immutable');
            }

            if (! empty($item['customer_sync_id'])) {
                $customerSyncId = (string) $item['customer_sync_id'];
                if (! isset($incomingCustomers[$customerSyncId]) && ! Customer::where('business_id', $business->id)->where('sync_id', $customerSyncId)->exists()) {
                    $violations[] = $this->violation('sales', 'upsert', 'invalid_customer_relation');
                }
            }

            if (! empty($item['shift_sync_id'])) {
                $shiftSyncId = (string) $item['shift_sync_id'];
                if (! isset($incomingShifts[$shiftSyncId]) && ! Shift::where('business_id', $business->id)->where('outlet_id', $outletId)->where('sync_id', $shiftSyncId)->exists()) {
                    $violations[] = $this->violation('sales', 'upsert', 'invalid_shift_relation');
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $incomingSales
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeSaleItems(Business $business, int $outletId, array $items, array $incomingSales): array
    {
        $violations = [];

        foreach ($items as $item) {
            $saleSyncId = (string) $item['sale_sync_id'];
            $productSyncId = (string) $item['product_sync_id'];

            $incomingProductId = Product::where('business_id', $business->id)
                ->where('sync_id', $productSyncId)
                ->value('id');
            $incomingSaleId = Sale::where('business_id', $business->id)
                ->where('sync_id', $saleSyncId)
                ->value('id');

            $record = SaleItem::where('business_id', $business->id)->where('sync_id', (string) $item['sync_id'])->first();
            if ($record instanceof SaleItem) {
                $saleOutletId = Sale::where('business_id', $business->id)->where('id', $record->sale_id)->value('outlet_id');
                if ((int) $saleOutletId !== $outletId) {
                    $violations[] = $this->violation('sale_items', 'upsert', 'foreign_outlet_relation');
                }

                // A formed sale item can never be repointed at another sale or
                // product, not even inside the same outlet.
                if (($incomingSaleId !== null && (int) $incomingSaleId !== (int) $record->sale_id)
                    || ($incomingProductId !== null && (int) $incomingProductId !== (int) $record->product_id)) {
                    $violations[] = $this->violation('sale_items', 'upsert', 'sale_item_relation_immutable');
                }

                // Price, quantity and captured HPP are historical evidence:
                // a later push may never rewrite what a sale actually recorded.
                if ($this->saleItemSnapshotChanged($record, $item)) {
                    $violations[] = $this->violation('sale_items', 'upsert', 'sale_item_snapshot_immutable');
                }
            }

            if (! $this->saleIsAllowed($business, $outletId, $saleSyncId, $incomingSales)) {
                $violations[] = $this->violation('sale_items', 'upsert', 'invalid_sale_relation');
            }

            if (! $incomingProductId) {
                $violations[] = $this->violation('sale_items', 'upsert', 'invalid_product_relation');
            }
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $incomingSales
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeCashLedger(Business $business, int $outletId, array $items, array $incomingSales): array
    {
        $violations = [];

        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $saleSyncId = isset($item['sale_sync_id']) ? (string) $item['sale_sync_id'] : '';

            if ($saleSyncId === '') {
                $violations[] = $this->violation('cash_ledger', 'upsert', 'manual_cash_not_allowed');

                continue;
            }

            if ((string) ($item['type'] ?? '') !== 'in') {
                $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_payment_type_not_allowed');
            }

            if (! $this->saleIsAllowed($business, $outletId, $saleSyncId, $incomingSales)) {
                $violations[] = $this->violation('cash_ledger', 'upsert', 'invalid_sale_relation');
            } else {
                // A cash settlement may only be attached to a sale that is
                // actually settled by physical cash. A QRIS/transfer/card sale
                // forged as a cash payment (or an unpaid order) is rejected.
                $payment = $this->salePaymentState($business, $saleSyncId, $incomingSales);

                if ($payment === null || ! $this->isCashPaymentMethod($payment['payment_method'])) {
                    $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_payment_requires_cash_sale');
                } elseif ($payment['payment_status'] !== 'paid') {
                    $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_payment_requires_paid_sale');
                }
            }

            $expectedTotal = $this->saleTotalAmount($business, $saleSyncId, $incomingSales);
            if ($expectedTotal !== null && ! $this->numericSame($item['amount'], $expectedTotal)) {
                $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_amount_mismatch');
            }

            $record = CashLedger::where('business_id', $business->id)->where('sync_id', $syncId)->first();
            if ($record instanceof CashLedger) {
                if ((int) $record->outlet_id !== $outletId) {
                    $violations[] = $this->violation('cash_ledger', 'upsert', 'foreign_outlet_relation');
                }

                // An existing cash row accepts only a valid identical retry:
                // its origin (manual owner entry vs sale payment), its sale
                // linkage and its recorded classification/metadata are immutable.
                if ($record->sale_sync_id === null) {
                    $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_ledger_origin_immutable');
                } elseif ((string) $record->sale_sync_id !== $saleSyncId) {
                    $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_ledger_sale_link_immutable');
                }

                if (! $this->cashLedgerMetadataMatches($record, $item, $business, $outletId)) {
                    $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_ledger_immutable');
                }
            }
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $incomingSales
     * @param  list<array<string, mixed>>  $incomingSaleItems
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeStockMovements(Business $business, int $outletId, array $items, array $incomingSales, array $incomingSaleItems): array
    {
        $violations = [];
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($items as $item) {
            $syncId = (string) $item['sync_id'];
            $saleSyncId = isset($item['sale_sync_id']) ? (string) $item['sale_sync_id'] : '';
            $productSyncId = isset($item['product_sync_id']) ? (string) $item['product_sync_id'] : '';

            $valid = true;

            if ((string) ($item['movement_type'] ?? '') !== 'sale') {
                $violations[] = $this->violation('stock_movements', 'upsert', 'stock_movement_type_not_allowed');
                $valid = false;
            }

            if ($saleSyncId === '') {
                $violations[] = $this->violation('stock_movements', 'upsert', 'missing_sale_relation');
                $valid = false;
            } elseif (! $this->saleIsAllowed($business, $outletId, $saleSyncId, $incomingSales)) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'invalid_sale_relation');
                $valid = false;
            }

            if ((float) ($item['quantity_change'] ?? 0) >= 0.0) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'sale_stock_movement_must_decrease_stock');
                $valid = false;
            }

            $productId = Product::where('business_id', $business->id)
                ->where('sync_id', $productSyncId)
                ->value('id');

            if (! $productId) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'invalid_product_relation');
                $valid = false;
            }

            // The identity and kind of an existing movement are immutable: a
            // retry may never repoint the same sync_id at another product, sale
            // or movement type.
            $record = StockMovement::where('business_id', $business->id)->where('sync_id', $syncId)->first();
            if ($record instanceof StockMovement) {
                if (($productId && (int) $record->product_id !== (int) $productId)
                    || (string) ($record->sale_sync_id ?? '') !== $saleSyncId
                    || (string) $record->movement_type !== (string) ($item['movement_type'] ?? '')) {
                    $violations[] = $this->violation('stock_movements', 'upsert', 'stock_movement_identity_immutable');
                    $valid = false;
                }

                // The recorded stock effect is historical evidence and can
                // never be rewritten by a cashier push, even with a valid base
                // version: an accepted deduction is applied exactly once.
                if (! $this->numericSame($record->quantity_change, $item['quantity_change'] ?? 0)) {
                    $violations[] = $this->violation('stock_movements', 'upsert', 'stock_movement_value_immutable');
                    $valid = false;
                }
            }

            if ($valid && $productId) {
                $grouped[$saleSyncId.'|'.$productSyncId][] = $item;
            }
        }

        // Per (sale, product) perimeter: the stock that cashier sync may ever
        // deduct can never exceed what the matching sale items actually sold,
        // counting both movements already accepted by the server and the ones
        // carried by this request. This blocks a duplicate movement that reuses
        // a different sync_id to double the reduction.
        foreach ($grouped as $key => $group) {
            [$saleSyncId, $productSyncId] = explode('|', $key, 2);

            $productId = (int) Product::where('business_id', $business->id)
                ->where('sync_id', $productSyncId)
                ->value('id');

            $sold = $this->soldQuantity($business, $saleSyncId, $productId, $productSyncId, $incomingSaleItems);

            if ($sold === null || $sold <= 0.0) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'stock_movement_without_sale_item');

                continue;
            }

            $accepted = $this->acceptedMovementMagnitude($business, $saleSyncId, $productId, $group);

            if ($accepted > $sold + 0.0001) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'stock_movement_exceeds_sold_quantity');
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $incomingSales
     */
    private function saleIsAllowed(Business $business, int $outletId, string $saleSyncId, array $incomingSales): bool
    {
        if (isset($incomingSales[$saleSyncId])) {
            return true;
        }

        return Sale::where('business_id', $business->id)
            ->where('outlet_id', $outletId)
            ->where('sync_id', $saleSyncId)
            ->exists();
    }

    /**
     * @param  array<string, array<string, mixed>>  $incomingSales
     */
    private function saleTotalAmount(Business $business, string $saleSyncId, array $incomingSales): ?int
    {
        if (isset($incomingSales[$saleSyncId])) {
            return (int) $incomingSales[$saleSyncId]['total_amount'];
        }

        $value = Sale::where('business_id', $business->id)
            ->where('sync_id', $saleSyncId)
            ->value('total_amount');

        return $value === null ? null : (int) $value;
    }

    /**
     * Effective payment state of a sale, preferring a sale carried by the same
     * envelope (so an unpaid -> paid transition settles atomically) and falling
     * back to the persisted server row.
     *
     * @param  array<string, array<string, mixed>>  $incomingSales
     * @return array{payment_method: string|null, payment_status: string}|null
     */
    private function salePaymentState(Business $business, string $saleSyncId, array $incomingSales): ?array
    {
        if (isset($incomingSales[$saleSyncId])) {
            $item = $incomingSales[$saleSyncId];

            return [
                'payment_method' => isset($item['payment_method'])
                    ? (string) $item['payment_method']
                    : null,
                'payment_status' => (string) ($item['payment_status'] ?? 'paid'),
            ];
        }

        $sale = Sale::where('business_id', $business->id)
            ->where('sync_id', $saleSyncId)
            ->first(['payment_method', 'payment_status']);

        if (! $sale instanceof Sale) {
            return null;
        }

        return [
            'payment_method' => $sale->payment_method !== null ? (string) $sale->payment_method : null,
            'payment_status' => (string) $sale->payment_status,
        ];
    }

    /**
     * The project records physical cash as `cash` (or its legacy alias
     * `tunai`). Any other method is a non-cash settlement.
     */
    private function isCashPaymentMethod(?string $method): bool
    {
        return $method !== null && in_array(strtolower($method), ['cash', 'tunai'], true);
    }

    /**
     * Quantity sold for one (sale, product) pair, unioned across the sale items
     * already on the server and the ones carried by the current envelope. Both
     * are keyed by their stable sync_id so a retried sale item is never counted
     * twice. Null means no matching sale item exists at all.
     *
     * @param  list<array<string, mixed>>  $incomingSaleItems
     */
    private function soldQuantity(Business $business, string $saleSyncId, int $productId, string $productSyncId, array $incomingSaleItems): ?float
    {
        /** @var array<string, float> $quantities */
        $quantities = [];

        $saleId = Sale::where('business_id', $business->id)
            ->where('sync_id', $saleSyncId)
            ->value('id');

        if ($saleId !== null) {
            $rows = SaleItem::where('business_id', $business->id)
                ->where('sale_id', $saleId)
                ->where('product_id', $productId)
                ->get(['sync_id', 'quantity']);

            foreach ($rows as $row) {
                $quantities[(string) $row->sync_id] = (float) $row->quantity;
            }
        }

        foreach ($incomingSaleItems as $saleItem) {
            if ((string) ($saleItem['sale_sync_id'] ?? '') !== $saleSyncId) {
                continue;
            }

            if ((string) ($saleItem['product_sync_id'] ?? '') !== $productSyncId) {
                continue;
            }

            $quantities[(string) ($saleItem['sync_id'] ?? '')] = (float) ($saleItem['quantity'] ?? 0);
        }

        if ($quantities === []) {
            return null;
        }

        return array_sum($quantities);
    }

    /**
     * Total stock magnitude already committed for one (sale, product) pair,
     * counting accepted server movements and the new movements in this request.
     * Movements reusing an existing sync_id are retries and are counted once.
     *
     * @param  list<array<string, mixed>>  $incomingMovements
     */
    private function acceptedMovementMagnitude(Business $business, string $saleSyncId, int $productId, array $incomingMovements): float
    {
        /** @var array<string, float> $magnitudes */
        $magnitudes = [];

        $existing = StockMovement::where('business_id', $business->id)
            ->where('sale_sync_id', $saleSyncId)
            ->where('product_id', $productId)
            ->get(['sync_id', 'quantity_change']);

        foreach ($existing as $movement) {
            $magnitudes[(string) $movement->sync_id] = abs((float) $movement->quantity_change);
        }

        foreach ($incomingMovements as $movement) {
            $syncId = (string) $movement['sync_id'];

            if (isset($magnitudes[$syncId])) {
                continue;
            }

            $magnitudes[$syncId] = abs((float) ($movement['quantity_change'] ?? 0));
        }

        return array_sum($magnitudes);
    }

    /**
     * Whether a cashier push rewrites an already-formed sale's financial
     * snapshot (totals, discount, tax, HPP). Payment status and laundry
     * lifecycle are purposefully not part of this comparison.
     *
     * @param  array<string, mixed>  $item
     */
    private function saleFinancialSnapshotChanged(Sale $record, array $item): bool
    {
        foreach (['subtotal', 'discount_amount', 'tax_amount', 'total_amount'] as $field) {
            if (array_key_exists($field, $item) && ! $this->numericSame($record->getAttribute($field), $item[$field])) {
                return true;
            }
        }

        if (array_key_exists('gross_profit', $item) && $item['gross_profit'] !== null
            && ! $this->numericSame($record->getAttribute('gross_profit'), $item['gross_profit'])) {
            return true;
        }

        return false;
    }

    /**
     * Whether a cashier push rewrites a formed sale item's historical price,
     * quantity, line total or captured HPP.
     *
     * @param  array<string, mixed>  $item
     */
    private function saleItemSnapshotChanged(SaleItem $record, array $item): bool
    {
        foreach (['unit_price', 'quantity', 'line_total'] as $field) {
            if (array_key_exists($field, $item) && ! $this->numericSame($record->getAttribute($field), $item[$field])) {
                return true;
            }
        }

        if (array_key_exists('cost_snapshot', $item) && $item['cost_snapshot'] !== null
            && ! $this->numericSame($record->getAttribute('cost_snapshot'), $item['cost_snapshot'])) {
            return true;
        }

        return false;
    }

    /**
     * Whether an existing sale-linked cash entry still matches the incoming
     * payload across every attribute the sync writer persists. Only a fully
     * identical retry is accepted, so a cashier can never re-classify an
     * existing cash row (category, shift, note, reference or timestamp).
     *
     * @param  array<string, mixed>  $item
     */
    private function cashLedgerMetadataMatches(CashLedger $record, array $item, Business $business, int $outletId): bool
    {
        if ((string) $record->type !== (string) ($item['type'] ?? '')) {
            return false;
        }

        if (! $this->numericSame($record->amount, $item['amount'] ?? 0)) {
            return false;
        }

        $category = isset($item['category']) ? (string) $item['category'] : null;
        if ($record->category !== $category) {
            return false;
        }

        $note = isset($item['note']) ? (string) $item['note'] : null;
        if ($record->note !== $note) {
            return false;
        }

        $reference = isset($item['reference_id']) ? (string) $item['reference_id'] : null;
        if ($record->reference_id !== $reference) {
            return false;
        }

        $occurredAt = Carbon::parse((string) $item['occurred_at']);
        if ($record->occurred_at->getTimestamp() !== $occurredAt->getTimestamp()) {
            return false;
        }

        $shiftId = null;
        if (! empty($item['shift_sync_id'])) {
            $shiftId = Shift::where('business_id', $business->id)
                ->where('outlet_id', $outletId)
                ->where('sync_id', (string) $item['shift_sync_id'])
                ->value('id');
        }

        $storedShiftId = $record->shift_id !== null ? (int) $record->shift_id : null;

        return $storedShiftId === ($shiftId !== null ? (int) $shiftId : null);
    }

    /**
     * Whether an incoming shift mutation would change an already closed shift.
     * Only a full identical retry is accepted.
     *
     * @param  array<string, mixed>  $item
     */
    private function shiftMutatesClosedRecord(Shift $record, array $item): bool
    {
        if ((string) ($item['status'] ?? 'open') !== 'closed') {
            return true;
        }

        if (array_key_exists('opening_cash', $item)
            && ! $this->numericSame($record->opening_cash, $item['opening_cash'] ?? 0)) {
            return true;
        }

        $incomingClosing = array_key_exists('closing_cash', $item) && $item['closing_cash'] !== null
            ? (int) $item['closing_cash']
            : null;

        if ($incomingClosing !== ($record->closing_cash !== null ? (int) $record->closing_cash : null)) {
            return true;
        }

        if (array_key_exists('shift_number', $item) && (string) $item['shift_number'] !== (string) $record->shift_number) {
            return true;
        }

        if (array_key_exists('opened_at', $item)
            && Carbon::parse((string) $item['opened_at'])->getTimestamp() !== $record->opened_at->getTimestamp()) {
            return true;
        }

        if (array_key_exists('closed_at', $item)) {
            $incoming = $item['closed_at'] !== null ? Carbon::parse((string) $item['closed_at'])->getTimestamp() : null;

            if ($incoming !== $record->closed_at?->getTimestamp()) {
                return true;
            }
        }

        if (array_key_exists('notes', $item)) {
            $incoming = $item['notes'] !== null ? (string) $item['notes'] : null;

            if ($incoming !== $record->notes) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tolerance-aware numeric equality for money and decimal quantities.
     */
    private function numericSame(mixed $a, mixed $b): bool
    {
        return abs((float) $a - (float) $b) <= 0.0001;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, true>
     */
    private function syncIdSet(array $items): array
    {
        $set = [];

        foreach ($items as $item) {
            if (isset($item['sync_id'])) {
                $set[(string) $item['sync_id']] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function salesBySyncId(array $items): array
    {
        $sales = [];

        foreach ($items as $item) {
            if (isset($item['sync_id'])) {
                $sales[(string) $item['sync_id']] = $item;
            }
        }

        return $sales;
    }

    /**
     * @return array{entity: string, operation: string, reason: string}
     */
    private function violation(string $entity, string $operation, string $reason): array
    {
        return [
            'entity' => $entity,
            'operation' => $operation,
            'reason' => $reason,
        ];
    }
}
