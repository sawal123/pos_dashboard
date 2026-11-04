<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncPushBarcodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setupSyncEnvironment(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['business_type' => 'grosir']);
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

    public function test_legacy_payload_with_null_barcode_preserves_existing_barcode(): void
    {
        $env = $this->setupSyncEnvironment();

        $prodSyncId = (string) Str::uuid();
        $existing = new Product([
            'business_id' => $env['business']->id,
            'name' => 'Gula Pasir 1kg',
            'sku' => 'GULA-01',
            'barcode' => '8991234567890',
            'price' => 15000,
            'status' => 'active',
        ]);
        $existing->sync_id = $prodSyncId;
        $existing->sync_version = 1;
        $existing->save();

        // Legacy mobile client pushes product update (e.g. price change) with barcode: null
        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'products' => [
                        [
                            'sync_id' => $prodSyncId,
                            'base_sync_version' => 1,
                            'name' => 'Gula Pasir 1kg (Updated)',
                            'sku' => 'GULA-01',
                            'barcode' => null, // Legacy client sending null
                            'price' => 16000,
                            'status' => 'active',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $existing->refresh();
        $this->assertSame(16000, (int) $existing->price);
        $this->assertSame('Gula Pasir 1kg (Updated)', $existing->name);
        // BARCODE MUST BE PRESERVED!
        $this->assertSame('8991234567890', $existing->barcode);
    }

    public function test_barcode_action_set_updates_barcode(): void
    {
        $env = $this->setupSyncEnvironment();

        $prodSyncId = (string) Str::uuid();
        $existing = new Product([
            'business_id' => $env['business']->id,
            'name' => 'Kopi Kapal Api',
            'sku' => 'KOPI-01',
            'barcode' => 'OLD-BARCODE',
            'price' => 3000,
            'status' => 'active',
        ]);
        $existing->sync_id = $prodSyncId;
        $existing->sync_version = 1;
        $existing->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'products' => [
                        [
                            'sync_id' => $prodSyncId,
                            'base_sync_version' => 1,
                            'name' => 'Kopi Kapal Api',
                            'sku' => 'KOPI-01',
                            'barcode' => '8999999123456',
                            'barcode_action' => 'set',
                            'price' => 3000,
                            'status' => 'active',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $existing->refresh();
        $this->assertSame('8999999123456', $existing->barcode);
    }

    public function test_barcode_action_clear_explicitly_clears_barcode(): void
    {
        $env = $this->setupSyncEnvironment();

        $prodSyncId = (string) Str::uuid();
        $existing = new Product([
            'business_id' => $env['business']->id,
            'name' => 'Teh Celup',
            'sku' => 'TEH-01',
            'barcode' => '8991111222233',
            'price' => 5000,
            'status' => 'active',
        ]);
        $existing->sync_id = $prodSyncId;
        $existing->sync_version = 1;
        $existing->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'products' => [
                        [
                            'sync_id' => $prodSyncId,
                            'base_sync_version' => 1,
                            'name' => 'Teh Celup',
                            'sku' => 'TEH-01',
                            'barcode' => null,
                            'barcode_action' => 'clear',
                            'price' => 5000,
                            'status' => 'active',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $existing->refresh();
        $this->assertNull($existing->barcode);
    }

    public function test_barcode_action_clear_with_non_null_barcode_returns_422(): void
    {
        $env = $this->setupSyncEnvironment();

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
                            'name' => 'Invalid Clear',
                            'sku' => 'INV-CLEAR',
                            'barcode' => '8991111222233', // Must be null when action is clear
                            'barcode_action' => 'clear',
                            'price' => 5000,
                            'status' => 'active',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_barcode_action_clear_on_new_product_create_is_rejected(): void
    {
        $env = $this->setupSyncEnvironment();

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
                            'name' => 'New Product with Clear',
                            'sku' => 'NEW-CLEAR',
                            'barcode' => null,
                            'barcode_action' => 'clear', // Clear on create is invalid
                            'price' => 5000,
                            'status' => 'active',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_cross_column_conflict_barcode_matching_another_product_sku_returns_409(): void
    {
        $env = $this->setupSyncEnvironment();

        Product::create([
            'business_id' => $env['business']->id,
            'name' => 'Existing Product',
            'sku' => 'EXISTING-SKU-100',
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
                            'name' => 'New Product',
                            'sku' => 'NEW-SKU-200',
                            'barcode' => 'EXISTING-SKU-100', // Collides with Product 1's SKU!
                            'barcode_action' => 'set',
                            'price' => 12000,
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
