# P38 — Multi-Device Data Integrity, Conflict & Recovery Hardening (Backend)

Branch: `feat/p38-sync-integrity-recovery` · base: `main` (P37 merged commit `2d2bbfe3abc9d540bb438d363bcbd236762e8444`)

P38 hardens the existing cloud sync contract. It does **not** redesign the POS
and does **not** change the P37 Free/Subscriber model.

---

## 1. Stock concurrency policy — server delta authority

`app/Services/Sync/SyncPushService::processStockMovements()`

* The **product row is locked first**, as a single `lockForUpdate()` read. No
  snapshot read of the product may precede the lock (that trips MariaDB error
  1020, "record has changed since last read").
* The accepted mutation is `quantity_change`. The authoritative server stock is
  `locked server stock + quantity_change`.
* Device `stock_before` / `stock_after` are stored as **historical evidence
  only** and are never used as the current-stock authority.
* Movement dedupe is by `sync_id` (`stock_movements` unique
  `(business_id, sync_id)`). A retry of the same `sync_id` never re-applies the
  delta; the same `request_id` is already deduped by `sync_requests`.
* `processProducts()` no longer lets a client snapshot overwrite current stock
  on **update** (`stock` is dropped from the update attribute set). Initial
  stock is still accepted on product **create**.
* Deadlocks / lock-wait timeouts / record-changed races are classified as
  `SYNC_RETRYABLE_CONFLICT` (409, `retryable: true`); nothing is applied and the
  device retries its durable outbox.

**Required regression** — `tests/Feature/Api/SyncIntegrityP38Test.php`
`test_concurrent_stock_deltas_converge_without_lost_update`: stock 10, movement A
−3, movement B −4 → server stock **3** with exactly **2** movements (never 7,
never 6).

## 2. Stock reconciliation policy

> **Superseded by P3-A** (`docs/qa/QA01_P3A_NEGATIVE_STOCK_SYNC.md`): negative
> resulting stock is now a valid offline outcome and is persisted instead of
> raising `STOCK_RECONCILIATION_REQUIRED`. The rest of this section is kept as
> the historical P38 record.

`app/Services/Sync/SyncStateRequiredException.php`

If a concurrent delta would drive the locked server stock below zero, the
request is rejected atomically with an **explicit, recoverable** state:

```
HTTP 409  code: STOCK_RECONCILIATION_REQUIRED
state: STOCK_RECONCILIATION_REQUIRED
details: { product_sync_id, quantity_change, server_stock_before,
           server_stock_after, device_stock_before, device_stock_after,
           reason: NEGATIVE_STOCK_NOT_ALLOWED }
```

No silent server-wins, no silent client-wins, no fabricated `stock_after`, no
historical sale deletion, no movement deletion. The device keeps its durable
outbox payload and surfaces an action-required state.

Policy source is the existing POS domain rule (retail checkout and stock
adjustment both refuse to go below zero); no new policy was invented. The
backend still stores `stock` as a signed `decimal(15,3)` — nothing about the
storage model changed.

## 3. Cash exactly-once policy

`SyncPushService::processCashLedger()`

* Concurrent settlements of one logical order are serialized on the **sale row**
  (`SELECT ... FOR UPDATE` on `sales` by `sale_sync_id`) before any
  `cash_ledger` row lock is taken.
* A cash entry carrying `sale_sync_id` is deduped by the **logical
  sale/payment identity**: `(business_id, sale_sync_id, type)`. Note, reference
  string and timestamp are device-local and are never part of the identity.
* Identical settlement → idempotent equivalent, no second row.
* Different amount / direction / method → deterministic `409 SYNC_CONFLICT`.

## 4. Laundry lifecycle conflict policy

`SyncPushService::processSales()` + `lifecycleRank()`

* Lifecycle rank: `Masuk(0) < Diproses(1) < Siap Diambil(2) < Selesai(3)`.
* An incoming `order_status` ranked **below** the server value is rejected with
  `409 SYNC_CONFLICT` — `Selesai → Siap Diambil`, `Siap Diambil → Diproses` and
  `Diproses → Masuk` are impossible from a stale device.
* Optimistic concurrency (`base_sync_version`) remains the primary guard.

## 5. Master conflict behaviour

Concurrent edits of products / categories / customers / expenses from the same
base version: the first commit wins, the loser receives a deterministic
`409 SYNC_CONFLICT` with the server version. Never last-write-wins.

## 6. Safe auto-resolution

`SyncPushService::attributesMatch()` runs **before** version validation. A
mutation that is already fully reflected on the server (identical field values)
is acknowledged as success without a conflict and without a further write — no
version churn. Genuinely different user edits always stay unresolved. Applied to
all entities plus the tombstone path (already-tombstoned rows auto-resolve).

## 7. Failure matrix (server side)

| Scenario | Result |
| --- | --- |
| Lost response, same `request_id` retried | `duplicate: true`, one row per entity |
| Same `sync_id`, new `request_id` | idempotent, delta applied once |
| Stale `base_sync_version` | `409 SYNC_CONFLICT` with `server_sync_version` |
| Equivalent replay with stale version | `200`, no conflict, no write |
| Negative concurrent delta | `200`; negative stock persisted (P3-A, supersedes `409 STOCK_RECONCILIATION_REQUIRED`) |
| Deadlock / lock-wait / record-changed | `409 SYNC_RETRYABLE_CONFLICT` |
| Cross-business / cross-outlet push | existing P37 guards (403 / 409) |

---

## 8. MySQL/MariaDB concurrency gate

Dedicated database: `pos_p38_concurrency_test` (MariaDB 12.1.2, InnoDB). Never
production, never the development database.

Config: `phpunit.p38concurrency.xml` · Tests: `tests/Feature/Api/SyncConcurrencyMySqlTest.php`
Two real PHP processes (`Concurrency::run`, process driver) call the real
`SyncPushService` against the same rows.

| RUN | Command | Result |
| --- | --- | --- |
| #1 | `vendor/bin/phpunit -c phpunit.p38concurrency.xml --filter SyncConcurrencyMySqlTest` | **OK (3 tests, 18 assertions)** |
| reset | `DROP DATABASE` + `CREATE DATABASE` (0 tables before run) | clean |
| #2 | same command on the clean database | **OK (3 tests, 18 assertions)** |

Covered: concurrent stock deltas (`10 − 3 − 4 = 3`, 2 movements), concurrent
master edit (exactly one `200` + one `409`, single row), concurrent cash
settlement of one sale (exactly one `cash_ledger` row).

The suite asserts the driver is `mysql`/`mariadb` and skips elsewhere — SQLite is
never accepted as concurrency evidence. Under the default `phpunit.xml` (SQLite)
these three tests report as skipped, which is why the default run shows
`322 passed, 3 skipped`.

---

## 9. Test counts

| Gate | Result |
| --- | --- |
| `php artisan test` (full) | **322 passed, 3 skipped**, 959 assertions |
| `tests/Feature/Api/SyncIntegrityP38Test.php` | 10 passed, 54 assertions |
| `tests/Feature/Api/SyncConcurrencyMySqlTest.php` (MySQL) | 3 passed, 18 assertions |
| Existing P37 sync suites (`SyncParityTest`, `SyncPushTest`, `SyncPullTest`) | 41 passed |
| PHPStan (level 7, larastan) | **0 errors** |
| Pint (`--test`) | PASS (110 files) |
| `git diff --check` | clean |

Two P37 fixtures were corrected to be delta-consistent (they encoded the old
client-`stock_after` authority): `test_stock_movement_retry_does_not_apply_deduction_twice`
and `test_stock_movement_updates_current_server_product_stock_once` now seed the
product with the stock the movement assumes. Behaviour assertions are unchanged
apart from the authority fix.

---

## 10. Real two-device E2E (mobile-driven)

Executed from the mobile repository against a real `php artisan serve` backend
on the dedicated database `pos_p38_e2e_test`
(`src/__tests__/p38-two-device-e2e.spec.js`). See the mobile QA doc for the full
result; the backend side observed:

* A offline cash sale qty 3, B offline non-cash sale qty 4, reconnect and
  near-concurrent push → server stock **3**, 2 sales, 2 stock movements,
  1 cash entry, no duplicates.
* Stale laundry lifecycle push (`base_sync_version 1`, `order_status Masuk`
  while the server is `Diproses`) → `409 SYNC_CONFLICT`, server not regressed.

## 11. Blockers

* `PRODUCTION_API_URL_REQUIRED` — still a **P39** blocker, not P38.
* No P38 blockers. Mobile PR depends on this backend PR only for the
  `STOCK_RECONCILIATION_REQUIRED` / `SYNC_RETRYABLE_CONFLICT` codes it surfaces.
