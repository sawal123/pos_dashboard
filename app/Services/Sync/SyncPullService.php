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
use App\Models\SyncCounter;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

class SyncPullService
{
    /**
     * Fetch delta changes for a device starting after a given sequence cursor.
     *
     * @param  array{user: User, business: Business, device: Device, outlet_id: int}  $context
     */
    public function pull(array $context, int $after = 0, int $limit = 100): JsonResponse
    {
        $limit = min(max($limit, 1), 200);
        $business = $context['business'];
        $device = $context['device'];
        $outletId = $context['outlet_id'];

        // 1. Capture snapshot server sequence
        $serverSequence = (int) (SyncCounter::where('business_id', $business->id)->value('current_sequence') ?? 0);
        $fetchLimit = $limit + 1;

        // 2. Fetch changes across syncable entities within sequence window
        $records = [];

        // Categories
        $categories = Category::where('business_id', $business->id)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->get();

        foreach ($categories as $cat) {
            $records[] = [
                'entity' => 'categories',
                'sync_sequence' => (int) $cat->sync_sequence,
                'data' => [
                    'sync_id' => $cat->sync_id,
                    'sync_version' => (int) $cat->sync_version,
                    'name' => $cat->name,
                    'status' => $cat->status,
                ],
            ];
        }

        // Products
        $products = Product::where('business_id', $business->id)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->with('category')
            ->get();

        foreach ($products as $prod) {
            $records[] = [
                'entity' => 'products',
                'sync_sequence' => (int) $prod->sync_sequence,
                'data' => [
                    'sync_id' => $prod->sync_id,
                    'sync_version' => (int) $prod->sync_version,
                    'category_sync_id' => $prod->category?->sync_id,
                    'name' => $prod->name,
                    'sku' => $prod->sku,
                    'barcode' => $prod->barcode,
                    'price' => (int) $prod->price,
                    'kind' => $prod->kind,
                    'cost' => (float) $prod->cost,
                    'stock' => (float) $prod->stock,
                    'unit' => $prod->unit,
                    'min_stock' => (float) $prod->min_stock,
                    'pricing_unit' => $prod->pricing_unit,
                    'min_quantity' => (float) $prod->min_quantity,
                    'estimated_duration' => $prod->estimated_duration,
                    'status' => $prod->status,
                ],
            ];
        }

        // Customers
        $customers = Customer::where('business_id', $business->id)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->get();

        foreach ($customers as $cust) {
            $records[] = [
                'entity' => 'customers',
                'sync_sequence' => (int) $cust->sync_sequence,
                'data' => [
                    'sync_id' => $cust->sync_id,
                    'sync_version' => (int) $cust->sync_version,
                    'name' => $cust->name,
                    'phone' => $cust->phone,
                    'email' => $cust->email,
                    'address' => $cust->address,
                    'notes' => $cust->notes,
                    'status' => $cust->status,
                ],
            ];
        }

        // Shifts (Outlet-specific)
        $shifts = Shift::where('business_id', $business->id)
            ->where('outlet_id', $outletId)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->get();

        foreach ($shifts as $shift) {
            $records[] = [
                'entity' => 'shifts',
                'sync_sequence' => (int) $shift->sync_sequence,
                'data' => [
                    'sync_id' => $shift->sync_id,
                    'sync_version' => (int) $shift->sync_version,
                    'shift_number' => $shift->shift_number,
                    'status' => $shift->status,
                    'opening_cash' => (int) $shift->opening_cash,
                    'closing_cash' => $shift->closing_cash !== null ? (int) $shift->closing_cash : null,
                    'opened_at' => $this->formatDate($shift->opened_at),
                    'closed_at' => $this->formatDate($shift->closed_at),
                    'notes' => $shift->notes,
                ],
            ];
        }

        // Sales (Outlet-specific)
        $sales = Sale::where('business_id', $business->id)
            ->where('outlet_id', $outletId)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->with(['customer', 'shift'])
            ->get();

        foreach ($sales as $sale) {
            $records[] = [
                'entity' => 'sales',
                'sync_sequence' => (int) $sale->sync_sequence,
                'data' => [
                    'sync_id' => $sale->sync_id,
                    'sync_version' => (int) $sale->sync_version,
                    'customer_sync_id' => $sale->customer?->sync_id,
                    'shift_sync_id' => $sale->shift?->sync_id,
                    'transaction_number' => $sale->transaction_number,
                    'status' => $sale->status,
                    'subtotal' => (int) $sale->subtotal,
                    'discount_amount' => (int) $sale->discount_amount,
                    'tax_amount' => (int) $sale->tax_amount,
                    'total_amount' => (int) $sale->total_amount,
                    'payment_method' => $sale->payment_method,
                    'payment_status' => $sale->payment_status,
                    'paid_at' => $this->formatDate($sale->paid_at),
                    'cash_received' => $sale->cash_received !== null ? (int) $sale->cash_received : null,
                    'change_amount' => $sale->change_amount !== null ? (int) $sale->change_amount : null,
                    'gross_profit' => (float) $sale->gross_profit,
                    'order_status' => $sale->order_status,
                    'estimated_completed_at' => $this->formatDate($sale->estimated_completed_at),
                    'note' => $sale->note,
                    'customer_snapshot' => $sale->customer_snapshot,
                    'business_snapshot' => $sale->business_snapshot,
                    'sold_at' => $this->formatDate($sale->sold_at),
                ],
            ];
        }

        // SaleItems (Outlet-specific via parent Sale)
        $saleItems = SaleItem::where('sale_items.business_id', $business->id)
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.outlet_id', $outletId)
            ->where('sale_items.sync_sequence', '>', $after)
            ->where('sale_items.sync_sequence', '<=', $serverSequence)
            ->orderBy('sale_items.sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->select('sale_items.*')
            ->with(['sale', 'product'])
            ->get();

        foreach ($saleItems as $item) {
            $records[] = [
                'entity' => 'sale_items',
                'sync_sequence' => (int) $item->sync_sequence,
                'data' => [
                    'sync_id' => $item->sync_id,
                    'sync_version' => (int) $item->sync_version,
                    'sale_sync_id' => $item->sale?->sync_id,
                    'product_sync_id' => $item->product?->sync_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'unit_price' => (int) $item->unit_price,
                    'quantity' => (float) $item->quantity,
                    'line_total' => (int) $item->line_total,
                    'cost_snapshot' => (float) $item->cost_snapshot,
                    'unit' => $item->unit,
                    'kind' => $item->kind,
                    'pricing_unit' => $item->pricing_unit,
                    'line_cost' => (float) $item->line_cost,
                ],
            ];
        }

        // Expenses (Outlet-specific)
        $expenses = Expense::where('business_id', $business->id)
            ->where('outlet_id', $outletId)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->with('shift')
            ->get();

        foreach ($expenses as $exp) {
            $records[] = [
                'entity' => 'expenses',
                'sync_sequence' => (int) $exp->sync_sequence,
                'data' => [
                    'sync_id' => $exp->sync_id,
                    'sync_version' => (int) $exp->sync_version,
                    'shift_sync_id' => $exp->shift?->sync_id,
                    'description' => $exp->description,
                    'category' => $exp->category,
                    'amount' => (int) $exp->amount,
                    'status' => $exp->status,
                    'occurred_at' => $this->formatDate($exp->occurred_at),
                    'notes' => $exp->notes,
                ],
            ];
        }

        // Cash ledger (Outlet-specific)
        $cashEntries = CashLedger::where('business_id', $business->id)
            ->where('outlet_id', $outletId)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->with('shift')
            ->get();

        foreach ($cashEntries as $entry) {
            $records[] = [
                'entity' => 'cash_ledger',
                'sync_sequence' => (int) $entry->sync_sequence,
                'data' => [
                    'sync_id' => $entry->sync_id,
                    'sync_version' => (int) $entry->sync_version,
                    'shift_sync_id' => $entry->shift?->sync_id,
                    'type' => $entry->type,
                    'amount' => (int) $entry->amount,
                    'category' => $entry->category,
                    'note' => $entry->note,
                    'reference_id' => $entry->reference_id,
                    'sale_sync_id' => $entry->sale_sync_id,
                    'occurred_at' => $this->formatDate($entry->occurred_at),
                ],
            ];
        }

        // Stock movements (Business-wide; parents are resolved by product identity)
        $movements = StockMovement::where('business_id', $business->id)
            ->where('sync_sequence', '>', $after)
            ->where('sync_sequence', '<=', $serverSequence)
            ->orderBy('sync_sequence', 'asc')
            ->limit($fetchLimit)
            ->with('product')
            ->get();

        foreach ($movements as $movement) {
            $records[] = [
                'entity' => 'stock_movements',
                'sync_sequence' => (int) $movement->sync_sequence,
                'data' => [
                    'sync_id' => $movement->sync_id,
                    'sync_version' => (int) $movement->sync_version,
                    'product_sync_id' => $movement->product?->sync_id,
                    'movement_type' => $movement->movement_type,
                    'quantity_change' => (float) $movement->quantity_change,
                    'stock_before' => (float) $movement->stock_before,
                    'stock_after' => (float) $movement->stock_after,
                    'reference_id' => $movement->reference_id,
                    'category' => $movement->category,
                    'note' => $movement->note,
                    'sale_sync_id' => $movement->sale_sync_id,
                    'occurred_at' => $this->formatDate($movement->occurred_at),
                ],
            ];
        }

        // 3. Sort records monotonically by sync_sequence ASC
        usort($records, fn (array $a, array $b) => $a['sync_sequence'] <=> $b['sync_sequence']);

        // 4. Paginate
        $totalRecords = count($records);
        $hasMore = $totalRecords > $limit;

        if ($hasMore) {
            $pagedRecords = array_slice($records, 0, $limit);
            /** @var array{entity: string, sync_sequence: int, data: array<string, mixed>} $lastItem */
            $lastItem = end($pagedRecords);
            $nextCursor = $lastItem['sync_sequence'];
        } else {
            $pagedRecords = $records;
            $nextCursor = $serverSequence;
        }

        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'data' => [
                'records' => $pagedRecords,
                'next_cursor' => $nextCursor,
                'server_sequence' => $serverSequence,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * Format date value safely.
     */
    protected function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return (string) $value;
    }
}
