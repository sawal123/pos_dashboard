# DASH-11 — Laundry Order Monitoring (Read-Only)

Branch: `feat/dashboard-laundry-monitoring` · base: `main` (`7784980`)

DASH-11 adds a **read-only** monitoring page for synced laundry orders. It reads
existing `sales` / `sale_items` data only: no schema change, no new API, no sync
contract change, no status/payment mutation, and it does not touch the Customer,
Shift, Outlet, Transaction or Sync modules.

---

## 1. What counts as a laundry order

A **laundry order is a sale with a non-null `order_status`** within the active
business:

```sql
WHERE business_id = :active AND order_status IS NOT NULL
```

Cafe/retail/grosir sales keep `order_status = null` and are therefore never part
of the dataset. The lifecycle values used by the POS/sync contract are
`Masuk`, `Diproses`, `Siap Diambil`, `Selesai` (`sales.order_status`).

`order_status` is the operational lifecycle and is **completely independent of**
the transaction `status` and of `payment_status`. The three axes answer different
questions and are never mixed:

| Axis | Column | Values (audited) | Meaning |
| --- | --- | --- | --- |
| Status pengerjaan (laundry) | `sales.order_status` | `Masuk`, `Diproses`, `Siap Diambil`, `Selesai` | how far the laundry work has progressed |
| Status transaksi | `sales.status` | `completed`, `cancelled` / `canceled`, `void` | whether the sale still stands |
| Status pembayaran | `sales.payment_status` | `paid`, `unpaid` | whether the order has been paid |

An order can be `Selesai` and still `unpaid` (both are shown separately), and a
`completed + paid` transaction can still be `Diproses`. Only the overdue metric
reacts to the transaction status (see §3); the order counters do not.

---

## 2. Data sources

| Displayed field | Source |
| --- | --- |
| Nomor transaksi | `sales.transaction_number` |
| Pelanggan | `sales.customer_snapshot->name` → `customers.name` → `"Pelanggan Umum"` |
| Telepon | `sales.customer_snapshot->phone` → `customers.phone` → `null` |
| Outlet | `sales.business_snapshot->outlet` → `outlets.name` → `-` |
| Tanggal pesanan | `sales.sold_at` |
| Estimasi selesai | `sales.estimated_completed_at` |
| Status pengerjaan | `sales.order_status` (raw value) |
| Status pembayaran | `sales.payment_status` (raw value) |
| Metode pembayaran | `sales.payment_method` (raw value) |
| Total | `sales.total_amount` |
| Catatan | `sales.note` |
| Item layanan | `sale_items.product_name` (snapshot), `quantity` (decimal), `unit`, `unit_price`, `line_total` |

Customer identity priority is snapshot → relation → generic label. Snapshots are
only ever used as display values; **rows are never grouped by customer name**, so
two different customers who share a name remain separate orders.

Item prices always come from the **historical `sale_items` snapshot**, never from
the current `products.price`. `quantity` is a `decimal(15,3)` and is never cast to
an integer (e.g. `2.5 kg` is preserved and shown as `2,5 kg`).

---

## 3. Metrics (summary)

The summary reflects the **whole active business, unfiltered** — consistent with
the existing Shift, Customer and Outlet monitoring pages, whose summary cards also
ignore list filters. This is a deliberate, documented decision.

| Metric | Definition |
| --- | --- |
| Total Pesanan | `COUNT(*)` of laundry orders in the active business |
| Masuk / Diproses / Siap Diambil / Selesai | `COUNT(*)` by exact `order_status` |
| Terlambat | see overdue rule below |
| Total Transaksi Lunas | `SUM(total_amount)` where transaction `status = completed` **AND** `payment_status = paid` (does **not** require `order_status = Selesai`) |

`Total Transaksi Lunas` is intentionally a **separate** metric from the order
counters. It reports completed + paid laundry transactions **regardless of the
laundry `order_status`** — a paid order that is still `Diproses` is included.
Cancelled/void transactions are never counted as realised revenue, and unpaid
orders are excluded from it. The label deliberately avoids “Selesai” so it can
never be confused with the laundry `Selesai` pengerjaan status; the underlying
formula is unchanged.

### Overdue rule

An order is **overdue** when all of the following hold:

```
estimated_completed_at IS NOT NULL
AND estimated_completed_at < now()
AND order_status != 'Selesai'
AND sales.status NOT IN ('cancelled', 'canceled', 'void')
```

* Orders **without** an estimate are never overdue.
* Orders already `Selesai` are never overdue, even if the estimate has passed.
* A **cancelled or voided transaction is never overdue**, even while its laundry
  `order_status` is still active. The audited cancellation values are
  `cancelled` / `canceled` (dashboard presentation maps and feature tests) and
  `void` (`SaleFoundationTest` proves a sale can be stored with `status = void`).
  `deleted` is not included: sales are never tombstoned.
* An **unpaid** order is still overdue while its laundry process is active —
  payment status never suppresses overdue.
* Cancellation affects **only** the overdue metric. The total order count and the
  per-status (`Masuk`/`Diproses`/`Siap Diambil`/`Selesai`) counters are unchanged,
  and the order still appears in the list.

The exact same definition is applied to the summary `overdue` count, the
`overdue` filter, the `ontime` filter (its complement), and the `is_overdue` field
on both the list rows and the detail payload.

---

## 4. Tenant isolation

* The business is taken **only** from the shared dashboard context
  (`ShareDashboardBusinessContext` → `dashboard_business`), never from a request
  parameter.
* Every query is scoped with `where('business_id', $activeBusinessId)` plus
  `whereNotNull('order_status')`.
* Detail of a foreign sale — or of an ordinary non-laundry transaction — returns
  `404`.
* The outlet filter only accepts outlets of the active business; a foreign or
  unknown outlet id is dropped (no 500, no leak).
* Customer names, phones, item names and outlets of other businesses never appear
  in the list or the detail payload.

---

## 5. Business-type detection — limitation

The sidebar previously rendered the "Pesanan Laundry" item behind
`@if ($businessContext === 'laundry')`. Audit result:

* **There is no `business_type` column** on `businesses` (or anywhere else).
* `layouts/app/sidebar.blade.php` renders `<x-ui.sidebar>` **without** passing
  `businessContext`, so the component default `'cafe'` is always used — the gate
  was therefore **always false** and the page was unreachable.

Because no trusted, persisted business type exists, DASH-11 makes the menu item
**always visible and wired to `laundry-orders.index`**, so the page is reachable
for every business. This is a navigation convenience, **not** type-based
authorization, and the page itself only shows rows that actually carry a laundry
`order_status`. No `business_type` column was added and the onboarding contract was
not changed.

---

## 6. Read-only limitations

Out of scope for DASH-11 (and deliberately not implemented):

* Creating orders, changing order status, cancelling, editing items/weight.
* Editing payments, HPP or stock.
* WhatsApp notifications, new POS Mobile APIs, schema migrations, sync-protocol
  changes, cashier role/RBAC, DASH-10B1 invitations, production deploy.
* **Status-change history** is not shown because no laundry lifecycle audit table
  exists — no fabricated timeline is produced.

The laundry lifecycle continues to be controlled by the existing POS/sync system.

---

## 7. Tests

`tests/Feature/LaundryOrdersPageTest.php` (32 tests) covers access control, tenant
isolation, list/detail `404` for foreign and non-laundry sales, business switching,
per-status summary, every overdue scenario (no estimate, finished, late active,
late unpaid, cancelled, void, and summary/filter/detail consistency), all filters
(status, payment, outlet, overdue/ontime), search (number/name/phone), date ranges,
25-item pagination with query string, decimal quantity precision, snapshot pricing,
customer identity fallback, revenue separation (cancelled/void/unpaid excluded; a
completed+paid order still `Diproses` is included), order-vs-payment independence,
invalid-parameter safety and cross-tenant data leakage.

`DashboardTest` was updated to assert the laundry menu is now visible and wired to
the route (it previously asserted the always-false gate).

All suites run under the QA-ENV-01 guard (`TestDatabaseGuard` → SQLite `:memory:`).

---

## 8. Next development needs

1. **Persisted business type** (e.g. `businesses.business_type`) to gate laundry
   navigation/modules properly.
2. **Laundry lifecycle audit table** to show real status history.
3. **Outlet/user assignment for laundry** if per-staff monitoring is needed.
4. **Notification/reminder** on overdue orders (outside read-only monitoring).
5. ~~**Role-aware access** for laundry operations once RBAC (DASH-10B2) exists.~~
   Delivered by DASH-10B2: the page is guarded by the `laundry.view` permission
   (`docs/dashboard/DASH10B2_CASHIER_RBAC.md`).
