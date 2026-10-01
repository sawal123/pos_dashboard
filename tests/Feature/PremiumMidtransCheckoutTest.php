<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Services\Subscription\Midtrans\MidtransNotificationResult;
use App\Services\Subscription\Midtrans\MidtransSnapResponse;
use App\Services\Subscription\Midtrans\OfficialMidtransGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PremiumMidtransCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_pricing_keeps_catalog_checkout_unavailable(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', false)
            ->assertJsonPath('data.plans', []);
    }

    public function test_configured_pricing_and_midtrans_make_cloud_purchasable(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->configurePricing();
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', true)
            ->assertJsonPath('data.plans.0.code', Subscription::PLAN_CLOUD)
            ->assertJsonPath('data.plans.0.purchasable', true)
            ->assertJsonPath('data.plans.0.billing_periods.0.period', 'monthly')
            ->assertJsonPath('data.plans.0.billing_periods.0.price_minor', 123456)
            ->assertJsonPath('data.plans.0.billing_periods.1.period', 'yearly')
            ->assertJsonPath('data.plans.0.billing_periods.1.price_minor', 1234560);
    }

    public function test_checkout_requires_authentication_and_business_membership(): void
    {
        [$business, $owner, $ownerToken] = $this->ownedBusiness();
        $other = Business::factory()->create();

        $this->configurePricing();
        $this->fakeGateway(configured: true);

        $payload = $this->checkoutPayload($business);

        $this->postJson('/api/mobile/subscription/checkout', $payload)->assertUnauthorized();

        $this->withToken($ownerToken)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($other))
            ->assertForbidden()
            ->assertJsonPath('code', 'BUSINESS_ACCESS_DENIED');
    }

    public function test_checkout_requires_mobile_token(): void
    {
        [$business, $owner] = $this->ownedBusiness();
        $webToken = $owner->createToken('web', ['web'])->plainTextToken;
        $this->configurePricing();
        $this->fakeGateway(configured: true);

        $this->withToken($webToken)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertForbidden()
            ->assertJsonPath('code', 'MOBILE_TOKEN_REQUIRED');
    }

    public function test_checkout_requires_owner_subscription_permission(): void
    {
        [$business] = $this->ownedBusiness();
        $member = User::factory()->create();
        $business->users()->attach($member->id, ['role' => Business::ROLE_MEMBER]);
        $memberToken = $member->createToken('mobile-api', ['mobile'])->plainTextToken;
        $this->configurePricing();
        $this->fakeGateway(configured: true);

        $this->withToken($memberToken)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_PURCHASE_FORBIDDEN');
    }

    public function test_checkout_rejects_invalid_plan_period_client_amount_and_missing_pricing(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business, ['plan' => 'premium']))
            ->assertUnprocessable();

        $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business, ['billing_period' => 'weekly']))
            ->assertUnprocessable();

        $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business, ['amount' => 1, 'currency' => 'USD']))
            ->assertUnprocessable();

        $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertStatus(409)
            ->assertJsonPath('code', 'CHECKOUT_UNAVAILABLE');
    }

    public function test_valid_checkout_creates_pending_payment_with_server_amount_and_is_idempotent(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->configurePricing();
        $gateway = $this->fakeGateway(configured: true);

        $payload = $this->checkoutPayload($business, [
            'idempotency_key' => 'tap-1',
            'amount' => 1,
        ]);
        unset($payload['amount']);

        $first = $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionPayment::STATUS_PENDING)
            ->assertJsonPath('data.provider', SubscriptionPayment::PROVIDER_MIDTRANS)
            ->assertJsonPath('data.snap_token', 'snap-token')
            ->json('data.payment_id');

        $second = $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $payload)
            ->assertCreated()
            ->json('data.payment_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, SubscriptionPayment::count());
        $this->assertSame(123456, SubscriptionPayment::firstOrFail()->amount);
        $this->assertSame(123456, $gateway->snapPayloads[0]['transaction_details']['gross_amount']);
        $this->assertFalse($business->fresh()->hasCloudAccess());
    }

    public function test_invalid_signature_and_unknown_order_do_not_activate_subscription(): void
    {
        [$business] = $this->ownedBusiness();
        $gateway = $this->fakeGateway(configured: true);
        $payment = SubscriptionPayment::factory()->create(['business_id' => $business->id]);

        $gateway->validSignature = false;
        $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))
            ->assertForbidden()
            ->assertJsonPath('code', 'INVALID_SIGNATURE');

        $this->assertFalse($business->fresh()->hasCloudAccess());

        $gateway->validSignature = true;
        $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement', orderId: 'missing-order'))
            ->assertOk()
            ->assertJsonPath('code', 'UNKNOWN_ORDER');

        $this->assertFalse($business->fresh()->hasCloudAccess());
    }

    public function test_official_midtrans_signature_verification_uses_server_key(): void
    {
        config()->set('premium.midtrans.server_key', 'server-key');
        $gateway = app(OfficialMidtransGateway::class);
        $payload = [
            'order_id' => 'order-123',
            'status_code' => '200',
            'gross_amount' => '123456.00',
        ];
        $payload['signature_key'] = hash('sha512', 'order-123'.'200'.'123456.00'.'server-key');

        $this->assertTrue($gateway->hasValidSignature($payload));

        $payload['signature_key'] = hash('sha512', 'order-123'.'200'.'123456.00'.'wrong-key');

        $this->assertFalse($gateway->hasValidSignature($payload));
    }

    public function test_midtrans_status_mapping_and_activation_rules(): void
    {
        [$business] = $this->ownedBusiness();
        $gateway = $this->fakeGateway(configured: true);

        foreach ([
            'pending' => SubscriptionPayment::STATUS_PENDING,
            'deny' => SubscriptionPayment::STATUS_FAILED,
            'cancel' => SubscriptionPayment::STATUS_CANCELLED,
            'expire' => SubscriptionPayment::STATUS_EXPIRED,
            'refund' => SubscriptionPayment::STATUS_REFUNDED,
        ] as $midtransStatus => $internalStatus) {
            $payment = SubscriptionPayment::factory()->create(['business_id' => $business->id]);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, $midtransStatus))
                ->assertOk();

            $this->assertSame($internalStatus, $payment->fresh()->status);
        }

        $challenge = SubscriptionPayment::factory()->create(['business_id' => $business->id]);
        $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($challenge, 'capture', 'challenge'))->assertOk();
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $challenge->fresh()->status);

        $paid = SubscriptionPayment::factory()->create(['business_id' => $business->id]);
        $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($paid, 'capture', 'accept'))->assertOk();
        $this->assertSame(SubscriptionPayment::STATUS_PAID, $paid->fresh()->status);
        $this->assertTrue($business->fresh()->hasCloudAccess());
        $this->assertNotNull($business->fresh()->subscription?->expires_at);
    }

    public function test_duplicate_paid_webhook_activates_once_and_manual_renewal_uses_calendar_periods(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->ownedBusiness();
            $this->fakeGateway(configured: true);

            $monthly = SubscriptionPayment::factory()->create(['business_id' => $business->id, 'billing_period' => 'monthly']);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($monthly, 'settlement'))->assertOk();
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($monthly, 'settlement'))->assertOk();

            $this->assertSame('2026-11-01 10:00:00', $business->fresh()->subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));

            $renewal = SubscriptionPayment::factory()->create(['business_id' => $business->id, 'billing_period' => 'yearly']);
            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($renewal, 'settlement'))->assertOk();

            $this->assertSame('2027-11-01 10:00:00', $business->fresh()->subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expired_subscription_renewal_starts_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->ownedBusiness();
            Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
            $this->fakeGateway(configured: true);
            $payment = SubscriptionPayment::factory()->create(['business_id' => $business->id, 'billing_period' => 'monthly']);

            $this->postJson('/api/webhooks/midtrans', $this->midtransPayload($payment, 'settlement'))->assertOk();

            $subscription = $business->fresh()->subscription;
            $this->assertSame('2026-10-01 10:00:00', $subscription?->starts_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-01 10:00:00', $subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cross_tenant_payment_status_returns_not_found(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        [$otherBusiness, $otherOwner, $otherToken] = $this->ownedBusiness();
        $payment = SubscriptionPayment::factory()->create(['business_id' => $business->id, 'user_id' => $owner->id]);

        $this->withToken($otherToken)
            ->getJson('/api/mobile/subscription/payments/'.$payment->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'PAYMENT_NOT_FOUND');
    }

    public function test_payment_status_and_history_reflect_backend_state_and_client_success_cannot_activate(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $otherBusiness = Business::factory()->create();
        $payment = SubscriptionPayment::factory()->create(['business_id' => $business->id, 'user_id' => $owner->id]);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/payments/'.$payment->id.'?status=success')
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionPayment::STATUS_PENDING)
            ->assertJsonPath('data.subscription', null);

        $this->assertFalse($business->fresh()->hasCloudAccess());

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/payments?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/payments?business_id='.$otherBusiness->id)
            ->assertForbidden();
    }

    /**
     * @return array{0: Business, 1: User, 2: string}
     */
    private function ownedBusiness(): array
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        return [$business, $owner, $owner->createToken('mobile-api', ['mobile'])->plainTextToken];
    }

    private function configurePricing(): void
    {
        config()->set('premium.pricing.configured', true);
        config()->set('premium.pricing.mobile_plans', [
            [
                'code' => Subscription::PLAN_CLOUD,
                'name' => 'Cloud',
                'billing_periods' => [
                    ['period' => 'monthly', 'currency' => 'IDR', 'price_minor' => 123456],
                    ['period' => 'yearly', 'currency' => 'IDR', 'price_minor' => 1234560],
                ],
                'available' => true,
            ],
        ]);
    }

    private function fakeGateway(bool $configured): FakeMidtransGateway
    {
        $gateway = new FakeMidtransGateway($configured);
        $this->app->instance(MidtransGateway::class, $gateway);

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function checkoutPayload(Business $business, array $overrides = []): array
    {
        return array_merge([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function midtransPayload(SubscriptionPayment $payment, string $status, ?string $fraudStatus = null, ?string $orderId = null): array
    {
        return [
            'order_id' => $orderId ?? $payment->provider_order_id,
            'transaction_status' => $status,
            'fraud_status' => $fraudStatus,
            'transaction_id' => 'midtrans-'.$payment->id,
            'payment_type' => 'bank_transfer',
        ];
    }
}

final class FakeMidtransGateway implements MidtransGateway
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $snapPayloads = [];

    public bool $validSignature = true;

    public function __construct(
        private readonly bool $configured,
    ) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createSnapTransaction(SubscriptionPayment $payment, Business $business, User $user): MidtransSnapResponse
    {
        $this->snapPayloads[] = [
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => $payment->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
        ];

        return new MidtransSnapResponse('snap-token', 'https://app.sandbox.midtrans.com/snap/v4/redirect');
    }

    public function verifyNotification(array $payload): ?MidtransNotificationResult
    {
        if (! $this->validSignature) {
            return null;
        }

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
