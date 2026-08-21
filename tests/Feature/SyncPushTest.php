<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setupSyncEnvironment(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Main POS',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return compact('user', 'business', 'outlet', 'device', 'token');
    }

    public function test_push_new_category_and_product_with_resolved_category_sync_id(): void
    {
        $env = $this->setupSyncEnvironment();
        $catSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'categories' => [
                        [
                            'sync_id' => $catSyncId,
                            'base_sync_version' => null,
                            'name' => 'Beverages',
                            'status' => 'active',
                        ],
                    ],
                    'products' => [
                        [
                            'sync_id' => $prodSyncId,
                            'base_sync_version' => null,
                            'category_sync_id' => $catSyncId,
                            'name' => 'Iced Tea',
                            'sku' => 'TEA-01',
                            'price' => 15000,
                            'status' => 'active',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['duplicate' => false]]);

        $this->assertDatabaseHas('categories', [
            'business_id' => $env['business']->id,
            'sync_id' => $catSyncId,
            'name' => 'Beverages',
            'sync_version' => 1,
        ]);

        $category = Category::where('sync_id', $catSyncId)->first();
        $this->assertDatabaseHas('products', [
            'business_id' => $env['business']->id,
            'category_id' => $category->id,
            'sync_id' => $prodSyncId,
            'name' => 'Iced Tea',
            'sku' => 'TEA-01',
            'price' => 15000,
            'sync_version' => 1,
        ]);
    }

    public function test_push_full_transaction_flow_with_shift_sale_sale_item_expense_and_customer(): void
    {
        $env = $this->setupSyncEnvironment();
        $catSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();
        $custSyncId = (string) Str::uuid();
        $shiftSyncId = (string) Str::uuid();
        $saleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();
        $expSyncId = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'categories' => [
                        ['sync_id' => $catSyncId, 'base_sync_version' => null, 'name' => 'Food'],
                    ],
                    'products' => [
                        ['sync_id' => $prodSyncId, 'base_sync_version' => null, 'category_sync_id' => $catSyncId, 'name' => 'Burger', 'sku' => 'BGR-01', 'price' => 30000],
                    ],
                    'customers' => [
                        ['sync_id' => $custSyncId, 'base_sync_version' => null, 'name' => 'Alice', 'phone' => '0812345678'],
                    ],
                    'shifts' => [
                        ['sync_id' => $shiftSyncId, 'base_sync_version' => null, 'shift_number' => 'SHIFT-001', 'opening_cash' => 100000, 'opened_at' => '2026-08-21 08:00:00'],
                    ],
                    'sales' => [
                        [
                            'sync_id' => $saleSyncId,
                            'base_sync_version' => null,
                            'customer_sync_id' => $custSyncId,
                            'shift_sync_id' => $shiftSyncId,
                            'transaction_number' => 'TRX-SYNC-01',
                            'subtotal' => 30000,
                            'discount_amount' => 0,
                            'tax_amount' => 0,
                            'total_amount' => 30000,
                            'sold_at' => '2026-08-21 09:00:00',
                        ],
                    ],
                    'sale_items' => [
                        [
                            'sync_id' => $itemSyncId,
                            'base_sync_version' => null,
                            'sale_sync_id' => $saleSyncId,
                            'product_sync_id' => $prodSyncId,
                            'product_name' => 'Burger',
                            'product_sku' => 'BGR-01',
                            'unit_price' => 30000,
                            'quantity' => 1,
                            'line_total' => 30000,
                        ],
                    ],
                    'expenses' => [
                        [
                            'sync_id' => $expSyncId,
                            'base_sync_version' => null,
                            'shift_sync_id' => $shiftSyncId,
                            'description' => 'Ice Cube Purchase',
                            'amount' => 10000,
                            'occurred_at' => '2026-08-21 09:30:00',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        // Verify shift, sale, and expense are strictly bound to device outlet
        $this->assertDatabaseHas('shifts', [
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'sync_id' => $shiftSyncId,
            'shift_number' => 'SHIFT-001',
        ]);
        $this->assertDatabaseHas('sales', [
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'sync_id' => $saleSyncId,
            'transaction_number' => 'TRX-SYNC-01',
            'total_amount' => 30000,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'business_id' => $env['business']->id,
            'sync_id' => $itemSyncId,
            'line_total' => 30000,
        ]);
        $this->assertDatabaseHas('expenses', [
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'sync_id' => $expSyncId,
            'amount' => 10000,
        ]);
    }

    public function test_push_idempotency_prevents_duplicate_processing(): void
    {
        $env = $this->setupSyncEnvironment();
        $requestId = (string) Str::uuid();
        $catSyncId = (string) Str::uuid();

        $payload = [
            'business_id' => $env['business']->id,
            'device_identifier' => 'POS-01',
            'request_id' => $requestId,
            'changes' => [
                'categories' => [
                    ['sync_id' => $catSyncId, 'base_sync_version' => null, 'name' => 'Snacks'],
                ],
            ],
        ];

        // 1st request
        $res1 = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', $payload);

        $res1->assertStatus(200);
        $res1->assertJson(['data' => ['request_id' => $requestId, 'duplicate' => false]]);

        $this->assertDatabaseCount('categories', 1);

        // 2nd request with same request_id
        $res2 = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', $payload);

        $res2->assertStatus(200);
        $res2->assertJson(['data' => ['request_id' => $requestId, 'duplicate' => true]]);

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_optimistic_concurrency_conflict_returns_409(): void
    {
        $env = $this->setupSyncEnvironment();
        $catSyncId = (string) Str::uuid();

        $category = new Category([
            'name' => 'Original Name',
        ]);
        $category->business_id = $env['business']->id;
        $category->sync_id = $catSyncId;
        $category->save();

        // Server sync_version is 1
        $this->assertSame(1, $category->sync_version);

        // Client pushes with mismatched base_sync_version (e.g. 5)
        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'categories' => [
                        [
                            'sync_id' => $catSyncId,
                            'base_sync_version' => 5,
                            'name' => 'Updated Stale Name',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'Sync conflict.',
            'code' => 'SYNC_CONFLICT',
            'conflicts' => [
                [
                    'entity' => 'categories',
                    'sync_id' => $catSyncId,
                    'server_sync_version' => 1,
                ],
            ],
        ]);

        // Server record remains unchanged
        $this->assertSame('Original Name', $category->fresh()->name);
    }

    public function test_outlet_isolation_device_a_cannot_update_records_of_outlet_b(): void
    {
        $env = $this->setupSyncEnvironment();

        // Create Outlet B and a Shift in Outlet B
        $outletB = Outlet::factory()->create(['business_id' => $env['business']->id]);
        $shiftSyncId = (string) Str::uuid();
        $shiftB = new Shift([
            'shift_number' => 'SHIFT-OUT-B',
            'opened_at' => '2026-08-21 08:00:00',
        ]);
        $shiftB->business_id = $env['business']->id;
        $shiftB->outlet_id = $outletB->id;
        $shiftB->sync_id = $shiftSyncId;
        $shiftB->save();

        // Device A (attached to Outlet A) tries to push update to Shift B
        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'shifts' => [
                        [
                            'sync_id' => $shiftSyncId,
                            'base_sync_version' => 1,
                            'shift_number' => 'HIJACKED-SHIFT',
                            'opened_at' => '2026-08-21 08:00:00',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(409);
        $this->assertSame('SHIFT-OUT-B', $shiftB->fresh()->shift_number);
    }

    public function test_transaction_rollback_when_one_record_in_batch_fails(): void
    {
        $env = $this->setupSyncEnvironment();
        $catSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();
        $requestId = (string) Str::uuid();

        // Product references non-existent category_sync_id
        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => $requestId,
                'changes' => [
                    'categories' => [
                        ['sync_id' => $catSyncId, 'base_sync_version' => null, 'name' => 'Valid Category'],
                    ],
                    'products' => [
                        [
                            'sync_id' => $prodSyncId,
                            'base_sync_version' => null,
                            'category_sync_id' => (string) Str::uuid(), // Invalid reference
                            'name' => 'Invalid Product',
                            'sku' => 'INV-01',
                            'price' => 10000,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(409);

        // Entire transaction must rollback (no category created, no sync request created)
        $this->assertDatabaseMissing('categories', ['sync_id' => $catSyncId]);
        $this->assertDatabaseMissing('sync_requests', ['request_id' => $requestId]);
    }

    public function test_duplicate_sku_from_different_sync_id_returns_409_sync_data_conflict(): void
    {
        $env = $this->setupSyncEnvironment();

        Product::create([
            'business_id' => $env['business']->id,
            'name' => 'Product 1',
            'sku' => 'DUP-SKU-99',
            'price' => 10000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'products' => [
                        [
                            'sync_id' => (string) Str::uuid(),
                            'base_sync_version' => null,
                            'name' => 'Product 2',
                            'sku' => 'DUP-SKU-99',
                            'price' => 20000,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'Sync data conflict.',
            'code' => 'SYNC_DATA_CONFLICT',
        ]);
    }
}
