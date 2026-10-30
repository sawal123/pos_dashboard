# Production Deployment Checklist

Applies to the POS Offline dashboard (`pos_dashboard`). Run every item before
switching production traffic, and after any configuration change.

> ⚠️ The local development `.env` is **not** a production `.env`. Never deploy it
> as-is: it runs `APP_ENV=local` with `APP_DEBUG=true`.

---

## 0. Automated preflight (run last)

After `config:cache`, run the fail-closed guard and require exit code `0`:

```bash
php artisan config:cache
php artisan deploy:preflight --strict
```

The command reads **resolved** configuration (so it reflects `config:cache`) and
lists each check as `PASS` / `WARN` / `FAIL`:

| Check | Severity | Requirement |
| --- | --- | --- |
| `APP_ENV=production` | FAIL | `APP_ENV=production` |
| `APP_DEBUG=false` | FAIL | `APP_DEBUG=false` |
| `APP_KEY is set` | FAIL | `php artisan key:generate` has been run |
| `APP_URL uses https` | FAIL | `APP_URL=https://…` — non-HTTPS is always a failure, there is **no** bypass flag |
| `SESSION_DRIVER is not array` | FAIL | not `array` |
| `SESSION_SECURE_COOKIE is enabled` | FAIL | `SESSION_SECURE_COOKIE=true` |
| `SESSION_HTTP_ONLY is enabled` | FAIL | not `false` |
| `DB_CONNECTION is not sqlite` | FAIL | MySQL/MariaDB in production |
| `LOG_LEVEL is not debug` | WARN | `LOG_LEVEL=warning` (or higher) |
| `QUEUE_CONNECTION is not sync` | WARN | a real worker/connection |
| `MAIL_MAILER is safe` | FAIL | real credential-delivery transport; never `log`, `array`, or unsafe failover |
| `AUTH_OTP_MAILER is safe` | FAIL | real OTP transport and valid sender |
| `Google Sign-in is configured` | WARN when disabled, FAIL when malformed | `GOOGLE_CLIENT_ID` is set (otherwise `/api/v2/auth/google` returns `503`) and every configured ID ends with `.apps.googleusercontent.com` |
| `Google JWT library is installed` | FAIL | `firebase/php-jwt` is present in `vendor/` — `composer install` ran for this release |
| `AUTH cache is shared and atomic` | FAIL | database/Redis/Memcached/shared file cache |
| `AUTH migrations are applied` | FAIL | OTP, mobile challenge, email-change, and password marker schema |
| `config is cached` | WARN | `config:cache` was run |

`--strict` turns every WARN into a release blocker. Use it in CI/CD.

---

## 1. Application environment

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` (closes H1 from QA-RELEASE Phase A)
- [ ] `APP_KEY` generated and stored in the secret manager, **not** in Git
- [ ] `APP_URL` is the public HTTPS URL
- [ ] `LOG_LEVEL=warning` (never `debug` in production)
- [ ] `GOOGLE_CLIENT_ID` is the production **web** OAuth client ID — an empty value
      makes `POST /api/v2/auth/google` fail with `503 GOOGLE_AUTH_NOT_CONFIGURED`
- [ ] `GOOGLE_ANDROID_CLIENT_IDS` lists every Android client ID the released app can
      mint ID tokens with (comma-separated, no spaces)

## 2. HTTPS & session

- [ ] TLS terminates at the proxy/load balancer; HTTP redirects to HTTPS
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_HTTP_ONLY=true` (keep the default)
- [ ] `SESSION_SAME_SITE=lax` (or `strict`)
- [ ] `SESSION_DRIVER` is `database`/`redis` — never `array`
- [ ] Trusted proxies configured if behind a load balancer

## 3. Database

- [ ] `DB_CONNECTION` is MySQL/MariaDB (never `sqlite`)
- [ ] A **fresh logical backup** is taken immediately before deploy and verified
- [ ] Migrations are reviewed; run them with `php artisan migrate --force` after the
      backup, not before
- [ ] `json`/`utf8mb4` collation matches the tested environment

## 4. Dependencies, queue, scheduler & cache

- [ ] `composer install --no-dev --optimize-autoloader` ran for this release —
      `vendor/` is not committed, and a stale vendor without `firebase/php-jwt`
      breaks both web and mobile Google login
- [ ] A queue worker runs under a supervisor (`queue:work`/`horizon`)
- [ ] `php artisan schedule:work` (or a cron entry for `schedule:run`) is active
- [ ] `QUEUE_CONNECTION` and `CACHE_STORE` point at a shared backend
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`

## 5. Storage & permissions

- [ ] `storage/` and `bootstrap/cache/` are writable by the web user
- [ ] `.env` is `0600` and owned by the deploy user
- [ ] The document root points at `public/` only (never the project root)
- [ ] `storage:link` has been run if the app serves uploaded files

## 6. Sensitive logging & secrets

- [ ] No secrets are committed to Git (`.env*`, `auth.json`, keys)
- [ ] Logs do not contain tokens, passwords or full request bodies
- [ ] Log retention/PII policy is applied
- [ ] Third-party secret scanning is enabled on the repository

## 7. Post-deploy verification

- [ ] `GET /up` returns `200`
- [ ] Log in as owner, member and cashier; confirm the sidebar and a 403 on an
      owner-only URL
- [ ] Create a cash entry, then reverse it (owner) and confirm the summary updates
- [ ] Download CSV/XLSX/PDF and confirm the totals match the on-screen report
- [ ] Confirm the mobile API still authenticates (`POST /api/auth/login`)
- [ ] Confirm Google login is configured:

      ```bash
      curl -sS -o /dev/null -w '%{http_code}\n' -X POST https://<host>/api/v2/auth/google \
        -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -d '{"id_token":"probe","client_platform":"android"}'
      ```

      Expect `401` (token rejected, configuration loaded). A `503` means
      `GOOGLE_CLIENT_ID` is empty or `config:cache` is stale.
- [ ] Open `/login` and confirm the Google button renders (it is hidden when
      `GOOGLE_CLIENT_ID` is empty) and that the dashboard origin is listed in the
      OAuth client's **Authorized JavaScript origins**

## 8. Rollback

- [ ] Previous release artefact is tagged and retained
- [ ] Rollback = redeploy the previous artefact + restore the pre-deploy backup if
      a migration was destructive
- [ ] `composer install --no-dev --optimize-autoloader`, `npm run build`, then
      re-run `config:cache`/`route:cache`/`view:cache`
- [ ] Confirm `/up` and a login smoke test after rollback

---

## Out of scope (tracked separately)

Large security-architecture changes are **not** part of this checklist and
remain separate tasks: device attestation / token-to-device binding, mobile
token rotation/expiry policy, and billing entitlements (DASH-12B).
