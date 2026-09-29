# QA-RELEASE PHASE B2 — Real Backend Integration

Role: Senior Full-stack QA Automation Engineer
Date: 2026-09-29 (final corrections revision)
Backend repo: https://github.com/sawal123/pos_dashboard — PR #47 (`cmd/qa-b2-live-integration`)
Mobile repo: https://github.com/sawal123/pos-mobile — PR #45 **MERGED**

> Every PASS/FAIL below is produced by an executed command whose output is
> quoted. The Android emulator deep-sync journey remains **NOT EXECUTED** (§10).
> No mock result is presented as a live result.

---

## 1. Environment & commits

| Item | Value |
| --- | --- |
| Backend base | `55ab3d9` (origin/main, merge of PR #46) |
| Backend QA branch | `cmd/qa-b2-live-integration` (PR #47) |
| **Mobile final commit** | `faf3d63070f2068c8ca9b90f536f368fba23efb7` (PR #45 merge; supersedes `d9a4b81`) |
| Mobile final fix under test | `f550667 fix(sync): harden request reconciliation for uncertain acceptance` |
| Mobile QA worktree | `pos-mobile-qa-b2` (branch `cmd/qa-b2-live-integration` @ `faf3d63`) — separate from the Codex worktree |
| PHP / Node / npm | 8.4.16 / v22.18.0 / 10.9.3 |
| Database server | MariaDB 12.1.2 (`127.0.0.1:3306`) |
| Android tooling | adb 1.0.41, Java 24.0.2, AVD `POS_API_34`, AVDs `Medium_Phone_API_36.0`, `Pixel` |
| Backend stack | Laravel `^13.17`, Fortify `^1.37.2`, Livewire `^4.1`, Flux `^2.13.1`, Sanctum |

Safety: the Codex worktrees (`pos_dashboard-shift`, and the mobile repo's
`codex/int04-…` checkout) were not modified; no `reset --hard`, `git clean`,
auto-stash or auto-merge was used; the dev DB `pos_dashboard` and the Herd dev
server on `127.0.0.1:8000` were left untouched.

---

## 2. Database safety (new in this revision)

### 2.1 Fail-closed guard

Added `App\Support\QaDatabaseGuard` (+ `UnsafeQaDatabaseException`), driven by
`config/qa.php` (`QA_ALLOWED_DATABASES`, default `pos_qa_b2`).

* The guard validates the **actively connected** database — a live
  `select database()` on MySQL/MariaDB — not a configured name
  (`resolveActiveDatabase()`).
* It aborts when: the environment is `production`/`prod`; the name is empty;
  the name has a forbidden prefix (`pos_dashboard`, `prod`); or the name is not
  in the explicit allow-list.
* It is invoked as the **first statement** of `QaB2FixtureSeeder::run()` and the
  destructive `P37E2EResetSeeder::run()` (which deletes users, transactions and
  tokens), so it aborts **before any write**.

### 2.2 Regression tests

`tests/Feature/Qa/QaDatabaseGuardTest.php` — 10 tests, all passing:

```
PASS  Tests\Feature\Qa\QaDatabaseGuardTest   (10 passed, 11 assertions)
  ✓ rejects the development database
  ✓ rejects a development prefixed database
  ✓ rejects the production database name
  ✓ rejects an unknown database
  ✓ rejects the production environment even for an allowed database
  ✓ accepts an explicitly allow listed database
  ✓ the allow list can be extended via config
  ✓ it reads the name of the actually connected database   (sqlite :memory:)
  ✓ the fixture seeder refuses on a non qa database
  ✓ the e2e reset seeder refuses on a non qa database
```

### 2.3 Live fail-closed proof (real MySQL)

Pointed at the **real dev database** `pos_dashboard`, both seeders abort before
touching a row:

```
$env:DB_DATABASE='pos_dashboard'; php artisan db:seed --class=QaB2FixtureSeeder --force
  1  database\seeders\QaB2FixtureSeeder.php:36
     App\Support\QaDatabaseGuard::assertIsolated()          → exit 1
$env:DB_DATABASE='pos_dashboard'; php artisan db:seed --class=P37E2EResetSeeder --force
  1  database\seeders\P37E2EResetSeeder.php:16
     App\Support\QaDatabaseGuard::assertIsolated()          → exit 1
```

The same commands succeed on `pos_qa_b2` (allow-listed).

---

## 3. Test matrix (tiered, re-executed on the final mobile commit)

Legend: ✅ pass · ⏭ skipped by design · ❌ fail (finding) · ⛔ not executed

| # | Tier | Suite | Result |
| --- | --- | --- | --- |
| T1 | UNIT/MOCK (backend) | `php artisan test` | ✅ **1147 passed, 6 skipped, 0 failed** (4242 assertions) |
| T2 | UNIT/MOCK (backend) | guard regression `QaDatabaseGuardTest` | ✅ 10 passed |
| T3 | UNIT/MOCK (backend) | `vendor/bin/phpunit -c phpunit.p38concurrency.xml` (real MariaDB row locks) | ✅ 24 passed |
| T4 | UNIT/MOCK (backend) | `pint --test` / `phpstan analyse` | ✅ 253 files clean / no errors |
| T5 | UNIT/MOCK (mobile) | `npx vitest run` | ✅ **1288 passed, 5 skipped** (3 E2E files skip without env) |
| T6 | BUILD (mobile) | `npm run build` | ✅ built |
| T7 | LIVE API (real mobile services) | `p37-app-service-e2e.spec.js` → live Laravel | ✅ 1 passed |
| T8 | LIVE API (real mobile services) | `p38-two-device-e2e.spec.js` → live Laravel | ✅ 2 passed |
| T9 | LIVE API (real mobile services) | **INT-04 live harness** (`b2-live-int04-reconciliation.spec.js`) | ✅ 1 passed + ⏭ 1 expected-fail tripwire (F5) |
| T10 | ANDROID EMULATOR | install + launch + render + Cloud Login screen | ⛔ NOT EXECUTED (see §10) |
| T11 | MANUAL DEVICE | physical device journey | ⛔ not executed |

---

## 4. Reproduction

```powershell
# Backend — OS-level APP_ENV/DB_* override must be pinned PER PROCESS
$env:APP_ENV='qa'
$env:DB_CONNECTION='mysql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='3306'
$env:DB_DATABASE='pos_qa_b2'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''
$env:QA_ALLOWED_DATABASES='pos_qa_b2'

php artisan migrate --force
php artisan db:seed --class=P37E2EResetSeeder --force   # guarded, destructive
php artisan db:seed --class=QaB2FixtureSeeder --force   # guarded fixtures
php artisan serve --host=0.0.0.0 --port=18010

php artisan test
vendor\bin\phpunit -c phpunit.p38concurrency.xml
composer lint:check ; composer types:check
```

```bash
# Mobile (from pos-mobile-qa-b2 @ faf3d63) — NOTE: global npm omit=dev
npm ci --include=dev
npx vitest run
npm run build

# Live API tier (fresh DB before each spec):
P37_E2E_BASE_URL=http://127.0.0.1:18010 P37_E2E_EMAIL=e2e-owner@example.com P37_E2E_PASSWORD=password \
  npx vitest run src/__tests__/p37-app-service-e2e.spec.js
P38_E2E_BASE_URL=http://127.0.0.1:18010 P38_E2E_EMAIL=e2e-owner@example.com P38_E2E_PASSWORD=password \
  npx vitest run src/__tests__/p38-two-device-e2e.spec.js

# Live INT-04 (real reconciliation):
B2_E2E_BASE_URL=http://127.0.0.1:18010 B2_E2E_EMAIL=member-a@example.com B2_E2E_PASSWORD=password B2_E2E_DB=pos_qa_b2 \
  npx vitest run src/__tests__/b2-live-int04-reconciliation.spec.js
```

The live INT-04 harness is committed as a reference copy at
`docs/qa/harness/b2-live-int04-reconciliation.spec.js` (it runs from the mobile
worktree).

---

## 5. LIVE INT-04 reconciliation evidence (task item 3)

Harness: real `syncPushService` + `syncReconciliationService` + durable outbox +
identity registry + local operation journal + Pinia, against the real Laravel
server. The **only** "cut" is the client discarding an already-received server
response — the exact lost-response situation.

### 5.1 Scenario A — lost response, then member → cashier (PASS)

```
✓ recovers a lost response after a member->cashier role change without duplication
```

Sequence and assertions (all passed):

1. Real login + `GET /api/mobile/context` → role `member`, device
   `QA-OWNER-A-DEV-1`.
2. Push a product (normal) → committed, outbox drained.
3. Commit an offline cash sale, then push whose response is discarded
   (`acceptance: 'unknown'`, envelope retained, `requestId` kept).
4. Server actually committed → `GET /api/sync/requests/{id}/status` = `committed`.
5. Role changed **member → cashier** (server-side); same token re-reads
   `role: cashier`, `push_mode: cashier_safe`.
6. `reconcile()` uses the real status endpoint → `SYNC_RECONCILIATION_COMMITTED`,
   `acceptance: accepted`, `remaining: 0`; CAS cleanup removed the outbox rows and
   cleared the envelope.
7. No duplication.

### 5.2 Scenario B — not_found while in-flight, then committed (PASS)

* A push that never reaches the server (dropped before send) leaves a retained
  envelope; `reconcile()` → real status → `not_found` → **nothing deleted**,
  same `request_id` kept (`resolved:false`, `interventionRequired:true`).
* The same request then reaches the server with the **same** `request_id` → 200;
  `reconcile()` → `committed` → CAS cleanup, outbox drained, customer stored
  exactly once.

### 5.3 Database assertions (business A of `pos_qa_b2`)

```
sync_requests          | 3   (product push, sale push, customer push — each once)
sales                  | 1
sale_items             | 1
cash_ledger            | 1
stock_movements        | 1     quantity_change -1.000
stock_movements_linked | 0     (sale_sync_id IS NOT NULL)  ← see F5
customers              | 1
products               | 2
```

Exactly-once holds: recovery after the role change produced **no** extra
`sync_requests`, `sales`, `cash_ledger` or `stock_movements` rows.

---

## 6. Findings

### F5 — MOBILE, HIGH (release blocker): cashier retail sales cannot sync their stock movement

**Live evidence (no mock):** a cashier completing a normal retail sale with the
production commit path (`localOperations.commitRetailSale`) is rejected by the
server:

```
CASHIER_PUSH_RESULT {"ok":false,"code":"SYNC_OPERATION_NOT_ALLOWED",
  "error":{"status":403,"data":{"code":"SYNC_OPERATION_NOT_ALLOWED",
  "violations":[{"entity":"stock_movements","operation":"upsert",
                 "reason":"missing_sale_relation"}]}}}
```

**Root cause (mobile):**
* `src/services/database/localOperationService.js` —
  `applySaleStockIdempotently()` calls
  `productStore.adjustStock(productId, { quantityChange, type:'sale', referenceId: transaction.id, … })`
  **without `transactionId`**.
* `src/services/sync/contractMapper.js` (~L1110–1123) links a stock movement to
  its sale **only** via `payload.transactionId`; without it, `sale_sync_id` is
  omitted.
* The cashier-safe server contract (`SyncPushService::processStockMovements` →
  `authorizeStockMovements`) requires a sale relation → `missing_sale_relation`.

**Impact:** for the full (owner/member) path the movement is stored with
`sale_sync_id = NULL` (traceability/audit gap, §5.3); for the **cashier** path
the whole movement is rejected, so an offline cashier sale with physical stock
cannot be fully synced (the envelope/outbox is retained — no data loss — but
sync is blocked). Compare `productStore.recordSaleStock()` (L477–492) which
**does** pass `transactionId`.

**Reproduction:** run the committed live harness (§4) — the `it.fails` tripwire
`B2-2b` fails exactly as above; or query
`SELECT COUNT(*) FROM stock_movements WHERE business_id=<A> AND sale_sync_id IS NOT NULL`
→ `0`.

**Suggested fix (mobile, for Codex — not applied here):**
`applySaleStockIdempotently` must pass `transactionId: transaction.id` to
`adjustStock` (mirroring `recordSaleStock`), so `sale_sync_id` is attached.

### F1 — Push authorization precedes idempotency (LOW / INFORMATIONAL, backend)

A committed request replayed after a role downgrade to cashier returns **403
`SYNC_OPERATION_NOT_ALLOWED`** rather than `duplicate=true`, because violations
are evaluated before dedupe. Server never duplicates. The INT-03 status endpoint
(uses `sync.pull`, held by every role) resolves it as `committed` — the
documented recovery path followed by scenario A. Recommendation: pin this
ordering with a contract test so it is never "fixed" into a duplicate response.

### F2 — Live E2E specs are not self-isolating (MEDIUM, test infra)

`p38-two-device-e2e.spec.js` assumes a fresh business; running it on a DB that
already holds history fails (`expected [ … ] to have a length of 1 but got 3`).
`P37E2EResetSeeder` + `P37E2ESeeder` first makes it pass (`2 passed`). Have the
E2E runner reset+seed before each spec.

### F3 — Runtime DB guard outside PHPUnit (resolved for seeders)

Previously `artisan migrate/db:seed` had no guard. This revision adds the
fail-closed `QaDatabaseGuard` to the QA seeders/reset (§2). Remaining nicety: a
runtime guard for `artisan serve`/`migrate` and documentation of the OS-env +
port-collision trap.

---

## 7. Release blockers

| ID | Blocker | Status |
| --- | --- | --- |
| B-3 | **F5** — cashier retail sale stock movement rejected live (403 `missing_sale_relation`) | **OPEN (mobile fix required)** |
| B-1 | Android emulator end-to-end offline→online→recovery journey not executed | OPEN — pre-release verification |
| B-2 | Physical-device (MANUAL) verification not executed | OPEN |

No backend (BLOCKER/HIGH) defect found in the executed scope.

---

## 8. Fix recommendations

1. **Mobile (B-3 / F5):** pass `transactionId: transaction.id` in
   `localOperationService.applySaleStockIdempotently` → `adjustStock` so the
   movement carries `sale_sync_id`; un-skip the `it.fails` tripwire.
2. **Mobile (F2):** reset+seed the dedicated E2E DB before each live spec.
3. **Backend (F1):** add a contract test pinning the 403-before-dedupe ordering
   and the status-endpoint recovery.
4. **Backend (F3):** extend the fail-closed guard to non-testing `serve`/`migrate`.

---

## 9. Database integrity summary

* Exactly-once by `request_id` and `sync_id` (no duplicate `sync_requests`,
  `sales`, `cash_ledger`, `stock_movements` across retries/reconciliation).
* Single stock deduction (`quantity_change -1.000`), historical HPP preserved.
* Tenant rows isolated per business; sequence counters per business.
* QA seeders/reset fail closed on any non-QA database (§2).

---

## 10. Android emulator result (honest disposition — unchanged)

**Executed (real emulator, AVD `POS_API_34`):** `npm run build` +
`npx cap sync android` + `gradlew assembleDebug` → **BUILD SUCCESSFUL**
(`app-debug.apk`), `adb install` → Success, app launched and rendered the local
dashboard, no crash, and the **Cloud Login** screen was reached.

**NOT EXECUTED:** the scripted interactive offline→online→recovery journey
(login → offline sale → airplane-off → force-stop → relaunch → reconnect →
sync/recovery with screenshots). Automated driving via `adb shell input tap`
was unreliable (WebView layout shifts when the soft keyboard opens). Reported as
NOT EXECUTED — it is **not** claimed as a pass. The equivalent behaviour was
exercised at the LIVE API tier with the real mobile services (§5).

---

## 11. Recommendation & next phase

* Backend contract, tenant isolation, role re-evaluation, exactly-once and the
  INT-04 lost-response recovery are **verified live**.
* Close **B-3/F5** (mobile) and **B-1/B-2** before release.
* Keep the QA-seeder guard and the live harness as the regression base.

Not merged, not deployed.
