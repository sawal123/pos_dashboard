# DASH-16 Cash & Expense Management

## Scope

DASH-16 enables dashboard owners to create financial records from `/cash`:

- Cash ledger entries for manual cash in and cash out.
- Operational expenses with optional shift binding.
- Optional `Dibayar dari Kas` handling that creates a linked cash-out ledger row.
- Append-only cash reversal and expense void flows for corrections.

Members and cashiers keep read access through `cash.view`, but all write endpoints require `cash.manage`, which is owner-only through the `*` owner permission.

## Data Contract

`CashLedger` and `Expense` remain separate accounting entities.

- `cash_ledger` tracks physical cash movement.
- `expenses` tracks operational expense accounting.
- Dashboard-created rows rely on `HasSyncMetadata` for `sync_id`, `sync_version`, and `sync_sequence`.
- Dashboard double-submit protection uses nullable `idempotency_key` columns with unique indexes scoped by `business_id`.
- A cash-paid expense stores the stable relationship on `cash_ledger.expense_id`.

An expense is not automatically treated as cash movement. The linked `CashLedger` is created only when the owner explicitly checks `Dibayar dari Kas`, and both rows are created in one database transaction.

## Tenant & Authorization Rules

All mutations derive `business_id` from the active dashboard context. Posted `business_id` values are ignored.

Validation restricts:

- `outlet_id` to the active business.
- `shift_id` to the active business and selected outlet.

Routes use `auth`, `verified`, dashboard business context sharing, CSRF, and `business.permission:cash.manage`.

## Corrections

Financial rows are not hard-deleted by dashboard actions.

- Cash correction creates an opposite-direction `CashLedger` reversal.
- Expense correction updates `expenses.status` to `void`.
- If a voided expense had a linked cash-out ledger, a linked cash-in reversal is appended.

Both correction paths use deterministic idempotency keys so retries do not create duplicate reversals.

### Correction Ownership

The generic `cash.ledger.reverse` flow only owns cash that verifiably originated from the
dashboard. A row is reversible only when it is a plain manual entry **and** carries the
dashboard origin identity: a non-null `idempotency_key` whose derived reference equals
`reference_id` (`DASH-CASH-` + first 12 characters of the key). It is rejected, with
guidance toward the originating mechanism, for:

- cash synced from a sale (`sale_sync_id` is set),
- cash linked to an expense (`expense_id` is set) — correct it by voiding the expense,
- a previous correction (`category` is `reversal` / `expense_void`, or `reverses_ledger_id` is set),
- manual cash pushed by POS Mobile, which never carries a dashboard `idempotency_key`
  and uses a device-local `reference_id` — it is corrected on the originating device.

### Deterministic Correction Link

`cash_ledger.reverses_ledger_id` records exactly which row a correction reverses. This makes
"has this row already been corrected?" an exact lookup instead of a guess from shared
`expense_id` values, and it lets a retry or a simultaneous request re-use the existing
correction rather than append a second one.

`Expense->cashLedger` resolves the original payment deterministically as the single cash-out
(`type = out`) row for the expense that is not itself a correction (`reverses_ledger_id`
is null). Void refunds preserve the same `expense_id` but are cash-in corrections, so they
can never be mistaken for the payment row.

`voidExpense` therefore refunds the linked cash at most once. If that cash was already
corrected, no second refund is appended.


## Reporting Note

Dashboard cash summaries keep cash and expense totals separate:

- `cash_in`, `cash_out`, and `net_cash_flow` come only from `cash_ledger`.
- `total_expense` comes only from recorded expenses.

This avoids counting a cash-paid expense twice in the expense total. Report export and report dataset code were intentionally left unchanged for DASH-13 isolation.
