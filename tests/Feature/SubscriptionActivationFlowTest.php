<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Services\Subscription\Midtrans\MidtransNotificationResult;
use App\Services\Subscription\Midtrans\MidtransSnapResponse;
use App\Services\Subscription\PremiumPolicy;
use App\Services\Subscription\PremiumSubscriptionActivator;
use App\Services\Subscription\SubscriptionPeriodCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SubscriptionActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeActivationMidtransGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeActivationMidtransGateway(configured: true);
        $this->app->instance(MidtransGateway::class, $this->gateway);
    }

    /**
     * 1. Initial Cloud activation from Free tier.
     */
    public function test_initial_cloud_activation_from_free_tier(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_CLOUD,
                'billing_period' => 'monthly',
                'status' => SubscriptionPayment::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
            ]);

            $response = $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'));
            $response->assertOk();

            $freshPayment = $payment->fresh();
            $subscription = $business->fresh()->subscription;

            $this->assertSame(SubscriptionPayment::STATUS_PAID, $freshPayment->status);
            $this->assertNotNull($freshPayment->paid_at);
            $this->assertSame('2026-10-01 10:00:00', $freshPayment->paid_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertNotNull($freshPayment->activated_at);
            $this->assertSame('2026-10-01 10:00:00', $freshPayment->activated_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 10:00:00', $freshPayment->expires_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

            $this->assertNotNull($subscription);
            $this->assertSame(Subscription::PLAN_CLOUD, $subscription->plan);
            $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
            $this->assertSame('2026-10-01 10:00:00', $subscription->starts_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 10:00:00', $subscription->expires_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

            $this->assertSame($subscription->expires_at->toIso8601String(), $freshPayment->expires_at->toIso8601String());
            $this->assertTrue($business->fresh()->hasCloudAccess());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 2. Duplicate settlement webhook is idempotent and does not double-extend duration.
     */
    public function test_duplicate_settlement_webhook_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'billing_period' => 'monthly',
            ]);

            // First webhook
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $firstActivationAt = $payment->fresh()->activated_at;
            $firstExpiresAt = $business->fresh()->subscription?->expires_at;

            $this->assertSame('2026-11-01 10:00:00', $firstExpiresAt?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

            // Advance time by 5 minutes, then replay identical settlement webhook
            Carbon::setTestNow(Carbon::parse('2026-10-01 10:05:00', 'Asia/Jakarta'));

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $freshPayment = $payment->fresh();
            $subscription = $business->fresh()->subscription;

            // activated_at unchanged from first activation
            $this->assertSame(
                $firstActivationAt->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                $freshPayment->activated_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            );

            // expires_at NOT doubled to December
            $this->assertSame('2026-11-01 10:00:00', $subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 10:00:00', $freshPayment->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 3. Active Cloud renewal extends from current expires_at and preserves original starts_at.
     */
    public function test_active_cloud_renewal_extends_from_current_expiry_and_preserves_starts_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();

            $initialStartsAt = now()->subDays(11);
            $initialExpiresAt = now()->addDays(19);

            // Existing active Cloud subscription with future expiry (remaining 19 days)
            Subscription::factory()->cloud()->create([
                'business_id' => $business->id,
                'starts_at' => $initialStartsAt,
                'expires_at' => $initialExpiresAt,
            ]);

            $renewalPayment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'billing_period' => 'monthly',
            ]);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($renewalPayment, 'settlement'))->assertOk();

            $subscription = $business->fresh()->subscription;
            $freshPayment = $renewalPayment->fresh();

            // Original starts_at preserved
            $this->assertSame('2026-09-20 10:00:00', $subscription?->starts_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

            // Resulting expiry extended from Oct 20 -> Nov 20 (preserving remaining 19 days)
            $this->assertSame('2026-11-20 10:00:00', $subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-20 10:00:00', $freshPayment->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-01 10:00:00', $freshPayment->activated_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 4. Expired Cloud renewal starts new effective duration from now and updates starts_at to now.
     */
    public function test_expired_cloud_renewal_starts_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();

            // Past expired subscription
            Subscription::factory()->cloud()->expired()->create([
                'business_id' => $business->id,
                'starts_at' => Carbon::parse('2026-08-01 10:00:00', 'Asia/Jakarta'),
                'expires_at' => Carbon::parse('2026-09-01 10:00:00', 'Asia/Jakarta'),
            ]);

            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'billing_period' => 'yearly',
            ]);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $subscription = $business->fresh()->subscription;

            $this->assertSame(Subscription::STATUS_ACTIVE, $subscription?->status);
            $this->assertSame('2026-10-01 10:00:00', $subscription?->starts_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2027-10-01 10:00:00', $subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertTrue($business->fresh()->hasCloudAccess());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 5. Failed, expired, and cancelled payments do not activate or mutate subscriptions.
     */
    public function test_failed_expired_cancelled_payments_do_not_activate_subscription(): void
    {
        [$business] = $this->createBusinessWithOwner();
        $subscription = Subscription::factory()->free()->create(['business_id' => $business->id]);

        $statuses = [
            'deny' => SubscriptionPayment::STATUS_FAILED,
            'expire' => SubscriptionPayment::STATUS_EXPIRED,
            'cancel' => SubscriptionPayment::STATUS_CANCELLED,
        ];

        foreach ($statuses as $midtransStatus => $internalStatus) {
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'status' => SubscriptionPayment::STATUS_PENDING,
            ]);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, $midtransStatus))->assertOk();

            $freshPayment = $payment->fresh();
            $this->assertSame($internalStatus, $freshPayment->status);
            $this->assertNull($freshPayment->activated_at);
            $this->assertNull($freshPayment->paid_at);

            // Subscription remains Free tier
            $freshSub = $business->fresh()->subscription;
            $this->assertSame(Subscription::PLAN_FREE, $freshSub?->plan);
            $this->assertSame(Subscription::STATUS_ACTIVE, $freshSub?->status);
            $this->assertNull($freshSub?->expires_at);
            $this->assertFalse($business->fresh()->hasCloudAccess());
        }
    }

    /**
     * 6. Out-of-order callback: settlement followed by stale pending does not downgrade payment or status.
     */
    public function test_out_of_order_callback_does_not_downgrade_paid_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'billing_period' => 'monthly',
            ]);

            // 1. Settlement arrives first
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->fresh()->status);
            $this->assertTrue($business->fresh()->hasCloudAccess());

            // 2. Stale delayed pending webhook arrives afterwards
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'pending'))->assertOk();

            $freshPayment = $payment->fresh();
            // Status remains PAID
            $this->assertSame(SubscriptionPayment::STATUS_PAID, $freshPayment->status);
            // Provider status is not downgraded to pending
            $this->assertSame('settlement', $freshPayment->provider_transaction_status);
            // Subscription remains active Cloud
            $this->assertTrue($business->fresh()->hasCloudAccess());
            $this->assertSame('2026-11-01 10:00:00', $business->fresh()->subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 7. Recovery: Payment paid but not yet activated is activated cleanly on replay.
     */
    public function test_recovery_paid_but_not_activated_activates_cleanly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();

            // Simulate payment already marked paid, but activated_at is null (e.g. prior crash before activator)
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_CLOUD,
                'billing_period' => 'monthly',
                'status' => SubscriptionPayment::STATUS_PAID,
                'paid_at' => now(),
                'activated_at' => null,
            ]);

            $this->assertFalse($business->fresh()->hasCloudAccess());

            // Replay settlement webhook or call activator
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $freshPayment = $payment->fresh();
            $subscription = $business->fresh()->subscription;

            $this->assertNotNull($freshPayment->activated_at);
            $this->assertSame('2026-10-01 10:00:00', $freshPayment->activated_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 10:00:00', $freshPayment->expires_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

            $this->assertNotNull($subscription);
            $this->assertSame(Subscription::PLAN_CLOUD, $subscription->plan);
            $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
            $this->assertSame('2026-11-01 10:00:00', $subscription->expires_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertTrue($business->fresh()->hasCloudAccess());

            // Second replay does not re-extend
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();
            $this->assertSame('2026-11-01 10:00:00', $business->fresh()->subscription?->expires_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 8. Direct invocation of activator is idempotent.
     */
    public function test_activator_direct_invocation_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_CLOUD,
                'billing_period' => 'monthly',
                'status' => SubscriptionPayment::STATUS_PAID,
                'paid_at' => now(),
                'activated_at' => null,
            ]);

            /** @var PremiumSubscriptionActivator $activator */
            $activator = $this->app->make(PremiumSubscriptionActivator::class);

            $activator->activatePaidPayment($payment);

            $this->assertNotNull($payment->fresh()->activated_at);
            $firstExpiresAt = $business->fresh()->subscription?->expires_at;

            // Second call
            $activator->activatePaidPayment($payment->fresh());

            $this->assertSame($firstExpiresAt->toIso8601String(), $business->fresh()->subscription?->expires_at->toIso8601String());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 9. Business without existing subscription row activates cleanly.
     */
    public function test_business_without_subscription_row_activates_cleanly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            $business = Business::factory()->create();
            // Ensure no subscription row exists
            Subscription::query()->where('business_id', $business->id)->delete();
            $this->assertNull($business->fresh()->subscription);

            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_CLOUD,
                'billing_period' => 'monthly',
            ]);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $subscription = $business->fresh()->subscription;
            $this->assertNotNull($subscription);
            $this->assertSame(Subscription::PLAN_CLOUD, $subscription->plan);
            $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
            $this->assertSame('2026-10-01 10:00:00', $subscription->starts_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 10:00:00', $subscription->expires_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertTrue($business->fresh()->hasCloudAccess());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 10. Payment snapshot is preserved when catalog pricing changes later.
     */
    public function test_payment_snapshot_preserved_regardless_of_future_catalog_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();

            $plan = SubscriptionPlan::factory()->create(['code' => Subscription::PLAN_CLOUD, 'name' => 'Cloud']);
            $price = SubscriptionPlanPrice::factory()->create([
                'subscription_plan_id' => $plan->id,
                'billing_period' => 'monthly',
                'currency' => 'IDR',
                'price_minor' => 150000,
                'is_active' => true,
            ]);

            // Payment was created with snapshot: 150000 IDR monthly
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_CLOUD,
                'billing_period' => 'monthly',
                'currency' => 'IDR',
                'amount' => 150000,
                'status' => SubscriptionPayment::STATUS_PENDING,
            ]);

            // Later, price in catalog changes to 250000 and is deactivated
            $price->update(['price_minor' => 250000, 'is_active' => false]);

            // Webhook settlement arrives for the original payment
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $freshPayment = $payment->fresh();
            $this->assertSame(150000, $freshPayment->amount);
            $this->assertSame('IDR', $freshPayment->currency);
            $this->assertSame('monthly', $freshPayment->billing_period);
            $this->assertSame(SubscriptionPayment::STATUS_PAID, $freshPayment->status);
            $this->assertTrue($business->fresh()->hasCloudAccess());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 11. Refunded payment does not mutate subscription (policy limitation).
     */
    public function test_refunded_payment_does_not_mutate_subscription(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();
            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'billing_period' => 'monthly',
            ]);

            // Settle first
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $subscription = $business->fresh()->subscription;
            $this->assertTrue($business->fresh()->hasCloudAccess());
            $originalExpiresAt = $subscription->expires_at;

            // Provider sends refund notification
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'refund'))->assertOk();

            $freshPayment = $payment->fresh();
            $this->assertSame(SubscriptionPayment::STATUS_REFUNDED, $freshPayment->status);

            // Subscription remains unchanged (ADMIN-07 invariant: do not invent unverified refund revocation policy)
            $freshSub = $business->fresh()->subscription;
            $this->assertSame(Subscription::PLAN_CLOUD, $freshSub->plan);
            $this->assertSame(Subscription::STATUS_ACTIVE, $freshSub->status);
            $this->assertSame($originalExpiresAt->toIso8601String(), $freshSub->expires_at->toIso8601String());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 12. Date-aware expiry automatically revokes entitlement without cron mutation.
     */
    public function test_date_aware_expiry_revokes_entitlements_automatically(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->createBusinessWithOwner();

            $subscription = Subscription::factory()->cloud()->create([
                'business_id' => $business->id,
                'starts_at' => Carbon::parse('2026-09-01 10:00:00', 'Asia/Jakarta'),
                'expires_at' => Carbon::parse('2026-10-02 10:00:00', 'Asia/Jakarta'),
            ]);

            /** @var PremiumPolicy $policy */
            $policy = $this->app->make(PremiumPolicy::class);

            // Currently active
            $this->assertTrue($business->hasCloudAccess());
            $this->assertFalse($subscription->isExpired());
            $this->assertTrue($policy->allows($business, PremiumPolicy::CAPABILITY_WEB_DASHBOARD));
            $this->assertTrue($policy->allows($business, PremiumPolicy::CAPABILITY_CLOUD_SYNC));
            $this->assertTrue($policy->allows($business, PremiumPolicy::CAPABILITY_CLOUD_DEVICES));

            // Fast-forward past expiry (2026-10-03)
            Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00', 'Asia/Jakarta'));

            $freshSub = $subscription->fresh();
            $freshBusiness = $business->fresh();

            // Status in DB remains 'active', but date-aware checks immediately deny access
            $this->assertSame(Subscription::STATUS_ACTIVE, $freshSub->status);
            $this->assertTrue($freshSub->isExpired());
            $this->assertFalse($freshBusiness->hasCloudAccess());

            $this->assertFalse($policy->allows($freshBusiness, PremiumPolicy::CAPABILITY_WEB_DASHBOARD));
            $this->assertFalse($policy->allows($freshBusiness, PremiumPolicy::CAPABILITY_CLOUD_SYNC));
            $this->assertFalse($policy->allows($freshBusiness, PremiumPolicy::CAPABILITY_CLOUD_DEVICES));

            $map = $policy->capabilityMap($freshBusiness);
            foreach ($map as $capability => $allowed) {
                $this->assertFalse($allowed, "Capability {$capability} should be false when expired.");
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 13. Canonical period calculator correctly handles calendar edge cases (Jan 31, leap year).
     */
    public function test_calendar_period_calculations_edge_cases(): void
    {
        $calculator = new SubscriptionPeriodCalculator;

        // 1. Jan 31 non-leap year (2025) -> Feb 28
        $jan31_2025 = Carbon::parse('2025-01-31 15:00:00');
        $feb_2025 = $calculator->calculateExpiry($jan31_2025, 'monthly');
        $this->assertSame('2025-02-28 15:00:00', $feb_2025->format('Y-m-d H:i:s'));

        // 2. Jan 31 leap year (2024) -> Feb 29
        $jan31_2024 = Carbon::parse('2024-01-31 15:00:00');
        $feb_2024 = $calculator->calculateExpiry($jan31_2024, 'monthly');
        $this->assertSame('2024-02-29 15:00:00', $feb_2024->format('Y-m-d H:i:s'));

        // 3. Mar 31 -> Apr 30
        $mar31 = Carbon::parse('2026-03-31 10:00:00');
        $apr30 = $calculator->calculateExpiry($mar31, 'monthly');
        $this->assertSame('2026-04-30 10:00:00', $apr30->format('Y-m-d H:i:s'));

        // 4. Aug 31 -> Sep 30
        $aug31 = Carbon::parse('2026-08-31 12:00:00');
        $sep30 = $calculator->calculateExpiry($aug31, 'monthly');
        $this->assertSame('2026-09-30 12:00:00', $sep30->format('Y-m-d H:i:s'));

        // 5. Feb 29 in leap year (2024) yearly -> Feb 28 next non-leap year (2025)
        $feb29_2024 = Carbon::parse('2024-02-29 12:00:00');
        $yearly = $calculator->calculateExpiry($feb29_2024, 'yearly');
        $this->assertSame('2025-02-28 12:00:00', $yearly->format('Y-m-d H:i:s'));
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function createBusinessWithOwner(): array
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        return [$business, $owner];
    }

    /**
     * @return array<string, mixed>
     */
    private function midtransPayload(SubscriptionPayment $payment, string $status, ?string $fraudStatus = null): array
    {
        return [
            'order_id' => $payment->provider_order_id,
            'transaction_status' => $status,
            'fraud_status' => $fraudStatus,
            'transaction_id' => 'midtrans-'.$payment->id,
            'payment_type' => 'bank_transfer',
        ];
    }
}

final class FakeActivationMidtransGateway implements MidtransGateway
{
    public function __construct(
        private readonly bool $configured = true,
    ) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createSnapTransaction(SubscriptionPayment $payment, Business $business, User $user): MidtransSnapResponse
    {
        return new MidtransSnapResponse('snap-token', 'https://app.sandbox.midtrans.com/snap/v4/redirect');
    }

    public function verifyNotification(array $payload): ?MidtransNotificationResult
    {
        $orderId = isset($payload['order_id']) && is_scalar($payload['order_id']) ? (string) $payload['order_id'] : '';
        $transactionStatus = isset($payload['transaction_status']) && is_scalar($payload['transaction_status']) ? (string) $payload['transaction_status'] : '';

        if ($orderId === '' || $transactionStatus === '') {
            return null;
        }

        return new MidtransNotificationResult(
            orderId: $orderId,
            transactionStatus: $transactionStatus,
            fraudStatus: isset($payload['fraud_status']) && is_scalar($payload['fraud_status']) ? (string) $payload['fraud_status'] : null,
            transactionId: isset($payload['transaction_id']) && is_scalar($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            paymentType: isset($payload['payment_type']) && is_scalar($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            payload: $payload,
        );
    }
}
