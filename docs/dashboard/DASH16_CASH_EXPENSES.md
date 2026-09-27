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

## Reporting Note

Dashboard cash summaries keep cash and expense totals separate:

- `cash_in`, `cash_out`, and `net_cash_flow` come only from `cash_ledger`.
- `total_expense` comes only from recorded expenses.

This avoids counting a cash-paid expense twice in the expense total. Report export and report dataset code were intentionally left unchanged for DASH-13 isolation.
