# AUTH-D06 — Sanctum Sessions, Login/Logout & Mobile 2FA

## Scope

API v2 now has one mobile authentication lifecycle for password and Google
login. Google identity resolution remains owned by AUTH-D04/AUTH-D05 and is
still keyed by the immutable Google `sub`; Google Sign-In does not replace
TOTP for an account with confirmed 2FA.

## Lifecycle

1. Password or Google identity is validated.
2. The user must be email-verified and any pending manual registration must be
   fully provisioned.
3. Users without confirmed 2FA receive a Sanctum token with only the `mobile`
   ability.
4. Users with confirmed 2FA receive a random, opaque challenge identifier and
   no bearer token. The challenge is stored as an application-keyed digest,
   is bound to the user and login method, expires within five minutes, and is
   single-use.
5. A valid TOTP or existing Fortify recovery code consumes the challenge and
   only then creates the mobile token.

## API v2

| Method | Endpoint | Result |
| --- | --- | --- |
| POST | `/api/v2/auth/login` | Password login, token or `202 TWO_FACTOR_REQUIRED` |
| POST | `/api/v2/auth/google` | Google login, token or `202 TWO_FACTOR_REQUIRED` |
| POST | `/api/v2/auth/2fa/verify` | Complete TOTP or recovery-code challenge |
| GET | `/api/v2/auth/me` | Current mobile user and authentication status |
| DELETE | `/api/v2/auth/logout` | Revoke only the current mobile token |

`/me` and `/logout` require a Sanctum bearer token with the `mobile` ability.
The response exposes only public user fields, email verification status, a
2FA-enabled flag, and `manual`/`google` method names. It never exposes the
Google subject, password, token, TOTP secret, or recovery codes.

## Challenge and recovery-code policy

Challenges have a maximum lifetime of five minutes and five failed attempts.
They are invalidated on success, expiry, lockout, or critical account-state
change. Verification uses row locking, so concurrent requests for one
challenge cannot both issue a token. Rate limiting is applied per challenge
and account/IP combination.

Recovery codes use Fortify's encrypted JSON format. A successful recovery-code
login removes that code atomically while the challenge row and user row are
locked. A consumed code cannot be reused.

## Sanctum sessions

Only v2 tokens created after complete authentication receive the configurable
30-day per-token expiry (`auth_mobile.token_ttl_days`). The global
`sanctum.expiration` setting remains unchanged, so existing legacy sessions
are not shortened unexpectedly. Logout deletes only the current personal
access-token row; other devices and business/POS data remain unchanged.

## Legacy compatibility and isolation

`/api/auth/login`, `/api/auth/me`, and `/api/auth/logout` retain their existing
contracts. In particular, legacy confirmed-2FA login remains fail-closed and
does not become a 2FA bypass. Mobile token ability does not grant business
membership, role, or entitlement access; existing tenant and subscription
authorization continues to run at business API boundaries.

## Error codes

- `TWO_FACTOR_REQUIRED` — primary login succeeded and a challenge is needed.
- `TWO_FACTOR_INVALID` — TOTP/recovery code was invalid.
- `TWO_FACTOR_CHALLENGE_EXPIRED` — challenge lifetime elapsed.
- `TWO_FACTOR_CHALLENGE_USED` — challenge was already consumed or locked.
- `TWO_FACTOR_CHALLENGE_LOCKED` — five failed attempts were reached.
- `EMAIL_NOT_VERIFIED` — bearer-token eligibility was not met.
- `AUTH_PROVISIONING_PENDING` — pending registration provisioning is incomplete.
- `MOBILE_ABILITY_REQUIRED` — the supplied token is not a mobile token.

## Mobile integration requirements

Store the challenge identifier only in protected in-memory or encrypted device
state, never log it, and do not send it as an Authorization bearer token. After
`202`, prompt for TOTP or a recovery code and submit it to the v2 verification
endpoint. Store the returned bearer token and expiry, refresh application
state on `401`, and call v2 logout when the device session ends.

## Remaining risks and deployment notes

Run the migration before enabling the mobile client flow and confirm the
database user can create the challenge table and its explicit indexes on
MySQL/MariaDB. Configure `SANCTUM_MOBILE_TOKEN_TTL_DAYS` only if a different
policy is approved; the challenge TTL is capped at five minutes.

AUTH-D04 backlog hardening remains tracked separately and is not expanded in
AUTH-D06. Full-device logout and reauthentication for that future operation
remain follow-up work for AUTH-D07.
