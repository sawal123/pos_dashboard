# PREM-D01 - Mobile Subscription Contract

Date: 2026-09-30

This document is the backend contract for POS Mobile PREM-M02/PREM-M04. It is
intentionally conservative: the backend is the source of truth, and nothing in
this contract invents pricing, billing periods, checkout, device limits,
auto-renewal, or benefits.

## Existing Backend Audit

Subscription storage is `subscriptions`, one row per business. The model is
`App\Models\Subscription` with canonical columns:

- `plan`
- `status`
- `starts_at`
- `expires_at`

Canonical plan codes currently modelled by the backend:

- `free`
- `cloud`

Subscription statuses currently used by the backend:

- `active`
- `expired`
- `inactive`

Cloud entitlement is authoritative in `Subscription::hasCloudAccess()` and
`Business::hasCloudAccess()`:

```text
plan == cloud
AND status == active
AND (expires_at is null OR expires_at > now)
```

`SyncContextResolver` and `MobileDeviceController` both enforce this server-side.
Free, expired, missing, or non-active cloud subscriptions do not receive cloud
sync access. POS offline remains usable without Premium because the restriction
applies to backend cloud sync/device registration endpoints, not local POS use.

DASH-12A is a read-only dashboard overview. DASH-12B remains undecided for paid
billing: there is no payment gateway, checkout, invoice, webhook, renewal rule,
official price table, or official billing-period source.

## GET /api/mobile/subscription/plans

Endpoint:

```http
GET /api/mobile/subscription/plans?business_id={business_id}
Authorization: Bearer {mobile-token}
Accept: application/json
```

Authentication and authorization:

- Requires Sanctum auth.
- Requires a token with the `mobile` ability.
- Requires `business_id`.
- The authenticated user must belong to the requested business.
- The endpoint is read-only and does not mutate subscription rows.
- Client-supplied plan, price, period, checkout, or entitlement parameters are
  ignored.

Successful response shape:

```json
{
  "data": {
    "business_id": 1,
    "plans": [],
    "checkout_available": false
  }
}
```

Plan shape when the backend later has official configured catalog data:

| Field | Type | Source |
| --- | --- | --- |
| `code` | string | Canonical `Subscription.plan` code, for example `cloud` |
| `name` | string | Backend catalog config |
| `billing_periods[].period` | string | Backend catalog config, `monthly` or `yearly` only |
| `billing_periods[].currency` | string | Backend catalog config |
| `billing_periods[].price_minor` | integer | Backend catalog config |
| `currency` | string or null | First configured billing period currency |
| `benefits` | array of strings | Backend catalog config |
| `available` | boolean | Backend catalog config |

Only canonical subscription plan codes may be exposed. There is no hidden mobile
mapping such as `premium` to `cloud`.

## Pricing

Current source of truth: none.

`config/premium.php` intentionally ships with an empty `mobile_plans` array
because Product/Billing has not decided official prices. POS Mobile must not
hard-code production prices from mockups or UI assumptions.

Price representation, once officially configured, is integer minor units:

- `currency`: ISO-style currency code from backend config, for example `IDR`.
- `price_minor`: integer minor-unit amount from backend config.

## Billing Periods

Current official billing periods: none.

The endpoint only exposes periods present in backend config and only supports
period codes the backend understands:

- `monthly`
- `yearly`

Because no official periods are configured today, the default catalog is empty.
POS Mobile should show the monthly/yearly toggle only when both periods are
present for a returned plan.

## Checkout Availability

`checkout_available` is currently always `false`.

Reason: the backend has no implemented checkout/payment capability. There is no
Midtrans endpoint, fake success path, hosted checkout URL, invoice, payment
webhook, or renewal workflow in this repository.

## Mobile Context

Endpoint:

```http
GET /api/mobile/context
Authorization: Bearer {mobile-token}
```

Existing fields remain unchanged. Subscription now includes the real persisted
dates when a subscription row exists:

```json
{
  "subscription": {
    "plan": "cloud",
    "status": "active",
    "starts_at": "2026-09-01T01:00:00.000000Z",
    "expires_at": "2026-10-01T01:00:00.000000Z"
  },
  "cloud_access": true
}
```

No synthetic dates are generated. If either database column is null, the field is
returned as null.

## Example Responses

Free business, catalog unavailable:

```json
{
  "data": {
    "business_id": 1,
    "plans": [],
    "checkout_available": false
  }
}
```

Cloud business, context:

```json
{
  "data": {
    "businesses": [
      {
        "subscription": {
          "plan": "cloud",
          "status": "active",
          "starts_at": "2026-09-01T01:00:00.000000Z",
          "expires_at": "2026-10-01T01:00:00.000000Z"
        },
        "cloud_access": true
      }
    ]
  }
}
```

Unavailable catalog means `plans: []` and `checkout_available: false`.

## Error Responses

Unauthenticated:

```json
{
  "message": "Unauthenticated."
}
```

Non-mobile token:

```json
{
  "message": "Mobile API token is required.",
  "code": "MOBILE_TOKEN_REQUIRED"
}
```

Missing or invalid `business_id`:

```json
{
  "message": "The business id field is required.",
  "errors": {
    "business_id": ["The business id field is required."]
  }
}
```

Business not owned/membered by the token user:

```json
{
  "message": "Business access denied.",
  "code": "BUSINESS_ACCESS_DENIED"
}
```

## Blockers

The following business decisions remain required before paid launch:

- Official production prices.
- Official billing periods.
- Checkout/payment provider and implementation.
- Renewal/cancellation rules.
- Official paid benefits and any device limits.
