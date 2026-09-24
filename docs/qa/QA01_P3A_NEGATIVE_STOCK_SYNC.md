# QA01 — P3-A Negative Stock Sync Compatibility (Backend)

Branch: `feat/p3-negative-stock-sync-parity` · base: `main` (`f390437`)

P3-A aligns the server-side offline sync contract with the POS Mobile rule that
an offline sale may drive an item's stock negative (an oversold item). POS
Mobile is **not** modified by this change; only the Laravel sync contract,
regression tests, and this document change.

Nothing about the existing idempotency, row-lock concurrency, optimistic
versioning, or tenant isolation mechanisms is redesigned.

---

## 1. Contract audit (before → after)

| Location | Field | Before | After |
| --- | --- | --- | --- |
| `SyncPushRequest` | `changes.products.*.stock` | `numeric, min:0` | `numeric` |
| `SyncPushRequest` | `changes.stock_movements.*.stock_before` | `numeric, min:0` | `numeric` |
| `SyncPushRequest` | `changes.stock_movements.*.stock_after` | `numeric, min:0` | `numeric` |
| `SyncPushService::processStockMovements()` | `serverStockAfter < 0` | `409 STOCK_RECONCILIATION_REQUIRED` | accepted, persisted |

Rules that were **kept** unchanged (still reject invalid values):

* `changes.products.*.price` → `integer, min:0`
* `changes.products.*.cost` → `numeric, min:0`
* `changes.products.*.min_stock`, `min_quantity` → `numeric, min:0`
* `changes.sale_items.*.unit_price`, `line_total`, `cost_snapshot`, `line_cost` → `min:0`
* `changes.sales.*.subtotal`, `discount_amount`, `tax_amount`, `total_amount` → `min:0`
* `changes.expenses.*.amount`, `changes.cash_ledger.*.amount` → `min:0`
* `changes.stock_movements.*.quantity_change` → `required, numeric` (unsigned, is and stays
  the signed mutation source)

### Database constraint audit

`products.stock`, `stock_movements.stock_before`, `stock_movements.stock_after`
are all stored as **signed** `decimal(15,3)`:

```
`stock`        decimal(15,3) NOT NULL DEFAULT 0.000
`stock_before` decimal(15,3) NOT NULL DEFAULT 0.000
`stock_after`  decimal(15,3) NOT NULL DEFAULT 0.000
```

`SHOW CREATE TABLE` on the migrated schema shows **no `CHECK` constraint and no
`UNSIGNED` modifier** on any stock column. **No migration is required or was
created.** Negative stock was always storable; only the application-level
guard blocked it.

---

## 2. Contract changes

### 2.1 Validation — `app/Http/Requests/Api/SyncPushRequest.php`

`min:0` was dropped from exactly three rules (`products.*.stock`,
`stock_movements.*.stock_before`, `stock_movements.*.stock_after`). The `numeric`
type guard remains, so non-numeric payloads are still rejected with `422`. Every
other `min:0` boundary (price, HPP/cost, min stock, minimum quantity, money
amounts) is untouched.

### 2.2 Service — `app/Services/Sync/SyncPushService::processStockMovements()`

The `if ($serverStockAfter < 0) { throw new SyncStateRequiredException(...) }`
branch was removed. The mutation model itself is unchanged:

* The **product row is locked first** with a single `lockForUpdate()` read; the
  authoritative server stock is always the locked value.
* The accepted mutation is `quantity_change`; the authoritative new stock is
  `locked server stock + quantity_change`. A negative result is persisted as-is.
* Device `stock_before` / `stock_after` are stored as **historical evidence
  only** and never override the server stock.
* Movement idempotency remains keyed on `sync_id` (unique
  `(business_id, sync_id)`); `processProducts()` still drops `stock` from the
  update attribute set, so a device product snapshot can never overwrite
  movement-driven stock on update.

The `SyncStateRequiredException` type and its controller-level catch handler are
kept as the generic recoverable-state plumbing; `STOCK_RECONCILIATION_REQUIRED`
is simply no longer raised for negative stock.

### 2.3 Pull — `app/Services/Sync/SyncPullService.php`

No change needed. Product `stock` and movement `stock_before` / `stock_after`
are already serialised with `(float)` casts, so negative values round-trip to
the device unchanged.

---

## 3. Two-device sync behaviour

Two devices that sold the same item offline both hold a stale absolute stock
snapshot. Because only `quantity_change` is authoritative and deltas are
applied to the locked server row, both offline sales are applied exactly once:

| Step | Device A | Device B | Server stock |
| --- | --- | --- | --- |
| Both pull offline | stock 5 | stock 5 | 5 |
| A sells 3, B sells 4 offline | snapshot `5 → 2` | snapshot `5 → 1` | 5 |
| A reconnects, pushes `-3` | | | 2 |
| B reconnects, pushes `-4` | | | **-2** |

The stale `stock_after` values (2 and 1) are never trusted; the result is
`5 − 3 − 4 = −2`, not a lost update and not a fabricated value. Recovery works
the same way: a `+6` adjustment on a `−3` server stock yields `+3`.

---

## 4. Regression tests

`tests/Feature/Api/SyncIntegrityP38Test.php` — the obsolete
`test_negative_stock_delta_returns_explicit_reconciliation_state` (which asserted
the removed `409`) was replaced by focused cases:

| Scenario | Test |
| --- | --- |
| Stock 2, sell 5 → server stock **-3** | `test_oversold_stock_delta_persists_negative_server_stock` |
| Negative product snapshot accepted on create | `test_negative_stock_product_snapshot_is_accepted_on_create` |
| Adjustment `-3 → +6 → 3` | `test_stock_adjustment_from_negative_recovers_to_positive` |
| Two offline devices, stale snapshots, no lost update (`5 − 3 − 4 = −2`) | `test_two_offline_devices_oversell_with_stale_snapshots_without_lost_update` |
| Pull returns negative stock and movement history | `test_negative_stock_is_returned_by_pull_with_movement_history` |
| Non-numeric `stock` / `stock_before` / `quantity_change` rejected `422` | `test_non_numeric_stock_payload_is_rejected` |
| Retry (same `request_id` **and** same `sync_id`) applies once | `test_negative_stock_retry_with_same_sync_id_and_request_id_is_exactly_once` |
| Cross-business push denied (`403 BUSINESS_ACCESS_DENIED`) | `test_cross_business_stock_movement_is_denied` |
| Stale version / changed delta still `409 SYNC_CONFLICT` | `test_stock_movement_version_conflict_still_rejected` |

Retry coverage for the full entity set (sale + item + stock + cash) stays in
`test_duplicate_http_retry_is_exactly_once_across_all_entities`. Existing
conflict, lifecycle, cash, and tenant tests are unmodified.

## 5. MySQL/MariaDB concurrency gate

Dedicated throwaway database `pos_p38_concurrency_test` (MariaDB, InnoDB) — never
production, never the development database. A new case was added so real row
locks are exercised on the oversell path:

| Test | Result |
| --- | --- |
| `test_concurrent_stock_deltas_serialize_on_real_row_locks` | PASS |
| `test_concurrent_oversell_serializes_on_real_row_locks` (`2 − 5 − 5 = −8`) | PASS |
| `test_concurrent_master_edit_produces_exactly_one_winner` | PASS |
| `test_concurrent_cash_settlement_for_one_sale_stays_exactly_once` | PASS |

Command: `vendor/bin/phpunit -c phpunit.p38concurrency.xml --filter SyncConcurrencyMySqlTest`
→ **OK (4 tests, 24 assertions)**. Two real PHP processes call the real
`SyncPushService` against the same rows.

---

## 6. Test results

| Gate | Command | Result |
| --- | --- | --- |
| Full suite (SQLite `:memory:`) | `vendor/bin/phpunit` | **695 tests, 2069 assertions, 4 skipped** |
| `SyncIntegrityP38Test` | `php artisan test --filter=SyncIntegrityP38Test` | **18 passed, 99 assertions** |
| `SyncConcurrencyMySqlTest` (MySQL) | see §5 | **4 passed, 24 assertions** |
| Laravel Pint | `vendor/bin/pint --parallel --test` | **PASS (157 files)** |
| PHPStan (larastan, level 7) | `vendor/bin/phpstan analyse` | **0 errors (116 files)** |
| `composer ci:check` | `composer ci:check` | **PASS** (Pint + PHPStan + 691 passed / 4 skipped) |

The 4 skipped assertions are the MySQL-only concurrency tests, which report
`CONCURRENCY_DB_REQUIRED` under SQLite and are run separately in §5. No test was
left failing.

> **Environment note.** The canonical run uses the SQLite `:memory:` settings in
> `phpunit.xml`. On this workstation the shell exported `DB_CONNECTION=mysql` /
> `APP_ENV=local`, which `php artisan test` picks up *before* `phpunit.xml`
> (PHPUnit `<env>` does not override pre-set OS variables). The documented
> numbers above were therefore produced with the `phpunit.xml` environment
> applied explicitly, and the concurrency gate was run against its dedicated
> MySQL database only.

## 7. POS Mobile compatibility

* The push request shape is unchanged; POS Mobile keeps sending `quantity_change`
  plus `stock_before` / `stock_after`. No mobile change is required.
* Negative `products.*.stock`, `stock_movements.*.stock_before`, and
  `stock_movements.*.stock_after` are now accepted instead of `422`/`409`.
* `STOCK_RECONCILIATION_REQUIRED` is no longer returned for a negative
  resulting stock. Any mobile handler that switched on that code becomes a dead
  branch but is harmless; the `200` ack path is unchanged.
* Pull now delivers negative `stock` / `stock_before` / `stock_after` values, so
  devices can render oversold items truthfully.

## 8. Deployment requirements

* **No migration.** Stock columns are already signed; nothing to deploy at the
  schema level.
* Deploy the backend (validation + service) before or together with the P3-B
  mobile work. No config, queue, or cache change is required.
* Do **not** run migrations against production as part of this change (none
  exist for it).

## 9. Integration status — important

Backend acceptance of negative stock is **not** proof that the full offline
integration works in production. This document only proves the server contract
accepts and converges negative stock under the existing guarantees. End-to-end
behaviour — real devices pushing oversold sales over the network, conflict
recovery, and dashboard/report rendering of negative stock — must be validated
in **P3-B**. Until P3-B is completed and signed off, treat negative-stock
support as backend-ready only.

## 10. Blockers

* None on the backend. Production integration sign-off is gated on P3-B.
