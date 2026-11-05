<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileSubscriptionCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['premium.midtrans.server_key' => 'test-server-key', 'premium.midtrans.is_production' => false]);
        Http::preventStrayRequests();
    }

    private function payment(string $role = Business::ROLE_OWNER): SubscriptionPayment
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);
        $this->withToken($user->createToken('mobile-api', ['mobile'])->plainTextToken);

        return SubscriptionPayment::factory()->create(['user_id' => $user->id, 'business_id' => $business->id, 'snap_token' => 'test-snap']);
    }

    /** @return array<string, mixed> */
    private function providerStatus(SubscriptionPayment $payment, string $status): array
    {
        return ['order_id' => $payment->provider_order_id, 'gross_amount' => (string) $payment->amount, 'transaction_status' => $status];
    }

    public function test_pending_payment_is_cancelled_only_after_provider_confirmation_and_retry_is_idempotent(): void
    {
        $payment = $this->payment();
        Http::fakeSequence()->push($this->providerStatus($payment, 'pending'))->push(['status_code' => '202'])->push($this->providerStatus($payment, 'cancel'));
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertOk();
        Http::assertSentCount(3);
        $this->assertSame('cancelled', $payment->refresh()->status);
        $this->assertNull($payment->activated_at);
    }

    public function test_snap_session_without_selected_method_is_cancelled(): void
    {
        $payment = $this->payment();
        Http::fakeSequence()->push(['status_code' => '404'], 404)->push(['canceled_at' => now()->toIso8601String()]);
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), '/snap/v1/transactions/test-snap/cancel') && $request->hasHeader('Authorization', 'test-server-key'));
    }

    public function test_provider_failure_leaves_payment_pending(): void
    {
        $payment = $this->payment();
        Http::fakeSequence()->push($this->providerStatus($payment, 'pending'))->push([], 503)->push($this->providerStatus($payment, 'pending'));
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertStatus(503)->assertJsonPath('code', 'PAYMENT_CANCEL_FAILED');
        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_unconfirmed_snap_failure_leaves_payment_pending(): void
    {
        $payment = $this->payment();
        Http::fakeSequence()->push(['status_code' => '404'], 404)->push(['errors' => ['unavailable']], 503)->push([], 503)->push([], 503);
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertStatus(503);
        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_already_paid_payment_is_not_sent_for_cancellation(): void
    {
        $payment = $this->payment();
        $payment->update(['status' => 'paid']);
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_paid_race_is_reconciled_without_cancelling_paid_payment(): void
    {
        $payment = $this->payment();
        Http::fakeSequence()->push($this->providerStatus($payment, 'settlement'));
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertStatus(409)->assertJsonPath('data.status', 'paid');
        Http::assertSentCount(1);
        $this->assertNotNull($payment->refresh()->activated_at);
    }

    public function test_wrong_provider_order_cannot_change_payment(): void
    {
        $payment = $this->payment();
        Http::fakeSequence()->push(['order_id' => 'other-order', 'gross_amount' => $payment->amount, 'transaction_status' => 'cancel']);
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertStatus(503);
        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_other_business_payment_is_hidden_and_cashier_cannot_cancel(): void
    {
        $payment = $this->payment(Business::ROLE_CASHIER);
        $this->postJson('/api/mobile/subscription/payments/'.$payment->id.'/cancel')->assertForbidden();
        $foreign = SubscriptionPayment::factory()->create();
        $this->postJson('/api/mobile/subscription/payments/'.$foreign->id.'/cancel')->assertNotFound();
        $this->getJson('/api/mobile/subscription/payments?business_id='.$payment->business_id)->assertOk()->assertJsonPath('data.0.can_cancel', false);
        Http::assertNothingSent();
    }

    public function test_history_contains_safe_display_metadata_and_owner_can_cancel_pending(): void
    {
        $payment = $this->payment();
        $this->getJson('/api/mobile/subscription/payments?business_id='.$payment->business_id)->assertOk()
            ->assertJsonPath('data.0.can_cancel', true)->assertJsonPath('data.0.order_id', $payment->provider_order_id)
            ->assertJsonMissingPath('data.0.snap_token')->assertJsonMissingPath('data.0.provider_payload');
    }
}
