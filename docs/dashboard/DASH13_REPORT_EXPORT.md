# DASH-13 — Report Export (CSV / XLSX / PDF)

Branch: `cmd/dash13-report-export` · base: `main`

Adds secure, server-generated downloads of the accounting report in three
formats, sharing one dataset with the on-screen page so the numbers can never
drift apart.

---

## 1. Endpoints

| Route name | URI | Format |
| --- | --- | --- |
| `reports.export.csv` | `GET /reports/export/csv` | CSV (streamed) |
| `reports.export.xlsx` | `GET /reports/export/xlsx` | XLSX (OpenSpout) |
| `reports.export.pdf` | `GET /reports/export/pdf` | PDF (DomPDF) |

All three are inside the dashboard group (`auth`, `verified`,
`ShareDashboardBusinessContext`) and carry
`business.permission:reports.view` — the same permission as the reports page.
Cashier and unknown roles therefore cannot export.

Controller: `ReportExportController` → `ReportExportRequest` →
`ReportExportService`.

The tenant is always the shared `dashboard_business` context. A `business_id`
query parameter is **ignored**; a cross-tenant `outlet_id` fails validation
(`302` back with an `outlet_id` error) instead of silently using another
business's outlet.

---

## 2. Architecture & source of truth

```
ReportFilters                 (normalisation + rules + date window + labels)
      │
ReportDatasetBuilder          (all accounting queries → ReportDataset)
      │
ReportExportService           (limits, meta, dispatch)
      │
ReportDocument  ──►  CsvReportFormatter / XlsxReportFormatter / PdfReportFormatter
```

`DashboardReportsData` (the page) and `ReportExportService` (the downloads) both
consume `ReportDatasetBuilder`, so summary, trend, payment and expense figures
are identical by construction. Query logic is not duplicated.

---

## 3. Filters

Shared rules (`ReportFilters::rules()`), used by both the page and the export
request:

| Param | Rule |
| --- | --- |
| `date` | `all` \| `today` \| `7d` \| `30d` \| `custom` |
| `start_date` | `Y-m-d` |
| `end_date` | `Y-m-d` |
| `outlet_id` | integer, must belong to the active business (export only) |

* `outlet_id=all` (what the UI submits for "Semua Outlet") is normalised to
  "no outlet filter".
* Windows use the application timezone (`config('app.timezone')`), the same one
  the page uses: `today` = today 00:00–23:59, `7d` = today−6 days → today end of
  day, `30d` = today−29 days → today end of day.
* `custom` with `start_date > end_date` is **swapped** (same rule as the page),
  never widened to "all time". If both bounds are empty, no date filter applies.
* On the **page**, an invalid filter is dropped and the report still renders (no
  500). On an **export**, invalid input is a validation error and **no file is
  generated** — we never silently export everything.

---

## 4. Dataset & accounting rules

Every export contains:

1. **Report identity** — business name, selected outlet, period label, resolved
   date range, generation timestamp, timezone.
2. **Summary** — total sales, total transactions, estimated gross profit, total
   expenses.
3. **Sales trend** — per calendar date: total sales, transaction count.
4. **Payment breakdown** — payment method, transaction count, total amount,
   percentage (share of transactions).
5. **Expense breakdown** — category, expense count, total amount, percentage
   (share of expense amount).

Inclusion rules (identical to the page):

* Revenue: `sales.status = completed` **and** `payment_status = paid`, dated by
  `sold_at`. Canceled / void / unpaid sales are excluded from revenue.
* Gross profit: the historical `sales.gross_profit` snapshot column. The export
  never reads the current product price/HPP, so past profit stays correct.
* Expenses: `expenses.status = recorded`, dated by `occurred_at`. `void` (and any
  non-`recorded`) expenses are excluded.

---

## 5. Volume limits

Exports are **aggregated** (one row per day / payment method / category), so row
counts are bounded by the date range rather than by transaction volume.

| Format | Cap | Config |
| --- | --- | --- |
| CSV, XLSX | 5 000 rows per section | `reports.export_max_rows` |
| PDF | 1 500 rows per section | `reports.pdf_max_rows` |

PDF is capped lower because DomPDF renders in memory. When a section reaches its
cap the export still completes but carries an explicit notice
("Data dipotong pada batas N baris…"); it never fails silently or emits a
partial file without a warning.

Nothing is written under `public/`: every file is created in the system temp
directory and deleted after the response is sent
(`BinaryFileResponse::deleteFileAfterSend`).

---

## 6. CSV

* Streamed row-by-row with `fputcsv`; flat memory use.
* UTF-8 with a BOM so Excel/LibreOffice detect the encoding.
* Explicit `escape=""` (RFC-4180 quoting) and a consistent `,` delimiter.
* Numbers are written as numbers — no `Rp` prefix or thousand separators — so
  spreadsheets can sum them. Decimal gross profit keeps 2 decimals.
* **Formula injection:** text values (business/outlet names, payment labels,
  expense categories, notes) starting with `=`, `+`, `-`, `@`, TAB, CR or LF are
  prefixed with `'`. Numeric values are never sanitised, so a legitimate negative
  amount keeps its sign.
* `Content-Type: text/csv; charset=UTF-8`, `Content-Disposition: attachment`
  with a slugified, timestamped filename.

## 7. XLSX

* Written with OpenSpout (streaming writer).
* Sheets: **Ringkasan**, **Tren Penjualan**, **Metode Pembayaran**,
  **Pengeluaran**.
* Real numeric cells (with `#,##0` / `#,##0.00` / `0` number formats) so Excel
  can compute; dates are ISO `YYYY-MM-DD` text.
* **Formula injection:** cells are built explicitly — `StringCell` for all
  text and `NumericCell` for numbers. This matters because OpenSpout's
  `Cell::fromValue()` would turn a string beginning with `=` into a real
  formula cell. With explicit string cells, `=SUM(A1:A2)` is stored as inert
  text (no `<f>` element in the workbook).
* Bold headers, section styling and explicit column widths.

## 8. PDF

* Rendered server-side with **DomPDF** (pure PHP, no external browser / no
  headless Chrome), so it works on shared hosting.
* Font: bundled **DejaVu Sans** (UTF-8 safe).
* Remote resource loading is disabled (`setIsRemoteEnabled(false)`), so
  user-controlled text can never make the renderer fetch a URL.
* Template `resources/views/reports/pdf.blade.php`: title + business identity,
  period/outlet/timezone block, summary grid, trend table, payment table, expense
  table, an empty state when there is no data, and the truncation notice.
* Multi-page safe: repeated `<thead>` groups (`display: table-header-group`) and
  `page-break-inside: avoid` on rows/blocks keep headers and subtotals intact.

---

## 9. Libraries added

| Package | Version | Why |
| --- | --- | --- |
| `openspout/openspout` | `^4.32` | XLSX writing (PHP 8.4, shared-hosting friendly) |
| `dompdf/dompdf` | `^3.1` | PDF rendering (pure PHP, bundled fonts) |

Both were added to `composer.json` **and** `composer.lock`. No overlapping export
library is installed. Required PHP extensions: `dom`, `mbstring`, `xmlreader`,
`zip` (see `.github/workflows/tests.yml`).

---

## 10. Frontend

The `Ekspor Laporan` button on the reports page is now a real dropdown with
**Download CSV**, **Download Excel (XLSX)** and **Download PDF**:

* Each item is a real link (works without JS) and its URL is **rebuilt from the
  form's current values** on click, so the export carries the filters the user is
  looking at.
* Keyboard accessible (`aria-haspopup`/`aria-expanded`, Arrow keys, Escape),
  closes on outside click, shows a short "Menyiapkan…" busy state, and registers
  its document listeners exactly once (no duplicates across Livewire
  navigations). Dark mode and responsive layout preserved.

---

## 11. Regression tests

`tests/Feature/ReportExportTest.php` (23 tests) covers: guest/unverified denial,
cashier + unknown-role denial, member allowed, no-business safety, tenant
isolation (A never sees B), forged `business_id`, cross-tenant outlet rejection,
CSV/XLSX/PDF headers + contents, XLSX opened and inspected with the OpenSpout
reader, PDF validity + rendered template, `today`/`7d`/`30d`/`custom`/outlet
filters, empty dataset safety, completed+paid inclusion,
canceled/void/unpaid + non-recorded exclusion, summary/breakdown parity with the
on-screen report, decimal preservation, CSV + XLSX formula-injection defence, and
the export volume cap.

`tests/Feature/ReportsPageTest.php` was updated (test 51) to assert the new
dropdown instead of the old "not available yet" placeholder toast.

Quality gates: `composer ci:check` (Pint + PHPStan level 7 + full suite),
`npm run build`, `git diff --check`.
