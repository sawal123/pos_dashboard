<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1-3. Auth & Access Gates
    // ============================================================

    public function test_01_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('products.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_02_unverified_users_cannot_access_products_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_03_verified_user_without_business_returns_200_empty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Produk');
        $response->assertSee('0');
    }

    // ============================================================
    // 4-8. Multi-Tenant Hard Isolation
    // ============================================================

    public function test_04_current_business_product_is_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kopi Gayo Asli',
            'sku' => 'SKU-GAYO-01',
            'kind' => 'product',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Kopi Gayo Asli');
        $response->assertSee('SKU-GAYO-01');
    }

    public function test_05_other_tenant_product_is_not_visible(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->create([
            'business_id' => $businessB->id,
            'name' => 'Barang Rahasia Tenant B',
            'sku' => 'SKU-SECRET-B',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertDontSee('Barang Rahasia Tenant B');
        $response->assertDontSee('SKU-SECRET-B');
    }

    public function test_06_other_tenant_service_is_not_visible(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->create([
            'business_id' => $businessB->id,
            'name' => 'Layanan Rahasia B',
            'sku' => 'SRV-SECRET-B',
            'kind' => 'service',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertDontSee('Layanan Rahasia B');
        $response->assertDontSee('SRV-SECRET-B');
    }

    public function test_07_other_tenant_category_is_not_visible(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Category::factory()->create([
            'business_id' => $businessB->id,
            'name' => 'Kategori Tenant B',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'categories']));
        $response->assertOk();
        $response->assertDontSee('Kategori Tenant B');
    }

    public function test_08_switching_business_changes_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        Product::factory()->create([
            'business_id' => $businessA->id,
            'name' => 'Produk Toko A',
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $businessB->id,
            'name' => 'Produk Toko B',
            'kind' => 'product',
        ]);

        // Viewing business A
        $this->withSession(['dashboard.current_business_id' => $businessA->id]);
        $resA = $this->actingAs($user)->get(route('products.index'));
        $resA->assertSee('Produk Toko A');
        $resA->assertDontSee('Produk Toko B');

        // Viewing business B
        $this->withSession(['dashboard.current_business_id' => $businessB->id]);
        $resB = $this->actingAs($user)->get(route('products.index'));
        $resB->assertSee('Produk Toko B');
        $resB->assertDontSee('Produk Toko A');
    }

    // ============================================================
    // 9-11. Tombstone Contract (status = deleted)
    // ============================================================

    public function test_09_deleted_product_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Produk Terhapus',
            'status' => 'deleted',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertDontSee('Produk Terhapus');
    }

    public function test_10_deleted_service_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Layanan Terhapus',
            'status' => 'deleted',
            'kind' => 'service',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertDontSee('Layanan Terhapus');
    }

    public function test_11_deleted_category_is_excluded(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Category::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kategori Terhapus',
            'status' => 'deleted',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'categories']));
        $response->assertOk();
        $response->assertDontSee('Kategori Terhapus');
    }

    // ============================================================
    // 12-15. Product vs Service Tab Separation
    // ============================================================

    public function test_12_product_kind_appears_in_products_tab(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Item Fisik',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'products']));
        $response->assertOk();
        $response->assertSee('Item Fisik');
    }

    public function test_13_service_kind_appears_in_services_tab(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Jasa Cuci Karpet',
            'kind' => 'service',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertSee('Jasa Cuci Karpet');
    }

    public function test_14_service_does_not_appear_in_products_tab(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Jasa Pijat Refleksi',
            'kind' => 'service',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'products']));
        $response->assertOk();
        $response->assertDontSee('Jasa Pijat Refleksi');
    }

    public function test_15_product_does_not_appear_in_services_tab(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Gelas Kaca',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertDontSee('Gelas Kaca');
    }

    // ============================================================
    // 16-19. Summary Metrics
    // ============================================================

    public function test_16_summary_total_products_is_tenant_scoped(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->count(3)->create([
            'business_id' => $businessA->id,
            'kind' => 'product',
            'status' => 'active',
        ]);
        Product::factory()->count(5)->create([
            'business_id' => $businessB->id,
            'kind' => 'product',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['total_products'] === 3);
    }

    public function test_17_summary_total_services_is_tenant_scoped(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->count(2)->create([
            'business_id' => $businessA->id,
            'kind' => 'service',
            'status' => 'active',
        ]);
        Product::factory()->count(4)->create([
            'business_id' => $businessB->id,
            'kind' => 'service',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['total_services'] === 2);
    }

    public function test_18_active_categories_only_counts_active_non_deleted(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        Category::factory()->create(['business_id' => $business->id, 'status' => 'active']);
        Category::factory()->create(['business_id' => $business->id, 'status' => 'inactive']);
        Category::factory()->create(['business_id' => $business->id, 'status' => 'deleted']);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['active_categories'] === 1);
    }

    public function test_19_active_items_only_counts_product_status_active(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        Product::factory()->create(['business_id' => $business->id, 'kind' => 'product', 'status' => 'active']);
        Product::factory()->create(['business_id' => $business->id, 'kind' => 'service', 'status' => 'active']);
        Product::factory()->create(['business_id' => $business->id, 'kind' => 'product', 'status' => 'inactive']);
        Product::factory()->create(['business_id' => $business->id, 'kind' => 'product', 'status' => 'deleted']);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => $summary['active_items'] === 2);
    }

    // ============================================================
    // 20-22. Catalog Status Presentation & Category items_count
    // ============================================================

    public function test_20_inactive_items_remain_visible_in_catalog(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Nonaktif',
            'status' => 'inactive',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Barang Nonaktif');
        $response->assertSee('Nonaktif');
    }

    public function test_21_unknown_status_is_humanized_and_not_forced_to_nonaktif(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Review',
            'status' => 'pending_review',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Pending Review');
    }

    public function test_22_category_items_count_does_not_count_deleted_products(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $category = Category::factory()->create([
            'business_id' => $business->id,
            'name' => 'Makanan Ringan',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'status' => 'active',
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'status' => 'deleted',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'categories']));
        $response->assertOk();
        $response->assertSee('1 item');
        $response->assertDontSee('2 item');
    }

    // ============================================================
    // 23-26. Query Scope & Filtering
    // ============================================================

    public function test_23_customer_or_other_modules_are_not_queried(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('products.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $tableNames = collect($queries)->map(fn ($q) => $q['query'])->implode(' ');
        $this->assertStringNotContainsString('customers', $tableNames);
        $this->assertStringNotContainsString('sales', $tableNames);
    }

    public function test_24_category_filter_uses_stable_category_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $cat1 = Category::factory()->create(['business_id' => $business->id, 'name' => 'Kopi']);
        $cat2 = Category::factory()->create(['business_id' => $business->id, 'name' => 'Teh']);

        Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $cat1->id,
            'name' => 'Espresso Hot',
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $cat2->id,
            'name' => 'Green Tea Cold',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['category_id' => $cat1->id]));
        $response->assertOk();
        $response->assertSee('Espresso Hot');
        $response->assertDontSee('Green Tea Cold');
    }

    public function test_25_foreign_category_id_does_not_leak_data(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();
        $foreignCat = Category::factory()->create(['business_id' => $businessB->id, 'name' => 'Foreign Cat']);

        Product::factory()->create([
            'business_id' => $businessA->id,
            'name' => 'Produk Toko Saya',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['category_id' => $foreignCat->id]));
        $response->assertOk();
        $response->assertDontSee('Produk Toko Saya');
    }

    public function test_26_search_is_tenant_scoped(): void
    {
        [$user, $businessA] = $this->makeUserWithBusiness();
        $businessB = Business::factory()->create();

        Product::factory()->create([
            'business_id' => $businessA->id,
            'name' => 'Americano Dingin',
            'sku' => 'AME-001',
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $businessB->id,
            'name' => 'Americano Hangat B',
            'sku' => 'AME-002',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['q' => 'Americano']));
        $response->assertOk();
        $response->assertSee('Americano Dingin');
        $response->assertDontSee('Americano Hangat B');
    }

    // ============================================================
    // 27-31. Stock Status Logic & Server-side Filter
    // ============================================================

    public function test_27_product_stock_status_negative(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Minus',
            'stock' => -5.000,
            'min_stock' => 10.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Minus');
    }

    public function test_28_product_stock_status_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Habis',
            'stock' => 0.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Habis');
    }

    public function test_29_product_stock_status_low(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Menipis',
            'stock' => 3.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Menipis');
    }

    public function test_30_product_stock_status_safe(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Aman',
            'stock' => 15.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Aman');
    }

    public function test_31_stock_status_server_side_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Safe Filter',
            'stock' => 20.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang Low Filter',
            'stock' => 2.000,
            'min_stock' => 5.000,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['stock_status' => 'safe']));
        $response->assertOk();
        $response->assertSee('Barang Safe Filter');
        $response->assertDontSee('Barang Low Filter');
    }

    // ============================================================
    // 32-34. Formatting & Contracts
    // ============================================================

    public function test_32_stock_decimal_preserves_three_places(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Beras Organik',
            'stock' => 4.250,
            'unit' => 'kg',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('4.250 kg');
    }

    public function test_33_service_min_quantity_zero_is_rendered_explicitly(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Jasa Konsultasi',
            'kind' => 'service',
            'min_quantity' => 0.000,
            'unit' => 'sesi',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertSee('0.000 sesi');
    }

    public function test_34_generic_pricing_unit_remains_generic(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Paket Wedding',
            'kind' => 'service',
            'pricing_unit' => 'paket',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertSee('Per paket');
    }

    public function test_service_pricing_unit_does_not_default_to_paket_when_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Konsultasi Bebas',
            'kind' => 'service',
            'pricing_unit' => '',
            'unit' => '',
            'min_quantity' => 0.000,
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertDontSee('Per paket');
        $response->assertViewHas('items', function ($items) {
            $item = $items->first();

            return $item['pricing_unit'] === '' && $item['unit'] === '';
        });
    }

    public function test_products_summary_does_not_hardcode_laundry_kiloan(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertDontSee('laundry kiloan', false);
        $response->assertSee('Item layanan');
    }

    // ============================================================
    // 35-38. Server-side Pagination
    // ============================================================

    public function test_35_product_pagination_twenty_five_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->count(26)->create([
            'business_id' => $business->id,
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertViewHas('items', fn ($items) => $items->perPage() === 25 && $items->total() === 26);
    }

    public function test_36_service_pagination_twenty_five_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->count(26)->create([
            'business_id' => $business->id,
            'kind' => 'service',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $response->assertOk();
        $response->assertViewHas('items', fn ($items) => $items->perPage() === 25 && $items->total() === 26);
    }

    public function test_37_category_pagination_twenty_five_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Category::factory()->count(26)->create([
            'business_id' => $business->id,
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['tab' => 'categories']));
        $response->assertOk();
        $response->assertViewHas('items', fn ($items) => $items->perPage() === 25 && $items->total() === 26);
    }

    public function test_38_pagination_preserves_query_string(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->count(26)->create([
            'business_id' => $business->id,
            'kind' => 'product',
            'stock' => 100,
            'min_stock' => 10,
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['stock_status' => 'safe', 'page' => 2]));
        $response->assertOk();
        $response->assertSee('stock_status=safe');
    }

    // ============================================================
    // 39-44. Empty States & Anti-Regression
    // ============================================================

    public function test_39_filtered_empty_state_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Barang A',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['q' => 'ZzzNonExistent']));
        $response->assertOk();
        $response->assertSee('Produk Tidak Ditemukan');
        $response->assertSee('Coba ubah pencarian atau filter yang digunakan.');
    }

    public function test_40_truly_empty_per_tab_state_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $resProd = $this->actingAs($user)->get(route('products.index', ['tab' => 'products']));
        $resProd->assertSee('Belum Ada Produk');

        $resSrv = $this->actingAs($user)->get(route('products.index', ['tab' => 'services']));
        $resSrv->assertSee('Belum Ada Layanan');

        $resCat = $this->actingAs($user)->get(route('products.index', ['tab' => 'categories']));
        $resCat->assertSee('Belum Ada Kategori');
    }

    public function test_41_fixture_kopi_001_does_not_appear_when_db_is_empty(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertDontSee('KOPI-001');
        $response->assertDontSee('Kopi Susu Gula Aren');
    }

    public function test_42_products_index_view_does_not_require_fixtures(): void
    {
        $viewContent = file_get_contents(resource_path('views/products/index.blade.php'));
        $this->assertStringNotContainsString('fixtures.php', $viewContent);
    }

    public function test_43_deleted_status_is_not_an_option_in_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        Product::factory()->create([
            'business_id' => $business->id,
            'status' => 'deleted',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertViewHas('statuses', fn ($statuses) => ! in_array('deleted', $statuses));
    }

    public function test_44_product_with_deleted_category_shows_neutral_category_name(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $deletedCat = Category::factory()->create([
            'business_id' => $business->id,
            'name' => 'Kategori Purba',
            'status' => 'deleted',
        ]);

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $deletedCat->id,
            'name' => 'Kopi Purba',
            'kind' => 'product',
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));
        $response->assertOk();
        $response->assertSee('Kopi Purba');
        $response->assertDontSee('Kategori Purba');
        $response->assertSee('Tanpa Kategori');
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
}
