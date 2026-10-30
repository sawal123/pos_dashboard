# AUTH-D04 — Google Sign-In OIDC Backend

## Architecture

The mobile client sends a Google OpenID Connect ID token to `POST /api/v2/auth/google`. The backend uses the maintained `firebase/php-jwt` JWK/JWT implementation to verify the JWT signature against Google's rotating JWKS, then validates the issuer, audience, authorized presenter, timestamps, subject, email format, and `email_verified` claim, and consumes a keyed one-time replay marker in the shared cache. The raw token is never stored or logged.

The backend then maps the immutable Google `sub` claim to `auth_identities(provider, provider_subject)`. Email is only used for the account-collision check; it is never used as the Google identity key.

## Configuration

Set these values in the backend environment, without committing secrets:

```dotenv
GOOGLE_CLIENT_ID=your-server-allowed-oauth-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_ANDROID_CLIENT_IDS=optional,comma,separated,android,client,ids
```

`GOOGLE_CLIENT_ID` is always an allowed server audience, including for Android. Additional Android audiences are accepted only when explicitly listed in `GOOGLE_ANDROID_CLIENT_IDS`; a client-supplied platform never adds an audience by itself. When present, `azp` must also be one of the explicitly configured server/client presenters. The client secret is retained in Laravel configuration for future authorization-code flows, but direct ID-token verification does not need it and it is never sent to mobile or written to logs.

If the required audience is missing, the endpoint returns `GOOGLE_AUTH_NOT_CONFIGURED` with HTTP 503 and performs no database mutation.

### Production 503 triage

`503 GOOGLE_AUTH_NOT_CONFIGURED` means the **resolved** configuration has an empty
`services.google.client_id` (or an empty `app.key` at replay reservation time). It
is not a Google outage. Check in this order:

```bash
php artisan tinker --execute="dump(config('services.google.client_id'));"
php artisan tinker --execute="dump(config('app.key') !== '');"
php artisan config:clear && php artisan config:cache
php artisan deploy:preflight --strict
```

The common cause is a `.env` that never received `GOOGLE_CLIENT_ID`, or a
`config:cache` generated before the value was added — `config()` keeps serving the
stale cached array until `config:cache` runs again. `GOOGLE_ANDROID_CLIENT_IDS`
does not cause a 503 when omitted; a missing Android audience shows up as
`401 GOOGLE_AUDIENCE_INVALID` instead.

### Diagnosing a rejected token

Web and mobile rejections are recorded in `storage/logs/auth-security-*.log` as
`auth.google.verification_failed` with `error_category` and a machine-readable
`reason`. Run the read-only diagnostic on the affected deployment to see the
resolved config, Google JWKS reachability, and — with a token — the exact claim
that failed. Raw tokens, subjects, and emails are never printed:

```bash
php artisan auth:google:diagnose
php artisan auth:google:diagnose --token="<fresh-id-token>" --platform=web
```

Common `reason` values: `jwks_unavailable`/`jwks_http_...` (server cannot reach
`www.googleapis.com`), `jwk_not_found` (stale key cache or unknown `kid`),
`signature_invalid` (wrong token or key), `issued_in_future`/`expired` (server
clock drift), `audience_invalid` (web page and `GOOGLE_CLIENT_ID` use different
clients), `replay_detected` (the same ID token was submitted twice), and
`email_unverified`. A token consumed by this command cannot be used for login
afterwards; request a fresh one.

## Google Cloud setup

Create the OAuth client IDs in the correct Google Cloud project. Configure the web client ID as `GOOGLE_CLIENT_ID`, and configure Android package/signing-certificate client IDs explicitly in `GOOGLE_ANDROID_CLIENT_IDS` when Android tokens use a different audience. Keep staging and production client IDs separate and use separate environment values.

The backend trusts only Google's JWKS endpoint over normal TLS validation. RSA JWKs are parsed from the provider's `kid`/`kty`/`alg`/`n`/`e` values using the maintained JWT library; an `x5c` certificate chain is not required. JWKS responses are cached according to the provider `Cache-Control` max-age, with a bounded fallback, and are refreshed once on an unknown key ID to support key rotation.

## API

Request:

```http
POST /api/v2/auth/google
Content-Type: application/json
```

```json
{
  "id_token": "<fresh-google-id-token>",
  "client_platform": "android"
}
```

Success is HTTP 200 and returns a Laravel Sanctum `mobile` ability token. The Google ID token is not used as a POS session token. A new account is verified from the signed Google assertion, receives one active retail Business, owner membership, `Outlet Utama`/`OUT-1`, and an active Free subscription in the same transaction. Google Sign-In does not send the POS email OTP.

Stable error codes are `GOOGLE_TOKEN_INVALID` (401), `GOOGLE_TOKEN_EXPIRED` (401), `GOOGLE_AUDIENCE_INVALID` (401), `GOOGLE_EMAIL_UNVERIFIED` (403), `ACCOUNT_LINK_REQUIRED` (409), `IDENTITY_CONFLICT` (409), `TWO_FACTOR_REQUIRED` (403), and `GOOGLE_AUTH_NOT_CONFIGURED` (503). Provisioning failures fail closed with HTTP 503 and no token.

### Web session login (`/login`)

The dashboard login page renders Google Identity Services (GIS) when
`GOOGLE_CLIENT_ID` resolves to a non-empty value. The browser receives a Google
ID token (`credential`) and posts it to `POST /auth/google` (route
`auth.google.login`, guest + `throttle:10,1`). The controller verifies the token
with `client_platform=web` through the same `GoogleOidcVerifier`, resolves or
provisions the account through `GoogleAccountService`, then creates a normal
Laravel session.

- The Google Cloud OAuth **web** client must list the dashboard origin (for
  example `https://pos.eradig.my.id`) under **Authorized JavaScript origins**.
  No redirect URI is used, and the client secret is not part of this flow.
- A confirmed-2FA user is never logged in directly: the controller stores
  `login.id`/`login.remember` and redirects to Fortify's `two-factor-challenge`,
  so the existing TOTP/recovery-code screen completes the login.
- Email collisions return a form error and require the existing password-login
  plus account-linking flow; a Google identity is never attached by matching an
  email address alone.
- If `GOOGLE_CLIENT_ID` is empty the button is not rendered and the endpoint
  fails closed with a form error.

## Identity and collision policy

`auth_identities` has a unique `(provider, provider_subject)` constraint and a foreign key to `users`. An existing Google subject always resolves to its original user. If the subject is new but the verified email already belongs to a user, the backend returns `ACCOUNT_LINK_REQUIRED` without changing the existing account or creating an identity. Account linking is AUTH-D05.

Provisioning is performed only while creating a new identity. The identity, verified user, membership, outlet, and subscription are committed atomically. Repeated login therefore issues another Sanctum token for the same user without creating another tenant or subscription. Concurrent creation is protected by the database uniqueness constraints and transaction handling.

Google-only accounts receive a cryptographically random password hash. The random value is not returned or logged; password login is unavailable until a separate, authorized password-setting flow exists.

## 2FA behavior

An existing user with confirmed 2FA receives `TWO_FACTOR_REQUIRED` and no bearer token. AUTH-D04 does not implement a new mobile TOTP challenge and does not bypass existing Fortify 2FA. Mobile 2FA completion is AUTH-D06.

## Verification checklist

- Signature is verified with RSA SHA-256 and a Google JWKS key; `none` and algorithm mismatches are rejected.
- Issuer, configured audience, `azp` when present/relevant, expiration, issued-at skew, subject, email, and `email_verified=true` are required.
- A keyed cache marker atomically rejects reuse of the same raw ID token until its expiry window.
- Cache and provider failures fail closed; no raw token, access token, refresh token, or client secret is persisted.
- Endpoint throttling is enabled independently of token verification.
- Database provisioning is atomic and idempotent by the provider identity constraint.

The replay marker is intentionally one-time because this endpoint does not receive a server-generated nonce from the client flow. Mobile must request a fresh Google ID token when retrying after a successful exchange; the backend does not claim nonce binding for a client flow that does not supply a nonce.

## Testing and mobile requirements

CI uses a fake verifier at the service boundary for account/provisioning tests and isolated test keys plus a mocked JWKS response for signature tests. It does not call Google production.

AUTH-M04 must send a fresh ID token, identify the configured client platform, handle the stable error codes, retain no Google token as a POS session, and route `ACCOUNT_LINK_REQUIRED` to the future AUTH-D05 linking flow. It must not treat an ID token as a Sanctum token and must not retry a consumed token indefinitely.

Remaining risks are limited to the client needing a fresh token after replay rejection and the requirement to deploy matching audience allow-lists for every environment. Account linking and mobile 2FA completion remain intentionally out of scope.
