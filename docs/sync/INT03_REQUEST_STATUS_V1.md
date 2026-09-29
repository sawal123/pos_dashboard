# INT-03 — Sync Request Status API V1

## Scope

`GET /api/sync/requests/{request_id}/status` is a **read-only** endpoint that
tells a POS Mobile client whether a previously sent `/api/sync/push` request has
already been committed by the server.

It exists because authorization runs **before** the idempotency check. If the
server commits a push but the response is lost, and the caller's role changes
afterwards (for example `member` → `cashier`), a naive retry could be rejected
with `403`. Without a way to ask "did you already accept this?", the client
cannot tell a rejected request from an accepted-but-unacknowledged one.

This endpoint answers that question. It does **not** change the idempotency
contract or the cashier-safe preflight.

---

## Endpoint

```
GET /api/sync/requests/{request_id}/status?business_id={id}&device_identifier={identifier}
```

| Part | Value |
| --- | --- |
| `{request_id}` | UUID of the original push `request_id` (validated as a UUID) |
| `business_id` | business the device belongs to |
| `device_identifier` | the calling device's identifier |
| Auth | `auth:sanctum` + a token with the `mobile` ability |

### Authorization

The endpoint reuses the existing sync context resolver with the
**`sync.pull`** permission:

| Requirement | Failure |
| --- | --- |
| Sanctum token with `mobile` ability | `403 MOBILE_TOKEN_REQUIRED` |
| The user still belongs to `business_id` | `403 BUSINESS_ACCESS_DENIED` |
| Role is `owner`, `member` or `cashier` (role is re-read per request) | `403 MOBILE_ROLE_NOT_SUPPORTED` |
| Active Cloud subscription | `403 CLOUD_SUBSCRIPTION_REQUIRED` |
| `device_identifier` belongs to that business | `403 SYNC_DEVICE_INVALID` |
| Device is active | `403 DEVICE_INACTIVE` |
| `request_id` is a UUID | `422` validation error |

`sync.pull` is held by owner, member **and** cashier. This is deliberate: the
status question must remain answerable after a role downgrade that would have
removed the push permission. The token is never the role authority — the role is
read from the membership row on every request, so an already-issued token
follows the current role.

---

## Response

Status `200` for both outcomes.

Found (request committed):

```json
{
  "data": {
    "request_id": "0b7c9e2a-1f4d-4a5b-9c3e-2d1a7f6b8c90",
    "status": "committed",
    "processed_at": "2026-09-28T08:15:30+00:00"
  }
}
```

Not found:

```json
{
  "data": {
    "request_id": "0b7c9e2a-1f4d-4a5b-9c3e-2d1a7f6b8c90",
    "status": "not_found",
    "processed_at": null
  }
}
```

`processed_at` is ISO-8601 with offset. `status` is `committed` or `not_found`.

### Source of truth

The status is read from `sync_requests`, scoped to
`business_id + device_id + request_id`. A row is written by `SyncPushService`
inside the same transaction that applies the changes, so `committed` means the
whole push envelope was applied and committed.

### What is never returned

Only the three contract fields. The endpoint never returns the request payload,
the applied changes, entity rows, tokens, or anything about the device beyond
the caller's own identity.

---

## ⚠️ `not_found` is not proof that the request will not commit

**A `not_found` response does not authorise a client to delete, rebuild, or
mutate a sync envelope whose acceptance is still uncertain.**

`not_found` means only: *at the moment this query ran, no committed record for
this `(business, device, request_id)` existed.* It does **not** mean the request
was rejected or will never be applied. Two cases produce it:

1. **The request is still in flight.** A concurrent push holds the
   `sync_requests` row in an uncommitted transaction; the row is invisible to
   other readers until that transaction commits.
2. **The request was never received** (e.g. it never left the device).

The server cannot distinguish these two cases from a single read, and neither
can the client.

### Correct client behaviour

* **Committed** → the push was applied. Safe to mark the envelope as synced and
  remove it from the outbox.
* **not_found** → **keep the envelope.** Do not delete it, do not change its
  `request_id`, do not rebuild it. Retry the original push with the **same
  `request_id`** (idempotency makes a retry of an already-applied request a
  no-op returning `duplicate: true`), and re-check the status after the retry or
  after a short backoff.
* Only remove an envelope when a status check reports `committed`, or when a
  retry is explicitly acknowledged (`duplicate: true` or `200`).

Because the request id is stable and the endpoint is read-only, polling the
status is always safe: it never consumes, mutates or re-applies anything.

---

## Isolation and privacy

* The lookup is scoped to the **resolved device**, never a caller-supplied
  device id. A `request_id` belonging to another device — even in the same
  business — is reported as `not_found`, indistinguishable from a missing one.
* A `request_id` belonging to another **business** is likewise reported as
  `not_found`; the endpoint does not reveal that it exists elsewhere.
* Because the device is resolved from `business_id` + `device_identifier` and
  must belong to the caller's business and be active, cross-tenant and
  cross-outlet probing is rejected by the same gates as push/pull.

---

## Read-only guarantee

The endpoint performs a single non-locking `SELECT`. It:

* never writes `sync_requests` (no `processed_at` bump, no insert),
* never touches stock, cash or any sync sequence,
* never advances the pull cursor,
* holds no lock, so it cannot block — or be blocked by — an in-flight push. A
  consistent read of an uncommitted row is not blocked under InnoDB's default
  `REPEATABLE READ`.

---

## Known limitations

* It reports only the client's **own** device's requests; it is not an
  administrative audit endpoint.
* It cannot report "in progress": the server does not track pending requests, so
  an in-flight request is reported as `not_found` (see the warning above).
* Retention follows the existing `sync_requests` rows; once a row is removed the
  status degrades to `not_found`.
* A very old `request_id` is not distinguishable from a misspelled one.

---

## Tests

`tests/Feature/SyncRequestStatusTest.php` (16 tests): committed/not_found
shapes, no payload leakage, member→cashier role change with the same token,
owner/member/cashier access, revoked membership, unsupported role, inactive
device, foreign device, inactive subscription, invalid UUID, missing token,
token without the mobile ability, cross-tenant and cross-device isolation, and
no side effects on stock/cash/sync state.

`tests/Feature/Api/SyncConcurrencyMySqlTest.php`
(`test_status_read_of_an_in_flight_request_does_not_block_and_reports_not_found`)
runs two real processes against the isolated `pos_p38_concurrency_test` database
(QA-ENV-01 guard) and proves that a status read inside the in-flight window is
not blocked and reports `not_found`, while the request becomes visible after the
writer commits.
