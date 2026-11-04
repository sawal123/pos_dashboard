<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Services\Catalog\ProductCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductCodeService();
    }

    public function test_validate_code_rules(): void
    {
        $this->assertNotNull($this->service->validateCode(null));
        $this->assertNotNull($this->service->validateCode('   '));
        $this->assertNotNull($this->service->validateCode(str_repeat('A', 256)));
        $this->assertNotNull($this->service->validateCode("hello\x00world"));

        $this->assertNull($this->service->validateCode('NXP-VALID-CODE'));
        $this->assertNull($this->service->validateCode('0891234567890'));
    }

    public function test_check_barcode_and_sku_cross_conflict(): void
    {
        $business = Business::factory()->create();

        $prodA = Product::factory()->create([
            'business_id' => $business->id,
            'sku' => 'SKU-A',
            'barcode' => 'BAR-A',
        ]);

        $prodB = Product::factory()->create([
            'business_id' => $business->id,
            'sku' => 'SKU-B',
            'barcode' => 'BAR-B',
        ]);

        // 1. Conflict on barcode: BAR-A already used by prodA
        $this->assertNotNull($this->service->checkBarcodeConflict($business->id, 'BAR-A', $prodB->id));

        // 2. Cross conflict: Barcode BAR-X is already used as SKU on prodA
        $this->assertNotNull($this->service->checkBarcodeConflict($business->id, 'SKU-A', $prodB->id));

        // 3. Same product can have barcode == sku
        $this->assertNull($this->service->checkBarcodeConflict($business->id, 'SKU-A', $prodA->id));

        // 4. Clean barcode has no conflict
        $this->assertNull($this->service->checkBarcodeConflict($business->id, 'BAR-NEW', $prodB->id));

        // 5. Check SKU cross conflict
        $this->assertNotNull($this->service->checkSkuConflict($business->id, 'SKU-A', $prodB->id));
        $this->assertNotNull($this->service->checkSkuConflict($business->id, 'BAR-A', $prodB->id));
        $this->assertNull($this->service->checkSkuConflict($business->id, 'SKU-NEW', $prodB->id));
    }

    public function test_generate_internal_barcode(): void
    {
        $business = Business::factory()->create();

        $code = $this->service->generateInternalBarcode($business);

        $this->assertStringStartsWith('NXP-', $code);
        $this->assertLessThanOrEqual(255, strlen($code));
        $this->assertGreaterThan(10, strlen($code));
    }

    public function test_assign_barcode_idempotent(): void
    {
        $business = Business::factory()->create();

        $prod = Product::factory()->create([
            'business_id' => $business->id,
            'sku' => 'SKU-TEST',
            'barcode' => null,
            'sync_version' => 1,
        ]);

        // First assignment generates new code
        $updated = $this->service->assignBarcode($prod);
        $this->assertNotNull($updated->barcode);
        $this->assertStringStartsWith('NXP-', $updated->barcode);
        $generatedCode = $updated->barcode;

        // Second assignment with null preserves existing code
        $again = $this->service->assignBarcode($updated);
        $this->assertSame($generatedCode, $again->barcode);

        // Assign explicit new barcode
        $custom = $this->service->assignBarcode($again, '8991234567890');
        $this->assertSame('8991234567890', $custom->barcode);
    }

    public function test_render_qr_svg_and_label_png(): void
    {
        $business = Business::factory()->create();
        $prod = Product::factory()->create([
            'business_id' => $business->id,
            'name' => 'Beras Pandan Wangi 5kg',
            'sku' => 'BERAS-01',
            'barcode' => '8991234567890',
        ]);

        $svg = $this->service->renderQrSvg($prod->barcode);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);

        $png = $this->service->renderLabelPng($prod);
        $this->assertNotEmpty($png);
        // Verify PNG magic header: \x89PNG\r\n\x1a\n
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
    }
}
