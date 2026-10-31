# INV-D00 — Roadmap: Gudang, Riwayat Stok, dan Promo per Tipe Bisnis

Status: draft rencana (belum ada kode yang diubah).
Scope: roadmap lintas domain untuk `INV-D01` (stock ledger & riwayat stok), `INV-D02` (gudang & persediaan), dan `PROMO-D01` (promo engine). Dokumen ini adalah rencana induk; spesifikasi tiap fase ditulis terpisah saat fase tersebut dikerjakan.
Keputusan lingkup: persediaan level bisnis dulu, promo terdefinisi di server, dikerjakan bertahap 3 fase, mutasi stok oleh owner + member.

## 0. Daftar tugas eksekusi

Dokumen ini adalah kerangka. Setiap tugas di bawah adalah spesifikasi mandiri yang bisa langsung diserahkan ke satu agent.

| Task | Judul | File | Depends on |
|---|---|---|---|
| A | Stock Ledger & Riwayat Stok | [INV_D01_STOCK_LEDGER_HISTORY.md](./INV_D01_STOCK_LEDGER_HISTORY.md) | — |
| B | Gudang & Persediaan | [INV_D02_WAREHOUSE_OPERATIONS.md](./INV_D02_WAREHOUSE_OPERATIONS.md) | Task A |
| C | Promo Engine | [PROMO_D01_PROMO_ENGINE.md](./PROMO_D01_PROMO_ENGINE.md) | Task A (untuk analitik diskon) |

Urutan eksekusi yang disarankan: **A → B → C**. B dan C tidak boleh dikerjakan bersamaan dengan A yang belum merge, karena keduanya memakai `StockLedgerService`/`StockMovementType`.

---

## 1. Ringkasan eksekutif

Saat ini sistem **belum punya gudang** dan **belum punya riwayat stok yang bisa dipakai owner**. Yang ada:

- `products.stock` — satu angka scalar per produk per bisnis (bukan per outlet).
- `stock_movements` — tabel ledger sudah ada, tetapi **hanya `movement_type='sale'`** yang pernah ditulis, dan hanya dari sync push device.
- Dashboard `/stock` **read-only**: daftar stok + drawer 50 movement terakhir per produk. Tidak ada penyesuaian, penerimaan, opname, atau halaman riwayat.
- **Promo belum ada sama sekali.** `sales.discount_amount` hanya snapshot pasif dari device; server tidak menghitung, tidak memvalidasi, tidak melaporkan.
- Tipe bisnis (`cafe`, `laundry`, `grosir`) hanya memengaruhi menu sidebar dan default form katalog — tidak memengaruhi inventory maupun promo.

Rencana ini menambahkan tiga lapis secara berurutan, tanpa memutus kontrak sync yang sudah berjalan:

| Fase | Nama | Hasil yang bisa dipakai owner |
|---|---|---|
| 1 | INV-D01 — Stock Ledger & Riwayat Stok | Riwayat stok lengkap, penyesuaian stok, penerimaan barang, rekap in/out |
| 2 | INV-D02 — Gudang & Persediaan | Stock opname, bahan baku non-jual, HPP dari penerimaan, profil per tipe bisnis |
| 3 | PROMO-D01 — Promo Engine | Promo tersimpan di server, tersinkron ke POS, tervalidasi saat push, laporan promo |

Prinsip yang dipegang di semua fase: **`products.stock` tetap otoritatif**, setiap perubahan stok wajib menghasilkan baris `stock_movements`, dan hanya webhook/sync/server yang boleh mengubah angka uang & stok (client tidak pernah dipercaya).

---

## 2. Temuan kunci (kondisi saat ini)

### 2.1 Data & alur stok

| Aspek | Kenyataan di kode |
|---|---|
| Stok per lokasi | Tidak ada. `outlets` tidak punya relasi stok. `stock_movements` tidak punya `outlet_id`. |
| Penulis stok | Hanya `SyncPushService::processStockMovements()` (delta dari device) + `CatalogManagementService::createProduct()` (stok awal saat create). |
| Otoritas delta | Server: `stock_after_server = locked_stock + quantity_change`; nilai `stock_before/stock_after` device hanya bukti historis. |
| Idempotency | `stock_movements.sync_id` (uuid, dari `HasSyncMetadata`). |
| Lock order | `Product` dulu (`lockForUpdate`) → baru `StockMovement` (deadlock-safe). |
| Label tipe | `DashboardStockData::presentMovementType()` sudah mengenal `adjustment` → "Penyesuaian", tetapi **tidak ada kode yang menulis tipe itu**. |
| Permission | Hanya `stock.view` (owner/member/cashier). Tidak ada `stock.manage`. |
| Menu | `resources/views/components/ui/sidebar.blade.php` — item "Stok" tanpa turunan/riwayat. |

### 2.2 Uang & promo

| Aspek | Kenyataan di kode |
|---|---|
| Kalkulasi total | **Tidak ada.** `SyncPushService::processSales()` menyalin `subtotal/discount_amount/tax_amount/total_amount` apa adanya dari device. |
| Invariant aritmetika | **Tidak ada** pengecekan `subtotal - discount + tax == total`. |
| Immutability | `SyncAuthorizationPolicy::saleFinancialSnapshotChanged()` menolak perubahan snapshot finansial setelah sale terbentuk. |
| Cash reconciliation | `authorizeCashLedger()` mewajibkan `amount === total_amount` (`cash_amount_mismatch`). |
| Reporting | `ReportDatasetBuilder` hanya menjumlah `total_amount` & `gross_profit`. `discount_amount` **tidak pernah** diagregasi. |
| Tabel promo | Tidak ada. Tidak ada migration, model, service, route, view, test. |

### 2.3 Tipe bisnis

| Aspek | Kenyataan di kode |
|---|---|
| Enum | `app/Enums/BusinessType.php`: `cafe`, `laundry`, `grosir`. "retail" hanya alias → `grosir`. |
| Pengaruh | Sidebar "Pesanan Laundry" + default `kind/unit/pricing_unit` di modal katalog. |
| Batas | Tipe bisnis **tidak boleh** menjadi otorisasi (aturan DASH-14 §7) — hanya relevansi/default. |
| Bug kecil | `ManualRegistrationService::provision()` menyimpan `business_type='retail'` (non-kanonik). Perlu diperbaiki jadi `grosir`. |

---

## 3. Matriks kebutuhan per tipe bisnis

| Kebutuhan | Cafe | Laundry | Grosir / Retail |
|---|---|---|---|
| Barang yang dilacak | Produk jual (cup/menu) + bahan baku (kopi, susu) | Jasa (kg/pcs, tidak berstok) + bahan habis pakai (deterjen, pewangi) | SKU jual, barcode, stok minimum |
| Penjualan mengurangi stok? | Ya untuk produk; **tidak** untuk bahan baku (butuh resep — di luar lingkup) | **Tidak** untuk `kind=service` | Ya per SKU |
| Penerimaan barang | Bahan baku, update HPP | Bahan habis pakai | Pembelian stok, update HPP |
| Opname | Mingguan/bulanan untuk bahan | Mingguan untuk bahan | Bulanan + audit variance |
| Promo utama | Happy hour, bundling menu | Minimal kg, potongan paket | Potongan volume, harga member |
| Metrik khas | Pemakaian bahan vs penjualan, waste | Pemakaian deterjen per kg cucian | Stock turnover, dead stock, shrinkage |

Aturan implementasi: tipe bisnis hanya mengubah **preset/default/panduan**, bukan hak akses. Semua route tetap dijaga `business.permission:*`.

---

## 4. Arsitektur target

```
                        ┌──────────────────────────────────────────┐
 Dashboard (owner/member)│  StockController / StockOpnameController │
                        │  PromoController                          │
                        └───────────────┬──────────────────────────┘
                                        │ FormRequest + business.permission
                        ┌───────────────▼──────────────────────────┐
                        │ StockLedgerService  (satu-satunya jalur   │
                        │ tulis stok dashboard: lock product →      │
                        │ hitung delta → simpan movement → update   │
                        │ products.stock)                           │
                        │ StockOpnameService  PromoService          │
                        └───────────────┬──────────────────────────┘
                                        │
        ┌───────────────────────────────┼────────────────────────────┐
        ▼                               ▼                            ▼
  stock_movements                 stock_opnames                promos / promo_targets
  (ledger, HasSyncMetadata)       + items                      (HasSyncMetadata)
        │                               │                            │
        └───────────────┬───────────────┴────────────┬───────────────┘
                        ▼                            ▼
                 SyncPullService               SyncPushService
                 (entity baru: stock_movements, (validasi promo saat sale masuk)
                  promos, promo_targets)
```

Aturan tulis stok (wajib sama dengan sync):
1. `DB::transaction`.
2. `Product::where(business_id)->whereKey(id)->lockForUpdate()->firstOrFail()`.
3. Hitung `delta`, `stock_before = stock`, `stock_after = stock + delta`.
4. Insert `stock_movements` (via Eloquent agar `HasSyncMetadata` mengisi `sync_id`, `sync_version`, `sync_sequence`).
5. Update `products.stock = stock_after`.
6. Jangan pernah menulis `products.stock` tanpa baris movement.

---

## 5. FASE 1 — INV-D01: Stock Ledger & Riwayat Stok

Tujuan: owner bisa melihat seluruh pergerakan stok dan mencatat penyesuaian/penerimaan. Ini fondasi untuk fase 2 dan 3.

### 5.1 Skema

**Baru: `app/Enums/StockMovementType.php`**

```php
enum StockMovementType: string {
    case Sale       = 'sale';
    case Receipt    = 'receipt';      // penerimaan barang / restock
    case Adjustment = 'adjustment';   // koreksi manual (+/-)
    case Opname     = 'opname';       // hasil stock opname
    case Usage      = 'usage';        // pemakaian bahan (cafe/laundry)
    case Return     = 'return';       // retur ke supplier
    public function label(): string;   // Penjualan, Penerimaan, Penyesuaian, Opname, Pemakaian, Retur
    public static function values(): array;
    public static function labelFor(string $raw): string;
}
```

**Migration baru** `database/migrations/<ts>_add_ledger_columns_to_stock_movements_table.php`

| Kolom | Tipe | Catatan |
|---|---|---|
| `user_id` | `unsignedBigInteger nullable` | siapa yang mencatat (null = dari device/sync) |
| `unit_cost` | `decimal(15,2) nullable` | HPP saat penerimaan (untuk update cost) |
| `idempotency_key` | `string(64) nullable` | cegah double submit dari dashboard |

Index: `(business_id, occurred_at)`, `(business_id, product_id, occurred_at)`, unique `(business_id, idempotency_key)`.

> Jangan menambah `outlet_id` di fase ini. Multi-gudang ditunda (keputusan: level bisnis dulu).

**Model** `app/Models/StockMovement.php`: tambahkan `user_id`, `unit_cost`, `idempotency_key` ke `#[Fillable]`, cast `unit_cost` → `decimal:2`, relasi `user(): BelongsTo`. Tambahkan `Product::stockMovements(): HasMany`.

### 5.2 Service tulis

**Baru:** `app/Services/Inventory/StockLedgerService.php`

```php
final class StockLedgerService
{
    public function __construct(private readonly ?int $lockTimeout = null) {}

    /**
     * @param  array{note?: ?string, reference_id?: ?string, idempotency_key?: ?string,
     *               unit_cost?: ?string, occurred_at?: ?CarbonInterface}  $meta
     */
    public function record(
        Business $business,
        Product $product,
        StockMovementType $type,
        float $delta,
        array $meta = [],
        ?User $user = null,
    ): StockMovement;

    public function receive(Business $b, User $u, array $lines, array $meta): array; // multi-line penerimaan
}
```

Perilaku:
- Validasi: `delta !== 0`, produk milik bisnis, `product->kind !== 'service'` (jasa tidak berstok), `product->status !== 'deleted'`.
- Idempotency: jika `idempotency_key` sudah ada untuk bisnis itu → kembalikan movement lama, jangan terapkan ulang.
- Lock product → insert movement → update stock, dalam satu transaksi.
- Jika `unit_cost` diisi dan `type = Receipt`: update `products.cost` dengan **weighted average**:
  `cost_baru = (stock_lama * cost_lama + qty * unit_cost) / (stock_lama + qty)` (clamp bila `stock_lama + qty <= 0` → gunakan `unit_cost`).
- `occurred_at` default `now()`.

### 5.3 Permission & route

**`app/Services/Authorization/BusinessPermission.php`**

- Tambah `public const STOCK_MANAGE = 'stock.manage';`
- Tambahkan ke role `member` (owner sudah `*`). `cashier` tetap tidak dapat.

**`routes/web.php`** (di dalam group dashboard, tepat setelah blok stock yang ada)

| Method | URI | Name | Permission | Keterangan |
|---|---|---|---|---|
| GET | `stock/history` | `stock.history` | `stock.view` | halaman riwayat |
| GET | `stock/history/data` | `stock.history.data` | `stock.view` | JSON feed (filter/pagination) |
| POST | `stock/adjustments` | `stock.adjustments.store` | `stock.manage` | penyesuaian +/- |
| POST | `stock/receipts` | `stock.receipts.store` | `stock.manage` | penerimaan multi-line |

Semua route wajib ikut membawa `premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD` seperti route stock yang ada, dan `throttle:stock-write` (daftarkan limiter di `AppServiceProvider`).

> Catatan urutan: daftarkan `stock/history` sebelum `stock/{productId}/movements` agar tidak tertangkap wildcard.

### 5.4 Read side

**Baru:** `app/Services/Dashboard/DashboardStockHistoryData.php` (mengikuti pola `DashboardStockData`)

```php
public const PER_PAGE = 25;
public function get(?Business $business, array $filters): array;   // items, summary, filterOptions
public function summary(?Business $business, array $filters): array;
```

Filter: `q` (nama/SKU produk), `product_id`, `type[]` (multi), `user_id`, `from`, `to`, `page`.
Summary: `total_in`, `total_out`, `net_change`, `movement_count`, `adjustment_count`, `stock_value` (`SUM(stock * cost)` pada produk non-deleted, `kind='product'`).

Query: `StockMovement::where(business_id)->with(['product:id,name,sku,unit', 'user:id,name'])`, `orderByDesc(occurred_at)->orderByDesc(id)`, `paginate(25)`.

### 5.5 Controller & view

- `app/Http/Controllers/Dashboard/StockController.php`: tambah `history(Request): View`, `historyData(Request): JsonResponse`, `storeAdjustment(...)`, `storeReceipt(...)`. Alternatif lebih rapi: controller baru `StockLedgerController` untuk history + `StockMovementController` untuk mutasi — pilih **satu controller baru `StockLedgerController`** agar `StockController` tetap fokus ke daftar stok.
- FormRequest baru: `app/Http/Requests/Dashboard/StockAdjustmentRequest.php`, `StockReceiptRequest.php` (pola `StoreDeviceRequest`: `authorize()` cek `STOCK_MANAGE`, `messages()` bahasa Indonesia, tenant check `abort(404)`).
- View:
  - `resources/views/stock/history.blade.php` — layout mengikuti `resources/views/stock/index.blade.php` (header, flash, summary cards, filter bar, tabel desktop + kartu mobile, pagination).
  - `resources/views/components/stock/history-summary-cards.blade.php`
  - `resources/views/components/stock/history-filter-bar.blade.php`
  - `resources/views/components/stock/history-table.blade.php`
  - `resources/views/components/stock/adjustment-modal.blade.php` (create penerimaan + penyesuaian dalam satu modal, pola `device-modal.blade.php`)
  - Tambah tab "Stok" / "Riwayat" di `resources/views/stock/index.blade.php` dan tautan di `resources/views/components/ui/sidebar.blade.php` (item turunan di bawah Stok, gated `stock.view`).
- `DashboardStockData::presentMovementType()` diubah untuk mendelegasikan ke `StockMovementType::labelFor()` sehingga `receipt`, `opname`, `usage`, `return` langsung punya label.

### 5.6 Analitik cepat (bisa rilis di fase ini)

`ReportDatasetBuilder::build()` tambah:

```php
$totalDiscount = (int) (clone $salesQuery)->sum('discount_amount');
$summary['total_discount'] = $totalDiscount;
$summary['discount_rate'] = $totalSales > 0 ? round($totalDiscount / ($totalSales + $totalDiscount), 4) : 0.0;
```

Tambahkan section `discountSummary` ke `ReportDataset` + tampilkan di `resources/views/reports/index.blade.php` dan formatter CSV/XLSX/PDF (`CsvReportFormatter`, `XlsxReportFormatter`, `PdfReportFormatter`).

Ini memberi owner data diskon **sebelum** promo engine ada, dan memvalidasi bahwa kolom `discount_amount` dari device memang berisi.

### 5.7 Test & dokumen fase 1

- `tests/Feature/StockHistoryPageTest.php` — akses owner/member/cashier, tenant isolation, filter, pagination 25, summary.
- `tests/Feature/StockAdjustmentTest.php` — owner & member bisa, cashier 403, delta 0 ditolak, produk jasa ditolak, `products.stock` konsisten dengan `stock_before/after`, idempotency key tidak dobel, concurrency (dua request paralel → stok akhir benar).
- `tests/Feature/StockReceiptTest.php` — multi-line, update HPP weighted average, unit cost opsional.
- `docs/inventory/INV_D01_STOCK_LEDGER_HISTORY.md` — sudah ada sebagai task spec; ubah `Status` menjadi `implemented` dan tambahkan bukti test setelah selesai.

---

## 6. FASE 2 — INV-D02: Gudang & Persediaan

### 6.1 Bahan baku / non-sellable

**Migration** `add_is_sellable_to_products_table.php`: `boolean('is_sellable')->default(true)`.
Alasan: cafe perlu melacak bahan baku yang tidak muncul di menu POS; laundry perlu deterjen. Tanpa kolom ini, semua item stok otomatis menjadi barang jual.

- `app/Models/Product.php`: tambah `is_sellable` ke fillable + cast `boolean`.
- `app/Http/Requests/Dashboard/ProductRequest.php` + `CatalogManagementService`: terima toggle `is_sellable`; non-sellable hanya muncul di tab "Persediaan", bukan tab produk jual.
- `SyncPullService::pull()` payload `products` tambah `is_sellable` (additive, aman untuk client lama).
- **Guard integritas**: `SyncAuthorizationPolicy` menolak `sale_items` yang menunjuk produk `is_sellable = false` dengan reason `product_not_sellable`. Ini mencegah bahan baku terjual dari device lama sebelum mobile diperbarui.
- **Batas**: resep/BOM (pemakaian bahan otomatis per penjualan) **tidak** termasuk fase ini — dicatat manual sebagai `usage`.

### 6.2 Stock opname

**Migration**

`stock_opnames`: `id`, `business_id`, `code` (unik per bisnis), `status` (`draft|posted|cancelled`), `note`, `created_by` (FK users), `posted_by` nullable, `posted_at` nullable, timestamps, `unique(business_id, id)`, `unique(business_id, code)`, sync columns.

`stock_opname_items`: `id`, `business_id`, `stock_opname_id`, `product_id`, `system_stock decimal(15,3)`, `counted_stock decimal(15,3)`, `variance decimal(15,3)` (generated/app-level), `unit_cost decimal(15,2) nullable`, timestamps, composite FK `(business_id, stock_opname_id)`, `(business_id, product_id)`.

**Service** `app/Services/Inventory/StockOpnameService.php`
- `open(Business, User, array $meta): StockOpname` — snapshot `system_stock` untuk semua produk `kind='product'`, `status!='deleted'`, `is_sellable` apa pun yang dilacak.
- `saveCounts(StockOpname, array $lines): void` — hanya saat `draft`.
- `post(StockOpname, User): array` — untuk tiap item dengan `variance != 0`, panggil `StockLedgerService::record(..., StockMovementType::Opname, variance)`; set `posted_at`, `posted_by`. Tidak boleh `post` dua kali.
- `cancel(StockOpname, User): void`.

**Route** (permission `stock.manage`): `stock/opname` (index), `stock/opname` (store), `stock/opname/{opnameId}` (show), `stock/opname/{opnameId}/counts` (PATCH), `stock/opname/{opnameId}/post` (POST), `stock/opname/{opnameId}/cancel` (POST).

**View**: `resources/views/stock/opname/{index,show}.blade.php` + komponen `stock/opname-table.blade.php`.

### 6.3 Penerimaan, HPP, dan profil tipe bisnis

- Penerimaan multi-line sudah dibuat di fase 1 (`stock.receipts.store`); fase 2 menambahkan update HPP weighted average + ringkasan nilai persediaan.
- **Baru:** `app/Support/Inventory/InventoryProfile.php`

```php
final class InventoryProfile
{
    /** @return array{tracked_kinds: list<string>, default_units: list<string>,
     *                suggest_min_stock: bool, opname_cadence_days: int,
     *                promo_templates: list<string>} */
    public static function for(?BusinessType $type): array;
}
```

| Tipe | `tracked_kinds` | Unit default | Opname | Template promo |
|---|---|---|---|---|
| `cafe` | `product` (+ bahan baku non-jual) | `pcs`, `gram`, `ml` | 7 hari | happy hour, bundling |
| `laundry` | `product` (bahan habis pakai) — `service` dikecualikan | `kg`, `liter`, `pcs` | 14 hari | minimal kg, paket |
| `grosir` | `product` | `pcs`, `dus`, `karung` | 30 hari | volume, harga member |
| `null` (belum ditentukan) | `product` | `pcs` | 30 hari | — |

Dipakai oleh: form katalog (default `unit`/`kind`), form opname (cadence reminder), form promo (template), dan kartu bantuan di halaman stok. **Tidak** dipakai untuk otorisasi.

### 6.4 Metrik gudang (data analyst)

Tambahkan ke `DashboardStockData::get()`:
- `stock_value` = `SUM(stock * cost)`.
- `dead_stock_count` = produk tanpa movement `sale` dalam 60 hari terakhir dan `stock > 0`.
- `shrinkage_rate` = `SUM(ABS(variance)) / SUM(system_stock)` dari opname `posted` pada periode.
- `days_of_cover` = `stock / rata-rata pemakaian harian 30 hari` (dari movement `sale` + `usage`).

Tampilkan di `dashboard` overview (`DashboardOverviewData::buildStockPanel`) dan halaman stok.

### 6.5 Test & dokumen fase 2

- `tests/Feature/ProductSellableTest.php` — default true, guard `product_not_sellable` saat push, pull payload additive.
- `tests/Feature/StockOpnameTest.php` — draft→posted, variance menghasilkan movement `opname` tepat sekali, post dua kali ditolak, tenant isolation, cashier 403.
- `tests/Feature/StockMetricsTest.php` — stock value, dead stock, shrinkage.
- `docs/inventory/INV_D02_WAREHOUSE_OPERATIONS.md`.

---

## 7. FASE 3 — PROMO-D01: Promo Engine

### 7.1 Skema

**`promos`**

| Kolom | Tipe | Catatan |
|---|---|---|
| `id`, `business_id` | | `foreignId(...)->constrained('businesses')->cascadeOnDelete()` |
| `name` | string | |
| `code` | string nullable | voucher code (opsional di fase ini) |
| `discount_type` | string | `percent` \| `fixed` \| `price_override` |
| `discount_value` | `unsignedInteger` | percent dalam **basis point** (1000 = 10%), fixed/override dalam rupiah |
| `scope` | string | `all` \| `product` \| `category` |
| `min_purchase` | `unsignedBigInteger` default 0 | minimal subtotal |
| `max_discount` | `unsignedBigInteger nullable` | cap untuk percent |
| `weekdays` | json nullable | `[1,2,3]` (1=Senin) |
| `starts_at`, `ends_at` | timestamp nullable | |
| `start_time`, `end_time` | string(5) nullable | jam lokal, untuk happy hour |
| `usage_limit_total` | unsignedInteger nullable | |
| `usage_limit_per_customer` | unsignedInteger nullable | |
| `priority` | unsignedInteger default 0 | jika beberapa promo cocok |
| `is_stackable` | boolean default false | fase ini: satu promo per sale |
| `status` | string default `draft` | `draft` \| `active` \| `inactive` |
| sync + timestamps | | `HasSyncMetadata`, `unique(business_id, id)` |

**`promo_targets`**: `id`, `business_id`, `promo_id`, `target_type` (`product`\|`category`), `target_id`, composite FK, `unique(business_id, promo_id, target_type, target_id)`.

**`sale_promotions`** (traceability, bukan tabel transaksi baru): `id`, `business_id`, `sale_id`, `promo_id`, `promo_sync_id`, `discount_amount`, `created_at`, `unique(business_id, sale_id, promo_id)`.

**`sales`**: tambah `promo_sync_id` (uuid nullable) — additive, memudahkan validasi & pull.

### 7.2 Service

- `app/Enums/PromoDiscountType.php`, `app/Enums/PromoScope.php`, `app/Enums/PromoStatus.php`.
- `app/Services/Promo/PromoEvaluator.php` — **pure function**, tanpa DB, dipakai server & didokumentasikan untuk mobile agar hasil identik.

```php
/**
 * @return array{eligible: bool, discount: int, reason: ?string}
 */
public function evaluate(Promo $promo, int $subtotal, array $lines, CarbonInterface $at): array;
```

Aturan pembulatan (wajib sama di mobile):
- `percent`: `discount = intdiv($subtotal * $bp, 10000)` (floor), lalu `min($discount, $max_discount ?? PHP_INT_MAX)`, lalu `min($discount, $subtotal)`.
- `fixed`: `min($discount_value, $subtotal)`.
- `price_override`: `discount = max(0, subtotal_target - harga_promo * qty)`, dibatasi subtotal.
- Tolak jika `subtotal < min_purchase`, di luar `starts_at/ends_at`, di luar `weekdays`/jam, target tidak cocok, atau kuota habis.

- `app/Services/Promo/PromoService.php` — CRUD + aktivasi + validasi bentrok (promo aktif dengan target sama dan periode sama → tolak atau beri warning).

### 7.3 Dashboard

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `promos` | `promos.index` | `promos.view` |
| POST | `promos` | `promos.store` | `promos.manage` |
| PATCH | `promos/{promoId}` | `promos.update` | `promos.manage` |
| PATCH | `promos/{promoId}/status` | `promos.status.update` | `promos.manage` |

Permission baru: `PROMO_VIEW` (owner, member) dan `PROMO_MANAGE` (owner saja — promo menyentuh harga, sebaiknya tetap owner). Semua route membawa `premium.access:WEB_DASHBOARD`.

View: `resources/views/promos/index.blade.php` + `components/promos/{summary-cards,filter-bar,table,mobile-cards,promo-modal,empty-state}.blade.php`. Modal mengikuti `device-modal.blade.php` (satu modal create/edit, restore state lewat `old()`).

### 7.4 Sinkronisasi

- `SyncPullService::pull()` tambah entity `promos` dan `promo_targets` (filter `status != draft`, berdasarkan `sync_sequence`). Payload `promos` berisi seluruh field + `discount_value`, `scope`, jendela waktu, kuota.
- `SyncAuthorizationPolicy` tambah pengecekan:
  - `promo_discount_mismatch` — jika `sale.promo_sync_id` diisi: promo harus ada, `status='active'`, berlaku pada `sold_at`, target cocok, `discount_amount` **sama persis** dengan hasil `PromoEvaluator`.
  - `sale_total_mismatch` — invariant baru `subtotal - discount_amount + tax_amount === total_amount`.
  - `promo_usage_limit_exceeded` — hitung dari `sale_promotions` + `sales` existing.
  - Tanpa `promo_sync_id`, `discount_amount > 0` tetap diizinkan **hanya** jika `promos.manage` dimiliki device tersebut? Tidak — pertahankan perilaku lama (diskon manual kasir), tetapi catat `sale_promotions` kosong. Beri flag di laporan: "diskon manual" vs "promo".
- `SyncPushService::processSales()` simpan `promo_sync_id`; setelah sale & items diproses, buat baris `sale_promotions`.

### 7.5 Laporan & analitik promo

`ReportDatasetBuilder` tambahkan section `promoSummary`:
- `total_discount`, `discount_rate`, `promo_transactions`, `manual_discount_transactions`.
- Breakdown per promo: `promo_name`, `redeemed_count`, `total_discount`, `revenue_after_discount`, `avg_basket`.
- `uplift` = `avg_basket_promo / avg_basket_non_promo - 1` (indikator kasar, dokumentasikan keterbatasannya).

Tampilkan di `resources/views/reports/index.blade.php` dan sertakan di ketiga formatter export.

### 7.6 Test & dokumen fase 3

- `tests/Feature/PromoManagementTest.php` — owner CRUD, member/cashier 403, validasi periode, validasi target lintas tenant.
- `tests/Feature/PromoEvaluatorTest.php` — unit test pembulatan percent/fixed/override, cap, min purchase, weekday/jam, kuota.
- `tests/Feature/PromoSyncTest.php` — pull payload, `promo_discount_mismatch`, `sale_total_mismatch`, kuota, idempotency retry.
- `tests/Feature/PromoReportTest.php` — agregasi diskon & breakdown promo di CSV/XLSX/PDF.
- `docs/inventory/PROMO_D01_PROMO_ENGINE.md` — sudah ada sebagai task spec; ubah `Status` menjadi `implemented` setelah selesai.

---

## 8. Lintas fase (wajib)

### 8.1 RBAC

| Permission | owner | member | cashier |
|---|:-:|:-:|:-:|
| `stock.view` (sudah ada) | ✓ | ✓ | ✓ |
| `stock.manage` (baru) | ✓ | ✓ | ✗ |
| `promos.view` (baru) | ✓ | ✓ | ✗ |
| `promos.manage` (baru) | ✓ | ✗ | ✗ |

Perbarui `docs/dashboard/DASH10B2_CASHIER_RBAC.md` dan sidebar (item baru hanya tampil bila permission dimiliki).

### 8.2 Premium gating

Semua route baru memakai `premium.access:'.PremiumPolicy::CAPABILITY_WEB_DASHBOARD`, konsisten dengan route stok/produk yang ada. Tanpa itu, bisnis free bisa membuka modul lewat URL langsung.

### 8.3 Sync contract

- Semua tabel baru memakai `HasSyncMetadata` + `unique(business_id, id)` + composite FK (pola `devices`).
- Setiap entity baru wajib punya blok di `SyncPullService` dan test bentuk payload.
- Perubahan ke `SyncPushRequest` harus **nullable/additive**; client lama tetap valid.
- Urutan lock: `Product` dulu, baru movement/opname.

### 8.4 Perbaikan kecil yang ikut dibawa

- `app/Services/Auth/ManualRegistrationService.php`: simpan `business_type = 'grosir'` (kanonik), bukan `'retail'`.
- `docs/dashboard/DASH11_LAUNDRY_MONITORING.md` §5 masih menyebut tipe bisnis belum ada — perbarui agar tidak menyesatkan.

### 8.5 Dokumentasi

Ikuti format `docs/<domain>/<FAMILY>_<ID>_<TITLE>.md` dengan H1, `Status:`, `Scope:`, section bernomor, tabel route/permission, dan bagian "Out of scope". Seluruh task pada roadmap ini disimpan di `docs/inventory/`.

---

## 9. Risiko & mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Mutasi stok dari dashboard tidak sampai ke device | Stok device berbeda dengan server | Semua tulisan masuk `stock_movements` dengan `sync_sequence`; tarik lewat pull. Catat sebagai work item mobile: device harus mengadopsi `stock_after` server untuk movement non-`sale`. |
| Double submit form penyesuaian | Stok terpotong dua kali | `idempotency_key` unik per bisnis + tombol disable + test konkuren. |
| Deadlock dengan sync push | Request gagal 500 | Lock order sama (`Product` → movement), transaksi pendek, retry di layer HTTP. |
| Bahan baku terjual dari device lama | Stok negatif salah | Guard `product_not_sellable` di `SyncAuthorizationPolicy`. |
| Promo dihitung beda antara mobile & server | Sale ditolak saat sync | `PromoEvaluator` jadi spesifikasi tunggal + unit test pembulatan; dokumentasikan di handoff mobile. |
| Diskonto melebihi subtotal | Total negatif | Clamp `discount = min(discount, subtotal)` + invariant `sale_total_mismatch`. |
| Backfill `is_sellable` salah | Bahan baku hilang dari katalog | Default `true` (tidak ada perubahan perilaku sampai owner menandai). |

---

## 10. Urutan pengerjaan & branch

1. Merge PR #103 (`feat/dashboard-subscription-checkout`) lebih dulu agar baseline bersih.
2. Branch `feat/inv-d01-stock-ledger-history` → fase 1 → PR.
3. Branch `feat/inv-d02-warehouse-operations` (dari `main` terbaru) → fase 2 → PR.
4. Branch `feat/promo-d01-promo-engine` (dari `main` terbaru) → fase 3 → PR.

Setiap fase harus hijau sebelum lanjut: `composer ci:check` (Pint + PHPStan level 7 + seluruh test) dan `npm run build`.

---

## 11. Verifikasi

Per fase:

```bash
# Targeted
php artisan test --filter=StockHistoryPageTest
php artisan test --filter=StockAdjustmentTest
php artisan test --filter=StockReceiptTest

# Regresi kontrak sync (wajib, karena menyentuh jalur stok/uang)
php artisan test --filter=StockPageTest
php artisan test --filter=SyncPushTest
php artisan test --filter=CashierSafeSyncTest
php artisan test --filter=SaleFoundationTest

# Gate penuh
composer ci:check
npm run build
```

Cek manual:
1. Login owner → `/stock` → tab **Riwayat** menampilkan movement penjualan yang sudah ada.
2. Catat penerimaan 10 pcs → stok naik, muncul 1 baris `receipt` dengan `stock_before/after` benar.
3. Login member → bisa menyesuaikan stok; login kasir → 403.
4. Fase 2: tandai produk sebagai bahan baku → hilang dari tab produk jual, tetap di persediaan; coba push `sale_items` untuk produk itu → ditolak `product_not_sellable`.
5. Fase 3: buat promo percent 10% + cap → push sale dengan `promo_sync_id` dan diskon tidak sesuai → ditolak `promo_discount_mismatch`; diskon sesuai → diterima dan muncul di laporan.

---

## 12. Di luar lingkup (sengaja ditunda)

- Stok per outlet / multi-gudang dan transfer antar gudang (fase lanjutan setelah INV-D02).
- Resep/BOM yang otomatis mengurangi bahan baku per penjualan.
- Supplier master, purchase order, dan hutang dagang.
- Auto-renewal promo, kupon unik sekali pakai per pelanggan dengan generator kode.
- Perubahan kode POS mobile di repo ini.
