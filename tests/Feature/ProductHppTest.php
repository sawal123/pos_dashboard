<?php

namespace Tests\Feature;

use App\Enums\CostSource;
use App\Enums\HppMethod;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductRecipeItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Inventory\HppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductHppTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_stock_receipt_with_manual_cost_source_updates_hpp_without_changing_cost(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $sugar = $this->createProduct($business, [
            'name' => 'Gula Pasir 1kg',
            'stock' => 1000,
            'cost' => 17000,
            'hpp' => 17000,
            'cost_source' => CostSource::Manual->value,
            'hpp_method' => HppMethod::Average->value,
        ]);

        // Penerimaan barang: 1000g @ Rp 19.000
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [
                    ['product_id' => $sugar->id, 'quantity' => 1000, 'unit_cost' => 19000],
                ],
                'reference_id' => 'PO-SUGAR-01',
            ])
            ->assertRedirect(route('stock.history'));

        $sugar->refresh();
        // Sisa 1000 @ 17000 + Masuk 1000 @ 19000 -> Total 2000 @ (17jt + 19jt)/2000 = 18.000
        $this->assertEquals(2000, (float) $sugar->stock);
        $this->assertEquals(18000, (float) $sugar->hpp);
        // Cost acuan tetap 17.000 karena cost_source = manual
        $this->assertEquals(17000, (float) $sugar->cost);

        // Riwayat perubahan HPP tercatat
        $this->assertDatabaseHas('product_cost_histories', [
            'product_id' => $sugar->id,
            'field' => 'hpp',
            'old_value' => '17000.00',
            'new_value' => '18000.00',
            'source' => 'receipt',
        ]);
    }

    public function test_02_stock_receipt_with_hpp_cost_source_updates_both_hpp_and_cost(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $sugar = $this->createProduct($business, [
            'name' => 'Gula Pasir 1kg',
            'stock' => 1000,
            'cost' => 17000,
            'hpp' => 17000,
            'cost_source' => CostSource::Hpp->value,
            'hpp_method' => HppMethod::Average->value,
        ]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [
                    ['product_id' => $sugar->id, 'quantity' => 1000, 'unit_cost' => 19000],
                ],
                'reference_id' => 'PO-SUGAR-02',
            ])
            ->assertRedirect(route('stock.history'));

        $sugar->refresh();
        $this->assertEquals(2000, (float) $sugar->stock);
        $this->assertEquals(18000, (float) $sugar->hpp);
        // Cost acuan ikut terupdate ke 18.000 karena cost_source = hpp
        $this->assertEquals(18000, (float) $sugar->cost);

        $this->assertDatabaseHas('product_cost_histories', [
            'product_id' => $sugar->id,
            'field' => 'cost',
            'old_value' => '17000.00',
            'new_value' => '18000.00',
            'source' => 'receipt',
        ]);
    }

    public function test_03_stock_receipt_when_initial_stock_is_zero_or_negative_uses_new_unit_cost(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $coffee = $this->createProduct($business, [
            'name' => 'Kopi Arabika',
            'stock' => 0,
            'cost' => 10000,
            'hpp' => 10000,
            'cost_source' => CostSource::Hpp->value,
            'hpp_method' => HppMethod::Average->value,
        ]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [
                    ['product_id' => $coffee->id, 'quantity' => 500, 'unit_cost' => 25000],
                ],
            ]);

        $coffee->refresh();
        $this->assertEquals(500, (float) $coffee->stock);
        $this->assertEquals(25000, (float) $coffee->hpp);
        $this->assertEquals(25000, (float) $coffee->cost);
    }

    public function test_04_recipe_calculation_with_components_and_overhead(): void
    {
        [$business] = $this->actingAsRole('owner');

        $sugar = $this->createProduct($business, [
            'name' => 'Gula',
            'unit' => 'gram',
            'cost' => 15, // 15 per gram
        ]);

        $milk = $this->createProduct($business, [
            'name' => 'Susu',
            'unit' => 'ml',
            'cost' => 20, // 20 per ml
        ]);

        $sweetMilk = $this->createProduct($business, [
            'name' => 'Susu Manis',
            'cost' => 0,
            'hpp' => 0,
            'cost_source' => CostSource::Hpp->value,
            'hpp_method' => HppMethod::Recipe->value,
            'hpp_overhead_fixed' => 500,
            'hpp_overhead_percent' => 10,
        ]);

        // Recipe: 20g gula (300) + 200ml susu (4000) = 4300
        // Overhead: fixed 500 + 10% dari 4300 (430) = 930
        // Total HPP: 4300 + 930 = 5230
        ProductRecipeItem::create([
            'business_id' => $business->id,
            'product_id' => $sweetMilk->id,
            'component_product_id' => $sugar->id,
            'quantity' => 20,
        ]);

        ProductRecipeItem::create([
            'business_id' => $business->id,
            'product_id' => $sweetMilk->id,
            'component_product_id' => $milk->id,
            'quantity' => 200,
        ]);

        $calculatedHpp = app(HppService::class)->recalculateRecipe($sweetMilk);

        $this->assertEquals(5230, $calculatedHpp);
        $sweetMilk->refresh();
        $this->assertEquals(5230, (float) $sweetMilk->hpp);
        $this->assertEquals(5230, (float) $sweetMilk->cost);
    }

    public function test_05_stock_receipt_on_ingredient_cascades_to_recipe_product(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $milk = $this->createProduct($business, [
            'name' => 'Susu Segar',
            'stock' => 1000,
            'cost' => 20,
            'hpp' => 20,
            'cost_source' => CostSource::Hpp->value,
        ]);

        $drink = $this->createProduct($business, [
            'name' => 'Es Susu',
            'cost' => 0,
            'hpp' => 0,
            'cost_source' => CostSource::Hpp->value,
            'hpp_method' => HppMethod::Recipe->value,
            'hpp_overhead_fixed' => 0,
            'hpp_overhead_percent' => 0,
        ]);

        ProductRecipeItem::create([
            'business_id' => $business->id,
            'product_id' => $drink->id,
            'component_product_id' => $milk->id,
            'quantity' => 200, // 200ml * 20 = 4000
        ]);

        app(HppService::class)->recalculateRecipe($drink);
        $drink->refresh();
        $this->assertEquals(4000, (float) $drink->cost);

        // Penerimaan barang Susu Segar dengan harga lebih mahal: 1000ml @ 40
        // New milk cost = (1000 * 20 + 1000 * 40) / 2000 = 30
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('stock.receipts.store'), [
                'lines' => [
                    ['product_id' => $milk->id, 'quantity' => 1000, 'unit_cost' => 40],
                ],
            ]);

        $milk->refresh();
        $this->assertEquals(30, (float) $milk->cost);

        // Recipe produk Es Susu otomatis ter-cascade terupdate: 200ml * 30 = 6000
        $drink->refresh();
        $this->assertEquals(6000, (float) $drink->hpp);
        $this->assertEquals(6000, (float) $drink->cost);
    }

    public function test_06_prevent_circular_dependency_in_recipe(): void
    {
        [$business] = $this->actingAsRole('owner');

        $productA = $this->createProduct($business, ['name' => 'Produk A']);
        $productB = $this->createProduct($business, ['name' => 'Produk B']);

        ProductRecipeItem::create([
            'business_id' => $business->id,
            'product_id' => $productA->id,
            'component_product_id' => $productB->id,
            'quantity' => 1,
        ]);

        // Coba masukkan Product A ke resep Product B (circular dependency A -> B -> A)
        $this->expectException(ValidationException::class);
        app(HppService::class)->assertNoCircularDependency($productB->id, [$productA->id]);
    }

    public function test_07_apply_hpp_endpoint_copies_hpp_to_cost_and_records_history(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $product = $this->createProduct($business, [
            'name' => 'Produk Manual',
            'cost' => 15000,
            'hpp' => 18500,
            'cost_source' => CostSource::Manual->value,
        ]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('products.apply-hpp', ['productId' => $product->id]))
            ->assertRedirect();

        $product->refresh();
        $this->assertEquals(18500, (float) $product->cost);

        $this->assertDatabaseHas('product_cost_histories', [
            'product_id' => $product->id,
            'field' => 'cost',
            'old_value' => '15000.00',
            'new_value' => '18500.00',
            'source' => 'apply_hpp',
            'user_id' => $user->id,
        ]);
    }

    public function test_08_crud_stores_and_updates_product_with_recipe_and_hpp_settings(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $ingredient = $this->createProduct($business, [
            'name' => 'Biji Kopi',
            'cost' => 200,
            'unit' => 'gram',
        ]);

        // Create product with recipe
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('products.store'), [
                'kind' => 'product',
                'name' => 'Americano Dingin',
                'sku' => 'SKU-AMER-01',
                'price' => 25000,
                'cost' => 0,
                'cost_source' => CostSource::Hpp->value,
                'hpp_method' => HppMethod::Recipe->value,
                'hpp_overhead_fixed' => 1000,
                'hpp_overhead_percent' => 5,
                'recipe_items' => [
                    ['component_product_id' => $ingredient->id, 'quantity' => 20],
                ],
            ])
            ->assertRedirect();

        $created = Product::query()->where('sku', 'SKU-AMER-01')->firstOrFail();
        // 20 * 200 = 4000. Fixed 1000 + 5% (200) = 1200. Total = 5200.
        $this->assertEquals(5200, (float) $created->hpp);
        $this->assertEquals(5200, (float) $created->cost);
        $this->assertCount(1, $created->recipeItems);

        // Update product to increase overhead fixed to 2000
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('products.update', ['productId' => $created->id]), [
                'kind' => 'product',
                'name' => 'Americano Dingin (Revisi)',
                'sku' => 'SKU-AMER-01',
                'price' => 28000,
                'cost' => 0,
                'cost_source' => CostSource::Hpp->value,
                'hpp_method' => HppMethod::Recipe->value,
                'hpp_overhead_fixed' => 2000,
                'hpp_overhead_percent' => 5,
                'recipe_items' => [
                    ['component_product_id' => $ingredient->id, 'quantity' => 20],
                ],
            ])
            ->assertRedirect();

        $created->refresh();
        // 4000 + 2000 + 200 = 6200
        $this->assertEquals(6200, (float) $created->hpp);
        $this->assertEquals(6200, (float) $created->cost);
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
            'hpp' => 1000,
            'cost_source' => CostSource::Manual->value,
            'hpp_method' => HppMethod::Average->value,
        ], $attributes));
    }
}
