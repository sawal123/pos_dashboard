# QA-RELEASE Phase A — Integrated Baseline & Security Audit

Status: **Phase A complete — not a final release sign-off.** Phase B is blocked on
INT-01 (cashier-safe sync) and DASH-12B (billing).

| Field | Value |
| --- | --- |
| Repository | https://github.com/sawal123/pos_dashboard |
| Worktree | `D:\PROJECT WEB\POS OFFLINE\pos_dashboard` |
| Branch | `cmd/qa-release-dashboard` |
| Baseline (`origin/main`) | `3176cb307b604481a5edb843b66806f3b2d35cc2` (merge of PR #41) |
| Audit date | 2026-09-28 |
| Codex worktree | `D:\PROJECT WEB\POS OFFLINE\pos_dashboard-shift` (`codex/int01-cashier-sync-api`) — **not touched** |

---

## 1. Scope

Phase A audits the integrated dashboard baseline as merged into `main`
(PRs #37–#41). It covers tenant/business context, business type, owner/member/
cashier RBAC, products/categories/services, inventory & stock movements,
transactions, shifts, customers, cash & expenses, reports/exports, devices &
mobile registration, the existing sync push/pull contract, and subscription /
Cloud entitlement.

Out of scope for Phase A: redesign, new business features, and the INT-01
cashier-safe sync endpoint (still in progress on a separate worktree). This audit
does not declare the overall QA-RELEASE complete.

---

## 2. Environment & database isolation

* PHP 8.4.16, Laravel 13, Livewire 4, Pest/PHPUnit 13, Node 22, MariaDB 12.1.2.
* The standard suite is forced to SQLite `:memory:` by `phpunit.xml`
  (`<env force>` **and** `<server>` mirrors) and is fail-closed-protected by
  `tests/Support/TestDatabaseGuard.php` (QA-ENV-01). The development `.env`
  (`pos_dashboard` on MySQL) was **never** migrated, refreshed or seeded during
  this audit.
* The concurrency gate ran against the dedicated throwaway database
  `pos_p38_concurrency_test` only (`phpunit.p38concurrency.xml`), which is the
  sole MySQL target the guard accepts.
* Browser QA ran against a **separate throwaway SQLite file** created for this
  audit, via process-level `DB_CONNECTION=sqlite` / `DB_DATABASE=<temp>` overrides
  (OS env wins over `.env`). The development database was untouched.
* No `.env` or `.commandcode/` files were deleted or committed.

---

## 3. Module × role coverage matrix

Roles: **owner** (`*`), **member**, **cashier**, **unknown** (no matrix entry),
**guest** (unauthenticated). "✅" = allowed, "⛔" = 403, "—" = not applicable.

| Module / operation | owner | member | cashier | unknown |
| --- | --- | --- | --- | --- |
| Dashboard view | ✅ | ✅ | ✅ | ⛔ |
| Transactions read (list/detail) | ✅ | ✅ | ✅ | ⛔ |
| Products/services read | ✅ | ✅ | ✅ | ⛔ |
| Products/services/categories **manage** | ✅ | ⛔ | ⛔ | ⛔ |
| Stock read + movements | ✅ | ✅ | ✅ | ⛔ |
| Cash & expenses read | ✅ | ✅ | ✅ | ⛔ |
| Cash ledger / expense **create** | ✅ | ⛔ | ⛔ | ⛔ |
| Cash reversal / expense void | ✅ | ⛔ | ⛔ | ⛔ |
| Shifts read / detail | ✅ | ✅ | ✅ | ⛔ |
| Customers read / detail | ✅ | ✅ | ✅ | ⛔ |
| Laundry orders read / detail | ✅ | ✅ | ✅ | ⛔ |
| Reports read + CSV/XLSX/PDF export | ✅ | ✅ | ⛔ | ⛔ |
| Outlets read / detail | ✅ | ✅ | ⛔ | ⛔ |
| Users/members read | ✅ | ⛔ | ⛔ | ⛔ |
| Invitations / member removal / role change | ✅ | ⛔ | ⛔ | ⛔ |
| Devices read | ✅ | ⛔ | ⛔ | ⛔ |
| Devices **register/edit/activate** | ✅ | ⛔ | ⛔ | ⛔ |
| Sync monitoring page | ✅ | ⛔ | ⛔ | ⛔ |
| Subscription overview | ✅ | ⛔ | ⛔ | ⛔ |
| Business profile / type settings | ✅ | ⛔ | ⛔ | ⛔ |
| Business context switch (`/dashboard/business-context`) | ✅ (own) | ✅ (own) | ✅ (own) | ⛔ |
| Mobile API `mobile/context`, `mobile/devices` | ✅ | ✅ | ⛔ | ⛔ |
| Sync `push` / `pull` | ✅ | ✅ | ⛔ | ⛔ |

Guests are redirected to login; unverified users to the verification notice.
`business_type` never grants access (DASH-14) — it only adapts the UI.

Server-side enforcement: every dashboard route carries an explicit
`business.permission:*` middleware (`routes/web.php`); the sidebar is cosmetic.
`unknown`/no-membership resolves to an empty permission list (deny-by-default).
The only unguarded dashboard route is `dashboard/business-context`, which is
protected by membership re-validation + `Gate::authorize('view')`.

---

## 4. Tenant / outlet isolation matrix

| Attack / condition | Expected | Observed | Evidence |
| --- | --- | --- | --- |
| Forged `business_id` in body/query | Ignored (active business from session) | Ignored | `CashierRbacTest`, `ReportExportTest` test_08 |
| Cross-tenant route id (product/customer/shift/sale/outlet/user/device/cash/expense) | 404 | 404 | controllers re-scope by `business_id` before `firstOrFail`; `CashCorrectionTest::test_reverse_and_void_are_tenant_scoped` |
| Cross-tenant outlet filter | Rejected or empty | Rejected (export) / empty (lists) | `ReportExportTest` test_09, `TransactionsPageTest` |
| Revoked membership | Business disappears next request | Re-validated every request | `DashboardBusinessContext::current()` matches against a fresh membership set |
| Role change (member↔cashier) | Applies on next request (incl. live Sanctum tokens) | Applies | `BusinessAuthorizer::roleIn()` re-reads the pivot per request |
| Switching active business | Dataset changes | Dataset changes (verified in browser) | `DashboardBusinessContextTest` |
| Cross-tenant sync business/device | 403 | 403 | `SyncAuthorizationTest`, `MobileSyncContextTest` |
| Free/expired/no subscription | 403 `CLOUD_SUBSCRIPTION_REQUIRED` | 403 | `SyncAuthorizationTest` |
| Inactive device | 403 `DEVICE_INACTIVE` | 403 | `SyncAuthorizationTest`, `DeviceManagementTest` |
| No active business | Empty state (200) on lists; 403 on owner-only controllers | As designed | `CashierRbacTest::test_user_without_business_keeps_empty_state_but_no_member_admin_access` |

No cross-tenant IDOR was found. Mass assignment is safe: all models use
`#[Fillable([...])]`, no `$request->all()` exists, and `business_id` is never a
validation rule (services always set the tenant server-side).

---

## 5. Automated regression results

Baseline suite (`php artisan test`, SQLite `:memory:`):

| Metric | Value |
| --- | --- |
| Passed | **1065** |
| Failed | **0** |
| Skipped | **4** (the MySQL-only `SyncConcurrencyMySqlTest`) |
| Assertions | 3850 |
| Duration | ~150s |

QA-RELEASE-focused classes named in the brief were all run and pass:
`BusinessTypeTest`, `BusinessTypeNavigationTest`, `DashboardBusinessContextTest`,
`CashierRbacTest`, `ProductsCrudTest`, `ProductsPageTest`, `StockPageTest`,
`TransactionsPageTest`, `ShiftsPageTest`, `CashManagementTest`,
`ExpenseManagementTest`, `CashCorrectionTest`, `ReportsPageTest`,
`ReportExportTest`, `DeviceManagementTest`, `DevicesPageTest`,
`MobileRoleAuthorizationTest`, `SyncAuthorizationTest`, `SyncParityTest`,
`SyncPushTest`, `SyncPullTest`.

New Phase A regression tests (`tests/Feature/QaReleasePhaseATest.php`, 3 tests,
46 assertions) were added for the two genuine gaps found (see §12 M1/L1 and
§11). No existing failing test was hidden.

---

## 6. Concurrency & database

The standard suite runs on SQLite, where `lockForUpdate()` is a **no-op** — it
is never accepted as concurrency evidence. The dedicated MySQL/MariaDB gate was
therefore executed on this machine:

```
vendor/bin/phpunit -c phpunit.p38concurrency.xml
→ OK (22 tests, 123 assertions)   against pos_p38_concurrency_test (MariaDB 12.1.2)
```

This exercises real InnoDB row locks for: concurrent stock deltas, concurrent
oversell (negative stock), concurrent master edit (exactly one winner),
concurrent sale-cash settlement (exactly once), plus the P38 integrity suite.

Status: **MySQL concurrency verification executed and passing.** Remaining
concurrency-relevant gaps are noted in §12 (M4, L2).

---

## 7. Financial reconciliation

Seeded scenario (isolated QA database), then compared across the screen and all
three exports with an identical `date=all` filter:

| Metric | Dashboard / screen | CSV export | XLSX export | PDF export |
| --- | --- | --- | --- | --- |
| Total Penjualan | Rp 113.500 | `113500` | `113500` | `Rp 113.500` |
| Total Transaksi | 4 | `4` | `4` | `4` |
| Estimasi Laba Kotor | Rp 45.400 | `45400.00` | `45400` | `Rp 45.400` |
| Total Pengeluaran | Rp 170.000 | `170000` | `170000` | `Rp 170.000` |

Cash page (live): Kas Masuk Rp 348.000, Kas Keluar Rp 45.000, Net Rp 303.000,
Total Pengeluaran Rp 170.000 — **void expense correctly excluded**.

Accounting rules confirmed: revenue = `status completed AND payment_status paid`;
gross profit = historical `gross_profit` snapshot; expenses = `status recorded`;
`cash_in/out/net` come only from `cash_ledger`; `total_expense` only from
`expenses`. Page and CSV/XLSX/PDF share one `ReportDatasetBuilder`.

Manual cash reversal rules were verified in code and by
`CashCorrectionTest` (22 tests): sale-synced cash, expense-linked cash, prior
corrections and POS-Mobile manual cash are all rejected; reversal/void are
retry-safe and single-instance.

> Phase A parity across **CSV only** was asserted by the existing suite. The new
> `QaReleasePhaseATest` now pins **XLSX and PDF** to the same on-screen dataset
> under a filter (previously untested).

---

## 8. Inventory & transaction regression

* **Negative stock is valid (P38/P3-A):** no `min:0` on `stock`,
  `stock_before`, `stock_after` or `quantity_change`; the columns are signed
  `decimal(15,3)`. Verified in browser (−3.000 porsi shown as "Minus") and by
  `SyncIntegrityP38Test`.
* Current stock is the persisted `products.stock` column maintained by movement
  **deltas**; movements are immutable history and are never re-summed.
* Movement idempotency: unique `(business_id, sync_id)` + lock-first +
  `attributesMatch` short-circuit; a retry never re-applies the delta.
* `SaleItem` is a full historical snapshot (`unit_price`, `cost_snapshot`,
  `product_name/sku`, `unit`, `kind`, `pricing_unit`) — dashboards never read the
  live product price/HPP.
* Sale-linked cash dedupe is `(business_id, sale_sync_id, type)`; a differing
  amount is a deterministic 409, never a silent second row.
* Laundry: decimal quantities flow end-to-end (`decimal(15,3)`, `min:0.001`), and
  the forward-only lifecycle `Masuk → Diproses → Siap Diambil → Selesai` rejects
  stale-device regressions with 409.
* Multi-outlet isolation of transactions holds (sales, sale items, shifts, cash,
  expenses are outlet-scoped on push and on pull).

No validation was found that rejects legitimate offline oversell or decimal
laundry quantities.

---

## 9. Device & sync security (DASH-17)

Verified:

* Owner-only dashboard device management (`devices.view` / `devices.manage`).
* Registration resolves by `(business_id, identifier)`; duplicate on the same
  outlet is idempotent, duplicate on another outlet is rejected as an outlet
  mismatch; a concurrent race is caught via the unique constraint.
* Inactive devices are never auto-reactivated and are rejected by re-registration
  and by both sync endpoints.
* Business/outlet isolation holds (device is resolved within the token's
  business; a foreign identifier yields `SYNC_DEVICE_INVALID`).
* `identifier` is immutable after registration.
* The UI never labels an `active` device "Online"/"Terhubung"; `last_seen_at` is
  shown as "Akses API Terakhir" / "Belum Pernah Akses API".

Browser-verified: the registration modal restored correctly after an
outlet-mismatch validation error, preserving the identifier and outlet.

Documented, **not fixed** (Phase A boundary — no new pairing protocol):

* Dashboard pre-registration is **not** secure pairing and not proof of
  possession.
* There is **no device secret** and **no token↔device binding**. Any business
  member with sync permission can name any `identifier` in that business and act
  as that device (see §12 M2).

---

## 10. Security sweep

| Area | Result |
| --- | --- |
| Route middleware | Every dashboard route carries `auth, verified, ShareDashboardBusinessContext` + an explicit permission; API routes are `auth:sanctum` |
| Server-side authorization | Enforced (middleware + form-request `authorize()` + service checks); sidebar is not security |
| CSRF | Enabled by the default `web` group (`ValidateCsrfToken`) |
| Sanctum abilities | Login issues only `mobile`; sync/context/device endpoints check `tokenCan('mobile')` and RBAC per request |
| Membership lookup per request | Yes — session stores only an ID; membership/role re-read from the pivot every request |
| Mass assignment | Safe (see §4) |
| SQL injection | No raw user input interpolated; query builder/ORM + bound params throughout |
| XSS / Blade escaping | Escaped by default; no `{!! !!}` on user data found in the audited surfaces |
| CSV/XLSX formula injection | CSV prefixes `= + - @ tab CR LF`; XLSX uses explicit string cells (no `<f>`); regression-tested |
| Cross-tenant / cross-outlet IDOR | None found (§4) |
| Error-response leakage | Generic sync error payloads; **but** see §12 H1 (debug mode) |
| Login/session | Throttled (6/min); Fortify flows present; 2FA users are rejected from mobile login (fail-closed) |
| Rate limiting | Membership/invitation mutations keyed per actor (10/min). **Sync endpoints are unthrottled** (§12 L2) |
| Export authorization | All export routes require `reports.view` (cashier/member with no reports.view → 403) |
| Queue/email | Invitation mail logs failures without the token; only `token_hash` is stored |
| Sensitive logging / secrets | No token/password logging found. `.env` is git-ignored; no secret was printed in this report or commit |

---

## 11. Browser smoke tests (desktop + mobile)

Executed against the isolated throwaway SQLite database with `agent-browser`
(Chrome via CDP). Screenshots were captured for each step.

| # | Flow | Result |
| --- | --- | --- |
| 1 | Login (owner) | Pass |
| 2 | Business switcher (QA Cafe ↔ QA Grosir) | Pass — datasets and CTA (`Kelola Paket` ↔ `Tingkatkan Paket`) change |
| 3 | Sidebar by role + business type | Pass |
| 4 | Products listing (stock badges incl. Minus/Menipis/Habis) | Pass |
| 5 | Product create (modal → submit) | Pass — flash "Produk berhasil ditambahkan", row appears |
| 6 | Cash summary | Pass — matches seeded ledger exactly; void excluded |
| 7 | Cash detail drawer | Pass |
| 8 | Device registration modal | Pass |
| 9 | Device validation-error recovery | Pass — modal reopens with the outlet-mismatch error; identifier/outlet preserved |
| 10 | Reports page + export links carry active filter | Pass |
| 11 | CSV/XLSX/PDF summary parity | Pass (numeric match, §7) |
| 12 | Cashier role: restricted sidebar + direct URL 403 | Pass (`/reports`, `/devices` → 403) |
| 13 | Dark mode | Pass |
| 14 | Responsive/mobile (390×844) | Pass — hamburger nav, stacked cards, no overflow |

Investigated and cleared: a headless click on the catalog **Simpan** button did
not submit while the button sat below the fold at a 568px-tall viewport. On
scroll the button hit-tests correctly (`elementFromPoint` → the button) and a
real submit succeeds. This is a viewport/scroll artifact, **not** a UI defect;
the action row is inside the modal's scroll container.

Not automated in Phase A (manual-only, listed for Phase B): full keyboard-only
navigation run, and PDF visual rendering inspection.

---

## 12. Findings by severity

No finding was hidden. Severity is release-relative.

### BLOCKER (release-level dependencies, not dashboard defects)

* **B1 — INT-01 cashier-safe sync not integrated.** Cashier sync authorization
  behaviour was baselined against current `main` only; the cashier-safe contract
  is unverified until INT-01 merges. *Action:* integrate and re-run Phase B;
  do not certify the sync contract before then.
* **B2 — DASH-12B billing undecided.** Gateway/pricing/period not decided, so the
  Cloud paid launch cannot be validated. *Action:* keep Cloud paid launch
  blocked until billing is implemented and validated.

### HIGH

* **H1 — Debug mode in the local `.env` leaks internals if deployed as-is.**
  *Repro:* `.env` has `APP_ENV=local` and `APP_DEBUG=true`; `bootstrap/app.php`
  renders JSON for `api/*`; an unhandled exception returns a stack trace/SQL.
  *Expected:* `APP_DEBUG=false`, `APP_ENV=production` in any deployed env.
  *Impact:* information disclosure of stack/SQL/config. *Files:* `.env`,
  `.env.example` (ships `APP_DEBUG=true`), `bootstrap/app.php:29-32`.
  *Recommendation:* enforce non-debug in deployment config / CI deploy checks.

### MEDIUM

* **M1 — DASH-16 cash reversal & expense void have no dashboard UI.**
  *Repro:* open `/cash` as owner; the reversible manual row shows only "Tutup";
  `grep` finds no view posting to `cash.ledger.reverse` or `cash.expenses.void`.
  *Expected:* a correction control for reversible rows (per DASH-16).
  *Actual:* the endpoints exist, are permission-gated, and are fully tested, but
  are unreachable from the UI. *Impact:* documented correction workflows are
  API-only; users cannot correct from the dashboard. *Files:* routes `web.php:85-95`,
  `resources/views/cash/*`. *Tests:* `CashCorrectionTest` (passes).
  *Recommendation:* add a confirmed reverse/void action, or explicitly document
  that corrections are intentionally not exposed in the DASH-16 UI.
* **M2 — No device secret / no token↔device binding.** A business member with
  sync permission can impersonate any device `identifier` in that business
  (documented in DASH-17 §11). *Impact:* within-tenant device spoofing; outlet
  selection within the tenant. *Recommendation:* a dedicated security task for
  device-scoped credentials (not Phase A).
* **M3 — Mobile API tokens never expire** (`config/sanctum.php` `expiration =>
  null`). *Impact:* a leaked bearer token is valid indefinitely.
  *Recommendation:* set an expiration and/or rotate on role change.
* **M4 — Exactly-once correction relies on PHP + a unique key, not a schema
  constraint on `reverses_ledger_id`.** `lockForUpdate` is a no-op on SQLite.
  *Impact:* low under the current code paths; a future writer could append a
  second correction. *Recommendation:* consider a unique index scoped by
  `business_id`.

### LOW

* **L1 — Legacy placeholder modals rendered globally. FIXED in this PR.**
  `layouts/app/sidebar.blade.php` included `x-ui.modal-add-product` and
  `x-ui.modal-delete` — hidden English demo markup ("Add Product" / "Save
  Product" / "Delete Product?") that nothing opens. Removed the includes and the
  two unused component files; regression-guarded by `QaReleasePhaseATest`.
* **L2 — Sync endpoints are unthrottled** (`routes/api.php:19-20`); only
  `/auth/login` is throttled. *Recommendation:* add a per-token/per-device
  limiter (product decision; not done in Phase A).
* **L3 — `GET /api/auth/me` and `DELETE /api/auth/logout` do not check the
  `mobile` ability** (any valid Sanctum token can call them; own-data only).
* **L4 — `device_identifier` has no `max` length in `SyncPushRequest` /
  `SyncPullRequest`** (inconsistent with `mobile/devices` `max:100`).
* **L5 — 2FA-confirmed users are rejected at mobile login** (fail-closed, but
  locks out 2FA users). *Recommendation:* define the intended 2FA behaviour.

### INFORMATIONAL

* **I1 — Business-wide entities are pulled to every device in a business**
  (categories/products/customers/stock_movements) → cross-outlet visibility
  within the tenant. Documented design; asserted by `SyncPullTest`.
* **I2 — A cash-paid expense appears in both `cash_out` and `total_expense`**
  (distinct accounting entities; each metric is internally non-double-counting).
  Confirm this is the intended presentation.
* **I3 — Reversal/void rows use `occurred_at = now()`**, so a period-filtered
  report attributes the correction to the correction period, not the original.
* **I4 — Manual cash/expense store idempotency is client-key-driven**: a client
  that generates a fresh UUID per retry can create a duplicate. Correction flows
  are server-deterministic and safe.
* **I5 — `EnsureBusinessPermission` passes through when there is no active
  business** (lists show a 200 empty state; owner-only controllers still 403).
  By design and covered by `CashierRbacTest`.

---

## 13. Quality gates

| Gate | Result |
| --- | --- |
| `composer ci:check` (config:clear → pint --test → phpstan → artisan test) | **PASS** — exit 0; Pint 240 files, PHPStan 175 files no errors, suite **1068 passed / 4 skipped / 3896 assertions** |
| `php artisan test` (baseline before this PR) | 1065 passed / 0 failed / 4 skipped (3850 assertions) |
| `php artisan test` (with this PR's 3 new tests) | 1068 passed / 0 failed / 4 skipped (3896 assertions) |
| `vendor/bin/pint --test` | PASS — 240 files |
| `vendor/bin/phpstan analyse --memory-limit=1G` | No errors — 175 files |
| `npm run build` | OK (non-fatal `fontaine`/plugin-timing warnings) |
| `git diff --check` | Clean (exit 0) |
| `vendor/bin/phpunit -c phpunit.p38concurrency.xml` | OK — 22 tests, 123 assertions |

GitHub Actions: the `tests` workflow runs `composer setup` + `composer ci:check`
on every PR. Phase A expects the same green result; the PR URL is recorded in the
PR itself.

---

## 14. Known limitations

* Phase A does not certify the cashier-safe sync contract (INT-01 pending).
* Browser QA ran against a synthetic fixture, not a production/staging dataset,
  and did not include a full keyboard-only traversal or visual PDF inspection.
* The MySQL concurrency gate ran on the local dedicated database; it is not part
  of the default CI job (CI uses `phpunit.xml`).
* INT-01 files on the Codex worktree were not reviewed or modified.

---

## 15. Pending dependencies

| Dependency | Status | Owner |
| --- | --- | --- |
| INT-01 Cashier-safe Sync API | In progress (Codex worktree) | Sync |
| DASH-12B Billing (gateway/pricing/period) | Awaiting decision | Product |

Cloud/free entitlements, POS-Mobile contract adoption, and token-after-role-change
behaviour are Phase B items gated on INT-01.

---

## 16. Phase B plan (after INT-01 merges)

1. Rebase on the latest `main` and integrate Codex's INT-01 regression tests.
2. Cashier-safe sync end-to-end (push/pull as cashier, mixed-payload rejection).
3. Existing token behaviour after a role downgrade (member → cashier).
4. Cloud vs free entitlement matrix across all sync endpoints.
5. POS-Mobile contract adoption (only "done" once the `pos-mobile` repo applies
   the required contract).
6. Re-run the full quality gates + the MySQL concurrency gate; update the release
   checklist.
7. DASH-12B billing validation before any Cloud paid launch.

---

## 17. Conclusion

The merged dashboard baseline is in good shape: **1065 passing tests, 0
failures**, clean Pint/PHPStan/build, and a passing real-MySQL concurrency gate.
No cross-tenant IDOR or mass-assignment vulnerability was found, and financial
reporting reconciles exactly across the screen and CSV/XLSX/PDF.

QA-RELEASE is **not** complete: the overall release remains blocked on INT-01 and
DASH-12B (§15), and the HIGH/MEDIUM findings above (debug-mode deployment, the
DASH-16 correction UI gap, device credential binding, token expiry) should be
triaged before a production launch. This Phase A PR (documentation + regression
tests + one isolated placeholder cleanup) may be reviewed and merged on its own
after CI is green.
