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
| `MAIL_MAILER is not log` | WARN | a real mailer |
| `CACHE_STORE is not array/null` | WARN | a shared cache store |
| `config is cached` | WARN | `config:cache` was run |

`--strict` turns every WARN into a release blocker. Use it in CI/CD.

---

## 1. Application environment

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` (closes H1 from QA-RELEASE Phase A)
- [ ] `APP_KEY` generated and stored in the secret manager, **not** in Git
- [ ] `APP_URL` is the public HTTPS URL
- [ ] `LOG_LEVEL=warning` (never `debug` in production)

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

## 4. Queue, scheduler & cache

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
