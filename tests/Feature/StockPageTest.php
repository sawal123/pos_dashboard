<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1-3. Auth & Access Gates
    // ============================================================

    public function test_01_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('stock.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_02_unverified_users_cannot_access_stock_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_03_verified_user_without_business_returns_200_empty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Data Stok');
        $response->assertSee('0');
    }

    // ============================================================
    // 4-8. Multi-Tenant Scope, Physical Products, Inactive Monitoring
    // ============================================================

    public function test_04_physical_product_current_tenant_is_displayed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Robusta 1kg',
            'sku' => 'SKU-ROB-01',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Kopi Robusta 1kg');
        $response->assertSee('SKU-ROB-01');
    }

    public function test_05_other_tenant_product_is_not_displayed(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->create([
            'business_id' => $businessB->id,
            'name' => 'Stok Milik Tenant B',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertDontSee('Stok Milik Tenant B');
    }

    public function test_06_service_kind_is_not_displayed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Jasa Cuci Karpet',
            'kind' => 'service',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertDontSee('Jasa Cuci Karpet');
    }

    public function test_07_deleted_product_is_not_displayed(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Terhapus',
            'status' => 'deleted',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertDontSee('Barang Terhapus');
    }

    public function test_08_inactive_physical_product_can_still_be_monitored(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Nonaktif Tetap Terpantau',
            'status' => 'inactive',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Barang Nonaktif Tetap Terpantau');
    }

    // ============================================================
    // 9-10. Authoritative Stock Source of Truth
    // ============================================================

    public function test_09_product_stock_is_authoritative(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Otoritatif',
            'stock' => 10.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        // Movements that sum to different quantity
        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'quantity_change' => '+100.000',
            'stock_before' => '0.000',
            'stock_after' => '100.000',
        ]);
        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'quantity_change' => '-2.000',
            'stock_before' => '100.000',
            'stock_after' => '98.000',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('10.000');
        $response->assertDontSee('98.000');
    }

    public function test_10_movement_is_not_used_to_calculate_current_stock(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Non Movement Calc',
            'stock' => 15.000,
            'kind' => 'product',
        ]);

        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'quantity_change' => '-50.000',
            'stock_before' => '100.000',
            'stock_after' => '50.000',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('15.000');
        $response->assertDontSee('50.000');
    }

    // ============================================================
    // 11-14. Stock Status Definition & Presentation
    // ============================================================

    public function test_11_negative_stock_displays_minus(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Stok Negatif',
            'stock' => -3.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Minus');
    }

    public function test_12_zero_stock_displays_habis(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Stok Kosong',
            'stock' => 0.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Habis');
    }

    public function test_13_low_stock_displays_menipis(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Stok Menipis Bro',
            'stock' => 4.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Menipis');
    }

    public function test_14_safe_stock_displays_aman(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Stok Aman Jaya',
            'stock' => 20.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Aman');
    }

    // ============================================================
    // 15-18. Stock Summary Metrics
    // ============================================================

    public function test_15_summary_total_items_is_tenant_scoped(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->count(3)->create(['business_id' => $businessA->id, 'kind' => 'product']);
        Product::factory()->count(5)->create(['business_id' => $businessB->id, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($s) => $s['total_items'] === 3);
    }

    public function test_16_safe_summary_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create(['business_id' => $business->id, 'stock' => 10, 'min_stock' => 5, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($s) => $s['safe_stock'] === 1);
    }

    public function test_17_low_summary_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create(['business_id' => $business->id, 'stock' => 3, 'min_stock' => 5, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($s) => $s['low_stock'] === 1);
    }

    public function test_18_critical_summary_is_sum_of_empty_and_negative(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create(['business_id' => $business->id, 'stock' => 0, 'min_stock' => 5, 'kind' => 'product']);
        Product::factory()->create(['business_id' => $business->id, 'stock' => -2, 'min_stock' => 5, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($s) => $s['critical_stock'] === 2);
    }

    // ============================================================
    // 19-22. Server-side Filtering & Tenant Scopes
    // ============================================================

    public function test_19_category_filter_uses_stable_category_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $cat = Category::factory()->create(['business_id' => $business->id, 'name' => 'Biji Kopi']);

        Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $cat->id,
            'name' => 'Arabica Preanger',
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Teh Melati',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index', ['category_id' => $cat->id]));
        $response->assertOk();
        $response->assertSee('Arabica Preanger');
        $response->assertDontSee('Teh Melati');
    }

    public function test_20_foreign_category_id_does_not_leak(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $foreignCat = Category::factory()->create(['business_id' => $businessB->id]);

        Product::factory()->create([
            'business_id' => $businessA->id,
            'name' => 'Barang Saya',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index', ['category_id' => $foreignCat->id]));
        $response->assertOk();
        $response->assertDontSee('Barang Saya');
    }

    public function test_21_search_is_tenant_scoped(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->create(['business_id' => $businessA->id, 'name' => 'Sirup Vanila A', 'kind' => 'product']);
        Product::factory()->create(['business_id' => $businessB->id, 'name' => 'Sirup Vanila B', 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index', ['q' => 'Vanila']));
        $response->assertOk();
        $response->assertSee('Sirup Vanila A');
        $response->assertDontSee('Sirup Vanila B');
    }

    public function test_22_stock_status_server_side_filter_works(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Item Habis Filter',
            'stock' => 0.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Item Aman Filter',
            'stock' => 20.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index', ['stock_status' => 'empty']));
        $response->assertOk();
        $response->assertSee('Item Habis Filter');
        $response->assertDontSee('Item Aman Filter');
    }

    // ============================================================
    // 23-29. Pagination, Decimal Safety, Fixtures Excluded
    // ============================================================

    public function test_23_pagination_twenty_five_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->count(26)->create(['business_id' => $business->id, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertViewHas('items', fn ($items) => $items->perPage() === 25 && $items->total() === 26);
    }

    public function test_24_query_string_preserved(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->count(26)->create(['business_id' => $business->id, 'kind' => 'product', 'stock' => 50, 'min_stock' => 10]);

        $response = $this->actingAs($user)->get(route('stock.index', ['stock_status' => 'safe', 'page' => 2]));
        $response->assertOk();
        $response->assertSee('stock_status=safe');
    }

    public function test_25_filtered_empty_state(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create(['business_id' => $business->id, 'name' => 'Stok Ada', 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index', ['q' => 'BukanItem']));
        $response->assertOk();
        $response->assertSee('Data Stok Tidak Ditemukan');
        $response->assertSee('Tidak ada item inventori yang cocok dengan kata kunci atau filter saat ini.');
    }

    public function test_26_truly_empty_state(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Data Stok');
        $response->assertSee('Data inventori yang telah tersinkron ke Cloud akan muncul di sini.');
    }

    public function test_27_stock_decimal_three_places_maintained(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Gula Pasir Halus',
            'stock' => 12.750,
            'min_stock' => 2.500,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertSee('12.750');
        $response->assertSee('2.500');
    }

    public function test_28_fixture_cup_16oz_does_not_appear_when_db_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();
        $response->assertDontSee('RAW-001');
        $response->assertDontSee('Cup 16oz');
    }

    public function test_29_stock_index_view_does_not_require_fixture(): void
    {
        $content = file_get_contents(resource_path('views/stock/index.blade.php'));
        $this->assertStringNotContainsString('fixtures.php', $content);
    }

    public function test_stock_row_and_button_carry_correct_movements_route(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.index'));
        $response->assertOk();

        $expectedUrl = route('stock.movements', ['productId' => $product->id]);
        $response->assertSee('data-movements-url="'.$expectedUrl.'"', false);
    }

    public function test_stock_index_view_does_not_contain_hardcoded_movements_url(): void
    {
        $content = file_get_contents(resource_path('views/stock/index.blade.php'));
        $this->assertStringNotContainsString('/stock/${itemData.id}/movements', $content);
        $this->assertStringNotContainsString('`/stock/${', $content);
    }

    // ============================================================
    // 30-44. Stock Movements Endpoint Tests
    // ============================================================

    public function test_30_current_tenant_product_movement_endpoint_returns_200(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $response->assertJsonStructure([
            'product' => ['id', 'name', 'sku', 'current_stock', 'min_stock', 'unit', 'stock_status'],
            'movements',
            'has_more',
        ]);
    }

    public function test_31_foreign_tenant_product_movement_returns_404(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $productB = Product::factory()->create(['business_id' => $businessB->id, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $productB->id]));
        $response->assertNotFound();
    }

    public function test_32_movement_foreign_tenant_does_not_leak(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $productA = Product::factory()->create(['business_id' => $businessA->id, 'kind' => 'product']);

        $businessB = Business::factory()->create();
        $productB = Product::factory()->create(['business_id' => $businessB->id, 'kind' => 'product']);
        $this->createStockMovement([
            'business_id' => $businessB->id,
            'product_id' => $productB->id,
            'note' => 'Catatan Rahasia Tenant B',
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $productA->id]));
        $response->assertOk();
        $response->assertDontSee('Catatan Rahasia Tenant B');
    }

    public function test_33_movements_ordered_by_occurred_at_desc_and_id_desc(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $m1 = $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'occurred_at' => now()->subDays(2),
            'note' => 'Pertama',
        ]);
        $m2 = $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'occurred_at' => now()->subDay(),
            'note' => 'Kedua',
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $movements = $response->json('movements');

        $this->assertEquals($m2->id, $movements[0]['id']);
        $this->assertEquals($m1->id, $movements[1]['id']);
    }

    public function test_34_movement_quantity_positive_is_safe(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'quantity_change' => '+15.000',
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertEquals('15.000', $response->json('movements.0.quantity_change'));
    }

    public function test_35_movement_quantity_negative_is_safe(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'quantity_change' => '-8.000',
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertEquals('-8.000', $response->json('movements.0.quantity_change'));
    }

    public function test_36_zero_movement_is_neutral(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'quantity_change' => '0.000',
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertEquals('0.000', $response->json('movements.0.quantity_change'));
    }

    public function test_37_unknown_movement_type_is_humanized(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'movement_type' => 'manual_recount',
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertEquals('Manual Recount', $response->json('movements.0.movement_type'));
    }

    public function test_38_nullable_reference_category_note_are_handled(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $this->createStockMovement([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'reference_id' => null,
            'category' => null,
            'note' => null,
        ]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertNull($response->json('movements.0.reference_id'));
        $this->assertNull($response->json('movements.0.category'));
        $this->assertNull($response->json('movements.0.note'));
    }

    public function test_39_endpoint_does_not_expose_sync_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);
        $this->createStockMovement(['business_id' => $business->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertArrayNotHasKey('sync_id', $response->json('movements.0'));
    }

    public function test_40_endpoint_does_not_expose_sync_version(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);
        $this->createStockMovement(['business_id' => $business->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertArrayNotHasKey('sync_version', $response->json('movements.0'));
    }

    public function test_41_endpoint_does_not_expose_sale_sync_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);
        $this->createStockMovement(['business_id' => $business->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertArrayNotHasKey('sale_sync_id', $response->json('movements.0'));
    }

    public function test_42_endpoint_returns_maximum_50_movements(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        for ($i = 0; $i < 55; $i++) {
            $this->createStockMovement(['business_id' => $business->id, 'product_id' => $product->id]);
        }

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertCount(50, $response->json('movements'));
    }

    public function test_43_has_more_is_true_when_more_than_50_movements(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        for ($i = 0; $i < 51; $i++) {
            $this->createStockMovement(['business_id' => $business->id, 'product_id' => $product->id]);
        }

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertTrue($response->json('has_more'));
    }

    public function test_44_no_movements_returns_empty_array_and_has_more_false(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create(['business_id' => $business->id, 'kind' => 'product']);

        $response = $this->actingAs($user)->get(route('stock.movements', ['productId' => $product->id]));
        $response->assertOk();
        $this->assertEquals([], $response->json('movements'));
        $this->assertFalse($response->json('has_more'));
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }

    /**
     * Local test helper to create a StockMovement without modifying models/factories.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createStockMovement(array $attributes = []): StockMovement
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $productId = $attributes['product_id'] ?? Product::factory()->create(['business_id' => $businessId])->id;

        return StockMovement::create(array_merge([
            'business_id' => $businessId,
            'product_id' => $productId,
            'movement_type' => 'sale',
            'quantity_change' => '-1.000',
            'stock_before' => '10.000',
            'stock_after' => '9.000',
            'reference_id' => 'REF-'.uniqid(),
            'category' => 'Penjualan',
            'note' => 'Penjualan kasir',
            'sale_sync_id' => null,
            'occurred_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }
}
