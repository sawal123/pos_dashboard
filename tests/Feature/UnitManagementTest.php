<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_owner_can_create_a_unit_via_ajax(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('products.units.store'), [
                'name' => 'cup',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'unit' => [
                    'name' => 'cup',
                ],
            ]);

        $this->assertDatabaseHas('units', [
            'business_id' => $business->id,
            'name' => 'cup',
            'status' => 'active',
        ]);
    }

    public function test_02_units_are_normalized_lowercase_trimmed(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('products.units.store'), [
                'name' => '  PORSI  ',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('units', [
            'business_id' => $business->id,
            'name' => 'porsi',
        ]);
    }

    public function test_03_duplicate_unit_creation_returns_existing(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        Unit::create([
            'business_id' => $business->id,
            'name' => 'liter',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('products.units.store'), [
                'name' => 'liter',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'unit' => [
                    'name' => 'liter',
                ],
            ]);

        $this->assertSame(1, Unit::query()->where('business_id', $business->id)->where('name', 'liter')->count());
    }

    public function test_04_creating_a_product_with_new_unit_auto_persists_to_units_table(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('products.store'), [
                'kind' => 'product',
                'name' => 'Sirup Vanila',
                'sku' => 'RAW-SIRUP',
                'price' => 0,
                'cost' => 50000,
                'stock' => 10,
                'min_stock' => 2,
                'unit' => 'botol',
                'is_sellable' => '0',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('units', [
            'business_id' => $business->id,
            'name' => 'botol',
        ]);
    }

    public function test_05_cashier_without_manage_permission_cannot_create_units(): void
    {
        [$business, $user] = $this->actingAsRole('cashier');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('products.units.store'), [
                'name' => 'pack',
            ]);

        $response->assertForbidden();
    }

    public function test_06_product_index_renders_units(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        Unit::create([
            'business_id' => $business->id,
            'name' => 'kaleng',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('products.index', ['tab' => 'supplies']));

        $response->assertOk();
        $response->assertSee('kaleng');
        $response->assertSee('Tambah Satuan');
    }

    public function test_07_catalog_modal_contains_recipe_section_and_unit_container_configured(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('products.index', ['tab' => 'products']));

        $response->assertOk();
        $response->assertSee('catalogRecipeSection', false);
        $response->assertSee('Komposisi Bahan Baku (Resep)');
        $response->assertSee('catalogProductUnitContainer', false);
        $response->assertSee('catalogAddRecipeItemBtn', false);
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
}

