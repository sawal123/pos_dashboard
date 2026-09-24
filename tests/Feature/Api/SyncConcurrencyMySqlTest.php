<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Sync\SyncPushService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P38 concurrency gate (T).
 *
 * Runs the real SyncPushService in TWO separate PHP processes against one
 * dedicated MySQL/MariaDB test database, so row-lock semantics (lockForUpdate)
 * are exercised for real. SQLite is never accepted as concurrency evidence.
 *
 * Run with: vendor/bin/phpunit -c phpunit.p38concurrency.xml
 */
class SyncConcurrencyMySqlTest extends TestCase
{
    /**
     * @var array{businessId: int, outletId: int, deviceId: int, userId: int}
     */
    private array $fixture = [];

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                'CONCURRENCY_DB_REQUIRED: this gate only runs against the dedicated MySQL/MariaDB test database.'
            );
        }

        // Real committed data is required: the racing pushes run in child
        // processes and must see the fixture. A wrapping test transaction
        // (RefreshDatabase) or a rollback teardown is therefore unusable.
        Artisan::call('migrate:fresh', ['--force' => true]);

        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Concurrency POS',
            'identifier' => 'POS-CONC-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $this->fixture = [
            'businessId' => (int) $business->id,
            'outletId' => (int) $outlet->id,
            'deviceId' => (int) $device->id,
            'userId' => (int) $user->id,
        ];
    }

    /**
     * Executes one real push (service level) inside a child process.
     *
     * @param  array{businessId: int, outletId: int, deviceId: int, userId: int}  $fixture
     * @param  array<string, list<array<string, mixed>>>  $changes
     * @return array{status: int, body: mixed}|string
     */
    public static function concurrentPush(array $fixture, array $changes, string $requestId): array|string
    {
        try {
            $business = Business::findOrFail($fixture['businessId']);
            $device = Device::findOrFail($fixture['deviceId']);
            $user = User::findOrFail($fixture['userId']);

            $response = app(SyncPushService::class)->process([
                'user' => $user,
                'business' => $business,
                'device' => $device,
                'outlet_id' => $fixture['outletId'],
            ], [
                'request_id' => $requestId,
                'changes' => $changes,
            ]);

            return [
                'status' => $response->getStatusCode(),
                'body' => json_decode((string) $response->getContent(), true),
            ];
        } catch (\Throwable $e) {
            return ['status' => 0, 'body' => get_class($e).': '.$e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function movement(string $syncId, string $productSyncId, float $delta, float $before, float $after, string $reference): array
    {
        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    private static function productEdit(string $productSyncId, int $price): array
    {
        return [
            'sync_id' => $productSyncId,
            'base_sync_version' => 1,
            'name' => 'Master',
            'sku' => 'SKU-CONC-2',
            'price' => $price,
            'status' => 'active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cashSettlement(string $saleSyncId, string $reference): array
    {
        return [
            'sync_id' => (string) Str::uuid(),
            'base_sync_version' => null,
            'type' => 'in',
            'amount' => 25000,
            'category' => 'Penjualan Cash',
            'reference_id' => $reference,
            'sale_sync_id' => $saleSyncId,
            'occurred_at' => '2026-09-17 10:00:00',
        ];
    }

    private function makeProduct(string $syncId, string $sku, float $stock): Product
    {
        $product = new Product(['name' => 'Master', 'sku' => $sku, 'price' => 10000, 'stock' => $stock]);
        $product->business_id = $this->fixture['businessId'];
        $product->sync_id = $syncId;
        $product->save();

        return $product;
    }

    public function test_concurrent_stock_deltas_serialize_on_real_row_locks(): void
    {
        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($prodSyncId, 'SKU-CONC-1', 10);

        $fixture = $this->fixture;
        $changeA = ['stock_movements' => [self::movement((string) Str::uuid(), $prodSyncId, -3, 10, 7, 'conc-a')]];
        $changeB = ['stock_movements' => [self::movement((string) Str::uuid(), $prodSyncId, -4, 10, 6, 'conc-b')]];

        [$resultA, $resultB] = Concurrency::run([
            fn () => self::concurrentPush($fixture, $changeA, (string) Str::uuid()),
            fn () => self::concurrentPush($fixture, $changeB, (string) Str::uuid()),
        ]);

        $this->assertIsArray($resultA, 'process A failed: '.(is_string($resultA) ? $resultA : ''));
        $this->assertIsArray($resultB, 'process B failed: '.(is_string($resultB) ? $resultB : ''));
        $this->assertSame(200, $resultA['status'], json_encode($resultA['body']));
        $this->assertSame(200, $resultB['status'], json_encode($resultB['body']));

        // 10 - 3 - 4 = 3: no lost update, no stock corruption.
        $this->assertSame(3.0, (float) Product::where('sync_id', $prodSyncId)->first()->stock);
        $this->assertSame(2, StockMovement::where('business_id', $this->fixture['businessId'])->count());
    }

    public function test_concurrent_oversell_serializes_on_real_row_locks(): void
    {
        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($prodSyncId, 'SKU-CONC-3', 2);

        $fixture = $this->fixture;
        $changeA = ['stock_movements' => [self::movement((string) Str::uuid(), $prodSyncId, -5, 2, -3, 'over-a')]];
        $changeB = ['stock_movements' => [self::movement((string) Str::uuid(), $prodSyncId, -5, 2, -3, 'over-b')]];

        [$resultA, $resultB] = Concurrency::run([
            fn () => self::concurrentPush($fixture, $changeA, (string) Str::uuid()),
            fn () => self::concurrentPush($fixture, $changeB, (string) Str::uuid()),
        ]);

        $this->assertIsArray($resultA, 'process A failed: '.(is_string($resultA) ? $resultA : ''));
        $this->assertIsArray($resultB, 'process B failed: '.(is_string($resultB) ? $resultB : ''));
        $this->assertSame(200, $resultA['status'], json_encode($resultA['body']));
        $this->assertSame(200, $resultB['status'], json_encode($resultB['body']));

        // 2 - 5 - 5 = -8: both offline oversells applied exactly once, no lost
        // update, and the negative server stock is accepted (no rejection).
        $this->assertSame(-8.0, (float) Product::where('sync_id', $prodSyncId)->first()->stock);
        $this->assertSame(2, StockMovement::where('business_id', $this->fixture['businessId'])->count());
    }

    public function test_concurrent_master_edit_produces_exactly_one_winner(): void
    {
        $prodSyncId = (string) Str::uuid();
        $this->makeProduct($prodSyncId, 'SKU-CONC-2', 10);

        $fixture = $this->fixture;
        $editA = ['products' => [self::productEdit($prodSyncId, 16000)]];
        $editB = ['products' => [self::productEdit($prodSyncId, 17000)]];

        [$resultA, $resultB] = Concurrency::run([
            fn () => self::concurrentPush($fixture, $editA, (string) Str::uuid()),
            fn () => self::concurrentPush($fixture, $editB, (string) Str::uuid()),
        ]);

        $this->assertIsArray($resultA, 'process A failed: '.(is_string($resultA) ? $resultA : ''));
        $this->assertIsArray($resultB, 'process B failed: '.(is_string($resultB) ? $resultB : ''));

        $statuses = [$resultA['status'], $resultB['status']];
        sort($statuses);

        // Deterministic: exactly one write wins, the loser gets an explicit conflict.
        $this->assertSame([200, 409], $statuses);

        $price = (int) Product::where('sync_id', $prodSyncId)->value('price');
        $this->assertContains($price, [16000, 17000]);
        $this->assertSame(1, Product::where('sync_id', $prodSyncId)->count());
    }

    public function test_concurrent_cash_settlement_for_one_sale_stays_exactly_once(): void
    {
        $saleSyncId = (string) Str::uuid();
        $sale = new Sale([
            'transaction_number' => 'LDR-CONC-1',
            'status' => 'unpaid',
            'subtotal' => 25000,
            'total_amount' => 25000,
            'payment_status' => 'unpaid',
            'order_status' => 'Masuk',
            'sold_at' => '2026-09-17 09:00:00',
        ]);
        $sale->business_id = $this->fixture['businessId'];
        $sale->outlet_id = $this->fixture['outletId'];
        $sale->sync_id = $saleSyncId;
        $sale->save();

        $fixture = $this->fixture;
        $settlementA = ['cash_ledger' => [self::cashSettlement($saleSyncId, 'conc-a')]];
        $settlementB = ['cash_ledger' => [self::cashSettlement($saleSyncId, 'conc-b')]];

        [$resultA, $resultB] = Concurrency::run([
            fn () => self::concurrentPush($fixture, $settlementA, (string) Str::uuid()),
            fn () => self::concurrentPush($fixture, $settlementB, (string) Str::uuid()),
        ]);

        $this->assertIsArray($resultA, 'process A failed: '.(is_string($resultA) ? $resultA : ''));
        $this->assertIsArray($resultB, 'process B failed: '.(is_string($resultB) ? $resultB : ''));

        // One logical settlement: exactly one cash row for the sale, no 5xx.
        $this->assertLessThan(500, $resultA['status']);
        $this->assertLessThan(500, $resultB['status']);
        $this->assertSame(1, CashLedger::where('business_id', $this->fixture['businessId'])->count());
        $this->assertSame(1, CashLedger::where('sale_sync_id', $saleSyncId)->count());
        $this->assertSame(25000, (int) CashLedger::where('sale_sync_id', $saleSyncId)->value('amount'));
    }
}
