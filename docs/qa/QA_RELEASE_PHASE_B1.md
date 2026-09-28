# QA-RELEASE Phase B1 — Integrated Dashboard Backend & Gap Closure

Status: **Phase B1 complete for the dashboard backend — Cloud paid launch is
still gated (see Release Blockers).** Phase B2 (cross-repo E2E with POS Mobile)
runs after INT-02 merges.

| Field | Value |
| --- | --- |
| Repository | https://github.com/sawal123/pos_dashboard |
| Worktree | `D:\PROJECT WEB\POS OFFLINE\pos_dashboard` |
| Branch | `cmd/qa-release-b1-integrated` |
| Baseline (`origin/main`) | `6b227fa83e3d48ba65f7a753a02593ac9b38c7db` (merge of PR #42 INT-01; PR #43 Phase A = `03bcd73`) |
| Audit date | 2026-09-28 |
| Codex worktree | `pos_dashboard-shift` (`codex/int01-cashier-sync-api`) — **not touched** |

Read alongside: `docs/sync/INT01_CASHIER_SYNC_V1.md`, `docs/qa/QA_RELEASE_PHASE_A.md`.

---

## 1. Scope

B1 exercises the **integrated** dashboard after INT-01 (cashier-safe sync) merged,
re-runs the full regression and the real-MariaDB concurrency gate, closes the
Dashboard gaps found in Phase A (DASH-16 correction UI, production config), and
updates findings. Cross-repository POS Mobile E2E is deliberately deferred to B2
after INT-02.

---

## 2. INT-01 regression matrix

INT-01 adds `SyncAuthorizationPolicy` (allowlist preflight + in-transaction
re-check), `sync.push.cashier-safe`, and the `cashier_sync_v1` capability block.
Every row below was re-run against the integrated baseline.

| Contract | Coverage | Result |
| --- | --- | --- |
| Cashier pull | `CashierSafeSyncTest::test_cashier_can_pull_*`, tenant/outlet scope | Pass |
| Cashier push (safe entities) | customers/shifts/sales/sale_items/sale-payment/sale-movement | Pass |
| Cashier forbidden entities | categories/products/expenses/deletions + manual cash + generic stock | Pass |
| Owner/member full sync | `SyncPushTest`, `SyncPullTest`, `SyncParityTest`, `SyncIntegrityP38Test` | Pass (no regression) |
| Business/tenant isolation | `SyncAuthorizationTest`, `SyncParityTest`, `CashierSafeSyncTest` | Pass |
| Device/outlet isolation | device-derived outlet; foreign outlet → 403/409 | Pass |
| Cloud / free entitlement | `CLOUD_SUBSCRIPTION_REQUIRED` on free/expired/missing | Pass |
| Old token after role change | `test_existing_token_follows_role_changes_and_membership_removal` | Pass |
| Membership removal | `BUSINESS_ACCESS_DENIED` on next request | Pass |
| Mixed payload atomicity | forbidden mutation rejects the whole envelope (403, nothing written) | Pass |
| Immutable sale/cash/item snapshots | sale totals/HPP, sale-item snapshot, cash origin/link/amount | Pass |
| Sold quantity vs stock movement | cumulative deduction ≤ quantity sold (preflight **and** locked transaction) | Pass |
| Negative stock | legitimate oversell still accepted (P38/P3-A preserved) | Pass |
| Laundry lifecycle | forward-only `Masuk→Diproses→Siap Diambil→Selesai`; stale regression → 409 | Pass |
| Idempotency & retry | `(business_id, device_id, request_id)`; identical movement retry never re-applies | Pass |
| Unknown role | `push_mode: none`, all entities denied | Pass |

No protection was lost after PR #42 merged: owner/member keep the full contract,
cashiers gain a **narrower** allowlist, and unknown roles remain deny-by-default.

---

## 3. MariaDB concurrency

Verified QA-ENV-01 first: `phpunit.p38concurrency.xml` pins the connection to the
isolated `pos_p38_concurrency_test` database, and `tests/Support/TestDatabaseGuard`
fails closed on anything else (it rejects `pos_dashboard` in every profile). The
dev database was never migrated, refreshed or seeded.

```
vendor/bin/phpunit -c phpunit.p38concurrency.xml
→ OK (23 tests, 130 assertions)   against pos_p38_concurrency_test (MariaDB 12.1.2)
```

Required scenarios, all covered by real row locks (never SQLite):

| Scenario | Test | Asserted outcome |
| --- | --- | --- |
| Two cashiers, different movements, same sale/product | `test_concurrent_cashier_movements_cannot_exceed_sold_quantity` | `[200, 403]`; stock 10→9; exactly 1 movement |
| Only one unit sold ⇒ one legal deduction | (same test) | loser gets `SYNC_OPERATION_NOT_ALLOWED` / `stock_movement_exceeds_sold_quantity` — a **domain 403, never a 500** |
| No double stock deduction | `test_concurrent_stock_deltas_serialize_on_real_row_locks` | 10 − 3 − 4 = 3, exactly 2 movements |
| Cash payment exactly-once | `test_concurrent_cash_settlement_for_one_sale_stays_exactly_once` | exactly 1 cash row |
| Concurrent optimistic conflicts | `test_concurrent_master_edit_produces_exactly_one_winner` | `[200, 409]`, one row |

SQLite is explicitly **not** treated as concurrency evidence.

---

## 4. DASH-16 cash correction UI (Phase A finding M1 — closed)

Phase A found the reverse/void endpoints worked but had no dashboard affordance.
B1 adds an owner-only UI on top of the **existing** endpoints — no second
correction path, no change to append-only/idempotency behaviour.

### What was added

* `presentLedger()` now exposes `is_reversible` (plain manual dashboard row, not
  yet corrected) and `is_correction`; `presentExpense()` exposes `is_voidable`.
* `ledger-table`, `expense-table` and `mobile-cards` render a **Koreksi** /
  **Batalkan** action only when `cash.manage` is held and the row qualifies.
* `x-cash.correction-modal` — a confirmation dialog showing jenis, nominal,
  referensi and the consequence, posting to `cash.ledger.reverse` /
  `cash.expenses.void`.
* Client-side: single submit (button disabled on submit), Escape/backdrop close.

### Server remains the final authority

The UI only hides ineligible rows; the endpoints re-validate on submit and the
existing flash/error banners show the outcome. The action is **never** offered for:

| Excluded row | Mechanism |
| --- | --- |
| Cash from a sale | `sale_sync_id` set → `isManuallyReversible()` false |
| Cash pushed by POS Mobile | no dashboard `idempotency_key` → false |
| Cash linked to an expense | `expense_id` set → corrected via expense void |
| An existing correction row | `reverses_ledger_id`/category → false |
| A row from another tenant | list is tenant-scoped; endpoint 404s |
| A user without `cash.manage` | action + dialog not rendered; endpoint 403 |

### Evidence

`tests/Feature/CashCorrectionUiTest.php` (6 tests, 44 assertions) + the existing
`CashCorrectionTest` (server rules) and `CashPageTest`/`ExpenseManagementTest`.

Browser (owner, isolated QA database): reversing the Rp 45.000 manual cash-out
appended `REV-CASH-…`, removed its button and moved **Kas Masuk Rp 348.000 →
Rp 393.000**; voiding "Beli Gula" (Rp 50.000) removed it from the list and moved
**Total Pengeluaran Rp 170.000 → Rp 120.000**. Cashier/member saw **0** correction
buttons and no dialog.

---

## 5. Production security checks (Phase A finding H1 — closed)

* New command **`php artisan deploy:preflight`** (`app/Console/Commands/
  ProductionPreflightCommand.php`). It reads **resolved** config (so it matches
  `config:cache`) and exits non-zero on unsafe values:

  | FAIL | WARN |
  | --- | --- |
  | `APP_ENV != production` | `LOG_LEVEL=debug` |
  | `APP_DEBUG=true` | `QUEUE_CONNECTION=sync` |
  | `APP_KEY` missing | `MAIL_MAILER=log` |
  | `APP_URL` not https | `CACHE_STORE=array/null` |
  | `SESSION_SECURE_COOKIE` off | config not cached |
  | `SESSION_HTTP_ONLY=false` | |
  | `DB_CONNECTION=sqlite` | |
  | `SESSION_DRIVER=array` | |

  `--strict` promotes warnings to failures for CI/CD.

* `docs/ops/PRODUCTION_CHECKLIST.md` — deploy checklist (env, HTTPS/session,
  database backup, queue/scheduler, cache/config, file permissions, rollback,
  sensitive logging).
* `tests/Feature/Console/ProductionPreflightTest.php` (7 tests) pins the
  fail-closed behaviour (`APP_ENV=production` + `APP_DEBUG=true` → exit 1).

The developer's local `.env` was **not** modified; no secret was added to Git.

---

## 6. Browser QA

Owner / member / cashier / unknown, two businesses (cloud + free), two outlets,
against a throwaway SQLite database (dev DB untouched).

| Flow | Owner | Member | Cashier |
| --- | --- | --- | --- |
| Cash & expenses | Pass | read-only | read-only |
| Reversal / void UI | Pass (dialog + apply) | not rendered | not rendered |
| Reports + export links | Pass (3 links) | Pass (later test) | 403 |
| Product management | Pass | read-only | read-only |
| Device management | Pass | not rendered | not rendered |
| Business switcher | Pass | — | — |
| Sync monitoring | Renders (HTTP 200, 195 ms) — see note | — | 403 |
| Responsive (390×844) | Pass (desktop table hidden, mobile card shows "Batalkan") | — | — |
| Dark mode | Pass | — | — |

**Note:** the `/sync` page returns 200 and renders (verified over authenticated
HTTP and by `SyncMonitoringPageTest`, 44 tests); the headless browser navigation
for that page timed out twice, so its *visual* smoke test is recorded as
NOT EXECUTED in the browser. No functional defect was observed.

---

## 7. Findings update (vs Phase A)

| Phase A finding | Status now |
| --- | --- |
| **H1** `APP_DEBUG=true` leaks internals | **Closed** — `deploy:preflight` + checklist + tests |
| **M1** DASH-16 reversal/void had no UI | **Closed** — owner-only UI + tests |
| **L1** legacy placeholder modals | Fixed in Phase A (regression-guarded) |
| **M2** no device secret / token↔device binding | **Remaining** — separate security task |
| **M3** Sanctum tokens never expire | **Remaining** — separate security task |
| **M4** no unique index on `reverses_ledger_id` | **Remaining** (low) |
| **L2** sync endpoints unthrottled | **Remaining** (low) |
| **L3** `me`/`logout` skip the `mobile` ability | **Remaining** (low) |
| **L4** no `max` on sync `device_identifier` | **Remaining** (low) |
| **L5** 2FA users rejected at mobile login | **Remaining** (low) |
| Business-wide pull entities (I1) | Verified as designed |
| Cash-paid expense in both `cash_out` and `total_expense` (I2) | Verified as designed |

No new HIGH/BLOCKER defect was found in the integrated dashboard.

---

## 8. Quality gates

| Gate | Result |
| --- | --- |
| `php artisan test` (SQLite `:memory:`, QA-ENV-01) | **1119 passed / 5 skipped / 0 failed** (see §9 for skips) |
| `vendor/bin/phpunit -c phpunit.p38concurrency.xml` | OK — 23 tests / 130 assertions |
| `vendor/bin/pint --test` | PASS — 246 files |
| `vendor/bin/phpstan analyse --memory-limit=1G` | No errors |
| `npm run build` | OK |
| `git diff --check` | Clean |
| `composer ci:check` | PASS |

The 5 skips are exactly the MySQL-only tests in `SyncConcurrencyMySqlTest`
(the 4 pre-existing + the new cashier double-deduction test), skipped on SQLite;
all 5 run and pass under `phpunit.p38concurrency.xml`.

---

## 9. Release blockers

* **B1 — INT-02 (POS Mobile) not merged.** Cross-repo E2E, outbox filtering and
  the token-after-role-change contract can only be certified once `pos-mobile`
  ships INT-02. Until then the backend remains the enforcement boundary and old
  mobile builds that enqueue forbidden rows get a 403 for the whole payload.
* **B2 — DASH-12B billing undecided.** Cloud paid launch cannot be validated;
  keep it blocked.

No dashboard-side blocker remains for the integrated backend itself.

---

## 10. Phase B2 plan (after INT-02)

1. Rebase on the latest `main`; integrate INT-02 mobile-side tests if any.
2. POS Mobile ↔ dashboard E2E: cashier outbox filtering against
   `sync_capabilities`, mixed-payload rejection surfaced in the app, no silent data
   drop.
3. Old-token-after-role-change and membership-removal E2E from the mobile client.
4. Cloud/free entitlement E2E for the paid launch (after DASH-12B).
5. Re-run all gates + the MariaDB concurrency gate; refresh the release checklist.

---

## 11. Conclusion

The integrated dashboard backend is solid: **1119 passing tests, 0 failures**,
a green MariaDB concurrency gate including the new cashier double-deduction
guard, and both Phase A blocker-adjacent dashboard gaps (H1 debug config, M1
correction UI) closed with regression tests and browser evidence. QA-RELEASE as a
whole remains **open** pending INT-02 and the DASH-12B decision.
