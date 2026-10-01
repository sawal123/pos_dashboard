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
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADMIN-06 — Platform Admin Premium plan & pricing management.
 */
class PlatformPremiumPricingTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // A. Access control
    // ============================================================

    public function test_platform_admin_can_open_plan_pricing_index(): void
    {
        $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Paket & Harga');
    }

    public function test_business_owner_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('platform.subscription-plans.index'))
            ->assertForbidden();
    }

    public function test_member_is_forbidden(): void
    {
        $member = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($member->id, ['role' => Business::ROLE_MEMBER]);

        $this->actingAs($member)
            ->get(route('platform.subscription-plans.index'))
            ->assertForbidden();
    }

    public function test_regular_user_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('platform.subscription-plans.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('platform.subscription-plans.index'))
            ->assertRedirect(route('login'));
    }

    public function test_business_owner_cannot_mutate_plan_or_prices(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)
            ->patch(route('platform.subscription-plans.update', $plan), ['name' => 'X', 'is_active' => '1'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('platform.subscription-plans.prices.store', $plan), [
                'billing_period' => 'yearly', 'price_minor' => 1, 'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 1, 'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(49000, $price->fresh()->price_minor);
    }

    // ============================================================
    // B. Catalog display + Rupiah formatting
    // ============================================================

    public function test_cloud_plan_and_both_prices_are_listed(): void
    {
        $plan = $this->canonicalPlan();
        $this->price($plan, 'monthly', 49000);
        $this->price($plan, 'yearly', 490000);

        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Cloud')
            ->assertSee($plan->code)
            ->assertSee('Rp 49.000')
            ->assertSee('Rp 490.000');
    }

    public function test_price_is_formatted_without_decimals(): void
    {
        $plan = $this->canonicalPlan();
        $this->price($plan, 'monthly', 49000);

        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Rp 49.000')
            ->assertDontSee('49.000,00');
    }

    public function test_show_page_displays_both_price_sections(): void
    {
        $plan = $this->canonicalPlan();
        $this->price($plan, 'monthly', 49000);
        $this->price($plan, 'yearly', 490000);

        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.show', $plan))
            ->assertOk()
            ->assertSee('Harga Bulanan')
            ->assertSee('Harga Tahunan')
            ->assertSee('Rp 49.000')
            ->assertSee('Rp 490.000');
    }

    // ============================================================
    // C. Plan update + enable/disable
    // ============================================================

    public function test_admin_can_update_plan(): void
    {
        $plan = $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.update', $plan), [
                'name' => 'Cloud Pro',
                'description' => 'Paket premium cloud',
                'is_active' => '1',
            ])
            ->assertRedirect(route('platform.subscription-plans.show', $plan));

        $plan->refresh();
        $this->assertSame('Cloud Pro', $plan->name);
        $this->assertSame('Paket premium cloud', $plan->description);
        $this->assertTrue($plan->is_active);
    }

    public function test_admin_can_disable_and_enable_cloud_plan(): void
    {
        $plan = $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.update', $plan), [
                'name' => $plan->name, 'description' => null, 'is_active' => '0',
            ])
            ->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.update', $plan), [
                'name' => $plan->name, 'description' => null, 'is_active' => '1',
            ])
            ->assertRedirect();
        $this->assertTrue($plan->fresh()->is_active);
    }

    public function test_plan_code_cannot_be_changed(): void
    {
        $plan = $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.update', $plan), [
                'name' => 'Cloud',
                'is_active' => '1',
                'code' => 'evil',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(Subscription::PLAN_CLOUD, $plan->fresh()->code);
    }

    public function test_non_canonical_plan_is_not_found(): void
    {
        $other = SubscriptionPlan::factory()->create(['code' => 'premium', 'name' => 'Premium']);

        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.show', $other))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.update', $other), ['name' => 'X', 'is_active' => '1'])
            ->assertNotFound();
    }

    // ============================================================
    // D. Price update + enable/disable
    // ============================================================

    public function test_admin_can_update_monthly_price(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 59000,
                'is_active' => '1',
            ])
            ->assertRedirect(route('platform.subscription-plans.show', $plan));

        $this->assertSame(59000, $price->fresh()->price_minor);
    }

    public function test_admin_can_update_yearly_price(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'yearly', 490000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 590000,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(590000, $price->fresh()->price_minor);
    }

    public function test_price_minor_is_stored_as_integer(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => '59000',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $stored = $price->fresh()->price_minor;
        $this->assertIsInt($stored);
        $this->assertSame(59000, $stored);
    }

    public function test_admin_can_disable_and_enable_prices(): void
    {
        $plan = $this->canonicalPlan();
        $monthly = $this->price($plan, 'monthly', 49000);
        $yearly = $this->price($plan, 'yearly', 490000);

        // Disable both.
        foreach ([$monthly, $yearly] as $price) {
            $this->actingAs($this->admin())
                ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                    'price_minor' => $price->price_minor,
                    'is_active' => '0',
                ])
                ->assertRedirect();
            $this->assertFalse($price->fresh()->is_active);
        }

        // Enable both.
        foreach ([$monthly, $yearly] as $price) {
            $this->actingAs($this->admin())
                ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                    'price_minor' => $price->price_minor,
                    'is_active' => '1',
                ])
                ->assertRedirect();
            $this->assertTrue($price->fresh()->is_active);
        }
    }

    public function test_billing_period_cannot_be_changed(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 59000,
                'is_active' => '1',
                'billing_period' => 'weekly',
            ])
            ->assertSessionHasErrors('billing_period');

        $this->assertSame('monthly', $price->fresh()->billing_period);
    }

    public function test_currency_cannot_be_changed(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 59000,
                'is_active' => '1',
                'currency' => 'USD',
            ])
            ->assertSessionHasErrors('currency');

        $this->assertSame('IDR', $price->fresh()->currency);
    }

    public function test_price_belongs_to_plan_scope_binding(): void
    {
        $plan = $this->canonicalPlan();
        $other = SubscriptionPlan::factory()->create(['code' => 'premium', 'name' => 'Premium']);
        $foreignPrice = $this->price($other, 'monthly', 1000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $foreignPrice]), [
                'price_minor' => 999,
                'is_active' => '1',
            ])
            ->assertNotFound();

        // The out-of-scope row is untouched.
        $this->assertSame(1000, $foreignPrice->fresh()->price_minor);
    }

    public function test_non_idr_cloud_monthly_price_cannot_be_mutated(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000, currency: 'USD');

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 59000,
                'is_active' => '0',
            ])
            ->assertNotFound();

        // Rejected, never silently converted to IDR.
        $price->refresh();
        $this->assertSame('USD', $price->currency);
        $this->assertSame(49000, $price->price_minor);
        $this->assertTrue($price->is_active);
    }

    public function test_non_idr_cloud_yearly_price_cannot_be_mutated(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'yearly', 490000, currency: 'USD');

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 590000,
                'is_active' => '0',
            ])
            ->assertNotFound();

        $price->refresh();
        $this->assertSame('USD', $price->currency);
        $this->assertSame(490000, $price->price_minor);
        $this->assertTrue($price->is_active);
    }

    public function test_unsupported_period_price_cannot_be_mutated(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'weekly', 15000);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 999,
                'is_active' => '1',
            ])
            ->assertNotFound();

        $price->refresh();
        $this->assertSame('weekly', $price->billing_period);
        $this->assertSame(15000, $price->price_minor);
    }

    // ============================================================
    // E. Missing price rows
    // ============================================================

    public function test_missing_monthly_row_can_be_created(): void
    {
        $plan = $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->post(route('platform.subscription-plans.prices.store', $plan), [
                'billing_period' => 'monthly',
                'price_minor' => 49000,
                'is_active' => '1',
            ])
            ->assertRedirect(route('platform.subscription-plans.show', $plan));

        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 49000,
            'is_active' => true,
        ]);
    }

    public function test_missing_yearly_row_can_be_created(): void
    {
        $plan = $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->post(route('platform.subscription-plans.prices.store', $plan), [
                'billing_period' => 'yearly',
                'price_minor' => 490000,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subscription_plan_prices', [
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'yearly',
            'currency' => 'IDR',
            'price_minor' => 490000,
        ]);
    }

    public function test_invalid_prices_are_rejected(): void
    {
        $plan = $this->canonicalPlan();

        foreach ([0, -1, 1000000001] as $bad) {
            $this->actingAs($this->admin())
                ->post(route('platform.subscription-plans.prices.store', $plan), [
                    'billing_period' => 'monthly',
                    'price_minor' => $bad,
                    'is_active' => '1',
                ])
                ->assertSessionHasErrors('price_minor');
        }

        $this->actingAs($this->admin())
            ->post(route('platform.subscription-plans.prices.store', $plan), [
                'billing_period' => 'monthly',
                'price_minor' => 'abc',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('price_minor');

        $this->assertSame(0, SubscriptionPlanPrice::query()->where('subscription_plan_id', $plan->id)->count());
    }

    public function test_invalid_billing_period_is_rejected(): void
    {
        $plan = $this->canonicalPlan();

        $this->actingAs($this->admin())
            ->post(route('platform.subscription-plans.prices.store', $plan), [
                'billing_period' => 'weekly',
                'price_minor' => 49000,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('billing_period');

        $this->assertSame(0, SubscriptionPlanPrice::query()->where('subscription_plan_id', $plan->id)->count());
    }

    // ============================================================
    // F. Checkout readiness
    // ============================================================

    public function test_readiness_becomes_true_with_valid_plan_prices_and_midtrans(): void
    {
        $plan = $this->canonicalPlan();

        $this->midtransConfigured(true);

        // Not ready yet: prices are missing.
        $this->assertFalse(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());
        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Checkout belum siap')
            ->assertSee('Harga Bulanan belum tersedia atau nonaktif.')
            ->assertSee('Harga Tahunan belum tersedia atau nonaktif.');

        $this->price($plan, 'monthly', 49000);
        $this->price($plan, 'yearly', 490000);

        $this->assertTrue(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());
        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Checkout siap')
            ->assertDontSee('Checkout belum siap');
    }

    public function test_readiness_false_when_plan_disabled(): void
    {
        $plan = $this->canonicalPlan();
        $plan->forceFill(['is_active' => false])->save();
        $this->price($plan, 'monthly', 49000);
        $this->price($plan, 'yearly', 490000);
        $this->midtransConfigured(true);

        $this->assertFalse(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());
        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Checkout belum siap')
            ->assertSee('Paket Cloud sedang nonaktif.');
    }

    public function test_readiness_false_when_required_price_disabled(): void
    {
        $plan = $this->canonicalPlan();
        $this->price($plan, 'monthly', 49000, active: false);
        $this->price($plan, 'yearly', 490000);
        $this->midtransConfigured(true);

        $this->assertFalse(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());
        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Checkout belum siap')
            ->assertSee('Harga Bulanan belum tersedia atau nonaktif.');
    }

    public function test_readiness_false_when_midtrans_not_configured(): void
    {
        $plan = $this->canonicalPlan();
        $this->price($plan, 'monthly', 49000);
        $this->price($plan, 'yearly', 490000);
        $this->midtransConfigured(false);

        $this->assertFalse(app(SubscriptionCheckoutService::class)->isCheckoutConfigured());
        $this->actingAs($this->admin())
            ->get(route('platform.subscription-plans.index'))
            ->assertOk()
            ->assertSee('Checkout belum siap')
            ->assertSee('Midtrans belum dikonfigurasi.');
    }

    // ============================================================
    // G. Historical snapshot protection
    // ============================================================

    public function test_price_update_does_not_change_existing_payment(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $payment = SubscriptionPayment::factory()->create([
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 49000,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('platform.subscription-plans.prices.update', [$plan, $price]), [
                'price_minor' => 59000,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(49000, $payment->amount);
        $this->assertSame('IDR', $payment->currency);
        $this->assertSame('monthly', $payment->billing_period);
    }

    public function test_new_checkout_uses_new_price(): void
    {
        $plan = $this->canonicalPlan();
        $price = $this->price($plan, 'monthly', 49000);

        $this->app->instance(MidtransGateway::class, new PlatformPricingFakeMidtransGateway(true));

        $checkout = app(SubscriptionCheckoutService::class);
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $first = $checkout->createCheckout($business, $user, Subscription::PLAN_CLOUD, 'monthly');
        $this->assertSame(49000, $first->amount);

        $price->forceFill(['price_minor' => 59000])->save();

        $second = $checkout->createCheckout($business, $user, Subscription::PLAN_CLOUD, 'monthly');
        $this->assertSame(59000, $second->amount);
        $this->assertNotSame($first->id, $second->id);

        // The earlier snapshot stays 49000.
        $this->assertSame(49000, $first->fresh()->amount);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function admin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    private function canonicalPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
        ]);
    }

    private function price(SubscriptionPlan $plan, string $period, int $priceMinor, bool $active = true, string $currency = 'IDR'): SubscriptionPlanPrice
    {
        return SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => $period,
            'currency' => $currency,
            'price_minor' => $priceMinor,
            'is_active' => $active,
        ]);
    }

    private function midtransConfigured(bool $configured): void
    {
        config()->set('premium.midtrans.server_key', $configured ? 'test-server-key' : '');
    }
}

final class PlatformPricingFakeMidtransGateway implements MidtransGateway
{
    public function __construct(
        private readonly bool $configured,
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
        return null;
    }
}
