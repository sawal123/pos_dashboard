<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promo;
use App\Models\SalePromotion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PromoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_pull_includes_non_draft_promos_only(): void
    {
        $env = $this->setupSyncEnvironment();
        $this->createPromo($env['business'], ['name' => 'Aktif', 'status' => 'active']);
        $this->createPromo($env['business'], ['name' => 'Draft', 'status' => 'draft']);

        $response = $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->getJson('/api/sync/pull?business_id='.$env['business']->id.'&device_identifier=POS-01');

        $response->assertOk();

        $promos = collect($response->json('data.records'))->where('entity', 'promos');

        $this->assertCount(1, $promos);
        $this->assertSame('Aktif', $promos->first()['data']['name']);
    }

    public function test_02_valid_promo_sale_is_accepted_and_traced(): void
    {
        $env = $this->setupSyncEnvironment();
        $promo = $this->createPromo($env['business']);
        $product = $this->createProduct($env['business']);

        $response = $this->pushSale($env, $product, [
            'promo_sync_id' => $promo->sync_id,
            'discount_amount' => 1000,
            'total_amount' => 9000,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('sale_promotions', 1);
        $this->assertSame(1000, (int) SalePromotion::query()->firstOrFail()->discount_amount);
    }

    public function test_03_discount_mismatch_is_rejected(): void
    {
        $env = $this->setupSyncEnvironment();
        $promo = $this->createPromo($env['business']);
        $product = $this->createProduct($env['business']);

        $response = $this->pushSale($env, $product, [
            'promo_sync_id' => $promo->sync_id,
            'discount_amount' => 500,
            'total_amount' => 9500,
        ]);

        $response->assertStatus(403);
        $this->assertContains('promo_discount_mismatch', $this->reasons($response));
        $this->assertDatabaseCount('sale_promotions', 0);
    }

    public function test_04_total_mismatch_is_rejected(): void
    {
        $env = $this->setupSyncEnvironment();
        $product = $this->createProduct($env['business']);

        $response = $this->pushSale($env, $product, [
            'discount_amount' => 0,
            'total_amount' => 9500,
        ]);

        $response->assertStatus(403);
        $this->assertContains('sale_total_mismatch', $this->reasons($response));
    }

    public function test_05_usage_limit_is_enforced(): void
    {
        $env = $this->setupSyncEnvironment();
        $promo = $this->createPromo($env['business'], ['usage_limit_total' => 1]);
        $product = $this->createProduct($env['business']);

        $this->pushSale($env, $product, [
            'promo_sync_id' => $promo->sync_id,
            'discount_amount' => 1000,
            'total_amount' => 9000,
        ])->assertStatus(200);

        $second = $this->pushSale($env, $product, [
            'promo_sync_id' => $promo->sync_id,
            'discount_amount' => 1000,
            'total_amount' => 9000,
        ]);

        $second->assertStatus(403);
        $this->assertContains('promo_usage_limit_exceeded', $this->reasons($second));
    }

    public function test_06_manual_discount_without_promo_still_works(): void
    {
        $env = $this->setupSyncEnvironment();
        $product = $this->createProduct($env['business']);

        $response = $this->pushSale($env, $product, [
            'discount_amount' => 1000,
            'total_amount' => 9000,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('sale_promotions', 0);
    }

    /**
     * @return array{business: Business, outlet: Outlet, token: string}
     */
    private function setupSyncEnvironment(): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => 'owner']);
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
     * @param  array<string, mixed>  $attributes
     */
    private function createPromo(Business $business, array $attributes = []): Promo
    {
        return Promo::create(array_merge([
            'business_id' => $business->id,
            'name' => 'Promo Uji',
            'discount_type' => 'percent',
            'discount_value' => 1000,
            'scope' => 'all',
            'status' => 'active',
        ], $attributes));
    }

    private function createProduct(Business $business): Product
    {
        return Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Susu',
            'kind' => 'product',
            'is_sellable' => true,
        ]);
    }

    /**
     * @param  array{business: Business, outlet: Outlet, token: string}  $env
     * @param  array<string, mixed>  $saleOverrides
     */
    private function pushSale(array $env, Product $product, array $saleOverrides = []): TestResponse
    {
        $saleSyncId = (string) Str::uuid();

        return $this->withHeader('Authorization', 'Bearer '.$env['token'])
            ->postJson('/api/sync/push', [
                'business_id' => $env['business']->id,
                'device_identifier' => 'POS-01',
                'request_id' => (string) Str::uuid(),
                'changes' => [
                    'sales' => [
                        array_merge([
                            'sync_id' => $saleSyncId,
                            'transaction_number' => 'TRX-'.Str::upper(Str::random(6)),
                            'status' => 'completed',
                            'subtotal' => 10000,
                            'discount_amount' => 0,
                            'tax_amount' => 0,
                            'total_amount' => 10000,
                            'payment_method' => 'cash',
                            'payment_status' => 'paid',
                            'sold_at' => now()->toDateTimeString(),
                        ], $saleOverrides),
                    ],
                    'sale_items' => [
                        [
                            'sync_id' => (string) Str::uuid(),
                            'sale_sync_id' => $saleSyncId,
                            'product_sync_id' => $product->sync_id,
                            'product_name' => $product->name,
                            'product_sku' => $product->sku,
                            'unit_price' => 10000,
                            'quantity' => 1,
                            'line_total' => 10000,
                            'unit' => 'pcs',
                            'kind' => 'product',
                            'pricing_unit' => 'pcs',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * @return list<string>
     */
    private function reasons(TestResponse $response): array
    {
        /** @var list<array<string, mixed>> $violations */
        $violations = $response->json('violations') ?? [];

        return array_values(array_map(
            static fn (array $violation): string => (string) ($violation['reason'] ?? ''),
            $violations,
        ));
    }
}
