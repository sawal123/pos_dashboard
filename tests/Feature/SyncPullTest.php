<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPullTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPullEnvironment(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-A']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-B']);

        $deviceA = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device A',
            'identifier' => 'DEV-A',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => null,
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return compact('user', 'business', 'outletA', 'outletB', 'deviceA', 'token');
    }

    public function test_pull_initial_returns_business_wide_and_outlet_specific_data(): void
    {
        $env = $this->setupPullEnvironment();

        $category = Category::create([
            'business_id' => $env['business']->id,
            'name' => 'Coffee',
        ]);
        $product = Product::create([
            'business_id' => $env['business']->id,
            'category_id' => $category->id,
            'name' => 'Espresso',
            'sku' => 'ESP-01',
            'price' => 20000,
        ]);
        $customer = Customer::create([
            'business_id' => $env['business']->id,
            'name' => 'Bob',
        ]);
        $shiftA = Shift::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outletA']->id,
            'shift_number' => 'SHIFT-A-01',
            'opened_at' => now(),
        ]);
        $saleA = Sale::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outletA']->id,
            'customer_id' => $customer->id,
            'shift_id' => $shiftA->id,
            'transaction_number' => 'TRX-A-01',
            'subtotal' => 20000,
            'total_amount' => 20000,
            'sold_at' => now(),
        ]);
        $itemA = SaleItem::create([
            'business_id' => $env['business']->id,
            'sale_id' => $saleA->id,
            'product_id' => $product->id,
            'product_name' => 'Espresso',
            'product_sku' => 'ESP-01',
            'unit_price' => 20000,
            'quantity' => 1,
            'line_total' => 20000,
        ]);
        $expenseA = Expense::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outletA']->id,
            'shift_id' => $shiftA->id,
            'description' => 'Milk',
            'amount' => 15000,
            'occurred_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson("/api/sync/pull?business_id={$env['business']->id}&device_identifier=DEV-A&after=0");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'records',
                'next_cursor',
                'server_sequence',
                'has_more',
            ],
        ]);

        $records = $response->json('data.records');
        $this->assertCount(7, $records);

        // Check reference resolution in pull payload
        $productRecord = collect($records)->firstWhere('entity', 'products');
        $this->assertSame($category->sync_id, $productRecord['data']['category_sync_id']);

        $saleRecord = collect($records)->firstWhere('entity', 'sales');
        $this->assertSame($customer->sync_id, $saleRecord['data']['customer_sync_id']);
        $this->assertSame($shiftA->sync_id, $saleRecord['data']['shift_sync_id']);

        $itemRecord = collect($records)->firstWhere('entity', 'sale_items');
        $this->assertSame($saleA->sync_id, $itemRecord['data']['sale_sync_id']);
        $this->assertSame($product->sync_id, $itemRecord['data']['product_sync_id']);

        $expRecord = collect($records)->firstWhere('entity', 'expenses');
        $this->assertSame($shiftA->sync_id, $expRecord['data']['shift_sync_id']);

        $this->assertNotNull($env['deviceA']->fresh()->last_seen_at);
    }

    public function test_pull_strictly_isolates_records_from_other_outlets(): void
    {
        $env = $this->setupPullEnvironment();

        // Create Outlet B operational data
        $shiftB = Shift::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outletB']->id,
            'shift_number' => 'SHIFT-B-99',
            'opened_at' => now(),
        ]);
        $saleB = Sale::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outletB']->id,
            'transaction_number' => 'TRX-B-99',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'sold_at' => now(),
        ]);
        $productB = Product::create([
            'business_id' => $env['business']->id,
            'name' => 'Secret Dish',
            'sku' => 'SEC-01',
            'price' => 50000,
        ]);
        SaleItem::create([
            'business_id' => $env['business']->id,
            'sale_id' => $saleB->id,
            'product_id' => $productB->id,
            'product_name' => 'Secret Dish',
            'product_sku' => 'SEC-01',
            'unit_price' => 50000,
            'quantity' => 1,
            'line_total' => 50000,
        ]);
        Expense::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outletB']->id,
            'description' => 'Outlet B Expense',
            'amount' => 50000,
            'occurred_at' => now(),
        ]);

        // Device A (attached to Outlet A) pulls changes
        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson("/api/sync/pull?business_id={$env['business']->id}&device_identifier=DEV-A&after=0");

        $response->assertStatus(200);
        $records = $response->json('data.records');

        // No Outlet B shifts, sales, sale_items, or expenses should be present
        $this->assertEmpty(collect($records)->where('entity', 'shifts'));
        $this->assertEmpty(collect($records)->where('entity', 'sales'));
        $this->assertEmpty(collect($records)->where('entity', 'sale_items'));
        $this->assertEmpty(collect($records)->where('entity', 'expenses'));
    }

    public function test_pull_pagination_with_monotonic_cursor_and_limit(): void
    {
        $env = $this->setupPullEnvironment();

        for ($i = 1; $i <= 5; $i++) {
            Category::create([
                'business_id' => $env['business']->id,
                'name' => "Category {$i}",
            ]);
        }

        // Pull with limit=2
        $res1 = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson("/api/sync/pull?business_id={$env['business']->id}&device_identifier=DEV-A&after=0&limit=2");

        $res1->assertStatus(200);
        $this->assertCount(2, $res1->json('data.records'));
        $this->assertTrue($res1->json('data.has_more'));
        $cursor1 = $res1->json('data.next_cursor');

        // Pull next page with after=$cursor1 and limit=2
        $res2 = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson("/api/sync/pull?business_id={$env['business']->id}&device_identifier=DEV-A&after={$cursor1}&limit=2");

        $res2->assertStatus(200);
        $this->assertCount(2, $res2->json('data.records'));
        $this->assertTrue($res2->json('data.has_more'));
        $cursor2 = $res2->json('data.next_cursor');

        // Pull final page
        $res3 = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson("/api/sync/pull?business_id={$env['business']->id}&device_identifier=DEV-A&after={$cursor2}&limit=2");

        $res3->assertStatus(200);
        $this->assertCount(1, $res3->json('data.records'));
        $this->assertFalse($res3->json('data.has_more'));
        $this->assertSame($res3->json('data.server_sequence'), $res3->json('data.next_cursor'));
    }
}
