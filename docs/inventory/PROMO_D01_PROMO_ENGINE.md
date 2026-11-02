# PROMO-D01 — Promo Engine (Server-Defined)

Status: ready for execution (belum ada kode).
Branch: `feat/promo-d01-promo-engine`
Depends on: **INV-D01** (untuk analitik diskon di laporan). Tidak bergantung pada INV-D02.
Kerangka: [INV-D00 Roadmap](./INV_D00_ROADMAP_STOCK_WAREHOUSE_PROMO.md)

Tugas ini membuat promo yang didefinisikan owner di dashboard, disinkronkan ke POS, dan **divalidasi ulang oleh server** saat transaksi masuk. Diskon manual kasir tetap diizinkan; promo memakai jalur yang lebih ketat.

---

## 1. Kondisi awal yang harus dipahami agent

- Belum ada tabel, model, service, route, atau test promo.
- `sales.subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `gross_profit` adalah snapshot immutable dari device (`SyncAuthorizationPolicy::saleFinancialSnapshotChanged()`).
- **Tidak ada** pengecekan `subtotal - discount + tax == total` di mana pun. Tugas ini menambahkannya.
- `SyncPushService::processSales()` menyalin nilai device apa adanya.
- `ReportDatasetBuilder` belum mengagregasi `discount_amount` (INV-D01 menambahkan summary dasar).
- Pola permission/route/CRUD: `docs/dashboard/DASH17_DEVICE_MANAGEMENT.md`.
- Semua tabel baru wajib `HasSyncMetadata`, `unique(business_id, id)`, dan composite FK bertipe tenant.

---

## 2. Deliverable file

### Baru

| File | Isi |
|---|---|
| `database/migrations/<ts>_create_promos_table.php` | tabel promo |
| `database/migrations/<ts>_create_promo_targets_table.php` | target produk/kategori |
| `database/migrations/<ts>_create_sale_promotions_table.php` | jejak promo per penjualan |
| `database/migrations/<ts>_add_promo_sync_id_to_sales_table.php` | kolom additive |
| `app/Enums/PromoDiscountType.php` | `percent`, `fixed`, `price_override` |
| `app/Enums/PromoScope.php` | `all`, `product`, `category` |
| `app/Enums/PromoStatus.php` | `draft`, `active`, `inactive` |
| `app/Models/Promo.php` | model + relasi + `HasSyncMetadata` |
| `app/Models/PromoTarget.php` | model target |
| `app/Models/SalePromotion.php` | model jejak |
| `app/Services/Promo/PromoEvaluator.php` | fungsi murni perhitungan diskon |
| `app/Services/Promo/PromoService.php` | CRUD, status, validasi bentrok |
| `app/Http/Controllers/Dashboard/PromosController.php` | index/store/update/status |
| `app/Http/Requests/Dashboard/StorePromoRequest.php` | validasi create |
| `app/Http/Requests/Dashboard/UpdatePromoRequest.php` | validasi update |
| `app/Http/Requests/Dashboard/UpdatePromoStatusRequest.php` | validasi status |
| `resources/views/promos/index.blade.php` | halaman promo |
| `resources/views/components/promos/summary-cards.blade.php` | KPI promo |
| `resources/views/components/promos/filter-bar.blade.php` | filter status/periode |
| `resources/views/components/promos/table.blade.php` | tabel desktop |
| `resources/views/components/promos/mobile-cards.blade.php` | kartu mobile |
| `resources/views/components/promos/promo-modal.blade.php` | modal create/edit |
| `resources/views/components/promos/empty-state.blade.php` | empty state |
| `tests/Feature/PromoEvaluatorTest.php` | unit test pembulatan & kelayakan |
| `tests/Feature/PromoManagementTest.php` | CRUD & RBAC |
| `tests/Feature/PromoSyncTest.php` | pull & validasi push |
| `tests/Feature/PromoReportTest.php` | agregasi di laporan |

### Diubah

| File | Perubahan |
|---|---|
| `app/Services/Authorization/BusinessPermission.php` | `PROMO_VIEW`, `PROMO_MANAGE` |
| `routes/web.php` | 4 route promo |
| `app/Services/Sync/SyncPullService.php` | entity `promos` + `promo_targets` |
| `app/Services/Sync/SyncPushService.php` | simpan `promo_sync_id` + baris `sale_promotions` |
| `app/Services/Sync/SyncAuthorizationPolicy.php` | `sale_total_mismatch`, `promo_discount_mismatch`, `promo_usage_limit_exceeded` |
| `app/Http/Requests/Api/SyncPushRequest.php` | `sales.*.promo_sync_id` nullable uuid |
| `app/Models/Sale.php` | `promo_sync_id` fillable + relasi `promotions()` |
| `app/Services/Dashboard/Reporting/ReportDatasetBuilder.php` | section `promoSummary` |
| `app/Services/Dashboard/Reporting/ReportDataset.php` | field promo |
| `app/Services/Dashboard/Reporting/*Formatter.php` | baris laporan promo |
| `resources/views/reports/index.blade.php` | kartu & tabel promo |
| `resources/views/components/ui/sidebar.blade.php` | item "Promo" (gated `promos.view`) |

---

## 3. Skema

### 3.1 `promos`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id`, `business_id` | | `cascadeOnDelete` |
| `name` | string(120) | |
| `code` | string(40) nullable | unik per bisnis bila diisi |
| `discount_type` | string(20) | `percent` \| `fixed` \| `price_override` |
| `discount_value` | unsignedInteger | percent = **basis point** (1000 = 10%), lainnya rupiah |
| `scope` | string(20) default `all` | `all` \| `product` \| `category` |
| `min_purchase` | unsignedBigInteger default 0 | minimal subtotal |
| `max_discount` | unsignedBigInteger nullable | cap untuk percent |
| `weekdays` | json nullable | `[1..7]`, 1 = Senin; null = semua hari |
| `starts_at`, `ends_at` | timestamp nullable | |
| `start_time`, `end_time` | string(5) nullable | `HH:MM` lokal |
| `usage_limit_total` | unsignedInteger nullable | |
| `usage_limit_per_customer` | unsignedInteger nullable | |
| `priority` | unsignedInteger default 0 | tie-breaker |
| `is_stackable` | boolean default false | fase ini hanya satu promo per sale |
| `status` | string(20) default `draft` | `draft` \| `active` \| `inactive` |
| sync + timestamps | | |

Index: `(business_id, status)`, `(business_id, starts_at, ends_at)`, unique `(business_id, id)`, unique `(business_id, code)`.

### 3.2 `promo_targets`

`id`, `business_id`, `promo_id`, `target_type` (`product`\|`category`), `target_id`, timestamps.
Unique `(business_id, promo_id, target_type, target_id)`. Composite FK ke `promos`, `products`, `categories` lewat `(business_id, id)`.

### 3.3 `sale_promotions`

`id`, `business_id`, `sale_id`, `promo_id`, `promo_sync_id` (uuid), `discount_amount` unsignedInteger, `created_at`.
Unique `(business_id, sale_id, promo_id)`. Index `(business_id, promo_id)`.

### 3.4 `sales.promo_sync_id`

`uuid nullable` + index `(business_id, promo_sync_id)`. Additive; sale lama tetap `null`.

---

## 4. `PromoEvaluator` — spesifikasi perhitungan

Fungsi murni tanpa DB, agar hasilnya identik di server dan mobile:

```php
/**
 * @param  list<array{product_id:int, category_id:?int, unit_price:int, quantity:float, line_total:int}>  $lines
 * @return array{eligible: bool, discount: int, reason: ?string}
 */
public function evaluate(Promo $promo, int $subtotal, array $lines, CarbonInterface $at): array;
```

Urutan pengecekan:
1. `status === 'active'`.
2. `at` di dalam `starts_at`/`ends_at` (null = tak terbatas).
3. `weekdays` cocok dengan `$at->dayOfWeekIso` (null = semua).
4. `start_time`/`end_time` cocok dengan jam `$at` (null = sepanjang hari; rentang yang melewati tengah malam harus ditangani).
5. `subtotal >= min_purchase`.
6. Target cocok: `scope='all'` selalu cocok; `product`/`category` minimal satu baris cocok.
7. Hitung diskon:
   - `percent`: `$discount = intdiv($subtotal * $promo->discount_value, 10000);`
   - `fixed`: `$discount = $promo->discount_value;`
   - `price_override`: `$discount = max(0, $targetSubtotal - $promo->discount_value * $targetQty);`
8. `$discount = min($discount, $promo->max_discount ?? $discount, $subtotal);`
9. Kembalikan `eligible=true` dengan `discount`.

Aturan pembulatan ini **wajib persis**; dokumentasikan di handoff mobile. Tidak boleh memakai `round()` atau float untuk percent.

---

## 5. Route & permission

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `promos` | `promos.index` | `promos.view` |
| POST | `promos` | `promos.store` | `promos.manage` |
| PATCH | `promos/{promoId}` | `promos.update` | `promos.manage` |
| PATCH | `promos/{promoId}/status` | `promos.status.update` | `promos.manage` |

Matrix:

| Permission | owner | member | cashier |
|---|:-:|:-:|:-:|
| `promos.view` | ✓ | ✓ | ✗ |
| `promos.manage` | ✓ | ✗ | ✗ |

Semua route memakai `premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD`. `promoId` memakai `->whereNumber('promoId')`, dan controller melakukan `where('business_id', ...)->whereKey(...)->firstOrFail()`.

Validasi bisnis di `PromoService`:
- `discount_type = percent` → `discount_value <= 10000`.
- `discount_type = fixed` → `discount_value > 0`.
- `price_override` → wajib `scope != 'all'` dan target minimal satu.
- `starts_at < ends_at`.
- `start_time < end_time` (atau tandai sebagai rentang lewat tengah malam dengan aturan eksplisit).
- Tolak promo `active` yang targetnya sama dengan promo `active` lain pada periode yang tumpang tindih, dengan pesan Indonesia yang jelas.
- `code` unik per bisnis (case-insensitive).

---

## 6. Sinkronisasi

### 6.1 Pull

`SyncPullService::pull()` tambah blok:

```php
$promos = Promo::where('business_id', $business->id)
    ->where('status', '!=', PromoStatus::Draft->value)
    ->where('sync_sequence', '>', $after)
    ->where('sync_sequence', '<=', $serverSequence)
    ->orderBy('sync_sequence')
    ->limit($fetchLimit)
    ->with('targets')
    ->get();
```

Entity `promos` wajib memuat: `sync_id`, `sync_version`, `name`, `code`, `discount_type`, `discount_value`, `scope`, `min_purchase`, `max_discount`, `weekdays`, `starts_at`, `ends_at`, `start_time`, `end_time`, `usage_limit_total`, `usage_limit_per_customer`, `is_stackable`, `status`, `targets[]` (`target_type`, `target_sync_id`).

Entity `promo_targets` juga boleh dikirim terpisah bila lebih konsisten dengan pola entity lain; pilih satu dan dokumentasikan.

### 6.2 Push

`SyncPushRequest`: tambah `'changes.sales.*.promo_sync_id' => ['nullable', 'uuid']` (additive).

`SyncAuthorizationPolicy` — tiga pengecekan baru:

1. **`sale_total_mismatch`** untuk semua sale baru:
   `subtotal - discount_amount + tax_amount !== total_amount` → violation. Ini menutup lubang integritas yang sudah ada. **Periksa fixture test lama**: bila ada test yang mengirim angka tidak konsisten, perbaiki fixture-nya, jangan melemahkan aturan.
2. **`promo_discount_mismatch`** hanya bila `promo_sync_id` diisi:
   - promo harus ada dan milik bisnis yang sama,
   - `status = active`,
   - `PromoEvaluator` menyatakan eligible pada `sold_at`,
   - `discount_amount` **sama persis** dengan hasil evaluator.
3. **`promo_usage_limit_exceeded`**:
   - total pemakaian (`sale_promotions` + sale dalam request ini) ≤ `usage_limit_total`,
   - pemakaian per pelanggan ≤ `usage_limit_per_customer` (hanya bila `customer_sync_id` ada; bila tidak ada, hanya batas total yang berlaku).

Tanpa `promo_sync_id`, `discount_amount > 0` tetap diizinkan sebagai **diskon manual** (perilaku lama), dan tidak membuat baris `sale_promotions`.

`SyncPushService::processSales()` simpan `promo_sync_id`. Setelah sale dan sale items diproses, buat `sale_promotions` bila `promo_sync_id` ada dan diskon > 0. Gunakan `firstOrCreate` agar retry idempoten.

---

## 7. Laporan & analitik

`ReportDatasetBuilder` tambah ke summary:

- `total_discount` (dari `sales.discount_amount`),
- `discount_rate`,
- `promo_transactions` (sale dengan `promo_sync_id != null`),
- `manual_discount_transactions` (diskon > 0 tanpa `promo_sync_id`).

Tambah section `promoSummary` (agregasi per promo dari `sale_promotions` join `sales` yang `status='completed'`, `payment_status='paid'`, dalam window filter): `promo_name`, `redeemed_count`, `total_discount`, `revenue_after_discount`, `avg_basket`.

Tampilkan di `resources/views/reports/index.blade.php` sebagai tabel "Performa Promo" dan sertakan di ketiga formatter export. Untuk `avg_basket` kosong, tampilkan `0`.

---

## 8. Acceptance criteria

- [ ] Owner bisa membuat, mengubah, dan mengaktifkan promo; member hanya bisa melihat; kasir 403.
- [ ] Promo persen memakai basis point dan pembulatan `intdiv`, bukan float.
- [ ] `max_discount` membatasi diskon persen; diskon tidak pernah melebihi subtotal.
- [ ] Promo di luar jadwal/hari/jam tidak eligible.
- [ ] Target produk/kategori dihormati; `scope='all'` berlaku untuk semua baris.
- [ ] Pull menyertakan promo non-draft beserta target dan `sync_id`-nya.
- [ ] Push sale dengan `promo_sync_id` dan diskon tidak sesuai → `promo_discount_mismatch`.
- [ ] Push sale dengan total tidak konsisten → `sale_total_mismatch`.
- [ ] Kuota total/per pelanggan ditegakkan; retry request yang sama tidak menambah kuota.
- [ ] Sale tanpa `promo_sync_id` dengan diskon manual tetap diterima dan tercatat sebagai diskon manual.
- [ ] Laporan menampilkan total diskon, rasio diskon, dan performa per promo di layar + CSV/XLSX/PDF.

---

## 9. Test yang wajib ditulis

`PromoEvaluatorTest.php`
1. percent 10% dari 100.000 = 10.000.
2. percent dengan cap 5.000 → 5.000.
3. percent pembulatan floor (subtotal 99.999, 10% → 9.999).
4. fixed lebih besar dari subtotal → dipotong ke subtotal.
5. min_purchase tidak terpenuhi → tidak eligible.
6. di luar `starts_at`/`ends_at` → tidak eligible.
7. weekday dan jam tidak cocok → tidak eligible.
8. target produk tidak ada di lines → tidak eligible.
9. `price_override` menghitung berdasarkan qty target.

`PromoManagementTest.php`
1. owner create/update/status.
2. member GET 200, POST 403.
3. cashier GET 403.
4. validasi `discount_type=percent` dengan nilai > 10000 ditolak.
5. `starts_at` setelah `ends_at` ditolak.
6. `code` duplikat dalam satu bisnis ditolak; bisnis lain boleh.
7. promo tenant lain → 404.
8. bentrok target & periode ditolak dengan pesan Indonesia.

`PromoSyncTest.php`
1. pull hanya mengirim promo non-draft + target.
2. sale dengan promo valid diterima dan membuat `sale_promotions`.
3. diskon tidak sesuai → 403 `promo_discount_mismatch`.
4. `sale_total_mismatch` untuk total tidak konsisten.
5. kuota total habis → `promo_usage_limit_exceeded`.
6. retry `request_id` yang sama tidak menggandakan `sale_promotions`.
7. diskon manual tanpa `promo_sync_id` tetap diterima.

`PromoReportTest.php`
1. `total_discount` & `discount_rate` benar.
2. breakdown per promo: redeemed count, total diskon, avg basket.
3. CSV/XLSX/PDF memuat section promo.

---

## 10. Verifikasi

```bash
php artisan test --filter=PromoEvaluatorTest
php artisan test --filter=PromoManagementTest
php artisan test --filter=PromoSyncTest
php artisan test --filter=PromoReportTest

# regresi wajib — aturan total baru menyentuh semua sales
php artisan test --filter=SyncPushTest
php artisan test --filter=CashierSafeSyncTest
php artisan test --filter=SaleFoundationTest
php artisan test --filter=SyncIntegrityP38Test
php artisan test --filter=ReportExportTest

composer ci:check
npm run build
```

---

## 11. Out of scope

- Kupon unik sekali pakai per pelanggan dengan generator kode.
- Stacking beberapa promo sekaligus (`is_stackable` disimpan tetapi belum diaktifkan).
- Auto-renewal atau promo berulang otomatis.
- Promo lintas bisnis / platform-level.
- Perubahan kode POS mobile (hanya kontrak API yang disiapkan).

## 12. Definition of Done

Semua test §9 hijau, gate `composer ci:check` dan `npm run build` lulus, dokumen ini `Status: implemented` dengan bukti test, PR ke `main` dari `feat/promo-d01-promo-engine`.
