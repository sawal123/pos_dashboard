<?php

namespace App\Services\Subscription;

use App\Models\Business;
use App\Models\Subscription;

/**
 * PREM-D02A — the single authoritative Premium policy.
 *
 * Product policy (what Premium *is*) is read from `config/premium.php`; pricing
 * is deliberately kept out of this class because no official price exists.
 *
 * Entitlement is never decided here either: every decision defers to
 * {@see Business::hasCloudAccess()} (and therefore
 * {@see Subscription::hasCloudAccess()}), which is the authoritative
 * server-side rule:
 *
 *     plan == cloud
 *     AND status == active
 *     AND (expires_at == null OR expires_at > now())
 *
 * An expired, inactive, free, unknown or missing subscription denies every
 * capability. Local POS usage is intentionally outside this policy: nothing here
 * can block offline sales, local products, local customers, local cash or local
 * backup, because those never pass through the cloud entitlement.
 */
final class PremiumPolicy
{
    public const CAPABILITY_WEB_DASHBOARD = 'web_dashboard';

    public const CAPABILITY_CLOUD_BACKUP = 'cloud_backup';

    public const CAPABILITY_CLOUD_RESTORE = 'cloud_restore';

    public const CAPABILITY_CLOUD_SYNC = 'cloud_sync';

    public const CAPABILITY_CLOUD_DEVICES = 'cloud_devices';

    public const PAYMENT_PROVIDER_MIDTRANS = 'midtrans';

    public const RENEWAL_MODE_MANUAL = 'manual';

    /**
     * Canonical paid plan code.
     */
    public function planCode(): string
    {
        $code = config('premium.plan.code');

        return is_string($code) && $code !== '' ? $code : 'cloud';
    }

    /**
     * Billing periods the canonical paid plan supports.
     *
     * @return list<string>
     */
    public function billingPeriods(): array
    {
        $periods = config('premium.plan.billing_periods');

        if (! is_array($periods)) {
            return [];
        }

        $normalized = [];

        foreach ($periods as $period) {
            if (is_string($period) && $period !== '') {
                $normalized[] = $period;
            }
        }

        return array_values(array_unique($normalized));
    }

    public function supportsBillingPeriod(string $period): bool
    {
        return in_array($period, $this->billingPeriods(), true);
    }

    /**
     * Maximum number of counted cloud devices per active Cloud business.
     * Returns 0 when the limit is not configured (treated as "no limit").
     */
    public function deviceLimit(): int
    {
        $limit = config('premium.plan.device_limit');

        return is_int($limit) && $limit > 0 ? $limit : 0;
    }

    public function paymentProvider(): string
    {
        $provider = config('premium.plan.payment_provider');

        return is_string($provider) && $provider !== '' ? $provider : self::PAYMENT_PROVIDER_MIDTRANS;
    }

    public function renewalMode(): string
    {
        $mode = config('premium.plan.renewal_mode');

        return is_string($mode) && $mode !== '' ? $mode : self::RENEWAL_MODE_MANUAL;
    }

    /**
     * PREM-D02A final decision: renewal is manual, never automatic.
     */
    public function isManualRenewal(): bool
    {
        return $this->renewalMode() === self::RENEWAL_MODE_MANUAL;
    }

    /**
     * A paid subscription must carry an explicit expiry when it is activated
     * through checkout (PREM-D02B). Legacy rows are not rewritten.
     */
    public function requiresExpiryOnActivation(): bool
    {
        return config('premium.plan.requires_expiry_on_activation') === true;
    }

    /**
     * Official capability list.
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        $capabilities = config('premium.capabilities');

        if (! is_array($capabilities)) {
            return [];
        }

        $normalized = [];

        foreach ($capabilities as $capability) {
            if (is_string($capability) && $capability !== '') {
                $normalized[] = $capability;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Whether the business currently holds an active Cloud entitlement.
     */
    public function hasCloudEntitlement(?Business $business): bool
    {
        return $business !== null && $business->hasCloudAccess();
    }

    /**
     * Deny-by-default capability check. An unknown capability, or a business
     * without an active Cloud entitlement, is always denied.
     */
    public function allows(?Business $business, string $capability): bool
    {
        if (! in_array($capability, $this->capabilities(), true)) {
            return false;
        }

        return $this->hasCloudEntitlement($business);
    }

    /**
     * Capability map for presentation and diagnostics.
     *
     * @return array<string, bool>
     */
    public function capabilityMap(?Business $business): array
    {
        $map = [];

        foreach ($this->capabilities() as $capability) {
            $map[$capability] = $this->allows($business, $capability);
        }

        return $map;
    }
}
