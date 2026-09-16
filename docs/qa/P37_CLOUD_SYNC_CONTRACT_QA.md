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
| `2026_09_16_082601_add_p37_sync_parity_fields_to_pos_tables` | `products`: `kind` (default `product`), `cost` decimal(15,2) default 0, `stock` decimal(15,3) default 0, `unit` default `pcs`, `min_stock` decimal(15,3) default 0, `pricing_unit` default `pcs`, `min_quantity` decimal(15,3) default 0, `estimated_duration` nullable. New `cash_ledger` table (business/outlet FKs, optional shift FK, `type` in/out, `amount`, `category`, `note`, `reference_id`, `sale_sync_id`, `occurred_at`, full sync metadata + `unique(business_id,sync_id)` + `unique(business_id,reference_id)` + `index(business_id,sync_sequence)`). New `stock_movements` table (business/product FKs, `movement_type`, decimal `quantity_change/stock_before/stock_after`, `reference_id`, `category`, `note`, `sale_sync_id`, `occurred_at`, full sync metadata + same uniqueness/indexes). `sales`: nullable `payment_method`, `payment_status` default `paid`, nullable `paid_at`/`cash_received`/`change_amount` |
| `2026_09_16_082833_change_sale_items_quantity_to_decimal` | `sale_items.quantity` `unsignedInteger` → `decimal(15,3)`; integer values preserved as-is (Laundry sells `2.5 kg`) |

Verified on the dev DB: both migrations ran (`migrate:status` shows them `Ran`);
`down()` drops only the P37 additions in reverse order.

## Contract (push `/api/sync/push`, pull `/api/sync/pull`)

Supported entities after P37 (9): `categories`, `products`, `customers`,
`shifts`, `sales`, `sale_items`, `expenses`, **`cash_ledger`**,
**`stock_movements`**. Entity allowlist in `SyncPushRequest::withValidator`
rejects anything else with 422.

Extended validation rules:

- `products.*`: `kind` in `product,service`; `cost/stock/min_stock/min_quantity`
  numeric ≥ 0; `unit/pricing_unit` ≤ 50 chars; `estimated_duration` nullable ≤ 255
- `sales.*`: `payment_method` nullable ≤ 50; `payment_status` in `paid,unpaid`;
  `paid_at` date nullable; `cash_received/change_amount` integer ≥ 0 nullable
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
update; (3) **reference guard**: a replay that invents a new `sync_id` for an
already-synced business `reference_id` is rejected with `409 SYNC_CONFLICT`
(`server_sync_version: 0`) so a cash entry or stock deduction can never be
created twice. All failures roll the whole batch back atomically.

Pull emits the new fields: products carry `kind/cost/stock/unit/min_stock/
pricing_unit/min_quantity/estimated_duration`; sales carry
`payment_method/payment_status/paid_at/cash_received/change_amount`;
`sale_items.quantity` is emitted as float; `cash_ledger` is outlet-scoped with
`shift_sync_id` resolution; `stock_movements` are business-wide with
`product_sync_id` resolution. Cursor/pagination mechanics are unchanged.

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

New `tests/Feature/SyncParityTest.php` (10 tests, 62 assertions): product
semantic round trip; update preserving unset semantic fields; sale payment
snapshot round trip; decimal quantity round trip; cash exactly-once on retry
and re-request; stock deduction never applied twice; pull carries cash/stock
with outlet isolation; free subscription rejected at server
(`CLOUD_SUBSCRIPTION_REQUIRED` on both device registration and manual push);
business-B-credentials-against-business-A rejected; cash pagination/cursor
convergence.

Updated `tests/Feature/SaleFoundationTest.php`: `quantity is stored as
integer` → `quantity supports decimal for laundry services` (2.5 round trip).
Updated `database/factories/ProductFactory.php` with semantic defaults.

Full suite: **305 passed, 0 failed** (855 assertions), including the 10 new
parity tests and all pre-existing sync/auth/foundation tests. Style gate:
`php vendor/bin/pint` clean on all 13 touched PHP files (one import-ordering
fix applied by Pint itself).

## Real HTTP E2E (against this backend)

A real `php artisan serve` instance on a dedicated `p37e2e.sqlite` database
(E2E-only env file, seed business/outlet/cloud subscription) served actual
HTTP to a Node client (`fetch`, no mocks): login → context → register device
A → initial 9-entity push → retry `duplicate:true` → pull device A (all 9
entities, semantics/payment/decimal verified) → register device B → bootstrap
pull restores 9/9 → offline mutation push → device-B pull converges (price
update + new cash entry) → stale push rejected with `409 SYNC_CONFLICT`.
Result **31/31 E2E checks passed**, reproducible across a full
reset-and-rerun (reset seeder wiped devices/tokens/sync rows; the dedicated
env file, seeders and sqlite file were deleted after the run — none are
committed).

Notable environment finding (documented, not a product bug): `php artisan
serve` does **not** inherit `DB_*` shell variables reliably on this machine
(`set FOO=...&& serve` showed MySQL `Unknown database` errors); the working
recipe is the app's `.env` file itself, i.e. a dedicated env file per
`--env`. The mobile QA report records the exact recipe.

## Known manual requirements

Multi-outlet concurrent-device conflict UX, Play/target distribution, and
physical printer checks remain manual and are tracked on the mobile report.
No production data was touched; migrations are additive and backfill-safe.

## Verdict

Contract parity complete on the server side: 9 entities, payment/decimal/
semantic coverage, exactly-once cash and stock semantics, unchanged
authorization gates, 305/305 green. **Ready for the mobile P37 client.**
