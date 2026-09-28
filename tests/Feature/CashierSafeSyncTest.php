<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashierSafeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_pull_with_active_cloud_and_active_device(): void
    {
        $env = $this->makeEnvironment('cashier');
        Category::create(['business_id' => $env['business']->id, 'name' => 'Drinks']);

        $this->withToken($env['token'])
            ->getJson($this->pullUrl($env))
            ->assertOk()
            ->assertJsonPath('data.records.0.entity', 'categories');
    }

    public function test_cashier_pull_is_tenant_and_outlet_scoped(): void
    {
        $env = $this->makeEnvironment('cashier');
        $otherOutlet = Outlet::factory()->create(['business_id' => $env['business']->id]);

        $ownShift = Shift::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'shift_number' => 'OWN-SHIFT',
            'opened_at' => now(),
        ]);
        Shift::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $otherOutlet->id,
            'shift_number' => 'OTHER-SHIFT',
            'opened_at' => now(),
        ]);
        Category::create(['business_id' => Business::factory()->create()->id, 'name' => 'Foreign']);

        $records = $this->withToken($env['token'])
            ->getJson($this->pullUrl($env))
            ->assertOk()
            ->json('data.records');

        $this->assertContains($ownShift->sync_id, collect($records)->pluck('data.sync_id')->all());
        $this->assertSame(['OWN-SHIFT'], collect($records)->where('entity', 'shifts')->pluck('data.shift_number')->values()->all());
        $this->assertNotContains('Foreign', collect($records)->where('entity', 'categories')->pluck('data.name')->all());
    }

    public function test_cashier_pull_rejects_foreign_device_inactive_device_and_free_plan(): void
    {
        $env = $this->makeEnvironment('cashier');
        $other = $this->makeEnvironment('cashier');

        $this->withToken($env['token'])
            ->getJson('/api/sync/pull?'.http_build_query([
                'business_id' => $env['business']->id,
                'device_identifier' => $other['device']->identifier,
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'SYNC_DEVICE_INVALID']);

        $env['device']->update(['status' => 'inactive']);
        $this->withToken($env['token'])
            ->getJson($this->pullUrl($env))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_INACTIVE']);

        Auth::forgetGuards();

        $free = $this->makeEnvironment('cashier', cloud: false);
        $this->withToken($free['token'])
            ->getJson($this->pullUrl($free))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_cashier_can_push_safe_customer_shift_sale_item_payment_and_stock_movement(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $payload = $this->safeTransactionPayload($env, $product);

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', false);

        $changes = $payload['changes'];
        $this->assertDatabaseHas('customers', ['sync_id' => $changes['customers'][0]['sync_id']]);
        $this->assertDatabaseHas('shifts', ['sync_id' => $changes['shifts'][0]['sync_id'], 'outlet_id' => $env['outlet']->id]);
        $this->assertDatabaseHas('sales', ['sync_id' => $changes['sales'][0]['sync_id'], 'outlet_id' => $env['outlet']->id]);
        $this->assertDatabaseHas('sale_items', ['sync_id' => $changes['sale_items'][0]['sync_id']]);
        $this->assertDatabaseHas('cash_ledger', ['sync_id' => $changes['cash_ledger'][0]['sync_id'], 'sale_sync_id' => $changes['sales'][0]['sync_id']]);
        $this->assertDatabaseHas('stock_movements', ['sync_id' => $changes['stock_movements'][0]['sync_id'], 'movement_type' => 'sale']);
        $this->assertSame(9.0, (float) $product->fresh()->stock);

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);
    }

    public function test_cashier_forbidden_entities_are_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');

        foreach ([
            'categories' => [['sync_id' => (string) Str::uuid(), 'base_sync_version' => null, 'name' => 'Forbidden']],
            'products' => [['sync_id' => (string) Str::uuid(), 'base_sync_version' => null, 'name' => 'Forbidden', 'sku' => 'F-'.Str::random(6), 'price' => 1000]],
            'expenses' => [['sync_id' => (string) Str::uuid(), 'base_sync_version' => null, 'description' => 'Forbidden', 'amount' => 1000, 'occurred_at' => '2026-09-28 08:00:00']],
            'deletions' => [['entity' => 'customers', 'sync_id' => (string) Str::uuid(), 'base_sync_version' => null]],
        ] as $entity => $items) {
            $this->withToken($env['token'])
                ->postJson('/api/sync/push', $this->payload($env, [$entity => $items]))
                ->assertStatus(403)
                ->assertJson(['code' => 'SYNC_OPERATION_NOT_ALLOWED'])
                ->assertJsonPath('violations.0.entity', $entity === 'deletions' ? 'customers' : $entity);
        }
    }

    public function test_cashier_manual_cash_and_generic_stock_mutations_are_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'cash_ledger' => [$this->cashEntry((string) Str::uuid(), null, 1000)],
            ]))
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'manual_cash_not_allowed');

        foreach ([
            ['movement_type' => 'adjustment', 'quantity_change' => 999999, 'reason' => 'stock_movement_type_not_allowed'],
            ['movement_type' => 'restock', 'quantity_change' => 5, 'reason' => 'stock_movement_type_not_allowed'],
            ['movement_type' => 'sale', 'quantity_change' => 5, 'reason' => 'missing_sale_relation'],
        ] as $case) {
            $this->withToken($env['token'])
                ->postJson('/api/sync/push', $this->payload($env, [
                    'stock_movements' => [[
                        'sync_id' => (string) Str::uuid(),
                        'base_sync_version' => null,
                        'product_sync_id' => $product->sync_id,
                        'movement_type' => $case['movement_type'],
                        'quantity_change' => $case['quantity_change'],
                        'occurred_at' => '2026-09-28 08:00:00',
                    ]],
                ]))
                ->assertStatus(403)
                ->assertJson(['code' => 'SYNC_OPERATION_NOT_ALLOWED']);
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10.0, (float) $product->fresh()->stock);
    }

    public function test_cashier_relationships_must_match_device_outlet(): void
    {
        $env = $this->makeEnvironment('cashier');
        $otherOutlet = Outlet::factory()->create(['business_id' => $env['business']->id]);
        $foreignSale = Sale::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $otherOutlet->id,
            'transaction_number' => 'FOREIGN-SALE',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'sold_at' => now(),
        ]);
        $product = $this->makeProduct($env);

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'sale_items' => [[
                    'sync_id' => (string) Str::uuid(),
                    'base_sync_version' => null,
                    'sale_sync_id' => $foreignSale->sync_id,
                    'product_sync_id' => $product->sync_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => 1000,
                    'quantity' => 1,
                    'line_total' => 1000,
                ]],
            ]))
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'invalid_sale_relation');

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'cash_ledger' => [$this->cashEntry((string) Str::uuid(), $foreignSale->sync_id, 1000)],
            ]))
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'invalid_sale_relation');

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'stock_movements' => [$this->stockMovement($product->sync_id, $foreignSale->sync_id)],
            ]))
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'invalid_sale_relation');
    }

    public function test_mixed_allowed_and_forbidden_cashier_payload_is_atomic(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();
        $productSyncId = (string) Str::uuid();
        $requestId = (string) Str::uuid();
        $sequenceBefore = (int) SyncCounter::where('business_id', $env['business']->id)->value('current_sequence');

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => $env['device']->identifier,
                'request_id' => $requestId,
                'changes' => [
                    'sales' => [$this->sale($saleSyncId)],
                    'products' => [[
                        'sync_id' => $productSyncId,
                        'base_sync_version' => null,
                        'name' => 'Malicious Product',
                        'sku' => 'MAL-01',
                        'price' => 1,
                    ]],
                ],
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'SYNC_OPERATION_NOT_ALLOWED']);

        $this->assertDatabaseMissing('sales', ['sync_id' => $saleSyncId]);
        $this->assertDatabaseMissing('products', ['sync_id' => $productSyncId]);
        $this->assertFalse(SyncRequest::where('request_id', $requestId)->exists());
        $this->assertSame($sequenceBefore, (int) SyncCounter::where('business_id', $env['business']->id)->value('current_sequence'));
    }

    public function test_existing_token_follows_role_changes_and_membership_removal(): void
    {
        $env = $this->makeEnvironment('member');

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'products' => [[
                    'sync_id' => (string) Str::uuid(),
                    'base_sync_version' => null,
                    'name' => 'Member Product',
                    'sku' => 'MEMBER-01',
                    'price' => 1000,
                ]],
            ]))
            ->assertOk();

        $this->setRole($env, 'cashier');
        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'products' => [[
                    'sync_id' => (string) Str::uuid(),
                    'base_sync_version' => null,
                    'name' => 'Denied Product',
                    'sku' => 'DENIED-01',
                    'price' => 1000,
                ]],
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'SYNC_OPERATION_NOT_ALLOWED']);

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'customers' => [['sync_id' => (string) Str::uuid(), 'base_sync_version' => null, 'name' => 'Cashier Customer']],
            ]))
            ->assertOk();

        $this->setRole($env, 'member');
        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, [
                'products' => [[
                    'sync_id' => (string) Str::uuid(),
                    'base_sync_version' => null,
                    'name' => 'Member Product Again',
                    'sku' => 'MEMBER-02',
                    'price' => 1000,
                ]],
            ]))
            ->assertOk();

        DB::table('business_user')
            ->where('business_id', $env['business']->id)
            ->where('user_id', $env['user']->id)
            ->delete();

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, []))
            ->assertStatus(403)
            ->assertJson(['code' => 'BUSINESS_ACCESS_DENIED']);
    }

    public function test_unknown_role_is_denied(): void
    {
        $env = $this->makeEnvironment('supervisor');

        $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, []))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeEnvironment(string $role, bool $cloud = true): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);
        $cloud
            ? Subscription::factory()->cloud()->create(['business_id' => $business->id])
            : Subscription::factory()->free()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS '.$role,
            'identifier' => 'POS-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return compact('user', 'business', 'outlet', 'device', 'token');
    }

    private function makeProduct(array $env): Product
    {
        $product = Product::create([
            'business_id' => $env['business']->id,
            'name' => 'Safe Product '.Str::random(6),
            'sku' => 'SAFE-'.Str::upper(Str::random(6)),
            'price' => 1000,
            'stock' => 10,
        ]);

        return $product->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function safeTransactionPayload(array $env, Product $product): array
    {
        $customerSyncId = (string) Str::uuid();
        $shiftSyncId = (string) Str::uuid();
        $saleSyncId = (string) Str::uuid();

        return $this->payload($env, [
            'customers' => [['sync_id' => $customerSyncId, 'base_sync_version' => null, 'name' => 'Walk In']],
            'shifts' => [[
                'sync_id' => $shiftSyncId,
                'base_sync_version' => null,
                'shift_number' => 'SHIFT-'.Str::upper(Str::random(6)),
                'status' => 'open',
                'opening_cash' => 100000,
                'opened_at' => '2026-09-28 08:00:00',
            ]],
            'sales' => [$this->sale($saleSyncId, $customerSyncId, $shiftSyncId)],
            'sale_items' => [[
                'sync_id' => (string) Str::uuid(),
                'base_sync_version' => null,
                'sale_sync_id' => $saleSyncId,
                'product_sync_id' => $product->sync_id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'unit_price' => 1000,
                'quantity' => 1,
                'line_total' => 1000,
            ]],
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), $saleSyncId, 1000)],
            'stock_movements' => [$this->stockMovement($product->sync_id, $saleSyncId)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $env, array $changes): array
    {
        return [
            'business_id' => $env['business']->id,
            'device_identifier' => $env['device']->identifier,
            'request_id' => (string) Str::uuid(),
            'changes' => $changes,
        ];
    }

    private function pullUrl(array $env): string
    {
        return '/api/sync/pull?'.http_build_query([
            'business_id' => $env['business']->id,
            'device_identifier' => $env['device']->identifier,
            'after' => 0,
            'limit' => 100,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sale(string $syncId, ?string $customerSyncId = null, ?string $shiftSyncId = null): array
    {
        return [
            'sync_id' => $syncId,
            'base_sync_version' => null,
            'customer_sync_id' => $customerSyncId,
            'shift_sync_id' => $shiftSyncId,
            'transaction_number' => 'TRX-'.Str::upper(Str::random(8)),
            'status' => 'completed',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'sold_at' => '2026-09-28 08:30:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cashEntry(string $syncId, ?string $saleSyncId, int $amount): array
    {
        return [
            'sync_id' => $syncId,
            'base_sync_version' => null,
            'shift_sync_id' => null,
            'type' => 'in',
            'amount' => $amount,
            'category' => 'sale',
            'note' => 'POS payment',
            'reference_id' => 'PAY-'.Str::upper(Str::random(6)),
            'sale_sync_id' => $saleSyncId,
            'occurred_at' => '2026-09-28 08:31:00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockMovement(string $productSyncId, string $saleSyncId): array
    {
        return [
            'sync_id' => (string) Str::uuid(),
            'base_sync_version' => null,
            'product_sync_id' => $productSyncId,
            'movement_type' => 'sale',
            'quantity_change' => -1,
            'stock_before' => 10,
            'stock_after' => 9,
            'reference_id' => 'SALE-'.Str::upper(Str::random(6)),
            'sale_sync_id' => $saleSyncId,
            'occurred_at' => '2026-09-28 08:32:00',
        ];
    }

    private function setRole(array $env, string $role): void
    {
        DB::table('business_user')
            ->where('business_id', $env['business']->id)
            ->where('user_id', $env['user']->id)
            ->update(['role' => $role, 'updated_at' => now()]);
    }
}
