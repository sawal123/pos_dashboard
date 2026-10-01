# PREM-D02B - Midtrans Checkout & Aktivasi Premium

## Architecture

Mobile starts checkout through Laravel. Laravel creates a `subscription_payments`
record with a server-owned price snapshot, creates a Midtrans Snap transaction,
then returns the Snap token/redirect URL. Mobile may poll Laravel for payment
status after returning from Snap, but client callbacks never activate Premium.

The only activation path is:

1. Midtrans sends `POST /api/webhooks/midtrans`.
2. Laravel verifies the Midtrans signature and checks transaction status through
   the official Midtrans PHP SDK.
3. Laravel maps the verified provider status to an internal payment state.
4. Only `paid` payments activate or extend the Cloud subscription.

## API Routes

`GET /api/mobile/subscription/plans?business_id={id}`

Returns the PREM-D01 catalog plus D02B checkout availability. Without official
pricing or Midtrans config, `checkout_available` is false and plans are not
purchasable.

`POST /api/mobile/subscription/checkout`

```json
{
  "business_id": 123,
  "plan": "cloud",
  "billing_period": "monthly",
  "idempotency_key": "optional-client-retry-key"
}
```

Response:

```json
{
  "data": {
    "payment_id": 1,
    "status": "pending",
    "provider": "midtrans",
    "snap_token": "...",
    "redirect_url": "..."
  }
}
```

`GET /api/mobile/subscription/payments/{payment}`

Returns payment status, price snapshot, paid timestamp, and current subscription
status when visible to the caller's business.

`GET /api/mobile/subscription/payments?business_id={id}`

Returns the latest 20 payment attempts for the tenant.

`POST /api/webhooks/midtrans`

Public Midtrans notification endpoint. It does not use Sanctum, but invalid
provider verification cannot mutate payment or subscription data.

## Persistence

`subscription_payments` stores:

- tenant/user: `business_id`, `user_id`
- provider correlation: `provider`, `provider_order_id`
- checkout idempotency: `idempotency_key`
- immutable purchase snapshot: `plan`, `billing_period`, `currency`, `amount`
- internal state: `pending`, `paid`, `failed`, `expired`, `cancelled`,
  `refunded`
- Snap response: `snap_token`, `redirect_url`
- provider metadata: transaction id, payment type, transaction status, fraud
  status, raw provider payload
- lifecycle timestamps: `paid_at`, `expires_at`, `activated_at`

No server key, card number, CVV, or sensitive payment credential is persisted.

## Pricing Configuration

> **Superseded by PREM-D02C.** Prices are now database-owned
> (`subscription_plans` / `subscription_plan_prices`) and read by
> `App\Services\Subscription\PremiumPricing`. The `PREMIUM_*` price env keys
> below no longer exist — see `docs/premium/PREM_D02C_DATABASE_PRICING.md`. The
> block is kept for historical context only.

No official price is committed. Checkout fails closed until these are configured:

```env
PREMIUM_PRICING_CONFIGURED=true
PREMIUM_CLOUD_MONTHLY_PRICE_MINOR=
PREMIUM_CLOUD_YEARLY_PRICE_MINOR=
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

Currency is `IDR`. Amounts are minor-unit integer snapshots on each payment, so
future config changes do not alter existing payment rows.

## Midtrans Boundary

The integration boundary is `App\Services\Subscription\Midtrans\MidtransGateway`.
Production binds it to `OfficialMidtransGateway`, which uses the official
`midtrans/midtrans-php` SDK for Snap creation and transaction status checks.
Tests bind a fake gateway and never perform real payments.

The server key is backend-only and is never returned to mobile clients.

## Webhook Verification

The webhook verifies Midtrans notification signature:

`sha512(order_id + status_code + gross_amount + server_key)`

After signature verification, Laravel asks Midtrans for authoritative
transaction status through the SDK. Raw notification fields alone are not used
as payment truth.

## State Mapping

| Midtrans status | Fraud status | Internal status |
| --- | --- | --- |
| `settlement` | any | `paid` |
| `capture` | `accept` or absent | `paid` |
| `capture` | `challenge` | `pending` |
| `capture` | `deny` | `failed` |
| `pending` | any | `pending` |
| `deny` | any | `failed` |
| `cancel` | any | `cancelled` |
| `expire` | any | `expired` |
| `refund` / `partial_refund` | any | `refunded` |

Old `pending` notifications cannot downgrade a final payment. Duplicate `paid`
webhooks are safe because activation is guarded by `activated_at`.

## Idempotency

Checkout supports optional `idempotency_key`. With a key, the same
business/user/plan/period/key reuses the existing pending payment. Without a
key, Laravel reuses a matching pending payment from the last 15 minutes to
absorb double taps.

Webhook handling uses database transactions and row locks. A paid payment can
activate only once.

## Activation And Renewal

Only verified `paid` payments activate Cloud. Activation writes:

- `plan = cloud`
- `status = active`
- `starts_at = now` for new/expired subscriptions
- `expires_at` always non-null

Monthly uses calendar month arithmetic. Yearly uses calendar year arithmetic.
The entitlement boundary remains `expires_at <= now` means inactive.

Renewal is manual only. If the subscription is still active, extension starts
from current `expires_at`; if expired, extension starts from now. There is no
recurring charge, stored card mandate, scheduler charging, or auto-renewal.

## Tenant And RBAC Security

Checkout/status/history require Sanctum and token ability `mobile`.

Tenant checks:

- caller must belong to `business_id`
- cross-tenant payment status returns not found
- history is scoped by business membership

RBAC:

- checkout requires `BusinessPermission::SUBSCRIPTION_MANAGE`
- current matrix grants it only through owner wildcard

Client-supplied `amount` and `currency` are prohibited. Plan is restricted to
canonical `cloud`; period is restricted to `monthly` or `yearly`.

## Compatibility

`GET /api/mobile/context` is unchanged. After verified payment, the existing
subscription object reports `cloud`, `active`, `starts_at`, and non-null
`expires_at`, so PREM-M01 can detect Premium without a breaking contract change.

Legacy `cloud + active + expires_at = null` remains compatible. D02B does not
rewrite legacy rows, but every paid activation through Midtrans writes
`expires_at`.

## Known Limitations

- Official monthly/yearly prices are still a blocker for live checkout.
- Automated tests use a fake Midtrans gateway; no real sandbox charge is made.
- Refund status is recorded on the payment. This task does not implement
  automatic entitlement revocation after a refund.
- Cloud backup/restore endpoints are not implemented here.

## Next Mobile Step

POS Mobile should call checkout, open Snap using `snap_token` or `redirect_url`,
then poll `GET /api/mobile/subscription/payments/{payment}` and refresh
`GET /api/mobile/context`. It must not treat its own success callback as
entitlement truth.
