<?php

namespace Tests\Feature;

use App\Enums\PromoDiscountType;
use App\Enums\PromoStatus;
use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Sale;
use App\Models\SalePromotion;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VariantGroup;
use App\Models\VariantOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_cannot_checkout_when_shift_is_not_open(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $product = $this->createProduct($business, ['price' => 20000, 'stock' => 10]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('shift');
        $this->assertSame(0, Sale::count());
    }

    public function test_02_open_shift_and_close_shift_flow(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();

        // 1. Open shift
        $openRes = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.shifts.open'), [
                'outlet_id' => $outlet->id,
                'opening_cash' => 150000,
                'notes' => 'Buka pagi',
            ]);

        $openRes->assertOk();
        $openRes->assertJsonPath('success', true);

        $shift = Shift::where('business_id', $business->id)->firstOrFail();
        $this->assertSame('open', $shift->status);
        $this->assertSame(150000, (int) $shift->opening_cash);

        // Verify initial cash ledger entry for opening cash
        $initialCash = CashLedger::where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('category', 'modal')
            ->first();
        $this->assertNotNull($initialCash);
        $this->assertSame(150000, (int) $initialCash->amount);

        // 2. Summary
        $sumRes = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->getJson(route('pos.shifts.summary', $shift->id));

        $sumRes->assertOk();
        $sumRes->assertJsonPath('summary.opening_cash', 150000);
        $sumRes->assertJsonPath('summary.expected_cash', 150000);

        // 3. Close shift
        $closeRes = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.shifts.close'), [
                'shift_id' => $shift->id,
                'closing_cash' => 150000,
                'notes' => 'Tutup aman',
            ]);

        $closeRes->assertOk();
        $this->assertSame('closed', $shift->fresh()->status);
        $this->assertSame(150000, (int) $shift->fresh()->closing_cash);
        $this->assertNotNull($shift->fresh()->closed_at);
    }

    public function test_03_successful_cash_sale_creates_sale_items_stock_and_cash(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);

        $product1 = $this->createProduct($business, [
            'name' => 'Kopi Latte',
            'sku' => 'LATTE-01',
            'price' => 25000,
            'cost' => 10000,
            'stock' => 15,
        ]);
        $product2 = $this->createProduct($business, [
            'name' => 'Croissant',
            'sku' => 'SNACK-01',
            'price' => 15000,
            'cost' => 6000,
            'stock' => 20,
        ]);

        $clientSaleId = (string) Str::uuid();

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'payment_method' => 'cash',
                'cash_received' => 100000,
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 2], // 50.000, cost: 20.000
                    ['product_id' => $product2->id, 'quantity' => 1], // 15.000, cost: 6.000
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('sale.subtotal', 65000);
        $response->assertJsonPath('sale.total_amount', 65000);
        $response->assertJsonPath('sale.cash_received', 100000);
        $response->assertJsonPath('sale.change_amount', 35000);

        // Verify Sale in database
        $sale = Sale::where('business_id', $business->id)->where('sync_id', $clientSaleId)->firstOrFail();
        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame(65000, (int) $sale->total_amount);
        $this->assertSame(39000.0, (float) $sale->gross_profit); // 65000 - 26000 cost

        // Verify items
        $this->assertSame(2, $sale->items()->count());
        $item1 = $sale->items()->where('product_id', $product1->id)->firstOrFail();
        $this->assertSame('2.000', (string) $item1->quantity);
        $this->assertSame(50000, (int) $item1->line_total);
        $this->assertSame('10000.00', (string) $item1->cost_snapshot);

        // Verify stock movements
        $this->assertSame('13.000', (string) $product1->fresh()->stock);
        $this->assertSame('19.000', (string) $product2->fresh()->stock);

        $mov1 = StockMovement::where('business_id', $business->id)
            ->where('product_id', $product1->id)
            ->where('sale_sync_id', $clientSaleId)
            ->firstOrFail();
        $this->assertSame('sale', $mov1->movement_type);
        $this->assertSame('-2.000', (string) $mov1->quantity_change);

        // Verify Cash Ledger
        $cashEntry = CashLedger::where('business_id', $business->id)
            ->where('sale_sync_id', $clientSaleId)
            ->firstOrFail();
        $this->assertSame('in', $cashEntry->type);
        $this->assertSame(65000, (int) $cashEntry->amount);
        $this->assertSame($shift->id, $cashEntry->shift_id);
    }

    public function test_04_idempotent_retry_returns_existing_sale(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);
        $product = $this->createProduct($business, ['price' => 10000, 'stock' => 10]);

        $clientSaleId = (string) Str::uuid();
        $payload = [
            'client_sale_id' => $clientSaleId,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'payment_method' => 'cash',
            'cash_received' => 10000,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        // First call
        $res1 = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), $payload);
        $res1->assertOk();

        // Second call with same client_sale_id (e.g. double click)
        $res2 = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), $payload);
        $res2->assertOk();

        // Exactly one sale and one stock movement created
        $this->assertSame(1, Sale::where('business_id', $business->id)->count());
        $this->assertSame(1, StockMovement::where('business_id', $business->id)->count());
        $this->assertSame('9.000', (string) $product->fresh()->stock);
    }

    public function test_05_non_cash_sale_does_not_create_physical_cash_ledger(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);
        $product = $this->createProduct($business, ['price' => 30000, 'stock' => 5]);

        $clientSaleId = (string) Str::uuid();

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'payment_method' => 'qris',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertOk();
        $sale = Sale::where('business_id', $business->id)->where('sync_id', $clientSaleId)->firstOrFail();
        $this->assertSame('qris', $sale->payment_method);
        $this->assertSame(30000, (int) $sale->total_amount);

        // No physical cash ledger entry for QRIS
        $this->assertNull(CashLedger::where('sale_sync_id', $clientSaleId)->first());
    }

    public function test_06_promo_discount_is_calculated_and_recorded(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);
        $product = $this->createProduct($business, ['price' => 50000, 'stock' => 10]);

        // 10% promo
        $promo = Promo::create([
            'business_id' => $business->id,
            'name' => 'Diskon 10%',
            'discount_type' => PromoDiscountType::Percent->value,
            'discount_value' => 1000, // 10% in basis points
            'status' => PromoStatus::Active->value,
        ]);

        $clientSaleId = (string) Str::uuid();

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'promo_id' => $promo->id,
                'payment_method' => 'cash',
                'cash_received' => 50000,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('sale.subtotal', 50000);
        $response->assertJsonPath('sale.discount_amount', 5000);
        $response->assertJsonPath('sale.total_amount', 45000);
        $response->assertJsonPath('sale.change_amount', 5000);

        // Check SalePromotion record
        $sale = Sale::where('business_id', $business->id)->where('sync_id', $clientSaleId)->firstOrFail();
        $this->assertSame((string) $promo->sync_id, (string) $sale->promo_sync_id);

        $sp = SalePromotion::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame($promo->id, $sp->promo_id);
        $this->assertSame(5000, (int) $sp->discount_amount);
    }

    public function test_07_service_items_are_sold_without_affecting_stock(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);

        $service = $this->createProduct($business, [
            'name' => 'Cuci Lipat Kiloan',
            'kind' => 'service',
            'price' => 8000,
            'stock' => 0,
        ]);

        $clientSaleId = (string) Str::uuid();

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'payment_method' => 'cash',
                'cash_received' => 20000,
                'items' => [
                    ['product_id' => $service->id, 'quantity' => 2],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('sale.total_amount', 16000);

        // No stock movement for services
        $this->assertSame(0, StockMovement::where('product_id', $service->id)->count());
    }

    public function test_08_insufficient_cash_is_rejected(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);
        $product = $this->createProduct($business, ['price' => 50000]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'payment_method' => 'cash',
                'cash_received' => 40000, // Less than 50.000
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cash_received');
    }

    public function test_09_inactive_or_non_sellable_products_are_rejected(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);

        $supply = $this->createProduct($business, [
            'name' => 'Biji Kopi Mentah',
            'is_sellable' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => (string) Str::uuid(),
                'outlet_id' => $outlet->id,
                'shift_id' => $shift->id,
                'payment_method' => 'cash',
                'items' => [
                    ['product_id' => $supply->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
    }

    public function test_10_receipt_view_renders_successfully(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $shift = $this->openShift($business, $outlet);
        $product = $this->createProduct($business, ['name' => 'Kopi Tubruk', 'price' => 12000]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'WEB-UTM-20261102-0001',
            'status' => 'completed',
            'subtotal' => 12000,
            'total_amount' => 12000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => 15000,
            'change_amount' => 3000,
            'sold_at' => now(),
        ]);

        $sale->items()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 12000,
            'quantity' => 1,
            'line_total' => 12000,
            'cost_snapshot' => 5000,
            'line_cost' => 5000,
            'unit' => 'cup',
            'kind' => 'product',
            'pricing_unit' => 'cup',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.receipt', $sale->id));

        $response->assertOk();
        $response->assertSee('WEB-UTM-20261102-0001');
        $response->assertSee('Kopi Tubruk');
        $response->assertSee('15.000');
        $response->assertSee('3.000');
    }

    public function test_11_checkout_with_variants_adds_price_adjustment_and_deducts_variant_ingredient_stock(): void
    {
        [$business, $user, $outlet] = $this->setupTenant();
        $this->openShift($business, $outlet);

        // 1. Raw material ingredient (Biji Kopi)
        $bijiKopi = $this->createProduct($business, [
            'name' => 'Biji Kopi Arabica (Bahan)',
            'sku' => 'RAW-BIJI-01',
            'is_sellable' => false,
            'stock' => 1000,
            'cost' => 150,
            'unit' => 'gr',
        ]);

        // 2. Variant Group & Option (Ekstra Shot)
        $group = VariantGroup::create([
            'business_id' => $business->id,
            'name' => 'Ekstra Shot',
            'selection_type' => 'single',
            'is_required' => false,
            'status' => 'active',
        ]);

        $option = VariantOption::create([
            'business_id' => $business->id,
            'variant_group_id' => $group->id,
            'name' => '1 Shot Espresso',
            'price_adjustment' => 5000,
            'cost_adjustment' => 2000,
            'ingredient_product_id' => $bijiKopi->id,
            'ingredient_quantity' => 15, // 15gr per shot
            'status' => 'active',
        ]);

        // 3. Sellable Product (Americano)
        $americano = $this->createProduct($business, [
            'name' => 'Americano',
            'sku' => 'AMR-01',
            'price' => 20000,
            'cost' => 5000,
            'stock' => 50,
            'is_sellable' => true,
        ]);
        $americano->variantGroups()->attach($group->id, ['business_id' => $business->id]);

        // 4. Checkout 2 Americano with Extra Shot
        $clientSaleId = (string) Str::uuid();
        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('pos.checkout'), [
                'client_sale_id' => $clientSaleId,
                'outlet_id' => $outlet->id,
                'payment_method' => 'cash',
                'cash_received' => 60000,
                'items' => [
                    [
                        'product_id' => $americano->id,
                        'quantity' => 2,
                        'variant_option_ids' => [$option->id],
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'sale' => [
                'subtotal' => 50000, // (20.000 + 5.000) * 2 = 50.000
                'total_amount' => 50000,
                'change_amount' => 10000,
            ],
        ]);

        // 5. Verify Sale & Sale Item snapshot
        $sale = Sale::where('sync_id', $clientSaleId)->firstOrFail();
        $this->assertSame(50000, (int) $sale->total_amount);
        $this->assertCount(1, $sale->items);

        $item = $sale->items->first();
        $this->assertStringContainsString('1 Shot Espresso', (string) $item->variant_summary);
        $this->assertIsArray($item->variants_snapshot);
        $this->assertCount(1, $item->variants_snapshot);
        $this->assertSame('1 Shot Espresso', $item->variants_snapshot[0]['option_name']);
        $this->assertSame(5000, $item->variants_snapshot[0]['price_adjustment']);
        $this->assertSame(25000, (int) $item->unit_price);
        $this->assertSame(50000, (int) $item->line_total);

        // 6. Verify main product stock deduction: 50 - 2 = 48
        $this->assertSame(48.0, (float) $americano->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $americano->id,
            'movement_type' => 'sale',
            'quantity_change' => -2.0,
        ]);

        // 7. Verify variant ingredient stock deduction: 1000 - (2 * 15) = 970
        $this->assertSame(970.0, (float) $bijiKopi->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $bijiKopi->id,
            'movement_type' => 'sale',
            'quantity_change' => -30.0,
        ]);
    }

    /**
     * @return array{0: Business, 1: User, 2: Outlet}
     */
    private function setupTenant(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Utama',
            'code' => 'UTM',
            'status' => 'active',
        ]);

        return [$business, $user, $outlet];
    }

    private function openShift(Business $business, Outlet $outlet): Shift
    {
        return Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHIFT-UTM-'.now()->format('Ymd').'-01',
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(Business $business, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'business_id' => $business->id,
            'name' => 'Produk Uji',
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'kind' => 'product',
            'is_sellable' => true,
            'price' => 20000,
            'cost' => 10000,
            'stock' => 10,
            'min_stock' => 2,
            'status' => 'active',
        ], $attributes));
    }
}
