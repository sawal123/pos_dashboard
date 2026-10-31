# INV-D01 — Stock Ledger & Riwayat Stok

Status: implemented on branch `feat/inv-d01-stock-ledger-history` (belum di-merge).
Branch: `feat/inv-d01-stock-ledger-history`
Depends on: tidak ada (fase pertama).
Kerangka: [INV-D00 Roadmap](./INV_D00_ROADMAP_STOCK_WAREHOUSE_PROMO.md)

Tugas ini membangun satu-satunya jalur tulis stok dari dashboard, halaman riwayat stok, penyesuaian, dan penerimaan barang. Setelah selesai, owner/member bisa mengubah stok dengan jejak ledger yang lengkap.

---

## 1. Kondisi awal yang harus dipahami agent

- Tabel `stock_movements` sudah ada, tetapi **hanya `movement_type='sale'`** yang pernah ditulis oleh `SyncPushService::processStockMovements()`.
- `products.stock` adalah otoritas stok. Jangan pernah mengubahnya tanpa membuat baris movement.
- Pola lock yang wajib diikuti: `Product` di-lock lebih dulu (`lockForUpdate`), baru `StockMovement`.
- `StockMovement` sudah memakai `App\Models\Concerns\HasSyncMetadata` (mengisi `sync_id`, `sync_version`, `sync_sequence` saat create).
- Dashboard stok saat ini read-only: `StockController::index()` + `StockController::movements()`.
- Pola controller/route/permission ada di `docs/dashboard/DASH17_DEVICE_MANAGEMENT.md` dan `docs/dashboard/DASH15_PRODUCTS_CRUD.md` — ikuti pola itu.

---

## 2. Deliverable file

### Baru

| File | Isi |
|---|---|
| `app/Enums/StockMovementType.php` | enum `sale`, `receipt`, `adjustment`, `opname`, `usage`, `return` + `label()`, `values()`, `labelFor()` |
| `app/Services/Inventory/StockLedgerService.php` | jalur tulis stok (`record`, `receive`) |
| `app/Services/Dashboard/DashboardStockHistoryData.php` | query riwayat + summary + filter options |
| `app/Http/Controllers/Dashboard/StockLedgerController.php` | `history`, `historyData`, `storeAdjustment`, `storeReceipt` |
| `app/Http/Requests/Dashboard/StockAdjustmentRequest.php` | validasi penyesuaian + otorisasi `stock.manage` |
| `app/Http/Requests/Dashboard/StockReceiptRequest.php` | validasi penerimaan multi-line + otorisasi `stock.manage` |
| `resources/views/stock/history.blade.php` | halaman riwayat |
| `resources/views/components/stock/history-summary-cards.blade.php` | kartu total masuk/keluar/nilai stok |
| `resources/views/components/stock/history-filter-bar.blade.php` | filter produk, tipe, user, tanggal |
| `resources/views/components/stock/history-table.blade.php` | tabel desktop |
| `resources/views/components/stock/history-mobile-cards.blade.php` | kartu mobile |
| `resources/views/components/stock/adjustment-modal.blade.php` | modal penyesuaian + penerimaan |
| `tests/Feature/StockHistoryPageTest.php` | test halaman & akses |
| `tests/Feature/StockAdjustmentTest.php` | test mutasi & konkurensi |
| `tests/Feature/StockReceiptTest.php` | test penerimaan & HPP |

### Diubah

| File | Perubahan |
|---|---|
| `database/migrations/<ts>_add_ledger_columns_to_stock_movements_table.php` | **baru** (lihat §3) |
| `app/Models/StockMovement.php` | fillable `user_id`, `unit_cost`, `idempotency_key`; cast; relasi `user()` |
| `app/Models/Product.php` | relasi `stockMovements(): HasMany` |
| `app/Services/Authorization/BusinessPermission.php` | `STOCK_MANAGE`, grant ke `member` |
| `routes/web.php` | 4 route baru |
| `app/Providers/AppServiceProvider.php` | rate limiter `stock-write` |
| `app/Services/Dashboard/DashboardStockData.php` | `presentMovementType()` delegasi ke enum |
| `resources/views/stock/index.blade.php` | tab "Stok" / "Riwayat" |
| `resources/views/components/ui/sidebar.blade.php` | item "Riwayat Stok" (gated `stock.view`) |
| `app/Services/Dashboard/Reporting/ReportDatasetBuilder.php` | summary diskon (§6) |
| `app/Services/Dashboard/Reporting/ReportDataset.php` | field diskon |
| `resources/views/reports/index.blade.php` + 3 formatter | tampilkan diskon |

---

## 3. Skema

Migration `add_ledger_columns_to_stock_movements_table.php`:

| Kolom | Tipe | Catatan |
|---|---|---|
| `user_id` | `unsignedBigInteger nullable` | FK `users.id`, null = dari device |
| `unit_cost` | `decimal(15,2) nullable` | HPP saat penerimaan |
| `idempotency_key` | `string(64) nullable` | cegah double submit |

Index/unique:
- `index(['business_id', 'occurred_at'])`
- `index(['business_id', 'product_id', 'occurred_at'])`
- `unique(['business_id', 'idempotency_key'])`

Wajib `down()` yang drop index + kolom. Harus jalan di SQLite (test) dan MySQL.

---

## 4. Aturan implementasi `StockLedgerService`

Signature:

```php
public function record(
    Business $business,
    Product $product,
    StockMovementType $type,
    float $delta,
    array $meta = [],
    ?User $user = null,
): StockMovement;

/**
 * @param  list<array{product_id: int, quantity: float, unit_cost?: ?float}>  $lines
 * @return list<StockMovement>
 */
public function receive(Business $business, User $user, array $lines, array $meta = []): array;
```

Wajib:
1. Semua mutasi di dalam `DB::transaction`.
2. Lock produk: `Product::where('business_id', $business->id)->whereKey($product->id)->lockForUpdate()->firstOrFail()`.
3. Tolak `delta == 0`.
4. Tolak `product->kind === 'service'` → `ValidationException::withMessages(['product_id' => 'Layanan tidak memiliki stok.'])`.
5. Tolak `product->status === 'deleted'`.
6. Idempotency: jika `idempotency_key` sudah ada untuk bisnis itu, kembalikan movement lama **tanpa** menerapkan delta. Tangkap `UniqueConstraintViolationException` untuk balapan konkuren lalu baca ulang.
7. Hitung `stock_before` dari nilai terkunci, `stock_after = stock_before + delta`.
8. Simpan movement lewat Eloquent (bukan insert mentah) agar `HasSyncMetadata` bekerja.
9. Update `products.stock` ke `stock_after`.
10. `occurred_at` default `now()`, boleh diisi dari meta.
11. Untuk `receive()`: setiap baris `quantity` harus > 0 dan diubah menjadi delta positif.
12. HPP weighted average bila `unit_cost` diisi:
    ```
    $newStock = $stockBefore + $qty;
    $newCost  = $newStock > 0
        ? (($stockBefore * (float) $product->cost) + ($qty * $unitCost)) / $newStock
        : $unitCost;
    ```
    Simpan dengan `number_format($newCost, 2, '.', '')`.

Jangan mengubah `SyncPushService`. Dua jalur boleh hidup berdampingan; keduanya memakai aturan delta yang sama.

---

## 5. Route & permission

```php
// routes/web.php — daftarkan SEBELUM stock/{productId}/movements
Route::get('stock/history', [StockLedgerController::class, 'history'])
    ->middleware(['business.permission:'.BusinessPermission::STOCK_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
    ->name('stock.history');

Route::get('stock/history/data', [StockLedgerController::class, 'historyData'])
    ->middleware(['business.permission:'.BusinessPermission::STOCK_VIEW, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD])
    ->name('stock.history.data');

Route::post('stock/adjustments', [StockLedgerController::class, 'storeAdjustment'])
    ->middleware(['business.permission:'.BusinessPermission::STOCK_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:stock-write'])
    ->name('stock.adjustments.store');

Route::post('stock/receipts', [StockLedgerController::class, 'storeReceipt'])
    ->middleware(['business.permission:'.BusinessPermission::STOCK_MANAGE, 'premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD, 'throttle:stock-write'])
    ->name('stock.receipts.store');
```

Permission matrix setelah tugas ini:

| Permission | owner | member | cashier |
|---|:-:|:-:|:-:|
| `stock.view` | ✓ | ✓ | ✓ |
| `stock.manage` (baru) | ✓ | ✓ | ✗ |

Rate limiter di `AppServiceProvider::boot()`:

```php
RateLimiter::for('stock-write', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
```

---

## 6. Read side & tampilan

`DashboardStockHistoryData::get(?Business, array $filters): array` mengembalikan:

- `items` — `LengthAwarePaginator` (25/halaman) dari movement, sudah dipetakan ke array;
- `summary` — `total_in`, `total_out`, `net_change`, `movement_count`, `adjustment_count`, `stock_value`;
- `products` — opsi filter (id, name, sku, unit);
- `currentFilters`, `hasAnyMovements`.

Filter yang diterima: `q`, `product_id`, `type[]`, `user_id`, `from`, `to`, `page`. Validasi filter lenient (drop key tidak valid), seperti `DevicesController::index()`.

Nilai `stock_value` = `SUM(products.stock * products.cost)` untuk `business_id`, `status != 'deleted'`, `kind = 'product'`.

Tampilan wajib mengikuti anatomi halaman dashboard yang ada: breadcrumb, H1, flash `session('status')`, blok `$errors`, summary cards, filter bar, tabel desktop + kartu mobile, pagination, modal. Nominal memakai `number_format($n, 0, ',', '.')`.

Label tipe memakai `StockMovementType::labelFor()` — jangan menambah `match` baru di view.

---

## 7. Analitik diskon (bagian dari tugas ini)

- `ReportDatasetBuilder::build()`: jumlahkan `discount_amount` pada query sales yang sudah ada.
- Tambah ke `ReportDataset`: `total_discount`, `discount_rate`.
- Tampilkan di `resources/views/reports/index.blade.php` sebagai kartu "Total Diskon" dan "Rasio Diskon".
- Sertakan baris yang sama di `CsvReportFormatter`, `XlsxReportFormatter`, `PdfReportFormatter`.
- Rumus: `discount_rate = total_discount / (total_sales + total_discount)`; `0.0` bila penyebut 0.

---

## 8. Acceptance criteria

- [ ] Owner dan member dapat mencatat penyesuaian; kasir mendapat 403.
- [ ] Setiap mutasi menghasilkan tepat satu baris `stock_movements` dengan `stock_before`/`stock_after` yang benar.
- [ ] `products.stock` selalu sama dengan `stock_after` movement terakhir.
- [ ] Penyesuaian dengan `idempotency_key` yang sama tidak menggandakan stok.
- [ ] Penerimaan multi-line memperbarui stok semua baris dalam satu transaksi (gagal satu = gagal semua).
- [ ] Penerimaan dengan `unit_cost` memperbarui `products.cost` secara weighted average.
- [ ] Produk `kind = 'service'` ditolak untuk semua mutasi.
- [ ] Halaman `/stock/history` menampilkan movement `sale` yang sudah ada sebelumnya.
- [ ] Filter dan pagination (25/halaman) bekerja; tenant lain tidak terlihat.
- [ ] Kasir tetap bisa membuka riwayat (stock.view) tetapi tidak melihat tombol mutasi.
- [ ] Laporan menampilkan total dan rasio diskon.

---

## 9. Test yang wajib ditulis

`StockHistoryPageTest.php`
1. guest redirect ke login; user tanpa bisnis mendapat halaman kosong 200.
2. owner, member, cashier dapat 200; tenant lain 200 tetapi data kosong.
3. ringkasan total in/out benar dari data seed.
4. filter tipe & rentang tanggal memfilter hasil.
5. pagination 25 per halaman.

`StockAdjustmentTest.php`
1. owner menambah stok → movement `adjustment`, `stock_after` benar.
2. member dapat menyesuaikan; cashier 403.
3. `quantity = 0` ditolak.
4. produk jasa ditolak.
5. produk tenant lain → 404.
6. idempotency key yang sama tidak menggandakan.
7. dua penyesuaian berurutan menghasilkan `stock_before` berantai.
8. `sync_id` & `sync_sequence` terisi dan tidak diekspos di response.

`StockReceiptTest.php`
1. penerimaan 3 baris → 3 movement `receipt`, stok benar.
2. satu baris invalid menggagalkan seluruh transaksi.
3. `unit_cost` memperbarui HPP weighted average.
4. tanpa `unit_cost`, HPP tidak berubah.

---

## 10. Verifikasi

```bash
php artisan test --filter=StockHistoryPageTest
php artisan test --filter=StockAdjustmentTest
php artisan test --filter=StockReceiptTest

# regresi wajib
php artisan test --filter=StockPageTest
php artisan test --filter=SyncPushTest
php artisan test --filter=CashierSafeSyncTest
php artisan test --filter=SaleFoundationTest
php artisan test --filter=ReportExportTest

composer ci:check
npm run build
```

---

## 11. Out of scope

- Stok per outlet / multi-gudang, transfer antar gudang.
- Stock opname (tugas INV-D02).
- Flag `is_sellable` dan bahan baku (tugas INV-D02).
- Promo (tugas PROMO-D01).
- Perubahan kode POS mobile.

## 12. Definition of Done

Semua test di §9 hijau, `composer ci:check` dan `npm run build` lulus, dokumen ini diubah `Status: implemented` dengan bukti hasil test, dan PR dibuat ke `main` dari branch `feat/inv-d01-stock-ledger-history`.

---

## 13. Bukti implementasi

- Branch: `feat/inv-d01-stock-ledger-history` (base `main` b85fae1).
- Rute terdaftar: `stock.history`, `stock.history.data`, `stock.adjustments.store`, `stock.receipts.store`.
- Permission baru `stock.manage` (owner, member); kasir tetap `stock.view` saja.
- Halaman: `/stock` (tab Daftar Stok) dan `/stock/history` (tab Riwayat Stok) dengan summary, filter, tabel desktop, kartu mobile, dan modal penyesuaian/penerimaan.
- Laporan menampilkan Total Diskon dan Rasio Diskon pada layar, CSV, XLSX, dan PDF.

Hasil verifikasi:

```
php vendor/bin/phpunit tests/Feature/StockHistoryPageTest.php \
  tests/Feature/StockAdjustmentTest.php tests/Feature/StockReceiptTest.php
# OK (27 tests, 84 assertions)

php vendor/bin/phpunit tests/Feature/StockPageTest.php tests/Feature/ReportExportTest.php \
  tests/Feature/SyncPushTest.php tests/Feature/CashierSafeSyncTest.php tests/Feature/SaleFoundationTest.php
# OK (151 tests, 646 assertions)

vendor/bin/pint --test   # PASS (457 files)
vendor/bin/phpstan analyse --level 7   # no errors
npm run build            # exit 0
```

