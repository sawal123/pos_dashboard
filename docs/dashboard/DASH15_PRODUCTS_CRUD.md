# DASH-15 — Product, Category & Service Management

Branch: `cmd/dash15-products-crud` · base: `main`

Turns the read-only `/products` catalog into an owner-managed CRUD surface for
**products**, **services** and **categories**, using the existing schema (no new
tables, no new catalog model) and the existing mobile sync contract.

---

## 1. Scope

* Categories: create, rename, activate/deactivate (no hard delete).
* Products: create, edit, activate/deactivate — name, SKU, barcode, category,
  selling price, HPP (cost), unit, minimum stock.
* Services: create, edit, activate/deactivate — name, price, category,
  pricing unit, minimum quantity, estimated duration. Services reuse
  `products.kind = 'service'`; there is **no** second service model.
* No bulk import / bulk edit.

---

## 2. Authorization (RBAC)

New permission `products.manage` (`BusinessPermission::PRODUCTS_MANAGE`).

| Role | products.view (read) | products.manage (mutate) |
| --- | --- | --- |
| owner | ✅ (`*`) | ✅ |
| member | ✅ | ❌ |
| cashier | ✅ | ❌ |
| unknown / none | ❌ | ❌ |

* Enforced server-side by `business.permission:products.manage` on **every**
  POST/PATCH route, plus a `BusinessAuthorizer` check inside each FormRequest
  (belt-and-braces, deny-by-default).
* `business_type` never grants access — it only adapts defaults.
* The active business always comes from the shared dashboard context
  (`dashboard_business`), never from a request parameter.

---

## 3. Routes

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `products` | `products.store` | `products.manage` |
| PATCH | `products/{productId}` | `products.update` | `products.manage` |
| PATCH | `products/{productId}/status` | `products.status.update` | `products.manage` |
| POST | `products/categories` | `products.categories.store` | `products.manage` |
| PATCH | `products/categories/{categoryId}` | `products.categories.update` | `products.manage` |
| PATCH | `products/categories/{categoryId}/status` | `products.categories.status.update` | `products.manage` |

Reading the page stays on `products.view`.

Cross-tenant `productId`/`categoryId` resolves to **404** (existence is never
leaked); a cross-tenant `category_id` on a product fails validation.

---

## 4. Business rules

### Pricing & inventory precision

| Field | Column | Precision |
| --- | --- | --- |
| selling price | `price` | integer (unchanged) |
| HPP | `cost` | `decimal(15,2)` |
| stock | `stock` | `decimal(15,3)` |
| minimum stock | `min_stock` | `decimal(15,3)` |
| minimum quantity | `min_quantity` | `decimal(15,3)` |

* **Negative stock is allowed.** No `min:0` is applied to `stock`; initial stock
  may be negative (this is the only place the dashboard writes `stock`).
* **Stock is movement-authoritative.** Editing a product never overwrites
  `stock`; the value must change through a stock movement. The form therefore
  exposes "Stok Awal" only on create, and update ignores any posted `stock`.
* Minimum stock may be negative; catalog updates never create a stock movement
  (verified by `test_14`).
* Nothing in this feature writes `stock_movements` — that stays with the sales
  and sync pipelines.

### Status lifecycle / no hard delete

* Only `active` and `inactive` are accepted from the dashboard. The `deleted`
  tombstone stays a sync-client responsibility, so dashboard actions can never
  hard-delete or tombstone a synced record.
* Categories are never hard-deleted (so a category still referenced by products
  is safe); deactivating is the only "hide" operation.

### Uniqueness (per business)

* `categories.name`, `products.sku`, `products.barcode` are unique per business
  (matching DB constraints). Same value in another business is allowed.
* A service without a SKU receives a generated business-unique `SRV-…` value
  (the `sku` column is NOT NULL).

---

## 5. Sync compatibility

Every write goes through Eloquent `create()` / `save()`, so `HasSyncMetadata`
mints `sync_id` (UUID), sets `sync_version = 1` and a real `sync_sequence` on
create, and advances `sync_version` + `sync_sequence` on update. Raw/bulk
updates are never used.

* `sync_id` is not mass-assignable → the trait always mints it.
* No change to the sync push/pull payload, base sync version, conflict
  resolution, or the P38 negative-stock parity.
* Editing a price/HPP never mutates `sale_items` snapshots (verified by
  `test_24`).
* Records are only ever deactivated (`status`), which is already understood by
  the pull payload — nothing is hard-deleted.

Dashboard limitation: the dashboard cannot hard-delete or tombstone catalog
records; that remains the sync client's job (documented, no new mechanism).

---

## 6. Business type

`business_type` only tunes the create form; it is never an authorization
boundary:

* **cafe** — default kind `product`, unit `pcs`.
* **laundry** — default kind `service`, default `pricing_unit`/`unit` `kg`,
  fractional quantities supported (`decimal:3`).
* **grosir** — default kind `product`, unit `pcs`, SKU/barcode/minimum stock.
* **unknown / null** — safe defaults (`product`, `pcs`); no assumption of cafe.

Changing the business type never deletes historical catalog or transaction data.

---

## 7. UI

* `Tambah Produk` / `Tambah Layanan` / `Tambah Kategori` open one modal
  (product/service form + category form) — fields switch by kind.
* Row actions: **Edit** (prefilled) and an explicit **Aktifkan/Nonaktifkan**
  status toggle. Non-owner roles see only read-only detail.
* The detail drawer's "Edit Item" feeds the same modal.
* Accessible: focus moves into the modal, Tab is trapped, Escape/backdrop close
  it and focus returns to the trigger; listeners are registered once and are
  idempotent across Livewire navigations.
* Submits disable the button ("Menyimpan…") to prevent double submits; server-side
  uniqueness also rejects duplicates. Validation errors are shown in a page
  banner and the modal reopens on load.
* Dark mode and responsive layout (tables + mobile cards) preserved; existing
  filters, search and pagination continue to work.

---

## 8. Files

* `app/Services/Authorization/BusinessPermission.php` — `PRODUCTS_MANAGE`.
* `app/Services/Dashboard/CatalogManagementService.php` — write logic.
* `app/Http/Requests/Dashboard/{ProductRequest,CategoryRequest,UpdateCatalogStatusRequest}.php`.
* `app/Http/Controllers/Dashboard/ProductsController.php` — CRUD actions.
* `routes/web.php` — mutation routes.
* `resources/views/components/products/catalog-modal.blade.php` — modal.
* `resources/views/components/products/{filter-bar,product-table,service-table,category-table,mobile-cards,detail-drawer}.blade.php`.
* `resources/views/products/index.blade.php` — banners + modal wiring.
* `tests/Feature/ProductsCrudTest.php` — regression suite.

---

## 9. Tests

`tests/Feature/ProductsCrudTest.php` (27 tests): category CRUD, product/service
CRUD, owner-only mutation, member/cashier read-only, unknown role denied,
cross-tenant product/category 404, forged `business_id`, duplicate SKU/barcode
and category name, invalid nominal, laundry decimals, negative stock, minimum
stock, active/inactive, historical snapshot safety, sync metadata, no hard
delete, double submit, and listing integration.

Regression suites run: `ProductsPageTest`, `StockPageTest`, `CashierRbacTest`,
`BusinessTypeTest`, `BusinessTypeNavigationTest`, sync tests and historical sale
tests, then the full suite.

Quality gates: `composer ci:check` (Pint + PHPStan + full suite), `npm run build`,
`git diff --check`.

---

## 10. Out of scope

No bulk import/editing, no hard delete, no change to cash/expense (DASH-16),
reports/export, billing, device management, the POS Mobile implementation or the
sync protocol.
