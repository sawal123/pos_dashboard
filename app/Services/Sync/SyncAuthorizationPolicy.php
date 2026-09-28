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
            $this->authorizeStockMovements($business, $outletId, $changes['stock_movements'] ?? [], $incomingSales),
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
            $record = SaleItem::where('business_id', $business->id)->where('sync_id', (string) $item['sync_id'])->first();
            if ($record instanceof SaleItem) {
                $saleOutletId = Sale::where('business_id', $business->id)->where('id', $record->sale_id)->value('outlet_id');
                if ((int) $saleOutletId !== $outletId) {
                    $violations[] = $this->violation('sale_items', 'upsert', 'foreign_outlet_relation');
                }
            }

            $saleSyncId = (string) $item['sale_sync_id'];
            if (! $this->saleIsAllowed($business, $outletId, $saleSyncId, $incomingSales)) {
                $violations[] = $this->violation('sale_items', 'upsert', 'invalid_sale_relation');
            }

            if (! Product::where('business_id', $business->id)->where('sync_id', (string) $item['product_sync_id'])->exists()) {
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
            }

            $expectedTotal = $this->saleTotalAmount($business, $saleSyncId, $incomingSales);
            if ($expectedTotal !== null && (int) $item['amount'] !== $expectedTotal) {
                $violations[] = $this->violation('cash_ledger', 'upsert', 'cash_amount_mismatch');
            }

            $record = CashLedger::where('business_id', $business->id)->where('sync_id', (string) $item['sync_id'])->first();
            if ($record instanceof CashLedger && (int) $record->outlet_id !== $outletId) {
                $violations[] = $this->violation('cash_ledger', 'upsert', 'foreign_outlet_relation');
            }
        }

        return $violations;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $incomingSales
     * @return list<array{entity: string, operation: string, reason?: string}>
     */
    private function authorizeStockMovements(Business $business, int $outletId, array $items, array $incomingSales): array
    {
        $violations = [];

        foreach ($items as $item) {
            $saleSyncId = isset($item['sale_sync_id']) ? (string) $item['sale_sync_id'] : '';

            if ((string) ($item['movement_type'] ?? '') !== 'sale') {
                $violations[] = $this->violation('stock_movements', 'upsert', 'stock_movement_type_not_allowed');
            }

            if ($saleSyncId === '') {
                $violations[] = $this->violation('stock_movements', 'upsert', 'missing_sale_relation');
            } elseif (! $this->saleIsAllowed($business, $outletId, $saleSyncId, $incomingSales)) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'invalid_sale_relation');
            }

            if ((float) ($item['quantity_change'] ?? 0) >= 0.0) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'sale_stock_movement_must_decrease_stock');
            }

            if (! Product::where('business_id', $business->id)->where('sync_id', (string) $item['product_sync_id'])->exists()) {
                $violations[] = $this->violation('stock_movements', 'upsert', 'invalid_product_relation');
            }

            $record = StockMovement::where('business_id', $business->id)->where('sync_id', (string) $item['sync_id'])->first();
            if ($record instanceof StockMovement) {
                $recordSaleSyncId = (string) ($record->sale_sync_id ?? '');
                if ($recordSaleSyncId !== '' && ! $this->saleIsAllowed($business, $outletId, $recordSaleSyncId, $incomingSales)) {
                    $violations[] = $this->violation('stock_movements', 'upsert', 'foreign_outlet_relation');
                }
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
