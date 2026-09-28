# INT-01 Cashier-Safe Sync API V1

## Scope

INT-01 makes POS Mobile sync usable by cashier memberships without granting the
full owner/member mutation contract. The backend remains the source of truth:
cashier sync is allowlist based, deny-by-default, tenant safe, outlet safe,
device safe, and atomic per `/api/sync/push` request.

POS Mobile was audited read-only from `D:\PROJECT WEB\POS OFFLINE\pos-mobile`.
No mobile code is changed in INT-01.

## Endpoints

| Endpoint | Method | Cashier | Owner | Member |
| --- | --- | --- | --- | --- |
| `/api/sync/pull` | GET | Allowed with valid mobile token, membership, Cloud subscription, and active device | Allowed | Allowed |
| `/api/sync/push` | POST | Allowed only in `cashier_safe` mode | Full contract | Full contract |
| `/api/mobile/context` | GET | Allowed; returns role and `sync_capabilities` | Allowed | Allowed |
| `/api/mobile/devices` | POST | Denied | Allowed | Allowed |

## Authentication And Context

All sync endpoints require a Sanctum token with the `mobile` ability.

The backend resolves the current membership role on every request from
`business_user.role`. The token is never the role authority. If a role changes
after a token is issued, the next request uses the new role. If membership is
removed, the request is rejected with `BUSINESS_ACCESS_DENIED`.

Sync also requires:

| Requirement | Error |
| --- | --- |
| User belongs to `business_id` | `403 BUSINESS_ACCESS_DENIED` |
| Cloud subscription is active | `403 CLOUD_SUBSCRIPTION_REQUIRED` |
| `device_identifier` belongs to that business | `403 SYNC_DEVICE_INVALID` |
| Device is active | `403 DEVICE_INACTIVE` |

Device outlet is authoritative. Payload outlet, business, or device fields are
not trusted to expand access.

## Push Matrix

| Entity | Cashier operation | Notes |
| --- | --- | --- |
| `categories` | Deny create/update/delete | Master data remains owner/member only. |
| `products` | Deny create/update/delete | Includes price, HPP/cost, stock snapshot, SKU, barcode, unit, status. |
| `customers` | Allow upsert | Tombstone/delete status is denied. Business scoped. |
| `shifts` | Allow upsert | Existing rows must belong to the device outlet. Closed shifts cannot be reopened by cashier sync. |
| `sales` | Allow upsert | Existing rows must belong to the device outlet. Customer/shift relations must be same business and shift must be same outlet or present in the same envelope. |
| `sale_items` | Allow upsert | Parent sale must be in the same envelope or already exist in the device outlet. Product must already exist in the business. |
| `cash_ledger` | Allow sale payment only | Requires `sale_sync_id`, sale must be own outlet, `type` must be `in`, and amount must match the sale total when known. Manual cash in/out is denied. |
| `stock_movements` | Allow sale movement only | Requires `movement_type=sale`, `sale_sync_id`, sale must be own outlet, product must be same business, and `quantity_change` must be negative. Adjustments/restocks/corrections are denied. |
| `expenses` | Deny create/update/delete | DASH-16 financial management remains owner-only. |
| `deletions` | Deny all | No cashier tombstones in V1. |

Unknown entities or operations are denied.

## Pull Matrix

Cashier pull keeps the existing read contract so local state can converge:

| Entity | Scope |
| --- | --- |
| `categories` | Business-wide |
| `products` | Business-wide |
| `customers` | Business-wide |
| `shifts` | Device outlet |
| `sales` | Device outlet |
| `sale_items` | Device outlet through parent sale |
| `expenses` | Device outlet |
| `cash_ledger` | Device outlet |
| `stock_movements` | Business-wide |

The pull cursor remains monotonic and is not filtered in a way that causes a
device to loop over the same server sequence indefinitely.

## Atomic Mixed Payloads

Cashier push authorization runs as a preflight over the entire envelope before
idempotency lookup, `SyncRequest` creation, entity writes, or sync sequence
increments.

If one mutation is forbidden, the whole request is rejected:

```json
{
  "message": "Sync operation is not allowed for this role.",
  "code": "SYNC_OPERATION_NOT_ALLOWED",
  "violations": [
    {
      "entity": "products",
      "operation": "upsert",
      "reason": "cashier_entity_not_allowed"
    }
  ]
}
```

The response status is `403`. No partial success is recorded.

## Idempotency And Conflict Behavior

Successful requests continue to use the existing `(business_id, device_id,
request_id)` idempotency contract. A successful duplicate returns `duplicate:
true`.

Forbidden cashier requests are not stored in `sync_requests`, so they cannot
become successful duplicates later.

Existing conflict semantics are unchanged:

| Condition | Error |
| --- | --- |
| Optimistic version mismatch | `409 SYNC_CONFLICT` |
| Data/unique conflict | `409 SYNC_DATA_CONFLICT` |
| Retryable database contention | `409 SYNC_RETRYABLE_CONFLICT` |
| Validation failure | `422` validation error |

Owner/member keep the existing full sync behavior and error semantics.

## Mobile Context Capabilities

`GET /api/mobile/context` adds per-business fields:

```json
{
  "role": "cashier",
  "sync_capabilities": {
    "pull": true,
    "push": true,
    "push_mode": "cashier_safe",
    "allowed_entities": {
      "customers": ["upsert"],
      "shifts": ["upsert"],
      "sales": ["upsert"],
      "sale_items": ["upsert"],
      "cash_ledger": ["sale_payment"],
      "stock_movements": ["sale"]
    },
    "denied_entities": ["categories", "products", "expenses", "deletions"],
    "contract_version": "cashier_sync_v1"
  }
}
```

Owner/member receive `push_mode: "full"`. Unsupported/unknown roles receive
`push_mode: "none"` with empty allowed entities.

The fields are additive and backward-compatible. Legacy POS Mobile can ignore
them, but until the mobile app consumes them the backend remains the enforcement
boundary.

## Required POS Mobile Follow-up

The read-only mobile audit found these current outbox sources:

| Mobile source | Server entity emitted |
| --- | --- |
| Product/category store | `categories`, `products`, and tombstones |
| Customer store | `customers` and customer tombstones |
| Expense store | `expenses` and expense tombstones |
| Transaction store | `sales`, `sale_items`, sale-linked cash payment fields |
| Cash store | `cash_ledger`, including manual cash in/out |
| Product/stock store | `stock_movements`, including sale movements and manual adjustments/restocks |
| Shift store | `shifts` |

For cashier users, POS Mobile must use `sync_capabilities` to avoid enqueueing:

| Must not enqueue for cashier | Allowed for cashier |
| --- | --- |
| Category create/update/delete | Customer upsert |
| Product create/update/delete | Shift upsert |
| Expense create/update/delete/void | Sale upsert |
| Customer tombstone/delete | Sale item upsert for valid sale/product |
| Any tombstone in `deletions` | Sale-linked cash payment |
| Manual cash in/out | Sale-linked negative stock movement |
| Manual stock adjustment/restock/correction |  |

If old mobile builds still enqueue forbidden rows, the backend rejects the
entire mixed payload. POS Mobile must then preserve the outbox and surface a
role/capability mismatch instead of dropping data silently.

## Known Limitations

Cashier V1 allows only sale-linked cash and stock rows that can be related to a
valid same-outlet sale. It does not implement device hardware attestation,
background sync redesign, QR pairing, billing changes, WebSocket sync, or any
POS Mobile UI/outbox filtering.

Stock movements are still pulled business-wide to preserve current stock parity.
