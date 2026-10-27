# AUTH-D07 — Password Recovery & Secure Email Change

## Password recovery

`POST /api/v2/auth/password/forgot` accepts an email address and always
returns the generic `202` response for unknown, unverified, pending, and
Google-only accounts. Eligible verified manual accounts use Laravel's `users`
Password Broker, preserving its 60-minute expiry and 60-second broker
throttle. The broker token is single-use and is never returned by the API.

`POST /api/v2/auth/password/reset` validates the broker token and the existing
Fortify password rules. A successful reset does not log the client in. It
rotates the remember token, revokes all Sanctum tokens, invalidates outstanding
mobile 2FA challenges and reauthentication grants, and leaves 2FA, Google
identities, and tenant data unchanged.

Web Fortify reset routes remain available and use the same eligibility policy.
The reset link is built from the server-configured `AUTH_PASSWORD_RESET_URL`
when present, otherwise `APP_URL`; no client-supplied redirect is used.

## Account eligibility

The additive `users.password_login_enabled` marker is explicit:

- `true`: manual password recovery is allowed.
- `false`: Google-only account; password recovery is denied and no reset
  notification is sent.
- `NULL`: pre-marker account with ambiguous origin; password recovery fails
  closed until an explicit migration/administrative policy exists.

New Fortify and manual-registration users are marked `true`. New Google-only
users are marked `false`. Manual accounts linked to Google remain eligible and
continue to resolve the same immutable Google `sub`. The marker is not inferred
from email or provider identity.

## Secure email change

Mobile clients use:

- `POST /api/v2/auth/email/change/request`
- `POST /api/v2/auth/email/change/verify`

Both endpoints require a personal Sanctum token with the `mobile` ability.
Manual accounts must provide the current password and, when confirmed 2FA is
enabled, a valid TOTP. Google-only accounts must provide a fresh Google ID
token whose verified immutable `sub` belongs to the authenticated user; the
email claim is never used as proof. Confirmed 2FA remains required.

The request stores an opaque UUID-bound pending record containing the original
email, original verification state, initiating user, and initiating Sanctum
token. The new email is normalized, checked for uniqueness, and receives an
AUTH-D02 OTP with the separate `email_change` purpose. TTL, five-attempt lock,
60-second cooldown, keyed digests, and rate limits come from the shared OTP
infrastructure. A dedicated mail template identifies the code as an email
change verification code.

Verification rechecks ownership, token binding, original email state, account
eligibility, and uniqueness inside the same transaction that consumes the OTP
and updates `users.email`. The old email remains active until this transaction
commits. On success, `email_verified_at` is retained, password-reset tokens
for old/new addresses are removed, all Sanctum sessions are revoked (including
the current token), and the response contains no bearer token.

## Error contract

- `PASSWORD_RESET_INVALID` — reset token or account policy is invalid.
- `PASSWORD_RESET_EXPIRED` — reset token lifetime elapsed.
- `PASSWORD_RESET_RATE_LIMITED` — Password Broker cooldown is active.
- `REAUTHENTICATION_REQUIRED` — ownership or account-state proof is missing.
- `INVALID_CREDENTIALS` — manual proof failed.
- `TWO_FACTOR_REQUIRED` — confirmed 2FA needs valid TOTP.
- `EMAIL_CHANGE_PENDING` — an active change is already awaiting OTP.
- `EMAIL_CHANGE_EXPIRED` — email-change OTP expired.
- `EMAIL_CHANGE_INVALID_OTP` — OTP is invalid, used, replaced, or locked.
- `EMAIL_ALREADY_USED` — another user already owns the normalized address.
- `EMAIL_CHANGE_CONFLICT` — session, account state, or concurrent update conflict.
- `OTP_RATE_LIMITED` — shared OTP/request rate limit is active.

Forgot-password responses for account discovery cases are intentionally
generic and do not expose whether an account exists.

## Mobile handoff and deployment

After a successful forgot request, the mobile client follows the official
server reset link or opens it in the trusted web flow. It must not treat a
forgot response as a login or expect a bearer token. After email-change
verification, discard the old token and require a fresh login because the
current session has been revoked.

Run both additive migrations before enabling the client flow:

- `add_password_login_eligibility_to_users_table`
- `create_email_change_requests_table`

The migration intentionally leaves existing users' marker `NULL`; this is a
fail-closed compatibility strategy for origin-ambiguous accounts. Review and
explicitly classify those accounts in a future controlled operation rather
than guessing from email or Google metadata.

Remaining deferred work belongs to AUTH-D08/AUTH-D09: operational handling for
legacy ambiguous account classification, approved recovery support workflows,
and broader account-security UX hardening.
