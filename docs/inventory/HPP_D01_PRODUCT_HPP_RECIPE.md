# HPP-D01 — Product HPP & Recipe Engine (BOM)

Status: ✅ Selesai dikerjakan & teruji (8 Feature Tests, 168 Catalog Tests, 124 Stock Tests passed).  
Branch: `feat/hpp-d01-product-hpp-recipe`  
Roadmap: [INV_D00_ROADMAP_STOCK_WAREHOUSE_PROMO.md](./INV_D00_ROADMAP_STOCK_WAREHOUSE_PROMO.md)

---

## 1. Latar Belakang & Tujuan
Fitur ini menyediakan sistem kalkulasi dan pengelolaan **HPP (Harga Pokok Penjualan / Cost of Goods Sold)** untuk produk di dashboard. HPP bersifat fleksibel dan dapat dikonfigurasi per produk:
- Pengguna dapat menentukan apakah harga modal acuan (`cost`) otomatis disinkronkan dari HPP atau tetap manual.
- Mendukung metode perhitungan: **Moving Average** (Rata-rata Tertimbang Stok), **Last Purchase** (Harga Beli Terakhir), dan **Recipe / BOM** (Komposisi Bahan Baku).
- Menjaga kerahasiaan bisnis: kasir di aplikasi kasir (POS Mobile) hanya menerima harga modal acuan (`cost`) untuk kalkulasi laba kotor transaksi, tanpa dapat melihat formula, overhead, maupun resep HPP internal.

---

## 2. Aturan Bisnis & Algoritma

### 2.1 Moving Average (Rata-rata Tertimbang Stok)
Saat terjadi penerimaan barang (*Stock Receipt*) dengan harga satuan (`unit_cost`):
$$\text{HPP Baru} = \frac{(\text{Sisa Stok} \times \text{Modal Lama}) + (\text{Qty Masuk} \times \text{Harga Beli Baru})}{\text{Sisa Stok} + \text{Qty Masuk}}$$

- **Pengecekan Sisa Stok**: Rumus menggunakan stok sebelum penerimaan (`stockBefore`).
- Jika sisa stok $\le 0$, HPP baru langsung mengambil harga beli baru (`unit_cost`).
- Jika `cost_source === 'hpp'`, harga modal acuan (`cost`) otomatis diupdate ke HPP baru.
- Jika `cost_source === 'manual'`, HPP baru tetap dihitung dan dicatat di riwayat, namun `cost` acuan tidak diubah otomatis sampai user menekan *"Salin HPP ke Modal"*.

### 2.2 Resep / Komposisi Bahan Baku (BOM)
Untuk produk olahan (contoh: minuman, makanan, paket):
$$\text{Subtotal Bahan} = \sum (\text{Qty Bahan}_i \times \text{Modal Bahan}_i)$$
$$\text{Overhead} = \text{Overhead Tetap} + \left(\text{Subtotal Bahan} \times \frac{\text{Overhead \%}}{100}\right)$$
$$\text{HPP Resep} = \text{Subtotal Bahan} + \text{Overhead}$$

- **Cascading Recalculation**: Saat harga modal suatu bahan baku berubah (misal susu naik), sistem otomatis mencari seluruh produk olahan yang memakai susu dan menghitung ulang HPP resepnya secara berantai.
- **Pencegahan Siklus Melingkar (Circular Dependency)**: Sistem memvalidasi graph resep secara transitif agar produk $A$ tidak dapat memasukkan produk $B$ jika $B$ (secara langsung maupun tidak langsung) telah memakai $A$.

---

## 3. Skema Basis Data

### 3.1 Perubahan Kolom Tabel `products`
Migration: `2026_11_02_000001_add_hpp_columns_to_products_table.php`
- `hpp` (`decimal(15,2)`, default: `cost` saat migrasi)
- `hpp_method` (`string(20)`, default: `'average'`) — Enum `HppMethod`: `average`, `last_purchase`, `recipe`, `none`
- `cost_source` (`string(10)`, default: `'hpp'`) — Enum `CostSource`: `manual`, `hpp`
- `hpp_overhead_fixed` (`decimal(15,2)`, default: `0`)
- `hpp_overhead_percent` (`decimal(5,2)`, default: `0`)
- `hpp_calculated_at` (`timestamp`, nullable)

### 3.2 Tabel `product_recipe_items`
Migration: `2026_11_02_000002_create_product_recipe_items_table.php`
- `id` (bigint, PK)
- `business_id` (foreign key ke `businesses.id`, cascade on delete)
- `product_id` (foreign key ke `products.id`, cascade on delete)
- `component_product_id` (foreign key ke `products.id`, restrict on delete)
- `quantity` (`decimal(15,4)`)
- `note` (`string(255)`, nullable)
- Unique constraint: `[product_id, component_product_id]`

### 3.3 Tabel `product_cost_histories`
Migration: `2026_11_02_000003_create_product_cost_histories_table.php`
- `id` (bigint, PK)
- `business_id` (foreign key ke `businesses.id`, cascade on delete)
- `product_id` (foreign key ke `products.id`, cascade on delete)
- `field` (`string(20)`) — `cost` atau `hpp`
- `old_value` (`decimal(15,2)`)
- `new_value` (`decimal(15,2)`)
- `source` (`string(50)`) — `receipt`, `manual_update`, `apply_hpp`, `recipe_recalculation`
- `reference_id` (`string(100)`, nullable)
- `user_id` (foreign key ke `users.id`, nullable)
- `created_at` (`timestamp`)

---

## 4. Arsitektur Layanan

- **`App\Services\Inventory\HppService`**:
  - `applyReceipt()`: Logika moving average saat stok masuk.
  - `recalculateRecipe()`: Kalkulasi resep bahan + overhead tetap + overhead %.
  - `syncRecipe()`: Sinkronisasi relasi resep produk.
  - `cascadeRecipeUpdates()`: Update berantai produk turunan saat harga komponen berubah.
  - `assertNoCircularDependency()`: Deteksi circular reference pada graph resep.
  - `applyHppToCost()`: Menyalin HPP terhitung ke `cost` aktif dan mencatat audit trail.
- **`App\Services\Inventory\StockLedgerService`**:
  - Menghubungkan penerimaan barang (*Stock Receipt*) dengan `HppService::applyReceipt()`.
- **`App\Services\Dashboard\CatalogManagementService`**:
  - Menangani mutasi pembuatan dan pembaruan produk dengan field HPP dan resep.
- **`App\Http\Controllers\Dashboard\ProductsController`**:
  - Endpoint `POST /products/{productId}/apply-hpp` untuk menyalin HPP ke modal secara manual.

---

## 5. Tampilan Pengguna (UI)

1. **Catalog Modal (`catalog-modal.blade.php`)**:
   - Card *"Harga Modal & HPP"* dengan Live Margin Indicator.
   - Pilihan Sumber Modal (`Manual` vs `Otomatis Ikuti HPP`).
   - Pilihan Metode Hitung HPP (`Rata-rata`, `Beli Terakhir`, `Resep`, `None`).
   - Tombol *"Salin ke Modal"* untuk menerapkan nilai kalkulasi HPP ke modal aktif.
   - Form builder resep dinamis dengan pemilihan bahan baku, takaran, satuan otomatis, kalkulasi live subtotal & overhead.
2. **Detail Drawer (`detail-drawer.blade.php`)**:
   - Menampilkan modal acuan (badge `Manual` / `Otomatis HPP`), HPP terhitung, dan estimasi margin.
   - Rincian resep komposisi bahan baku dan biaya operasional overhead.
3. **Product Table & Mobile Cards (`product-table.blade.php` & `mobile-cards.blade.php`)**:
   - Kolom *"Modal (HPP)"* dengan status label (`Auto HPP`, `Resep`, atau `Manual`).
   - Kolom *"Harga Jual"* menampilkan margin persentase laba secara responsif.

---

## 6. Verifikasi & Pengujian

Rangkaian tes otomatis:
- `tests/Feature/ProductHppTest.php`:
  - `test_01_stock_receipt_with_manual_cost_source_updates_hpp_without_changing_cost`: PASSED
  - `test_02_stock_receipt_with_hpp_cost_source_updates_both_hpp_and_cost`: PASSED
  - `test_03_stock_receipt_when_initial_stock_is_zero_or_negative_uses_new_unit_cost`: PASSED
  - `test_04_recipe_calculation_with_components_and_overhead`: PASSED
  - `test_05_stock_receipt_on_ingredient_cascades_to_recipe_product`: PASSED
  - `test_06_prevent_circular_dependency_in_recipe`: PASSED
  - `test_07_apply_hpp_endpoint_copies_hpp_to_cost_and_records_history`: PASSED
  - `test_08_crud_stores_and_updates_product_with_recipe_and_hpp_settings`: PASSED
- `StockReceiptTest` (8 tests): PASSED
- `ProductSellableTest` (6 tests): PASSED
- `ProductsPageTest` (46 tests): PASSED
- `ProductsCrudTest` (32 tests): PASSED
- Pint Code Formatter: Clean & standard.
