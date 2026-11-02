<?php

namespace Database\Seeders;

use App\Enums\CostSource;
use App\Enums\HppMethod;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\User;
use App\Services\Inventory\HppService;
use Illuminate\Database\Seeder;

/**
 * Seeder data realistis untuk fitur HPP (Harga Pokok Penjualan) & Resep BOM.
 *
 * Mengisi:
 * 1. Bahan Baku / Supplies (Biji Kopi, Susu, Gula, Cup, dsb) dengan is_sellable = false
 * 2. Menu Olahan dengan Resep BOM dinamis (Latte, Cappuccino, Americano, Matcha, Es Teh)
 * 3. Menu Makanan Jadi dengan Moving Average (Croissant, Brownies)
 * 4. Item Merchandise dengan Biaya Modal Manual
 * 5. Log audit trail ProductCostHistory
 *
 * Idempotent: aman dijalankan berulang kali.
 */
class ProductHppSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = Business::query()->whereIn('business_type', ['cafe', 'retail', 'fnb'])->get();

        if ($businesses->isEmpty()) {
            $businesses = Business::query()->limit(1)->get();
        }

        if ($businesses->isEmpty()) {
            $this->command?->warn('Tidak ada bisnis ditemukan untuk di-seed HPP.');

            return;
        }

        $hppService = app(HppService::class);
        $user = User::query()->first();

        foreach ($businesses as $business) {
            $this->seedBusinessHpp($business, $hppService, $user);
        }

        $this->command?->info('Data seeder HPP & Resep Produk berhasil di-seed.');
    }

    private function seedBusinessHpp(Business $business, HppService $hppService, ?User $user): void
    {
        // 1. Kategori Bahan Baku
        $rawCategory = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Bahan Baku & Kemasan'],
            ['status' => 'active']
        );

        $kopiCategory = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Kopi'],
            ['status' => 'active']
        );

        $nonKopiCategory = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Non-Kopi'],
            ['status' => 'active']
        );

        $merchCategory = Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => 'Merchandise'],
            ['status' => 'active']
        );

        // 2. Buat Bahan Baku (Supplies / Raw Materials)
        $beans = $this->upsertRawMaterial($business, $rawCategory, 'RAW-BEANS', 'Biji Kopi House Blend (Arabika-Robusta)', 250, 'gram', 12000, 2000);
        $robusta = $this->upsertRawMaterial($business, $rawCategory, 'RAW-ROBUSTA', 'Biji Kopi Robusta Dampit', 160, 'gram', 8000, 1500);
        $milk = $this->upsertRawMaterial($business, $rawCategory, 'RAW-MILK', 'Susu Fresh Milk Pasteurisasi', 22, 'ml', 24000, 5000);
        $syrup = $this->upsertRawMaterial($business, $rawCategory, 'RAW-SYRUP', 'Simple Syrup / Gula Cair', 15, 'ml', 6000, 1000);
        $aren = $this->upsertRawMaterial($business, $rawCategory, 'RAW-AREN', 'Gula Aren Organik Cair', 28, 'ml', 4000, 800);
        $matcha = $this->upsertRawMaterial($business, $rawCategory, 'RAW-MATCHA', 'Bubuk Matcha Uji Premium', 350, 'gram', 1500, 300);
        $tea = $this->upsertRawMaterial($business, $rawCategory, 'RAW-TEA', 'Daun Teh Hitam Premium', 80, 'gram', 3500, 500);
        $cup12 = $this->upsertRawMaterial($business, $rawCategory, 'RAW-CUP-12OZ', 'Paper Cup 12oz + Lid Tutup', 850, 'pcs', 1200, 200);

        // 3. Produk Menu Olahan Berdasarkan Resep (BOM)
        $this->upsertRecipeProduct($business, $kopiCategory, 'KOPI-LATTE', 'Cafe Latte', 25000, [
            ['component_id' => $beans->id, 'qty' => 18],     // 18g * 250 = 4500
            ['component_id' => $milk->id, 'qty' => 180],     // 180ml * 22 = 3960
            ['component_id' => $cup12->id, 'qty' => 1],      // 1 cup = 850
        ], overheadFixed: 500, overheadPercent: 5, hppService: $hppService, user: $user);

        $this->upsertRecipeProduct($business, $kopiCategory, 'KOPI-CAPPUCCINO', 'Cappuccino', 26000, [
            ['component_id' => $beans->id, 'qty' => 18],     // 18g * 250 = 4500
            ['component_id' => $milk->id, 'qty' => 140],     // 140ml * 22 = 3080
            ['component_id' => $cup12->id, 'qty' => 1],      // 1 cup = 850
        ], overheadFixed: 500, overheadPercent: 5, hppService: $hppService, user: $user);

        $this->upsertRecipeProduct($business, $kopiCategory, 'KOPI-AMERICANO', 'Americano', 22000, [
            ['component_id' => $beans->id, 'qty' => 18],     // 18g * 250 = 4500
            ['component_id' => $cup12->id, 'qty' => 1],      // 1 cup = 850
        ], overheadFixed: 500, overheadPercent: 5, hppService: $hppService, user: $user);

        $this->upsertRecipeProduct($business, $kopiCategory, 'KOPI-ESPRESSO', 'Espresso Single Shot', 18000, [
            ['component_id' => $beans->id, 'qty' => 18],     // 18g * 250 = 4500
        ], overheadFixed: 500, overheadPercent: 0, hppService: $hppService, user: $user);

        $this->upsertRecipeProduct($business, $kopiCategory, 'KOPI-SOESOE-AREN', 'Es Kopi Susu Gula Aren', 20000, [
            ['component_id' => $robusta->id, 'qty' => 20],   // 20g * 160 = 3200
            ['component_id' => $milk->id, 'qty' => 120],     // 120ml * 22 = 2640
            ['component_id' => $aren->id, 'qty' => 25],      // 25ml * 28 = 700
            ['component_id' => $cup12->id, 'qty' => 1],      // 1 cup = 850
        ], overheadFixed: 500, overheadPercent: 5, hppService: $hppService, user: $user);

        $this->upsertRecipeProduct($business, $kopiCategory, 'KOPI-MATCHA', 'Matcha Latte', 28000, [
            ['component_id' => $matcha->id, 'qty' => 20],    // 20g * 350 = 7000
            ['component_id' => $milk->id, 'qty' => 180],     // 180ml * 22 = 3960
            ['component_id' => $syrup->id, 'qty' => 20],     // 20ml * 15 = 300
            ['component_id' => $cup12->id, 'qty' => 1],      // 1 cup = 850
        ], overheadFixed: 500, overheadPercent: 5, hppService: $hppService, user: $user);

        $this->upsertRecipeProduct($business, $nonKopiCategory, 'NONKOPI-TEA', 'Es Teh Manis Jasmine', 8000, [
            ['component_id' => $tea->id, 'qty' => 8],        // 8g * 80 = 640
            ['component_id' => $syrup->id, 'qty' => 25],     // 25ml * 15 = 375
            ['component_id' => $cup12->id, 'qty' => 1],      // 1 cup = 850
        ], overheadFixed: 300, overheadPercent: 5, hppService: $hppService, user: $user);

        // 4. Produk Makanan Jadi (Moving Average - Beli Langsung Jadi)
        $croissant = Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'SNACK-CROISSANT'],
            [
                'name' => 'Croissant Butter',
                'kind' => 'product',
                'is_sellable' => true,
                'price' => 20000,
                'cost' => 8800,
                'hpp' => 8800,
                'cost_source' => CostSource::Hpp->value,
                'hpp_method' => HppMethod::Average->value,
                'stock' => 25,
                'unit' => 'pcs',
                'status' => 'active',
            ]
        );

        $brownies = Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'SNACK-BROWNIES'],
            [
                'name' => 'Brownies Cokelat Panggang',
                'kind' => 'product',
                'is_sellable' => true,
                'price' => 15000,
                'cost' => 6000,
                'hpp' => 6000,
                'cost_source' => CostSource::Hpp->value,
                'hpp_method' => HppMethod::Average->value,
                'stock' => 15,
                'unit' => 'pcs',
                'status' => 'active',
            ]
        );

        // 5. Produk Merchandise (Modal Manual - Tidak Berubah oleh HPP)
        Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => 'MERCH-TUMBLER'],
            [
                'category_id' => $merchCategory->id,
                'name' => 'Tumbler Stainless Steel 500ml Nexa',
                'kind' => 'product',
                'is_sellable' => true,
                'price' => 125000,
                'cost' => 65000,
                'hpp' => 65000,
                'cost_source' => CostSource::Manual->value,
                'hpp_method' => HppMethod::None->value,
                'stock' => 30,
                'unit' => 'pcs',
                'status' => 'active',
            ]
        );

        // 6. Catat Riwayat Perubahan HPP untuk Keperluan Audit / Timeline
        $this->seedCostHistories($croissant, $user);
        $this->seedCostHistories($brownies, $user);
    }

    private function upsertRawMaterial(
        Business $business,
        Category $category,
        string $sku,
        string $name,
        float $cost,
        string $unit,
        float $stock,
        float $minStock
    ): Product {
        return Product::updateOrCreate(
            ['business_id' => $business->id, 'sku' => $sku],
            [
                'category_id' => $category->id,
                'name' => $name,
                'kind' => 'product',
                'is_sellable' => false, // Bahan Baku / persediaan tidak dijual langsung di kasir
                'price' => 0,
                'cost' => $cost,
                'hpp' => $cost,
                'cost_source' => CostSource::Hpp->value,
                'hpp_method' => HppMethod::Average->value,
                'stock' => $stock,
                'min_stock' => $minStock,
                'unit' => $unit,
                'status' => 'active',
                'hpp_calculated_at' => now(),
            ]
        );
    }

    /**
     * @param  list<array{component_id: int, qty: float}>  $recipe
     */
    private function upsertRecipeProduct(
        Business $business,
        Category $category,
        string $sku,
        string $name,
        int $price,
        array $recipe,
        float $overheadFixed,
        float $overheadPercent,
        HppService $hppService,
        ?User $user
    ): Product {
        $product = Product::updateOrCreate(
            ['business_id' => $business->id, 'sku' => $sku],
            [
                'category_id' => $category->id,
                'name' => $name,
                'kind' => 'product',
                'is_sellable' => true,
                'price' => $price,
                'cost_source' => CostSource::Hpp->value,
                'hpp_method' => HppMethod::Recipe->value,
                'hpp_overhead_fixed' => $overheadFixed,
                'hpp_overhead_percent' => $overheadPercent,
                'unit' => 'cup',
                'status' => 'active',
            ]
        );

        // Sinkronisasi komponen resep
        $itemsToSync = [];
        foreach ($recipe as $r) {
            $itemsToSync[] = [
                'component_product_id' => $r['component_id'],
                'quantity' => $r['qty'],
            ];
        }

        $hppService->syncRecipe($product, $itemsToSync, $user);
        $product->refresh();

        return $product;
    }

    private function seedCostHistories(Product $product, ?User $user): void
    {
        $existing = ProductCostHistory::query()->where('product_id', $product->id)->exists();
        if ($existing) {
            return;
        }

        ProductCostHistory::create([
            'business_id' => $product->business_id,
            'product_id' => $product->id,
            'field' => 'cost',
            'old_value' => (float) $product->cost * 0.9,
            'new_value' => (float) $product->cost,
            'source' => 'receipt',
            'reference_id' => 'PO-INITIAL-01',
            'user_id' => $user?->id,
            'created_at' => now()->subDays(3),
        ]);
    }
}
