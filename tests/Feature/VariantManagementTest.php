<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\User;
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

    public function test_owner_can_create_variant_group_via_http(): void
    {
        [$business] = $this->actingAsRole('owner');

        $payload = [
            'name' => 'Topping Minuman',
            'selection_type' => 'multiple',
            'is_required' => false,
            'min_selection' => 0,
            'max_selection' => 3,
            'options' => [
                [
                    'name' => 'Boba Pearl',
                    'price_adjustment' => 4000,
                    'is_default' => false,
                ],
                [
                    'name' => 'Egg Pudding',
                    'price_adjustment' => 5000,
                    'is_default' => false,
                ],
            ],
        ];

        $response = $this->post(route('products.variant-groups.store'), $payload);
        $response->assertRedirect(route('products.index', ['tab' => 'variants']));

        $this->assertDatabaseHas('variant_groups', [
            'business_id' => $business->id,
            'name' => 'Topping Minuman',
            'selection_type' => 'multiple',
            'max_selection' => 3,
        ]);

        $group = VariantGroup::where('business_id', $business->id)->where('name', 'Topping Minuman')->firstOrFail();
        $this->assertCount(2, $group->options);
        $this->assertDatabaseHas('variant_options', [
            'variant_group_id' => $group->id,
            'name' => 'Boba Pearl',
            'price_adjustment' => 4000,
        ]);
    }

    public function test_owner_can_update_variant_group_via_http(): void
    {
        [$business] = $this->actingAsRole('owner');

        $group = VariantGroup::create([
            'business_id' => $business->id,
            'name' => 'Ukuran Gelas',
            'selection_type' => 'single',
            'is_required' => true,
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Ukuran Cup (Updated)',
            'selection_type' => 'single',
            'is_required' => true,
            'options' => [
                [
                    'name' => 'Regular',
                    'price_adjustment' => 0,
                    'is_default' => true,
                ],
                [
                    'name' => 'Large',
                    'price_adjustment' => 6000,
                    'is_default' => false,
                ],
            ],
        ];

        $response = $this->patch(route('products.variant-groups.update', ['groupId' => $group->id]), $payload);
        $response->assertRedirect(route('products.index', ['tab' => 'variants']));

        $group->refresh();
        $this->assertSame('Ukuran Cup (Updated)', $group->name);
        $this->assertCount(2, $group->options);
    }

    public function test_owner_can_delete_variant_group_via_http(): void
    {
        [$business] = $this->actingAsRole('owner');

        $group = VariantGroup::create([
            'business_id' => $business->id,
            'name' => 'Mau Dihapus',
            'selection_type' => 'single',
            'is_required' => false,
            'status' => 'active',
        ]);

        $response = $this->delete(route('products.variant-groups.destroy', ['groupId' => $group->id]));
        $response->assertRedirect(route('products.index', ['tab' => 'variants']));

        $this->assertSame('deleted', $group->fresh()->status);
    }

    public function test_cashier_cannot_mutate_variant_group(): void
    {
        [$business] = $this->actingAsRole('cashier');

        $this->post(route('products.variant-groups.store'), [
            'name' => 'Grup Ilegal',
            'selection_type' => 'single',
            'options' => [['name' => 'Opsi 1']],
        ])->assertForbidden();
    }

    public function test_can_assign_variant_groups_to_product_via_http(): void
    {
        [$business] = $this->actingAsRole('owner');

        $group1 = VariantGroup::create([
            'business_id' => $business->id,
            'name' => 'Suhu',
            'selection_type' => 'single',
            'is_required' => true,
            'status' => 'active',
        ]);

        $group2 = VariantGroup::create([
            'business_id' => $business->id,
            'name' => 'Level Gula',
            'selection_type' => 'single',
            'is_required' => true,
            'status' => 'active',
        ]);

        // Create product with variant_group_ids
        $response = $this->post(route('products.store'), [
            'kind' => 'product',
            'name' => 'Kopi Susu Gula Aren',
            'sku' => 'KPS-AREN-1',
            'price' => 18000,
            'is_sellable' => 1,
            'variant_group_ids' => [$group1->id, $group2->id],
        ]);
        $response->assertRedirect();

        $product = Product::where('business_id', $business->id)->where('sku', 'KPS-AREN-1')->firstOrFail();
        $this->assertCount(2, $product->variantGroups);
        $this->assertTrue($product->variantGroups->pluck('id')->contains($group1->id));
        $this->assertTrue($product->variantGroups->pluck('id')->contains($group2->id));
    }

    public function test_can_view_variants_tab_on_products_page(): void
    {
        [$business] = $this->actingAsRole('owner');

        VariantGroup::create([
            'business_id' => $business->id,
            'name' => 'Level Es',
            'selection_type' => 'single',
            'is_required' => true,
            'status' => 'active',
        ]);

        $response = $this->get(route('products.index', ['tab' => 'variants']));
        $response->assertOk();
        $response->assertSee('Template Varian');
        $response->assertSee('Level Es');
    }

    public function test_free_account_cannot_create_variant_group(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        $response = $this->post(route('products.variant-groups.store'), [
            'name' => 'Topping Free',
            'selection_type' => 'single',
            'options' => [['name' => 'Opsi 1']],
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('variant_groups', ['name' => 'Topping Free']);
    }

    public function test_free_account_cannot_assign_variant_groups_to_product(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $business->id]);
        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        $response = $this->post(route('products.store'), [
            'kind' => 'product',
            'name' => 'Kopi Free',
            'sku' => 'KPS-FREE-1',
            'price' => 15000,
            'is_sellable' => 1,
            'variant_group_ids' => [1, 2],
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role, array $businessAttributes = []): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create($businessAttributes);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $business->users()->attach($user->id, ['role' => $role]);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }
}
