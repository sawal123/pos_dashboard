<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AddItemsToProcessingOrderTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(bool $taxEnabled = false, float $taxRate = 10.0): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create([
            'name' => 'Temuan Space',
            'business_type' => 'cafe',
            'tax_enabled' => $taxEnabled,
            'tax_rate' => $taxRate,
        ]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Cabang Utama',
            'code' => 'MAIN',
            'status' => 'active',
        ]);

        $shift = Shift::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_number' => 'SHF-'.strtoupper(Str::random(6)),
            'opened_by' => $owner->id,
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => now(),
            'sync_id' => (string) Str::uuid(),
        ]);

        return [$business, $owner, $outlet, $shift];
    }

    private function createSale(array $attributes): Sale
    {
        return Sale::create(array_merge([
            'customer_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(uniqid()),
            'status' => 'completed',
            'order_status' => 'Proses',
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'gross_profit' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => 0,
            'change_amount' => 0,
            'sold_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }

    private function createProduct(array $attributes): Product
    {
        return Product::create(array_merge([
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'cost' => 8000,
            'price' => 20000,
            'stock' => 20,
            'min_stock' => 0,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => 'pcs',
            'status' => 'active',
            'is_sellable' => true,
            'sync_id' => (string) Str::uuid(),
        ], $attributes));
    }

    public function test_can_add_items_to_processing_paid_cash_order_and_updates_stock_totals_and_cash(): void
    {
        [$business, $owner, $outlet, $shift] = $this->setupContext(taxEnabled: true, taxRate: 10.0);

        $p1 = $this->createProduct([
            'business_id' => $business->id,
            'name' => 'Americano',
            'price' => 20000,
            'cost' => 8000,
            'stock' => 20,
        ]);

        $p2 = $this->createProduct([
            'business_id' => $business->id,
            'name' => 'Croissant',
            'price' => 25000,
            'cost' => 10000,
            'stock' => 10,
        ]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-ADD-001',
            'status' => 'completed',
            'order_status' => 'Proses',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 20000,
            'discount_amount' => 0,
            'tax_amount' => 2000,
            'total_amount' => 22000,
            'gross_profit' => 12000,
            'cash_received' => 50000,
            'change_amount' => 28000,
        ]);

        SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'product_sku' => $p1->sku,
            'unit_price' => 20000,
            'quantity' => 1,
            'line_total' => 20000,
            'cost_snapshot' => 8000,
            'line_cost' => 8000,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => 'pcs',
        ]);

        // Add +1 Americano (merges) and +2 Croissant (new item)
        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('transactions.add-items', $sale), [
                'items' => [
                    ['product_id' => $p1->id, 'quantity' => 1],
                    ['product_id' => $p2->id, 'quantity' => 2],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', 90000)      // 2*20k + 2*25k = 90k
            ->assertJsonPath('data.tax_amount', 9000)     // 10% of 90k
            ->assertJsonPath('data.total_amount', 99000); // 99k

        $sale->refresh();
        $this->assertEquals(2, $sale->items()->count());
        $this->assertEquals(2.0, (float) $sale->items()->where('product_id', $p1->id)->first()->quantity);
        $this->assertEquals(2.0, (float) $sale->items()->where('product_id', $p2->id)->first()->quantity);

        // Stock deducted
        $this->assertEquals(19.0, (float) $p1->fresh()->stock);
        $this->assertEquals(8.0, (float) $p2->fresh()->stock);

        // Cash ledger recorded the added total (99000 - 22000 = 77000)
        $cashEntry = CashLedger::where('sale_sync_id', $sale->sync_id)->first();
        $this->assertNotNull($cashEntry);
        $this->assertEquals(77000, (int) $cashEntry->amount);
        $this->assertEquals('in', $cashEntry->type);
    }

    public function test_can_add_items_to_processing_unpaid_order_without_cash_ledger(): void
    {
        [$business, $owner, $outlet, $shift] = $this->setupContext();

        $p1 = $this->createProduct([
            'business_id' => $business->id,
            'name' => 'Latte',
            'price' => 25000,
            'cost' => 10000,
            'stock' => 15,
        ]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'transaction_number' => 'TRX-UNPAID-001',
            'status' => 'completed',
            'order_status' => 'Proses',
            'payment_method' => 'unpaid',
            'payment_status' => 'unpaid',
            'paid_at' => null,
            'subtotal' => 25000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 25000,
            'gross_profit' => 15000,
            'cash_received' => 0,
            'change_amount' => 0,
        ]);

        SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $p1->id,
            'product_name' => $p1->name,
            'product_sku' => $p1->sku,
            'unit_price' => 25000,
            'quantity' => 1,
            'line_total' => 25000,
            'cost_snapshot' => 10000,
            'line_cost' => 10000,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => 'pcs',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('transactions.add-items', $sale), [
                'items' => [
                    ['product_id' => $p1->id, 'quantity' => 2],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total_amount', 75000)
            ->assertJsonPath('data.payment_status_raw', 'unpaid');

        $this->assertEquals(0, CashLedger::where('sale_sync_id', $sale->sync_id)->count());
        $this->assertEquals(13.0, (float) $p1->fresh()->stock);
    }

    public function test_cannot_add_items_to_ready_or_cancelled_order(): void
    {
        [$business, $owner, $outlet, $shift] = $this->setupContext();

        $product = $this->createProduct([
            'business_id' => $business->id,
            'name' => 'Matcha',
            'price' => 22000,
            'stock' => 10,
        ]);

        $readySale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'status' => 'completed',
            'order_status' => 'Siap',
        ]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('transactions.add-items', $readySale), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $cancelledSale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'status' => 'cancelled',
            'order_status' => 'Batal',
        ]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('transactions.add-items', $cancelledSale), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_cashier_can_fetch_branch_priced_addable_products_and_add_items(): void
    {
        [$business, $owner, $outlet, $shift] = $this->setupContext();

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, [
            'role' => Business::ROLE_CASHIER,
            'outlet_id' => $outlet->id,
        ]);

        $product = $this->createProduct([
            'business_id' => $business->id,
            'name' => 'Kopi Susu',
            'price' => 18000,
            'cost' => 7000,
            'stock' => 25,
        ]);

        // Set outlet override price to 16000
        DB::table('outlet_product')->insert([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'price' => 16000,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sale = $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'shift_id' => $shift->id,
            'status' => 'completed',
            'order_status' => 'Proses',
            'payment_method' => 'qris',
            'payment_status' => 'paid',
            'subtotal' => 16000,
            'total_amount' => 16000,
        ]);

        SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 16000,
            'quantity' => 1,
            'line_total' => 16000,
            'cost_snapshot' => 7000,
            'line_cost' => 7000,
            'unit' => 'pcs',
            'kind' => 'product',
            'pricing_unit' => 'pcs',
        ]);

        // 1. Fetch addable products
        $catalogRes = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->getJson(route('transactions.addable-products', $sale));

        $catalogRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonPath('products.0.price', 16000);

        // 2. Add 1 more Kopi Susu using branch price 16000
        $addRes = $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('transactions.add-items', $sale), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        $addRes->assertOk()
            ->assertJsonPath('data.total_amount', 32000);
    }
}
