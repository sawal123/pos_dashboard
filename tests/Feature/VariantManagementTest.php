<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\VariantGroup;
use App\Models\VariantOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantManagementTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->create();
    }

    public function test_can_create_variant_group_with_sync_metadata(): void
    {
        $group = VariantGroup::create([
            'business_id' => $this->business->id,
            'name' => 'Tingkat Gula',
            'selection_type' => 'single',
            'is_required' => true,
            'min_selection' => 1,
            'max_selection' => 1,
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('variant_groups', [
            'id' => $group->id,
            'name' => 'Tingkat Gula',
            'selection_type' => 'single',
            'is_required' => true,
        ]);

        $this->assertNotEmpty($group->sync_id);
        $this->assertEquals(1, $group->sync_version);
        $this->assertGreaterThan(0, $group->sync_sequence);
    }

    public function test_can_create_variant_options_with_ingredient_binding(): void
    {
        $sugarIngredient = Product::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Gula Pasir (Bahan Baku)',
            'is_sellable' => false,
            'stock' => 1000,
            'unit' => 'gr',
        ]);

        $group = VariantGroup::create([
            'business_id' => $this->business->id,
            'name' => 'Tingkat Gula',
            'selection_type' => 'single',
        ]);

        $noSugar = VariantOption::create([
            'business_id' => $this->business->id,
            'variant_group_id' => $group->id,
            'name' => 'No Sugar',
            'price_adjustment' => 0,
            'ingredient_product_id' => null,
            'ingredient_quantity' => 0,
            'is_default' => false,
            'sort_order' => 1,
        ]);

        $normalSugar = VariantOption::create([
            'business_id' => $this->business->id,
            'variant_group_id' => $group->id,
            'name' => 'Normal Sugar',
            'price_adjustment' => 0,
            'ingredient_product_id' => $sugarIngredient->id,
            'ingredient_quantity' => 20.0,
            'is_default' => true,
            'sort_order' => 2,
        ]);

        $this->assertCount(2, $group->options);
        $this->assertEquals('Normal Sugar', $group->options->where('is_default', true)->first()->name);
        $this->assertEquals($sugarIngredient->id, $normalSugar->ingredientProduct->id);
        $this->assertEquals('20.0000', (string) $normalSugar->ingredient_quantity);
    }

    public function test_can_assign_variant_group_to_product(): void
    {
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Americano',
            'price' => 20000,
            'is_sellable' => true,
        ]);

        $sugarGroup = VariantGroup::create([
            'business_id' => $this->business->id,
            'name' => 'Tingkat Gula',
            'selection_type' => 'single',
        ]);

        $sizeGroup = VariantGroup::create([
            'business_id' => $this->business->id,
            'name' => 'Ukuran Cup',
            'selection_type' => 'single',
        ]);

        $product->variantGroups()->attach([
            $sugarGroup->id => ['business_id' => $this->business->id, 'sort_order' => 1],
            $sizeGroup->id => ['business_id' => $this->business->id, 'sort_order' => 2],
        ]);

        $this->assertCount(2, $product->fresh()->variantGroups);
        $this->assertEquals(['Tingkat Gula', 'Ukuran Cup'], $product->fresh()->variantGroups->pluck('name')->all());
    }

    public function test_can_store_sale_item_with_variant_snapshot(): void
    {
        $product = Product::factory()->create([
            'business_id' => $this->business->id,
            'name' => 'Americano',
            'price' => 20000,
        ]);

        $outlet = Outlet::create([
            'business_id' => $this->business->id,
            'name' => 'Outlet Test',
            'code' => 'OUT-01',
            'status' => 'active',
        ]);

        $sale = Sale::create([
            'business_id' => $this->business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-VAR-001',
            'status' => 'completed',
            'subtotal' => 25000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 25000,
            'sold_at' => now(),
        ]);

        $saleItem = SaleItem::create([
            'business_id' => $this->business->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'variant_summary' => 'Less Sugar, Large (+5.000)',
            'variants_snapshot' => [
                [
                    'group_name' => 'Tingkat Gula',
                    'option_name' => 'Less Sugar',
                    'price_adjustment' => 0,
                ],
                [
                    'group_name' => 'Ukuran Cup',
                    'option_name' => 'Large',
                    'price_adjustment' => 5000,
                ],
            ],
            'unit_price' => 25000,
            'quantity' => 1,
            'line_total' => 25000,
            'cost_snapshot' => 8000,
            'line_cost' => 8000,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'id' => $saleItem->id,
            'variant_summary' => 'Less Sugar, Large (+5.000)',
        ]);

        $fresh = $saleItem->fresh();
        $this->assertIsArray($fresh->variants_snapshot);
        $this->assertCount(2, $fresh->variants_snapshot);
        $this->assertEquals('Less Sugar', $fresh->variants_snapshot[0]['option_name']);
        $this->assertEquals(5000, $fresh->variants_snapshot[1]['price_adjustment']);
    }
}
