<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P38 — multi-device data integrity, conflict and recovery hardening.
 *
 * Every test drives the real sync HTTP contract with raw payloads (no mobile
 * code path involved) so the server-side guarantees are asserted directly.
 */
class SyncIntegrityP38Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, business: Business, outlet: Outlet, token: string}
     */
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

    protected function secondDevice(array $env, string $identifier = 'POS-02'): void
    {
        Device::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'name' => 'Second POS',
            'identifier' => $identifier,
            'status' => 'active',
            'registered_at' => now(),
        ]);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $changes
     */
    protected function pushAs(array $env, string $deviceIdentifier, array $changes, ?string $requestId = null): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => $deviceIdentifier,
                'request_id' => $requestId ?? (string) Str::uuid(),
                'changes' => $changes,
            ]);
    }

    protected function makeProduct(array $env, string $syncId, float $stock, int $price = 10000): Product
    {
        $product = new Product([
            'name' => 'Produk '.substr($syncId, 0, 4),
            'sku' => 'SKU-'.substr($syncId, 0, 8),
            'price' => $price,
            'stock' => $stock,
        ]);
        $product->business_id = $env['business']->id;
        $product->sync_id = $syncId;
        $product->save();

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    protected function movement(string $syncId, string $productSyncId, float $delta, float $before, float $after, ?string $reference = null, ?string $saleSyncId = null): array
    {
        $movement = [
            'sync_id' => $syncId,
            'base_sync_version' => null,
            'product_sync_id' => $productSyncId,
            'movement_type' => 'sale',
            'quantity_change' => $delta,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_id' => $reference,
            'occurred_at' => '2026-09-17 10:00:00',
        ];

        if ($saleSyncId !== null) {
            $movement['sale_sync_id'] = $saleSyncId;
        }

        return $movement;
    }

    /**
     * @return array<string, mixed>
     */
    protected function cashEntry(string $syncId, int $amount, string $saleSyncId, string $reference): array
    {
        return [
            'sync_id' => $syncId,
            'base_sync_version' => null,
            'type' => 'in',
            'amount' => $amount,
            'category' => 'Penjualan Cash',
            'reference_id' => $reference,
            'sale_sync_id' => $saleSyncId,
            'occurred_at' => '2026-09-17 10:00:00',
        ];
    }

    public function test_concurrent_stock_deltas_converge_without_lost_update(): void
    {
        $env = $this->setupSyncEnvironment();
        $this->secondDevice($env);

        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($env, $prodSyncId, 10);

        $moveA = (string) Str::uuid();
        $moveB = (string) Str::uuid();

        // Device A sells 3, device B sells 4. Both pulled stock 10 offline, so
        // both client snapshots claim their own stock_after (7 and 6).
        $this->pushAs($env, 'POS-01', [
            'stock_movements' => [$this->movement($moveA, $prodSyncId, -3, 10, 7, 'sale-a')],
        ])->assertStatus(200);

        $this->pushAs($env, 'POS-02', [
            'stock_movements' => [$this->movement($moveB, $prodSyncId, -4, 10, 6, 'sale-b')],
        ])->assertStatus(200);

        // 10 - 3 - 4 = 3. Not 6, not 7.
        $this->assertEquals(3, (float) Product::where('sync_id', $prodSyncId)->first()->stock);
        $this->assertSame(2, StockMovement::where('business_id', $env['business']->id)->count());

        // A retry of the very same movement sync_id must not apply the delta again.
        $this->pushAs($env, 'POS-01', [
            'stock_movements' => [$this->movement($moveA, $prodSyncId, -3, 10, 7, 'sale-a')],
        ])->assertStatus(200);

        $this->assertEquals(3, (float) Product::where('sync_id', $prodSyncId)->first()->stock);
        $this->assertSame(2, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_stock_movement_treats_quantity_change_as_authoritative_delta(): void
    {
        $env = $this->setupSyncEnvironment();
        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($env, $prodSyncId, 10);

        $moveSyncId = (string) Str::uuid();

        // A deliberately wrong client stock_after (1) must not be trusted.
        $this->pushAs($env, 'POS-01', [
            'stock_movements' => [$this->movement($moveSyncId, $prodSyncId, -3, 10, 1, 'sale-x')],
        ])->assertStatus(200);

        $this->assertEquals(7, (float) Product::where('sync_id', $prodSyncId)->first()->stock);

        // Historical evidence stays exactly as the device reported it.
        $movement = StockMovement::where('sync_id', $moveSyncId)->first();
        $this->assertEquals(1, (float) $movement->stock_after);
        $this->assertEquals(10, (float) $movement->stock_before);
        $this->assertEquals(-3, (float) $movement->quantity_change);
    }

    public function test_product_snapshot_cannot_override_movement_driven_stock(): void
    {
        $env = $this->setupSyncEnvironment();
        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($env, $prodSyncId, 10);

        $this->pushAs($env, 'POS-01', [
            'products' => [
                [
                    'sync_id' => $prodSyncId,
                    'base_sync_version' => 1,
                    'name' => 'Produk Rename',
                    'sku' => 'SKU-'.substr($prodSyncId, 0, 8),
                    'price' => 12000,
                    'stock' => 1,
                    'status' => 'active',
                ],
            ],
        ])->assertStatus(200);

        $product = Product::where('sync_id', $prodSyncId)->first();
        $this->assertSame('Produk Rename', $product->name);
        // The stale absolute snapshot is not the stock authority.
        $this->assertEquals(10, (float) $product->stock);
    }

    public function test_negative_stock_delta_returns_explicit_reconciliation_state(): void
    {
        $env = $this->setupSyncEnvironment();
        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($env, $prodSyncId, 5);

        $response = $this->pushAs($env, 'POS-01', [
            'stock_movements' => [$this->movement((string) Str::uuid(), $prodSyncId, -6, 5, 0, 'sale-over')],
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'code' => 'STOCK_RECONCILIATION_REQUIRED',
            'state' => 'STOCK_RECONCILIATION_REQUIRED',
        ]);

        // Nothing is deleted and nothing is silently resolved.
        $this->assertEquals(5, (float) Product::where('sync_id', $prodSyncId)->first()->stock);
        $this->assertSame(0, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_concurrent_cash_settlement_for_one_sale_is_exactly_once(): void
    {
        $env = $this->setupSyncEnvironment();
        $this->secondDevice($env);

        $saleSyncId = (string) Str::uuid();
        $this->seedUnpaidLaundrySale($env, $saleSyncId);

        // Both devices settled the same logical order offline: same logical
        // sale, different local sync ids and different local references.
        $first = $this->pushAs($env, 'POS-01', [
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), 25000, $saleSyncId, 'sale-local-a')],
        ]);
        $first->assertStatus(200);

        $second = $this->pushAs($env, 'POS-02', [
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), 25000, $saleSyncId, 'sale-local-b')],
        ]);
        $second->assertStatus(200);

        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());
        $this->assertDatabaseHas('cash_ledger', [
            'business_id' => $env['business']->id,
            'sale_sync_id' => $saleSyncId,
            'amount' => 25000,
        ]);
    }

    public function test_cash_settlement_with_different_amount_is_deterministic_conflict(): void
    {
        $env = $this->setupSyncEnvironment();
        $this->secondDevice($env);

        $saleSyncId = (string) Str::uuid();
        $this->seedUnpaidLaundrySale($env, $saleSyncId);

        $this->pushAs($env, 'POS-01', [
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), 25000, $saleSyncId, 'sale-local-a')],
        ])->assertStatus(200);

        $conflict = $this->pushAs($env, 'POS-02', [
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), 20000, $saleSyncId, 'sale-local-b')],
        ]);

        $conflict->assertStatus(409);
        $conflict->assertJson(['code' => 'SYNC_CONFLICT']);

        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());
        $this->assertSame(25000, (int) CashLedger::where('sale_sync_id', $saleSyncId)->value('amount'));
    }

    public function test_laundry_lifecycle_regression_from_stale_device_is_rejected(): void
    {
        $env = $this->setupSyncEnvironment();
        $this->secondDevice($env);

        $saleSyncId = (string) Str::uuid();
        $this->seedUnpaidLaundrySale($env, $saleSyncId);

        // Device A advances Masuk -> Diproses (version 2 on the server).
        $this->pushAs($env, 'POS-01', [
            'sales' => [$this->salePayload($saleSyncId, 'Diproses', 1)],
        ])->assertStatus(200);

        $this->assertSame('Diproses', Sale::where('sync_id', $saleSyncId)->value('order_status'));

        // Stale device B still believes the order is Masuk and pushes a
        // different lifecycle mutation with its stale base version.
        $stale = $this->pushAs($env, 'POS-02', [
            'sales' => [$this->salePayload($saleSyncId, 'Masuk', 1, 'LDR-STALE-EDIT')],
        ]);

        $stale->assertStatus(409);
        $stale->assertJson(['code' => 'SYNC_CONFLICT']);

        // No regression, no silent overwrite.
        $sale = Sale::where('sync_id', $saleSyncId)->first();
        $this->assertSame('Diproses', $sale->order_status);
        $this->assertSame('LDR-20260917-0001', $sale->transaction_number);
    }

    public function test_equivalent_mutation_auto_resolves_without_conflict(): void
    {
        $env = $this->setupSyncEnvironment();

        $saleSyncId = (string) Str::uuid();
        $this->seedUnpaidLaundrySale($env, $saleSyncId);

        $sale = Sale::where('sync_id', $saleSyncId)->first();
        $version = (int) $sale->sync_version;

        // Replay of a mutation the server already holds: identical payload,
        // stale base version. It must resolve as equivalent, not as conflict.
        $response = $this->pushAs($env, 'POS-01', [
            'sales' => [$this->salePayload($saleSyncId, 'Masuk', 1)],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['duplicate' => false]]);

        $this->assertSame($version, (int) Sale::where('sync_id', $saleSyncId)->first()->sync_version);
    }

    public function test_concurrent_master_edit_conflict_is_deterministic_and_non_destructive(): void
    {
        $env = $this->setupSyncEnvironment();
        $this->secondDevice($env);

        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($env, $prodSyncId, 10);

        // Device A edits price first and wins.
        $this->pushAs($env, 'POS-01', [
            'products' => [[
                'sync_id' => $prodSyncId,
                'base_sync_version' => 1,
                'name' => 'Produk '.substr($prodSyncId, 0, 4),
                'sku' => 'SKU-'.substr($prodSyncId, 0, 8),
                'price' => 16000,
                'status' => 'active',
            ]],
        ])->assertStatus(200);

        // Device B edits a different value from the same base version.
        $loser = $this->pushAs($env, 'POS-02', [
            'products' => [[
                'sync_id' => $prodSyncId,
                'base_sync_version' => 1,
                'name' => 'Produk '.substr($prodSyncId, 0, 4),
                'sku' => 'SKU-'.substr($prodSyncId, 0, 8),
                'price' => 17000,
                'status' => 'active',
            ]],
        ]);

        $loser->assertStatus(409);
        $loser->assertJson(['code' => 'SYNC_CONFLICT']);

        // Deterministic: the first committed value survives, nothing is
        // dropped and no last-write-wins occurred.
        $this->assertSame(16000, (int) Product::where('sync_id', $prodSyncId)->value('price'));
    }

    public function test_duplicate_http_retry_is_exactly_once_across_all_entities(): void
    {
        $env = $this->setupSyncEnvironment();

        $prodSyncId = (string) Str::uuid();
        $saleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();
        $moveSyncId = (string) Str::uuid();
        $cashSyncId = (string) Str::uuid();
        $requestId = (string) Str::uuid();

        $this->makeProduct($env, $prodSyncId, 10);

        $changes = [
            'sales' => [[
                'sync_id' => $saleSyncId,
                'base_sync_version' => null,
                'transaction_number' => 'TRX-RETRY-01',
                'status' => 'paid',
                'subtotal' => 30000,
                'total_amount' => 30000,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'paid_at' => '2026-09-17 10:00:00',
                'sold_at' => '2026-09-17 10:00:00',
            ]],
            'sale_items' => [[
                'sync_id' => $itemSyncId,
                'base_sync_version' => null,
                'sale_sync_id' => $saleSyncId,
                'product_sync_id' => $prodSyncId,
                'product_name' => 'Produk',
                'product_sku' => 'SKU-RETRY',
                'unit_price' => 10000,
                'quantity' => 3,
                'line_total' => 30000,
            ]],
            'stock_movements' => [
                $this->movement($moveSyncId, $prodSyncId, -3, 10, 7, 'sale-retry', $saleSyncId),
            ],
            'cash_ledger' => [
                $this->cashEntry($cashSyncId, 30000, $saleSyncId, 'sale-retry'),
            ],
        ];

        $first = $this->pushAs($env, 'POS-01', $changes, $requestId);
        $first->assertStatus(200);
        $first->assertJson(['data' => ['duplicate' => false]]);

        // Lost response: the client retries the exact same request_id.
        $retry = $this->pushAs($env, 'POS-01', $changes, $requestId);
        $retry->assertStatus(200);
        $retry->assertJson(['data' => ['duplicate' => true]]);

        $this->assertSame(1, Sale::where('business_id', $env['business']->id)->count());
        $this->assertSame(1, SaleItem::where('business_id', $env['business']->id)->count());
        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());
        $this->assertEquals(7, (float) Product::where('sync_id', $prodSyncId)->first()->stock);
    }

    protected function seedUnpaidLaundrySale(array $env, string $saleSyncId, int $total = 25000): void
    {
        $this->pushAs($env, 'POS-01', [
            'sales' => [[
                'sync_id' => $saleSyncId,
                'base_sync_version' => null,
                'transaction_number' => 'LDR-20260917-0001',
                'status' => 'unpaid',
                'subtotal' => $total,
                'total_amount' => $total,
                'payment_status' => 'unpaid',
                'order_status' => 'Masuk',
                'sold_at' => '2026-09-17 09:00:00',
            ]],
        ])->assertStatus(200);
    }

    /**
     * @return array<string, mixed>
     */
    protected function salePayload(string $saleSyncId, string $nextOrderStatus, int $baseVersion, string $transactionNumber = 'LDR-20260917-0001'): array
    {
        return [
            'sync_id' => $saleSyncId,
            'base_sync_version' => $baseVersion,
            'transaction_number' => $transactionNumber,
            'status' => 'unpaid',
            'subtotal' => 25000,
            'total_amount' => 25000,
            'payment_status' => 'unpaid',
            'order_status' => $nextOrderStatus,
            'sold_at' => '2026-09-17 09:00:00',
        ];
    }
}
