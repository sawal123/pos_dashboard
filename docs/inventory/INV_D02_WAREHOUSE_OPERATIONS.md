# INV-D02 — Gudang & Persediaan (Bahan Baku, Opname, Metrik)

Status: implemented on branch `feat/inv-d02-warehouse-operations` (stacked on INV-D01, belum di-merge).
Branch: `feat/inv-d02-warehouse-operations`
Depends on: **INV-D01** (`StockLedgerService`, `StockMovementType`, permission `stock.manage`).
Kerangka: [INV-D00 Roadmap](./INV_D00_ROADMAP_STOCK_WAREHOUSE_PROMO.md)

Tugas ini melengkapi modul gudang: menandai barang non-jual (bahan baku/habis pakai), stock opname dengan variance, HPP dari penerimaan, dan metrik persediaan yang bisa dibaca owner.

---

## 1. Kondisi awal yang harus dipahami agent

- Setelah INV-D01: `StockLedgerService::record()` dan `receive()` sudah menjadi jalur tulis stok dashboard, `StockMovementType` sudah punya case `opname` dan `usage`, permission `stock.manage` sudah ada.
- `products` belum punya penanda barang non-jual. Saat ini semua `kind='product'` dianggap barang jual dan ikut tampil di katalog POS.
- `kind` hanya `product` / `service` (`CatalogManagementService::KINDS`).
- Belum ada tabel opname, supplier, atau purchase order.
- Halaman `/stock` menampilkan summary `total_items`, `safe_stock`, `low_stock`, `critical_stock` dari `DashboardStockData::get()`.

---

## 2. Deliverable file

### Baru

| File | Isi |
|---|---|
| `database/migrations/<ts>_add_is_sellable_to_products_table.php` | kolom boolean |
| `database/migrations/<ts>_create_stock_opnames_table.php` | header opname |
| `database/migrations/<ts>_create_stock_opname_items_table.php` | item hitung |
| `app/Models/StockOpname.php` | model + status constants + `HasSyncMetadata` |
| `app/Models/StockOpnameItem.php` | model item |
| `app/Services/Inventory/StockOpnameService.php` | open / saveCounts / post / cancel |
| `app/Support/Inventory/InventoryProfile.php` | preset per tipe bisnis |
| `app/Http/Controllers/Dashboard/StockOpnameController.php` | index, show, store, updateCounts, post, cancel |
| `app/Http/Requests/Dashboard/StockOpnameRequest.php` | buka opname |
| `app/Http/Requests/Dashboard/StockOpnameCountsRequest.php` | simpan hasil hitung |
| `resources/views/stock/opname/index.blade.php` | daftar sesi opname |
| `resources/views/stock/opname/show.blade.php` | form hitung + variance |
| `resources/views/components/stock/opname-table.blade.php` | tabel hitung |
| `resources/views/components/stock/opname-summary.blade.php` | ringkasan variance |
| `tests/Feature/ProductSellableTest.php` | flag & guard sync |
| `tests/Feature/StockOpnameTest.php` | siklus opname |
| `tests/Feature/StockMetricsTest.php` | metrik persediaan |

### Diubah

| File | Perubahan |
|---|---|
| `app/Models/Product.php` | `is_sellable` fillable + cast boolean |
| `app/Http/Requests/Dashboard/ProductRequest.php` | rule & pesan `is_sellable` |
| `app/Services/Dashboard/CatalogManagementService.php` | simpan `is_sellable`; pisahkan tab barang jual vs persediaan |
| `app/Services/Dashboard/DashboardProductsData.php` | filter tab persediaan |
| `routes/web.php` | route opname |
| `app/Services/Authorization/BusinessPermission.php` | tidak berubah (pakai `stock.view` / `stock.manage` dari INV-D01) |
| `app/Services/Sync/SyncPullService.php` | payload produk + `is_sellable` |
| `app/Services/Sync/SyncAuthorizationPolicy.php` | guard `product_not_sellable` |
| `app/Services/Dashboard/DashboardStockData.php` | metrik gudang |
| `app/Services/Dashboard/DashboardOverviewData.php` | kartu metrik stok |
| `resources/views/stock/index.blade.php` + `components/stock/summary-cards.blade.php` | metrik baru |
| `resources/views/products/index.blade.php` + `components/products/catalog-modal.blade.php` | toggle bahan baku |
| `resources/views/components/ui/sidebar.blade.php` | item "Stock Opname" (gated `stock.view`) |
| `docs/dashboard/DASH15_PRODUCTS_CRUD.md` | catatan `is_sellable` |

---

## 3. Skema

### 3.1 `products.is_sellable`

```php
Schema::table('products', function (Blueprint $table) {
    $table->boolean('is_sellable')->default(true)->after('kind');
});
```

Default `true` = tidak ada perubahan perilaku sampai owner menandai. `down()` drop kolom.

### 3.2 `stock_opnames`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id`, `business_id` | | `cascadeOnDelete` |
| `code` | string | unik per bisnis, format `OPN-YYYYMMDD-####` |
| `status` | string default `draft` | `draft` \| `posted` \| `cancelled` |
| `note` | text nullable | |
| `created_by` | unsignedBigInteger | FK `users.id` |
| `posted_by` | unsignedBigInteger nullable | FK `users.id` |
| `posted_at` | timestamp nullable | |
| sync columns + `timestamps` | | |

Unique: `(business_id, code)`, `(business_id, id)`. Index `(business_id, status, created_at)`.

### 3.3 `stock_opname_items`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id`, `business_id`, `stock_opname_id`, `product_id` | | composite FK ke `(business_id, id)` |
| `system_stock` | `decimal(15,3)` | snapshot saat opname dibuka |
| `counted_stock` | `decimal(15,3)` nullable | diisi saat hitung |
| `variance` | `decimal(15,3)` default 0 | `counted_stock - system_stock` |
| `unit_cost` | `decimal(15,2)` nullable | dari `products.cost` saat snapshot |
| `timestamps` | | |

Unique `(business_id, stock_opname_id, product_id)`. Index `(business_id, stock_opname_id)`.

---

## 4. Logika `StockOpnameService`

```php
public function open(Business $business, User $user, array $data): StockOpname;
public function saveCounts(StockOpname $opname, user $actor, array $lines): void;
public function post(StockOpname $opname, User $user): array;   // ['movements' => int]
public function cancel(StockOpname $opname, User $user): void;
```

Aturan:
1. `open()` dalam transaksi: buat header, snapshot semua produk `kind='product'`, `status != 'deleted'`, `business_id` sama → satu item per produk dengan `system_stock` dan `unit_cost`.
2. `saveCounts()` hanya saat `status='draft'`; update `counted_stock`, hitung `variance`; tolak opname tenant lain (`404` dari controller).
3. `post()` dalam transaksi + `lockForUpdate()` pada header; hanya `draft`; untuk setiap item `variance != 0` panggil `StockLedgerService::record($business, $product, StockMovementType::Opname, (float) $variance, ['note' => "Opname {$opname->code}"], $user)`.
4. `post()` dua kali → `ValidationException::withMessages(['status' => 'Stock opname ini sudah diposting.'])`.
5. `cancel()` hanya `draft`.
6. Jumlah item besar tidak boleh memblokir: batasi snapshot ke produk aktif bisnis, dan bungkus dalam satu transaksi.

---

## 5. Route & permission

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `stock/opname` | `stock.opname.index` | `stock.view` |
| POST | `stock/opname` | `stock.opname.store` | `stock.manage` |
| GET | `stock/opname/{opnameId}` | `stock.opname.show` | `stock.view` |
| PATCH | `stock/opname/{opnameId}/counts` | `stock.opname.counts.update` | `stock.manage` |
| POST | `stock/opname/{opnameId}/post` | `stock.opname.post` | `stock.manage` |
| POST | `stock/opname/{opnameId}/cancel` | `stock.opname.cancel` | `stock.manage` |

Semua route memakai `premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD`. Route `stock/opname` didaftarkan **sebelum** `stock/{productId}/movements`. Semua parameter angka memakai `->whereNumber('opnameId')`.

---

## 6. `InventoryProfile`

```php
namespace App\Support\Inventory;

final class InventoryProfile
{
    /** @return array{tracked_kinds: list<string>, default_units: list<string>,
     *                opname_cadence_days: int, promo_templates: list<string>} */
    public static function for(?BusinessType $type): array;
}
```

| Tipe | `tracked_kinds` | Unit default | Opname | Template promo |
|---|---|---|---|---|
| `cafe` | `['product']` | `pcs`, `gram`, `ml` | 7 | happy_hour, bundle |
| `laundry` | `['product']` | `kg`, `liter`, `pcs` | 14 | min_weight, package |
| `grosir` | `['product']` | `pcs`, `dus`, `karung` | 30 | volume, member_price |
| `null` | `['product']` | `pcs` | 30 | `[]` |

Dipakai untuk default form (katalog, opname) dan teks bantuan. **Tidak** untuk otorisasi. Jangan mengubah `BusinessType` enum untuk ini; buat kelas terpisah.

---

## 7. Guard sync `is_sellable`

`SyncPullService::pull()` — payload `products` tambah `'is_sellable' => (bool) $prod->is_sellable` (additive).

`SyncAuthorizationPolicy` — pada pemeriksaan `sale_items`, jika produk target `is_sellable = false`, tambahkan violation:

```php
[
    'entity' => 'sale_items',
    'operation' => 'upsert',
    'reason' => 'product_not_sellable',
]
```

Alasan: mencegah bahan baku terjual dari device lama sebelum POS mobile diperbarui. Jalankan pengecekan yang sama saat cashier-safe maupun full mode. Tambahkan juga di `SyncPushService` pada guard integritas dalam transaksi bila diperlukan konsistensi.

---

## 8. Metrik gudang

Tambahkan ke `DashboardStockData::get()` → `summary`:

| Metrik | Rumus |
|---|---|
| `stock_value` | `SUM(products.stock * products.cost)`, `kind='product'`, `status!='deleted'` |
| `dead_stock_count` | produk `stock > 0` tanpa movement `sale` dalam 60 hari |
| `shrinkage_rate` | `SUM(ABS(variance)) / NULLIF(SUM(system_stock),0)` dari opname `posted` 90 hari terakhir |
| `days_of_cover` | `stock / AVG(pemakaian harian 30 hari)`; fallback `null` bila pemakaian 0 |

Tampilkan di `components/stock/summary-cards.blade.php` dan kartu ringkas di `DashboardOverviewData::buildStockPanel()`. Format uang `number_format($n, 0, ',', '.')`, persen 1 desimal.

---

## 9. Acceptance criteria

- [ ] Produk baru otomatis `is_sellable = true`; tidak ada backfill yang mengubah data lama.
- [ ] Owner bisa menandai produk sebagai bahan baku; produk hilang dari tab "Produk Jual" dan tetap ada di tab "Persediaan" serta halaman Stok.
- [ ] Push `sale_items` untuk produk `is_sellable=false` ditolak `product_not_sellable`.
- [ ] Payload pull produk menyertakan `is_sellable` tanpa menghapus key lama.
- [ ] Opname `draft` bisa diisi sebagian; `post` membuat movement `opname` hanya untuk variance ≠ 0.
- [ ] `post` dua kali ditolak; opname `cancelled` tidak bisa diposting.
- [ ] Variance nol tidak membuat movement.
- [ ] Kasir dapat melihat opname tetapi semua aksi mutasi 403.
- [ ] Metrik stock value, dead stock, shrinkage tampil dan angkanya benar pada data seed.

---

## 10. Test yang wajib ditulis

`ProductSellableTest.php`
1. default `true` saat create lewat service dan lewat factory.
2. `is_sellable=false` tersimpan dari form produk.
3. produk non-jual tidak muncul di daftar produk jual, muncul di persediaan.
4. push sale item untuk produk non-jual → 403 `product_not_sellable`.
5. pull produk menyertakan `is_sellable` untuk produk jual dan non-jual.

`StockOpnameTest.php`
1. owner membuka opname → item snapshot sesuai jumlah produk `kind=product` non-deleted.
2. member bisa menyimpan hitungan; kasir 403.
3. `variance` dihitung benar (termasuk negatif).
4. `post` membuat movement `opname` hanya untuk variance ≠ 0 dan stok akhir sesuai.
5. `post` dua kali → error validasi, tidak ada movement tambahan.
6. `cancel` setelah posted ditolak.
7. opname tenant lain → 404.

`StockMetricsTest.php`
1. `stock_value` menjumlahkan stok × HPP.
2. `dead_stock_count` hanya menghitung produk tanpa penjualan 60 hari.
3. `shrinkage_rate` dari opname posted.
4. `days_of_cover` null saat tidak ada pemakaian.

---

## 11. Verifikasi

```bash
php artisan test --filter=ProductSellableTest
php artisan test --filter=StockOpnameTest
php artisan test --filter=StockMetricsTest

# regresi
php artisan test --filter=StockAdjustmentTest
php artisan test --filter=StockHistoryPageTest
php artisan test --filter=StockPageTest
php artisan test --filter=ProductsCrudTest
php artisan test --filter=SyncPushTest
php artisan test --filter=CashierSafeSyncTest

composer ci:check
npm run build
```

---

## 12. Out of scope

- Stok per outlet, transfer antar gudang.
- Resep/BOM otomatis.
- Supplier master, purchase order, hutang dagang.
- Promo (tugas PROMO-D01).
- Perubahan kode POS mobile.

## 13. Definition of Done

Semua test §10 hijau, gate `composer ci:check` dan `npm run build` lulus, dokumen ini `Status: implemented` dengan bukti test, PR ke `main` dari `feat/inv-d02-warehouse-operations`.

---

## 14. Bukti implementasi

- `products.is_sellable` (default `true`) + tab **Persediaan** pada katalog; checkbox "Dijual di POS" di modal produk.
- `SyncPullService` mengirim `is_sellable`; `SyncAuthorizationPolicy` menolak `sale_items` baru untuk produk non-jual (`product_not_sellable`) tanpa merusak retry sale lama.
- `stock_opnames` / `stock_opname_items` + `StockOpnameService` (open, saveCounts, post, cancel) dan halaman `/stock/opname`.
- Posting hanya membuat movement `opname` untuk variance ≠ 0 dan dibungkus satu transaksi.
- `InventoryProfile` menyediakan default unit/opname per tipe bisnis tanpa menyentuh otorisasi.
- Metrik stok: `stock_value`, `dead_stock_count`, `shrinkage_rate`, `days_of_cover` tampil di halaman stok.

Hasil verifikasi:

```
php vendor/bin/phpunit tests/Feature/ProductSellableTest.php \
  tests/Feature/StockOpnameTest.php tests/Feature/StockMetricsTest.php
# OK (18 tests, 53 assertions)

php vendor/bin/phpunit tests/Feature/ProductsCrudTest.php tests/Feature/StockPageTest.php \
  tests/Feature/StockHistoryPageTest.php tests/Feature/StockAdjustmentTest.php \
  tests/Feature/StockReceiptTest.php tests/Feature/SyncPushTest.php \
  tests/Feature/CashierSafeSyncTest.php tests/Feature/SaleFoundationTest.php \
  tests/Feature/ReportExportTest.php
# OK (227 tests, 844 assertions)

vendor/bin/pint --test   # PASS
vendor/bin/phpstan analyse   # no errors
npm run build            # exit 0
```

