# DASH-10 — Users & Cashiers

Branch: `feat/dashboard-users-cashiers` · base: `main` (`5fc6b02`, PR #29 merged)

DASH-10A (this change) is **read-only** monitoring of business members. It does
**not** create, invite, modify, deactivate or delete users, does **not** add a
cashier role, and does **not** touch Fortify, Sanctum, `routes/api.php`, the sync
contract, the database schema, the Customer module, the Outlet module, the Shift
module, or POS Mobile.

> **Status:** cashier support is **not implemented**. Only `owner` and `member`
> have proven behaviour, and only `owner` has an enforced dashboard authorization
> contract. Do not treat any "Kasir" label as a working access level until the
> DASH-10B items below are delivered.
>
> **Update (DASH-10B1 / DASH-10B2):** invitations (`docs/dashboard/DASH10B1_MEMBER_INVITATIONS.md`)
> and the cashier role + RBAC matrix (`docs/dashboard/DASH10B2_CASHIER_RBAC.md`)
> are now delivered. This document is retained as the historical DASH-10A record.

---

## 1. Current membership structure

Schema (`database/migrations/2026_08_21_105944_create_business_user_table.php`):

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `business_id` | FK → `businesses.id`, cascade delete | |
| `user_id` | FK → `users.id`, cascade delete | |
| `role` | string, **required** | no enum, no check constraint |
| `created_at` / `updated_at` | timestamps | pivot join date |

* Unique constraint `(business_id, user_id)` — a user has **at most one row per
  business**, so one role per business.
* The role has no default and no database-level allowlist. Membership requires an
  explicit role (`tests/Feature/BusinessMembershipTest.php::test_membership_requires_explicit_role`).

Relations:

* `User::businesses()` — `belongsToMany(Business::class)->withPivot('role')->withTimestamps()`.
* `Business::users()` — `belongsToMany(User::class)->withPivot('role')->withTimestamps()`.
* `Business::owners()` — `users()->wherePivot('role', 'owner')`.
* `User::belongsToBusiness()`, `User::ownsBusiness()`, `Business::hasMember()`,
  `Business::isOwnedBy()`.

Active business resolution is session-backed and validated against membership
(`App\Services\Dashboard\DashboardBusinessContext`, session key
`dashboard.current_business_id`). It falls back to the lowest business id when the
session is missing or stale, and clears the session when the user has no business.

## 2. Role & permission matrix actually enforced

| Capability | owner | member | any other role | Enforced by |
| --- | --- | --- | --- | --- |
| View a business | ✅ | ✅ | ✅ (if a member row exists) | `BusinessPolicy::view` (`Business::hasMember`) |
| Update a business | ✅ | ❌ | ❌ | `BusinessPolicy::update` (`Business::isOwnedBy`) |
| Delete a business | ✅ | ❌ | ❌ | `BusinessPolicy::delete` |
| View business members (`/users`) | ✅ | ❌ (403) | ❌ (403) | `BusinessPolicy::viewMembers` (`Business::isOwnedBy`) |
| Switch active business | ✅ | ✅ | ✅ | `DashboardBusinessContext::switchTo` + `view` |

There is **no** role that grants a subset of operating permissions (no cashier, no
supervisor, no manager). An unrecognised `role` string grants only the generic
member-level `view` and is otherwise treated as a plain membership row; the UI
labels it with its raw formatted value and counts it under "Peran Lain".

## 3. Fields and relations that do **not** exist yet

These are deliberately **not** shown by DASH-10A:

* No `users.is_active` column — accounts cannot be enabled/disabled.
* No `users.last_login_at` (and no heartbeat/activity source) — no "online now" or
  "last seen" metric can be produced.
* No operator/user FK on `shifts` or `sales` — there is no way to attribute a
  shift or a transaction to a user, so there is no "outlet assignment",
  "transactions handled", "cashier sales total" or "cashier identity".
* No outlet-to-user assignment table.
* No invitation table, no invitation status, no pending-membership model.
* No audit/activity log for membership or role changes.

## 4. What DASH-10A delivers

Routes (`routes/web.php`, inside the `auth` + `verified` + `ShareDashboardBusinessContext` group):

* `GET /users` → `users.index` → `App\Http\Controllers\Dashboard\UsersController@index`
* `GET /users/{userId}/detail` → `users.detail` → `UsersController@detail`

Data service: `App\Services\Dashboard\DashboardUsersData` (`PER_PAGE = 25`).

Authorization (`UsersController::authorizeOwnerAccess`):

* Business is taken **only** from the request attribute set by the shared
  middleware — never from a query/body parameter.
* `business === null` → `403`.
* Otherwise `Gate::authorize('viewMembers', $business)` → non-owner `403`.
* Detail is scoped with `where('business_user.business_id', …)` **and**
  `where('users.id', …)`; a foreign or unknown id → `404`.
* The detail payload is an explicit allowlist, so `password`, `remember_token`,
  `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`,
  tokens and any other-business membership are never serialised.

List filters: `q` (name/email, max 100), `role` (actual role value, max 50),
`verification` (`verified` | `unverified`), server-side pagination of 25 with
`withQueryString()`. Invalid parameter types are dropped by validation and never
produce a 500.

Sidebar: the "Pengguna / Kasir" item is rendered only when the active business role
is `owner`. This is a UI convenience; the server-side `viewMembers` check is the
real enforcement boundary.

### Metric sources (all tenant-scoped)

| Metric | Source |
| --- | --- |
| Total anggota | `COUNT(*)` of `business_user` for the active business |
| Pemilik | `business_user` rows with `role = 'owner'` |
| Anggota | `business_user` rows with `role = 'member'` |
| Peran lain | `business_user` rows with any other `role` |
| Email terverifikasi | joined `users.email_verified_at IS NOT NULL` |
| Email belum terverifikasi | joined `users.email_verified_at IS NULL` |
| Tanggal bergabung | `business_user.created_at` for the active business |
| Akun dibuat | `users.created_at` (detail only) |

## 5. Risk of assigning a cashier role without RBAC

Adding `role = 'cashier'` today would grant **nothing extra** (the generic member
`view` at most) but would also **not restrict anything**: the user could still
reach every member-visible dashboard page and every Sanctum-protected API route.
A "cashier" whose role changes no behaviour is misleading and creates a false
sense of least privilege. Before any cashier role exists it must come with:

* A defined permission set and a policy/policy-method that enforces it server-side.
* Route-level and API-level enforcement (not menu hiding).
* Tests proving both the allow and the deny paths.

## 6. DASH-10B — required before user/cashier management is complete

1. **Invitations** — create/invite/re-invite/revoke, with status and expiry.
2. **Account provisioning** — accept-invite flow, password set, email verification.
3. **Role management** — assign/change role with validation against an allowlist.
4. **Cashier role + RBAC** — explicit permission set, dashboard route guards, API
   scope/authorization, and deny-path tests.
5. **Access restriction** — constrain what `member` and `cashier` can reach on the
   dashboard and through `/api/*`.
6. **Membership management** — remove a member, transfer/hand over ownership.
7. **Deactivation** — requires an `is_active` (or equivalent) column and its
   enforcement in login, Sanctum token checks and dashboard access.
8. **Audit log** — append-only record of invitations, role changes, removals and
   access revocations (actor, target, business, before/after, timestamp).
9. **Last-owner and multi-business protection** — see below.

## 7. Invitation mechanism recommendation

* An invitation is bound to the **email address**, not to an existing user id.
* The invitee must prove ownership of that email (verification link / one-time
  token) before the membership row is created — never attach a business to an
  unconfirmed email.
* An invitation may target an email that already has an account (it becomes a new
  membership for that user) or one that does not (account creation then attach).
* Invitations must expire, be single-use, and be revocable by an owner.
* Accepting an invite must not let the inviter silently change an existing user's
  global credentials or other businesses.

## 8. Last-owner and multi-business protection

* A business must always retain **at least one** `owner`. Block demotion, removal
  or self-removal when it would leave zero owners.
* Ownership hand-over should require an explicit confirmation and, ideally, the
  target owner's acceptance.
* A user may belong to several businesses with **different** roles; every
  authorization decision must be evaluated against the **active** business only
  (never a global "is owner somewhere"). DASH-10A already displays and authorizes
  per active business — the same rule must hold for DASH-10B mutations.

## 9. Audit log requirements

Every membership or role mutation must be recorded with: actor user, target user,
business, previous role, new role, action (`invite`, `accept`, `role_change`,
`remove`, `revoke`), timestamp, and request origin. The log must be append-only
and readable by owners, and must survive the removal of the membership it
describes.

## 10. Regression tests

`tests/Feature/UsersPageTest.php` covers guest redirect, unverified redirect,
owner access, non-owner `403`, no-business `403`, cross-business isolation,
foreign detail `404`, business switching, per-active-business role display,
summary buckets (including an unknown role not counted as member), verification
counts, name/email search, role and verification filters, 25-item pagination with
preserved query string, invalid-parameter safety, sensitive-attribute redaction,
id-manipulation `404`, owner-only sidebar visibility, and unchanged
membership/API behaviour.
