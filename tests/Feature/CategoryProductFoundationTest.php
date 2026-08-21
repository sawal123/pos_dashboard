<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryProductFoundationTest extends TestCase
{
    use RefreshDatabase;

    // --- Category Tests ---

    public function test_category_can_be_created_for_business(): void
    {
        $business = Business::factory()->create();

        $category = Category::create([
            'business_id' => $business->id,
            'name' => 'Food & Beverages',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'business_id' => $business->id,
            'name' => 'Food & Beverages',
            'status' => 'active',
        ]);
        $this->assertNotNull($category->id);
    }

    public function test_category_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $category = Category::factory()->create([
            'business_id' => $business->id,
        ]);

        $this->assertNotNull($category->business);
        $this->assertSame($business->id, $category->business->id);
    }

    public function test_business_can_have_multiple_categories(): void
    {
        $business = Business::factory()->create();

        $cat1 = Category::factory()->create(['business_id' => $business->id, 'name' => 'Food']);
        $cat2 = Category::factory()->create(['business_id' => $business->id, 'name' => 'Drinks']);

        $this->assertCount(2, $business->categories);
        $this->assertTrue($business->categories->contains($cat1));
        $this->assertTrue($business->categories->contains($cat2));
    }

    public function test_categories_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $catA = Category::factory()->create(['business_id' => $businessA->id, 'name' => 'Food']);
        $catB = Category::factory()->create(['business_id' => $businessB->id, 'name' => 'Food']);

        $this->assertCount(1, $businessA->categories);
        $this->assertTrue($businessA->categories->contains($catA));
        $this->assertFalse($businessA->categories->contains($catB));

        $this->assertCount(1, $businessB->categories);
        $this->assertTrue($businessB->categories->contains($catB));
        $this->assertFalse($businessB->categories->contains($catA));
    }

    public function test_category_name_must_be_unique_within_the_same_business(): void
    {
        $business = Business::factory()->create();

        Category::create([
            'business_id' => $business->id,
            'name' => 'Snacks',
        ]);

        $this->expectException(QueryException::class);

        Category::create([
            'business_id' => $business->id,
            'name' => 'Snacks',
        ]);
    }

    public function test_same_category_name_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $catA = Category::create([
            'business_id' => $businessA->id,
            'name' => 'Desserts',
        ]);

        $catB = Category::create([
            'business_id' => $businessB->id,
            'name' => 'Desserts',
        ]);

        $this->assertDatabaseHas('categories', ['id' => $catA->id, 'business_id' => $businessA->id]);
        $this->assertDatabaseHas('categories', ['id' => $catB->id, 'business_id' => $businessB->id]);
    }

    public function test_default_status_of_category_is_active(): void
    {
        $business = Business::factory()->create();

        $category = Category::create([
            'business_id' => $business->id,
            'name' => 'Services',
        ]);

        $this->assertSame('active', $category->status);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'status' => 'active']);
    }

    public function test_inactive_category_can_be_created(): void
    {
        $business = Business::factory()->create();

        $category = Category::factory()->inactive()->create([
            'business_id' => $business->id,
            'name' => 'Archived Category',
        ]);

        $this->assertSame('inactive', $category->status);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'status' => 'inactive']);
    }

    // --- Product Tests ---

    public function test_product_can_be_created_for_business(): void
    {
        $business = Business::factory()->create();

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Espresso Double',
            'sku' => 'ESP-002',
            'barcode' => '8991234567890',
            'price' => 25000,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'business_id' => $business->id,
            'name' => 'Espresso Double',
            'sku' => 'ESP-002',
            'barcode' => '8991234567890',
            'price' => 25000,
            'status' => 'active',
        ]);
        $this->assertNotNull($product->id);
    }

    public function test_product_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->create([
            'business_id' => $business->id,
        ]);

        $this->assertNotNull($product->business);
        $this->assertSame($business->id, $product->business->id);
    }

    public function test_business_can_have_multiple_products(): void
    {
        $business = Business::factory()->create();

        $prod1 = Product::factory()->create(['business_id' => $business->id, 'sku' => 'SKU-01']);
        $prod2 = Product::factory()->create(['business_id' => $business->id, 'sku' => 'SKU-02']);

        $this->assertCount(2, $business->products);
        $this->assertTrue($business->products->contains($prod1));
        $this->assertTrue($business->products->contains($prod2));
    }

    public function test_product_can_have_null_category(): void
    {
        $business = Business::factory()->create();

        $product = Product::create([
            'business_id' => $business->id,
            'category_id' => null,
            'name' => 'Service Fee',
            'sku' => 'FEE-001',
            'price' => 5000,
        ]);

        $this->assertNull($product->category_id);
        $this->assertNull($product->category);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    public function test_product_can_have_category_from_same_business(): void
    {
        $business = Business::factory()->create();
        $category = Category::factory()->create(['business_id' => $business->id]);

        $product = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name' => 'Cappuccino',
            'sku' => 'CAP-001',
            'price' => 30000,
        ]);

        $this->assertSame($category->id, $product->category_id);
        $this->assertNotNull($product->category);
        $this->assertSame($category->id, $product->category->id);
        $this->assertTrue($category->products->contains($product));
    }

    public function test_products_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $prodA = Product::factory()->create(['business_id' => $businessA->id, 'sku' => 'SKU-01']);
        $prodB = Product::factory()->create(['business_id' => $businessB->id, 'sku' => 'SKU-01']);

        $this->assertCount(1, $businessA->products);
        $this->assertTrue($businessA->products->contains($prodA));
        $this->assertFalse($businessA->products->contains($prodB));

        $this->assertCount(1, $businessB->products);
        $this->assertTrue($businessB->products->contains($prodB));
        $this->assertFalse($businessB->products->contains($prodA));
    }

    public function test_sku_must_be_unique_within_the_same_business(): void
    {
        $business = Business::factory()->create();

        Product::create([
            'business_id' => $business->id,
            'name' => 'Product 1',
            'sku' => 'SAME-SKU',
            'price' => 10000,
        ]);

        $this->expectException(QueryException::class);

        Product::create([
            'business_id' => $business->id,
            'name' => 'Product 2',
            'sku' => 'SAME-SKU',
            'price' => 15000,
        ]);
    }

    public function test_same_sku_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $prodA = Product::create([
            'business_id' => $businessA->id,
            'name' => 'Product A',
            'sku' => 'SHARED-SKU',
            'price' => 10000,
        ]);

        $prodB = Product::create([
            'business_id' => $businessB->id,
            'name' => 'Product B',
            'sku' => 'SHARED-SKU',
            'price' => 12000,
        ]);

        $this->assertDatabaseHas('products', ['id' => $prodA->id, 'business_id' => $businessA->id]);
        $this->assertDatabaseHas('products', ['id' => $prodB->id, 'business_id' => $businessB->id]);
    }

    public function test_barcode_can_be_null(): void
    {
        $business = Business::factory()->create();

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'No Barcode Product',
            'sku' => 'NOBC-001',
            'barcode' => null,
            'price' => 10000,
        ]);

        $this->assertNull($product->barcode);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'barcode' => null]);
    }

    public function test_barcode_must_be_unique_within_the_same_business(): void
    {
        $business = Business::factory()->create();

        Product::create([
            'business_id' => $business->id,
            'name' => 'Barcode Product 1',
            'sku' => 'BC-001',
            'barcode' => '8990001',
            'price' => 10000,
        ]);

        $this->expectException(QueryException::class);

        Product::create([
            'business_id' => $business->id,
            'name' => 'Barcode Product 2',
            'sku' => 'BC-002',
            'barcode' => '8990001',
            'price' => 20000,
        ]);
    }

    public function test_same_barcode_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $prodA = Product::create([
            'business_id' => $businessA->id,
            'name' => 'Barcode Product A',
            'sku' => 'BC-A',
            'barcode' => '8999999',
            'price' => 10000,
        ]);

        $prodB = Product::create([
            'business_id' => $businessB->id,
            'name' => 'Barcode Product B',
            'sku' => 'BC-B',
            'barcode' => '8999999',
            'price' => 15000,
        ]);

        $this->assertDatabaseHas('products', ['id' => $prodA->id, 'barcode' => '8999999']);
        $this->assertDatabaseHas('products', ['id' => $prodB->id, 'barcode' => '8999999']);
    }

    public function test_default_status_of_product_is_active(): void
    {
        $business = Business::factory()->create();

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Default Product',
            'sku' => 'DEF-001',
            'price' => 10000,
        ]);

        $this->assertSame('active', $product->status);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
    }

    public function test_inactive_product_can_be_created(): void
    {
        $business = Business::factory()->create();

        $product = Product::factory()->inactive()->create([
            'business_id' => $business->id,
        ]);

        $this->assertSame('inactive', $product->status);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'inactive']);
    }

    public function test_price_is_stored_and_cast_as_integer_rupiah(): void
    {
        $business = Business::factory()->create();

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Priced Product',
            'sku' => 'PRICE-001',
            'price' => 12500,
        ]);

        $this->assertIsInt($product->price);
        $this->assertSame(12500, $product->price);
    }

    // --- Cross-tenant Protection ---

    public function test_product_of_business_a_cannot_use_category_of_business_b(): void
    {
        $businessA = Business::factory()->create(['name' => 'Business A']);
        $businessB = Business::factory()->create(['name' => 'Business B']);

        $categoryB = Category::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(QueryException::class);

        Product::create([
            'business_id' => $businessA->id,
            'category_id' => $categoryB->id,
            'name' => 'Cross-tenant Product',
            'sku' => 'CROSS-001',
            'price' => 20000,
        ]);
    }

    // --- Cascade & Safety Tests ---

    public function test_deleting_business_cascades_and_deletes_its_categories_and_products(): void
    {
        $business = Business::factory()->create();
        $category = Category::factory()->create(['business_id' => $business->id]);
        $product = Product::factory()->create([
            'business_id' => $business->id,
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $business->delete();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deleting_business_a_does_not_delete_categories_and_products_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $categoryA = Category::factory()->create(['business_id' => $businessA->id]);
        $categoryB = Category::factory()->create(['business_id' => $businessB->id]);

        $productA = Product::factory()->create(['business_id' => $businessA->id, 'category_id' => $categoryA->id]);
        $productB = Product::factory()->create(['business_id' => $businessB->id, 'category_id' => $categoryB->id]);

        $businessA->delete();

        $this->assertDatabaseMissing('categories', ['id' => $categoryA->id]);
        $this->assertDatabaseMissing('products', ['id' => $productA->id]);

        $this->assertDatabaseHas('categories', ['id' => $categoryB->id]);
        $this->assertDatabaseHas('products', ['id' => $productB->id]);
    }
}
