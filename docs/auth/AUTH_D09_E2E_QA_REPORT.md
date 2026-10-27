# AUTH-D09 Backend E2E QA Report

## Result

- Tested commit: `d966cae057f9fe8132084a11049bf582e925dd46`
- Branch: `feat/auth-d09-e2e-qa-mobile-handoff`
- Repository: `sawal123/pos_dashboard`
- QA environment: Windows PowerShell, PHP 8.4.16, Laravel 13.26.1, Composer 2.8.10.
- Automated tests: SQLite, array cache, array mailer, no production credentials.
- Application environment during preflight: local, debug enabled. No production configuration was changed.
- MariaDB service exists locally, but no disposable isolated AUTH-D09 database was provisioned. Cross-process database races were not run.

## Executed tests

| Suite | Result | Tests | Assertions | Coverage observed |
|---|---:|---:|---:|---|
| `ManualRegistrationOtpTest` | PASS | 20 | 172 | Pending registration, idempotency, collisions, OTP verification, lockout, resend, provisioning, and pending-login rejection |
| `EmailOtpInfrastructureTest` | PASS | 14 | 68 | OTP digest storage, delivery failure, cooldown, replacement, expiry, attempts, cache-backed limits |
| `GoogleAuthenticationTest` | PASS | 16 | 69 | Provisioning, repeated subject, collisions, RSA/JWK verification, issuer/audience/azp/expiry, replay and cache failure |
| `GoogleAccountLinkingTest` | PASS | 10 | 54 | Reauthentication, TOTP, grant binding/expiry/reuse, subject conflicts, pending registration |
| `MobileAuthTest` | PASS | 4 | 28 | Mobile token, 2FA challenge, recovery-code consumption, ability and current-token logout |
| `PasswordRecoveryTest` | PASS | 9 | 41 | Reset request, generic enumeration response, broker cooldown, token reuse/expiry, token revocation, 2FA preservation |
| `EmailChangeTest` | PASS | 4 | 30 | OTP email change, proof/2FA/collision, mixed-case reset-token invalidation, session revocation |
| `ApiAuthenticationTest` | PASS | 11 | 45 | Legacy API authentication compatibility |
| `AuthSecurityHardeningTest` | PASS | 2 | 10 | Security audit channel and preflight reporting |
| `ProductionPreflightTest` | PASS | 12 | 20 | Production policy checks, audit channel effectiveness, strict mode |

Targeted total: **102 tests, 537 assertions, all passed**.

Full `composer ci:check`: **PASS** — 1,756 tests, 1,748 passed, 8 skipped, 7,430 assertions. PHPStan reported 0 errors and Pint passed.

## QA matrix

### Manual registration and OTP — PASS (automated SQLite coverage)

The executed suite verifies pending state before OTP, no Sanctum token before verification, valid and invalid six-digit codes, five-attempt lockout, resend cooldown and replacement, idempotency replay/conflict, email collision decoys, provider delivery failure, pending-login rejection, and exactly one default business, owner membership, outlet, and Free subscription after provisioning.

The suite also verifies that a concurrent-style repeated verification is idempotent at the application level. A real cross-process race on MySQL/MariaDB was not executed.

### Google OIDC — PASS (local fixtures and mocked verifier/JWKS paths)

The executed suite covers new and repeated Google subjects, manual-email collision requiring linking, immutable subject ownership, RSA/JWK signature checks, wrong issuer, audience and authorized presenter, expiry, unknown `kid`, equivalent token encoding replay, replay reservation failure, and Google-only provisioning. Tests use local RSA fixtures and test doubles; no Google production account was contacted.

JWKS timeout/rotation and parallel cross-process reservation remain staging items because no external provider or shared production-like cache was used in this run.

### Account linking — PASS (automated feature coverage)

The suite verifies manual-account reauthentication, invalid password, confirmed-2FA TOTP, grant expiry, single use, token/device binding, pending-registration rejection, existing identity ownership, and subject conflict. Tenant, business, role, outlet, subscription, and token invariants are asserted by the tests.

Cross-process concurrent linking was not run without an isolated MySQL/MariaDB fixture.

### Sanctum and 2FA — PASS (automated feature coverage)

The executed tests verify manual and Google login token/challenge behavior, confirmed 2FA without a bearer token, TOTP and recovery-code completion, single-use challenge/recovery behavior, mobile ability enforcement, 30-day token configuration, expired/revoked token rejection, and current-token-only logout. No Google path bypassed the 2FA branch covered by the tests.

### Password recovery — PASS (automated feature coverage)

Verified manual and linked accounts can request and complete reset. Google-only and unknown addresses receive the same generic response. Broker throttling does not produce a distinguishable public response or a second notification. Reset expiry/reuse, old-password rejection, Sanctum revocation, TOTP preservation, and unsafe-mailer rejection are covered.

### Email change — PASS (automated feature coverage)

The suite verifies password/Google proof, TOTP, OTP delivery to the new address, invalid OTP non-mutation, collision rejection, mixed-case original email reset-token deletion, old-token rejection, session revocation, and preservation of the linked Google subject.

### Tenant isolation and business authorization — PASS for exercised regression coverage

Authentication tests assert that provisioning creates one tenant and that account linking/password recovery do not mutate identity or tenant state. Existing business/cloud/sync regression suites were not rebuilt as part of AUTH-D09. A full multi-process tenant race audit was not run.

### Shared cache — PASS for configured/application-level checks; cross-process validation NOT RUN

The tests exercise database-backed cache paths and replay/idempotency failure handling. The test runner itself uses array cache unless a specific test overrides it. Production-like shared-cache atomicity requires staging validation with the configured shared store.

### SMTP/JWKS failure paths — PASS with test doubles

Unsafe mailer rejection, OTP delivery failure, verifier failures, invalid keys, unknown `kid`, and replay-cache failure are covered without calling production SMTP or Google services. Real SMTP delivery and live JWKS outage recovery were not run.

## Production preflight

Executed:

```text
php artisan config:cache
php artisan deploy:preflight --strict
```

The command ran and correctly failed for the local environment with six failures: non-production `APP_ENV`, debug enabled, insecure session cookie setting, unsafe log mailers, and missing AUTH schema requirements. The audit channel itself passed the effectiveness check, and configuration caching succeeded. This is **NOT a production approval**.

## MySQL/MariaDB concurrency

**NOT RUN — ENVIRONMENT NOT VERIFIED AS ISOLATED/DISPOSABLE.** The machine has a MariaDB service and the local `.env` points at the project database, but AUTH-D09 did not create or migrate a separate disposable database. No destructive migration or race workload was run against that database.

Required staging procedure:

1. Provision a disposable MySQL 8 or MariaDB database and separate test credentials.
2. Run migrations in that database only.
3. Configure a shared atomic cache and test mail/JWKS doubles.
4. Start two or more independent PHP processes/connections for OTP verification, recovery-code consumption, 2FA completion, Google provisioning/linking, reset-token use, email-change verification, and replay reservation.
5. Assert unique constraints and final database invariants, not only HTTP status.

## Security review

- Audit fields remain allow-listed and account identifiers remain HMAC-protected.
- Passwords, OTPs, bearer tokens, Google JWTs, reset tokens, and credentials are not returned or written by the reviewed auth paths.
- Forgot-password responses remain generic for unknown, Google-only, pending, existing, and broker-throttled addresses.
- `LOG_LEVEL=warning` does not filter the dedicated `auth_security` info channel; preflight validates the effective handler.
- Token and challenge identifiers are not accepted as bearer tokens.
- OIDC and password-reset failure paths fail closed in the executed tests.

## Findings and remaining risks

No new P0/P1 authentication defect was found in the executed automated scope. No application code fix was required for AUTH-D09.

Known rollout risks:

1. Legacy `POST /api/auth/register` still auto-verifies the email and issues a token. It remains for compatibility and is not covered by the v2 OTP security claim.
2. MySQL/MariaDB cross-process concurrency is pending isolated staging execution.
3. Production SMTP, live JWKS rotation/outage, trusted-proxy deployment, HTTPS, secure-cookie, shared-cache, and production preflight settings require staging evidence.

## Release readiness

**CONDITIONAL.** Automated backend QA and full CI are green, but production release remains blocked pending an isolated MySQL/MariaDB concurrency run, staging `deploy:preflight --strict` pass, real SMTP/JWKS configuration verification, and an approved legacy-registration retirement/migration decision. No deployment was performed.
