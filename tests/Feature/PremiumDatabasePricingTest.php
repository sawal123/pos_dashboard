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
use App\Services\Subscription\PremiumPricing;
use App\Services\Subscription\SubscriptionCheckoutService;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PREM-D02C — the database is the authoritative Premium pricing source.
 *
 * Covers the plan/price schema, the idempotent seeder (dummy prices are never
 * official and never overwrite existing rows), the PremiumPricing reader, the
 * mobile catalog, checkout snapshots, price-change history and fail-closed
 * behaviour.
 */
class PremiumDatabasePricingTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1. Canonical plan + dummy seed
    // ============================================================

    public function test_cloud_plan_is_seeded_with_canonical_identity(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $this->assertDatabaseHas('subscription_plans', [
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
            'is_active' => true,
        ]);
    }

    public function test_dummy_development_prices_are_seeded(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $plan = SubscriptionPlan::where('code', Subscription::PLAN_CLOUD)->firstOrFail();

        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 49000,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'yearly',
            'currency' => 'IDR',
            'price_minor' => 490000,
            'is_active' => true,
        ]);
    }

    public function test_seeder_is_idempotent_and_never_overwrites_existing_prices(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->assertSame(1, SubscriptionPlan::count());
        $this->assertSame(2, SubscriptionPlanPrice::count());

        // An admin edits a price; re-seeding must not clobber it.
        $monthly = SubscriptionPlanPrice::where('billing_period', 'monthly')->firstOrFail();
        $monthly->forceFill(['price_minor' => 59000])->save();

        $this->seed(SubscriptionPlanSeeder::class);

        $this->assertSame(59000, $monthly->fresh()->price_minor);
        $this->assertSame(2, SubscriptionPlanPrice::count());
    }

    // ============================================================
    // 2. Schema constraints
    // ============================================================

    public function test_plan_code_is_unique(): void
    {
        SubscriptionPlan::factory()->create(['code' => Subscription::PLAN_CLOUD]);

        $this->expectException(QueryException::class);

        SubscriptionPlan::factory()->create(['code' => Subscription::PLAN_CLOUD]);
    }

    public function test_duplicate_period_and_currency_is_prevented(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->seedPrice($plan, 'monthly', 49000);

        $this->expectException(QueryException::class);

        $this->seedPrice($plan, 'monthly', 59000);
    }

    // ============================================================
    // 3. PremiumPricing reads the database and fails closed
    // ============================================================

    public function test_premium_pricing_reads_database(): void
    {
        $this->seedPlan(activePrices: ['monthly' => 49000]);

        $pricing = app(PremiumPricing::class);

        $this->assertSame(
            ['currency' => 'IDR', 'price_minor' => 49000],
            $pricing->priceFor(Subscription::PLAN_CLOUD, 'monthly'),
        );
        $this->assertTrue($pricing->isConfigured());
    }

    public function test_missing_plan_or_unsupported_input_fails_closed(): void
    {
        $pricing = app(PremiumPricing::class);

        // No plan / no price at all.
        $this->assertNull($pricing->priceFor(Subscription::PLAN_CLOUD, 'monthly'));
        $this->assertFalse($pricing->isConfigured());

        $this->seedPlan(activePrices: ['monthly' => 49000]);

        // Unsupported period and non-canonical plan codes stay denied.
        $this->assertNull($pricing->priceFor(Subscription::PLAN_CLOUD, 'weekly'));
        $this->assertNull($pricing->priceFor(Subscription::PLAN_FREE, 'monthly'));
        $this->assertNull($pricing->priceFor('premium', 'monthly'));
    }

    // ============================================================
    // 4. Catalog is database-driven
    // ============================================================

    public function test_catalog_periods_and_prices_come_from_database(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->seedPlan(activePrices: ['monthly' => 49000, 'yearly' => 490000]);
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', true)
            ->assertJsonPath('data.plans.0.code', Subscription::PLAN_CLOUD)
            ->assertJsonPath('data.plans.0.name', 'Cloud')
            ->assertJsonPath('data.plans.0.available', true)
            ->assertJsonPath('data.plans.0.currency', 'IDR')
            ->assertJsonPath('data.plans.0.billing_periods.0.period', 'monthly')
            ->assertJsonPath('data.plans.0.billing_periods.0.price_minor', 49000)
            ->assertJsonPath('data.plans.0.billing_periods.1.period', 'yearly')
            ->assertJsonPath('data.plans.0.billing_periods.1.price_minor', 490000)
            ->assertJsonPath('data.plans.0.purchasable', true);
    }

    public function test_unpriced_catalog_stays_empty_and_checkout_closed(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        SubscriptionPlan::factory()->create(['code' => Subscription::PLAN_CLOUD]);
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.plans', [])
            ->assertJsonPath('data.checkout_available', false);

        $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertStatus(409)
            ->assertJsonPath('code', 'CHECKOUT_UNAVAILABLE');
    }

    public function test_inactive_plan_is_listed_but_not_purchasable(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->seedPlan(active: false, activePrices: ['monthly' => 49000, 'yearly' => 490000]);
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', false)
            ->assertJsonPath('data.plans.0.code', Subscription::PLAN_CLOUD)
            ->assertJsonPath('data.plans.0.available', false)
            ->assertJsonPath('data.plans.0.billing_periods', [])
            ->assertJsonPath('data.plans.0.purchasable', false);
    }

    public function test_inactive_price_is_not_purchasable(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->seedPlan(
            activePrices: ['monthly' => 49000],
            inactivePrices: ['yearly' => 490000],
        );
        $this->fakeGateway(configured: true);

        $pricing = app(PremiumPricing::class);
        $this->assertNotNull($pricing->priceFor(Subscription::PLAN_CLOUD, 'monthly'));
        $this->assertNull($pricing->priceFor(Subscription::PLAN_CLOUD, 'yearly'));

        $this->withToken($token)
            ->getJson('/api/mobile/subscription/plans?business_id='.$business->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_available', false)
            ->assertJsonPath('data.plans.0.billing_periods.0.period', 'monthly')
            ->assertJsonPath('data.plans.0.purchasable', false);
    }

    public function test_checkout_available_requires_both_periods_and_midtrans(): void
    {
        $plan = $this->seedPlan(activePrices: ['monthly' => 49000]);
        $this->fakeGateway(configured: true);

        $this->assertFalse(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());

        $this->seedPrice($plan, 'yearly', 490000);
        $this->assertTrue(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());

        $this->fakeGateway(configured: false);
        $this->assertFalse(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());
    }

    // ============================================================
    // 5. Checkout is server-authoritative
    // ============================================================

    public function test_checkout_uses_server_database_price_and_snapshots_it(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->seedPlan(activePrices: ['monthly' => 49000, 'yearly' => 490000]);
        $gateway = $this->fakeGateway(configured: true);

        $paymentId = $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertCreated()
            ->json('data.payment_id');

        $payment = SubscriptionPayment::findOrFail($paymentId);

        $this->assertSame(Subscription::PLAN_CLOUD, $payment->plan);
        $this->assertSame('monthly', $payment->billing_period);
        $this->assertSame('IDR', $payment->currency);
        $this->assertSame(49000, $payment->amount);
        $this->assertSame(49000, $gateway->snapPayloads[0]['transaction_details']['gross_amount']);
    }

    public function test_client_cannot_forge_amount_currency_or_price_minor(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $this->seedPlan(activePrices: ['monthly' => 49000]);
        $this->fakeGateway(configured: true);

        foreach (['amount' => 1, 'currency' => 'USD', 'price_minor' => 1] as $field => $value) {
            $this->withToken($token)
                ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business, [$field => $value]))
                ->assertUnprocessable();
        }

        $this->assertSame(0, SubscriptionPayment::count());
    }

    public function test_price_change_affects_new_checkout_but_not_existing_payment(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        $plan = $this->seedPlan(activePrices: ['monthly' => 49000]);
        $this->fakeGateway(configured: true);

        $firstId = $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertCreated()
            ->json('data.payment_id');

        $this->assertSame(49000, SubscriptionPayment::findOrFail($firstId)->amount);

        // Admin raises the price.
        SubscriptionPlanPrice::query()
            ->where('subscription_plan_id', $plan->id)
            ->where('billing_period', 'monthly')
            ->update(['price_minor' => 59000]);

        $secondId = $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($business))
            ->assertCreated()
            ->json('data.payment_id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(59000, SubscriptionPayment::findOrFail($secondId)->amount);
        // The historical snapshot never follows the live price.
        $this->assertSame(49000, SubscriptionPayment::findOrFail($firstId)->amount);
    }

    // ============================================================
    // 6. Tenant security, webhook and activation are unchanged
    // ============================================================

    public function test_tenant_isolation_is_still_enforced(): void
    {
        [$business, $owner, $token] = $this->ownedBusiness();
        [$otherBusiness, , $otherToken] = $this->ownedBusiness();
        $this->seedPlan(activePrices: ['monthly' => 49000, 'yearly' => 490000]);
        $this->fakeGateway(configured: true);

        $this->withToken($token)
            ->postJson('/api/mobile/subscription/checkout', $this->checkoutPayload($otherBusiness))
            ->assertForbidden()
            ->assertJsonPath('code', 'BUSINESS_ACCESS_DENIED');

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
        ]);

        // Switching tokens within one test: the Sanctum guard caches the first
        // resolved user, so drop the guards before acting as another tenant.
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)
            ->getJson('/api/mobile/subscription/payments/'.$payment->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'PAYMENT_NOT_FOUND');
    }

    public function test_webhook_and_activation_behavior_is_unchanged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00', 'Asia/Jakarta'));

        try {
            [$business] = $this->ownedBusiness();
            $this->seedPlan(activePrices: ['monthly' => 49000, 'yearly' => 490000]);
            $this->fakeGateway(configured: true);

            $payment = SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'billing_period' => 'monthly',
            ]);

            $payload = [
                'order_id' => $payment->provider_order_id,
                'transaction_status' => 'settlement',
                'fraud_status' => null,
                'transaction_id' => 'midtrans-'.$payment->id,
                'payment_type' => 'bank_transfer',
            ];

            $this->postJson('/api/webhooks/midtrans', $payload)->assertOk();
            // Duplicate paid webhook must not double-activate.
            $this->postJson('/api/webhooks/midtrans', $payload)->assertOk();

            $subscription = $business->fresh()->subscription;
            $this->assertTrue($business->fresh()->hasCloudAccess());
            $this->assertSame(
                '2026-11-01 10:00:00',
                $subscription?->expires_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: Business, 1: User, 2: string}
     */
    private function ownedBusiness(): array
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        return [$business, $owner, $owner->createToken('mobile-api', ['mobile'])->plainTextToken];
    }

    /**
     * @param  array<string, int>  $activePrices
     * @param  array<string, int>  $inactivePrices
     */
    private function seedPlan(
        bool $active = true,
        array $activePrices = ['monthly' => 49000, 'yearly' => 490000],
        array $inactivePrices = [],
    ): SubscriptionPlan {
        $plan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
            'is_active' => $active,
        ]);

        foreach ($activePrices as $period => $priceMinor) {
            $this->seedPrice($plan, $period, $priceMinor, true);
        }

        foreach ($inactivePrices as $period => $priceMinor) {
            $this->seedPrice($plan, $period, $priceMinor, false);
        }

        return $plan;
    }

    private function seedPrice(
        SubscriptionPlan $plan,
        string $period,
        int $priceMinor,
        bool $active = true,
    ): SubscriptionPlanPrice {
        return SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => $period,
            'currency' => 'IDR',
            'price_minor' => $priceMinor,
            'is_active' => $active,
        ]);
    }

    private function fakeGateway(bool $configured): DatabasePricingFakeMidtransGateway
    {
        $gateway = new DatabasePricingFakeMidtransGateway($configured);
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
}

final class DatabasePricingFakeMidtransGateway implements MidtransGateway
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $snapPayloads = [];

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
        ];

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
