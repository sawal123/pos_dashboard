<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_stock_value_sums_stock_times_cost(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $this->createProduct($business, ['stock' => 10, 'cost' => 1000]);
        $this->createProduct($business, ['stock' => 5, 'cost' => 2000]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['stock_value'] === 20000.0);
    }

    public function test_02_dead_stock_counts_products_without_recent_sales(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $dead = $this->createProduct($business, ['stock' => 5]);
        $live = $this->createProduct($business, ['stock' => 5]);

        $this->createMovement($business, $live, [
            'movement_type' => 'sale',
            'quantity_change' => '-1.000',
            'occurred_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['dead_stock_count'] === 1);
        $this->assertNotSame($dead->id, null);
    }

    public function test_03_shrinkage_rate_uses_posted_opname_variance(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 100]);

        $opname = StockOpname::create([
            'business_id' => $business->id,
            'code' => 'OPN-TEST-0001',
            'status' => StockOpname::STATUS_POSTED,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        StockOpnameItem::create([
            'business_id' => $business->id,
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_stock' => '100.000',
            'counted_stock' => '90.000',
            'variance' => '-10.000',
            'unit_cost' => '1000.00',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['shrinkage_rate'] === 10.0);
    }

    public function test_04_days_of_cover_uses_recent_outflow(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $product = $this->createProduct($business, ['stock' => 30]);

        $this->createMovement($business, $product, [
            'movement_type' => 'sale',
            'quantity_change' => '-30.000',
            'occurred_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['days_of_cover'] === 30.0);
    }

    public function test_05_days_of_cover_is_null_without_outflow(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $this->createProduct($business, ['stock' => 10]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('stock.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['days_of_cover'] === null);
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
     * @param  array<string, mixed>  $attributes
     */
    private function createMovement(Business $business, Product $product, array $attributes = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'movement_type' => 'sale',
            'quantity_change' => '-1.000',
            'stock_before' => '10.000',
            'stock_after' => '9.000',
            'occurred_at' => now(),
        ], $attributes));
    }
}
