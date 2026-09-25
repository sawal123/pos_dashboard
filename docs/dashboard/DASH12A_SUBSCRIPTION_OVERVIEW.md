# DASH-12A — Subscription Overview & Cloud Entitlement

Branch: `feat/dashboard-subscription-overview` · base: `main` (`1a4180f`)

DASH-12A adds a **read-only** subscription overview page for the active business.
It reads existing subscription data only: no schema change, no plan/status/expiry
mutation, no payment flow, and no change to `SyncContextResolver` or the mobile
API contract.

---

## 1. Source of truth

* Table `subscriptions` — **one row per business** (`business_id` is unique,
  cascade on business delete).
* Columns: `plan`, `status`, `starts_at`, `expires_at`.
* Plans that actually exist: **`free`** and **`cloud`**. No other plan is
  invented by the dashboard.
* Model: `App\Models\Subscription` with `isFree()`, `isCloud()`, `isExpired()`,
  `hasCloudAccess()`.

The dashboard never derives entitlement from `plan = cloud` alone — it always
calls `Business::hasCloudAccess()` (which delegates to
`Subscription::hasCloudAccess()`).

### Cloud access rule (unchanged, server-enforced)

```
hasCloudAccess = plan === 'cloud'
                 AND status === 'active'
                 AND (expires_at IS NULL OR expires_at > now())
```

An expiry exactly at `now()` counts as **expired** (`expires_at <= now()`).

---

## 2. Page & navigation

* Route: `GET /subscription` → `subscriptions.index` →
  `App\Http\Controllers\Dashboard\SubscriptionsController@index`.
* Middleware: `auth`, `verified`, `ShareDashboardBusinessContext`.
* Owner-only: reuses the existing `BusinessPolicy::update` rule via
  `Gate::authorize('update', $business)` — the policy is **not modified**.
* Data service: `App\Services\Dashboard\DashboardSubscriptionData`.
* The business always comes from the shared dashboard context
  (`dashboard_business`), never a request parameter.
* Sidebar: the **Langganan** item now links to `subscriptions.index`. The
  "Kelola Paket" / "Tingkatkan Paket" button in the cloud card is now a real link
  to the same page (the previous placeholder toast handler was removed).

The page is strictly read-only — there is no endpoint to change plan, status or
expiry.

---

## 3. Presented states

`DashboardSubscriptionData` resolves exactly one state, always consistent with
`hasCloudAccess()`:

| State | Condition | Cloud access presented |
| --- | --- | --- |
| `none` | no subscription record | Tidak Aktif |
| `free` | `plan = free` | Tidak Aktif |
| `cloud_active` | `plan = cloud` and `hasCloudAccess()` | **Aktif** |
| `cloud_expired` | `plan = cloud`, no access, and expired (`status = expired` or `expires_at <= now()`) | Tidak Aktif |
| `cloud_inactive` | `plan = cloud`, no access, not expired (e.g. `status = inactive`) | Tidak Aktif |
| `unknown` | any other `plan` value | Tidak Aktif |

A past `expires_at` is therefore never shown as an active package. For a Free
plan the plan is shown as `Free`, independent of the subscription `status`
(`status_label` is still displayed as-is).

The page shows: active business name, current plan, actual subscription status,
`starts_at`, `expires_at`, remaining active days (computed only when an
`expires_at` exists) and the real Cloud access status.

---

## 4. Free vs Cloud (audited)

Only behaviour that the server actually enforces is shown:

* **Free** — POS remains usable locally on supported apps; no Cloud
  synchronisation.
* **Cloud** — Cloud synchronisation is available only while the subscription is
  truly active, and access still depends on authentication, business membership
  and an active device (`SyncContextResolver`,
  `MobileDeviceController`, `MobileContextController` all gate on
  `hasCloudAccess()`).

Device counts, transaction limits and paid payment features are **not** shown
because the backend does not enforce them.

---

## 5. Billing limitations (DASH-12B)

DASH-12A is an overview only:

* No payment gateway, checkout, paid invoice, payment webhook or automatic
  renewal.
* No user-initiated subscription creation or plan change.
* The page states that purchase/renewal is not yet available and offers **no**
  fake payment button, checkout link or invoice.

Pricing, billing periods and payment history are deliberately not displayed
because they are not modelled.

---

## 6. Security / isolation

* Guest → login; unverified → verification notice; non-owner member → `403`.
* User with no active business → `403` (no accidental default subscription).
* A subscription of another business is never exposed.
* A forged `?business_id=` cannot change the displayed subscription (the query
  parameter is ignored entirely).

---

## 7. Regression tests

`tests/Feature/SubscriptionsPageTest.php` (22 tests) covers guest/unverified
redirects, owner access, member `403`, no-business `403`, cross-tenant isolation,
forged `business_id`, Free / Cloud active / Cloud expired-by-date / Cloud
inactive / no-record / unknown-plan presentation, `starts_at` + `expires_at`
display, cloud entitlement parity with the model, the exact expiry boundary
(`expires_at == now` expired, `now + 1s` active) using controlled time, the
sidebar and "Kelola Paket" links, absence of fake purchase actions, one business
change not affecting another, and the existing API cloud gate (free business
rejected `CLOUD_SUBSCRIPTION_REQUIRED`, active cloud business allowed).

All suites run under the QA-ENV-01 guard (`TestDatabaseGuard` → SQLite
`:memory:`).
