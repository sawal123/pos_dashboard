<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Services\Subscription\Midtrans\MidtransSnapResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionCheckoutWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_start_checkout_and_is_redirected_to_midtrans(): void
    {
        [$user, $business] = $this->ownedBusiness();
        $this->configurePricing();
        $this->fakeGateway();

        $response = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('subscriptions.checkout'), ['billing_period' => 'monthly']);

        $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirect');

        $payment = SubscriptionPayment::query()->firstOrFail();
        $this->assertSame($business->id, $payment->business_id);
        $this->assertSame($user->id, $payment->user_id);
        $this->assertSame(49000, $payment->amount);
        $this->assertSame('monthly', $payment->billing_period);
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $payment->status);
    }

    public function test_member_cannot_start_checkout(): void
    {
        [, $business] = $this->ownedBusiness();
        $member = User::factory()->create();
        $business->users()->attach($member->id, ['role' => Business::ROLE_MEMBER]);
        $this->configurePricing();
        $this->fakeGateway();

        $this->actingAs($member)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('subscriptions.checkout'), ['billing_period' => 'monthly'])
            ->assertForbidden();

        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_checkout_fails_closed_without_pricing(): void
    {
        [$user, $business] = $this->ownedBusiness();
        $this->fakeGateway();

        $response = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('subscriptions.checkout'), ['billing_period' => 'monthly']);

        $response->assertSessionHasErrors('checkout');
        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_checkout_rejects_an_unsupported_period(): void
    {
        [$user, $business] = $this->ownedBusiness();
        $this->configurePricing();
        $this->fakeGateway();

        $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('subscriptions.checkout'), ['billing_period' => 'weekly'])
            ->assertSessionHasErrors('billing_period');

        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_browser_payment_result_page_uses_server_status(): void
    {
        [$user, $business] = $this->ownedBusiness();
        $payment = SubscriptionPayment::factory()->paid()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $this->get(route('midtrans.finish', ['order_id' => $payment->provider_order_id]))
            ->assertOk()
            ->assertSee('Pembayaran Dikonfirmasi')
            ->assertSee($payment->provider_order_id);
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function ownedBusiness(): array
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($user->id, ['role' => Business::ROLE_OWNER]);

        return [$user, $business];
    }

    private function configurePricing(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'code' => Subscription::PLAN_CLOUD,
            'name' => 'Cloud',
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 49000,
        ]);

        SubscriptionPlanPrice::factory()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'yearly',
            'currency' => 'IDR',
            'price_minor' => 490000,
        ]);
    }

    private function fakeGateway(): void
    {
        $gateway = Mockery::mock(MidtransGateway::class);
        $gateway->shouldReceive('isConfigured')->andReturn(true);
        $gateway->shouldReceive('createSnapTransaction')->andReturn(
            new MidtransSnapResponse('snap-token', 'https://app.sandbox.midtrans.com/snap/v4/redirect'),
        );

        $this->app->instance(MidtransGateway::class, $gateway);
    }
}
