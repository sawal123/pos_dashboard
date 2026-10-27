# AUTH-D05 — Secure Google Account Linking

Manual users link a Google identity only from an authenticated mobile Sanctum
session after password reauthentication (and a valid existing Fortify TOTP
code when two-factor authentication is confirmed).

`POST /api/v2/auth/reauthenticate` issues a five-minute, single-use opaque
grant. Only an HMAC digest is stored, and the grant is bound to the user,
the current personal access token, and the `google_link` purpose.

`POST /api/v2/auth/google/link` verifies the Google ID token before opening a
database transaction. The transaction locks the current user and grant,
consumes the grant atomically with the identity insert, and relies on the
database uniqueness constraints for concurrent ownership races.

Google-only accounts retain their existing random password and are not given a
password-based linking path. Pending email registrations and unverified users
are denied without bypassing OTP. Linking never changes the user email or
provisions a business, outlet, subscription, or additional access token.

The provider subject is the ownership key. An identity owned by another user
returns `IDENTITY_ALREADY_LINKED`; a different Google subject for the current
user returns `IDENTITY_CONFLICT`; linking the same subject again is idempotent.
