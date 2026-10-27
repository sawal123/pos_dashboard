# AUTH-D08 - Authentication Security Hardening

This document records the scoped hardening applied to the Cloud authentication
surface from AUTH-D02 through AUTH-D07. It does not change POS-Mobile, billing,
sync, backup, entitlement, or tenant authorization behavior.

## Findings and severity

| Finding | Severity | Result |
| --- | --- | --- |
| Replay fingerprint depended on the raw Google JWT string | P1 | Fixed with canonical signed input plus decoded signature bytes and atomic cache reservation |
| firebase/php-jwt used zero leeway while application policy allowed 60 seconds | P2 | Fixed with request-scoped leeway and matching application checks |
| Password login lacked an account/email bucket | P1 | Fixed with a keyed per-account plus IP limiter in addition to route throttling |
| Password reset could create a token with unsafe mail delivery | P1 | Fixed with safe-mailer preflight and token cleanup on delivery failure |
| Authentication security events had no safe structured audit path | P2 | Fixed with an allow-listed, best-effort audit logger |
| Production preflight did not validate AUTH mail, Google, cache, or schema settings | P1 | Fixed with fail-closed AUTH checks |

Existing enumeration protections, OTP cooldowns, challenge locks, Google
identity ownership, and the AUTH-D07 API contracts are preserved.

## Google JWT replay policy

`GoogleOidcVerifier` continues to require RS256, a configured `kid`, Google
JWKS validation, issuer, audience, `azp` when applicable, expiry, issued-at,
and verified email. It never trusts unsigned claims or email as an identity
key.

After signature validation, replay material is derived from:

1. canonical unpadded Base64URL header bytes;
2. canonical unpadded Base64URL payload bytes; and
3. the decoded signature bytes.

The material is protected with an HMAC using `APP_KEY`. The raw JWT is never
stored in cache or logs. `Cache::add()` reserves the marker atomically; a
reservation failure rejects the authentication attempt. The marker lifetime
covers the configured token lifetime plus the accepted clock skew and is at
least the configured replay TTL.

The replay cache must be shared across application workers and hosts. The
database, Redis, Memcached, and an appropriately shared file cache are
supported by the preflight policy; array, null, failover-to-array, and local
process-only stores are rejected for production.

## Clock skew policy

The single configured policy is `services.google.clock_skew_seconds`, default
60 seconds and hard-capped at 300 seconds. The value is applied to `exp`,
`iat`, and `nbf`. The firebase/php-jwt static leeway is set only around the
individual decode call and restored in a `finally` block, so another request
cannot inherit the value. Application checks use the same boundary policy.

Google tokens are also limited to the configured maximum lifetime, default one
hour. Missing or malformed timestamps, future timestamps outside the skew,
and expired timestamps outside the skew fail closed.

## Rate-limit matrix

| Operation | Existing route limit | Service/account control |
| --- | --- | --- |
| Login | 6 per minute per route/IP | 10 per minute per normalized email + IP, keyed with HMAC |
| Google login | 10 per minute per route/IP | Replay reservation; invalid tokens never provision |
| Registration | 6 per minute per route/IP | Idempotency reservation and OTP subject/email/IP buckets |
| OTP verify/resend | 30/10 per minute per route/IP | Challenge, subject, email, and IP buckets; five-attempt lock |
| 2FA verify | 10 per minute per route/IP | Challenge and account/IP buckets; five-attempt challenge lock |
| Reauthentication/linking | 5/10 per minute per route/IP | User/IP keyed reauthentication control and one-time grants |
| Password recovery | 6/10 per minute per route/IP | 60-second Laravel broker cooldown; generic public response |
| Email change | 10/30 per minute per route/IP | User/IP request limit and shared OTP controls |

All identifier-derived service keys use keyed digests. No plaintext email,
OTP, challenge, bearer token, or reset token is used as a rate-limit key.
`Retry-After` is returned where the existing OTP or authentication limiter has
a safe retry interval. Trusted-proxy configuration remains an operations
responsibility: only explicitly trusted proxy addresses may be configured so
untrusted forwarded headers cannot spoof the client IP used by route limits.

## OTP and SMTP safe-delivery policy

AUTH OTP delivery rejects `log`, `array`, `failover`, and `roundrobin` mailers.
`Mail::fake()` remains allowed in automated tests. Password reset delivery now
uses the same real-mailer allow-list and validates the sender configuration
before asking Laravel's Password Broker to create a token. If a provider fails
after token creation, the token is removed and the public forgot-password
response remains generic.

OTP creation and replacement remain transactional: a delivery failure does
not verify an account and does not replace a usable prior challenge. Manual
registration remains recoverable after provider failure and does not duplicate
business provisioning on retry. No password, OTP, reset token, Google JWT,
SMTP password, bearer token, or authorization header is written by the AUTH
audit code.

## Safe audit event schema

`AuthSecurityAuditService` writes to the configured Laravel log channel using
the event name, timestamp, result/status, error category, provider, client
platform, retry interval, and safe aggregate counters. When an account
identifier is useful, only an HMAC of the user ID or normalized identifier is
included. Logging failures are swallowed and never turn successful
authentication into a failure. Replay reservation remains fail-closed because
that is an authentication enforcement operation, not merely telemetry.

Implemented events include Google verification/replay failures, login success
and failure, OTP delivery/rate-limit failures, password reset request and
completion, and email-change completion.

## JWKS and upstream failure behavior

JWKS retrieval uses a five-second HTTP timeout, validates a successful JSON
response, parses only RSA RS256 signing keys, and caches provider-provided
max-age with bounded one-minute to one-day limits. Unknown `kid` values cause
one bounded refresh; missing keys, malformed JWKS, invalid signatures,
unsupported algorithms, issuer/audience errors, and provider failures reject
the request. No stale key, unsigned claim, email match, account provisioning,
identity link, or Sanctum token is used as a fallback.

## Production preflight

Run after configuration is cached:

```bash
php artisan config:cache
php artisan deploy:preflight --strict
```

In addition to the existing environment, HTTPS, session, database, logging,
and cache checks, AUTH-D08 validates:

- optional Google client IDs when Google Sign-In is enabled;
- real mail transports for both the default mailer and AUTH OTP mailer;
- a valid AUTH sender address;
- a shared atomic cache suitable for replay and throttling; and
- the AUTH OTP, mobile challenge, email-change, and password-login schema.

Google is optional: an entirely empty Google client configuration is allowed.
If any Google client is configured, the server audience and every Android
audience must use the `*.apps.googleusercontent.com` format. Production must
use a shared cache and a real mail transport; no real credentials are stored
in this repository or this document.

## Shared-hosting compatibility

The hardening uses Laravel's existing cache, database, synchronous mail, and
rate-limiter infrastructure. It does not require Redis, a queue worker, a
supervisor, a daemon, or a paid monitoring service. Shared hosting may use
synchronous SMTP delivery, provided `deploy:preflight --strict` passes and
the configured mail provider is reachable. A database cache is acceptable
when all web processes use the same database and the cache migration is
applied. Email must not be made queue-only for these flows.

## Safe errors and account enumeration

Public forgot-password requests remain the same generic HTTP 202 for unknown,
ineligible, eligible, and broker-throttled addresses. Invalid login remains a
generic 401 response, while route/global throttles may return a consistent
429. Google verification errors never include provider response details. API
responses do not include credentials, raw tokens, exception traces, or mail
configuration values.

## Deployment and rollback

1. Review the branch and configuration without copying secrets into source.
2. Apply the already-required AUTH-D02 through AUTH-D07 migrations.
3. Configure a shared cache, safe mailers, sender address, Google audiences if
   enabled, and the production environment values.
4. Run `config:cache`, then `deploy:preflight --strict`.
5. Run the AUTH regression suite and a staging login/OTP/Google smoke test.

The changes are additive and preserve existing tables and data. Roll back by
deploying the previous application artifact only if it does not silently
disable an already-required security control; otherwise fix configuration and
redeploy this hardening. Do not remove replay markers, OTP protections, or
safe-mail checks as an emergency shortcut. No destructive migration is part
of AUTH-D08.

## Verification and limitations

The regression suite covers canonical replay, duplicate replay rejection,
invalid signatures, unknown keys, scoped clock skew, cache failure, password
reset mail safety, login rate limiting, OTP safety, enumeration contracts,
and pre-existing AUTH flows. CI uses deterministic local RSA/JWKS fixtures and
does not call Google production.

SQLite tests prove the application behavior but do not prove cross-process
locking on MySQL/MariaDB. AUTH-D09 must run the same replay, database-cache,
OTP, and concurrent-login cases against the supported MySQL/MariaDB version
and verify trusted-proxy deployment settings.

## AUTH-D09 handoff

QA should validate production-like shared cache atomicity, JWKS rotation and
timeout behavior, SMTP provider failure/retry behavior, proxy IP handling,
and concurrent authentication requests. Confirm that logs contain only the
allow-listed audit fields and that no reset token, OTP, JWT, or bearer token
appears in application or mail-provider logs.
