# AUTH-D09 Release Readiness Checklist

Status is based on commit `d966cae057f9fe8132084a11049bf582e925dd46`. No production deployment was performed.

## Automated gates

- [x] Targeted auth QA suites pass: 102 tests, 537 assertions.
- [x] `composer ci:check` passes: 1,756 tests, 1,748 passed, 8 skipped, 7,430 assertions.
- [x] PHPStan reports 0 errors.
- [x] Pint passes.
- [x] `git diff --check` passes.
- [x] No application code change was required for AUTH-D09.

## Staging configuration

- [ ] `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] HTTPS `APP_URL`, secure/HTTP-only cookies, and trusted proxies are configured and tested from the real ingress path.
- [ ] `php artisan config:cache` succeeds in the staging release image.
- [ ] `php artisan deploy:preflight --strict` passes with zero failures.
- [ ] Staging uses isolated data, cache, mail, OIDC, and encryption credentials.

The local preflight was intentionally run without changing secrets. It failed because the environment is local/debug, uses unsafe mail settings, has insecure session-cookie configuration, and does not satisfy production AUTH schema checks. The audit-channel effectiveness check passed.

## SMTP and OTP

- [ ] `AUTH_OTP_MAILER` and `MAIL_MAILER` use the approved credential-delivery transport, not `log`, `array`, or unsafe failover.
- [ ] Sender address/domain and SPF/DKIM/DMARC are verified.
- [ ] OTP delivery, resend cooldown, expiry, replacement, lockout, and provider failure are smoke-tested.
- [ ] Password-reset mailer passes the safe-delivery preflight.
- [ ] No OTP, password, reset token, bearer token, or Google JWT appears in logs or responses.

## Google OIDC

- [ ] Production web/Android client IDs and secrets are configured without committing secrets.
- [ ] Issuer, audience, `azp`, signature, expiry, future-time, unverified-email, unknown-`kid`, replay, and JWKS outage paths are tested with staging doubles.
- [ ] JWKS cache and bounded refresh behavior are confirmed.
- [ ] Shared replay reservation cache is atomic and available to all application workers.
- [ ] No production Google account is used for automated QA.

## Database and concurrency

- [ ] AUTH migrations are applied to the staging MySQL/MariaDB schema.
- [ ] Unique constraints for users, identities, idempotency, challenges, grants, and email changes are verified.
- [ ] Two independent processes/connections race OTP verification, recovery-code consumption, 2FA completion, Google provisioning/linking, reset/email-change verification, and replay reservation.
- [ ] Final invariants show no duplicate tenant provisioning, identity ownership, token issuance, or single-use consumption.

AUTH-D09 did not run these races because the available MariaDB service was not verified as an isolated disposable database. Do not treat SQLite CI as cross-process concurrency evidence.

## Cache and audit logging

- [ ] Production cache is shared and supports atomic reservation/lock operations.
- [ ] `AUTH_AUDIT_LOG_CHANNEL=auth_security` or an explicitly validated equivalent is configured.
- [ ] The dedicated audit handler accepts `info` events even when application `LOG_LEVEL=warning`.
- [ ] `storage/logs/auth-security.log` permissions, rotation, retention, monitoring, and disk alerts are verified.
- [ ] `php artisan deploy:preflight --strict` rejects null, handlerless, or info-filtered audit channels.
- [ ] Audit logs contain only allow-listed fields and HMAC account identifiers.

## Manual smoke test

- [ ] Register v2 account with `Idempotency-Key`; confirm `202 pending_email` and no token.
- [ ] Verify valid OTP; confirm one User, Business, owner membership, Outlet, and Free Subscription.
- [ ] Login manually; confirm bearer token or `202 TWO_FACTOR_REQUIRED` without token.
- [ ] Complete TOTP/recovery code; confirm mobile-only token and `/api/v2/auth/me`.
- [ ] Login with Google fixture; confirm collision/linking and 2FA behavior.
- [ ] Reauthenticate and link Google; confirm tenant state is unchanged.
- [ ] Request password reset for known, unknown, Google-only, and broker-throttled addresses; compare public responses.
- [ ] Change email with proof and OTP; confirm all sessions and old reset tokens are revoked.
- [ ] Load `/api/mobile/context`; verify business roles, outlets, subscription/cloud access, and optional device context.
- [ ] Logout; confirm only the current token is revoked and offline workspace/sync queue remains.

## Backward compatibility and legacy risk

- [ ] Exercise legacy `/api/auth/register`, `/api/auth/login`, `/api/auth/me`, and `/api/auth/logout` clients.
- [ ] Obtain separate approval for the legacy registration route, which still auto-verifies and issues a token.
- [ ] Do not claim that all registration paths are OTP-protected until the migration/retirement plan is approved.
- [ ] Confirm Free users can continue without Cloud entitlement and expired Premium users are not forcibly logged out by auth changes.

## Rollback plan

1. Stop promotion if preflight, SMTP, OIDC, cache, or concurrency checks fail.
2. Revert the application release to the previous approved commit using the normal deployment mechanism.
3. Do not roll back database migrations destructively; use a forward-compatible migration plan.
4. Revoke only affected test/staging credentials and rotate any credential exposed by an operational mistake.
5. Preserve offline mobile data and pending sync queues during cloud rollback/logout.
6. Re-run auth smoke tests and inspect audit logs before resuming promotion.

## Recommendation

**CONDITIONAL / NO-GO for production deployment at this checkpoint.** Backend automated QA and CI are green, but staging production preflight, isolated MySQL/MariaDB concurrency, real SMTP/JWKS configuration, shared-cache verification, and legacy-registration approval remain outstanding.
