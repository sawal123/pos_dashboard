# P37 — Cloud Sync Contract Parity QA (Backend)

- Date: 2026-09-16
- Base/main SHA: `3c58eb7e1e8bf65825b2b849a31f6391caab4d0b`
- Branch: `feat/p37-cloud-sync-contract-parity` (PR to `main`, not merged)
- Depends on / merge order: **backend first, then mobile** — the mobile P37
  contract (`feat/p37-cloud-sync-production-e2e`) pushes `cash_ledger`,
  `stock_movements`, product semantic and sale payment fields that only this
  backend revision validates and stores.
- Environment: PHP `8.4.16`, Laravel `13.26.1`, Pest `5`, SQLite (test harness
  in-memory; E2E on a dedicated `p37e2e.sqlite` file deleted afterwards)

## Schema changes (all additive, no drops/renames/truncates)

| Migration | Change |
|---|---|
| `2026_09_16_082601_add_p37_sync_parity_fields_to_pos_tables` | `products`: `kind` (default `product`), `cost` decimal(15,2) default 0, `stock` decimal(15,3) default 0, `unit` default `pcs`, `min_stock` decimal(15,3) default 0, `pricing_unit` default `pcs`, `min_quantity` decimal(15,3) default 0, `estimated_duration` nullable. New `cash_ledger` table (business/outlet FKs, optional shift FK, `type` in/out, `amount`, `category`, `note`, `reference_id`, `sale_sync_id`, `occurred_at`, full sync metadata + `unique(business_id,sync_id)` + `index(business_id,sync_sequence)`). New `stock_movements` table (business/product FKs, `movement_type`, decimal `quantity_change/stock_before/stock_after`, `reference_id`, `category`, `note`, `sale_sync_id`, `occurred_at`, full sync metadata + same uniqueness/indexes). Stable idempotency key is `sync_id` (one sale may emit several movements sharing one `reference_id`, one per product). `sales`: nullable `payment_method`, `payment_status` default `paid`, nullable `paid_at`/`cash_received`/`change_amount` |
| `2026_09_16_094711_add_p37_snapshot_tombstone_fields_to_sync_tables` | `sale_items`: `cost_snapshot` decimal default 0, `unit`/`kind`/`pricing_unit` defaults, `line_cost` decimal default 0. `sales`: `gross_profit` decimal default 0, nullable `order_status`/`estimated_completed_at`/`note`, nullable JSON `customer_snapshot`/`business_snapshot`. `expenses`: nullable `category`. Historical HPP always uses `cost_snapshot`, never recomputed from `Product.cost`. Tombstone deletes (`changes.deletions[]` for categories/products/customers/expenses) set `status=deleted`/`void` and bump `sync_version`/`sync_sequence`; immutable history (sales/sale_items/cash_ledger/stock_movements/shifts) is rejected, never hard-deleted |
| `2026_09_16_082833_change_sale_items_quantity_to_decimal` | `sale_items.quantity` `unsignedInteger` → `decimal(15,3)`; integer values preserved as-is (Laundry sells `2.5 kg`) |

Verified on the dev DB: both migrations ran (`migrate:status` shows them `Ran`);
`down()` drops only the P37 additions in reverse order.

## Contract (push `/api/sync/push`, pull `/api/sync/pull`)

Supported entities after P37 (9): `categories`, `products`, `customers`,
`shifts`, `sales` (+`gross_profit`, `order_status`, `estimated_completed_at`,
`note`, `customer_snapshot`, `business_snapshot`), `sale_items` (+`cost_snapshot`,
`unit`, `kind`, `pricing_unit`, `line_cost`), `expenses` (+`category`),
**`cash_ledger`**, **`stock_movements`**, plus **`changes.deletions[]`**
tombstones for `categories/products/customers/expenses`. Entity allowlist in
`SyncPushRequest::withValidator` rejects anything else with 422.

Extended validation rules:

- `products.*`: `kind` in `product,service`; `cost/stock/min_stock/min_quantity`
  numeric ≥ 0; `unit/pricing_unit` ≤ 50 chars; `estimated_duration` nullable ≤ 255
- `sales.*`: `payment_method` nullable ≤ 50; `payment_status` in `paid,unpaid`;
  `paid_at` date nullable; `cash_received/change_amount` integer ≥ 0 nullable;
  `gross_profit` numeric; `order_status` in `Masuk,Diproses,Siap Diambil,Selesai`
  nullable; `estimated_completed_at` date nullable; `note` nullable;
  `customer_snapshot`/`business_snapshot` array nullable
- `sale_items.*`: `cost_snapshot`/`line_cost` numeric ≥ 0; `unit`/`pricing_unit`
  ≤ 50; `kind` in `product,service`
- `expenses.*.category` nullable ≤ 255
- `changes.deletions.*`: `entity` in `categories,products,customers,expenses`,
  `sync_id` uuid, `base_sync_version` nullable (history entities rejected)
- `sale_items.*.quantity`: `numeric` ≥ `0.001` (was `integer` ≥ 1) — fractional
  Laundry quantities accepted, zero still rejected
- `cash_ledger.*`: `sync_id` uuid, `type` in `in,out`, `amount` integer ≥ 1,
  `shift_sync_id` uuid nullable, `reference_id`/`sale_sync_id` rules,
  `occurred_at` date
- `stock_movements.*`: `product_sync_id` uuid required, `movement_type` ≤ 50,
  `quantity_change` numeric non-zero, optional before/after snapshots,
  `reference_id`, `occurred_at` date

Processing order inside one transaction (dependency-safe):
`categories → products → customers → shifts → sales → sale_items → expenses →
cash_ledger → stock_movements`, then the idempotency `SyncRequest` row.
`cash_ledger` resolves its `shift_sync_id` against the device outlet;
`stock_movements` resolves `product_sync_id` business-wide; both use the
outlet-scoped / business-scoped concurrency guard like their neighbours.

Idempotency layers: (1) identical `request_id` → `duplicate:true` without
reprocessing; (2) same `sync_id` + matching `base_sync_version` → same-row
update (also for retries of cash/stock rows); multi-product sales share one
`reference_id` across several `sync_id` rows. New movements lock `Product`,
apply `stock_after` so `Product.stock` converges, and retries of the same
`sync_id` never apply twice. Tombstone deletes lock the row, validate
`base_sync_version`, set `status=deleted`/`void` (bumping version/sequence),
and treat missing rows as idempotent success — never hard-delete. All
failures roll the whole batch back atomically.

Pull emits the new fields: products carry `kind/cost/stock/unit/min_stock/
pricing_unit/min_quantity/estimated_duration`; sales carry
`payment_method/payment_status/paid_at/cash_received/change_amount` plus
`gross_profit/order_status/estimated_completed_at/note/customer_snapshot/
business_snapshot`; sale_items carry `cost_snapshot/unit/kind/pricing_unit/
line_cost`; expenses carry `category`; tombstoned masters keep flowing so
other devices deactivate. `sale_items.quantity` is emitted as float;
`cash_ledger` is outlet-scoped with `shift_sync_id` resolution;
`stock_movements` are business-wide with `product_sync_id` resolution.
Cursor/pagination mechanics are unchanged.

Intentionally **not** synced: `business/outlet/device/subscription` rows
(context is read-only; devices use the dedicated register endpoint),
`products.image_data` (device-local base64, no object storage architecture),
local `businessSnapshot` blobs, and `app_meta`/queue/cursor internals.

## Supported entities matrix

| Entity | Push | Pull | Scope |
|---|---|---|---|
| categories | yes | yes | business |
| products (+semantics) | yes | yes | business |
| customers | yes | yes | business |
| shifts | yes | versions only (mobile applies locally) | outlet |
| sales (+payment snapshot) | yes | yes | outlet |
| sale_items (decimal qty) | yes | yes | outlet (via parent sale) |
| expenses | yes | yes | outlet |
| cash_ledger | yes | yes | outlet |
| stock_movements | yes | yes (history; local stock levels stay authoritative) | business |

## Authorization (unchanged, still server-authoritative)

`auth:sanctum` → 401; `mobile` token ability → 403 `MOBILE_TOKEN_REQUIRED`;
business membership → 403 `BUSINESS_ACCESS_DENIED`; cloud subscription
(active `cloud` plan, unexpired) → 403 `CLOUD_SUBSCRIPTION_REQUIRED` (free and
expired subscriptions rejected on both device registration and sync
endpoints); unknown device → 403 `SYNC_DEVICE_INVALID`; inactive device →
403 `DEVICE_INACTIVE`; cross-outlet registration → 409
`DEVICE_OUTLET_MISMATCH`; stale/missing base versions and cross-outlet writes
→ 409 `SYNC_CONFLICT`; duplicate unique keys → 409 `SYNC_DATA_CONFLICT`.

## Tests

New `tests/Feature/SyncParityTest.php` (17 tests, 112 assertions): product
semantic round trip; update preserving unset semantic fields; sale payment
snapshot round trip; decimal quantity round trip; cash exactly-once on retry
and same-sync_id replay; stock deduction retry + multi-product same-reference
movements; pull carries cash/stock with outlet isolation; free subscription
rejected at server; cross-business access rejected; HPP/gross-profit snapshot
round trip; Laundry lifecycle + snapshots round trip; expense category round
trip; stock movement converges server product stock once; tombstone delete
deactivates master and survives pull; immutable history delete rejected;
missing-row delete idempotent; cash pagination/cursor convergence.

Updated `tests/Feature/SaleFoundationTest.php`: `quantity is stored as
integer` → `quantity supports decimal for laundry services` (2.5 round trip).
Updated `database/factories/ProductFactory.php` with semantic defaults.

Full suite: **312 passed, 0 failed** (905 assertions), including the 17 new
parity tests and all pre-existing sync/auth/foundation tests. Style gate:
`./vendor/bin/pint --test` clean on the touched PHP files; `git diff --check`
clean.

## Real HTTP E2E (against this backend)

Mobile `p37-app-service-e2e.spec.js` (actual push/pull services + real Laravel
HTTP on dedicated `p37e2e.sqlite` TEST DB, served via
`DB_CONNECTION=sqlite DB_DATABASE=.../p37e2e.sqlite php artisan serve
--host=127.0.0.1 --port=18010` — shell `DB_*` overrides do **not** reach a
`serve` started without them, so the TEST env must be set on the server
process itself): **RUN #1 PASS (1/1), full TEST DB reset, RUN #2 PASS (1/1)**.
No production/dev database touched; the TEST sqlite file is git-ignored.

## Known manual requirements

Multi-outlet concurrent-device conflict UX, Play/target distribution, and
physical printer checks remain manual and are tracked on the mobile report.
No production data was touched; migrations are additive and backfill-safe.

## Verdict

Contract parity complete on the server side: 9 entities + tombstones,
snapshot/history parity, multi-product stock movements, server stock
convergence, 312/312 green, real app-service E2E RUNx2 PASS. **Ready for the
mobile P37 client.**
