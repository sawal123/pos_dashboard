# DASH-10B1 — Secure Member Invitations & Membership Management

Branch: `feat/dashboard-member-invitations` · base: `main` (PR #32 QA-ENV-01 merged)

DASH-10B1 turns the read-only Users page from DASH-10A into an owner-driven
member-management surface. It adds email invitations, membership removal, an
append-only audit trail, and per-request membership enforcement — **without**
introducing a cashier role, without touching the mobile/sync API contract, and
without changing any other dashboard module.

> **Role reality (unchanged):** the only enforced authorization contract is
> still `owner` (`BusinessPolicy`). A `member` currently still reaches the
> member-visible dashboard pages via the existing generic `BusinessPolicy::view`
> rule. This task does **not** add a cashier role and does **not** claim a
> complete RBAC model — see "DASH-10B2" below.

---

## 1. Data model

### `business_invitations`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `business_id` | FK → `businesses.id`, cascade | |
| `invited_by` | FK → `users.id` nullable, null on delete | actor who sent it |
| `email` | string | lower-cased/normalised |
| `role` | string, default `member` | only `member` is accepted |
| `token_hash` | string(64) **unique** | SHA-256 of the plaintext token |
| `status` | string(20), default `pending` | `pending` / `accepted` / `expired` / `revoked` |
| `active_key` | string(64) **nullable unique** | one *active* invitation per business+email |
| `expires_at` | timestamp | TTL = 7 days |
| `accepted_at`, `revoked_at` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique `token_hash`, unique `active_key`, `(business_id, status)`,
`(business_id, email)`.

The `active_key` is a deterministic hash set only while an invitation is
`pending` (NULL otherwise), so the **database itself** guarantees a single
active invitation per business + email — multiple NULLs are allowed by MySQL and
SQLite. A revoked/expired invitation clears its key, allowing a fresh invite.

### `membership_audit_logs` (append-only)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `business_id` | FK → `businesses.id`, cascade | |
| `actor_id` | FK → `users.id` nullable | who acted |
| `target_user_id` | FK → `users.id` nullable | affected user (if any) |
| `target_email` | string nullable | invited/removed email |
| `action` | string(50) | see below |
| `metadata` | json nullable | **whitelisted** keys only |
| `created_at` | timestamp | no `updated_at` (immutable) |

Actions: `invitation_created`, `invitation_resent`, `invitation_revoked`,
`invitation_accepted`, `membership_removed`. Only
`role`, `invitation_id`, `was_verified` and `status` are persisted in metadata —
a plaintext token, password or any other secret can never reach the log.

---

## 2. Invitation flow

1. **Create** — `POST /users/invitations` (`users.invitations.store`).
   Owner-only. Email is normalised; the role is fixed to `member`. A pending
   invitation for an existing member email or for an email that already has a
   pending invite is rejected.
2. **Email** — `BusinessInvitationMail` carries the one-time link
   `/invitations/{token}`. It is **queued after the database transaction
   commits** and implements `ShouldQueue` + `ShouldBeEncrypted`: the encrypted
   payload never exposes the plaintext token in the `jobs`/`failed_jobs` tables,
   and a transient delivery failure is retried (3 attempts, `[10, 60, 300]`
   backoff) instead of surfacing as a 500. The plaintext token is never
   persisted in the database, session, queue payload or logs.
3. **Open** — `GET /invitations/{token}` (`invitations.show`) is public so a
   brand-new user can be routed into Fortify registration. The token is **not**
   stored in the session (see security table); a new user reopens the emailed
   link after registering and verifying. Authenticated users see the accept
   button; unverified users are redirected to the verification notice; a
   mismatched email receives `403`.
4. **Accept** — `POST /invitations/{token}/accept` (`invitations.accept`),
   requiring `auth` + `verified`. Validates token, email match, status and
   expiry, then, inside a transaction with a row lock, attaches the `member`
   membership (idempotently) and marks the invitation accepted. The newly joined
   business becomes the active dashboard context.
5. **Resend** — `POST /users/invitations/{id}/resend` rotates the token and
   extends the expiry (pending only).
6. **Revoke** — `POST /users/invitations/{id}/revoke` marks it revoked (pending
   only).

Acceptance supports **existing accounts** (login then accept) and **new
accounts** (register via Fortify, verify the email, then accept).

---

## 3. Membership removal

`DELETE /users/members/{userId}` (`users.members.destroy`) — owner-only.

* Only rows with role `member` may be removed. An `owner` can never be removed
  here, so a business can never lose its last owner.
* The **global user account is never deleted** and **no tokens are revoked**.
* Membership on other businesses is untouched.
* After removal, access to that business is denied **per request**:
  `ShareDashboardBusinessContext`/`DashboardBusinessContext` re-evaluate
  membership for the dashboard (switching back returns `403`), and
  `SyncContextResolver` returns
  `403 BUSINESS_ACCESS_DENIED` for the removed business while still allowing any
  other business the user legitimately belongs to.

---

## 4. Security protections

| Concern | Control |
| --- | --- |
| Authorization | `BusinessPolicy::manageInvitations` / `manageMembers`, enforced via Form Request `authorize()` and re-checked in controllers. |
| Tenant scoping | The business always comes from the active-business context (`dashboard_business` attribute / session), never from a request parameter. Cross-tenant invitation actions return `404`. |
| Token secrecy | Random 64-char token, stored as SHA-256 only; single-use; TTL 7 days; revocable; never written to logs or the audit trail. |
| Session | The token-bearing URL is never persisted: invitation routes skip `_previous.url` / `url.intended` bookkeeping, so the database session store holds no plaintext token. |
| Create race | The unique `active_key` constraint is kept; a duplicate is translated into an `email` validation error instead of a 500. |
| Replay | Acceptance runs in a transaction with `lockForUpdate` on the invitation row; a second attempt fails and no duplicate membership is created. |
| Email ownership | Acceptance requires an authenticated user whose **verified** email matches the invitation exactly. |
| Least privilege | Acceptance only ever grants `member`; it cannot change credentials or other memberships and cannot make the user an owner. |
| Throttling | Named rate limiters (10/min, keyed per actor/IP) on create, resend, revoke, remove and accept. |
| Mail | Queued after commit, encrypted at rest (`ShouldBeEncrypted`) and retried on failure; the plaintext token never reaches the database, session, queue payloads or logs. |

---

## 5. UI (Users page)

Extends the existing `users/index.blade.php` and keeps its responsive layout,
dark mode, pagination and detail drawer:

* **Undang Anggota** header button → invite modal (email + fixed `member` role).
* **Undang Anggota** panel listing invitations with status badges
  (Menunggu / Diterima / Kedaluwarsa / Dibatalkan) and per-pending
  **Kirim ulang** / **Batalkan** actions.
* **Hapus** action on `member` rows (table and mobile cards) → confirmation
  modal posting a `DELETE`.
* Clear success and error flash messages.
* All actions are owner-only on the server; the UI only mirrors that.

---

## 6. Regression tests

* `tests/Feature/BusinessInvitationTest.php` — creation authorization, hashed
  single-use token, token-never-persisted, encrypted queue payload + retry
  configuration, delivery failure + resend, duplicate/existing-member/role
  validation, `active_key` concurrency (validation error, not 500), resend,
  revoke, cross-tenant `404`, unknown token, token-never-in-session (array and
  database drivers), guest/login/verification gates, email mismatch,
  existing-user and new-user (Fortify) acceptance, replay protection, expiry,
  revocation, credential/other-business safety, and creation throttling.
* `tests/Feature/BusinessMembershipManagementTest.php` — removal authorization,
  owner protection + last-owner invariant, cross-business/non-member `404`,
  other-business membership integrity, dashboard switch denial, and sync denial
  for the removed business while another business still works.

Both suites run under the QA-ENV-01 guard (`TestDatabaseGuard`), i.e. only
SQLite `:memory:` for the standard suite.

---

## 7. DASH-10B2 (out of scope here)

The following are deliberately **not** delivered by DASH-10B1:

1. **Cashier role + RBAC** — an explicit permission set, policy methods,
   dashboard route guards and API scope/authorization with deny-path tests.
2. **Role management** — changing an existing member's role against an
   allowlist (and the enforced behaviour that role implies).
3. **Ownership hand-over / transfer** — an explicit, confirmed flow.
4. **Access restriction** — constraining what a `member` can currently reach on
   the dashboard and through `/api/*` (today `member` still has the generic
   `view`).
5. **Deactivation** — needs an `is_active` (or equivalent) column enforced in
   login, Sanctum checks and dashboard access.
6. **Invitation housekeeping** — a scheduled job to persist `expired` status,
   and pagination/filtering if invitation volume grows.
7. **Notification UX** — in-app notifications and richer delivery status.
