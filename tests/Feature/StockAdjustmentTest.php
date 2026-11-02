<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_owner_can_increase_stock(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 10]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'in', 5))
            ->assertRedirect(route('stock.history'));

        $this->assertSame('15.000', (string) $product->fresh()->stock);

        $movement = StockMovement::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertSame('5.000', (string) $movement->quantity_change);
        $this->assertSame('10.000', (string) $movement->stock_before);
        $this->assertSame('15.000', (string) $movement->stock_after);
        $this->assertSame($user->id, $movement->user_id);
    }

    public function test_02_member_can_adjust_stock(): void
    {
        [$business, $user] = $this->actingAsRole('member');
        $product = $this->createProduct($business, ['stock' => 4]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'out', 1))
            ->assertRedirect(route('stock.history'));

        $this->assertSame('3.000', (string) $product->fresh()->stock);
    }

    public function test_03_cashier_is_forbidden(): void
    {
        [$business, $user] = $this->actingAsRole('cashier');
        $product = $this->createProduct($business);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'in', 1))
            ->assertForbidden();

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_04_zero_quantity_is_rejected(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'in', 0))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_05_service_products_cannot_be_adjusted(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $service = $this->createProduct($business, ['kind' => 'service']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($service, 'in', 1))
            ->assertSessionHasErrors('product_id');

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_06_foreign_tenant_product_returns_404(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $foreignBusiness = Business::factory()->create();
        $foreignProduct = $this->createProduct($foreignBusiness);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($foreignProduct, 'in', 1))
            ->assertNotFound();

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_07_idempotency_key_prevents_double_apply(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 10]);

        $payload = $this->payload($product, 'in', 5, ['idempotency_key' => 'adjust-once']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $payload);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $payload);

        $this->assertSame('15.000', (string) $product->fresh()->stock);
        $this->assertSame(1, StockMovement::query()->where('business_id', $business->id)->count());
    }

    public function test_08_movements_chain_stock_before_and_after(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 10]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'in', 5));

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'out', 2));

        $movements = StockMovement::query()
            ->where('business_id', $business->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertSame('10.000', (string) $movements[0]->stock_before);
        $this->assertSame('15.000', (string) $movements[0]->stock_after);
        $this->assertSame('15.000', (string) $movements[1]->stock_before);
        $this->assertSame('13.000', (string) $movements[1]->stock_after);
    }

    public function test_09_sync_identity_is_populated(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.adjustments.store'), $this->payload($product, 'in', 1));

        $movement = StockMovement::query()->where('business_id', $business->id)->firstOrFail();

        $this->assertNotEmpty($movement->sync_id);
        $this->assertSame(1, (int) $movement->sync_version);
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
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(Business $business, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'business_id' => $business->id,
            'name' => 'Produk Uji',
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'kind' => 'product',
            'stock' => 10,
            'min_stock' => 2,
            'cost' => 1000,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Product $product, string $direction, int|float $quantity, array $extra = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'direction' => $direction,
            'quantity' => $quantity,
            'note' => 'Koreksi manual',
        ], $extra);
    }
}
