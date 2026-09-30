# PREM-D02A - Premium Billing Configuration & Entitlement Policy

Date: 2026-09-30
Branch: `feat/prem-d02a-billing-policy` · base: `feat/prem-d01-subscription-contract` (PREM-D01, PR #49)

This task records the **final** Premium product policy in configuration and enforces it
server-side. It adds **no** payment integration: Midtrans is decided as the provider, but
checkout is PREM-D02B.

## 1. Final product decisions

| Decision | Value |
| --- | --- |
| Canonical paid plan | `cloud` |
| Billing periods | `monthly`, `yearly` |
| Payment provider | Midtrans (integration: PREM-D02B) |
| Renewal | **manual** — no auto-renewal anywhere |
| Cloud device limit | **5** active devices per active Cloud business |
| Pricing | **UNDECIDED (BLOCKER)** — see §7 |
| Checkout | Not available; `checkout_available` stays `false` |
| Source of truth | The **server** — never the mobile UI, never a client callback |

## 2. Configuration: product policy vs pricing

`config/premium.php` is now split so policy cannot be confused with pricing:

```php
'plan' => [
    'code' => 'cloud',
    'billing_periods' => ['monthly', 'yearly'],
    'payment_provider' => 'midtrans',
    'renewal_mode' => 'manual',
    'device_limit' => 5,
    'requires_expiry_on_activation' => true,
],

'capabilities' => [
    'web_dashboard', 'cloud_backup', 'cloud_restore', 'cloud_sync', 'cloud_devices',
],

'pricing' => [
    'configured' => false,   // BLOCKER
    'mobile_plans' => [],    // no official price exists
],
```

* **No price is present anywhere in the repository.** The previous mockup numbers are not
  reproduced, and no price is derived from a plan name.
* `App\Services\Subscription\PremiumPolicy` is the single reader of this configuration.
  Nothing else calls `config('premium.*')` directly except the plan catalog.
* `pricing.mobile_plans` is the canonical location. The legacy top-level
  `premium.mobile_plans` key is still honoured as a fallback, so the PREM-D01 contract and
  its regression tests keep working (no breaking change).

## 3. Entitlement rule (authoritative)

```
hasCloudAccess = plan == cloud
                 AND status == active
                 AND (expires_at IS NULL OR expires_at > now())
```

`PremiumPolicy::allows($business, $capability)` denies by default: an unknown capability,
a missing business, a free plan, an unknown plan, an expired subscription, an inactive
one, or a past `expires_at` all resolve to **denied**.

### Expiry invariant for paid activation

A paid Cloud subscription activated through checkout **must** carry an explicit
`expires_at` (`requires_expiry_on_activation`). PREM-D02B must set it at activation.

Legacy rows with `plan = cloud`, `status = active`, `expires_at = null` are **still treated
as active**. That is deliberate (no destructive data migration in this task), and it is a
documented residual risk: such a row grants cloud access indefinitely until it is updated.
PREM-D02B should remediate them when checkout ships; PREM-D02A does not rewrite
subscription rows.

## 4. Expired / Free / inactive behavior

Denied for free, expired, inactive, missing or invalid subscriptions:

* Web Dashboard Premium surface (§6)
* Cloud synchronisation (`/api/sync/push`, `/api/sync/pull`)
* Cloud device registration and use (`/api/mobile/devices`, dashboard `/devices`)
* Cloud backup and cloud restore (declared capabilities with **no backend implementation**
  yet — denied by default for everyone until the feature exists)
* Any other cloud-only capability

Still available (never gated by the subscription):

* POS offline / local sales, local products, local customers, local inventory, local cash
* Local backup & restore on the device
* Cloud account login, session (`/api/auth/*`) and the POS bootstrap
  (`GET /api/mobile/context` still returns business + outlets with `cloud_access: false`)
* Dashboard shell, subscription/billing page, business settings, business switching

## 5. Device limit (5)

`App\Services\Subscription\CloudDeviceLimit` + `config('premium.plan.device_limit')`.

**Counting rule — derived from the existing lifecycle, nothing invented:** a device occupies
a slot only while `status = active`, which is exactly the status
`SyncContextResolver` requires for an API call. `inactive` devices keep their row,
identifier and history but never consume a slot, so deactivating a device frees one. There
is no `revoked`/`deleted` state in this schema and none was added.

**Enforcement points (server-side only):**

| Path | Behavior at the limit |
| --- | --- |
| `POST /api/mobile/devices` (mobile) | `403` `{"code":"CLOUD_DEVICE_LIMIT_REACHED","device_limit":5,"active_devices":5}` |
| `POST /devices` (dashboard pre-registration) | validation error on `identifier`, no row created |

* Only **new** device creation is limited. Re-submitting an existing identifier is an
  idempotent resolve and is never blocked, so an existing device can always reconnect.
* The entitlement check runs **before** the limit: a non-entitled business receives
  `CLOUD_SUBSCRIPTION_REQUIRED`, never a limit error.
* The count is per business, so one tenant's devices can never block another tenant.
* Residual risk (documented, not hidden): the check is a read-then-insert, so two truly
  concurrent registrations for the same business can both pass the count and land at
  limit + 1. The unique `(business_id, identifier)` index still prevents duplicates, and
  the existing concurrency handling (catch → resolve winner) is unchanged.

## 6. Web Dashboard authorization

Two separate server-side checks now run on dashboard routes, **RBAC first**:

```
business.permission:<permission>   (DASH-10B2 — role)
premium.access:<capability>        (PREM-D02A — Cloud entitlement)
```

RBAC is evaluated first so an under-privileged role still receives `403` and the
entitlement layer cannot mask an authorization result.

**Gated (Premium) routes:** `transactions`, `products` (read + manage), `stock`, `cash`
(read + manage), `shifts`, `customers`, `laundry-orders`, `outlets`, `users`
(+ invitations/members/roles), `reports` (+ `csv`/`xlsx`/`pdf` exports), `devices`, `sync`.

**Deliberately NOT gated** — the account/billing path an expired business still needs:
`dashboard` (subscription state, cloud card and upsell shell), `subscription`
(status, and renewal once PREM-D02B ships), `business-settings`,
`dashboard/business-context`, and everything in `settings.php` (auth/profile).

**Denial is never a dead end:** a browser request is redirected back to `dashboard` with
the reason, which the dashboard now renders as a status banner (the message is
`EnsurePremiumAccess::LOCKED_MESSAGE`). A JSON/API request receives
`403 {"code":"CLOUD_SUBSCRIPTION_REQUIRED"}` — the same stable code the mobile API uses.
An expired owner therefore always sees their real status and can reach the Langganan page.

`dashboard`/`subscription` are intentionally kept reachable while still being honest: the
overview degrades on cloud access (DASH-12A) and the billing page shows the real
non-active state.

## 7. Pricing — UNDECIDED (BLOCKER)

There is **no official price** for monthly or yearly yet. Therefore:

* `config('premium.pricing.mobile_plans')` stays empty → `GET /api/mobile/subscription/plans`
  returns `plans: []`.
* `checkout_available` is hard `false` (no Midtrans integration exists).
* A plan is exposed as `purchasable: true` **only** when it has an officially priced
  billing period *and* the backend has checkout. Today that combination cannot occur.
* The catalog only accepts the periods the policy declares (`monthly`, `yearly`); an
  unknown period such as `weekly` is dropped instead of being passed through.
* POS Mobile must keep showing the "pricing/checkout unavailable" state; no fake price,
  no fake purchase, no fake invoice.

**Blocker owner:** Product/Billing must publish the official monthly and yearly prices
(currency + amount) before checkout can be enabled.

## 8. Mobile API contract changes

Additive only; nothing was removed or renamed:

| Change | Detail |
| --- | --- |
| `plans[].purchasable` | **new** boolean. `false` whenever the plan has no official price or the backend has no checkout |
| `plans[].billing_periods[]` | unchanged shape, now filtered by the product policy (`monthly`, `yearly` only) |
| `data.checkout_available` | unchanged, always `false` |
| `POST /api/mobile/devices` | **new** failure mode: `403` `CLOUD_DEVICE_LIMIT_REACHED` with `device_limit` and `active_devices` |

### Cross-repo note (handoff)

The POS Mobile PREM-M02 catalog adapter currently normalises a flat shape
(`plans[].price` + `plans[].period`), while this backend exposes
`plans[].billing_periods[{period, currency, price_minor}]`. Because the catalog is empty
today the mismatch is latent, but PREM-D02B (or a small POS Mobile follow-up) must
reconcile it — either by emitting both representations or by updating the mobile adapter
to read `billing_periods`. It must be settled before checkout is enabled.

## 9. Handoff to PREM-D02B

PREM-D02B owns the whole payment lifecycle:

* create checkout/order; Midtrans transaction creation
* payment status polling + webhook
* webhook signature verification
* idempotency (one payment → one activation)
* activation: `plan = cloud`, `status = active`, `starts_at`, **`expires_at` (required)**
* renewal (manual only — no auto-renewal, no stored billing mandate)
* payment history/invoices

Constraints inherited from this task:

1. **No activation may ever be driven by a client/mobile callback.** Activation is derived
   from a verified server-to-server Midtrans notification.
2. Checkout stays disabled until official prices are configured; `checkout_available`
   flips to `true` only together with a verified contract.
3. Prices come from the backend configuration/database — never from the client.
4. `requires_expiry_on_activation` must be satisfied by every paid activation.
5. The entitlement rule in §3 is not to be re-implemented or bypassed; reuse
   `Business::hasCloudAccess()` / `PremiumPolicy`.

## 10. Regression tests

`tests/Feature/PremiumBillingPolicyTest.php` (33 tests) pins: canonical plan + both billing
periods + unsupported-period rejection, Midtrans provider, manual-only renewal, device
limit = 5 (and configurable), the official capability list, the activation-expiry
invariant plus explicit legacy `null`-expiry behavior, active/free/absent/expired-by-date/
expired-by-status/inactive entitlement, the exclusive expiry boundary, deny-by-default for
unknown capability, tenant isolation, empty catalog with `checkout_available: false`, no
plan is purchasable without checkout, legacy `premium.mobile_plans` compatibility, devices
1–5 allowed / 6th rejected (`CLOUD_DEVICE_LIMIT_REACHED`), inactive devices not consuming a
slot, deactivation freeing a slot, idempotent re-resolution at the limit, per-tenant device
counts, dashboard registration unable to bypass the limit, entitlement checked before the
limit, expired cloud losing sync/device/registration while keeping POS bootstrap + login,
Premium dashboard denial (redirect + JSON 403), active cloud access to the same routes,
account/billing paths still reachable after expiry with the real non-active status, the
visible denial reason, and RBAC still winning over the entitlement gate.

Existing dashboard suites were aligned to the new policy by giving the **active** business
an active Cloud subscription in their fixtures (26 files, fixture-only: no assertion was
changed, relaxed or removed).

## 11. Out of scope

* Midtrans/payment integration, checkout, invoices, webhooks, payment history.
* Any schema change, any destructive migration of existing subscription rows.
* Cloud backup / cloud restore implementation (declared, still denied).
* POS Mobile changes (tracked in the mobile repository).
* Sidebar/menu cosmetic gating in the dashboard UI (server-side denial is authoritative;
  the index pages themselves are still reachable only with entitlement).
