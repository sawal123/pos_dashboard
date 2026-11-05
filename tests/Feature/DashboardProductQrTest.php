<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardProductQrTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessWithUser(string $role = 'owner', string $businessType = 'grosir'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create([
            'business_type' => $businessType,
        ]);
        $user->businesses()->attach($business, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        return [$user, $business];
    }

    public function test_owner_can_generate_qr_code_for_product(): void
    {
        [$user, $business] = $this->createBusinessWithUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'barcode' => null,
            'sku' => 'SKU-TEST-01',
            'kind' => 'product',
            'is_sellable' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->postJson(route('products.generate-qr', ['productId' => $product->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'product_id' => $product->id,
        ]);

        $product->refresh();
        $this->assertNotNull($product->barcode);
        $this->assertStringStartsWith('NXP-', $product->barcode);
    }

    public function test_generate_qr_is_idempotent_and_does_not_overwrite_existing_barcode(): void
    {
        [$user, $business] = $this->createBusinessWithUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'barcode' => 'EXISTING-CODE-123',
            'sku' => 'SKU-TEST-02',
        ]);
        $initialVersion = $product->sync_version;

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->postJson(route('products.generate-qr', ['productId' => $product->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'barcode' => 'EXISTING-CODE-123',
        ]);

        $product->refresh();
        $this->assertSame('EXISTING-CODE-123', $product->barcode);
        $this->assertSame($initialVersion, $product->sync_version);
    }

    public function test_cannot_generate_qr_for_cross_tenant_product(): void
    {
        [$userA, $businessA] = $this->createBusinessWithUser('owner');
        [$userB, $businessB] = $this->createBusinessWithUser('owner');

        $productB = Product::factory()->create([
            'business_id' => $businessB->id,
            'barcode' => null,
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['current_business_id' => $businessA->id])
            ->postJson(route('products.generate-qr', ['productId' => $productB->id]));

        $response->assertStatus(404);
    }

    public function test_cashier_cannot_generate_qr_code(): void
    {
        [$user, $business] = $this->createBusinessWithUser('cashier');

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'barcode' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->postJson(route('products.generate-qr', ['productId' => $product->id]));

        $response->assertStatus(403);
    }

    public function test_qr_preview_returns_svg_and_metadata(): void
    {
        [$user, $business] = $this->createBusinessWithUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'barcode' => 'BARCODE-999',
            'name' => 'Beras Pandan Wangi',
            'sku' => 'SKU-BERAS-01',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->getJson(route('products.qr-preview', ['productId' => $product->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'product_id' => $product->id,
            'name' => 'Beras Pandan Wangi',
            'barcode' => 'BARCODE-999',
            'sku' => 'SKU-BERAS-01',
        ]);
        $this->assertStringContainsString('<svg', $response->json('svg'));
    }

    public function test_qr_preview_returns_404_when_product_has_no_barcode(): void
    {
        [$user, $business] = $this->createBusinessWithUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'barcode' => null,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->getJson(route('products.qr-preview', ['productId' => $product->id]));

        $response->assertStatus(404);
    }

    public function test_qr_download_returns_png_attachment(): void
    {
        [$user, $business] = $this->createBusinessWithUser();

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'barcode' => 'BARCODE-PNG-TEST',
            'name' => 'Minyak Goreng 2L',
            'sku' => 'SKU-MYK-02',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->get(route('products.qr-download', ['productId' => $product->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        // PNG magic number check (\x89PNG)
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_creating_sellable_product_in_grosir_auto_generates_barcode_if_omitted(): void
    {
        [$user, $business] = $this->createBusinessWithUser('owner', 'grosir');

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('products.store'), [
                'name' => 'Gula Pasir 1kg',
                'sku' => 'SKU-GULA-01',
                'price' => 15000,
                'cost' => 12000,
                'is_sellable' => true,
                'kind' => 'product',
                'unit' => 'kg',
            ]);

        $response->assertRedirect();

        $product = Product::where('business_id', $business->id)->where('sku', 'SKU-GULA-01')->firstOrFail();
        $this->assertNotNull($product->barcode);
        $this->assertStringStartsWith('NXP-', $product->barcode);
    }

    public function test_creating_raw_material_in_grosir_does_not_auto_generate_barcode(): void
    {
        [$user, $business] = $this->createBusinessWithUser('owner', 'grosir');

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('products.store'), [
                'name' => 'Bahan Baku Kemasan',
                'sku' => 'SKU-BAHAN-01',
                'price' => 0,
                'cost' => 500,
                'is_sellable' => false,
                'kind' => 'product',
                'unit' => 'pcs',
            ]);

        $response->assertRedirect();

        $product = Product::where('business_id', $business->id)->where('sku', 'SKU-BAHAN-01')->firstOrFail();
        $this->assertNull($product->barcode);
    }

    public function test_cross_column_conflict_validation_in_product_request(): void
    {
        [$user, $business] = $this->createBusinessWithUser('owner', 'grosir');

        Product::factory()->create([
            'business_id' => $business->id,
            'sku' => 'SKU-EXISTING-A',
            'barcode' => 'BARCODE-EXISTING-A',
        ]);

        // Try creating product B with barcode matching product A's SKU
        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('products.store'), [
                'name' => 'Produk B Bentrok',
                'sku' => 'SKU-PROD-B',
                'barcode' => 'SKU-EXISTING-A',
                'price' => 20000,
                'cost' => 15000,
                'is_sellable' => true,
                'kind' => 'product',
            ]);

        $response->assertSessionHasErrors('barcode');

        // Try creating product C with SKU matching product A's barcode
        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('products.store'), [
                'name' => 'Produk C Bentrok',
                'sku' => 'BARCODE-EXISTING-A',
                'price' => 20000,
                'cost' => 15000,
                'is_sellable' => true,
                'kind' => 'product',
            ]);

        $response->assertSessionHasErrors('sku');
    }

    public function test_updating_product_preserves_barcode_when_not_supplied(): void
    {
        [$user, $business] = $this->createBusinessWithUser('owner', 'grosir');

        $product = Product::factory()->create([
            'business_id' => $business->id,
            'sku' => 'SKU-PRESERVE-01',
            'barcode' => 'INITIAL-BARCODE-555',
            'name' => 'Nama Lama',
            'price' => 10000,
        ]);

        $catalogService = app(\App\Services\Dashboard\CatalogManagementService::class);
        $updated = $catalogService->updateProduct($product, [
            'name' => 'Nama Baru',
            'price' => 12000,
        ]);

        $this->assertSame('INITIAL-BARCODE-555', $updated->barcode);
        $this->assertSame('Nama Baru', $updated->name);
    }
}

