# DASH-14 — Persisted Business Type & Dynamic Navigation

Status: implemented on branch `codex/dash14-business-type` (PR: [CODEX] DASH-14 — Business Type & Navigation).
Scope: dashboard + one additive field on an existing mobile API payload. Read-only w.r.t. POS Mobile.

## 1. Canonical business types

`App\Enums\BusinessType` is the **single source of truth** for the allowlist, the display
label, legacy/alias normalization, validation and the unknown state.

| Internal (stored) value | Display label           | POS Mobile template label |
| ----------------------- | ----------------------- | ------------------------- |
| `cafe`                  | Cafe / UMKM             | Cafe / UMKM               |
| `laundry`               | Laundry                 | Laundry                   |
| `grosir`                | Grosir / Toko Kelontong | Grosir / Toko Kelontong   |

Stored values are always canonical. Display labels are presentation only and are **never**
persisted.

## 2. Legacy aliases

`BusinessType::tryFromInput()` normalizes *explicit* legacy/alias input. Aliases are matched
case-insensitively after trimming:

- `cafe`, `cafe / umkm`, `umkm`, `restoran`, `restaurant` → `cafe`
- `laundry` → `laundry`
- `grosir`, `grosir / toko kelontong`, `toko kelontong`, `retail` → `grosir`

Normalization happens **on read** (`Business::businessType()`,
`Business::normalizedBusinessType()`, the mobile context payload). The raw stored column is
never rewritten by a read.

## 3. NULL and unknown handling

- `businesses.business_type` is **nullable with no default**. A business whose type is not
  known stays `NULL`.
- `NULL`, empty/whitespace input and any unrecognized string (e.g. `salon`) all resolve to
  **unknown** (`null`). Unknown is deliberately kept separate from `cafe` — nothing guesses
  a type from the business name, product names, categories, the presence of laundry
  transactions, or the POS Mobile default menu.
- Display label for unknown: `Belum ditentukan`.
- **Audit note (POS Mobile):** `src/data/businessTemplates.js: normalizeBusinessType()`
  defaults unknown input to `Cafe / UMKM` *on the device*. The server intentionally does
  **not** copy that behaviour: an absent/unknown value stays unknown server-side so the
  dashboard never presents a business as Cafe by accident. POS Mobile was not modified.

## 4. Migration of existing businesses

`2026_09_25_000003_add_business_type_to_businesses_table.php` adds a nullable string column
(compatible with MySQL and SQLite) and does **nothing else**: no default, no backfill, no
data rewrite. Every pre-existing business remains `NULL` until an owner explicitly chooses a
type. Adding a type never deletes products, sales, prices, stock, laundry data or the
subscription.

## 5. Owner setup flow

- Route: `GET /business-settings` (`business-settings.edit`) and
  `PATCH /business-settings/business-type` (`business-settings.business-type.update`).
- Guarded by the DASH-10B2 permission `business.settings.manage` (owner-only; `owner` is the
  only role holding the wildcard `*`). Member, cashier and unknown roles receive **403**.
- The active business is always resolved from the shared, session-backed
  `DashboardBusinessContext` — never from a request parameter. A forged `business_id` is
  ignored and cannot target another tenant.
- UI (`resources/views/business-settings/index.blade.php` +
  `components/business-settings/business-type-form.blade.php`): current type (or
  `Belum ditentukan`), the three options with descriptions, a server-validated form, and a
  **confirmation checkbox required only when changing an already-set type**.
- Validation: `business_type` is `required` and must be a canonical value
  (`Rule::in(BusinessType::values())`). Arbitrary or alias strings are rejected at the
  endpoint — aliases only exist for normalizing already-stored explicit values.
- The unknown-type sidebar prompt links owners here.

## 6. Navigation rules

Business type decides **relevance**; `BusinessPermission` decides **authorization**.

| Active business type | Menu behaviour |
| -------------------- | -------------- |
| `laundry`            | Shows **Pesanan Laundry** (+ Transaksi, Produk & Layanan, Stok, Kas, Shift, Pelanggan… per permission) |
| `cafe`               | Hides Pesanan Laundry; keeps Transaksi, Produk & Layanan and the rest per permission |
| `grosir`             | Hides Pesanan Laundry; keeps Transaksi, Produk & Layanan, Stok and the rest per permission |
| unknown (`NULL`)     | Safe general navigation (no laundry item, no Cafe pretence); owners additionally get a "Tipe bisnis belum ditentukan" prompt linking to the setup page |

The sidebar (`resources/views/components/ui/sidebar.blade.php`) derives
`$activeBusinessType` from the shared `dashboardBusinessType` (which comes from
`currentBusiness->business_type`) and gates the laundry item on
`$can(BusinessPermission::LAUNDRY_VIEW) && $activeBusinessType === 'laundry'`. The
`data-business-context` attribute now reflects the real type (or `unknown`).

## 7. RBAC vs business type (separate concerns)

- Business type **never** grants or removes a permission. `business.permission:*` middleware
  remains on every route and is unchanged.
- Hiding a menu item is never the only control. Documented direct-URL behaviour:
  **a direct URL is governed by the permission matrix, not by the business type.** A cafe
  owner (or a cashier, who holds `laundry.view`) can still open `/laundry-orders` and see the
  (possibly empty) page; an unknown role is denied with 403 even on a laundry business.
- Historical laundry data is never deleted or hidden at the database level when a business
  changes type — only menu relevance changes.

## 8. Switching businesses

`dashboardBusinessType` is resolved per request from the active business, so switching the
active business immediately updates the navigation type **and** the role/permissions
(`dashboardBusinessRole` / `dashboardPermissions`). No cache of a previous business's type is
carried across: e.g. A=cafe, B=laundry, C=grosir shows the correct menu for each active
business, and `NULL` never causes a 500.

## 9. Mobile API compatibility

`GET /api/mobile/context` gains exactly **one** additive field per business:

```json
{
  "id": 1,
  "name": "Kopi Sore",
  "business_type": "cafe",          // "cafe" | "laundry" | "grosir" | null
  "subscription": { "plan": "cloud", "status": "active" },
  "cloud_access": true,
  "outlets": [ { "id": 1, "name": "Outlet Utama", "code": "OUT-1", "status": "active" } ],
  "device_context": null
}
```

- `business_type` is canonical and `null` when unknown. Legacy stored values are normalized
  in the payload.
- No existing key was removed or renamed, and a client that does not know the new field is
  unaffected (verified by regression test asserting the exact key set).
- Mapping for POS Mobile: `cafe` → `Cafe / UMKM`, `laundry` → `Laundry`,
  `grosir` → `Grosir / Toko Kelontong`, `null` → no type chosen (do not guess).

## 10. Business type sync limitations

- There is currently **no upload path** for a business type chosen offline on POS Mobile.
  The device store keeps the type locally (`businessStore.setBusiness`,
  `normalizeBusinessType`) and nothing pushes it to the dashboard. DASH-14 does **not**
  invent a two-way sync: the mobile sync protocol (`sync/push`, `sync/pull`,
  `base_sync_version`, `SyncContextResolver`, device registration) is unchanged, and
  `business_type` is not part of any sync change payload.
- Therefore an owner typically sets the dashboard type via the new owner setup page. If a
  future sync of the business type is agreed, it must be specified separately.
- Cashier sync restrictions from DASH-10B2 still apply: a business type (e.g. `laundry`)
  does not open `sync.push` / `sync.pull` for a cashier.

## 11. Out of scope (unchanged)

No DASH-13 report export, product/cash CRUD, billing, device registration, dashboard
redesign, POS Mobile implementation change, sync protocol overhaul, `business_type` column on
other tables, or production deployment.
