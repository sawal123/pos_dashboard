# PREM-D03 — Cloud Backup API & Private Storage

## Purpose

PREM-M06 (mobile restore) was hard-gated because the backend had no Cloud
Backup/Restore API. PREM-D03 delivers the **backend contract only**: authorized,
private, immutable snapshot storage plus an authorized download.

> **The backend never restores data into the POS.**
> It stores and returns opaque snapshot bytes. The actual restore stays on the
> device (PREM-M06) after its validation, safety-backup and atomicity work is
> complete. Nothing here interprets, migrates or normalizes POS domain data.

## Architecture

```
POS Mobile ──(Sanctum mobile token)──▶  POST /api/mobile/backups
                                          │  mobile ability
                                          │  business membership
                                          │  role permission (cloud.backup)
                                          │  active Cloud entitlement
                                          │  active device
                                          │  size + SHA-256 verification
                                          ▼
                                   private disk (cloud_backups)
                                          │  immutable READY row
                                          ▼
                                   retention: keep newest 10

          ──(cloud.restore)──▶  GET /api/mobile/backups/{uuid}/download
                                          ▼
                                   streamed private bytes
```

The server is a **validated private snapshot store**, not an interpreter. The
exact byte string uploaded is what is stored and later returned.

## Scope / Non-goals

Implemented: upload, list, metadata detail, authorized private download,
immutable snapshot storage, checksum verification, Premium entitlement gate,
business/device authorization, automatic retention, idempotency.

Explicitly **not** implemented here: mobile UI, local restore, automatic
server-side restore, a new sync engine, backup scheduler/auto-backup, public
download URLs, backup sharing, manual DELETE endpoint, arbitrary retention
settings, encryption claims, compression, subscription/payment changes.

## API contract

All endpoints live behind `auth:sanctum` in `routes/api.php` and reuse the
existing response conventions: success is `{ "data": ... }`, failure is
`{ "message": ..., "code": "UPPER_SNAKE" }` with an HTTP status.

### `POST /api/mobile/backups`

Request:

```json
{
  "business_id": 12,
  "device_identifier": "POS-AB12CD34",
  "schema_version": 1,
  "app_version": "1.4.0",
  "checksum_sha256": "<64 hex chars>",
  "size_bytes": 2048,
  "payload": "<exact serialized local-backup snapshot string>",
  "idempotency_key": "optional-client-retry-key"
}
```

- `payload` **must be a string** — the exact serialized local backup. The server
  stores those exact bytes; it does not re-encode a nested JSON object.
- `idempotency_key` is optional.

Response `201` on first write, `200` on an idempotent replay:

```json
{
  "data": {
    "id": 41,
    "uuid": "9d1d6f1a-7f0a-4a5b-9d0e-9d0d0c1b2a3f",
    "created_at": "2026-10-02T03:15:00+00:00",
    "schema_version": 1,
    "app_version": "1.4.0",
    "size_bytes": 2048,
    "checksum_sha256": "<verified sha-256>",
    "status": "ready",
    "device": { "id": 7, "identifier": "POS-AB12CD34", "name": "Kasir 1", "platform": "android" },
    "duplicate": false
  }
}
```

`storage_path`, the storage disk and the payload are **never** serialized.

### `GET /api/mobile/backups?business_id={id}`

Optional `device_identifier` (validated when present) and `limit` (1–10,
default 10). Returns metadata only, newest first:

```json
{ "data": [ { "id": 41, "uuid": "...", "created_at": "...", "schema_version": 1,
  "app_version": "1.4.0", "size_bytes": 2048, "checksum_sha256": "...",
  "status": "ready", "device": { "id": 7, "identifier": "...", "name": "...",
  "platform": "android" } } ] }
```

### `GET /api/mobile/backups/{backup}?business_id={id}`

`{backup}` is the snapshot **uuid**. Returns the same metadata object. Not found
in the authorized business (including another tenant's snapshot) → `404`.

### `GET /api/mobile/backups/{backup}/download?business_id={id}`

Streams the exact stored bytes (`Content-Type: application/octet-stream`) with:

- `X-Checksum-Sha256: <verified sha-256>`
- `X-Backup-Schema-Version: <schema_version>`

The filesystem path is never revealed. Missing private file → `404`
`BACKUP_FILE_MISSING`.

## Authorization stack

Every endpoint runs the same server-side gate
(`App\Services\Backup\CloudBackupContextResolver`), mirroring the mobile sync
gate. Nothing is trusted from the client.

1. **Mobile ability** — the Sanctum token must grant `mobile`
   (`MOBILE_TOKEN_REQUIRED`). Web/dashboard sessions are not mobile
   authorization.
2. **Business membership** — `business_id` must exist and the authenticated user
   must be a member (`BUSINESS_ACCESS_DENIED`).
3. **Role permission** — owner/member hold `cloud.backup` and `cloud.restore`;
   cashier holds neither (`MOBILE_ROLE_NOT_SUPPORTED`).
4. **Cloud entitlement** — `Business::hasCloudAccess()` must be true
   (plan=`cloud`, status=`active`, `expires_at` null or in the future). Free,
   expired, inactive, unknown or missing subscriptions → `403`
   `CLOUD_SUBSCRIPTION_REQUIRED`.
5. **Device** — `device_identifier` must match an `active` device of the same
   business. Upload **requires** it; list/detail/download accept it optionally
   but validate it when present. Unknown/foreign → `DEVICE_NOT_FOUND`; inactive
   → `DEVICE_INACTIVE`.

### Capability separation

`cloud_backup` gates upload/list/detail; `cloud_restore` gates the download.
Both are declared in `config/premium.php` and enforced through
`PremiumPolicy::allows()`; both still require an active Cloud entitlement. No
second permission system was introduced.

Since ADMIN-10, `PremiumPolicy::allows()` also requires the capability to have a
live backend (`config('premium.capability_availability')`, fail-closed for any
unknown/unset entry). PREM-D03 is that backend, so the merged configuration sets
`cloud_backup => true` and `cloud_restore => true`. The platform readiness page
(`/platform/backups`) reflects this: the backup API and `cloud_backups` schema
rows are now available, while restore *execution*, storage telemetry and
scheduling remain explicitly marked as not implemented.

### Cross-tenant behavior

Resource-specific endpoints return `404` (`BACKUP_NOT_FOUND`) for a snapshot that
does not belong to the authorized business, so existence never leaks across
tenants.

## Storage

- Disk: `cloud_backups` (config `premium.backup.disk`, env `CLOUD_BACKUP_DISK`).
  It is a **private** `local` disk rooted at
  `storage_path('app/private/cloud-backups')`, with `serve => false` (no
  temporary public URL is ever generated). The `public` disk is never used.
- Path is fully server-generated: `{business_id}/{uuid}.json`. It is never
  client-controlled, so path traversal is impossible.
- `storage_path` is internal only; download is always via the authorized
  controller stream.

## Size limit

Hard application limit: **25 MB** (`premium.backup.max_bytes` =
`25 * 1024 * 1024`). Enforced in the application layer, not only by
php/nginx/apache. The declared `size_bytes` is not trusted: the authoritative
size is `strlen()` of the received payload. `declared != actual` →
`BACKUP_SIZE_MISMATCH`; either exceeding the limit → `BACKUP_TOO_LARGE`.

## Checksum

SHA-256 is mandatory. The server computes `hash('sha256', $payload)` over the
**exact bytes it will store** and compares with `hash_equals()` against the
declared value (lower-cased). Mismatch → `BACKUP_CHECKSUM_MISMATCH`, and no
READY record is written and no corrupt payload is retained. The database always
stores the server-verified checksum.

## Immutability

READY snapshots are immutable: there is no update endpoint and the
`CloudBackup` model throws `ImmutableCloudBackupException` on any update once
READY. Retention only ever deletes.

## Idempotency

Scope: `(business_id, device_id, idempotency_key)` via a unique index. The same
retry for the same business/device returns the same logical snapshot (`200`,
`"duplicate": true`) instead of creating a second one. The same key under a
different business or a different device is independent (a null key is never
deduplicated). The key is never global — it is always tenant + device scoped.

## Retention

Maximum **10 READY backups per business** (`premium.backup.retention`).
Retention runs **only after** a new backup is stored, checksum-verified and
persisted as READY. The oldest READY snapshots beyond ten are removed (private
file first, then the row). A failed new upload never triggers retention, so
existing backups are untouched. A cleanup failure is logged and swallowed: the
new backup stays valid and a failing file delete still removes the row so the
READY count stays bounded.

## Concurrency

Two devices may upload simultaneously. Each upload runs inside a DB transaction
that takes a `lockForUpdate()` on the parent **business** row — never the whole
`cloud_backups` table — so uploads for one business are serialized (retention
cannot race and exceed ten) while different businesses proceed independently.
The unique idempotency index is a second line of defence.

## Negative stock compatibility

The backend performs only structural/security validation. It does **not**
re-validate POS domain invariants: a payload containing `product.stock = -3`,
`stock_before = 2`, `stock_after = -3`, `quantity_change = -5` is accepted and
stored byte-for-byte. There is no `stock >= 0` constraint anywhere on this path.

## Failure cleanup

If storage write, checksum, validation or DB persistence fails, the request
fails closed: no READY record is created and any orphaned object is deleted.
A failure never affects existing backups.

## Security

Private disk; no public URL; authenticated download; mobile ability; business
membership; Premium entitlement; active device; cross-tenant protection
(404); 25 MB cap; checksum verification; immutable snapshots; server-only
storage paths; no path traversal; no user-controlled storage path; no
secret/token/payload in logs. Payloads are private business data.

## Auditability

Minimal, payload-free logging: `cloud_backup.created` (id, business, device,
size, status), `cloud_backup.failed` (business, device, size, disk, exception
message), and `cloud_backup.retention_*_failed`. No payload, token or secret is
logged.

## Deployment requirements

- A writable private path: `storage/app/private/cloud-backups` (create the
  directory / ensure the web user can write). Override the disk with
  `CLOUD_BACKUP_DISK` if a different private disk (e.g. `s3`) is used.
- The disk must remain **private** — never point it at the `public` disk.
- If the transport uses multipart/form-data, PHP/server upload limits must be
  `>= 25 MB`: `upload_max_filesize`, `post_max_size`, and the web server's body
  size limit. The application limit is enforced regardless.
- Run `php artisan migrate`.

## Known limitations

- Retention deletes the row even when the private file delete fails, so a
  failure can leave an orphaned object (reported via logs).
- Deleting a business/device removes snapshot rows via FK cascade but does not
  delete the underlying objects (no lifecycle hook on business/device deletion
  yet).
- The capability is all-or-nothing: `cloud_backup`/`cloud_restore` cannot be
  granted separately from the active Cloud entitlement.
- `processing`/`failed` statuses exist in the vocabulary but are not produced:
  failures are rejected before a row is written.
- Concurrency is exercised on SQLite in the standard suite; the
  `lockForUpdate` path is only meaningfully exercised on MySQL/MariaDB.
