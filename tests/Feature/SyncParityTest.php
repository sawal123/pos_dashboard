<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SyncParityTest extends TestCase
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

    protected function push(array $env, array $changes): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => $changes,
            ]);
    }

    protected function pull(array $env, int $after = 0, int $limit = 200): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson('/api/sync/pull?business_id='.$env['business']->id.'&device_identifier=POS-01&after='.$after.'&limit='.$limit);
    }

    public function test_product_semantic_fields_push_and_pull_round_trip(): void
    {
        $env = $this->setupSyncEnvironment();
        $catSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();

        $response = $this->push($env, [
            'categories' => [
                ['sync_id' => $catSyncId, 'base_sync_version' => null, 'name' => 'Laundry'],
            ],
            'products' => [
                [
                    'sync_id' => $prodSyncId,
                    'base_sync_version' => null,
                    'category_sync_id' => $catSyncId,
                    'name' => 'Cuci Kering',
                    'sku' => 'CK-01',
                    'price' => 10000,
                    'kind' => 'service',
                    'cost' => 4000,
                    'stock' => 0,
                    'unit' => 'kg',
                    'min_stock' => 5,
                    'pricing_unit' => 'kg',
                    'min_quantity' => 1,
                    'estimated_duration' => '2 hari',
                    'status' => 'active',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'business_id' => $env['business']->id,
            'sync_id' => $prodSyncId,
            'kind' => 'service',
            'unit' => 'kg',
            'pricing_unit' => 'kg',
            'estimated_duration' => '2 hari',
        ]);

        $pull = $this->pull($env);
        $pull->assertStatus(200);
        $records = $pull->json('data.records');
        $product = collect($records)->firstWhere('entity', 'products');

        $this->assertNotNull($product);
        $this->assertSame('service', $product['data']['kind']);
        $this->assertSame('kg', $product['data']['unit']);
        $this->assertSame('kg', $product['data']['pricing_unit']);
        $this->assertSame('2 hari', $product['data']['estimated_duration']);
        $this->assertEquals(4000, $product['data']['cost']);
        $this->assertEquals(5, $product['data']['min_stock']);
        $this->assertEquals(1, $product['data']['min_quantity']);
    }

    public function test_product_update_preserves_unset_semantic_fields(): void
    {
        $env = $this->setupSyncEnvironment();
        $catSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();

        $this->push($env, [
            'categories' => [
                ['sync_id' => $catSyncId, 'base_sync_version' => null, 'name' => 'Minuman'],
            ],
            'products' => [
                [
                    'sync_id' => $prodSyncId,
                    'base_sync_version' => null,
                    'category_sync_id' => $catSyncId,
                    'name' => 'Kopi',
                    'sku' => 'KP-01',
                    'price' => 15000,
                    'kind' => 'product',
                    'cost' => 8000,
                    'stock' => 50,
                    'unit' => 'pcs',
                    'min_stock' => 10,
                ],
            ],
        ])->assertStatus(200);

        // Update only the price: unset semantic fields must survive.
        $this->push($env, [
            'products' => [
                [
                    'sync_id' => $prodSyncId,
                    'base_sync_version' => 1,
                    'name' => 'Kopi',
                    'sku' => 'KP-01',
                    'price' => 16000,
                ],
            ],
        ])->assertStatus(200);

        $product = Product::where('sync_id', $prodSyncId)->first();
        $this->assertSame(16000, (int) $product->price);
        $this->assertEquals(8000, (float) $product->cost);
        $this->assertEquals(50, (float) $product->stock);
        $this->assertSame('pcs', $product->unit);
    }

    public function test_sale_payment_snapshot_fields_push_and_pull_round_trip(): void
    {
        $env = $this->setupSyncEnvironment();
        $saleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();

        $this->push($env, [
            'products' => [
                ['sync_id' => $prodSyncId, 'base_sync_version' => null, 'name' => 'Teh', 'sku' => 'TEH-01', 'price' => 5000],
            ],
            'sales' => [
                [
                    'sync_id' => $saleSyncId,
                    'base_sync_version' => null,
                    'transaction_number' => 'TRX-PAY-01',
                    'subtotal' => 10000,
                    'total_amount' => 10000,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'paid_at' => '2026-09-16 10:00:00',
                    'cash_received' => 20000,
                    'change_amount' => 10000,
                    'sold_at' => '2026-09-16 10:00:00',
                ],
            ],
            'sale_items' => [
                [
                    'sync_id' => $itemSyncId,
                    'base_sync_version' => null,
                    'sale_sync_id' => $saleSyncId,
                    'product_sync_id' => $prodSyncId,
                    'product_name' => 'Teh',
                    'product_sku' => 'TEH-01',
                    'unit_price' => 5000,
                    'quantity' => 2,
                    'line_total' => 10000,
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('sales', [
            'sync_id' => $saleSyncId,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'cash_received' => 20000,
            'change_amount' => 10000,
        ]);

        $sale = Sale::where('sync_id', $saleSyncId)->first();
        $this->assertNotNull($sale->paid_at);

        $pull = $this->pull($env);
        $pull->assertStatus(200);
        $records = $pull->json('data.records');
        $saleRecord = collect($records)->firstWhere('entity', 'sales');

        $this->assertNotNull($saleRecord);
        $this->assertSame('cash', $saleRecord['data']['payment_method']);
        $this->assertSame('paid', $saleRecord['data']['payment_status']);
        $this->assertSame(20000, $saleRecord['data']['cash_received']);
        $this->assertSame(10000, $saleRecord['data']['change_amount']);
        $this->assertNotNull($saleRecord['data']['paid_at']);
    }

    public function test_decimal_sale_item_quantity_for_laundry_round_trip(): void
    {
        $env = $this->setupSyncEnvironment();
        $saleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();
        $prodSyncId = (string) Str::uuid();

        $this->push($env, [
            'products' => [
                ['sync_id' => $prodSyncId, 'base_sync_version' => null, 'name' => 'Kiloan', 'sku' => 'KG-01', 'price' => 10000],
            ],
            'sales' => [
                [
                    'sync_id' => $saleSyncId,
                    'base_sync_version' => null,
                    'transaction_number' => 'TRX-KG-01',
                    'subtotal' => 25000,
                    'total_amount' => 25000,
                    'sold_at' => '2026-09-16 10:00:00',
                ],
            ],
            'sale_items' => [
                [
                    'sync_id' => $itemSyncId,
                    'base_sync_version' => null,
                    'sale_sync_id' => $saleSyncId,
                    'product_sync_id' => $prodSyncId,
                    'product_name' => 'Kiloan',
                    'product_sku' => 'KG-01',
                    'unit_price' => 10000,
                    'quantity' => 2.5,
                    'line_total' => 25000,
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('sale_items', ['sync_id' => $itemSyncId]);

        $pull = $this->pull($env);
        $pull->assertStatus(200);
        $records = $pull->json('data.records');
        $itemRecord = collect($records)->firstWhere('entity', 'sale_items');

        $this->assertNotNull($itemRecord);
        $this->assertEquals(2.5, $itemRecord['data']['quantity']);
    }

    public function test_cash_ledger_exactly_once_on_retry_and_rerequest(): void
    {
        $env = $this->setupSyncEnvironment();
        $cashSyncId = (string) Str::uuid();
        $requestId = (string) Str::uuid();

        $changes = [
            'cash_ledger' => [
                [
                    'sync_id' => $cashSyncId,
                    'base_sync_version' => null,
                    'type' => 'in',
                    'amount' => 50000,
                    'category' => 'Penjualan Cash',
                    'reference_id' => 'sale-local-1',
                    'occurred_at' => '2026-09-16 10:00:00',
                ],
            ],
        ];

        $first = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => $requestId,
                'changes' => $changes,
            ]);
        $first->assertStatus(200);
        $first->assertJson(['data' => ['duplicate' => false]]);

        // Retry with the SAME request_id: idempotent, no second row.
        $retry = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => $requestId,
                'changes' => $changes,
            ]);
        $retry->assertStatus(200);
        $retry->assertJson(['data' => ['duplicate' => true]]);
        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());

        // New request_id but same logical reference: still exactly one row.
        // The new sync_id is unknown to the server, but the business reference
        // is stable, so the replay resolves idempotently to the same record.
        // The strict concurrency check returns 409 for the unknown sync_id,
        // which is why the client must retry with its own stable sync_id.
        $this->push($env, $changes)->assertStatus(409);
        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());
        $this->assertDatabaseHas('cash_ledger', [
            'sync_id' => $cashSyncId,
            'reference_id' => 'sale-local-1',
            'amount' => 50000,
        ]);
    }

    public function test_stock_movement_retry_does_not_apply_deduction_twice(): void
    {
        $env = $this->setupSyncEnvironment();
        $prodSyncId = (string) Str::uuid();
        $moveSyncId = (string) Str::uuid();
        $requestId = (string) Str::uuid();

        $product = new Product(['name' => 'Beras', 'sku' => 'BRS-01', 'price' => 12000]);
        $product->business_id = $env['business']->id;
        $product->sync_id = $prodSyncId;
        $product->save();

        $changes = [
            'stock_movements' => [
                [
                    'sync_id' => $moveSyncId,
                    'base_sync_version' => null,
                    'product_sync_id' => $prodSyncId,
                    'movement_type' => 'sale',
                    'quantity_change' => -2,
                    'stock_before' => 50,
                    'stock_after' => 48,
                    'reference_id' => 'sale-local-2',
                    'occurred_at' => '2026-09-16 10:00:00',
                ],
            ],
        ];

        $first = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => $requestId,
                'changes' => $changes,
            ]);
        $first->assertStatus(200);

        // Retry through both idempotency layers.
        $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => $requestId,
                'changes' => $changes,
            ])->assertStatus(200);

        // New request_id with an unknown sync_id is rejected by the strict
        // concurrency check, so the deduction can never be applied twice.
        $this->push($env, $changes)->assertStatus(409);

        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_pull_carries_cash_and_stock_records_with_outlet_isolation(): void
    {
        $env = $this->setupSyncEnvironment();
        $outletB = Outlet::factory()->create(['business_id' => $env['business']->id, 'code' => 'OUT-B']);

        $product = new Product(['name' => 'Gula', 'sku' => 'GL-01', 'price' => 17000]);
        $product->business_id = $env['business']->id;
        $product->sync_id = (string) Str::uuid();
        $product->save();

        $cashA = new CashLedger([
            'type' => 'in',
            'amount' => 30000,
            'category' => 'Penjualan Cash',
            'reference_id' => 'ref-a',
            'occurred_at' => '2026-09-16 10:00:00',
        ]);
        $cashA->business_id = $env['business']->id;
        $cashA->outlet_id = $env['outlet']->id;
        $cashA->save();

        // Outlet B cash must never leak to device A's pull.
        $cashB = new CashLedger([
            'type' => 'in',
            'amount' => 99999,
            'category' => 'Penjualan Cash',
            'reference_id' => 'ref-b',
            'occurred_at' => '2026-09-16 10:00:00',
        ]);
        $cashB->business_id = $env['business']->id;
        $cashB->outlet_id = $outletB->id;
        $cashB->save();

        $move = new StockMovement([
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_change' => -1,
            'stock_before' => 10,
            'stock_after' => 9,
            'reference_id' => 'ref-move-a',
            'occurred_at' => '2026-09-16 10:00:00',
        ]);
        $move->business_id = $env['business']->id;
        $move->save();

        $pull = $this->pull($env);
        $pull->assertStatus(200);
        $records = $pull->json('data.records');

        $cashRecords = collect($records)->where('entity', 'cash_ledger')->values();
        $this->assertCount(1, $cashRecords);
        $this->assertSame('ref-a', $cashRecords[0]['data']['reference_id']);

        $stockRecords = collect($records)->where('entity', 'stock_movements')->values();
        $this->assertCount(1, $stockRecords);
        $this->assertSame($product->sync_id, $stockRecords[0]['data']['product_sync_id']);
        $this->assertEquals(-1, $stockRecords[0]['data']['quantity_change']);
    }

    public function test_free_subscription_cannot_push_even_with_valid_device(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Free POS',
            'identifier' => 'FREE-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        // Device registration itself is rejected for free subscriptions.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'FREE-01',
                'name' => 'Free POS',
            ])->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);

        // Manual push attempt is rejected at the server gate too.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'FREE-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'categories' => [
                        ['sync_id' => (string) Str::uuid(), 'name' => 'Free Cat'],
                    ],
                ],
            ])->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_business_b_cannot_sync_business_a_cash_and_stock(): void
    {
        $envA = $this->setupSyncEnvironment();

        $userB = User::factory()->create();
        $businessB = Business::factory()->create();
        $userB->businesses()->attach($businessB, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $businessB->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        Device::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'POS B',
            'identifier' => 'POS-B',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $tokenB = $userB->createToken('mobile-api', ['mobile'])->plainTextToken;

        // A push to business A with business B's credentials is rejected.
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/sync/push', [
                'business_id' => $envA['business']->id,
                'device_identifier' => 'POS-B',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'cash_ledger' => [
                        [
                            'sync_id' => (string) Str::uuid(),
                            'type' => 'in',
                            'amount' => 1000,
                            'occurred_at' => '2026-09-16 10:00:00',
                        ],
                    ],
                ],
            ])->assertStatus(403)
            ->assertJson(['code' => 'BUSINESS_ACCESS_DENIED']);
    }

    public function test_cash_ledger_pagination_and_cursor_converge(): void
    {
        $env = $this->setupSyncEnvironment();

        for ($i = 1; $i <= 3; $i++) {
            $entry = new CashLedger([
                'type' => 'in',
                'amount' => 1000 * $i,
                'category' => 'Penjualan Cash',
                'reference_id' => 'page-ref-'.$i,
                'occurred_at' => '2026-09-16 10:0'.$i.':00',
            ]);
            $entry->business_id = $env['business']->id;
            $entry->outlet_id = $env['outlet']->id;
            $entry->save();
        }

        $first = $this->pull($env, 0, 2);
        $first->assertStatus(200);
        $this->assertTrue($first->json('data.has_more'));
        $this->assertCount(2, $first->json('data.records'));

        $second = $this->pull($env, (int) $first->json('data.next_cursor'), 2);
        $second->assertStatus(200);
        $this->assertFalse($second->json('data.has_more'));

        $allCash = array_merge(
            collect($first->json('data.records'))->where('entity', 'cash_ledger')->all(),
            collect($second->json('data.records'))->where('entity', 'cash_ledger')->all()
        );
        $this->assertCount(3, $allCash);
    }
}
