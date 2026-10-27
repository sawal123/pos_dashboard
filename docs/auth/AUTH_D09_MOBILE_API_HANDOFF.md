# AUTH-D09 Mobile API Handoff

This contract is derived from `routes/api.php`, the API controllers, request classes, auth services, and the executed feature tests at commit `d966cae057f9fe8132084a11049bf582e925dd46`.

## Common client rules

- Base URL is deployment-specific. Use `Accept: application/json` and `Content-Type: application/json` for JSON requests.
- Authenticated calls use `Authorization: Bearer <sanctum-token>`.
- Only v2 mobile tokens with the `mobile` ability may access the mobile-protected v2 endpoints and `/api/mobile/context`.
- Do not store passwords, OTPs, Google JWTs, challenge IDs, reset tokens, or reauthentication grants as session tokens.
- Store the returned Sanctum token only in platform secure storage. Keep offline SQLite/workspace data when cloud authentication fails or logout occurs.
- `422` validation responses use Laravel's standard JSON validation shape (`message` plus `errors`). Stable application errors include a top-level `code`.
- Retry only idempotent or explicitly retryable operations. A `503` does not prove that registration or login failed; re-check state before repeating.

## v2 authentication endpoints

### `POST /api/v2/auth/register`

- Auth: none. Headers: JSON headers plus required `Idempotency-Key` matching the server's 16–255 character token format.
- Request:

```json
{"name":"Ada","email":"ada@example.com","password":"StrongPassword123!","password_confirmation":"StrongPassword123!","business_name":"Ada Store"}
```

- Success: `202` with `message`, `data.registration_id`, `data.state=pending_email`, `data.otp_expires_at`, and `data.resend_available_at`. No bearer token is issued.
- Replayed same key/payload returns the same pending registration response. Same key with a different payload returns `409 IDEMPOTENCY_KEY_REUSED`.
- Email collisions return a generic `202` decoy response. Invalid input is `422`; safe OTP delivery failure is `503 OTP_DELIVERY_FAILED`.
- Retry: repeat only with the same key and identical payload; never generate a new key blindly after a timeout.
- State: open OTP verification for `pending_email`.

### `POST /api/v2/auth/otp/verify`

- Auth: none. Request: `registration_id` UUID and six-digit `code`.
- Success: `200` with `data.state=verified` and `data.provisioning=complete`. Provisioning creates exactly one User, Business, owner membership, default Outlet, and active Free Subscription. No bearer token is issued.
- Errors: `422 OTP_INVALID`, `OTP_MAX_ATTEMPTS`, `PENDING_EMAIL`, `AUTH_PROVISIONING_PENDING`; `410 OTP_EXPIRED`, `OTP_REPLACED`, or `OTP_ALREADY_USED` where applicable.
- Retry: wrong codes are rate-limited/lockable; do not retry after `OTP_MAX_ATTEMPTS` without the documented recovery path.
- State: continue to manual v2 login.

### `POST /api/v2/auth/otp/resend`

- Auth: none. Request: `registration_id` UUID.
- Success: `202` with generic message and registration state/timestamps; no token.
- Errors: `422` for unavailable/pending state, `429 OTP_RESEND_COOLDOWN` or `OTP_RATE_LIMITED` with `Retry-After`, `503 OTP_DELIVERY_FAILED`.
- Retry: honor `Retry-After` and `data.resend_available_at`. A successful resend replaces the old code.
- State: remain in OTP verification.

### `POST /api/v2/auth/login`

- Auth: none. Request: `{ "email": "...", "password": "..." }`.
- Success without confirmed 2FA: `200` with `data.token_type=Bearer`, `data.token`, and `data.user {id,name,email}`. Token has `mobile` ability and the configured v2 lifetime (default 30 days).
- Confirmed 2FA: `202 TWO_FACTOR_REQUIRED` with `data.challenge_id`, `data.expires_at`, and `data.methods`; no bearer token.
- Errors: `401` invalid credentials or 2FA code; `403 EMAIL_NOT_VERIFIED`, `AUTH_PROVISIONING_PENDING`, or `TWO_FACTOR_REQUIRED`; `429` rate limit with `Retry-After` where emitted.
- Retry: apply server cooldown; never treat a `202` challenge as an authenticated session.
- State: save a token only after `200`; otherwise open the 2FA UI.

### `POST /api/v2/auth/google`

- Auth: none. Request: `{ "id_token": "<Google ID token>", "client_platform": "android" }`, where `client_platform` is `web` or `android`.
- Success without 2FA: `200` with `message`, `data.token_type=Bearer`, `data.token`, `data.expires_at`, and `data.user {id,name,email}`.
- Confirmed 2FA: `202 TWO_FACTOR_REQUIRED` with challenge data and no bearer token.
- Errors: `401` token/signature/issuer/audience/expiry/replay failures; `403 GOOGLE_EMAIL_UNVERIFIED`, `EMAIL_NOT_VERIFIED`, or `AUTH_PROVISIONING_PENDING`; `409 ACCOUNT_LINK_REQUIRED` or `IDENTITY_CONFLICT`; `503 GOOGLE_AUTH_NOT_CONFIGURED` or provisioning failure.
- Retry: never retry the same Google assertion after a replay response. Retry provider-unavailable responses only with a fresh assertion and safe state check.
- State: Google subject is the immutable identity key; Google-only users do not receive password login automatically.

### `POST /api/v2/auth/2fa/verify`

- Auth: none; request contains the short-lived challenge. Request: `{ "challenge_id": "<opaque challenge>", "method": "totp|recovery_code", "code": "<code>" }`.
- Success: `200` with `data.token_type=Bearer`, `data.token`, `data.expires_at`, and `data.user`.
- Errors: `422 TWO_FACTOR_CHALLENGE_INVALID` or invalid code; `410 TWO_FACTOR_CHALLENGE_EXPIRED` or `TWO_FACTOR_CHALLENGE_USED`; `429 TWO_FACTOR_CHALLENGE_LOCKED` or `TWO_FACTOR_RATE_LIMITED` according to the service policy.
- Retry: honor `Retry-After` when present. Recovery codes are single use; a consumed challenge cannot be retried.
- State: persist the token only after success and then call `/api/v2/auth/me`.

### `GET /api/v2/auth/me`

- Auth: `auth:sanctum` plus `mobile` ability.
- Success: `200` with `data.user`, `data.email_verified`, `data.two_factor_enabled`, and `data.authentication_methods` (for example `manual`, `google`).
- Errors: `401` unauthenticated/expired/revoked token; `403` mobile ability required.
- Token effect: none. State: refresh the authenticated user state.

### `DELETE /api/v2/auth/logout`

- Auth: `auth:sanctum` plus `mobile` ability.
- Success: `200 {"message":"Logged out successfully."}`.
- Errors: `401` or `403` for missing/incorrect token ability.
- Token effect: revokes only the current token; other device tokens remain active.
- State: clear the current cloud session from secure storage, not offline data or pending sync work.

## Linking and recovery endpoints

### `POST /api/v2/auth/reauthenticate`

- Auth: authenticated Sanctum token; the controller requires a mobile-capable token.
- Request: `{ "password":"...", "totp_code":"123456" }`; `totp_code` is required by service policy when confirmed 2FA is enabled.
- Success: `200` with `data.reauthentication_token`, `data.expires_in`, and `data.purpose`.
- Errors: `401 INVALID_CREDENTIALS`; `403 REAUTHENTICATION_REQUIRED`, `TWO_FACTOR_REQUIRED`, `REAUTHENTICATION_EXPIRED`, or `REAUTHENTICATION_ALREADY_USED`; `429 REAUTHENTICATION_RATE_LIMITED`.
- Token effect: no Sanctum token change; returned grant is short-lived, single-use, and bound to the current user/token.
- State: hold the grant only in memory until the next link request.

### `POST /api/v2/auth/google/link`

- Auth: authenticated mobile Sanctum token.
- Request: `{ "reauthentication_token":"...", "id_token":"<Google ID token>", "client_platform":"android" }`.
- Success: `200` with `message` and `data {provider:"google", linked:true|false}`. Existing matching identity is idempotent (`linked:false`).
- Errors: `401` invalid Google proof/token; `403 REAUTHENTICATION_REQUIRED`, `REAUTHENTICATION_EXPIRED`, `REAUTHENTICATION_ALREADY_USED`, or `TWO_FACTOR_REQUIRED`; `409 IDENTITY_ALREADY_LINKED` or `IDENTITY_CONFLICT`; `503 GOOGLE_AUTH_NOT_CONFIGURED`.
- Token effect: current Sanctum token remains; identity ownership is changed only on successful link.
- State: refresh authentication methods. No unlink operation is introduced by AUTH-D09.

### `POST /api/v2/auth/password/forgot`

- Auth: none. Request: `{ "email":"user@example.com" }`.
- Success for eligible, unknown, Google-only, pending, and broker-throttled addresses: `202` with the same generic message `Jika akun memenuhi syarat, instruksi pemulihan telah dikirim.`
- Errors: `422` only for request validation; route IP throttling may return `429` independently of account existence.
- Token effect: no token is returned. A successful eligible request sends the broker notification; broker cooldown prevents another notification.
- Retry: honor route-level cooldown; do not use response differences to infer account existence.
- State: wait for email, then submit the reset token. Do not activate a new session before reset completion.

### `POST /api/v2/auth/password/reset`

- Auth: none. Request: `{ "email":"...", "token":"...", "password":"...", "password_confirmation":"..." }`.
- Success: `200 {"message":"Password berhasil diubah. Silakan masuk kembali."}`.
- Errors: `422 PASSWORD_RESET_INVALID`, `410 PASSWORD_RESET_EXPIRED`, or `422` validation failure.
- Token effect: password reset invalidates existing Sanctum sessions; no bearer token is issued. TOTP state and business membership remain.
- Retry: expired/reused tokens cannot be retried; request a new reset link.
- State: require fresh login.

### `POST /api/v2/auth/email/change/request`

- Auth: `auth:sanctum` plus `mobile` ability.
- Request: `new_email` plus one proof path: `current_password`, or fresh Google `id_token` and `client_platform`; include `totp_code` when confirmed 2FA requires it.
- Success: `202` with `message` and `data {change_id,state:"pending_verification",otp_expires_at,resend_available_at}`. No token is issued.
- Errors: `401 INVALID_CREDENTIALS`; `403 TWO_FACTOR_REQUIRED`, `REAUTHENTICATION_REQUIRED`, or `MOBILE_ABILITY_REQUIRED`; `409 EMAIL_ALREADY_USED`, `EMAIL_CHANGE_PENDING`, or `EMAIL_CHANGE_CONFLICT`; `429 OTP_RATE_LIMITED`.
- Retry: honor OTP cooldown and pending state. Do not create parallel active changes.
- State: open OTP verification for the initiating user and session only.

### `POST /api/v2/auth/email/change/verify`

- Auth: `auth:sanctum` plus `mobile` ability and the same initiating token/session.
- Request: `{ "change_id":"<uuid>", "code":"123456" }`.
- Success: `200` with `data.state=verified` and `data.reauthentication_required=true`.
- Errors: `401` unauthenticated; `403 MOBILE_ABILITY_REQUIRED`; `409 EMAIL_CHANGE_CONFLICT`; `410 EMAIL_CHANGE_EXPIRED`; `422` invalid OTP; `429 OTP_RATE_LIMITED`.
- Token effect: all Sanctum sessions are revoked after the change; no replacement token is issued.
- State: update the local email only after success, clear the cloud session, and require a fresh login. Old password-reset tokens are invalidated.

### `GET /api/mobile/context`

- Auth: `auth:sanctum` with a token that has `mobile` ability. Optional query: `device_identifier`.
- Success: `200` with `data.user` and `data.businesses[]`. Each business includes `id`, `name`, `role`, `business_type`, `sync_capabilities`, `subscription`, `cloud_access`, `outlets[]`, and optional `device_context`.
- Errors: `401` unauthenticated; `403 MOBILE_TOKEN_REQUIRED`.
- Token effect: none. State: load cloud/business context after a valid token; preserve offline data on failure.

## Required mobile state mapping

| Backend response | Mobile action |
|---|---|
| `202` `pending_email` | Open OTP verification |
| `200` `verified` | Continue to manual login |
| `202` `TWO_FACTOR_REQUIRED` | Open TOTP/recovery UI; do not mark session active |
| `200` with Bearer token | Save token in secure storage, then load cloud context |
| `401` authenticated API | Treat cloud session as invalid; preserve offline data |
| `403 CLOUD_SUBSCRIPTION_REQUIRED` | Show entitlement state; do not erase offline data. This code belongs to downstream cloud policy, not the auth controllers above. |
| `409 ACCOUNT_LINK_REQUIRED` | Show account-linking path |
| `429` | Apply `Retry-After` or safe cooldown |
| `503` mail/OIDC unavailable | Retry safely after state review; do not assume success |

Authentication, business membership/linking, device registration, and Cloud entitlement are separate states. A valid auth token does not grant Cloud entitlement, and Cloud logout must not delete the local SQLite workspace or sync queue.

## Legacy API compatibility

The following routes remain unchanged for compatibility:

- `POST /api/auth/register`: accepts name/email/password/business name, returns `201` with a mobile bearer token, and auto-verifies the email. This is an acknowledged rollout risk and is not OTP-protected.
- `POST /api/auth/login`: returns `200` with a token, or `401` invalid credentials, `403 EMAIL_NOT_VERIFIED`, `403 TWO_FACTOR_REQUIRED` for confirmed 2FA.
- `GET /api/auth/me`: authenticated Sanctum profile response with `data.id`, `data.name`, and `data.email`.
- `DELETE /api/auth/logout`: deletes the current Sanctum token and returns `200` with `Logged out successfully.`

Do not describe the entire registration surface as OTP-protected until a separately approved legacy retirement or migration plan is completed.

## Mobile integration test scenarios

Before release, `pos-mobile` should test the v2 flows with mocked mail/JWKS and no production credentials: pending registration, idempotent replay/conflict, OTP expiry/lockout/resend, manual and Google login, 2FA challenge/recovery, mobile ability enforcement, linking grant binding, generic password recovery, email-change session revocation, context loading, `401/403/409/429/503` transitions, secure token storage, and offline data preservation.
