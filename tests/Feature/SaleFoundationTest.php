<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SaleFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // A. SALE FOUNDATION
    // ==========================================

    public function test_sale_can_be_created_for_valid_business_and_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-001',
            'status' => 'completed',
            'subtotal' => 50000,
            'discount_amount' => 5000,
            'tax_amount' => 4500,
            'total_amount' => 49500,
            'sold_at' => now(),
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-001',
            'total_amount' => 49500,
        ]);
        $this->assertNotNull($sale->id);
    }

    public function test_sale_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-001',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->assertNotNull($sale->business);
        $this->assertSame($business->id, $sale->business->id);
    }

    public function test_sale_belongs_to_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-001',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->assertNotNull($sale->outlet);
        $this->assertSame($outlet->id, $sale->outlet->id);
    }

    public function test_business_can_have_multiple_sales(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale1 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-001',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $sale2 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-002',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);

        $this->assertCount(2, $business->sales);
        $this->assertTrue($business->sales->contains($sale1));
        $this->assertTrue($business->sales->contains($sale2));
    }

    public function test_outlet_can_have_multiple_sales(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale1 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-001',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $sale2 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-002',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);

        $this->assertCount(2, $outlet->sales);
        $this->assertTrue($outlet->sales->contains($sale1));
        $this->assertTrue($outlet->sales->contains($sale2));
    }

    public function test_customer_can_be_null_for_walk_in_sale(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => null,
            'transaction_number' => 'TRX-WALKIN',
            'subtotal' => 15000,
            'total_amount' => 15000,
            'sold_at' => now(),
        ]);

        $this->assertNull($sale->customer_id);
        $this->assertNull($sale->customer);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'customer_id' => null]);
    }

    public function test_sale_can_use_customer_from_same_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = Customer::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'TRX-CUST',
            'subtotal' => 25000,
            'total_amount' => 25000,
            'sold_at' => now(),
        ]);

        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertNotNull($sale->customer);
        $this->assertSame($customer->id, $sale->customer->id);
    }

    public function test_customer_can_have_multiple_sales(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = Customer::factory()->create(['business_id' => $business->id]);

        $sale1 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'TRX-001',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $sale2 = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'TRX-002',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);

        $this->assertCount(2, $customer->sales);
        $this->assertTrue($customer->sales->contains($sale1));
        $this->assertTrue($customer->sales->contains($sale2));
    }

    // ==========================================
    // B. TENANT ISOLATION
    // ==========================================

    public function test_sales_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-01',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $saleB = Sale::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-01',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->assertCount(1, $businessA->sales);
        $this->assertTrue($businessA->sales->contains($saleA));
        $this->assertFalse($businessA->sales->contains($saleB));

        $this->assertCount(1, $businessB->sales);
        $this->assertTrue($businessB->sales->contains($saleB));
        $this->assertFalse($businessB->sales->contains($saleA));
    }

    public function test_sale_of_business_a_cannot_use_outlet_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(QueryException::class);

        Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-CROSS-OUTLET',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
    }

    public function test_sale_of_business_a_cannot_use_customer_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $customerB = Customer::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(QueryException::class);

        Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'customer_id' => $customerB->id,
            'transaction_number' => 'TRX-CROSS-CUST',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
    }

    // ==========================================
    // C. TRANSACTION NUMBER
    // ==========================================

    public function test_transaction_number_must_be_unique_within_same_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DUP',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DUP',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);
    }

    public function test_same_transaction_number_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-SHARED',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $saleB = Sale::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-SHARED',
            'subtotal' => 15000,
            'total_amount' => 15000,
            'sold_at' => now(),
        ]);

        $this->assertDatabaseHas('sales', ['id' => $saleA->id, 'business_id' => $businessA->id]);
        $this->assertDatabaseHas('sales', ['id' => $saleB->id, 'business_id' => $businessB->id]);
    }

    // ==========================================
    // D. STATUS / MONEY
    // ==========================================

    public function test_default_status_of_sale_is_completed(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEFAULT-STATUS',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->assertSame('completed', $sale->status);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'completed']);
    }

    public function test_void_status_sale_can_be_stored(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-VOID',
            'status' => 'void',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->assertSame('void', $sale->status);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'void']);
    }

    public function test_subtotal_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-SUB',
            'subtotal' => 125000,
            'total_amount' => 125000,
            'sold_at' => now(),
        ]);

        $this->assertIsInt($sale->subtotal);
        $this->assertSame(125000, $sale->subtotal);
    }

    public function test_discount_amount_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DISC',
            'subtotal' => 50000,
            'discount_amount' => 5000,
            'total_amount' => 45000,
            'sold_at' => now(),
        ]);

        $this->assertIsInt($sale->discount_amount);
        $this->assertSame(5000, $sale->discount_amount);
    }

    public function test_tax_amount_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-TAX',
            'subtotal' => 50000,
            'tax_amount' => 5500,
            'total_amount' => 55500,
            'sold_at' => now(),
        ]);

        $this->assertIsInt($sale->tax_amount);
        $this->assertSame(5500, $sale->tax_amount);
    }

    public function test_total_amount_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-TOT',
            'subtotal' => 100000,
            'discount_amount' => 10000,
            'tax_amount' => 9900,
            'total_amount' => 99900,
            'sold_at' => now(),
        ]);

        $this->assertIsInt($sale->total_amount);
        $this->assertSame(99900, $sale->total_amount);
    }

    public function test_sold_at_is_cast_to_datetime(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $now = Carbon::parse('2026-08-21 14:00:00');

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-TIME',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => $now,
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $sale->sold_at);
        $this->assertSame('2026-08-21 14:00:00', $sale->sold_at->format('Y-m-d H:i:s'));
    }

    // ==========================================
    // E. SALE ITEM
    // ==========================================

    public function test_sale_can_have_multiple_sale_items(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product1 = Product::factory()->create(['business_id' => $business->id, 'price' => 10000]);
        $product2 = Product::factory()->create(['business_id' => $business->id, 'price' => 15000]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-ITEMS',
            'subtotal' => 25000,
            'total_amount' => 25000,
            'sold_at' => now(),
        ]);

        $item1 = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product1->id,
            'product_name' => $product1->name,
            'product_sku' => $product1->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $item2 = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product2->id,
            'product_name' => $product2->name,
            'product_sku' => $product2->sku,
            'unit_price' => 15000,
            'quantity' => 1,
            'line_total' => 15000,
        ]);

        $this->assertCount(2, $sale->items);
        $this->assertTrue($sale->items->contains($item1));
        $this->assertTrue($sale->items->contains($item2));
    }

    public function test_sale_item_belongs_to_sale(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-ITEM-SALE',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertNotNull($item->sale);
        $this->assertSame($sale->id, $item->sale->id);
    }

    public function test_sale_item_belongs_to_product(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-ITEM-PROD',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertNotNull($item->product);
        $this->assertSame($product->id, $item->product->id);
    }

    public function test_sale_item_must_belong_to_same_business_as_sale(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-SAME-BIZ-SALE',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertSame($sale->business_id, $item->business_id);
    }

    public function test_sale_item_must_belong_to_same_business_as_product(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-SAME-BIZ-PROD',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertSame($product->business_id, $item->business_id);
    }

    public function test_sale_item_of_business_a_cannot_use_sale_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $productA = Product::factory()->create(['business_id' => $businessA->id]);

        $saleB = Sale::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-SALE-B',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        SaleItem::create([
            'business_id' => $businessA->id,
            'sale_id' => $saleB->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'product_sku' => $productA->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);
    }

    public function test_sale_item_of_business_a_cannot_use_product_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $productB = Product::factory()->create(['business_id' => $businessB->id]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-SALE-A',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        SaleItem::create([
            'business_id' => $businessA->id,
            'sale_id' => $saleA->id,
            'product_id' => $productB->id,
            'product_name' => $productB->name,
            'product_sku' => $productB->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);
    }

    // ==========================================
    // F. PRODUCT SNAPSHOT
    // ==========================================

    public function test_sale_item_stores_product_name_snapshot(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id, 'name' => 'Kopi Susu Original']);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-NAME-SNAP',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertSame('Kopi Susu Original', $item->product_name);
    }

    public function test_sale_item_stores_product_sku_snapshot(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id, 'sku' => 'KOPI-ORI-01']);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-SKU-SNAP',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertSame('KOPI-ORI-01', $item->product_sku);
    }

    public function test_sale_item_stores_unit_price_snapshot(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id, 'price' => 18000]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-PRICE-SNAP',
            'subtotal' => 18000,
            'total_amount' => 18000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 18000,
            'quantity' => 1,
            'line_total' => 18000,
        ]);

        $this->assertSame(18000, $item->unit_price);
    }

    public function test_updating_product_does_not_alter_historical_sale_item_snapshot(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Regular',
            'sku' => 'KOP-REG',
            'price' => 10000,
        ]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-HIST-SNAP',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => $product->price,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        // Product updated in database
        $product->update([
            'name' => 'Kopi Premium Max',
            'sku' => 'KOP-PREM',
            'price' => 25000,
        ]);

        $refreshedItem = $item->fresh();
        $this->assertSame('Kopi Regular', $refreshedItem->product_name);
        $this->assertSame('KOP-REG', $refreshedItem->product_sku);
        $this->assertSame(10000, $refreshedItem->unit_price);
    }

    // ==========================================
    // G. QUANTITY & TOTALS
    // ==========================================

    public function test_quantity_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-QTY',
            'subtotal' => 30000,
            'total_amount' => 30000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 3,
            'line_total' => 30000,
        ]);

        $this->assertIsInt($item->quantity);
        $this->assertSame(3, $item->quantity);
    }

    public function test_line_total_is_stored_as_integer(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-LINE',
            'subtotal' => 45000,
            'total_amount' => 45000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 15000,
            'quantity' => 3,
            'line_total' => 45000,
        ]);

        $this->assertIsInt($item->line_total);
        $this->assertSame(45000, $item->line_total);
    }

    // ==========================================
    // H. DELETE SAFETY
    // ==========================================

    public function test_deleting_sale_cascades_and_deletes_its_sale_items(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEL-SALE',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->assertDatabaseHas('sale_items', ['id' => $item->id]);

        $sale->delete();

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('sale_items', ['id' => $item->id]);
    }

    public function test_deleting_product_referenced_by_sale_item_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEL-PROD',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $this->expectException(QueryException::class);

        $product->delete();
    }

    public function test_deleting_outlet_with_sales_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEL-OUTLET',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $outlet->delete();
    }

    public function test_deleting_customer_with_sales_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $customer = Customer::factory()->create(['business_id' => $business->id]);

        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'TRX-DEL-CUST',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $customer->delete();
    }

    public function test_deleting_business_a_cleans_up_sales_and_sale_items(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create(['business_id' => $business->id]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-DEL-BIZ',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $item = SaleItem::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $business->delete();

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('sale_items', ['id' => $item->id]);
    }

    public function test_deleting_business_a_does_not_delete_sales_or_sale_items_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $productA = Product::factory()->create(['business_id' => $businessA->id]);
        $productB = Product::factory()->create(['business_id' => $businessB->id]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'transaction_number' => 'TRX-A',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $itemA = SaleItem::create([
            'business_id' => $businessA->id,
            'sale_id' => $saleA->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'product_sku' => $productA->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $saleB = Sale::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'transaction_number' => 'TRX-B',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);
        $itemB = SaleItem::create([
            'business_id' => $businessB->id,
            'sale_id' => $saleB->id,
            'product_id' => $productB->id,
            'product_name' => $productB->name,
            'product_sku' => $productB->sku,
            'unit_price' => 20000,
            'quantity' => 1,
            'line_total' => 20000,
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('sales', ['id' => $saleA->id]);
        $this->assertDatabaseMissing('sale_items', ['id' => $itemA->id]);

        $this->assertDatabaseHas('sales', ['id' => $saleB->id]);
        $this->assertDatabaseHas('sale_items', ['id' => $itemB->id]);
    }

    public function test_deleting_business_cleans_all_tenant_sales_dependencies(): void
    {
        $businessA = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $customerA = Customer::factory()->create(['business_id' => $businessA->id]);
        $productA = Product::factory()->create(['business_id' => $businessA->id]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'customer_id' => $customerA->id,
            'transaction_number' => 'TRX-FULL-A',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);
        $itemA = SaleItem::create([
            'business_id' => $businessA->id,
            'sale_id' => $saleA->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'product_sku' => $productA->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $customerB = Customer::factory()->create(['business_id' => $businessB->id]);
        $productB = Product::factory()->create(['business_id' => $businessB->id]);

        $saleB = Sale::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'customer_id' => $customerB->id,
            'transaction_number' => 'TRX-FULL-B',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);
        $itemB = SaleItem::create([
            'business_id' => $businessB->id,
            'sale_id' => $saleB->id,
            'product_id' => $productB->id,
            'product_name' => $productB->name,
            'product_sku' => $productB->sku,
            'unit_price' => 20000,
            'quantity' => 1,
            'line_total' => 20000,
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $businessA->id]);
        $this->assertDatabaseMissing('outlets', ['id' => $outletA->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customerA->id]);
        $this->assertDatabaseMissing('products', ['id' => $productA->id]);
        $this->assertDatabaseMissing('sales', ['id' => $saleA->id]);
        $this->assertDatabaseMissing('sale_items', ['id' => $itemA->id]);

        $this->assertDatabaseHas('businesses', ['id' => $businessB->id]);
        $this->assertDatabaseHas('outlets', ['id' => $outletB->id]);
        $this->assertDatabaseHas('customers', ['id' => $customerB->id]);
        $this->assertDatabaseHas('products', ['id' => $productB->id]);
        $this->assertDatabaseHas('sales', ['id' => $saleB->id]);
        $this->assertDatabaseHas('sale_items', ['id' => $itemB->id]);
    }
}
