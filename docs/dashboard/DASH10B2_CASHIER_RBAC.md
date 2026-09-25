# DASH-10B2 — Cashier Role & Role-Based Access Control

Branch: `feat/dashboard-cashier-rbac` · base: `main` (PR #33 invitations, PR #34 laundry, QA-ENV-01)

DASH-10B2 turns the stored membership `role` into an **explicit, server-enforced
authorization contract**. It adds a `cashier` role, restricts what `member` and
`cashier` can reach, and lets an owner change a member's role between
`member` and `cashier`. It does not add a separate dashboard per role, does not
touch subscriptions/billing, and does not change the mobile sync payload
contract for existing `owner`/`member` accounts.

---

## 1. Role model

The role is stored on the `business_user` pivot (`role` varchar). **There is no
global role** — a user may be `owner` of business A, `member` of B and `cashier`
of C, and every request is evaluated against the *active* business only.

| Constant | Value | Meaning |
| --- | --- | --- |
| `Business::ROLE_OWNER` | `owner` | full control of the business |
| `Business::ROLE_MEMBER` | `member` | operational access + reporting, no administration |
| `Business::ROLE_CASHIER` | `cashier` | limited operational access |
| `Business::ROLES` | all three | explicit allowlist |
| `Business::MANAGED_ROLES` | `member`, `cashier` | roles an owner may assign/remove |

Any other value (e.g. `supervisor`) is **unknown** and denied by default: it has
no permission at all. `owner` is the only role with a `*` wildcard, so a business
owner can never lose access to a capability added later.

---

## 2. Permission matrix

Central matrix: `App\Services\Authorization\BusinessPermission`.
Evaluator: `App\Services\Authorization\BusinessAuthorizer`.

Legend: ✓ = allowed, ✗ = HTTP 403.

### Dashboard routes (`routes/web.php`, `auth` + `verified` + business context)

| Route | Permission | owner | member | cashier |
| --- | --- | :-: | :-: | :-: |
| `dashboard` | `dashboard.view` | ✓ | ✓ | ✓ |
| `transactions.index` | `transactions.view` | ✓ | ✓ | ✓ |
| `products.index` | `products.view` | ✓ | ✓ | ✓ |
| `stock.index`, `stock.movements` | `stock.view` | ✓ | ✓ | ✓ |
| `cash.index` | `cash.view` | ✓ | ✓ | ✓ |
| `shifts.index`, `shifts.detail` | `shifts.view` | ✓ | ✓ | ✓ |
| `customers.index`, `customers.detail` | `customers.view` | ✓ | ✓ | ✓ |
| `laundry-orders.index`, `laundry-orders.detail` | `laundry.view` | ✓ | ✓ | ✓ |
| `reports.index` | `reports.view` | ✓ | ✓ | ✗ |
| `outlets.index`, `outlets.detail` | `outlets.view` | ✓ | ✓ | ✗ |
| `users.index`, `users.detail` | `users.view` | ✓ | ✗ | ✗ |
| `users.invitations.store/resend/revoke` | `invitations.manage` | ✓ | ✗ | ✗ |
| `users.members.destroy` | `members.manage` | ✓ | ✗ | ✗ |
| `users.members.role.update` | `roles.manage` | ✓ | ✗ | ✗ |
| `devices.index` | `devices.view` | ✓ | ✗ | ✗ |
| `sync.index` | `sync.view` | ✓ | ✗ | ✗ |
| `subscriptions.index` (DASH-12A) | `subscription.manage` | ✓ | ✗ | ✗ |
| `dashboard.business-context.update` | membership (`BusinessPolicy::view`) | ✓ | ✓ | ✓ |

`subscription.manage` is owner-only and is enforced both by the route middleware
and by `SubscriptionsController` (`Gate::authorize('update', ...)`). The sidebar
"Langganan" entry and both "Kelola Paket" actions (expanded and collapsed) use
this same permission and point at `subscriptions.index`; the "Paket Cloud" card
itself stays visible to every role as read-only information.

### API endpoints (`routes/api.php`, `auth:sanctum`)

| Endpoint | Permission | owner | member | cashier |
| --- | --- | :-: | :-: | :-: |
| `POST /api/auth/login`, `GET /me`, `DELETE /logout` | account only | ✓ | ✓ | ✓ |
| `GET /api/mobile/context` | *(unchanged, read-only bootstrap)* | ✓ | ✓ | ✓ |
| `POST /api/mobile/devices` | `mobile.devices.manage` | ✓ | ✓ | ✗ |
| `POST /api/sync/push` | `sync.push` | ✓ | ✓ | ✗ |
| `GET /api/sync/pull` | `sync.pull` | ✓ | ✓ | ✗ |

A denied mobile role returns `403` with code `MOBILE_ROLE_NOT_SUPPORTED`.

### Cashier API limitation (open item)

The sync payload accepts **every** entity type (`categories`, `products`,
`customers`, `shifts`, `sales`, `sale_items`, `expenses`, `cash_ledger`,
`stock_movements`, `deletions`) with create/update/delete semantics inferred from
`base_sync_version`, and there is no cashier-safe per-entity/per-operation
contract yet. Cashier is therefore **explicitly denied** `sync/push`,
`sync/pull` and device registration. Supporting POS Mobile for cashiers is a
**next integration stage** and is out of scope here. `/api/mobile/context`
remains reachable (read-only bootstrap) and is the only mobile surface a cashier
can still call.

---

## 3. Enforcement

Dashboard: the `business.permission:<permission>` middleware alias
(`App\Http\Middleware\EnsureBusinessPermission`) is attached to **every**
business-scoped route. It resolves the active business from the shared context
(never a request parameter) and aborts 403 when the role lacks the permission.
Hiding a menu item is therefore never the only protection — direct URLs, JSON
drawer endpoints, POST/PATCH/DELETE and the refreshable dashboard are all
guarded.

API: `SyncContextResolver` and `MobileDeviceController` evaluate the same matrix
against the requested `business_id` after the membership check.

A user with **no active business** keeps the pre-existing "empty state" contract
(operational pages render an empty shell, member administration still 403s).

---

## 4. Role management

`PATCH users/members/{userId}/role` (`users.members.role.update`) →
`UpdateBusinessMemberRoleRequest` → `BusinessMembershipService::changeRole()`.

Rules enforced inside a `DB::transaction` with `lockForUpdate` on the pivot row:

* Target must belong to the **active** business, else `404` (no cross-tenant leak).
* New role must be in `Business::MANAGED_ROLES` (`member`, `cashier`) — an
  allowlist; `owner`, `admin`, `supervisor`, … are rejected.
* An `owner` row is **never** changeable (`owner` is outside the allowlist), so
  the last owner can never be demoted and ownership hand-over stays out of scope.
* A user cannot change their own role.
* The current role must itself be a managed role (an unmapped role is not editable).
* Every change writes a `membership_audit_logs` row (`action = role_changed`,
  `actor_id`, `target_user_id`, `target_email`, `metadata.old_role`,
  `metadata.new_role`, `business_id`, `created_at`).

Removal (`DELETE users/members/{userId}`) now accepts both `member` and
`cashier` rows, still never an owner, and still never touches the global account
or its tokens.

Because the role lives on the membership row and is read on every request, a role
change takes effect immediately — including for an already-issued Sanctum token.

---

## 5. UI

* Sidebar items are rendered from the shared `dashboardPermissions` list, not
  from a hard-coded role string.
* Role badges: owner (indigo), member (slate), cashier (emerald), unknown (amber).
* Users page: summary now has a **Kasir** bucket; managed rows show **Ubah Peran**
  (modal with only the allowlisted roles) and **Hapus**.
* Flash messages are validation/authorization aware.
* Invitations still only grant `member`; the role is changed afterwards via
  **Ubah Peran** (DASH-10B1 behaviour preserved).

---

## 6. Regression tests

* `tests/Feature/CashierRbacTest.php` — full dashboard matrix for owner/member/
  cashier/unknown, direct-URL denial, per-business role evaluation, forged
  `business_id`, guest/unverified, management denial, sidebar mirroring.
* `tests/Feature/BusinessRoleManagementTest.php` — role change happy path + audit,
  next-request effect, owner/self protection, allowlist, cross-tenant `404`,
  multi-business isolation, guest/unverified, cashier removal.
* `tests/Feature/MobileRoleAuthorizationTest.php` — cashier denied push/pull/
  device registration, unknown role denied, owner/member contract preserved,
  role change applies to an existing token, mobile-context boundary.

Existing suites (`UsersPageTest`, `BusinessInvitationTest`,
`BusinessMembershipManagementTest`, `DashboardBusinessContextTest`,
`LaundryOrdersPageTest`, `SyncAuthorizationTest`, `MobileSyncContextTest`, …)
continue to pass; the only intentional contract updates are:

1. `UsersPageTest` — a `cashier` row is now its own summary bucket
   (`cashier_count`) instead of `other_role_count`.
2. `DashboardTest` — a user with **no** active business now sees no business
   menu items (deny-by-default), while an owner still sees the full menu.
