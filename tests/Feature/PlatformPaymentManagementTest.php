<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformPaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    /**
     * A. Access Control Tests
     */
    public function test_platform_admin_can_access_payments_index(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/payments');

        $response->assertOk();
        $response->assertSee('Manajemen Pembayaran');
        $response->assertSee('Total Transaksi');
        $response->assertSee('Pembayaran Berhasil');
    }

    public function test_business_owner_is_forbidden_from_payments_index(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/payments')->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_payments_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/payments')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_payments_index(): void
    {
        $this->get('/platform/payments')->assertRedirect(route('login'));
    }

    public function test_platform_admin_can_access_payment_detail(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $payment = SubscriptionPayment::factory()->paid()->create([
            'provider_order_id' => 'ORDER-ADMIN-06-TEST',
            'amount' => 150000,
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('ORDER-ADMIN-06-TEST');
        $response->assertSee('Rp 150.000');
        $response->assertSee('Identitas Pembayaran');
    }

    public function test_business_owner_is_forbidden_from_payment_detail(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $payment = SubscriptionPayment::factory()->create(['business_id' => $business->id]);

        $this->actingAs($owner)->get("/platform/payments/{$payment->id}")->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_payment_detail(): void
    {
        $payment = SubscriptionPayment::factory()->create();

        $this->get("/platform/payments/{$payment->id}")->assertRedirect(route('login'));
    }

    /**
     * B. List Rendering and Summary Aggregates
     */
    public function test_payments_index_displays_canonical_records_and_summary_cards(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $biz1 = Business::factory()->create(['name' => 'Kedai Kopi Nusantara', 'slug' => 'kedai-kopi-nusantara']);
        $biz2 = Business::factory()->create(['name' => 'Laundry Express Bersih', 'slug' => 'laundry-express-bersih']);

        $paid = SubscriptionPayment::factory()->paid()->create([
            'business_id' => $biz1->id,
            'provider_order_id' => 'ORDER-PAID-001',
            'amount' => 200000,
            'billing_period' => 'monthly',
            'plan' => Subscription::PLAN_CLOUD,
        ]);

        $pending = SubscriptionPayment::factory()->create([
            'business_id' => $biz2->id,
            'provider_order_id' => 'ORDER-PENDING-002',
            'amount' => 100000,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'billing_period' => 'monthly',
        ]);

        $failed = SubscriptionPayment::factory()->create([
            'business_id' => $biz1->id,
            'provider_order_id' => 'ORDER-FAILED-003',
            'amount' => 50000,
            'status' => SubscriptionPayment::STATUS_FAILED,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments');

        $response->assertOk();
        $response->assertSee('ORDER-PAID-001');
        $response->assertSee('ORDER-PENDING-002');
        $response->assertSee('ORDER-FAILED-003');
        $response->assertSee('Kedai Kopi Nusantara');
        $response->assertSee('Laundry Express Bersih');
        $response->assertSee('Rp 200.000');
        $response->assertSee('Total Pembayaran Berhasil');
        $response->assertDontSee('MRR');
        $response->assertDontSee('ARR');
    }

    /**
     * C. Search Functionality
     */
    public function test_search_by_provider_order_id(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        SubscriptionPayment::factory()->create(['provider_order_id' => 'MIDTRANS-TARGET-123']);
        SubscriptionPayment::factory()->create(['provider_order_id' => 'MIDTRANS-OTHER-456']);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments?q=TARGET-123');

        $response->assertOk();
        $response->assertSee('MIDTRANS-TARGET-123');
        $response->assertDontSee('MIDTRANS-OTHER-456');
    }

    public function test_search_by_business_name_and_slug(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $biz1 = Business::factory()->create(['name' => 'Bakso Solo Enak', 'slug' => 'bakso-solo-enak']);
        $biz2 = Business::factory()->create(['name' => 'Ayam Geprek Pedas', 'slug' => 'ayam-geprek-pedas']);

        SubscriptionPayment::factory()->create([
            'business_id' => $biz1->id,
            'provider_order_id' => 'ORDER-SOLO-1',
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $biz2->id,
            'provider_order_id' => 'ORDER-GEPREK-2',
        ]);

        // Search by business name
        $response = $this->actingAs($platformAdmin)->get('/platform/payments?q=Bakso+Solo');
        $response->assertOk();
        $response->assertSee('ORDER-SOLO-1');
        $response->assertDontSee('ORDER-GEPREK-2');

        // Search by business slug
        $response2 = $this->actingAs($platformAdmin)->get('/platform/payments?q=ayam-geprek');
        $response2->assertOk();
        $response2->assertSee('ORDER-GEPREK-2');
        $response2->assertDontSee('ORDER-SOLO-1');
    }

    public function test_search_by_idempotency_key(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-IDEM-MATCH',
            'idempotency_key' => 'IDEM-KEY-UNIQUE-789',
        ]);
        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-IDEM-OTHER',
            'idempotency_key' => 'IDEM-KEY-COMMON-000',
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments?q=UNIQUE-789');

        $response->assertOk();
        $response->assertSee('ORDER-IDEM-MATCH');
        $response->assertDontSee('ORDER-IDEM-OTHER');
    }

    /**
     * D. Filter Functionality
     */
    public function test_filter_by_status(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-PENDING-ROW',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);
        SubscriptionPayment::factory()->paid()->create([
            'provider_order_id' => 'ORDER-PAID-ROW',
        ]);
        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-FAILED-ROW',
            'status' => SubscriptionPayment::STATUS_FAILED,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments?status=paid');

        $response->assertOk();
        $response->assertSee('ORDER-PAID-ROW');
        $response->assertDontSee('ORDER-PENDING-ROW');
        $response->assertDontSee('ORDER-FAILED-ROW');
    }

    public function test_filter_by_billing_period(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-MONTHLY-ROW',
            'billing_period' => 'monthly',
        ]);
        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-YEARLY-ROW',
            'billing_period' => 'yearly',
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments?billing_period=yearly');

        $response->assertOk();
        $response->assertSee('ORDER-YEARLY-ROW');
        $response->assertDontSee('ORDER-MONTHLY-ROW');
    }

    public function test_filter_by_plan(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-CLOUD-PLAN',
            'plan' => Subscription::PLAN_CLOUD,
        ]);
        SubscriptionPayment::factory()->create([
            'provider_order_id' => 'ORDER-FREE-PLAN',
            'plan' => Subscription::PLAN_FREE,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments?plan=cloud');

        $response->assertOk();
        $response->assertSee('ORDER-CLOUD-PLAN');
        $response->assertDontSee('ORDER-FREE-PLAN');
    }

    /**
     * E. Pagination (> 25 records)
     */
    public function test_pagination_and_query_string_preservation(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        SubscriptionPayment::factory()->count(30)->create([
            'status' => SubscriptionPayment::STATUS_PAID,
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/payments?status=paid&page=1');

        $response->assertOk();
        $response->assertSee('page=2');
    }

    /**
     * F. Payment Snapshot Accuracy (Historical Integrity)
     */
    public function test_payment_detail_displays_historical_snapshot_not_current_pricing(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['name' => 'Warkop Berkah']);
        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'provider_order_id' => 'HISTORICAL-ORDER-12345',
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'yearly',
            'currency' => 'IDR',
            'amount' => 999999, // Custom historical price snapshot
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => Carbon::parse('2026-05-10 14:30:00'),
            'activated_at' => Carbon::parse('2026-05-10 14:31:00'),
            'expires_at' => Carbon::parse('2027-05-10 14:31:00'),
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('HISTORICAL-ORDER-12345');
        $response->assertSee('Rp 999.999');
        $response->assertSee('Warkop Berkah');
        $response->assertSee('Tahunan (Yearly)');
        $response->assertSee('10 May 2026 14:30:00');
        $response->assertSee('10 May 2026 14:31:00');
        $response->assertSee('10 May 2027 14:31:00');
    }

    /**
     * G. Reconciliation Diagnostic Tests (Historical Integrity decoupled from Current Entitlement)
     */
    public function test_historical_paid_activated_and_expired_is_considered_consistent(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subMonth(),
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now()->subMonths(2),
            'activated_at' => now()->subMonths(2),
            'expires_at' => now()->subMonth(),
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('Konsisten');
        $response->assertDontSee('Perlu Pemeriksaan');
        $response->assertSee('Aktivasi pembayaran tercatat dan masa entitlement dari transaksi ini telah berakhir.');
        $response->assertSee('Denied');
    }

    public function test_historical_paid_activated_with_current_free_plan_is_not_mismatch(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now()->subDays(10),
            'activated_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('Konsisten');
        $response->assertDontSee('Perlu Pemeriksaan');
        $response->assertSee('Free');
        $response->assertSee('Denied');
    }

    public function test_reconciliation_diagnostic_mismatch_when_paid_and_activated_at_is_null(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'paid_at' => now()->subDay(),
            'activated_at' => null, // Mismatch: paid but never activated
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('Perlu Pemeriksaan');
        $response->assertSee('Pembayaran berhasil tetapi aktivasi subscription tidak tercatat.');
    }

    public function test_reconciliation_diagnostic_mismatch_when_non_paid_and_activated_at_is_not_null(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_FAILED,
            'paid_at' => null,
            'activated_at' => now()->subDay(), // Mismatch: failed but has activation timestamp
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('Perlu Pemeriksaan');
        $response->assertSee('Subscription activation tercatat meskipun status pembayaran bukan berhasil');
    }

    public function test_pending_renewal_payment_while_business_has_active_cloud_is_normal(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create();
        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'paid_at' => null,
            'activated_at' => null,
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('Sesuai Status');
        $response->assertDontSee('Perlu Pemeriksaan');
        $response->assertSee('Granted');
    }

    /**
     * H. Edge Cases: Missing Subscription / Orphan Business
     */
    public function test_detail_view_handles_missing_subscription_gracefully(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(); // No subscription created

        $payment = SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('Belum terdaftar');
    }

    public function test_detail_view_handles_safe_metadata_display_and_masks_secrets(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $payment = SubscriptionPayment::factory()->create([
            'snap_token' => 'mock-snap-token-1234567890',
            'idempotency_key' => 'IDEM-SECRET-KEY-9999',
            'provider_payload' => [
                'transaction_status' => 'settlement',
                'payment_type' => 'bank_transfer',
                'fraud_status' => 'accept',
                'bank' => 'bca',
                'signature_key' => 'LEAKED_SIGNATURE_KEY_SHOULD_NEVER_SHOW',
                'server_key' => 'LEAKED_SERVER_KEY_SHOULD_NEVER_SHOW',
            ],
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $response->assertOk();
        $response->assertSee('settlement');
        $response->assertSee('bank_transfer');
        $response->assertSee('bca');
        $response->assertDontSee('LEAKED_SIGNATURE_KEY_SHOULD_NEVER_SHOW');
        $response->assertDontSee('LEAKED_SERVER_KEY_SHOULD_NEVER_SHOW');
        $response->assertDontSee('mock-snap-token-1234567890'); // Token is masked
    }

    /**
     * I. Pure Read-Only Guarantee
     */
    public function test_accessing_payment_console_does_not_mutate_any_state(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $payment = SubscriptionPayment::factory()->create([
            'status' => SubscriptionPayment::STATUS_PENDING,
            'amount' => 100000,
        ]);

        $this->actingAs($platformAdmin)->get('/platform/payments');
        $this->actingAs($platformAdmin)->get("/platform/payments/{$payment->id}");

        $freshPayment = $payment->fresh();
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $freshPayment->status);
        $this->assertSame(100000, $freshPayment->amount);
        $this->assertNull($freshPayment->paid_at);
        $this->assertNull($freshPayment->activated_at);
    }
}
