# QA-RELEASE PHASE B2 — Real Backend Integration

Role: Senior Full-stack QA Automation Engineer
Date: 2026-09-29
Backend repo: https://github.com/sawal123/pos_dashboard
Mobile repo: https://github.com/sawal123/pos-mobile
Mobile PR under test: #45 (`refs/pull/45/head`)

> This report is written from **executed** evidence only. Every PASS below was
> produced by a command whose output is quoted in the evidence sections. The
> Android emulator deep-sync journey is explicitly marked **NOT EXECUTED** —
> see §9. No emulator result is inferred from mocks or adjacent artifacts.

---

## 1. Environment & commits

| Item | Value |
| --- | --- |
| Backend base commit (origin/main) | `55ab3d90aac0cc5a68df42a0af603a867bccb6a3` — Merge PR #46 (feat/ui-login) |
| Backend QA branch | `cmd/qa-b2-live-integration` (branched from `origin/main`) |
| Mobile commit under test | `d9a4b81dc7b89f32618923986bad19b0cdbfb427` — `feat(sync): reconcile retained push envelopes against request status` (PR #45 head) |
| Mobile QA worktree | `D:\PROJECT WEB\POS OFFLINE\pos-mobile-qa-b2` (branch `cmd/qa-b2-live-integration`) — **separate** from the Codex worktree |
| PHP | 8.4.16 (NTS, VC22) |
| Node / npm | v22.18.0 / 10.9.3 |
| Database server | MariaDB 12.1.2 (host `127.0.0.1:3306`) |
| Android tooling | adb 1.0.41, Java 24.0.2, AVD `POS_API_34` (Android 14) |
| Backend stack | Laravel `^13.17`, Fortify `^1.37.2`, Livewire `^4.1`, Flux `^2.13.1`, Laravel Sanctum |

### Worktree / repository safety

* Backend worktree `pos_dashboard` and the **Codex worktree**
  `pos_dashboard-shift` (`codex/int01-cashier-sync-api`) are untouched.
* The mobile repo's existing worktree (branch
  `codex/int04-mobile-request-reconciliation`) is the Codex worktree and was
  **not modified**; testing used the new, separate worktree above.
* No `git reset --hard`, `git clean`, auto-stash or auto-merge was used.
* The user's dev database `pos_dashboard` and the pre-existing Herd dev server
  on `127.0.0.1:8000` were left running and untouched.

### ⚠ Environment hazard discovered (MEDIUM — operational)

This machine exports the QA-ENV-01 variables **at OS level**, so Laravel's
immutable Dotenv and `$_SERVER` make them win over `.env` **and** over
`php artisan --env=qa`:

```
APP_ENV=local  DB_CONNECTION=mysql  DB_DATABASE=pos_dashboard  DB_HOST=127.0.0.1  ...
```

Consequences seen during this run:
* `php artisan migrate --env=qa` silently resolved to `pos_dashboard`
  ("Nothing to migrate" instead of creating tables).
* Two servers were bound to port 8000 — `127.0.0.1:8000` (a pre-existing Herd
  dev server) and `0.0.0.0:8000` (the QA server). Linux/Windows route
  `127.0.0.1` to the more specific `127.0.0.1` listener, so the first QA
  requests were served by the **dev** server and returned `401`.

Mitigation used for every QA command: a per-process override
(`scratch/qa-env.ps1`) that pins `APP_ENV=qa` + all `DB_*` values, followed by
a **fail-closed** assertion that refuses to continue unless the resolved
database is exactly `pos_qa_b2`. All QA work then ran on `127.0.0.1:18010`.

---

## 2. Isolated test database & fixtures

| Item | Value |
| --- | --- |
| QA database | `pos_qa_b2` (created new; utf8mb4 / utf8mb4_unicode_ci) |
| Guard | resolves `config('database.connections.mysql.database')`; aborts unless == `pos_qa_b2`; rejects `pos_dashboard*`, `production`, `prod` |
| Migrations | 30 migrations, all applied (`migrate --force`) |
| Dedicated concurrency DB | `pos_p38_concurrency_test` (pre-existing, QA-ENV-01 profile `p38_mysql`) |

Synthetic fixtures — `database/seeders/QaB2FixtureSeeder.php` (new, idempotent):

| Entity | Value |
| --- | --- |
| Owner | `owner-a@example.com` (password `password`, verified) |
| Member | `member-a@example.com` |
| Cashier | `cashier-a@example.com` |
| 2nd tenant owner | `owner-b@example.com` |
| Business A (cloud, type `cafe`) | outlets `A1`, `A2`; devices `QA-OWNER-A-DEV-1` (owner) + `QA-CASHIER-A-DEV-1` (pre-registered by owner) |
| Business B (cloud, type `grosir`) | outlet `B1`; device `QA-OWNER-B-DEV-1` |
| Catalog | category `QA Kategori`, product `QA-PROD-1` (stock 100) |
| E2E business (cloud) | `e2e-owner@example.com` + `OUT-1` — consumed by the automated P37/P38 live specs |

---

## 3. Test matrix (tiered)

Legend: ✅ pass · ⏭ skipped (by design) · ⛔ not executed

| # | Tier | Suite / probe | Result |
| --- | --- | --- | --- |
| T1 | UNIT/MOCK (backend) | `php artisan test` | ✅ 1137 passed, 6 skipped, 0 failed (4231 assertions) |
| T2 | UNIT/MOCK (backend) | `vendor/bin/phpunit -c phpunit.p38concurrency.xml` (real MariaDB row locks) | ✅ 24 passed (135 assertions) |
| T3 | UNIT/MOCK (backend) | `pint --test` / `phpstan analyse` | ✅ 249 files clean / no errors |
| T4 | UNIT/MOCK (mobile) | `npx vitest run` | ✅ 1277 passed, 3 skipped (2 E2E files skip without env) |
| T5 | BUILD (mobile) | `npm run build` | ✅ built (index 576 kB) |
| T6 | LIVE API | live contract probe (`scratch/api-contract-check.mjs`) | ✅ 49/49 |
| T7 | LIVE API | live role/device/subscription edges (`scratch/b2-edge-cases.mjs`) | ✅ 15/15 |
| T8 | LIVE API (real mobile services) | `p37-app-service-e2e.spec.js` → live Laravel | ✅ 1 passed |
| T9 | LIVE API (real mobile services) | `p38-two-device-e2e.spec.js` → live Laravel | ✅ 2 passed (on fresh DB) |
| T10 | ANDROID EMULATOR | install + launch + render + Cloud Login screen | ⛔ partial (see §9) |
| T11 | MANUAL DEVICE | physical device journey | ⛔ not executed (no device attached) |

Module × role × tenant coverage exercised live (T6–T9):

| Endpoint | Owner | Member | Cashier | Cross-tenant |
| --- | --- | --- | --- | --- |
| `POST /api/auth/login` | ✅ 200 / 401 wrong pw | ✅ 200 | ✅ 200 | — |
| `GET /api/mobile/context` | ✅ role/cloud/outlets/device_context | ✅ push_mode full | ✅ push_mode `cashier_safe` | ✅ only own business |
| `POST /api/mobile/devices` | ✅ 200, idempotent | ✅ allowed | ✅ 403 `MOBILE_ROLE_NOT_SUPPORTED` | ✅ 403 `BUSINESS_ACCESS_DENIED` |
| `POST /api/sync/push` (products/categories) | ✅ 200 | ✅ 200 | ✅ 403 `cashier_entity_not_allowed` | ✅ 403 |
| `POST /api/sync/push` (sales/items/movements/cash) | ✅ | ✅ | ✅ 200 (allowed subset) | ✅ |
| `POST /api/sync/push` cash manual | — | — | ✅ 403 `manual_cash_not_allowed` | — |
| `POST /api/sync/push` stale version | ✅ 409 `SYNC_CONFLICT` | ✅ 409 | ✅ 409 | — |
| `POST /api/sync/push` inaktif device | ✅ 403 `DEVICE_INACTIVE` | — | — | — |
| `POST /api/sync/push` no cloud | ✅ 403 `CLOUD_SUBSCRIPTION_REQUIRED` | — | — | — |
| `GET /api/sync/pull` | ✅ `records/next_cursor/server_sequence/has_more` | ✅ | ✅ | ✅ |
| `GET /api/sync/requests/{id}/status` | ✅ committed | ✅ | ✅ committed (after role change) | ✅ 403; wrong device → `not_found` |
| `DELETE /api/auth/logout` | ✅ 200, revoked token → 401 | ✅ | ✅ | — |

---

## 4. Reproduction

```powershell
# Backend (from D:\PROJECT WEB\POS OFFLINE\pos_dashboard)
# 1. OS-level APP_ENV/DB_* override — pin them PER PROCESS before every artisan
#    command, otherwise Dotenv/`$_SERVER` resolve them to pos_dashboard.
$env:APP_ENV='qa'
$env:DB_CONNECTION='mysql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='3306'
$env:DB_DATABASE='pos_qa_b2'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''

# Fail-closed check: abort unless the resolved DB is exactly pos_qa_b2
php -r "require 'vendor/autoload.php';`$a=require 'bootstrap/app.php';`$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();`$d=config('database.connections.mysql.database'); echo `$d.PHP_EOL; exit(`$d==='pos_qa_b2'?0:1);"

php artisan migrate --force
php artisan db:seed --class=QaB2FixtureSeeder --force

# 2. Serve the QA backend (port 18010 to avoid any dev server on 8000)
php artisan serve --host=0.0.0.0 --port=18010

# 3. Quality gates
php artisan test
vendor\bin\phpunit -c phpunit.p38concurrency.xml
composer lint:check ; composer types:check
```

The backend live-API contract (T6) and role/device/subscription edge cases (T7)
were driven by two local Node harnesses (kept out of the commit) that issue raw
`fetch` calls to the server above and, for the edge cases, apply reversible
`UPDATE`s to `pos_qa_b2` via the MariaDB client. The mobile live-API tier (T8/T9)
is reproducible with the committed specs shown below.

```bash
# Mobile (from D:\PROJECT WEB\POS OFFLINE\pos-mobile-qa-b2)
npm ci --include=dev          # NOTE: global npm omit=dev skips devDeps otherwise
npx vitest run                # unit/mock tier
npm run build

# Live API tier — reset+seed the E2E fixture FIRST, then run a spec that uses
# the REAL mobile services against the live backend.
# (backend: php artisan db:seed --class=P37E2EResetSeeder --force
#            php artisan db:seed --class=P37E2ESeeder     --force)
P37_E2E_BASE_URL=http://127.0.0.1:18010 P37_E2E_EMAIL=e2e-owner@example.com P37_E2E_PASSWORD=password \
  npx vitest run src/__tests__/p37-app-service-e2e.spec.js
P38_E2E_BASE_URL=http://127.0.0.1:18010 P38_E2E_EMAIL=e2e-owner@example.com P38_E2E_PASSWORD=password \
  npx vitest run src/__tests__/p38-two-device-e2e.spec.js
```

Expected vs actual highlights (all Actual == Expected unless noted):

| Check | Expected | Actual |
| --- | --- | --- |
| owner login | 200 + `data.token` | 200, `token_type=Bearer` |
| wrong password | 401 | 401 `Invalid credentials.` |
| context role/business_type | `owner` / `cafe` | `owner` / `cafe` |
| cashier capabilities | push_mode `cashier_safe`, denied `[categories,products,expenses,deletions]` | identical |
| device register by cashier | 403 `MOBILE_ROLE_NOT_SUPPORTED` | identical |
| replay same `request_id` | 200 `duplicate=true`, no new row | identical (1 row) |
| stale `base_sync_version` | 409 `SYNC_CONFLICT` | identical |
| status unknown id | `not_found` | `not_found` |
| status other device | `not_found` (device-scoped) | `not_found` |
| status foreign business | 403 (no existence leak) | 403 `BUSINESS_ACCESS_DENIED` |

---

## 5. Database integrity evidence (Phase 7)

After the live pushes, `pos_qa_b2` (business A) contained:

```
sync_requests : 2 rows  ->  request 147863c1… (device 1) and 682147ad… (device 2)
products      : QA Live Product  stock = 18.000  (20 seeded - 2 sold)  sync_version 2
sales         : QA-TRX-211d04  total 24000  payment_status paid  payment_method cash
sale_items    : quantity 2.000  unit_price 12000  line_total 24000  cost_snapshot 5000.00
stock_movements: sale  quantity_change -2.000  sale_sync_id 211d040d…
cash_ledger   : in  amount 24000  sale_sync_id 211d040d…
sync_counters : business 1 -> 9 ; business 2 -> 0 ; business 3 -> 0   (tenant isolation)

exactly-once by natural key:
  owner_request_rows=1  cashier_request_rows=1  movement_rows=1  cash_rows=1  sale_item_rows=1
```

`sync_sequence` distribution (business 1): categories `1,3` · products `2,9` ·
sales `5` · sale_items `6` · cash_ledger `7` · stock_movements `8` · counter `9`.
The product carries a newer sequence (9) because the cashier sale re-versioned
it when the stock moved — i.e. the stock effect was applied exactly once and
the sequence is contiguous with no duplicate rows.

**Conclusion:** no duplication, no double stock deduction, no double cash
entry, historical HPP preserved (`cost_snapshot=5000`), and tenant rows are
fully isolated.

---

## 6. Findings

### F1 — Push authorization is evaluated before idempotency (LOW / INFORMATIONAL)

**Reproduction:** member pushes products with `request_id R` → 200 (committed).
Role is then changed to `cashier`. Replaying the *same* envelope `R` returns
**HTTP 403 `SYNC_OPERATION_NOT_ALLOWED`**, not `duplicate=true`.

Observed:

```
replay of R after role change -> HTTP 403 code=SYNC_OPERATION_NOT_ALLOWED
status endpoint as cashier    -> 200 body=committed
products with sync_id R       -> 1 row   (server did NOT duplicate)
```

**Impact:** none on data integrity (proved by the row count and status). A
client that relied on push idempotency alone would mis-handle this. The
INT-04 design already answers it: `GET /api/sync/requests/{id}/status` uses
`sync.pull` (held by every role) so a role change can never hide a committed
request behind a 403, and the mobile client reconciles via status, keeps the
envelope, and never rebuilds it. **Safe client behaviour:** on `403` with
unknown acceptance, call the status endpoint; treat `committed` as accepted and
clean the outbox by CAS; treat `not_found` as inconclusive and keep the
envelope. Recommend adding an explicit contract note/test asserting this
ordering so it is never "fixed" into a duplicate-response regression.

### F2 — Live E2E specs are not self-isolating (MEDIUM — test infrastructure)

**Reproduction:** against a database that already contains history (e.g. after
running the P37 spec), `p38-two-device-e2e.spec.js` test 1 fails:

```
AssertionError: expected [ … ] to have a length of 1 but got 3
  at src/__tests__/p38-two-device-e2e.spec.js:343
```

Cause: the specs assume a freshly seeded business; device A re-pulls and
inherits earlier transactions. Re-running with
`P37E2EResetSeeder` + `P37E2ESeeder` first makes both P38 tests pass
(`2 passed`). **Impact:** false failures / flaky QA signal, not a product bug.
**Recommendation:** have the E2E runner reset+seed before each spec, or make
the specs assert deltas rather than absolute lengths.

### F3 — No fail-closed runtime DB guard outside PHPUnit (MEDIUM — operational)

`TestDatabaseGuard` protects the PHPUnit suites only. `php artisan
migrate`/`db:seed`/`serve` have **no** guard, so on this machine they silently
targeted the dev database until the OS env was overridden by hand (see §1).
**Recommendation:** add a small guard (artisan command or bootstrap check) that
fails closed when a non-`testing` APP_ENV points at a database named
`pos_dashboard*`/`prod*`, mirroring QA-ENV-01; document the port-collision trap
(never bind a QA server to a port already used by a dev server).

### F4 — Emulator deep-sync journey not executed (release-process finding, INFO)

See §9. The product code is not implicated; this is a coverage gap that must be
closed before release per the phase plan.

---

## 7. Release blockers

| ID | Blocker | Status |
| --- | --- | --- |
| B-1 | Android emulator end-to-end offline→online→recovery journey not executed | **OPEN — required pre-release verification** |
| B-2 | Physical-device (MANUAL) verification not executed | OPEN |

No functional backend/mobile code defect (severity BLOCKER/HIGH) was found in
the executed scope.

---

## 8. Fix recommendations (technical)

1. **E2E runner (mobile/QA):** reset + seed the dedicated DB before each live
   spec; fail the run loudly if the DB is not fresh (F2).
2. **Backend (ops safety):** add a fail-closed runtime database guard for
   non-testing environments and document the OS-env + port-collision trap (F3).
3. **Contract test (backend):** pin the F1 ordering — a `403`-after-commit must
   remain resolvable via the status endpoint, and a replay must never duplicate.
4. **Mobile:** keep the reconciliation rule — `not_found` never deletes or
   rebuilds an envelope; cleanup only via CAS on `committed`.

---

## 9. Android emulator result (honest disposition)

**Executed (real emulator, AVD `POS_API_34`, Android 14):**

* `npm run build` (with `VITE_API_BASE_URL=http://10.0.2.2:18010`) → `npx cap sync android` → `gradlew assembleDebug` → **BUILD SUCCESSFUL** (`app-debug.apk`, 13.4 MB).
* `adb install -r` → **Success**; app launched via `monkey`.
* App booted: splash → local dashboard rendered ("POS Mobile", Grosir / Toko Kelontong), **no crash** (`adb logcat -b crash` empty).
* Navigated to **Cloud Login** form (Email / Password / Login Cloud) — reachable and rendered.

**NOT EXECUTED:** the scripted interactive journey (login → offline sale →
airplane-off → force-stop → relaunch → reconnect → sync/recovery with
screenshots). Automated driving by blind `adb shell input tap` proved
unreliable (WebView layout shifts when the soft keyboard opens, so taps landed
on wrong controls — the login submit was missed twice). This is reported as
partial, **not** as a pass, and is a release blocker (B-1). The equivalent
behaviour was instead exercised at the LIVE API tier with the *real* mobile
sync services (T8/T9) and at the UNIT tier (T4).

---

## 10. Recommendation & next phase

* Backend contract, tenant isolation, role re-evaluation, and exactly-once
  integrity are **verified live** with no data-integrity defect.
* Close B-1/B-2 with a deterministic, scriptable emulator/device harness
  (e.g. `adb reverse` + a fixed-density AVD + accessibility-id based UI taps,
  or an instrumented Espresso/Detox flow) rather than coordinate taps.
* Address F2/F3 before the next release-QA phase so the signal is trustworthy.

Not merged, not deployed.
