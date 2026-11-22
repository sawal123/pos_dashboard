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
use App\Services\Catalog\OutletPricing;
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
        protected OutletPricing $outletPricing = new OutletPricing,
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
                ->with(['outletOverrides' => fn ($q) => $q->where('outlet_id', $outlet->id)])
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

                $outletPricing = $this->outletPricing->forProduct($product, $outlet);
                if (! $outletPricing['is_available']) {
                    throw ValidationException::withMessages([
                        'items' => "Produk '{$product->name}' tidak tersedia di cabang ini.",
                    ]);
                }

                $unitPrice = (int) $outletPricing['price'];
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
                    ->forOutlet($outlet->id)
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

            $payLater = ! empty($payload['pay_later']) || in_array(($payload['payment_status'] ?? ''), ['unpaid', 'belum lunas'], true) || in_array(($payload['payment_method'] ?? ''), ['later', 'tempo', 'unpaid'], true);
            $paymentMethod = trim((string) ($payload['payment_method'] ?? 'cash'));
            $paymentMethodService = app(\App\Services\Payment\PaymentMethodService::class);
            $legacyMethods = ['cash', 'qris', 'transfer', 'debit', 'later', 'tempo', 'unpaid'];
            $resolvedMethod = $paymentMethodService->resolveMethod($business, $paymentMethod);

            if (! $payLater && ! $resolvedMethod && ! in_array(strtolower($paymentMethod), $legacyMethods, true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Metode pembayaran tidak valid atau tidak terdaftar pada bisnis ini.',
                ]);
            }

            if ($payLater) {
                $paymentStatus = 'unpaid';
                $paidAt = null;
                $cashReceived = 0;
                $changeAmount = 0;
                $isCash = false;
                $salePaymentMethod = in_array($paymentMethod, ['later', 'tempo', 'unpaid'], true) ? $paymentMethod : 'unpaid';
            } else {
                $paymentStatus = 'paid';
                $paidAt = $now;
                $isCash = $resolvedMethod ? (bool) $resolvedMethod->is_cash : (strtolower($paymentMethod) === 'cash');
                $salePaymentMethod = $resolvedMethod ? (string) $resolvedMethod->code : $paymentMethod;

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
            }

            $grossProfit = round($taxableSubtotal - $costTotal, 2);

            // 8. Snapshots
            $customCustomerName = ! empty($payload['customer_name']) ? trim((string) $payload['customer_name']) : null;
            $customerSnapshot = $customer ? [
                'id' => $customer->id,
                'name' => (string) $customer->name,
                'phone' => (string) ($customer->phone ?? ''),
                'email' => (string) ($customer->email ?? ''),
            ] : ($customCustomerName !== null && $customCustomerName !== '' ? [
                'id' => null,
                'name' => $customCustomerName,
                'phone' => '',
                'email' => '',
            ] : null);

            $tableNumber = ! empty($payload['table_number']) ? trim((string) $payload['table_number']) : null;

            $businessSnapshot = [
                'name' => (string) $business->name,
                'outlet' => (string) $outlet->name,
                'phone' => (string) ($business->phone ?? ''),
            ];

            // 9. Generate Transaction Number
            $transactionNumber = $this->numberGenerator->generateTransactionNumber($business, $outlet, $now);

            // 10. Create Sale — Pesanan baru selalu dimulai dengan status pesanan: Proses
            $sale = new Sale([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'customer_id' => $customer?->id,
                'table_number' => $tableNumber,
                'transaction_number' => $transactionNumber,
                'status' => 'completed',
                'order_status' => 'Proses',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'gross_profit' => $grossProfit,
                'payment_method' => $salePaymentMethod,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'promo_sync_id' => $appliedPromo?->sync_id,
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
                            'outlet_id' => $outlet->id,
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
                                    'outlet_id' => $outlet->id,
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

    /**
     * Add additional menu items to an existing order that is still in "Proses" status.
     *
     * @param  list<array{
     *     product_id: int,
     *     quantity: int|float,
     *     variant_option_ids?: list<int|string>,
     *     variants?: list<array<string, mixed>>
     * }>  $itemsData
     */
    public function addItemsToProcessingSale(
        Business $business,
        Sale $sale,
        array $itemsData,
        ?User $user = null,
        ?bool $markUnpaid = null
    ): Sale {
        if ((int) $sale->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'sale' => 'Transaksi tidak ditemukan pada bisnis ini.',
            ]);
        }

        if (empty($itemsData)) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal 1 item menu tambahan.',
            ]);
        }

        $now = Carbon::now();
        $batchId = (string) \Illuminate\Support\Str::uuid();

        return DB::transaction(function () use (
            $business,
            $sale,
            $itemsData,
            $user,
            $markUnpaid,
            $now,
            $batchId
        ): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()
                ->where('business_id', $business->id)
                ->where('id', $sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statusLower = strtolower(trim((string) $lockedSale->status));
            $orderStatusLower = strtolower(trim((string) $lockedSale->order_status));

            $isCancelled = in_array($statusLower, ['cancelled', 'canceled', 'batal', 'dibatalkan'], true)
                || in_array($orderStatusLower, ['batal', 'cancelled', 'canceled', 'dibatalkan'], true);

            $isProcessing = ! $isCancelled && (
                in_array($orderStatusLower, ['proses', 'diproses', 'masuk', 'processing'], true)
                || in_array($statusLower, ['processing', 'proses'], true)
            );

            if (! $isProcessing) {
                throw ValidationException::withMessages([
                    'status' => 'Pesanan sudah tidak dalam status Proses sehingga tidak dapat ditambah item lagi.',
                ]);
            }

            /** @var Outlet $outlet */
            $outlet = Outlet::query()
                ->where('business_id', $business->id)
                ->where('id', (int) $lockedSale->outlet_id)
                ->firstOrFail();

            // Extract all variant option IDs across added items
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

            $ingredientProductIds = [];
            foreach ($loadedOptions as $opt) {
                if ($opt->ingredient_product_id) {
                    $ingredientProductIds[] = (int) $opt->ingredient_product_id;
                }
            }

            $productIds = array_map(fn ($row) => (int) $row['product_id'], $itemsData);
            $allLockProductIds = array_values(array_unique(array_merge($productIds, $ingredientProductIds)));
            sort($allLockProductIds);

            /** @var Collection<int, Product> $lockedProducts */
            $lockedProducts = Product::query()
                ->where('business_id', $business->id)
                ->whereIn('id', $allLockProductIds)
                ->with(['outletOverrides' => fn ($q) => $q->where('outlet_id', $outlet->id)])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $orderLines = [];

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

                $outletPricing = $this->outletPricing->forProduct($product, $outlet);
                if (! $outletPricing['is_available']) {
                    throw ValidationException::withMessages([
                        'items' => "Produk '{$product->name}' tidak tersedia di cabang ini.",
                    ]);
                }

                $unitPrice = (int) $outletPricing['price'];
                $unitCost = (float) $product->cost;

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
            }

            // Upsert / append SaleItems and deduct stock
            $existingItems = $lockedSale->items()->get();

            foreach ($orderLines as $index => $line) {
                /** @var Product $product */
                $product = $line['product'];

                $matchingItem = $existingItems->first(function (SaleItem $item) use ($product, $line) {
                    return (int) $item->product_id === (int) $product->id
                        && (int) $item->unit_price === (int) $line['unit_price']
                        && (string) ($item->variant_summary ?? '') === (string) ($line['variant_summary'] ?? '');
                });

                if ($matchingItem instanceof SaleItem) {
                    $newQty = round((float) $matchingItem->quantity + (float) $line['quantity'], 3);
                    $matchingItem->update([
                        'quantity' => $newQty,
                        'line_total' => (int) $matchingItem->line_total + (int) $line['line_total'],
                        'line_cost' => round((float) $matchingItem->line_cost + (float) $line['line_cost'], 2),
                    ]);
                    $saleItem = $matchingItem;
                } else {
                    $saleItem = SaleItem::create([
                        'business_id' => $business->id,
                        'sale_id' => $lockedSale->id,
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
                    $existingItems->push($saleItem);
                }

                // Stock deduction for physical trackable products
                if ($product->kind === 'product') {
                    $this->stockLedgerService->record(
                        business: $business,
                        product: $product,
                        type: StockMovementType::Sale,
                        delta: -((float) $line['quantity']),
                        meta: [
                            'outlet_id' => $outlet->id,
                            'sale_sync_id' => (string) $lockedSale->sync_id,
                            'reference_id' => $lockedSale->transaction_number,
                            'category' => 'Tambahan Pesanan POS',
                            'note' => "Tambahan pesanan #{$lockedSale->transaction_number}",
                            'idempotency_key' => "pos-add-item:{$lockedSale->sync_id}:{$batchId}:{$index}:{$product->id}",
                            'occurred_at' => $now,
                        ],
                        user: $user,
                    );
                }

                // Stock deduction for variant ingredients
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
                                    'outlet_id' => $outlet->id,
                                    'sale_sync_id' => (string) $lockedSale->sync_id,
                                    'reference_id' => $lockedSale->transaction_number,
                                    'category' => 'Bahan Baku Varian',
                                    'note' => "Tambahan bahan varian '{$vOpt->name}' untuk {$product->name} (x{$line['quantity']}) #{$lockedSale->transaction_number}",
                                    'idempotency_key' => "pos-add-variant:{$lockedSale->sync_id}:{$batchId}:{$index}:{$saleItem->id}:{$ingProd->id}",
                                    'occurred_at' => $now,
                                ],
                                user: $user,
                            );
                        }
                    }
                }
            }

            // Recalculate totals from authoritative SaleItems
            $allItems = $lockedSale->items()->get();
            $oldTotalAmount = (int) $lockedSale->total_amount;
            $newSubtotal = (int) $allItems->sum('line_total');
            $totalCost = round((float) $allItems->sum('line_cost'), 2);
            $discountAmount = (int) $lockedSale->discount_amount;
            $taxableSubtotal = max(0, $newSubtotal - $discountAmount);
            $taxRate = $business->tax_enabled ? (float) $business->tax_rate : 0.0;
            $newTaxAmount = $business->tax_enabled ? (int) round(($taxableSubtotal * $taxRate) / 100) : 0;
            $newTotalAmount = $taxableSubtotal + $newTaxAmount;
            $addedTotalAmount = max(0, $newTotalAmount - $oldTotalAmount);
            $newGrossProfit = round($taxableSubtotal - $totalCost, 2);

            $paymentStatus = (string) $lockedSale->payment_status;
            $paidAt = $lockedSale->paid_at;
            $cashReceived = $lockedSale->cash_received;
            $changeAmount = $lockedSale->change_amount;

            $isCashMethod = in_array(strtolower(trim((string) $lockedSale->payment_method)), ['cash', 'tunai'], true);

            if ($markUnpaid === true) {
                $paymentStatus = 'unpaid';
                $paidAt = null;
                $cashReceived = 0;
                $changeAmount = 0;
            } elseif (in_array(strtolower($paymentStatus), ['paid', 'lunas'], true) && $addedTotalAmount > 0) {
                if ($isCashMethod) {
                    $cashReceived = max($newTotalAmount, (int) ($cashReceived ?? $oldTotalAmount) + $addedTotalAmount);
                    $changeAmount = max(0, $cashReceived - $newTotalAmount);

                    if ($lockedSale->shift_id) {
                        CashLedger::create([
                            'business_id' => $business->id,
                            'outlet_id' => $outlet->id,
                            'shift_id' => $lockedSale->shift_id,
                            'type' => 'in',
                            'amount' => $addedTotalAmount,
                            'category' => 'Penjualan',
                            'note' => "Tambahan pesanan {$lockedSale->transaction_number}",
                            'reference_id' => "CASH-ADD-{$lockedSale->transaction_number}",
                            'sale_sync_id' => (string) $lockedSale->sync_id,
                            'idempotency_key' => "pos-cash-add:{$lockedSale->sync_id}:{$batchId}",
                            'occurred_at' => $now,
                        ]);
                    }
                } else {
                    $cashReceived = $newTotalAmount;
                    $changeAmount = 0;
                }
            }

            $lockedSale->update([
                'subtotal' => $newSubtotal,
                'tax_amount' => $newTaxAmount,
                'total_amount' => $newTotalAmount,
                'gross_profit' => $newGrossProfit,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
            ]);

            return $lockedSale->fresh(['items', 'promotions.promo', 'customer', 'shift', 'outlet']);
        });
    }
}
