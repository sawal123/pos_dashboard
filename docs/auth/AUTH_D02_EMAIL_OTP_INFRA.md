# AUTH-D02 — Secure Email OTP Infrastructure

AUTH-D02 adds an isolated OTP challenge, policy, delivery boundary, and testable service for AUTH-D03. It does not add routes, call the existing registration/login flows, update email_verified_at, provision accounts, issue bearer tokens, or change Fortify/Sanctum behavior.

## Configuration

The new config/auth_otp.php file contains the security defaults:

| Setting | Default | Purpose |
|---|---:|---|
| purpose | email_verification | Prevents a challenge issued for one operation being used for another. |
| code_length | 6 | ASCII numeric OTP length. |
| ttl_minutes | 10 | Challenge lifetime. |
| max_attempts | 5 | Failed verification attempts before lock. |
| resend_cooldown_seconds | 60 | Minimum interval between sends for one subject. |
| key | AUTH_OTP_KEY, then domain-separated APP_KEY derivation | Keyed HMAC material for OTP verifiers and cache identifiers. |
| mailer | AUTH_OTP_MAILER, then MAIL_MAILER | Explicit delivery mailer selected by the service. |

AUTH_OTP_KEY should be a deployment secret with at least 32 random bytes encoded for the deployment environment. If it is omitted, the service derives a domain-separated key from APP_KEY; if neither is present it fails closed. Do not log or commit either value.

AUTH-D03 must complete validation before opening its provisioning transaction. This keeps database-backed rate limiting and durable failed-attempt accounting outside the provisioning lock boundary. The default database connection is used for both validation and consumption; no second OTP database connection is required.

### Safe mail delivery

The service permits only smtp, ses, postmark, resend, or sendmail for real delivery. log, array, failover, and roundrobin are rejected because an OTP could be written to logs, discarded, or fall back to an unsafe transport. Mail::fake() is allowed for automated tests.

For shared hosting, configure one synchronous Laravel mailer and verify the sender domain, TLS, credentials, and outbound connectivity before enabling AUTH-D03. This task deliberately does not enable queue delivery: a queue worker is not guaranteed by the repository deployment assumptions, and a queued message would make database challenge state and delivery state harder to reason about. If a later task introduces a queue, it must define retry, expiry, and a failed-delivery state first.

The repository default MAIL_MAILER=log is therefore safe for non-OTP development but intentionally unusable for production OTP issuance. No .env or credential was changed by AUTH-D02.

## Internal service contract

App\Services\Auth\EmailOtpService exposes infrastructure-only methods:

- issue(subjectReference, email, purpose, ipAddress) creates the first challenge or returns an existing unexpired challenge for an idempotent retry.
- resend(subjectReference, email, purpose, ipAddress) enforces cooldown, sends a replacement, then invalidates the old challenge.
- validate(challengeId, subjectReference, purpose, code, ipAddress) applies format/rate limits and durably records invalid attempts. A valid code returns an internal validation result but is not consumed.
- consume(validationResult) is the transaction-composable consume primitive. AUTH-D03 must pass the result from validate inside the same transaction as email verification and provisioning.
- verify(...) is a standalone compatibility helper that calls validate then consume. It is not the integration contract for a provisioning transaction.
- state(challengeId, subjectReference, purpose) returns only non-secret state.

The subject reference must be an opaque pending-registration identifier supplied by AUTH-D03. The service never looks up or mutates a User and has no Business, Outlet, Subscription, route, token, or email_verified_at dependency. A caller must not pass a raw code to consume; this prevents the old unsafe path from bypassing durable attempt accounting.

## Persistence and concurrency

auth_otp_challenges stores the normalized recipient email, keyed HMAC digest, nonce, timestamps, attempt counters, lock/consume/replacement state, and an active_key. The OTP plaintext is never persisted. The active_key is nullable and unique: historical rows can coexist while the database guarantees at most one active row for a subject/purpose pair.

Validation first checks that the default database connection has no active transaction, before touching the cache or OTP database. If called inside an active transaction it fails closed with OTP_VALIDATION_TRANSACTION_ACTIVE. Outside a transaction it applies rate limiting, locks the challenge row with lockForUpdate(), checks subject/purpose/expiry/consumption/replacement/attempt state, compares HMACs with hash_equals(), increments failures durably, and clears the active key on lock.

The fifth failed attempt locks the challenge before returning OTP_MAX_ATTEMPTS. A valid validation result is only a two-phase verifier; consume re-locks the challenge and re-checks current state and the keyed digest before clearing the active key. Therefore a replacement, expiry, lock, replay, or concurrent consume fails closed.

Consume uses the default database connection inside the caller's provisioning transaction, making successful OTP consumption atomic with AUTH-D03 verification/provisioning. If that transaction rolls back, the valid OTP remains available for a safe retry.

Resend locks the current active row. It creates a non-active replacement, sends the email while the old challenge remains active, and switches active state only after send succeeds. If the mail provider throws, the transaction rolls back and the old challenge remains usable. A database commit failure after an SMTP server accepts a message is an unavoidable external consistency boundary and must be handled by AUTH-D03 as a safe retry/reconciliation case.

Full cross-process lock behavior must be verified on the production database engine. The default SQLite test database can verify the unique active-key constraint and transaction semantics, but does not reproduce every production row-lock interleaving.

## Rate limits

Laravel RateLimiter is used directly by the service. Keys contain keyed digests, not plaintext emails or subject references. Limits are applied independently to subject, normalized email, and IP for issue/resend, and to subject/IP for verification. AUTH-D03 must pass the request IP; it must not bypass limits by generating a new challenge ID.

## AUTH-D03 integration requirements

AUTH-D03 must:

1. create and persist an opaque pending-registration subject before calling issue;
2. map OtpException::$errorCode to the public API error matrix without returning exception messages as a security contract;
3. call validate before opening the provisioning transaction, then pass its result to consume inside the same transaction that sets email_verified_at and provisions the account;
4. issue no bearer token until the outer transaction commits successfully;
5. use the returned challenge metadata only; never expose the mailable code or model digest in HTTP responses;
6. preserve the idempotent subject on retries and never create a second active challenge for one subject/purpose.
