<?php

namespace App\Services\Pos;

use App\Enums\PromoStatus;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePromotion;
use App\Models\Shift;
use App\Models\User;
use App\Models\VariantGroup;
use App\Models\VariantOption;
use App\Services\Inventory\StockLedgerService;
use App\Services\Promo\PromoEvaluator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCheckoutService
{
    public function __construct(
        protected PosNumberGenerator $numberGenerator,
        protected StockLedgerService $stockLedgerService,
        protected PromoEvaluator $promoEvaluator,
        protected PosShiftService $shiftService,
    ) {}

    /**
     * Process checkout for a POS Web order.
     *
     * @param  array{
     *     outlet_id: int,
     *     shift_id?: int|null,
     *     customer_id?: int|null,
     *     payment_method: string,
     *     cash_received?: int|null,
     *     promo_id?: int|null,
     *     note?: string|null,
     *     client_sale_id: string,
     *     items: list<array{
     *         product_id: int,
     *         quantity: int|float,
     *         variant_option_ids?: list<int|string>,
     *         variants?: list<array<string, mixed>>
     *     }>
     * }  $payload
     */
    public function checkout(
        Business $business,
        array $payload,
        ?User $user = null
    ): Sale {
        $clientSaleId = (string) $payload['client_sale_id'];

        // 1. Idempotency check: if sale already exists by this client_sale_id, return it immediately
        $existing = Sale::query()
            ->where('business_id', $business->id)
            ->where('sync_id', $clientSaleId)
            ->with(['items', 'promotions.promo', 'customer', 'shift', 'outlet'])
            ->first();

        if ($existing instanceof Sale) {
            return $existing;
        }

        // 2. Validate Outlet
        /** @var Outlet|null $outlet */
        $outlet = Outlet::query()
            ->where('business_id', $business->id)
            ->where('id', (int) $payload['outlet_id'])
            ->first();

        if ($outlet === null) {
            throw ValidationException::withMessages([
                'outlet_id' => 'Outlet tidak ditemukan atau bukan milik bisnis ini.',
            ]);
        }

        if ($outlet->status !== 'active') {
            throw ValidationException::withMessages([
                'outlet_id' => 'Outlet ini sedang tidak aktif.',
            ]);
        }

        // 3. Resolve Shift (must be open)
        $shift = null;
        if (! empty($payload['shift_id'])) {
            $shift = Shift::query()
                ->where('business_id', $business->id)
                ->where('outlet_id', $outlet->id)
                ->where('id', (int) $payload['shift_id'])
                ->where('status', 'open')
                ->first();

            if ($shift === null) {
                throw ValidationException::withMessages([
                    'shift' => 'Shift yang dipilih tidak valid atau sudah ditutup.',
                ]);
            }
        } else {
            $shift = $this->shiftService->currentOpenShift($business, $outlet);
            if ($shift === null) {
                throw ValidationException::withMessages([
                    'shift' => 'Belum ada shift kasir yang terbuka di outlet ini. Buka shift terlebih dahulu.',
                ]);
            }
        }

        // 4. Resolve Customer
        $customer = null;
        if (! empty($payload['customer_id'])) {
            $customer = Customer::query()
                ->where('business_id', $business->id)
                ->where('id', (int) $payload['customer_id'])
                ->first();

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan tidak ditemukan.',
                ]);
            }
        }

        // 5. Validate Items List
        $itemsData = $payload['items'];
        if (empty($itemsData)) {
            throw ValidationException::withMessages([
                'items' => 'Keranjang belanja tidak boleh kosong.',
            ]);
        }

        $now = Carbon::now();

        return DB::transaction(function () use (
            $business,
            $outlet,
            $shift,
            $customer,
            $payload,
            $clientSaleId,
            $itemsData,
            $now,
            $user
        ): Sale {
            // Re-check idempotency under lock
            $existing = Sale::query()
                ->where('business_id', $business->id)
                ->where('sync_id', $clientSaleId)
                ->with(['items', 'promotions.promo', 'customer', 'shift', 'outlet'])
                ->first();

            if ($existing instanceof Sale) {
                return $existing;
            }

            // Extract all variant option IDs across all items
            $allOptionIds = [];
            foreach ($itemsData as $row) {
                if (! empty($row['variant_option_ids'])) {
                    foreach ($row['variant_option_ids'] as $oid) {
                        $allOptionIds[] = (int) $oid;
                    }
                } elseif (! empty($row['variants'])) {
                    foreach ($row['variants'] as $v) {
                        if (! empty($v['option_id'])) {
                            $allOptionIds[] = (int) $v['option_id'];
                        }
                    }
                }
            }
            $allOptionIds = array_values(array_unique(array_filter($allOptionIds)));

            /** @var Collection<int, VariantOption> $loadedOptions */
            $loadedOptions = ! empty($allOptionIds)
                ? VariantOption::query()
                    ->where('business_id', $business->id)
                    ->whereIn('id', $allOptionIds)
                    ->where('status', '!=', 'deleted')
                    ->with(['group', 'ingredientProduct'])
                    ->get()
                    ->keyBy('id')
                : collect();

            // Extract ingredient product IDs to lock together with sellable products
            $ingredientProductIds = [];
            foreach ($loadedOptions as $opt) {
                if ($opt->ingredient_product_id) {
                    $ingredientProductIds[] = (int) $opt->ingredient_product_id;
                }
            }

            // Extract and lock product IDs in deterministic order to prevent deadlocks
            $productIds = array_map(fn ($row) => (int) $row['product_id'], $itemsData);
            $allLockProductIds = array_values(array_unique(array_merge($productIds, $ingredientProductIds)));
            sort($allLockProductIds);

            /** @var Collection<int, Product> $lockedProducts */
            $lockedProducts = Product::query()
                ->where('business_id', $business->id)
                ->whereIn('id', $allLockProductIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $costTotal = 0.0;
            $orderLines = [];
            $evaluatorLines = [];

            foreach ($itemsData as $row) {
                $pId = (int) $row['product_id'];
                $qty = (float) $row['quantity'];

                if ($qty <= 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah kuantitas item harus lebih besar dari 0.',
                    ]);
                }

                $product = $lockedProducts->get($pId);
                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'items' => "Produk ID {$pId} tidak ditemukan dalam katalog bisnis.",
                    ]);
                }

                if ($product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => "Produk '{$product->name}' sedang non-aktif dan tidak dapat dijual.",
                    ]);
                }

                if (! $product->is_sellable) {
                    throw ValidationException::withMessages([
                        'items' => "Item '{$product->name}' adalah bahan persediaan dan tidak diset untuk dijual di POS.",
                    ]);
                }

                $unitPrice = (int) $product->price;
                $unitCost = (float) $product->cost;

                // Process selected variants for this line item
                $selectedOptionIds = [];
                if (! empty($row['variant_option_ids'])) {
                    $selectedOptionIds = array_map('intval', $row['variant_option_ids']);
                } elseif (! empty($row['variants'])) {
                    $selectedOptionIds = array_map(fn ($v) => (int) ($v['option_id'] ?? 0), $row['variants']);
                }
                $selectedOptionIds = array_values(array_unique(array_filter($selectedOptionIds)));

                $lineVariantPriceAdj = 0;
                $lineVariantCostAdj = 0;
                $lineVariantsSnapshot = [];
                $summaryParts = [];
                $lineVariantOptions = [];

                foreach ($selectedOptionIds as $optId) {
                    $opt = $loadedOptions->get($optId);
                    if ($opt instanceof VariantOption) {
                        $lineVariantPriceAdj += (int) $opt->price_adjustment;
                        $lineVariantCostAdj += (int) $opt->cost_adjustment;
                        $lineVariantOptions[] = $opt;

                        $part = $opt->name;
                        if ($opt->price_adjustment > 0) {
                            $part .= ' (+'.number_format($opt->price_adjustment, 0, ',', '.').')';
                        }
                        $summaryParts[] = $part;

                        $ingProd = $opt->ingredient_product_id ? $lockedProducts->get($opt->ingredient_product_id) : null;
                        if ($ingProd instanceof Product && (float) $opt->ingredient_quantity > 0) {
                            $lineVariantCostAdj += round((float) $ingProd->cost * (float) $opt->ingredient_quantity, 2);
                        }

                        $groupName = $opt->group instanceof VariantGroup ? $opt->group->name : 'Varian';
                        $ingredientName = $ingProd instanceof Product
                            ? $ingProd->name
                            : ($opt->ingredientProduct instanceof Product ? $opt->ingredientProduct->name : null);

                        $lineVariantsSnapshot[] = [
                            'group_id' => $opt->variant_group_id,
                            'group_name' => $groupName,
                            'option_id' => $opt->id,
                            'option_name' => $opt->name,
                            'price_adjustment' => (int) $opt->price_adjustment,
                            'cost_adjustment' => (int) $opt->cost_adjustment,
                            'ingredient_product_id' => $opt->ingredient_product_id,
                            'ingredient_name' => $ingredientName,
                            'ingredient_quantity' => (float) $opt->ingredient_quantity,
                        ];
                    }
                }

                $variantSummary = ! empty($summaryParts) ? implode(', ', $summaryParts) : null;
                $effectiveUnitPrice = $unitPrice + $lineVariantPriceAdj;
                $effectiveUnitCost = $unitCost + $lineVariantCostAdj;
                $lineTotal = (int) round($effectiveUnitPrice * $qty);
                $lineCost = round($effectiveUnitCost * $qty, 2);

                $subtotal += $lineTotal;
                $costTotal += $lineCost;

                $orderLines[] = [
                    'product' => $product,
                    'unit_price' => $effectiveUnitPrice,
                    'unit_cost' => $effectiveUnitCost,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                    'variant_summary' => $variantSummary,
                    'variants_snapshot' => ! empty($lineVariantsSnapshot) ? $lineVariantsSnapshot : null,
                    'variant_options' => $lineVariantOptions,
                ];

                $evaluatorLines[] = [
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'unit_price' => $effectiveUnitPrice,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            // 6. Evaluate Promotion if selected
            $discountAmount = 0;
            $appliedPromo = null;
            if (! empty($payload['promo_id'])) {
                /** @var Promo|null $promo */
                $promo = Promo::query()
                    ->where('business_id', $business->id)
                    ->where('id', (int) $payload['promo_id'])
                    ->first();

                if ($promo instanceof Promo && $promo->status === PromoStatus::Active->value) {
                    $promoCustomerId = $customer instanceof Customer ? (int) $customer->id : null;
                    $result = $this->promoEvaluator->evaluate($promo, $subtotal, $evaluatorLines, $now, $promoCustomerId);
                    if ($result['eligible']) {
                        // Check usage limit total if defined
                        $limitExceeded = false;
                        if ($promo->usage_limit_total !== null) {
                            $usedCount = SalePromotion::query()
                                ->where('business_id', $business->id)
                                ->where('promo_id', $promo->id)
                                ->count();

                            if ($usedCount >= (int) $promo->usage_limit_total) {
                                $limitExceeded = true;
                            }
                        }

                        if (! $limitExceeded && $promo->usage_limit_per_customer !== null && $customer instanceof Customer) {
                            $usedByCustomer = SalePromotion::query()
                                ->where('sale_promotions.business_id', $business->id)
                                ->where('sale_promotions.promo_id', $promo->id)
                                ->join('sales', 'sales.id', '=', 'sale_promotions.sale_id')
                                ->where('sales.customer_id', $customer->id)
                                ->count();

                            if ($usedByCustomer >= (int) $promo->usage_limit_per_customer) {
                                $limitExceeded = true;
                            }
                        }

                        if (! $limitExceeded) {
                            $discountAmount = (int) $result['discount'];
                            $appliedPromo = $promo;
                        }
                    }
                }
            }

            // 7. Calculate Final Total and Payment
            $taxRate = $business->tax_enabled ? (float) $business->tax_rate : 0.0;
            $taxableSubtotal = max(0, $subtotal - $discountAmount);
            $taxAmount = $business->tax_enabled ? (int) round(($taxableSubtotal * $taxRate) / 100) : 0;
            $totalAmount = $taxableSubtotal + $taxAmount;
            $paymentMethod = (string) $payload['payment_method'];
            $validMethods = ['cash', 'qris', 'transfer', 'debit'];
            if (! in_array($paymentMethod, $validMethods, true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Metode pembayaran tidak valid.',
                ]);
            }

            $isCash = ($paymentMethod === 'cash');
            if ($isCash) {
                $cashReceived = isset($payload['cash_received'])
                    ? (int) $payload['cash_received']
                    : $totalAmount;

                if ($cashReceived < $totalAmount) {
                    throw ValidationException::withMessages([
                        'cash_received' => 'Uang tunai yang diterima kurang dari total tagihan.',
                    ]);
                }
                $changeAmount = $cashReceived - $totalAmount;
            } else {
                $cashReceived = $totalAmount;
                $changeAmount = 0;
            }

            $grossProfit = round($taxableSubtotal - $costTotal, 2);

            // 8. Snapshots
            $customerSnapshot = $customer ? [
                'id' => $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) ($customer->phone ?? ''),
                'email' => (string) ($customer->email ?? ''),
            ] : null;

            $businessSnapshot = [
                'name' => (string) $business->name,
                'outlet' => (string) $outlet->name,
                'phone' => (string) ($business->phone ?? ''),
            ];

            // 9. Generate Transaction Number
            $transactionNumber = $this->numberGenerator->generateTransactionNumber($business, $outlet, $now);

            // 10. Create Sale
            $sale = new Sale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'customer_id' => $customer?->id,
                'transaction_number' => $transactionNumber,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'gross_profit' => $grossProfit,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'paid_at' => $now,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'promo_sync_id' => $appliedPromo?->sync_id,
                'order_status' => $business->normalizedBusinessType() === 'laundry' ? 'Masuk' : null,
                'note' => ! empty($payload['note']) ? (string) $payload['note'] : null,
                'customer_snapshot' => $customerSnapshot,
                'business_snapshot' => $businessSnapshot,
                'sold_at' => $now,
            ]);
            $sale->sync_id = $clientSaleId;
            $sale->save();

            // 11. Create Sale Items
            foreach ($orderLines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                $saleItem = SaleItem::create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => (string) $product->name,
                    'product_sku' => (string) $product->sku,
                    'variant_summary' => $line['variant_summary'],
                    'variants_snapshot' => $line['variants_snapshot'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'cost_snapshot' => $line['unit_cost'],
                    'unit' => (string) ($product->unit ?? 'pcs'),
                    'kind' => (string) ($product->kind ?? 'product'),
                    'pricing_unit' => (string) ($product->pricing_unit ?? 'pcs'),
                    'line_cost' => $line['line_cost'],
                ]);

                // 12. Stock Deduction for physical trackable products
                if ($product->kind === 'product') {
                    $this->stockLedgerService->record(
                        business: $business,
                        product: $product,
                        type: StockMovementType::Sale,
                        delta: -((float) $line['quantity']),
                        meta: [
                            'sale_sync_id' => (string) $sale->sync_id,
                            'reference_id' => $sale->transaction_number,
                            'category' => 'Penjualan POS',
                            'note' => "Penjualan kasir #{$sale->transaction_number}",
                            'idempotency_key' => "pos-sale:{$sale->sync_id}:{$product->id}",
                            'occurred_at' => $now,
                        ],
                        user: $user,
                    );
                }

                // 12b. Stock Deduction for variant ingredients
                if (! empty($line['variant_options'])) {
                    foreach ($line['variant_options'] as $vOpt) {
                        $ingProd = $vOpt->ingredient_product_id ? $lockedProducts->get($vOpt->ingredient_product_id) : null;
                        if (! $ingProd instanceof Product) {
                            $ingProd = $vOpt->ingredientProduct;
                        }

                        if ($ingProd instanceof Product && (float) $vOpt->ingredient_quantity > 0) {
                            $deductIngredientQty = (float) $vOpt->ingredient_quantity * (float) $line['quantity'];
                            $this->stockLedgerService->record(
                                business: $business,
                                product: $ingProd,
                                type: StockMovementType::Sale,
                                delta: -$deductIngredientQty,
                                meta: [
                                    'sale_sync_id' => (string) $sale->sync_id,
                                    'reference_id' => $sale->transaction_number,
                                    'category' => 'Bahan Baku Varian',
                                    'note' => "Bahan varian '{$vOpt->name}' untuk {$product->name} (x{$line['quantity']}) #{$sale->transaction_number}",
                                    'idempotency_key' => "pos-sale-variant:{$sale->sync_id}:{$saleItem->id}:{$ingProd->id}",
                                    'occurred_at' => $now,
                                ],
                                user: $user,
                            );
                        }
                    }
                }
            }

            // 13. Create SalePromotion if applied
            if ($appliedPromo instanceof Promo) {
                SalePromotion::create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'promo_id' => $appliedPromo->id,
                    'promo_sync_id' => (string) $appliedPromo->sync_id,
                    'discount_amount' => $discountAmount,
                    'created_at' => $now,
                ]);
            }

            // 14. Record Cash Ledger for Cash Sale
            if ($isCash) {
                CashLedger::create([
                    'business_id' => $business->id,
                    'outlet_id' => $outlet->id,
                    'shift_id' => $shift->id,
                    'type' => 'in',
                    'amount' => $totalAmount,
                    'category' => 'Penjualan',
                    'note' => "Pembayaran transaksi {$sale->transaction_number}",
                    'reference_id' => "CASH-{$sale->transaction_number}",
                    'sale_sync_id' => (string) $sale->sync_id,
                    'idempotency_key' => "pos-cash:{$sale->sync_id}",
                    'occurred_at' => $now,
                ]);
            }

            return $sale->load(['items', 'promotions.promo', 'customer', 'shift', 'outlet']);
        });
    }
}
