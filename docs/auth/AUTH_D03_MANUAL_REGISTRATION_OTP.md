# AUTH-D03 — Manual Registration & Email OTP Verification

## Scope and status

AUTH-D03 adds a versioned manual-registration contract at `/api/v2/auth`. The existing `/api/auth/register` contract is intentionally unchanged during the mobile migration window; it still auto-verifies and issues a token and therefore remains a known legacy security risk.

The v2 flow is:

`pending_email` → OTP validation → `verified` → atomic default provisioning.

Registration and verification never issue a Sanctum token. The existing login endpoint remains the token-producing path after email verification.

## Endpoints

### `POST /api/v2/auth/register`

Required header: `Idempotency-Key` (opaque ASCII token, 16–255 characters).

```json
{
  "name": "User Example",
  "email": "user@example.com",
  "password": "ValidPassword123!",
  "password_confirmation": "ValidPassword123!",
  "business_name": "Toko Example"
}
```

`202 Accepted`:

```json
{
  "message": "Jika alamat email dapat digunakan, instruksi verifikasi telah dikirim.",
  "data": {
    "registration_id": "opaque-registration-uuid",
    "state": "pending_email",
    "otp_expires_at": "2026-10-26T12:10:00+00:00",
    "resend_available_at": "2026-10-26T12:01:00+00:00"
  }
}
```

The account is persisted with `email_verified_at = null`; no business, outlet, subscription, or token exists yet. An email collision returns the same generic accepted shape with a non-persisted opaque UUID and never returns another account's registration ID.

### `POST /api/v2/auth/otp/verify`

```json
{
  "registration_id": "opaque-registration-uuid",
  "code": "123456"
}
```

`200 OK`:

```json
{
  "message": "Email berhasil diverifikasi.",
  "data": {
    "state": "verified",
    "provisioning": "complete"
  }
}
```

The current active challenge is resolved server-side from the registration subject and purpose. Clients never submit a challenge identifier.

### `POST /api/v2/auth/otp/resend`

```json
{
  "registration_id": "opaque-registration-uuid"
}
```

Resend is `202 Accepted` for a usable pending registration and applies the AUTH-D02 60-second cooldown. Invalid, verified, or foreign references do not reveal account state.

## Storage and idempotency

`pending_registrations` stores an opaque UUID, the user reference, normalized email, business-name intent, status, provisioning marker, and nullable provisioning result references. It stores neither plaintext passwords, OTPs, bearer tokens, nor the original Idempotency-Key. The key and complete request fingerprint are keyed SHA-256 values.

Database uniqueness is authoritative for user email, pending normalized email, user reference, and idempotency key. The same key and same payload resumes the same registration and active challenge. Reuse with a different payload returns `409 IDEMPOTENCY_KEY_REUSED`. A different key cannot modify an existing pending account or password.

## OTP transaction flow

The controller resolves the latest active challenge and calls `EmailOtpService::validate()` before opening the provisioning transaction. This makes invalid-attempt accounting durable even when the database-backed rate limiter is used.

The provisioning transaction locks the pending-registration row and user, re-checks state and email binding, calls `EmailOtpService::consume()` inside that transaction, marks the user verified, and creates/resolves the business, owner membership, `OUT-1` / `Outlet Utama`, and active Free subscription. Any provisioning exception rolls back OTP consumption, email verification, and all provisioning writes together. A verified+complete retry returns success without provisioning again.

## Provisioning strategy

Each registration receives exactly one active business with an active owner membership, one default outlet, and one active Free subscription. The existing unique business-user, outlet-code, and subscription-business constraints remain in force; row locks and the persisted references protect retries and concurrent verification. Existing businesses and tenants are not selected by email or modified.

## Error mapping

| Code | HTTP | Meaning |
|---|---:|---|
| `PENDING_EMAIL` | 422 | Registration is unavailable or still requires email verification. |
| `OTP_INVALID` | 422 | Code mismatch; failed-attempt count is durable. |
| `OTP_EXPIRED` | 410 | Challenge expired. |
| `OTP_MAX_ATTEMPTS` | 422 | Five failed attempts locked the challenge. |
| `OTP_RESEND_COOLDOWN` | 429 | Resend is blocked until cooldown expires. |
| `OTP_RATE_LIMITED` | 429 | Subject/IP OTP rate limit reached. |
| `OTP_REPLACED` | 410 | A newer OTP replaced the challenge. |
| `OTP_ALREADY_USED` | 410 | Challenge was consumed already. |
| `OTP_DELIVERY_FAILED` | 503 | Mail delivery failed; pending state remains retryable. |
| `AUTH_PROVISIONING_PENDING` | 409 | Recovery is required; the flow fails closed. |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Key was reused with a different payload. |

429 responses include `Retry-After` whenever AUTH-D02 supplies a retry interval. Error responses never include OTPs, digests, hashes, stack traces, or database details.

## Mail and rate limiting requirements

Production must configure `AUTH_OTP_MAILER` to an allowed real delivery mailer (`smtp`, `ses`, `postmark`, `resend`, or `sendmail`) and configure the corresponding Laravel mail credentials. The unsafe `log` and test mailers are rejected by AUTH-D02. OTPs are six ASCII digits, valid for ten minutes, single-use, and limited to five failed attempts.

AUTH-D02 service limits apply per keyed subject, normalized email, and IP. The routes additionally apply request throttles (registration 6/minute, verify 30/minute, resend 10/minute). No mail call is made by the provisioning transaction.

## Compatibility and rollout

Fortify web registration, signed web email verification, existing API login, Sanctum token lifecycle, 2FA fail-closed behavior, tenant RBAC, subscriptions, device management, sync, and the legacy API route are unchanged by AUTH-D03.

Before switching `pos-mobile` to v2, configure and verify production SMTP, ship the mobile client contract, monitor delivery and recovery errors, and maintain a migration window. After all clients use v2, deprecate and then remove `/api/auth/register` in a separately reviewed task. Do not claim all Cloud registration paths are OTP-protected while the legacy endpoint remains enabled.

Rollback is additive: stop routing new clients to v2 and revert the application release only after preserving the pending-registration migration. Do not delete pending rows or OTP challenges during rollback; they contain recovery state and no plaintext secrets.

## Test matrix and results

The AUTH-D03 feature suite covers valid pending registration, validation, normalized email, no token or provisioning before verification, mail delivery, idempotency, collision non-enumeration, OTP success/failure/expiry/lockout/replacement/replay, resend cooldown, server-side challenge binding, idempotent provisioning, login compatibility, and retry safety. AUTH-D02 infrastructure tests cover durable failed attempts and validate-before-outer-transaction behavior.

The default automated suite uses SQLite in-memory. It verifies transaction rollback and uniqueness behavior, but it does not provide full multi-process MySQL/MariaDB race testing. A MySQL/MariaDB integration run is required before production rollout for concurrent registration and verification.

## Mobile integration contract

Persist `registration_id` from the `202` response and submit it with the six-digit code. On `OTP_RESEND_COOLDOWN` or `OTP_RATE_LIMITED`, honor `Retry-After`. On `OTP_DELIVERY_FAILED`, retry the same registration request with the same Idempotency-Key; do not create a new account. After `verified`, call the existing `/api/auth/login` with the original email and password to obtain the normal mobile Sanctum token.
