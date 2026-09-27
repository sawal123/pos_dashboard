# DASH-17 — Device Registration & Management

Branch: `cmd/dash17-device-registration` · base: `main`

Enables owner-only device registration and management from the `/devices`
dashboard using the existing `devices` table and the existing mobile
sync/registration contract. No second device model, no token or sync-protocol
change.

---

## 1. What this adds

* `devices.manage` — new owner-only dashboard permission.
* Dashboard device **pre-registration** (name, identifier, outlet, platform,
  optional notes) with server-side validation.
* Safe metadata editing (name + notes).
* Activate / deactivate (no hard delete).
* Replaces the `alert()` placeholder with a real, accessible modal.

Routes (all inside `auth`, `verified`, `ShareDashboardBusinessContext`):

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `devices` | `devices.store` | `business.permission:devices.manage` |
| PATCH | `devices/{deviceId}` | `devices.update` | `business.permission:devices.manage` |
| PATCH | `devices/{deviceId}/status` | `devices.status.update` | `business.permission:devices.manage` |

Reading stays on `devices.view`.

---

## 2. Dashboard registration flow

1. Owner opens **Daftarkan Perangkat** (header or empty-state CTA) → modal.
2. Fills name, identifier (≤100 chars), outlet, optional platform (≤50), notes.
3. `StoreDeviceRequest` authorizes `devices.manage`, resolves the active
   business from the shared context, and validates that the outlet belongs to
   that business.
4. `DeviceManagementService::register()` resolves by
   `(business_id, identifier)`:
   * **not registered** → create (active, `registered_at = now()`,
     `last_seen_at = null`);
   * **registered on the same outlet** → return the existing device unchanged;
   * **registered on another outlet** → validation error (outlet mismatch).
5. Redirect back to `/devices` with a success/info flash message; the listing
   reflects the change immediately.

---

## 3. Pre-registration vs pairing

Dashboard registration is a **pre-registration by identifier**. It records that
a device *should* exist for an outlet. It is deliberately **not**:

* secure pairing,
* hardware attestation,
* proof that the physical device is connected.

Therefore `last_seen_at` is never set by the dashboard. A device only appears as
"seen" once it actually calls the mobile API (`POST /api/mobile/devices`) or the
sync endpoints, which stamp `last_seen_at`. The UI never labels an `active`
device as "Online".

---

## 4. Identifier contract

* `identifier` is required, ≤100 characters (identical to the mobile API's
  `device_identifier` rule), trimmed, and immutable after creation.
* Uniqueness is per business (`unique(business_id, identifier)`), the existing
  DB constraint.
* The same identifier may be used by a different business.
* Identifier uniqueness is intentionally **not** a plain validation rule: a
  duplicate on the same outlet is resolved idempotently, and a duplicate on a
  different outlet is rejected with an outlet-mismatch validation error rather
  than a 500.

---

## 5. Device status lifecycle

* States: `active` / `inactive` (values already understood by the mobile API and
  sync resolver).
* Deactivation keeps the identifier, sync history, sales history and any Sanctum
  tokens — nothing is deleted or revoked.
* The mobile device registration API and both sync endpoints already reject
  inactive devices (`DEVICE_INACTIVE`), so a deactivated device is cut off from
  push/pull and re-registration immediately.
* Reactivation is only reachable through an explicit owner action.
* Devices are never hard-deleted from the dashboard.

---

## 6. Outlet rules

* A device is bound to exactly one outlet; an outlet from another tenant is
  rejected at validation.
* **The outlet is immutable from DASH-17.** Moving a device between outlets
  would change which outlet's data it pulls and would invalidate the
  historical outlet scope of its sync and sales history, so the dashboard does
  not allow it. A deliberate re-registration on a different outlet is rejected
  as an outlet mismatch rather than silently relocating the device.
  (Reassigning an outlet safely — e.g. retiring a device and provisioning a new
  one — is a future task.)

---

## 7. Cloud entitlement

* `POST /api/mobile/devices` and the sync endpoints still require an active
  cloud subscription (`CLOUD_SUBSCRIPTION_REQUIRED`). This is unchanged.
* Dashboard pre-registration is **not** gated on cloud entitlement: an owner may
  pre-register devices before/without cloud. Such a device simply cannot sync
  until the business has cloud access. This mirrors the existing split (the
  dashboard is an owner administration surface; the mobile API is the runtime
  contract).

---

## 8. Access rights

| Action | Permission | owner | member | cashier | unknown |
| --- | --- | --- | --- | --- | --- |
| View `/devices` | `devices.view` | ✅ | ❌ | ❌ | ❌ |
| Register / edit / activate | `devices.manage` | ✅ | ❌ | ❌ | ❌ |
| Mobile `POST /api/mobile/devices` | `mobile.devices.manage` | ✅ | ✅ | ❌ | ❌ |
| Sync push/pull | `sync.push` / `sync.pull` | ✅ | ✅ | ❌ | ❌ |

`devices.manage` is owner-only (via the owner `*` wildcard) and separate from
`mobile.devices.manage`, so the existing mobile member access is preserved
exactly. `business_type` never grants access. Every dashboard mutation is
enforced server-side; a foreign `deviceId` returns 404.

---

## 9. Idempotency & concurrency

* Re-submitting the same identifier never creates a duplicate and never
  overwrites the existing row's name/notes/status.
* A concurrent registration race is handled by catching
  `UniqueConstraintViolationException` and resolving the winner's row — never a
  500. (Regression-tested with a SQLite trigger that forces the unique error.)
* The dashboard submit button is disabled on submit; server-side uniqueness is
  the real guard.

---

## 10. Impact on existing sync

* No change to the push/pull payload, base sync version, conflict resolution,
  token handling, or the cashier-safe boundary (INT-01 remains future work).
* A device created from the dashboard is a normal `devices` row, so the mobile
  API recognises it as soon as the identifier **and** outlet match — the same
  resolution path already covered by `MobileSyncContextTest`.
* No new sync token or pairing protocol is introduced.

---

## 11. Security limitations found (out of scope)

These are documented, not fixed here (fixing them would expand DASH-17's scope):

1. **No device attestation / secret.** The mobile API resolves a device purely
   by the caller-supplied `device_identifier` + `outlet_id`, covered by an
   owner/member bearer token. A leaked token can register or resolve any
   identifier for an owned outlet. Recommend a separate security task for
   device-scoped credentials.
2. **Dashboard pre-registration is not proof of possession.** Anyone with owner
   access can pre-register an arbitrary identifier; it only becomes "seen" when
   a device actually authenticates.
3. **`last_seen_at` reflects API contact, not physical presence.** Both the
   mobile registration API and sync stamp it; it is not a heartbeat and must not
   be read as liveness.

---

## 12. Tests

`tests/Feature/DeviceManagementTest.php` (28 tests): owner registration,
member/cashier/unknown denial, guest/unverified denial, active-business tenant,
forged `business_id`, cross-tenant outlet, duplicate identifier (same/other
business), outlet mismatch, no auto-reactivation, activate/deactivate,
non-owner denial, foreign 404, concurrency race, `last_seen_at` honesty,
inactive rejected by sync push/pull, mobile API recognition, cashier denial,
cloud entitlement, filters/pagination, modal replacement and no cross-tenant
leak. `DevicesPageTest` test 34 updated from the placeholder to the real modal.

Regression suites: `DevicesPageTest`, `DeviceFoundationTest`,
`MobileRoleAuthorizationTest`, `MobileSyncContextTest`, `SyncAuthorizationTest`,
`SyncPushTest`, `SyncPullTest`, `CashierRbacTest`, then the full suite.

Quality gates: `composer ci:check` (Pint + PHPStan + full suite), `npm run build`,
`git diff --check`.
