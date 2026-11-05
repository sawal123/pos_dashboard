<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Services\Subscription\Midtrans\MidtransNotificationResult;
use App\Services\Subscription\Midtrans\MidtransSnapResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MidtransGateway::class, new class implements MidtransGateway {
            public function isConfigured(): bool
            {
                return true;
            }

            public function createSnapTransaction(SubscriptionPayment $payment, Business $business, User $user): MidtransSnapResponse
            {
                return new MidtransSnapResponse('token-123', 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-123');
            }

            public function verifyNotification(array $payload): ?MidtransNotificationResult
            {
                return null;
            }

            public function checkTransactionStatus(string $orderId): ?MidtransNotificationResult
            {
                return null;
            }

            public function cancelTransaction(string $orderId): bool
            {
                return true;
            }
        });
    }

    private function createBusinessWithOwner(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business, ['role' => 'owner']);

        return [$user, $business];
    }

    public function test_user_can_view_payment_history_on_subscription_page(): void
    {
        [$user, $business] = $this->createBusinessWithOwner();

        $paymentPending = SubscriptionPayment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-test-pending-01',
            'plan' => 'cloud',
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 49000,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-01',
            'expires_at' => now()->addMinutes(30),
        ]);

        $paymentPaid = SubscriptionPayment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-test-paid-02',
            'plan' => 'cloud',
            'billing_period' => 'yearly',
            'currency' => 'IDR',
            'amount' => 490000,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->get(route('subscriptions.index'));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Transaksi Langganan');
        $response->assertSee('pdash-test-pending-01');
        $response->assertSee('pdash-test-paid-02');
        $response->assertSee('Menunggu Pembayaran');
        $response->assertSee('Berhasil');
        $response->assertSee('Batalkan');
    }

    public function test_user_can_cancel_pending_payment(): void
    {
        [$user, $business] = $this->createBusinessWithOwner();

        $payment = SubscriptionPayment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-cancel-test-01',
            'plan' => 'cloud',
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 49000,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/token-01',
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('subscriptions.payments.cancel', ['paymentId' => $payment->id]));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Transaksi pembayaran berhasil dibatalkan.');

        $payment->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_CANCELLED, $payment->status);
    }

    public function test_user_cannot_cancel_already_paid_payment(): void
    {
        [$user, $business] = $this->createBusinessWithOwner();

        $payment = SubscriptionPayment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-paid-cancel-01',
            'plan' => 'cloud',
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 49000,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('subscriptions.payments.cancel', ['paymentId' => $payment->id]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('cancel');

        $payment->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->status);
    }

    public function test_user_cannot_cancel_cross_tenant_payment(): void
    {
        [$userA, $businessA] = $this->createBusinessWithOwner();
        [$userB, $businessB] = $this->createBusinessWithOwner();

        $paymentB = SubscriptionPayment::create([
            'business_id' => $businessB->id,
            'user_id' => $userB->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-tenant-b-01',
            'plan' => 'cloud',
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 49000,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['current_business_id' => $businessA->id])
            ->post(route('subscriptions.payments.cancel', ['paymentId' => $paymentB->id]));

        $response->assertStatus(404);

        $paymentB->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $paymentB->status);
    }

    public function test_user_can_sync_pending_payment_status(): void
    {
        [$user, $business] = $this->createBusinessWithOwner();

        $payment = SubscriptionPayment::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'provider_order_id' => 'pdash-sync-test-01',
            'plan' => 'cloud',
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'amount' => 49000,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->app->instance(MidtransGateway::class, new class implements MidtransGateway {
            public function isConfigured(): bool { return true; }
            public function createSnapTransaction(SubscriptionPayment $payment, Business $business, User $user): MidtransSnapResponse {
                return new MidtransSnapResponse('t', 'u');
            }
            public function verifyNotification(array $payload): ?MidtransNotificationResult { return null; }
            public function checkTransactionStatus(string $orderId): ?MidtransNotificationResult {
                return new MidtransNotificationResult(
                    orderId: $orderId,
                    transactionStatus: 'settlement',
                    fraudStatus: 'accept',
                    transactionId: 'trx-12345',
                    paymentType: 'bank_transfer',
                    payload: ['status_code' => '200'],
                );
            }
            public function cancelTransaction(string $orderId): bool { return true; }
        });

        $response = $this->actingAs($user)
            ->withSession(['current_business_id' => $business->id])
            ->post(route('subscriptions.payments.sync', ['paymentId' => $payment->id]));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $payment->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->status);
        $this->assertSame('trx-12345', $payment->provider_transaction_id);
    }
}

