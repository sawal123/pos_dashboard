<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductSellableTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_products_are_sellable_by_default(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $this->assertTrue((bool) $product->fresh()->is_sellable);
    }

    public function test_02_owner_can_create_a_non_sellable_supply(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('products.store'), [
                'kind' => 'product',
                'name' => 'Biji Kopi Arabica',
                'sku' => 'RAW-ARABICA',
                'price' => 0,
                'cost' => 120000,
                'stock' => 5,
                'min_stock' => 1,
                'unit' => 'kg',
                'is_sellable' => '0',
                'status' => 'active',
            ])
            ->assertRedirect();

        $product = Product::query()->where('business_id', $business->id)->where('sku', 'RAW-ARABICA')->firstOrFail();
        $this->assertFalse((bool) $product->is_sellable);
    }

    public function test_03_supplies_are_separated_from_sellable_products(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Susu',
            'sku' => 'SELL-01',
            'kind' => 'product',
            'is_sellable' => true,
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Susu UHT',
            'sku' => 'RAW-01',
            'kind' => 'product',
            'is_sellable' => false,
        ]);

        $sellable = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('products.index', ['tab' => 'products']));
        $sellable->assertOk();
        $sellable->assertSee('Kopi Susu');
        $sellable->assertDontSee('Susu UHT');

        $supplies = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('products.index', ['tab' => 'supplies']));
        $supplies->assertOk();
        $supplies->assertSee('Susu UHT');
        $supplies->assertDontSee('Kopi Susu');
    }

    public function test_04_pull_payload_includes_is_sellable(): void
    {
        $env = $this->setupSyncEnvironment('owner');
        Product::factory()->create([
            'business_id' => $env['business']->id,
            'name' => 'Bahan Baku',
            'kind' => 'product',
            'is_sellable' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson('/api/sync/pull?business_id='.$env['business']->id.'&device_identifier=POS-01');

        $response->assertOk();

        $records = collect($response->json('data.records'));
        $product = $records->firstWhere('entity', 'products');

        $this->assertNotNull($product);
        $this->assertArrayHasKey('is_sellable', $product['data']);
        $this->assertFalse($product['data']['is_sellable']);
    }

    public function test_05_cashier_cannot_sell_a_non_sellable_product(): void
    {
        $env = $this->setupSyncEnvironment('cashier');
        $product = Product::factory()->create([
            'business_id' => $env['business']->id,
            'name' => 'Deterjen',
            'kind' => 'product',
            'is_sellable' => false,
        ]);

        $response = $this->pushSale($env, $product);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'SYNC_OPERATION_NOT_ALLOWED');
        $this->assertSame('product_not_sellable', $response->json('violations.0.reason'));
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_06_sellable_product_still_passes_the_guard(): void
    {
        $env = $this->setupSyncEnvironment('cashier');
        $product = Product::factory()->create([
            'business_id' => $env['business']->id,
            'name' => 'Kopi Susu',
            'kind' => 'product',
            'is_sellable' => true,
        ]);

        $response = $this->pushSale($env, $product);

        $response->assertStatus(200);
        $this->assertDatabaseCount('sale_items', 1);
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }

    /**
     * @return array{business: Business, outlet: Outlet, token: string}
     */
    private function setupSyncEnvironment(string $role): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Main POS',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        return [
            'business' => $business,
            'outlet' => $outlet,
            'token' => $user->createToken('mobile-api', ['mobile'])->plainTextToken,
        ];
    }

    /**
     * @param  array{business: Business, outlet: Outlet, token: string}  $env
     */
    private function pushSale(array $env, Product $product): TestResponse
    {
        $saleSyncId = (string) Str::uuid();

        return $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'sales' => [
                        [
                            'sync_id' => $saleSyncId,
                            'transaction_number' => 'TRX-'.Str::upper(Str::random(6)),
                            'status' => 'completed',
                            'subtotal' => 1000,
                            'discount_amount' => 0,
                            'tax_amount' => 0,
                            'total_amount' => 1000,
                            'payment_method' => 'cash',
                            'payment_status' => 'paid',
                            'sold_at' => now()->toDateTimeString(),
                        ],
                    ],
                    'sale_items' => [
                        [
                            'sync_id' => (string) Str::uuid(),
                            'sale_sync_id' => $saleSyncId,
                            'product_sync_id' => $product->sync_id,
                            'product_name' => $product->name,
                            'product_sku' => $product->sku,
                            'unit_price' => 1000,
                            'quantity' => 1,
                            'line_total' => 1000,
                            'unit' => 'pcs',
                            'kind' => 'product',
                            'pricing_unit' => 'pcs',
                        ],
                    ],
                ],
            ]);
    }
}
