<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DASH-15 — product, service & category management regression suite.
 *
 * Covers owner-only mutation, read-only member/cashier, cross-tenant scoping,
 * uniqueness per business, decimal/negative stock rules, sync metadata,
 * historical snapshot safety and the no-hard-delete contract.
 */
class ProductsCrudTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Authorization / RBAC
    // ============================================================

    public function test_01_guests_cannot_mutate_the_catalog(): void
    {
        $this->post(route('products.store'), ['name' => 'X', 'price' => 1000, 'kind' => 'product', 'sku' => 'X-1'])
            ->assertRedirect(route('login'));
    }

    public function test_02_member_and_cashier_cannot_mutate(): void
    {
        foreach (['member', 'cashier'] as $role) {
            [$business, $actor] = $this->actingAsRole($role);

            $this->post(route('products.store'), [
                'kind' => 'product',
                'name' => 'Produk '.$role,
                'sku' => 'SKU-'.$role,
                'price' => 5000,
            ])->assertForbidden();

            $this->post(route('products.categories.store'), ['name' => 'Kategori '.$role])
                ->assertForbidden();

            $product = Product::factory()->create(['business_id' => $business->id, 'status' => 'active']);
            $this->patch(route('products.update', ['productId' => $product->id]), [
                'name' => 'Diubah', 'price' => 9999,
            ])->assertForbidden();
            $this->patch(route('products.status.update', ['productId' => $product->id]), ['status' => 'inactive'])
                ->assertForbidden();

            $this->assertSame(0, Product::where('business_id', $business->id)->where('name', 'Produk '.$role)->count());
            $this->assertSame(0, Category::where('business_id', $business->id)->where('name', 'Kategori '.$role)->count());
        }
    }

    public function test_03_unknown_role_is_denied(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($user->id, ['role' => 'supervisor']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('products.store'), ['kind' => 'product', 'name' => 'X', 'sku' => 'X-1', 'price' => 1000])
            ->assertForbidden();
    }

    public function test_04_business_type_never_grants_mutation_access(): void
    {
        $business = Business::factory()->create(['business_type' => 'grosir']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($user->id, ['role' => 'cashier']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('products.store'), ['kind' => 'product', 'name' => 'X', 'sku' => 'X-1', 'price' => 1000])
            ->assertForbidden();
    }

    // ============================================================
    // Category CRUD
    // ============================================================

    public function test_05_owner_creates_and_updates_a_category(): void
    {
        [$business] = $this->actingAsRole('owner');

        $this->post(route('products.categories.store'), ['name' => 'Minuman'])
            ->assertRedirect(route('products.index', ['tab' => 'categories']));

        $category = Category::where('business_id', $business->id)->firstOrFail();
        $this->assertSame('Minuman', $category->name);
        $this->assertSame('active', $category->status);

        $this->patch(route('products.categories.update', ['categoryId' => $category->id]), ['name' => 'Minuman Dingin'])
            ->assertRedirect(route('products.index', ['tab' => 'categories']));

        $this->assertSame('Minuman Dingin', $category->fresh()->name);
    }

    public function test_06_duplicate_category_name_is_rejected_per_business(): void
    {
        [$business] = $this->actingAsRole('owner');
        Category::factory()->create(['business_id' => $business->id, 'name' => 'Sembako']);

        $this->post(route('products.categories.store'), ['name' => 'Sembako'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::where('business_id', $business->id)->where('name', 'Sembako')->count());
    }

    public function test_07_same_category_name_is_allowed_in_another_business(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $businessB = Business::factory()->create();
        Category::factory()->create(['business_id' => $businessB->id, 'name' => 'Sembako']);

        $this->post(route('products.categories.store'), ['name' => 'Sembako'])->assertRedirect();

        $this->assertSame(1, Category::where('business_id', $businessA->id)->where('name', 'Sembako')->count());
    }

    public function test_08_category_status_toggles_and_deleted_is_rejected(): void
    {
        [$business] = $this->actingAsRole('owner');
        $category = Category::factory()->create(['business_id' => $business->id, 'status' => 'active']);

        $this->patch(route('products.categories.status.update', ['categoryId' => $category->id]), ['status' => 'inactive'])
            ->assertRedirect();
        $this->assertSame('inactive', $category->fresh()->status);

        $this->from(route('products.index'))
            ->patch(route('products.categories.status.update', ['categoryId' => $category->id]), ['status' => 'deleted'])
            ->assertSessionHasErrors('status');

        $this->assertSame('inactive', $category->fresh()->status);
    }

    public function test_09_cross_tenant_category_mutation_returns_404(): void
    {
        $this->actingAsRole('owner');
        $foreign = Category::factory()->create(['business_id' => Business::factory()->create()->id, 'name' => 'Foreign']);

        $this->patch(route('products.categories.update', ['categoryId' => $foreign->id]), ['name' => 'Hijack'])
            ->assertNotFound();
        $this->patch(route('products.categories.status.update', ['categoryId' => $foreign->id]), ['status' => 'inactive'])
            ->assertNotFound();

        $this->assertSame('Foreign', $foreign->fresh()->name);
        $this->assertSame('active', $foreign->fresh()->status);
    }

    // ============================================================
    // Product & service CRUD
    // ============================================================

    public function test_10_owner_creates_a_product_with_decimal_fields_and_sync_metadata(): void
    {
        [$business] = $this->actingAsRole('owner');
        $category = Category::factory()->create(['business_id' => $business->id]);

        $this->post(route('products.store'), [
            'kind' => 'product',
            'name' => 'Kopi Susu',
            'sku' => 'KOPI-01',
            'barcode' => '8991234567890',
            'category_id' => $category->id,
            'price' => 18000,
            'cost' => '9000.50',
            'stock' => '12.5',
            'unit' => 'kg',
            'min_stock' => '2.25',
        ])->assertRedirect(route('products.index', ['tab' => 'products']));

        $product = Product::where('business_id', $business->id)->where('sku', 'KOPI-01')->firstOrFail();
        $this->assertSame('product', $product->kind);
        $this->assertSame(18000, $product->price);
        $this->assertSame('9000.50', $product->cost);
        $this->assertSame('12.500', $product->stock);
        $this->assertSame('2.250', $product->min_stock);
        $this->assertSame('kg', $product->unit);

        // Sync contract: identity minted, version 1, a real (non-zero) sequence.
        $this->assertTrue(Str::isUuid($product->sync_id));
        $this->assertSame(1, $product->sync_version);
        $this->assertGreaterThan(0, $product->sync_sequence);
    }

    public function test_11_owner_creates_a_service_and_sku_is_generated_when_blank(): void
    {
        [$business] = $this->actingAsRole('owner');

        $this->post(route('products.store'), [
            'kind' => 'service',
            'name' => 'Cuci Kiloan',
            'price' => 7000,
            'pricing_unit' => 'kg',
            'unit' => 'kg',
            'min_quantity' => '2.5',
            'estimated_duration' => '2 hari',
        ])->assertRedirect(route('products.index', ['tab' => 'services']));

        $service = Product::where('business_id', $business->id)->firstOrFail();
        $this->assertSame('service', $service->kind);
        $this->assertStringStartsWith('SRV-', $service->sku);
        $this->assertSame('kg', $service->pricing_unit);
        $this->assertSame('2.500', $service->min_quantity);
        $this->assertSame('2 hari', $service->estimated_duration);
    }

    public function test_12_duplicate_sku_and_barcode_are_rejected_per_business(): void
    {
        [$business] = $this->actingAsRole('owner');
        Product::factory()->create(['business_id' => $business->id, 'sku' => 'DUP-1', 'barcode' => '111222']);

        $this->post(route('products.store'), ['kind' => 'product', 'name' => 'A', 'sku' => 'DUP-1', 'price' => 1000])
            ->assertSessionHasErrors('sku');
        $this->post(route('products.store'), ['kind' => 'product', 'name' => 'B', 'sku' => 'NEW-1', 'barcode' => '111222', 'price' => 1000])
            ->assertSessionHasErrors('barcode');

        $this->assertSame(1, Product::where('business_id', $business->id)->count());
    }

    public function test_13_same_sku_is_allowed_in_another_business(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        Product::factory()->create(['business_id' => Business::factory()->create()->id, 'sku' => 'SHARED-1']);

        $this->post(route('products.store'), ['kind' => 'product', 'name' => 'A', 'sku' => 'SHARED-1', 'price' => 1000])
            ->assertRedirect();

        $this->assertSame(1, Product::where('business_id', $businessA->id)->where('sku', 'SHARED-1')->count());
    }

    public function test_14_update_never_overwrites_movement_driven_stock_or_creates_movements(): void
    {
        [$business] = $this->actingAsRole('owner');
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'kind' => 'product',
            'sku' => 'UPD-1',
            'price' => 10000,
            'cost' => '5000.00',
            'stock' => '7.000',
            'status' => 'active',
        ]);

        $this->patch(route('products.update', ['productId' => $product->id]), [
            'name' => 'Harga Baru',
            'sku' => 'UPD-1',
            'price' => 15000,
            'cost' => '6000.00',
            'min_stock' => '3.000',
            'stock' => '999.000',
        ])->assertRedirect(route('products.index', ['tab' => 'products']));

        $product->refresh();
        $this->assertSame(15000, $product->price);
        $this->assertSame('6000.00', $product->cost);
        $this->assertSame('3.000', $product->min_stock);
        // Stock is authoritative via movements — the catalog form cannot set it.
        $this->assertSame('7.000', $product->stock);
        $this->assertSame(0, StockMovement::where('business_id', $business->id)->count());
    }

    public function test_15_update_a_service_field_set(): void
    {
        [$business] = $this->actingAsRole('owner');
        $service = Product::factory()->create([
            'business_id' => $business->id,
            'kind' => 'service',
            'sku' => 'SRV-UPD',
            'pricing_unit' => 'pcs',
            'min_quantity' => '0.000',
        ]);

        $this->patch(route('products.update', ['productId' => $service->id]), [
            'name' => 'Express',
            'price' => 12000,
            'pricing_unit' => 'kg',
            'unit' => 'kg',
            'min_quantity' => '1.5',
            'estimated_duration' => '1 hari',
        ])->assertRedirect(route('products.index', ['tab' => 'services']));

        $service->refresh();
        $this->assertSame('Express', $service->name);
        $this->assertSame('kg', $service->pricing_unit);
        $this->assertSame('1.500', $service->min_quantity);
        $this->assertSame('1 hari', $service->estimated_duration);
    }

    public function test_16_cross_tenant_product_mutation_returns_404(): void
    {
        $this->actingAsRole('owner');
        $foreign = Product::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->patch(route('products.update', ['productId' => $foreign->id]), ['name' => 'Hijack', 'price' => 1])
            ->assertNotFound();
        $this->patch(route('products.status.update', ['productId' => $foreign->id]), ['status' => 'inactive'])
            ->assertNotFound();
    }

    // ============================================================
    // Validation & tenant scoping
    // ============================================================

    public function test_17_invalid_price_is_rejected(): void
    {
        $this->actingAsRole('owner');

        $this->from(route('products.index'))
            ->post(route('products.store'), ['kind' => 'product', 'name' => 'X', 'sku' => 'P-1', 'price' => 'abc'])
            ->assertSessionHasErrors('price');

        $this->from(route('products.index'))
            ->post(route('products.store'), ['kind' => 'product', 'name' => 'X', 'sku' => 'P-2', 'price' => -100])
            ->assertSessionHasErrors('price');
    }

    public function test_18_foreign_category_id_is_rejected_on_create(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $foreignCategory = Category::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->from(route('products.index'))
            ->post(route('products.store'), [
                'kind' => 'product',
                'name' => 'X',
                'sku' => 'CAT-1',
                'price' => 1000,
                'category_id' => $foreignCategory->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Product::where('business_id', $businessA->id)->count());
    }

    public function test_19_forged_business_id_is_ignored(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $businessB = Business::factory()->create();

        $this->post(route('products.store'), [
            'kind' => 'product',
            'name' => 'Scoped',
            'sku' => 'SCOPE-1',
            'price' => 1000,
            'business_id' => $businessB->id,
        ])->assertRedirect();

        $this->assertSame(1, Product::where('business_id', $businessA->id)->where('sku', 'SCOPE-1')->count());
        $this->assertSame(0, Product::where('business_id', $businessB->id)->count());
    }

    // ============================================================
    // Stock rules
    // ============================================================

    public function test_20_negative_initial_stock_is_allowed(): void
    {
        [$business] = $this->actingAsRole('owner');

        $this->post(route('products.store'), [
            'kind' => 'product',
            'name' => 'Minus',
            'sku' => 'NEG-1',
            'price' => 1000,
            'stock' => '-5.25',
        ])->assertRedirect();

        $this->assertSame('-5.250', Product::where('business_id', $business->id)->where('sku', 'NEG-1')->value('stock'));
    }

    public function test_21_laundry_fractional_service_keeps_three_decimals(): void
    {
        [$business] = $this->actingAsRole('owner', ['business_type' => 'laundry']);

        $this->post(route('products.store'), [
            'kind' => 'service',
            'name' => 'Kiloan Reguler',
            'price' => 6000,
            'pricing_unit' => 'kg',
            'unit' => 'kg',
            'min_quantity' => '2.25',
        ])->assertRedirect();

        $service = Product::where('business_id', $business->id)->firstOrFail();
        $this->assertSame('2.250', $service->min_quantity);
        $this->assertSame('kg', $service->pricing_unit);
    }

    // ============================================================
    // Sync metadata & lifecycle
    // ============================================================

    public function test_22_updates_advance_sync_version_and_sequence(): void
    {
        [$business] = $this->actingAsRole('owner');
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product', 'sku' => 'SYNC-1']);

        $versionBefore = $product->sync_version;
        $sequenceBefore = $product->sync_sequence;

        $this->patch(route('products.update', ['productId' => $product->id]), [
            'name' => 'Updated', 'sku' => 'SYNC-1', 'price' => 12345,
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame($versionBefore + 1, $product->sync_version);
        $this->assertGreaterThan($sequenceBefore, $product->sync_sequence);
    }

    public function test_23_status_toggle_is_active_inactive_only_and_versioned(): void
    {
        [$business] = $this->actingAsRole('owner');
        $product = Product::factory()->create(['business_id' => $business->id, 'status' => 'active']);

        $this->patch(route('products.status.update', ['productId' => $product->id]), ['status' => 'inactive'])
            ->assertRedirect();
        $product->refresh();
        $this->assertSame('inactive', $product->status);

        $this->from(route('products.index'))
            ->patch(route('products.status.update', ['productId' => $product->id]), ['status' => 'deleted'])
            ->assertSessionHasErrors('status');
        $this->assertSame('inactive', $product->fresh()->status);
    }

    public function test_24_updating_price_does_not_touch_historical_sale_snapshots(): void
    {
        [$business] = $this->actingAsRole('owner');
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product', 'sku' => 'HIST-1', 'price' => 100000]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $saleId = DB::table('sales')->insertGetId([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-HIST-1',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('sale_items')->insertGetId([
            'business_id' => $business->id,
            'sale_id' => $saleId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => 'HIST-1',
            'unit_price' => 100000,
            'quantity' => 1,
            'line_total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patch(route('products.update', ['productId' => $product->id]), [
            'name' => $product->name, 'sku' => 'HIST-1', 'price' => 250000, 'cost' => '50000.00',
        ])->assertRedirect();

        $this->assertSame(250000, $product->fresh()->price);
        $this->assertSame(100000, (int) DB::table('sale_items')->where('id', $itemId)->value('unit_price'));
        $this->assertDatabaseHas('sale_items', ['id' => $itemId, 'product_sku' => 'HIST-1']);
    }

    public function test_25_repeated_submit_does_not_create_duplicates(): void
    {
        [$business] = $this->actingAsRole('owner');
        $payload = ['kind' => 'product', 'name' => 'Double', 'sku' => 'DOUBLE-1', 'price' => 1000];

        $this->post(route('products.store'), $payload)->assertRedirect();
        $this->from(route('products.index'))->post(route('products.store'), $payload)->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::where('business_id', $business->id)->where('sku', 'DOUBLE-1')->count());
    }

    // ============================================================
    // Listing integration
    // ============================================================

    public function test_26_created_item_appears_in_the_filtered_listing(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');

        $this->post(route('products.store'), [
            'kind' => 'product', 'name' => 'Matcha Latte', 'sku' => 'MATCHA-1', 'price' => 22000,
        ])->assertRedirect();

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('products.index', ['q' => 'Matcha']));

        $response->assertOk();
        $response->assertSee('Matcha Latte');
        $response->assertSee('MATCHA-1');
    }

    public function test_27_category_mutation_does_not_affect_foreign_tenant_data(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $businessB = Business::factory()->create();
        $foreign = Category::factory()->create(['business_id' => $businessB->id, 'name' => 'B Only']);

        $this->post(route('products.categories.store'), ['name' => 'A Only'])->assertRedirect();

        $this->assertSame(1, Category::where('business_id', $businessA->id)->count());
        $this->assertSame('B Only', $foreign->fresh()->name);
        $this->assertSame(1, Category::where('business_id', $businessB->id)->count());
    }

    // ============================================================
    // Service SKU uniqueness (must be a validation error, never a 500)
    // ============================================================

    public function test_28_duplicate_service_sku_in_the_same_business_is_rejected(): void
    {
        [$business] = $this->actingAsRole('owner');
        Product::factory()->create(['business_id' => $business->id, 'kind' => 'service', 'sku' => 'SRV-DUP']);

        $this->from(route('products.index'))
            ->post(route('products.store'), [
                'kind' => 'service',
                'name' => 'Layanan Kedua',
                'sku' => 'SRV-DUP',
                'price' => 5000,
            ])
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::where('business_id', $business->id)->where('sku', 'SRV-DUP')->count());
    }

    public function test_29_same_service_sku_is_allowed_in_another_business(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        Product::factory()->create([
            'business_id' => Business::factory()->create()->id,
            'kind' => 'service',
            'sku' => 'SRV-SHARE',
        ]);

        $this->post(route('products.store'), [
            'kind' => 'service',
            'name' => 'Layanan',
            'sku' => 'SRV-SHARE',
            'price' => 5000,
        ])->assertRedirect();

        $this->assertSame(1, Product::where('business_id', $businessA->id)->where('sku', 'SRV-SHARE')->count());
    }

    public function test_30_service_sku_colliding_with_a_product_sku_is_rejected(): void
    {
        [$business] = $this->actingAsRole('owner');
        Product::factory()->create(['business_id' => $business->id, 'kind' => 'product', 'sku' => 'MIX-1']);

        $this->from(route('products.index'))
            ->post(route('products.store'), [
                'kind' => 'service',
                'name' => 'Layanan Bentrok',
                'sku' => 'MIX-1',
                'price' => 5000,
            ])
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::where('business_id', $business->id)->where('sku', 'MIX-1')->count());
    }

    public function test_31_editing_a_service_without_changing_its_sku_is_allowed(): void
    {
        [$business] = $this->actingAsRole('owner');
        $service = Product::factory()->create([
            'business_id' => $business->id,
            'kind' => 'service',
            'sku' => 'SRV-KEEP',
            'pricing_unit' => 'pcs',
        ]);

        $this->patch(route('products.update', ['productId' => $service->id]), [
            'name' => 'Nama Layanan Baru',
            'sku' => 'SRV-KEEP',
            'price' => 9000,
            'pricing_unit' => 'kg',
        ])->assertRedirect(route('products.index', ['tab' => 'services']));

        $service->refresh();
        $this->assertSame('SRV-KEEP', $service->sku);
        $this->assertSame('Nama Layanan Baru', $service->name);
        $this->assertSame('kg', $service->pricing_unit);
    }

    public function test_32_updating_a_service_to_a_duplicate_sku_is_rejected(): void
    {
        [$business] = $this->actingAsRole('owner');
        Product::factory()->create(['business_id' => $business->id, 'kind' => 'service', 'sku' => 'SRV-A']);
        $service = Product::factory()->create(['business_id' => $business->id, 'kind' => 'service', 'sku' => 'SRV-B']);

        $this->from(route('products.index'))
            ->patch(route('products.update', ['productId' => $service->id]), [
                'name' => $service->name,
                'sku' => 'SRV-A',
                'price' => 5000,
            ])
            ->assertSessionHasErrors('sku');

        $this->assertSame('SRV-B', $service->fresh()->sku);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @param  array<string, mixed>  $businessAttributes
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role, array $businessAttributes = []): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create($businessAttributes);
        Subscription::factory()->create(['business_id' => $business->id]);
        $business->users()->attach($user->id, ['role' => $role]);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }
}
