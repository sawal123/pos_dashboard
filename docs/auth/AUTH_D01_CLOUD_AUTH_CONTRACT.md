# AUTH-D01 — Cloud Authentication Audit & Contract

**Repository:** `sawal123/pos_dashboard`
**Baseline:** `origin/main` at `8be54f5693b86d062a1a8ef3351066bee3e0c8cb` (merge PR #88)
**Audit date:** 2026-10-24 (Asia/Jakarta)
**Scope:** audit, security analysis, and implementation contract only. No application behavior is changed by AUTH-D01.

> **Reading rule.** `EXISTING` means verified in the current source tree. `PROPOSED` means a target contract for a later implementation task. `SECURITY FINDING` is an observed risk or incompatibility. `OPEN DECISION` requires a product/security decision before implementation. The examples in this document are not implemented endpoints.

## 1. Executive Summary

### Existing

- The web dashboard uses Laravel Fortify with the `web` session guard, email verification, TOTP 2FA, recovery codes, and passkeys. Dashboard routes are protected by `auth` and `verified`; platform-admin routes additionally require the platform-admin flag and confirmed 2FA (`config/fortify.php:163-175`, `routes/web.php:89-95,292-294`, `routes/settings.php:8-22`).
- The mobile/cloud API has four authentication endpoints: `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`, and `DELETE /api/auth/logout` (`routes/api.php:14-25`). Login issues a Sanctum personal access token with the `mobile` ability. Logout revokes only the current token.
- API registration currently marks the email verified, creates a business, owner membership, `Outlet Utama`, and an active Free subscription in one transaction, then immediately issues a bearer token (`app/Http/Controllers/Api/AuthController.php:23-91`). No email OTP or Google OIDC exists.
- API login rejects unverified accounts and accounts with confirmed 2FA, but it has no API 2FA challenge. A confirmed-2FA user receives `403 TWO_FACTOR_REQUIRED` and cannot complete mobile login using the current API contract (`AuthController.php:95-125`).
- Tenant authorization is membership-based. `BusinessAuthorizer` re-reads the `business_user` pivot on each request; roles are `owner`, `member`, and `cashier`, and unknown roles deny by default (`Business.php:31-56`, `BusinessAuthorizer.php:20-91`). Cloud entitlement is separately derived from the business subscription (`Subscription.php:72-106`).

### Target

The recommended target is a versioned API namespace, `/api/v2/auth`, so the current mobile registration response is not silently changed. Manual registration becomes a pending account plus email OTP; Google OIDC accepts a backend-verified Google ID token and never adds a POS OTP; explicit account linking requires proof of the existing account; Sanctum issuance occurs only after verification and any required 2FA; business/outlet/Free provisioning is idempotent.

### Highest-priority findings

1. **High — current API registration auto-verifies email and issues a token before proof of mailbox ownership.** This conflicts with the requested OTP policy.
2. **High — Sanctum token expiration is disabled (`config/sanctum.php:45-57`), so token lifetime and global revocation are not bounded by configuration.**
3. **High — current API 2FA is fail-closed but incomplete for mobile:** it blocks token issuance without offering a challenge completion contract.
4. **High — Google identity storage and token verification are absent; matching by email must not be introduced as a shortcut.
5. **Medium — current registration is not request-idempotent.** A lost response followed by a retry returns a duplicate-email validation failure rather than the original registration state, although the transaction and unique email constraint prevent a second user/business for the same email.
6. **Medium — `/api/auth/me` and `/api/auth/logout` require only `auth:sanctum`; unlike most mobile business endpoints they do not check the `mobile` ability (`routes/api.php:23-25`, `AuthController.php:130-154`).

## 2. Existing Authentication Architecture

### 2.1 Identity and guards — EXISTING

`User` is the single Eloquent authentication model. It implements `MustVerifyEmail` and uses `HasApiTokens`, Fortify `TwoFactorAuthenticatable`, and passkey authentication. Sensitive model attributes are hidden: `password`, `two_factor_secret`, `two_factor_recovery_codes`, and `remember_token` (`app/Models/User.php:13-44`). The only configured guard is the session-backed `web` guard using the `users` provider (`config/auth.php:22-76`).

There is no provider identity table, Google subject column, OAuth route, Socialite configuration, OIDC issuer configuration, or Google package in `composer.json`. A Google `sub` therefore has nowhere safe to be stored today.

### 2.2 Fortify web flow — EXISTING

- Fortify is configured for registration, password reset, email verification, TOTP 2FA with confirmation and password confirmation, and passkeys (`config/fortify.php:163-175`).
- `CreateNewUser` validates and creates only a `User`; it does not create a business, outlet, subscription, or OTP challenge (`app/Actions/Fortify/CreateNewUser.php:20-31`).
- The generated Fortify routes include `/login`, `/register`, `/logout`, `/email/verify`, `/email/verification-notification`, `/two-factor-challenge`, password reset routes, and 2FA/passkey management routes. They are web/session flows, not mobile bearer-token flows.
- Dashboard routes use `auth`, `verified`, and `ShareDashboardBusinessContext`; route-level permission and Premium middleware then authorize the active business (`routes/web.php:89-95` and subsequent route declarations).
- Platform routes require `auth`, `verified`, `platform.admin`, `platform.admin.2fa`, and security headers (`routes/web.php:290-294`). The 2FA middleware denies non-admins and redirects an admin without confirmed 2FA to security settings (`EnsurePlatformAdminTwoFactor.php:19-32`).
- The login response redirects platform admins to the platform dashboard and ordinary users to the intended Fortify destination (`app/Http/Responses/LoginResponse.php:18-35`).

### 2.3 Web email verification — EXISTING

Fortify owns the signed verification URL and `email_verified_at` update. Existing tests confirm valid signed links mark the user verified, invalid hashes do not, and repeated valid links do not dispatch `Verified` again (`tests/Feature/Auth/EmailVerificationTest.php:24-91`). Web protected routes rely on `verified`; the current API login performs its own verification check instead of using `verified` middleware.

### 2.4 TOTP, recovery codes, and trusted device — EXISTING

- TOTP state is stored in `two_factor_secret`, `two_factor_recovery_codes`, and `two_factor_confirmed_at` (`database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php:12-31`). Fortify encrypts the secret/recovery-code columns; the model hides them.
- The settings component requires password confirmation, enables/disables TOTP, confirms a six-digit code, and queues a trusted-device cookie (`app/Livewire/Settings/Security.php:77-88,188-252,265-276`).
- `RedirectIfTwoFactorAuthenticatable` redirects web logins to the Fortify challenge unless the user has a valid custom trusted-device cookie (`app/Actions/Fortify/RedirectIfTwoFactorAuthenticatable.php:20-44`).
- `TwoFactorDeviceService` derives the cookie HMAC from application key, user ID, expiry, password hash, and encrypted 2FA secret; the cookie is HttpOnly and SameSite=Lax but uses `config('session.secure', false)` (`app/Services/Auth/TwoFactorDeviceService.php:24-72`).
- There is no current Sanctum/mobile 2FA challenge, mobile trusted-device record, or API recovery-code contract.

### 2.5 Sanctum — EXISTING

- `User` uses `HasApiTokens`; API registration/login call `createToken('mobile-api', ['mobile'])` and return `plainTextToken` (`User.php:47-49`, `AuthController.php:79-91,119-125`).
- `personal_access_tokens` stores tokenable identity, token hash, abilities, last-used timestamp, optional expiry, and timestamps (`database/migrations/2026_08_21_083011_create_personal_access_tokens_table.php:12-25`).
- `auth:sanctum` protects the authenticated API group (`routes/api.php:23`). Most mobile endpoints then check `tokenCan('mobile')` and re-evaluate business membership, role, device, and cloud entitlement (`MobileContextController.php:20-48`, `SyncContextResolver.php:26-87`).
- `config/sanctum.php` checks the `web` guard for stateful SPA requests, has no configured expiration, and leaves token prefix empty unless supplied by environment (`config/sanctum.php:25-57`).

### 2.6 Business, tenant, entitlement, and device model — EXISTING

- A user can belong to many businesses through `business_user`; the pivot has a unique `(business_id, user_id)` and a role string (`database/migrations/2026_08_21_105944_create_business_user_table.php:12-24`).
- A business has many outlets and exactly one subscription due to a unique `subscriptions.business_id`; outlet code is unique per business (`2026_08_21_111227_create_subscriptions_table.php:12-23`, `2026_08_21_114954_create_outlets_table.php:12-23`).
- Free is the default modeled plan and does not grant Cloud access. Cloud access requires plan `cloud`, status `active`, and a non-expired date (`app/Models/Subscription.php:18-106`). This must not be used to gate local/offline POS operation.
- Mobile context returns all user memberships, role, business type, subscription, cloud access, outlets, and optional device context (`MobileContextController.php:50-132`).
- Device registration is idempotent per `(business_id, identifier)` and enforces membership, role, same-business outlet, active Cloud entitlement, and the configured device limit (`MobileDeviceController.php:20-177`).

### 2.7 Current provisioning — EXISTING

API registration creates the user, marks `email_verified_at` immediately, creates a random-slug active retail business, attaches the owner role, creates `OUT-1`/`Outlet Utama`, creates an active Free subscription, and issues a token inside one database transaction (`AuthController.php:31-79`). The current transaction is atomic, but it has no idempotency key or durable registration state. Web Fortify registration does not provision these records.

## 3. Current API Inventory

All routes below are **EXISTING** unless explicitly marked otherwise.

| Endpoint | Auth/middleware | Actual behavior | Actual response / status |
|---|---|---|---|
| `POST /api/auth/register` | `throttle:6,1` | Validates `name`, unique `email`, password confirmation/min 8, optional `business_name`; auto-verifies, provisions business/outlet/Free subscription, issues mobile token. | `201` with `message`, `data.token_type`, `data.token`, and basic user. Validation is Laravel `422`; duplicate email is not idempotent. |
| `POST /api/auth/login` | `throttle:6,1` | Validates credentials; rejects unknown/wrong credentials; rejects unverified email; rejects confirmed 2FA; otherwise issues mobile token. | `200` with `data.token_type`, `data.token`, user; `401` `Invalid credentials.`; `403 EMAIL_NOT_VERIFIED`; `403 TWO_FACTOR_REQUIRED`. |
| `GET /api/auth/me` | `auth:sanctum` | Returns current user ID/name/email. It does not check the `mobile` ability, `verified`, business membership, or Cloud entitlement. | `200` `{data:{id,name,email}}`; unauthenticated requests receive framework `401`. |
| `DELETE /api/auth/logout` | `auth:sanctum` | Deletes `currentAccessToken()`. | `200` `{message:"Logged out successfully."}`. Other tokens remain active. |
| `GET /api/mobile/context` | `auth:sanctum`, controller checks `mobile` | Returns user memberships, role, sync capabilities, business type, subscription, Cloud access, outlets, and optional device context. | `200`; `403 MOBILE_TOKEN_REQUIRED` or `BUSINESS_ACCESS_DENIED` in relevant paths. |
| `POST /api/mobile/devices` | `auth:sanctum`, controller checks `mobile` | Idempotently registers/resolves a device while enforcing membership, device-management role, outlet ownership, Cloud access, and device limit. | `200` existing / `201` new; stable `403`/`409` error codes. |
| `POST /api/sync/push` and `GET /api/sync/pull` | `auth:sanctum`, resolver checks `mobile` | Resolves business membership, role permission, Cloud access, active device, then performs sync. | `403 MOBILE_TOKEN_REQUIRED`, `BUSINESS_ACCESS_DENIED`, `MOBILE_ROLE_NOT_SUPPORTED`, `CLOUD_SUBSCRIPTION_REQUIRED`, `SYNC_DEVICE_INVALID`, or `DEVICE_INACTIVE` as applicable. |
| `GET/POST /api/mobile/subscription/*` and `/api/mobile/backups/*` | `auth:sanctum`, controllers/resolvers check `mobile` and tenant policy | Subscription catalog/checkout and private Cloud backup operations. | Existing module-specific JSON contracts; they are downstream consumers of auth and tenant context. |

The current API tests cover login success/failure, email verification gate, 2FA fail-closed behavior, `/me`, current-token logout, multi-token behavior, and login rate limiting (`tests/Feature/ApiAuthenticationTest.php:16-257`). They do not cover the current registration endpoint's provisioning/idempotency behavior, OTP, Google, linking, or mobile 2FA completion.

## 4. Security Findings

| ID | Classification | Finding | Evidence | Required follow-up |
|---|---|---|---|---|
| SEC-AUTH-01 | High | API registration trusts the request as proof of email ownership. | `AuthController.php:39-40` sets `email_verified_at` before any email proof. | Replace with pending state and OTP in AUTH-D02/AUTH-D03. |
| SEC-AUTH-02 | High | API token lifetime is unbounded by Sanctum configuration. | `config/sanctum.php:45-57` has `expiration => null`; token rows permit nullable `expires_at`. | Set an explicit mobile lifetime and implement token/session revocation in AUTH-D06. |
| SEC-AUTH-03 | High | API 2FA blocks login but cannot complete it. | `AuthController.php:120-125`; no API challenge route exists. | Add short-lived challenge and TOTP/recovery verification; Google must enter the same policy. |
| SEC-AUTH-04 | High | No Google OIDC implementation or durable provider identity exists. | No Google/OIDC/social login source or dependency; `User` has no provider relation. | Add verified-subject identity storage and backend verification in AUTH-D04. |
| SEC-AUTH-05 | High | Automatic account linking by email would permit takeover when email is not a sufficient account-linking proof. | Current model has only unique email; no provider subject or link proof. | Fail closed on email collision and require authenticated explicit linking. |
| SEC-AUTH-06 | Medium | Registration is atomic but not idempotent at the API contract level. | `DB::transaction` protects one attempt, but no request key/state is stored (`AuthController.php:31-77`). | Persist registration/idempotency state and make retries return the same pending/provisioned result. |
| SEC-AUTH-07 | Medium | `/me` and logout accept any valid Sanctum token, not specifically `mobile`. | Route group only has `auth:sanctum`; controllers do not call `tokenCan('mobile')`. | Decide whether these are general Sanctum endpoints; recommended target requires `mobile` for mobile namespace. |
| SEC-AUTH-08 | Medium | Existing password changes revoke other web sessions but do not specify revocation of all Sanctum tokens. | `Security.php:100-125` calls `Auth::logoutOtherDevices`, not token deletion. | Revoke or rotate mobile tokens after password/identity/security changes. |
| SEC-AUTH-09 | Medium | Custom trusted-device cookie uses `secure=false` when `SESSION_SECURE_COOKIE` is unset. | `TwoFactorDeviceService.php:50-63`. | Production must force HTTPS/secure cookies; API trusted-device policy must be separate and explicit. |
| SEC-AUTH-10 | Medium | Current email uniqueness validation and login responses need a single normalized-email policy across provider/mobile clients. | Fortify lowercases usernames; API validation uses `unique:users,email` and direct `where('email', ...)`. | Normalize consistently, preserve database uniqueness, and use generic anti-enumeration responses. |
| SEC-AUTH-11 | Low | Existing web registration provisions no business while API registration provisions one. | `CreateNewUser.php:27-31` versus `AuthController.php:31-76`. | Preserve this distinction unless a later product task explicitly unifies onboarding. |

## 5. Target Authentication Architecture

### 5.1 Contract boundaries — PROPOSED

1. Use `/api/v2/auth` for the new mobile contract. Keep existing unversioned routes during a coordinated migration window; do not silently change `201 + token` into `202 + pending` for clients that already depend on it.
2. `User` remains the account/security aggregate. Add a separate `auth_identities` relation for provider subjects; do not overload email as a provider key.
3. Registration creates a pending user but no operational bearer token. A successful OTP verification sets `email_verified_at` and performs idempotent default-business provisioning.
4. Every token-producing path (password login, Google login, 2FA completion, and future linking re-login) passes through one token issuance policy that checks verified status, account status, 2FA, and intended token ability.
5. Cloud authorization is layered: token authentication → verified account → membership in requested business → role permission → active Cloud entitlement → active device where required. No Cloud entitlement check may disable local/offline POS access.

### 5.2 Account states — PROPOSED

`pending_email` → `verified` → `authenticated` is the normal manual flow. `verified` may additionally be `two_factor_required` during login. A Google-created account starts `verified` only after ID-token validation with `email_verified=true`; it does not enter the POS email OTP path. `suspended` and `deleted` are deny states for all token issuance. Existing accounts with non-null `email_verified_at` remain verified during migration; see section 17.

## 6. Manual Registration + Email OTP Flow

### 6.1 Rules — PROPOSED

- OTP is exactly six ASCII digits, generated with a cryptographically secure random source.
- TTL is 10 minutes, maximum five failed attempts per challenge, single-use, and invalidated on successful verification or replacement by a newer challenge.
- Resend is blocked for at least 60 seconds per registration/email and rate-limited by registration ID, normalized email, IP, and device fingerprint where available.
- Store only a keyed digest, for example `HMAC-SHA-256(app/auth OTP key, registration_id || code || challenge_nonce)`, with a server-side key distinct from logs and client data. Never store or log plaintext OTP.
- Verification must run in a transaction with a row lock or an equivalent atomic compare-and-consume operation. Check expiry and attempt count, increment failures atomically, and mark `consumed_at` in the same commit as `email_verified_at`.
- Pending users receive no operational Sanctum token and cannot call Cloud business endpoints. A temporary challenge identifier is not a bearer API token.
- Provisioning occurs only after valid verification. A retry must resolve the same user/registration and existing business/outlet/subscription rather than create another set.

### 6.2 Flow

1. `POST /api/v2/auth/register` validates input, creates or resumes a pending registration, and sends an OTP through the configured mail channel. Response is `202` and contains no token.
2. `POST /api/v2/auth/otp/resend` creates one replacement challenge only after cooldown and returns a generic accepted response.
3. `POST /api/v2/auth/otp/verify` atomically consumes the challenge. It sets `email_verified_at`, provisions the default business/outlet/Free subscription idempotently, and returns verified account state without a bearer token.
4. `POST /api/v2/auth/login` then performs normal password login. If 2FA is enabled, it returns a short-lived non-operational challenge; only challenge completion can issue the Sanctum token.

## 7. Google OpenID Connect Flow

### 7.1 Verification policy — PROPOSED

The backend must verify the received Google ID token with an official or mature Google OIDC library and current Google signing keys. It must validate:

- signature and key availability via Google JWKS/discovery;
- `iss` equal to the configured Google issuers (`https://accounts.google.com` and, only if the library normalizes it, `accounts.google.com`);
- `aud` against a server-side allow-list of registered Android and web client IDs, selected by a server-controlled platform value;
- `exp`, `iat`, and `nbf` with a small documented clock-skew allowance;
- `email_verified === true`;
- `sub` is present and treated as the stable provider identity;
- `nonce` for browser authorization flows where a nonce was issued;
- optional `azp`/authorized-party consistency when Google returns it.

The backend must never accept a client-supplied email, name, or `sub` as proof; it must not store the Google ID token as a POS session; and it must issue a normal Sanctum token only after validation and the account/2FA policy.

### 7.2 Google login — PROPOSED

`POST /api/v2/auth/google` accepts an ID token and a server-validated client platform. A Google `sub` already linked to a user logs that user in. A new `sub` creates one user and one default business only once, using the same idempotent provisioning service as manual verification. Google login does not send or require an additional POS email OTP because Google has already asserted a verified email; the backend still performs all ID-token validation above.

If the validated email matches a manual account but the `sub` is not linked, link the identity transactionally and continue the login **only when that account's own email is verified and provisioned** — both sides have proven control of the address. An unverified or still-pending manual account returns `409 ACCOUNT_LINK_REQUIRED` with no token. If the account already has a different Google `sub`, return `409 IDENTITY_CONFLICT`. If that manual account has 2FA, linking and subsequent Google login still require the 2FA policy; Google is not a 2FA bypass.

### 7.3 Google Cloud Console configuration — PROPOSED, not executed

- **Android client:** create an Android OAuth client for each package/application identity and signing certificate (including release/production certificate). Record the client ID in a server-side allow-list; do not place a client secret in the app.
- **Web client:** create a Web application OAuth client with exact HTTPS authorized origins and redirect URIs. Use an OIDC nonce and PKCE where an authorization-code flow is used.
- **Backend:** configure issuer/JWKS discovery, allowed audience IDs, clock-skew policy, and environment-specific client IDs. Secrets and private configuration belong in deployment secret management, not this repository document.
- **Operational:** rotate/retire client IDs through configuration rollout; do not change accepted audiences without a migration window for installed mobile versions.

## 8. Account Linking Policy

### 8.1 Required proof — PROPOSED

Linking is an authenticated account-management operation, not a login-side effect. Require a valid current Sanctum session with `mobile` ability plus recent reauthentication. If the account has 2FA, require password proof and a valid TOTP/recovery-code proof in the reauthentication step. Validate the Google ID token independently. Use a short-lived, single-use reauthentication grant bound to user, device/session, and intended action.

Recommended endpoints are `POST /api/v2/auth/reauthenticate`, `POST /api/v2/auth/google/link`, and `DELETE /api/v2/auth/google` — all **PROPOSED**. Unlinking is rejected when it would leave a Google-only account with no usable sign-in method; adding a password is required first.

### 8.2 Required cases — PROPOSED

| Case | Required behavior |
|---|---|
| Manual account is new | Register with OTP, provision once, then optionally link Google through authenticated reauthentication. |
| Google account is new | Validate token, create user and provider subject, mark account verified from Google assertion, provision once, issue Sanctum after 2FA policy (normally no 2FA configured). |
| Existing manual account links Google | Require current authenticated account, recent reauthentication, valid Google token, and unique `sub`; attach identity transactionally. |
| Google email equals an existing verified, provisioned manual email but `sub` is unlinked | Link the identity transactionally and continue the login; both sides proved the same address. Unverified/pending accounts return `409 ACCOUNT_LINK_REQUIRED` without mutation. |
| Google `sub` already linked | Sign in the owning user only after token validation. If the identity row points to more than one user or conflicts with the email/account state, fail closed with `409 IDENTITY_CONFLICT`. |
| Google `sub` differs while email belongs to an existing account | Never replace or merge. If the account has no Google identity and is verified, link it; if a different `sub` is already linked, return `409 IDENTITY_CONFLICT`. Preserve existing business, tenant, device, subscription, and roles. |
| User has several businesses | Linking changes only the global user identity. It never selects, transfers, merges, or creates a business implicitly. The existing membership set remains unchanged. |
| User has 2FA | Linking requires the same reauthentication/2FA policy. Google login after linking still enters the 2FA challenge before token issuance. |

No linking operation may move an owner, membership, tenant record, device, subscription, payment, sync record, or backup. Existing membership uniqueness and role values remain authoritative.

## 9. Authentication State Machine

The following state machine is **PROPOSED**:

```text
REGISTER_REQUEST
  ├─ invalid input ───────────────> VALIDATION_ERROR
  ├─ existing verified email ─────> GENERIC_CHECK_EMAIL (no mutation/token)
  └─ new/resumable registration ──> PENDING_EMAIL

PENDING_EMAIL
  ├─ resend before 60s ───────────> OTP_COOLDOWN
  ├─ expired/5 failed attempts ───> OTP_REJECTED (new challenge required)
  ├─ wrong code (<5 attempts) ────> PENDING_EMAIL
  └─ valid code, atomically used ─> VERIFIED + PROVISIONED

VERIFIED
  ├─ password login failure ──────> INVALID_CREDENTIALS
  ├─ password login + 2FA off ────> AUTHENTICATED (Sanctum token)
  ├─ password/Google login + 2FA > TWO_FACTOR_CHALLENGE
  └─ Google email collision ------- > auto-link if local email verified, else ACCOUNT_LINK_REQUIRED

TWO_FACTOR_CHALLENGE
  ├─ valid TOTP/recovery code ─────> AUTHENTICATED (Sanctum token)
  ├─ invalid/expired/max attempts > AUTH_REJECTED
  └─ no challenge token ------------> no bearer token

AUTHENTICATED
  ├─ logout current ---------------> TOKEN_REVOKED
  ├─ logout all/security change ---> ALL_TOKENS_REVOKED
  └─ each tenant request ----------> membership → role → entitlement → device
```

## 10. Proposed API Endpoints

The table is the target `/api/v2/auth` contract. Every row is **PROPOSED** and is not present in the current code unless noted.

| Method | Path | Auth | Purpose | Success |
|---|---|---|---|---|
| `POST` | `/api/v2/auth/register` | Public | Create/resume pending manual registration and send first OTP. | `202 PENDING_EMAIL`; no bearer token. |
| `POST` | `/api/v2/auth/otp/resend` | Public + challenge rate limits | Replace/send OTP after cooldown. | `202`; generic response. |
| `POST` | `/api/v2/auth/otp/verify` | Public + challenge rate limits | Verify one OTP atomically, set `email_verified_at`, provision idempotently. | `200 VERIFIED`; no bearer token. |
| `POST` | `/api/v2/auth/login` | Public | Password login. | `200` token or `202` 2FA challenge. |
| `POST` | `/api/v2/auth/2fa/verify` | Public + temporary challenge | Complete TOTP or single-use recovery code. | `200` token. |
| `POST` | `/api/v2/auth/google` | Public | Verify Google ID token, resolve/create/link-safe account, then login or return 2FA challenge. | `200` token or `202` challenge. |
| `POST` | `/api/v2/auth/reauthenticate` | `auth:sanctum` + `mobile` | Prove password and, when enabled, 2FA for sensitive account actions. | `200` short-lived reauth grant. |
| `POST` | `/api/v2/auth/google/link` | `auth:sanctum` + `mobile` + reauth | Attach one verified Google `sub` to current user. | `200` identity summary. |
| `DELETE` | `/api/v2/auth/google` | `auth:sanctum` + `mobile` + reauth | Remove Google identity if another usable sign-in method remains. | `204`. |
| `GET` | `/api/v2/auth/me` | `auth:sanctum` + `mobile` + verified | Return account, auth-method, and verification state. | `200`. |
| `DELETE` | `/api/v2/auth/logout` | `auth:sanctum` + `mobile` | Revoke current mobile token. | `204` or stable `200`. |
| `DELETE` | `/api/v2/auth/sessions` | `auth:sanctum` + `mobile` + reauth | Revoke all other/current mobile tokens according to the requested mode. | `204`. |

`/api/auth/*` remains the **EXISTING** compatibility surface until AUTH-D07/AUTH-D08 complete a client migration. Exact sunset timing and whether a version header may replace `/v2` are an **OPEN DECISION**.

## 11. Request/Response Contracts

### 11.1 Manual registration — PROPOSED

```http
POST /api/v2/auth/register
Idempotency-Key: 8b7f6b7d-4f0f-4f4f-9c90-2a4f7b7f4b01
Content-Type: application/json

{
  "name": "Sari",
  "email": "sari@example.com",
  "password": "correct horse battery staple",
  "password_confirmation": "correct horse battery staple",
  "business_name": "Toko Sari"
}
```

```json
{
  "message": "If the address can be used, a verification code has been sent.",
  "data": {
    "registration_id": "reg_opaque_id",
    "state": "pending_email",
    "otp_expires_at": "2026-10-24T10:10:00+07:00",
    "resend_available_at": "2026-10-24T10:01:00+07:00"
  }
}
```

Do not return whether a verified account already exists. A repeated identical `Idempotency-Key` returns the same safe registration state; reusing the key with a different payload returns `409 IDEMPOTENCY_KEY_REUSED`.

### 11.2 OTP resend and verify — PROPOSED

```json
POST /api/v2/auth/otp/resend
{
  "registration_id": "reg_opaque_id"
}
```

```json
POST /api/v2/auth/otp/verify
{
  "registration_id": "reg_opaque_id",
  "code": "083271"
}
```

Successful verification:

```json
{
  "message": "Email verified.",
  "data": {
    "state": "verified",
    "provisioning": "complete",
    "user": {"id": 42, "name": "Sari", "email": "sari@example.com"}
  }
}
```

If provisioning is retriable but not committed, return `202 AUTH_PROVISIONING_PENDING` and allow the same registration to be retried; never create a second business.

### 11.3 Login and API 2FA — PROPOSED

Without 2FA:

```json
{
  "data": {
    "token_type": "Bearer",
    "token": "<opaque-sanctum-token>",
    "expires_at": "2026-11-23T10:00:00+07:00",
    "user": {"id": 42, "name": "Sari", "email": "sari@example.com"}
  }
}
```

With 2FA:

```json
{
  "message": "Two-factor authentication is required.",
  "code": "TWO_FACTOR_REQUIRED",
  "data": {
    "challenge_id": "ch_opaque_id",
    "expires_at": "2026-10-24T10:05:00+07:00",
    "methods": ["totp", "recovery_code"]
  }
}
```

The challenge is short-lived, single-purpose, attempt-limited, and cannot call any business endpoint. `POST /api/v2/auth/2fa/verify` consumes it and returns the normal token response. The server must not accept a Google login as completion of an existing 2FA requirement.

### 11.4 Google login and linking — PROPOSED

```json
POST /api/v2/auth/google
{
  "id_token": "<google-id-token>",
  "client_platform": "android"
}
```

```json
POST /api/v2/auth/reauthenticate
{
  "password": "current password",
  "totp_code": "123456"
}
```

```json
POST /api/v2/auth/google/link
{
  "id_token": "<google-id-token>",
  "client_platform": "web",
  "reauthentication_token": "<short-lived-one-time-grant>"
}
```

Successful link:

```json
{
  "message": "Google account linked.",
  "data": {
    "provider": "google",
    "subject_linked": true,
    "email_verified": true
  }
}
```

Never return the raw Google ID token, provider access token, refresh token, or client secret in any response.

### 11.5 `/me` and logout — PROPOSED target; current behavior is EXISTING in section 3

```json
GET /api/v2/auth/me
{
  "data": {
    "user": {
      "id": 42,
      "name": "Sari",
      "email": "sari@example.com",
      "email_verified": true,
      "two_factor_enabled": false,
      "auth_methods": ["password", "google"]
    },
    "businesses": []
  }
}
```

The `businesses` array is a compact account summary only; detailed tenant context remains the existing `/api/mobile/context` contract. Logout revokes the token presented by the caller; an explicit all-session endpoint is required for global revocation.

## 12. Error Code Matrix

The following target codes are **PROPOSED** unless marked `EXISTING`.

| Code | HTTP | Meaning and client action |
|---|---:|---|
| `VALIDATION_ERROR` | 422 | Input invalid; field details may be returned without secrets. **EXISTING framework behavior** for current validation. |
| `INVALID_CREDENTIALS` | 401 | Unknown email or wrong password; same response and timing class to reduce enumeration. Current message is `Invalid credentials.` |
| `EMAIL_NOT_VERIFIED` | 403 | Account cannot receive an operational token until verified. **EXISTING** on current login. |
| `PENDING_EMAIL` | 202 | Registration is awaiting OTP; no token. **PROPOSED**. |
| `OTP_INVALID` | 422 | Code does not match; attempts are consumed. **PROPOSED**. |
| `OTP_EXPIRED` | 422 | Challenge is past 10 minutes. **PROPOSED**. |
| `OTP_MAX_ATTEMPTS` | 429 | Five failed attempts reached; request a new challenge. **PROPOSED**. |
| `OTP_COOLDOWN` | 429 | Resend is before the 60-second cooldown. **PROPOSED**. |
| `OTP_ALREADY_USED` | 409 | Challenge was successfully consumed or replaced. **PROPOSED**. |
| `TWO_FACTOR_REQUIRED` | 202 target / 403 current | Login needs TOTP/recovery completion. Current API returns 403 with no completion route; target returns a short-lived challenge. |
| `TWO_FACTOR_INVALID` | 422 | Invalid TOTP/recovery code; no token. **PROPOSED**. |
| `AUTH_CHALLENGE_EXPIRED` | 401 | Temporary login/reauthentication challenge expired. **PROPOSED**. |
| `MOBILE_TOKEN_REQUIRED` | 403 | Token lacks `mobile` ability. **EXISTING** in mobile controllers. |
| `UNAUTHENTICATED` | 401 | Missing/revoked/expired Sanctum token. **EXISTING framework behavior**. |
| `TOKEN_REVOKED` | 401 | Previously issued token is no longer accepted. **PROPOSED stable mapping**. |
| `ACCOUNT_LINK_REQUIRED` | 409 | Valid Google identity collides with an existing **unverified or pending** account; verification/manual linking is required. **PROPOSED**. |
| `GOOGLE_TOKEN_INVALID` | 401 | Signature/issuer/audience/expiry/nonce/email verification failed. Do not disclose which claim failed. **PROPOSED**. |
| `IDENTITY_CONFLICT` | 409 | Provider subject is attached inconsistently or to a different user. Fail closed and alert. **PROPOSED**. |
| `GOOGLE_IDENTITY_EXISTS` | 409 | Link target is already linked to another account. **PROPOSED**. |
| `BUSINESS_ACCESS_DENIED` | 403 | User is not a member of the requested business. **EXISTING**. |
| `MOBILE_ROLE_NOT_SUPPORTED` | 403 | Role lacks the requested mobile permission. **EXISTING**. |
| `CLOUD_SUBSCRIPTION_REQUIRED` | 403 | Business is Free, expired, inactive, or otherwise lacks Cloud entitlement. **EXISTING**. |
| `DEVICE_INACTIVE` | 403 | Registered device is not active. **EXISTING**. |
| `RATE_LIMITED` | 429 | Login, OTP, Google verification, or linking limit reached. **PROPOSED stable mapping**. |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Same key was reused with different request data. **PROPOSED**. |
| `AUTH_PROVISIONING_PENDING` | 202 | Verification succeeded but retryable provisioning is not yet complete. **PROPOSED**. |
| `AUTH_SERVICE_UNAVAILABLE` | 503 | Auth/mail/Google verification dependency unavailable; client retains local offline work and retries safely. **PROPOSED**. |

## 13. Sanctum Token & 2FA Policy

### Existing behavior

- Tokens are created with the single `mobile` ability and returned as bearer tokens. Current tokens do not have a configured expiration.
- Logout deletes only `currentAccessToken()`. Membership role changes are intentionally evaluated per request and therefore apply to already-issued tokens; this is good tenant behavior but is not token revocation.
- Current API login fails closed for a confirmed-2FA user, but no API route completes the challenge.

### Target policy — PROPOSED

- Use a named mobile token with only the `mobile` ability; do not accept a generic token for mobile endpoints.
- Set a finite token lifetime (recommended initial policy: 30 days, subject to product decision), persist/return `expires_at`, and rotate the token after a successful reauthentication or high-risk account change.
- Revoke current token on logout; provide explicit all-session revocation; revoke all mobile tokens after password reset, provider unlink, account recovery, or suspected takeover. A 2FA enable/disable or secret change must invalidate trusted sessions/tokens according to the final threat model.
- Never issue a bearer token while email verification, 2FA, Google validation, or provisioning is incomplete.
- Use a cache-backed, single-use API auth challenge for TOTP/recovery completion. Apply per-user, per-challenge, per-IP, and global abuse limits. Recovery codes are consumed atomically and never returned after use.
- Web Fortify sessions, web trusted-device cookies, mobile Sanctum tokens, and Google ID tokens are distinct credentials. A Google ID token is an assertion used once, not a POS session.

## 14. Business/Outlet/Subscription Provisioning

### Existing

Current API registration performs all four writes inside one transaction, while database constraints provide unique business slug, unique business membership, unique outlet code per business, and unique subscription per business. The random slug means a replay after a successful but response-lost request cannot be resolved by slug; the email uniqueness constraint only turns the replay into a validation failure.

### Target — PROPOSED

Use one durable registration/provisioning identity per user/registration. In a transaction:

1. lock the registration/user row;
2. if `provisioning_completed_at` is set, return the existing provisioned IDs;
3. create or find exactly one business using the registration-owned idempotency key;
4. create or find the owner membership using the unique `(business_id,user_id)` key;
5. create or find the default outlet using a deterministic registration-scoped code;
6. create or find the Free subscription using unique `business_id`;
7. mark provisioning complete only after all writes commit.

On deadlock/temporary database failure, retry the transaction with bounded backoff. On a permanent failure, return `AUTH_PROVISIONING_PENDING` or a safe service error without issuing a token. Never let a client-selected business ID alter an existing owner's tenant data during registration.

The Free subscription grants no Cloud access but must not prevent offline POS operation. Premium/Cloud entitlement is evaluated only at Cloud API boundaries and remains independent of authentication proof.

## 15. Database Changes Proposed (Not Implemented)

The following are design proposals only; AUTH-D01 intentionally adds no migration.

1. `auth_identities`: `id`, `user_id`, `provider`, `provider_subject`, normalized provider email snapshot, timestamps, optional last-login metadata; unique `(provider, provider_subject)` and unique `(user_id, provider)`. Never use email as the unique provider key.
2. `auth_registrations`: opaque registration ID, `user_id`, normalized email, idempotency key/fingerprint, state, verified timestamp, provisioning completion timestamp, timestamps; unique user and idempotency key.
3. `auth_otp_challenges`: registration ID, challenge nonce, keyed digest, expiry, failed-attempt counter, resend-available timestamp, consumed timestamp, replacement timestamp, timestamps; index active challenge lookup. Retain only operational metadata, not plaintext code.
4. `auth_reauthentication_grants` or equivalent cache contract: hash of one-time grant, user/token/device binding, allowed action, expiry, consumed timestamp. A cache-only implementation is acceptable if loss is safe and forces reauthentication.
5. Optional `auth_sessions`/trusted mobile-device metadata if product requires a remembered mobile device. Do not reuse the web cookie format for native clients.
6. User password nullability or an explicit unusable-password strategy for Google-only accounts is required before implementation. The choice is an **OPEN DECISION**; a random usable password must never be generated and disclosed.

All changes must be backward-compatible with current `users`, `business_user`, business data, devices, subscriptions, and existing Fortify columns. No migration belongs in AUTH-D01.

## 16. Security Threat Model

| Threat | Control in target contract | Negative test |
|---|---|---|
| OTP brute force | Six digits, 10-minute TTL, five attempts, per-challenge/IP/account throttles, atomic counter. | Six wrong codes lock the challenge; seventh is not evaluated. |
| OTP replay/race | Single-use digest and atomic consume under transaction/lock. | Two concurrent valid verifies yield one success and one `OTP_ALREADY_USED`. |
| OTP database/log disclosure | HMAC/keyed digest only; redact code, email tokens, and headers from logs. | Database/log inspection contains no plaintext OTP. |
| Account enumeration | Generic register/resend response and same login failure response/timing class. | Existing and unknown email do not disclose account state. |
| Google token forgery | Signature/JWKS, issuer, audience, expiry, nonce, `email_verified`, and subject checks. | Altered signature, wrong audience, expired token, wrong issuer, unverified email all fail. |
| Google replay | Validate expiry/nonce; do not persist token as session. | Replayed/expired token is rejected; issued Sanctum token remains independent. |
| Email-collision takeover | Auto-link only when the local email is verified and provisioned; never link unverified/pending accounts; `sub` uniqueness. | A different `sub` already linked returns `IDENTITY_CONFLICT`; an unverified email returns `ACCOUNT_LINK_REQUIRED`. |
| 2FA bypass through Google | Google login enters the same 2FA policy as password login. | A linked 2FA user receives a challenge and no bearer token. |
| Stolen bearer token | Finite expiry, current/all-token revocation, mobile ability, security-change rotation. | Revoked/expired token receives 401; a token without `mobile` cannot use mobile endpoints. |
| Tenant IDOR | Membership/role checks per requested business; scoped device/data queries; no implicit business transfer. | User A cannot read or mutate User B's business by changing `business_id`. |
| Privilege escalation | Roles are evaluated from current pivot; unknown roles deny; entitlement is separate. | Role change/removal affects next request even for an old token. |
| Provisioning duplication | Durable registration key, row lock, unique constraints, idempotent service. | Retried verify/login creates one user/business/outlet/subscription only. |
| Mail/Google/database outage | Fail closed for token issuance; preserve local/offline POS; safe retry with idempotency. | Dependency timeout yields no token and no duplicate provisioning. |
| Secret leakage | Hide model fields; never return Google token, OTP, secret, recovery codes, or raw Sanctum token except first issuance. | JSON/log redaction tests. |

## 17. Existing Account Compatibility & Rollout

### Compatibility rules

- Existing verified email/password login remains valid. Existing web Fortify routes and sessions remain unchanged.
- Existing unverified users continue through Fortify email verification. They must not receive a new Cloud bearer token until the target verification policy says so.
- Existing users with `two_factor_confirmed_at` remain subject to 2FA; Google does not remove or downgrade that requirement.
- Existing businesses, memberships, roles, devices, sync records, backups, subscriptions, billing, and Free/Premium entitlement are not moved by linking or provider migration.
- Offline Free POS remains usable without a Cloud account or network. Cloud login and sync retries must not delete or invalidate local offline data.

### Accounts whose email is already auto-verified — PROPOSED migration

The current API has already set `email_verified_at` for accounts created through `/api/auth/register`. Treat the persisted non-null value as a backward-compatible verified state; do not send a surprise OTP, alter business data, or reset sessions during migration. At first successful target login, lazily create the `auth_registrations`/provisioning marker and, if necessary, reconcile the existing owned business/outlet/subscription by primary keys and unique constraints. If the account has no business, provision through the idempotent service only after the user has passed the new target login policy. Record the migration path for audit without storing secrets.

### Recommended rollout — PROPOSED

1. Ship schema and read-only identity/provisioning compatibility.
2. Ship verification and provisioning services behind feature flags; test existing accounts and retries.
3. Add `/api/v2/auth` and mobile contract tests without changing `/api/auth/*`.
4. Release `pos-mobile` support for v2 registration/OTP/2FA/Google and monitor adoption.
5. Make v2 the default for new clients; announce legacy registration sunset only after client coverage.
6. Enable Google linking/login progressively; monitor identity conflicts and failed verification without logging token contents.
7. Decide and execute legacy endpoint retirement in a separate change set.

## 18. Backend-to-Mobile Integration Contract

The mobile client must treat authentication as a server state machine, not as a local guess.

- Store the Sanctum bearer token only in platform secure storage. Do not store Google ID tokens as the long-lived POS credential.
- On `202 PENDING_EMAIL`, show OTP UI and preserve the opaque registration ID. On `OTP_COOLDOWN`, use `Retry-After`; on expiry/max attempts, request a new challenge.
- On `202 TWO_FACTOR_REQUIRED`, retain only the short-lived challenge ID and show TOTP/recovery UI. Do not call `/api/mobile/*` until a bearer token is returned.
- On `401`, clear the Cloud token and require login; preserve offline POS data and pending local sync work.
- On `403 CLOUD_SUBSCRIPTION_REQUIRED`, keep local/offline features available and present Cloud entitlement/billing state; do not treat it as account logout.
- On membership/role/device denial, refresh `/api/mobile/context` before retrying. The server is authoritative for current role, membership, entitlement, and device status.
- Use an idempotency key for registration and any future account-provisioning action; retry only with the same key and same payload.
- Treat unknown error codes as non-success and preserve the response correlation ID if one is later added.
- Google Android and web clients must send an ID token from the client ID registered for that platform; the backend chooses accepted audiences from configuration.

No change is made to `pos-mobile` by AUTH-D01. Its implementation and release sequencing are dependencies of AUTH-D08.

## 19. Regression Test Plan

### Existing regression coverage to preserve

- `tests/Feature/ApiAuthenticationTest.php`: current API login, generic credential errors, email verification gate, 2FA fail-closed behavior, `/me`, current-token logout, multi-token behavior, and throttling.
- `tests/Feature/Auth/AuthenticationTest.php`, `RegistrationTest.php`, `EmailVerificationTest.php`, `TwoFactorChallengeTest.php`, and `TwoFactorTrustedDeviceTest.php`: Fortify web session, verification, TOTP, recovery, and trusted-device behavior.
- Business membership/RBAC, mobile context, sync authorization, device management, subscription, backup, and platform security tests: tenant and entitlement regressions.

### New tests required — PROPOSED

1. Registration returns 202/no token, creates pending state, and sends no duplicate active challenge on an idempotent retry.
2. OTP accepts exactly six digits, expires at ten minutes, rejects after five failures, enforces 60-second resend cooldown, never stores plaintext, and consumes atomically under concurrency.
3. Successful OTP sets `email_verified_at` once and provisions exactly one owner business, outlet, and Free subscription across retries/concurrent requests.
4. Pending accounts cannot access `/me`/Cloud endpoints with any temporary challenge identifier.
5. Login remains generic for unknown email/wrong password; rate limits are applied per account/IP without a bypass through endpoint variation.
6. API TOTP and recovery-code challenges issue one token only; invalid/replayed/expired challenges issue none; 2FA secret changes revoke appropriate credentials.
7. Google tokens with invalid signature, issuer, audience, expiry, nonce, or `email_verified=false` fail closed with no account mutation.
8. New Google `sub` creates one verified user/provisioning set; replay is idempotent; Google login does not send POS OTP.
9. Existing manual email collision requires explicit link; a different `sub` cannot link; a linked `sub` cannot move between users.
10. Linking/unlinking requires reauthentication/2FA and does not alter businesses, memberships, devices, subscriptions, billing, sync, or backups.
11. Mobile token expiration/revocation, `mobile` ability, `/me`, logout-current, logout-all, and security-change revocation are covered.
12. Multi-business owner/member/cashier access, Free/Premium entitlement, device limit, and offline behavior remain unchanged.

## 20. Open Decisions and Risks

1. **OPEN DECISION — API versioning:** use `/api/v2/auth` as recommended, or introduce a version header while preserving unversioned paths?
2. **OPEN DECISION — API 2FA UX:** issue a `202` temporary challenge (recommended) or retain current `403 TWO_FACTOR_REQUIRED` until a separate mobile 2FA task ships?
3. **OPEN DECISION — Google-only password model:** nullable password, unusable password hash, or mandatory password setup after first Google login.
4. **OPEN DECISION — token lifetime:** recommended initial 30 days, but product/security owners must select absolute and idle lifetime, rotation, and all-session revocation semantics.
5. **OPEN DECISION — remembered mobile devices:** no remembered mobile bypass is the safest initial policy; if required, define a separate device-bound credential and revocation UI.
6. **OPEN DECISION — account state taxonomy:** confirm whether suspended/deleted/locked states belong in AUTH-D02 or a broader account-management task.
7. **OPEN DECISION — mail delivery:** confirm queue, retry, provider, sender/domain, bounce handling, and whether OTP resend is allowed after mail-provider timeout.
8. **OPEN DECISION — current auto-verified API accounts:** approve lazy migration semantics above, including whether an existing account with no business may be provisioned at first v2 login.
9. **Risk:** changing current `/api/auth/register` in place can break installed mobile clients that expect a bearer token in the 201 response.
10. **Risk:** provider misconfiguration or stale Google JWKS must fail closed and be observable without exposing claim/token material.
11. **Risk:** token expiration or all-session revocation may surprise users on multiple devices; communicate and test the offline recovery path.

## 21. Implementation Dependencies — AUTH-D02 through AUTH-D09

The following sequence is **PROPOSED** and should remain separate from AUTH-D01:

| Task | Dependency/output |
|---|---|
| AUTH-D02 | Registration state, OTP digest/challenge storage, mail delivery, TTL/attempt/cooldown policy, and idempotency schema. |
| AUTH-D03 | Manual `/api/v2/auth/register`, resend, verify, provisioning orchestration, and API contract tests. |
| AUTH-D04 | Google OIDC verifier, JWKS/audience configuration, `auth_identities`, Google login, and Cloud Console runbook. |
| AUTH-D05 | Explicit account linking/unlinking, reauthentication grants, conflict handling, and multi-business/2FA policy. |
| AUTH-D06 | Sanctum lifetime, token rotation/revocation, mobile 2FA challenge, recovery-code policy, and ability middleware. |
| AUTH-D07 | Existing-account migration, web/API compatibility, legacy route strategy, and rollout flags. |
| AUTH-D08 | `pos-mobile` integration, secure storage, offline/error UX, v2 client release, and end-to-end contract tests. |
| AUTH-D09 | Security hardening, rate-limit tuning, audit/metrics, alerting, abuse response, documentation, and production readiness review. |

AUTH-D02 should be the next implementation task for the manual OTP foundation. AUTH-D04 may proceed after the identity-storage and Google audience decisions are approved.

## 22. Definition of Done

AUTH-D01 is complete when:

- the existing Fortify, Sanctum, API, 2FA, tenant, business, outlet, subscription, device, and relevant test behavior is documented from source;
- existing and proposed behavior are explicitly separated;
- manual OTP, Google OIDC, account linking, 2FA, Sanctum, provisioning, error, threat, rollout, and mobile contracts are specified;
- existing verified accounts and offline Free POS have a backward-compatible migration strategy;
- no OTP, Google OAuth, migration, dependency, endpoint, or application behavior is implemented in this task;
- the Git change is exactly this Markdown document and contains no secret or production credential;
- the document passes `git diff --check`, and the final branch diff contains only `docs/auth/AUTH_D01_CLOUD_AUTH_CONTRACT.md`.
