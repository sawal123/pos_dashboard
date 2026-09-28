<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Category;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    // =========================================================================
    // Finding 1 — stock movements must match sold sale-item quantities
    // =========================================================================

    public function test_cashier_stock_movement_exceeding_sold_quantity_is_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();

        // Sale of exactly one unit, with no movement yet.
        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id)],
        ])->assertOk();

        // A movement of -2 against a single sold unit is over-deduction.
        $this->pushCashier($env, [
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -2)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'stock_movement_exceeds_sold_quantity');

        $this->assertSame(10.0, (float) $product->fresh()->stock);
        $this->assertSame(0, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_duplicate_movement_with_different_sync_id_cannot_double_stock(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id, ['sync_id' => $itemSyncId])],
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -1)],
        ])->assertOk();

        $this->assertSame(9.0, (float) $product->fresh()->stock);

        // Same (sale, product) with a fresh sync_id: the second -1 would double
        // the reduction beyond the single sold unit.
        $this->pushCashier($env, [
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -1)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'stock_movement_exceeds_sold_quantity');

        $this->assertSame(9.0, (float) $product->fresh()->stock);
        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_stock_movement_without_matching_sale_item_is_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();

        // A valid sale that has no sale items to justify a stock deduction.
        $this->pushCashier($env, ['sales' => [$this->sale($saleSyncId)]])->assertOk();

        $this->pushCashier($env, [
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -1)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'stock_movement_without_sale_item');

        $this->assertSame(10.0, (float) $product->fresh()->stock);
        $this->assertSame(0, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_cannot_change_existing_stock_movement_identity(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $otherProduct = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $movementSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id)],
            'stock_movements' => [$this->movementWithId($movementSyncId, $product->sync_id, $saleSyncId, -1)],
        ])->assertOk();

        // Reusing the movement sync_id to point at another product must fail.
        $this->pushCashier($env, [
            'stock_movements' => [$this->movementWithId($movementSyncId, $otherProduct->sync_id, $saleSyncId, -1)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'stock_movement_identity_immutable');

        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
        $this->assertSame(
            (int) $product->id,
            (int) StockMovement::where('sync_id', $movementSyncId)->value('product_id'),
        );
    }

    public function test_cashier_movement_retry_with_new_request_id_is_idempotent(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $changes = [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id)],
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -1)],
        ];

        $this->pushCashier($env, $changes)->assertOk();
        // Same logical mutation under a new request_id is deduped by sync_id.
        $this->pushCashier($env, $changes)->assertOk();

        $this->assertSame(9.0, (float) $product->fresh()->stock);
        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_sale_items_and_movements_in_separate_batches_are_safe(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();

        // Batch 1: sale + sale item, no movement.
        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id)],
        ])->assertOk();
        $this->assertSame(10.0, (float) $product->fresh()->stock);

        // Batch 2: the movement alone. The persisted sale item authorizes it.
        $this->pushCashier($env, [
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -1)],
        ])->assertOk();

        $this->assertSame(9.0, (float) $product->fresh()->stock);
        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
    }

    // =========================================================================
    // Finding 2 — cash ledger may only settle a valid cash sale
    // =========================================================================

    public function test_cashier_cash_payment_for_qris_sale_is_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();

        // A QRIS sale already recorded on the server.
        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), ['payment_method' => 'qris'])],
        ])->assertOk();

        $this->pushCashier($env, [
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), $saleSyncId, 1000)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_payment_requires_cash_sale');

        $this->assertSame(0, CashLedger::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_cash_payment_for_unpaid_sale_is_rejected(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), ['payment_status' => 'unpaid'])],
        ])->assertOk();

        $this->pushCashier($env, [
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), $saleSyncId, 1000)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_payment_requires_paid_sale');

        $this->assertSame(0, CashLedger::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_cannot_convert_manual_owner_cash_into_a_sale_payment(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();
        $cashSyncId = (string) Str::uuid();

        // An owner's manual cash row (no sale linkage).
        $manual = new CashLedger([
            'type' => 'in',
            'amount' => 5000,
            'category' => 'manual',
            'occurred_at' => '2026-09-28 08:00:00',
        ]);
        $manual->business_id = $env['business']->id;
        $manual->outlet_id = $env['outlet']->id;
        $manual->sync_id = $cashSyncId;
        $manual->save();

        $this->pushCashier($env, ['sales' => [$this->sale($saleSyncId)]])->assertOk();

        $this->pushCashier($env, [
            'cash_ledger' => [$this->cashEntry($cashSyncId, $saleSyncId, 1000)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_ledger_origin_immutable');

        $record = CashLedger::where('sync_id', $cashSyncId)->firstOrFail();
        $this->assertNull($record->sale_sync_id);
        $this->assertSame(5000, (int) $record->amount);
    }

    public function test_cashier_cannot_move_an_existing_sale_cash_linkage(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $otherSaleSyncId = (string) Str::uuid();

        $payload = $this->safeTransactionPayload($env, $product);
        $saleSyncId = $payload['changes']['sales'][0]['sync_id'];
        $cashSyncId = $payload['changes']['cash_ledger'][0]['sync_id'];
        $this->pushCashier($env, $payload['changes'])->assertOk();

        // A second, independent cash sale in the same outlet.
        $this->pushCashier($env, ['sales' => [$this->sale($otherSaleSyncId)]])->assertOk();

        $this->pushCashier($env, [
            'cash_ledger' => [$this->cashEntry($cashSyncId, $otherSaleSyncId, 1000)],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_ledger_sale_link_immutable');

        $this->assertSame($saleSyncId, CashLedger::where('sync_id', $cashSyncId)->value('sale_sync_id'));
    }

    public function test_cashier_cannot_change_an_existing_cash_amount(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $payload = $this->safeTransactionPayload($env, $product);
        $saleSyncId = $payload['changes']['sales'][0]['sync_id'];
        $cashSyncId = $payload['changes']['cash_ledger'][0]['sync_id'];
        $this->pushCashier($env, $payload['changes'])->assertOk();

        $response = $this->pushCashier($env, [
            'cash_ledger' => [$this->cashEntry($cashSyncId, $saleSyncId, 999)],
        ]);

        $response->assertStatus(403);
        $reasons = collect($response->json('violations'))->pluck('reason')->all();
        $this->assertContains('cash_ledger_immutable', $reasons);

        $this->assertSame(1000, (int) CashLedger::where('sync_id', $cashSyncId)->value('amount'));
    }

    public function test_cashier_identical_cash_retry_is_accepted_exactly_once(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $changes = $this->safeTransactionPayload($env, $product)['changes'];

        $this->pushCashier($env, $changes)->assertOk();
        // New request_id, identical mutation: valid identical retry.
        $this->pushCashier($env, $changes)->assertOk();

        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());
    }

    // =========================================================================
    // Finding 3 — historical financial snapshots are immutable
    // =========================================================================

    public function test_cashier_cannot_rewrite_a_formed_sale_financial_snapshot(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();
        $this->pushCashier($env, ['sales' => [$this->sale($saleSyncId)]])->assertOk();

        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), ['base_sync_version' => 1, 'total_amount' => 500])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'sale_financial_snapshot_immutable');

        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), ['base_sync_version' => 1, 'gross_profit' => 999])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'sale_financial_snapshot_immutable');

        $sale = Sale::where('sync_id', $saleSyncId)->firstOrFail();
        $this->assertSame(1000, (int) $sale->total_amount);
        $this->assertSame(0.0, (float) $sale->gross_profit);
    }

    public function test_cashier_cannot_rewrite_a_formed_sale_item_snapshot(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id, ['sync_id' => $itemSyncId])],
        ])->assertOk();

        $this->pushCashier($env, [
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id, [
                'sync_id' => $itemSyncId,
                'base_sync_version' => 1,
                'unit_price' => 1500,
            ])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'sale_item_snapshot_immutable');

        $item = SaleItem::where('sync_id', $itemSyncId)->firstOrFail();
        $this->assertSame(1000, (int) $item->unit_price);
        $this->assertSame(1.0, (float) $item->quantity);
    }

    public function test_cashier_shift_cash_is_immutable_and_closed_shift_is_locked(): void
    {
        $env = $this->makeEnvironment('cashier');
        $shiftSyncId = (string) Str::uuid();

        $this->pushCashier($env, ['shifts' => [$this->shiftPayload($shiftSyncId)]])->assertOk();

        // Opening cash cannot be re-derived on an existing shift.
        $this->pushCashier($env, [
            'shifts' => [array_merge($this->shiftPayload($shiftSyncId), ['base_sync_version' => 1, 'opening_cash' => 200000])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'shift_opening_cash_immutable');

        // Closing the shift (open -> closed) is allowed.
        $this->pushCashier($env, [
            'shifts' => [array_merge($this->shiftPayload($shiftSyncId), [
                'base_sync_version' => 1,
                'status' => 'closed',
                'closing_cash' => 150000,
                'closed_at' => '2026-09-28 17:00:00',
            ])],
        ])->assertOk();

        $this->assertSame('closed', Shift::where('sync_id', $shiftSyncId)->value('status'));

        // A closed shift rejects any non-identical change.
        $this->pushCashier($env, [
            'shifts' => [array_merge($this->shiftPayload($shiftSyncId), [
                'base_sync_version' => 2,
                'status' => 'closed',
                'closing_cash' => 999,
                'closed_at' => '2026-09-28 17:00:00',
            ])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'shift_closed_immutable');

        // ...and cannot be reopened.
        $this->pushCashier($env, [
            'shifts' => [array_merge($this->shiftPayload($shiftSyncId), ['base_sync_version' => 2, 'status' => 'open'])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'shift_reopen_not_allowed');

        $shift = Shift::where('sync_id', $shiftSyncId)->firstOrFail();
        $this->assertSame('closed', $shift->status);
        $this->assertSame(150000, (int) $shift->closing_cash);
    }

    public function test_cashier_can_transition_unpaid_to_paid_and_advance_laundry_status(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), [
                'status' => 'unpaid',
                'payment_method' => null,
                'payment_status' => 'unpaid',
                'order_status' => 'Masuk',
            ])],
        ])->assertOk();

        // Paying an order settles cash and advances the laundry lifecycle in
        // the same envelope: both mutations stay legal.
        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), [
                'base_sync_version' => 1,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'order_status' => 'Diproses',
            ])],
            'cash_ledger' => [$this->cashEntry((string) Str::uuid(), $saleSyncId, 1000)],
        ])->assertOk();

        $sale = Sale::where('sync_id', $saleSyncId)->firstOrFail();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('Diproses', $sale->order_status);
        $this->assertSame(1, CashLedger::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_historical_violation_keeps_a_mixed_payload_atomic(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();
        $this->pushCashier($env, ['sales' => [$this->sale($saleSyncId)]])->assertOk();
        $requestsBefore = SyncRequest::count();

        $customerSyncId = (string) Str::uuid();
        $this->pushCashier($env, [
            'customers' => [['sync_id' => $customerSyncId, 'base_sync_version' => null, 'name' => 'Must Not Persist']],
            'sales' => [array_merge($this->sale($saleSyncId), ['base_sync_version' => 1, 'total_amount' => 500])],
        ])
            ->assertStatus(403)
            ->assertJson(['code' => 'SYNC_OPERATION_NOT_ALLOWED']);

        $this->assertDatabaseMissing('customers', ['sync_id' => $customerSyncId]);
        $this->assertSame(1000, (int) Sale::where('sync_id', $saleSyncId)->value('total_amount'));
        $this->assertSame($requestsBefore, SyncRequest::count());
    }

    public function test_cashier_stale_version_on_a_financially_identical_update_is_a_conflict(): void
    {
        $env = $this->makeEnvironment('cashier');
        $saleSyncId = (string) Str::uuid();
        $this->pushCashier($env, ['sales' => [$this->sale($saleSyncId)]])->assertOk();

        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($saleSyncId), [
                'base_sync_version' => 99,
                'transaction_number' => 'TRX-STALE',
            ])],
        ])
            ->assertStatus(409)
            ->assertJson(['code' => 'SYNC_CONFLICT']);

        $this->assertNotSame('TRX-STALE', Sale::where('sync_id', $saleSyncId)->value('transaction_number'));
    }

    public function test_cashier_cannot_mutate_a_sale_from_another_outlet(): void
    {
        $env = $this->makeEnvironment('cashier');
        $otherOutlet = Outlet::factory()->create(['business_id' => $env['business']->id]);
        $foreignSyncId = (string) Str::uuid();

        $foreignSale = new Sale([
            'transaction_number' => 'FOREIGN-HISTORY',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'sold_at' => '2026-09-28 08:00:00',
        ]);
        $foreignSale->business_id = $env['business']->id;
        $foreignSale->outlet_id = $otherOutlet->id;
        $foreignSale->sync_id = $foreignSyncId;
        $foreignSale->save();

        $this->pushCashier($env, [
            'sales' => [array_merge($this->sale($foreignSyncId), [
                'base_sync_version' => 1,
                'transaction_number' => 'HIJACKED',
            ])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'foreign_outlet_relation');

        $this->assertSame('FOREIGN-HISTORY', $foreignSale->fresh()->transaction_number);
    }

    // =========================================================================
    // Finding 1 (round 2) — existing movement identity/value immutability
    // =========================================================================

    public function test_cashier_cannot_rewrite_an_existing_movement_delta_even_with_valid_version(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $movementSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id)],
            'stock_movements' => [$this->movementWithId($movementSyncId, $product->sync_id, $saleSyncId, -1)],
        ])->assertOk();

        $this->assertSame(9.0, (float) $product->fresh()->stock);

        // A fresh, *valid* base version does not authorize rewriting the delta.
        $this->pushCashier($env, [
            'stock_movements' => [array_merge(
                $this->movementWithId($movementSyncId, $product->sync_id, $saleSyncId, -2),
                ['base_sync_version' => 1],
            )],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'stock_movement_value_immutable');

        $this->assertSame(-1.0, (float) StockMovement::where('sync_id', $movementSyncId)->value('quantity_change'));
        $this->assertSame(9.0, (float) $product->fresh()->stock);
        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_identical_movement_retry_does_not_reapply_stock(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $changes = [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id)],
            'stock_movements' => [$this->movementWithId((string) Str::uuid(), $product->sync_id, $saleSyncId, -1)],
        ];

        // Identical retry under a new request id: acknowledged, applied once.
        $this->pushCashier($env, $changes)->assertOk();
        $this->pushCashier($env, $changes)->assertOk();
        $this->assertSame(9.0, (float) $product->fresh()->stock);
        $this->assertSame(1, StockMovement::where('business_id', $env['business']->id)->count());
    }

    public function test_cashier_cannot_rewrite_an_existing_movement_type(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $movementSyncId = (string) Str::uuid();

        $this->pushCashier($env, ['sales' => [$this->sale($saleSyncId)]])->assertOk();

        // An owner-created adjustment the cashier tries to relabel as a sale.
        $movement = new StockMovement([
            'movement_type' => 'adjustment',
            'quantity_change' => -1,
            'stock_before' => 10,
            'stock_after' => 9,
            'sale_sync_id' => $saleSyncId,
            'occurred_at' => '2026-09-28 08:00:00',
        ]);
        $movement->business_id = $env['business']->id;
        $movement->product_id = $product->id;
        $movement->sync_id = $movementSyncId;
        $movement->save();

        $this->pushCashier($env, [
            'stock_movements' => [array_merge(
                $this->movementWithId($movementSyncId, $product->sync_id, $saleSyncId, -1),
                ['base_sync_version' => 1],
            )],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'stock_movement_identity_immutable');

        $this->assertSame('adjustment', StockMovement::where('sync_id', $movementSyncId)->value('movement_type'));
    }

    // =========================================================================
    // Finding 2 (round 2) — existing sale item relation immutability
    // =========================================================================

    public function test_cashier_cannot_repoint_a_sale_item_to_another_product_or_sale(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $otherProduct = $this->makeProduct($env);
        $saleSyncId = (string) Str::uuid();
        $otherSaleSyncId = (string) Str::uuid();
        $itemSyncId = (string) Str::uuid();

        $this->pushCashier($env, [
            'sales' => [$this->sale($saleSyncId)],
            'sale_items' => [$this->saleItemPayload($saleSyncId, $product->sync_id, ['sync_id' => $itemSyncId])],
        ])->assertOk();

        $this->pushCashier($env, ['sales' => [$this->sale($otherSaleSyncId)]])->assertOk();

        // Same outlet, different product.
        $this->pushCashier($env, [
            'sale_items' => [$this->saleItemPayload($saleSyncId, $otherProduct->sync_id, [
                'sync_id' => $itemSyncId,
                'base_sync_version' => 1,
            ])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'sale_item_relation_immutable');

        // Same outlet, different sale.
        $this->pushCashier($env, [
            'sale_items' => [$this->saleItemPayload($otherSaleSyncId, $product->sync_id, [
                'sync_id' => $itemSyncId,
                'base_sync_version' => 1,
            ])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'sale_item_relation_immutable');

        $item = SaleItem::where('sync_id', $itemSyncId)->firstOrFail();
        $this->assertSame((int) $product->id, (int) $item->product_id);
        $this->assertSame((int) Sale::where('sync_id', $saleSyncId)->value('id'), (int) $item->sale_id);
    }

    public function test_cashier_cannot_reclassify_an_existing_cash_entry(): void
    {
        $env = $this->makeEnvironment('cashier');
        $product = $this->makeProduct($env);
        $payload = $this->safeTransactionPayload($env, $product);
        $saleSyncId = $payload['changes']['sales'][0]['sync_id'];
        $cashSyncId = $payload['changes']['cash_ledger'][0]['sync_id'];
        $original = $payload['changes']['cash_ledger'][0];
        $this->pushCashier($env, $payload['changes'])->assertOk();

        $shiftSyncId = (string) Str::uuid();
        $this->pushCashier($env, ['shifts' => [$this->shiftPayload($shiftSyncId)]])->assertOk();

        // Category (classification) change.
        $this->pushCashier($env, [
            'cash_ledger' => [array_merge($original, ['category' => 'reversal'])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_ledger_immutable');

        // Historical note change.
        $this->pushCashier($env, [
            'cash_ledger' => [array_merge($original, ['note' => 'tampered'])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_ledger_immutable');

        // Attaching the entry to a shift it was never recorded against.
        $this->pushCashier($env, [
            'cash_ledger' => [array_merge($original, ['shift_sync_id' => $shiftSyncId])],
        ])
            ->assertStatus(403)
            ->assertJsonPath('violations.0.reason', 'cash_ledger_immutable');

        $record = CashLedger::where('sync_id', $cashSyncId)->firstOrFail();
        $this->assertSame('sale', $record->category);
        $this->assertSame('POS payment', $record->note);
        $this->assertNull($record->shift_id);
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
     * @param  array<string, mixed>  $changes
     */
    private function pushCashier(array $env, array $changes): TestResponse
    {
        return $this->withToken($env['token'])
            ->postJson('/api/sync/push', $this->payload($env, $changes));
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function saleItemPayload(string $saleSyncId, string $productSyncId, array $overrides = []): array
    {
        return array_merge([
            'sync_id' => (string) Str::uuid(),
            'base_sync_version' => null,
            'sale_sync_id' => $saleSyncId,
            'product_sync_id' => $productSyncId,
            'product_name' => 'Item',
            'product_sku' => 'SKU-ITEM',
            'unit_price' => 1000,
            'quantity' => 1,
            'line_total' => 1000,
            'cost_snapshot' => 400,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function shiftPayload(string $syncId, array $overrides = []): array
    {
        return array_merge([
            'sync_id' => $syncId,
            'base_sync_version' => null,
            'shift_number' => 'SHIFT-'.Str::upper(Str::random(6)),
            'status' => 'open',
            'opening_cash' => 100000,
            'opened_at' => '2026-09-28 08:00:00',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function movementWithId(string $syncId, string $productSyncId, string $saleSyncId, float $delta): array
    {
        return [
            'sync_id' => $syncId,
            'base_sync_version' => null,
            'product_sync_id' => $productSyncId,
            'movement_type' => 'sale',
            'quantity_change' => $delta,
            'stock_before' => 10,
            'stock_after' => 10 + $delta,
            'reference_id' => 'SALE-'.Str::upper(Str::random(6)),
            'sale_sync_id' => $saleSyncId,
            'occurred_at' => '2026-09-28 08:32:00',
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
