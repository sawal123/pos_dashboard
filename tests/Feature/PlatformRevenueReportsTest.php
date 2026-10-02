<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Platform\PlatformRevenueReportsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformRevenueReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    // ============================================================
    // 1. Access Control Tests (Section 61)
    // ============================================================

    public function test_platform_admin_can_access_revenue_reports(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue');

        $response->assertOk();
        $response->assertSee('Laporan Revenue &amp; Billing', false);
        $response->assertSee('Revenue Subscription Paid');
        $response->assertSee('Pendapatan Pembayaran Berhasil');
    }

    public function test_business_owner_is_forbidden_from_revenue_reports(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/revenue')->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_revenue_reports(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/revenue')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/platform/revenue')->assertRedirect(route('login'));
    }

    public function test_unverified_platform_admin_is_redirected_to_verification(): void
    {
        $unverifiedAdmin = User::factory()->unverified()->create(['is_platform_admin' => true]);

        $this->actingAs($unverifiedAdmin)->get('/platform/revenue')->assertRedirect(route('verification.notice'));
    }

    // ============================================================
    // 2. Paid Revenue & Status Filtering Tests (Section 62)
    // ============================================================

    public function test_only_current_status_paid_is_counted_in_paid_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        // 2 paid payments
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 100000,
            'paid_at' => now()->subDays(5),
            'currency' => 'IDR',
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 200000,
            'paid_at' => now()->subDays(3),
            'currency' => 'IDR',
        ]);

        // Non-paid statuses
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'amount' => 300000,
            'paid_at' => null,
            'created_at' => now()->subDays(2),
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_FAILED,
            'amount' => 400000,
            'paid_at' => null,
            'created_at' => now()->subDays(2),
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_REFUNDED,
            'amount' => 500000,
            'paid_at' => now()->subDays(1), // Had paid_at originally, but status is now refunded!
            'created_at' => now()->subDays(4),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue');

        $response->assertOk();
        // Expected paid revenue: 100.000 + 200.000 = 300.000
        $response->assertSee('Rp 300.000');
        // Total paid payments count: 2
        $data = app(PlatformRevenueReportsData::class)->get();
        $this->assertSame(300000, $data['summary']['total_paid_revenue']);
        $this->assertSame(2, $data['summary']['total_paid_payments']);
    }

    // ============================================================
    // 3. Paid_at Semantics Tests (Section 63)
    // ============================================================

    public function test_revenue_recognition_follows_paid_at_not_created_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        // Payment A: created inside 30-day period (10 days ago), but paid_at was 45 days ago (outside period)
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'created_at' => now()->subDays(10),
            'paid_at' => now()->subDays(45),
            'currency' => 'IDR',
        ]);

        // Payment B: created outside period (45 days ago), but paid_at inside period (5 days ago)
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 250000,
            'created_at' => now()->subDays(45),
            'paid_at' => now()->subDays(5),
            'currency' => 'IDR',
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Only Payment B should be recognized in 30-day paid revenue
        $this->assertSame(250000, $data['summary']['total_paid_revenue']);
        $this->assertSame(1, $data['summary']['total_paid_payments']);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue?date=30days');
        $response->assertOk();
        $response->assertSee('Rp 250.000');
    }

    // ============================================================
    // 4. Billing Period Breakdown Tests (Section 64)
    // ============================================================

    public function test_billing_period_breakdown_calculates_monthly_and_yearly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        // 2 Monthly paid
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'billing_period' => 'monthly',
            'amount' => 50000,
            'paid_at' => now()->subDays(10),
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'billing_period' => 'monthly',
            'amount' => 50000,
            'paid_at' => now()->subDays(5),
        ]);

        // 1 Yearly paid
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'billing_period' => 'yearly',
            'amount' => 500000,
            'paid_at' => now()->subDays(2),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $this->assertSame(100000, $data['billing_period_breakdown']['monthly']['amount']);
        $this->assertSame(2, $data['billing_period_breakdown']['monthly']['count']);
        $this->assertSame(500000, $data['billing_period_breakdown']['yearly']['amount']);
        $this->assertSame(1, $data['billing_period_breakdown']['yearly']['count']);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue?date=30days');
        $response->assertOk();
        $response->assertSee('Rp 100.000');
        $response->assertSee('Rp 500.000');
    }

    // ============================================================
    // 5. Price Snapshot Test (Section 65)
    // ============================================================

    public function test_historical_revenue_uses_payment_snapshot_not_current_catalog(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        // Historical payment was snapshot at Rp 150.000
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
            'amount' => 150000,
            'paid_at' => now()->subDays(10),
        ]);

        // Even if active price catalog is now Rp 250.000
        SubscriptionPlanPrice::factory()->create([
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 250000,
            'is_active' => true,
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Revenue must remain Rp 150.000 (payment snapshot)
        $this->assertSame(150000, $data['summary']['total_paid_revenue']);
    }

    // ============================================================
    // 6. Refund Semantics Tests (Section 66)
    // ============================================================

    public function test_refunded_payment_is_excluded_from_paid_revenue_but_activation_counted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        // Payment was originally paid and activated within period, but later marked refunded
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_REFUNDED,
            'amount' => 150000,
            'paid_at' => now()->subDays(5),
            'activated_at' => now()->subDays(5),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Revenue must NOT include refunded payment
        $this->assertSame(0, $data['summary']['total_paid_revenue']);
        $this->assertSame(0, $data['summary']['total_paid_payments']);

        // But historical activation event IS counted
        $this->assertSame(1, $data['activation_stats']['total_activations']);
        $this->assertSame(1, $data['activation_stats']['first_activations']);
        // And current refunded diagnostics
        $this->assertSame(1, $data['diagnostics']['current_refunded_total']);
    }

    // ============================================================
    // 7. Payment Status Breakdown Tests (Section 67)
    // ============================================================

    public function test_payment_status_funnel_groups_by_created_at_in_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        $statuses = [
            SubscriptionPayment::STATUS_PENDING,
            SubscriptionPayment::STATUS_PAID,
            SubscriptionPayment::STATUS_FAILED,
            SubscriptionPayment::STATUS_EXPIRED,
            SubscriptionPayment::STATUS_CANCELLED,
            SubscriptionPayment::STATUS_REFUNDED,
        ];

        foreach ($statuses as $st) {
            SubscriptionPayment::factory()->create([
                'business_id' => $business->id,
                'status' => $st,
                'created_at' => now()->subDays(2),
                'paid_at' => $st === SubscriptionPayment::STATUS_PAID ? now()->subDays(2) : null,
            ]);
        }

        // An older payment created 40 days ago should NOT be in the created_at funnel
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PENDING,
            'created_at' => now()->subDays(40),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $funnel = $data['payment_status_distribution'];
        $this->assertSame(1, $funnel['pending']);
        $this->assertSame(1, $funnel['paid']);
        $this->assertSame(1, $funnel['failed']);
        $this->assertSame(1, $funnel['expired']);
        $this->assertSame(1, $funnel['cancelled']);
        $this->assertSame(1, $funnel['refunded']);
        $this->assertSame(6, $funnel['total']);
    }

    // ============================================================
    // 8. First vs Subsequent Activation Tests (Sections 68, 69, 70)
    // ============================================================

    public function test_first_and_subsequent_activation_ordering_per_business(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        // Payment 1 activated earlier
        SubscriptionPayment::factory()->create([
            'id' => 10,
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'activated_at' => Carbon::parse('2026-10-01 10:00:00'),
            'paid_at' => Carbon::parse('2026-10-01 09:59:00'),
        ]);

        // Payment 2 activated later
        SubscriptionPayment::factory()->create([
            'id' => 20,
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'activated_at' => Carbon::parse('2026-10-02 10:00:00'),
            'paid_at' => Carbon::parse('2026-10-02 09:59:00'),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get([
            'date' => 'custom',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
        ]);

        $this->assertSame(1, $data['activation_stats']['first_activations']);
        $this->assertSame(1, $data['activation_stats']['subsequent_activations']);
        $this->assertSame(2, $data['activation_stats']['total_activations']);
    }

    public function test_same_timestamp_activation_tie_breaker_uses_id(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();
        $sameTime = Carbon::parse('2026-10-01 12:00:00');

        // Payment A: lower id
        SubscriptionPayment::factory()->create([
            'id' => 100,
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'activated_at' => $sameTime,
            'paid_at' => $sameTime,
        ]);

        // Payment B: higher id
        SubscriptionPayment::factory()->create([
            'id' => 200,
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'activated_at' => $sameTime,
            'paid_at' => $sameTime,
        ]);

        $data = app(PlatformRevenueReportsData::class)->get([
            'date' => 'custom',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
        ]);

        // Exactly 1 first activation and 1 subsequent activation
        $this->assertSame(1, $data['activation_stats']['first_activations']);
        $this->assertSame(1, $data['activation_stats']['subsequent_activations']);
    }

    public function test_multi_business_first_activations_are_scoped_per_business(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        SubscriptionPayment::factory()->create([
            'business_id' => $businessA->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'activated_at' => now()->subDays(3),
            'paid_at' => now()->subDays(3),
        ]);

        SubscriptionPayment::factory()->create([
            'business_id' => $businessB->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'activated_at' => now()->subDays(1),
            'paid_at' => now()->subDays(1),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Both are first activations for their respective businesses
        $this->assertSame(2, $data['activation_stats']['first_activations']);
        $this->assertSame(0, $data['activation_stats']['subsequent_activations']);
    }

    // ============================================================
    // 9. Manual Subscription & Diagnostic Tests (Sections 71, 72)
    // ============================================================

    public function test_manual_admin_subscription_without_payment_not_counted_in_payment_activations(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        // Create active Cloud subscription row manually without payment
        Subscription::create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $this->assertSame(0, $data['activation_stats']['first_activations']);
        $this->assertSame(0, $data['activation_stats']['subsequent_activations']);
        // But appears in real-time Cloud snapshot
        $this->assertSame(1, $data['subscription_snapshot']['cloud_active']);
    }

    public function test_paid_not_activated_payment_enters_revenue_and_diagnostic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'paid_at' => now()->subDays(2),
            'activated_at' => null, // Paid but NOT activated!
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Revenue includes it
        $this->assertSame(150000, $data['summary']['total_paid_revenue']);
        $this->assertSame(1, $data['summary']['total_paid_payments']);

        // Diagnostic flag detects it
        $this->assertSame(1, $data['diagnostics']['paid_not_activated']);

        // But NOT counted as activation
        $this->assertSame(0, $data['activation_stats']['first_activations']);
        $this->assertSame(0, $data['activation_stats']['subsequent_activations']);
    }

    // ============================================================
    // 10. Date-Aware Current Expired Test (Section 73)
    // ============================================================

    public function test_subscription_active_in_db_but_past_expires_at_is_treated_as_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        // status = active in DB, but expires_at was yesterday
        Subscription::create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_period' => 'monthly',
            'starts_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $this->assertSame(0, $data['subscription_snapshot']['cloud_active']);
        $this->assertSame(1, $data['subscription_snapshot']['cloud_expired']);
    }

    // ============================================================
    // 11. Multi-Currency Test (Section 74)
    // ============================================================

    public function test_multi_currency_payments_are_not_summed_together_and_flag_set(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'currency' => 'IDR',
            'paid_at' => now()->subDays(2),
        ]);

        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 50,
            'currency' => 'USD',
            'paid_at' => now()->subDays(1),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $this->assertTrue($data['summary']['is_multi_currency']);
        $this->assertArrayHasKey('IDR', $data['summary']['currency_breakdown']);
        $this->assertArrayHasKey('USD', $data['summary']['currency_breakdown']);

        $this->assertSame(150000, $data['summary']['currency_breakdown']['IDR']['amount']);
        $this->assertSame(50, $data['summary']['currency_breakdown']['USD']['amount']);
    }

    public function test_billing_breakdown_multi_currency_never_sums_cross_currency(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();

        // Monthly: IDR 150000 + USD 50
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'billing_period' => 'monthly',
            'amount' => 150000,
            'currency' => 'IDR',
            'paid_at' => now()->subDays(2),
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'billing_period' => 'monthly',
            'amount' => 50,
            'currency' => 'USD',
            'paid_at' => now()->subDays(2),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $monthlyBreakdown = $data['billing_period_breakdown']['monthly'];
        $this->assertSame(2, $monthlyBreakdown['count']);

        // Assert strictly grouped canonical structure exists
        $this->assertArrayHasKey('IDR', $monthlyBreakdown['currencies']);
        $this->assertArrayHasKey('USD', $monthlyBreakdown['currencies']);
        $this->assertSame(150000, $monthlyBreakdown['currencies']['IDR']['amount']);
        $this->assertSame(50, $monthlyBreakdown['currencies']['USD']['amount']);

        // Assert NO combined cross-currency arithmetic (e.g. 150050)
        $this->assertNotSame(150050, $monthlyBreakdown['amount']);
        $this->assertSame(150000, $monthlyBreakdown['idr_amount']);
        $this->assertSame(150000, $data['summary']['monthly_revenue_idr']);
        $this->assertNotSame(150050, $data['summary']['monthly_revenue']);
    }

    public function test_daily_trend_multi_currency_separates_currency_totals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();
        $targetDate = Carbon::parse('2026-10-01 10:00:00');

        // Same day: IDR 150000 + USD 50
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'currency' => 'IDR',
            'paid_at' => $targetDate,
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 50,
            'currency' => 'USD',
            'paid_at' => $targetDate,
        ]);

        $data = app(PlatformRevenueReportsData::class)->get([
            'date' => 'custom',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
        ]);

        $trend = $data['trend'];
        $this->assertTrue($trend['is_multi_currency']);

        // Currency totals must be separate map, NOT 150050
        $this->assertSame(['IDR' => 150000, 'USD' => 50], $trend['currency_totals']);
        $this->assertNotSame(150050, $trend['total_amount']);

        // Check the interval on 2026-10-01
        $dayInterval = $trend['intervals'][0];
        $this->assertSame('2026-10-01', $dayInterval['date']);
        $this->assertSame(2, $dayInterval['count']);
        $this->assertArrayHasKey('IDR', $dayInterval['currencies']);
        $this->assertArrayHasKey('USD', $dayInterval['currencies']);
        $this->assertSame(150000, $dayInterval['currencies']['IDR']['amount']);
        $this->assertSame(50, $dayInterval['currencies']['USD']['amount']);
        $this->assertNotSame(150050, $dayInterval['amount']);
    }

    public function test_top_businesses_multi_currency_excludes_non_idr_from_idr_ranking(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();

        $businessA = Business::factory()->create(['name' => 'Bisnis Lokal IDR']);
        $businessB = Business::factory()->create(['name' => 'Bisnis Global USD']);

        // Business A has IDR 100.000
        SubscriptionPayment::factory()->create([
            'business_id' => $businessA->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 100000,
            'currency' => 'IDR',
            'paid_at' => now()->subDays(2),
        ]);

        // Business B has USD 1.000 (nominally smaller number 1000 than 100000, but completely different currency)
        SubscriptionPayment::factory()->create([
            'business_id' => $businessB->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 1000,
            'currency' => 'USD',
            'paid_at' => now()->subDays(1),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Business B (USD) must NOT enter IDR top ranking
        $topList = $data['top_businesses'];
        $this->assertCount(1, $topList);
        $this->assertSame('Bisnis Lokal IDR', $topList[0]['business_name']);
        $this->assertSame('IDR', $topList[0]['currency']);
        $this->assertSame(100000, $topList[0]['paid_revenue']);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue?date=30days');
        $response->assertOk();
        $response->assertSee('Bisnis Lokal IDR');
        $response->assertDontSee('Bisnis Global USD');
    }

    public function test_ui_response_never_renders_combined_cross_currency_sum(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'currency' => 'IDR',
            'paid_at' => now()->subDays(2),
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 50,
            'currency' => 'USD',
            'paid_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue?date=30days');

        $response->assertOk();
        // Assert that misleading cross-currency sums are never displayed
        $response->assertDontSee('150.050');
        $response->assertDontSee('Rp 150.050');
        // Assert currency breakdown is visible
        $response->assertSee('Ditemukan Pembayaran Lebih Dari Satu Mata Uang');
        $response->assertSee('IDR: Rp 150.000');
        $response->assertSee('USD: USD 50');
    }

    // ============================================================
    // 12. Top Paying Businesses Test (Section 75)
    // ============================================================

    public function test_top_paying_businesses_ranked_by_paid_revenue_descending(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();

        $businessA = Business::factory()->create(['name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['name' => 'Bisnis Beta']);

        // Business A: 500.000 paid
        SubscriptionPayment::factory()->create([
            'business_id' => $businessA->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 500000,
            'paid_at' => now()->subDays(2),
        ]);

        // Business B: 200.000 paid + 1.000.000 refunded (refunded should NOT count!)
        SubscriptionPayment::factory()->create([
            'business_id' => $businessB->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 200000,
            'paid_at' => now()->subDays(3),
        ]);
        SubscriptionPayment::factory()->create([
            'business_id' => $businessB->id,
            'status' => SubscriptionPayment::STATUS_REFUNDED,
            'amount' => 1000000,
            'paid_at' => now()->subDays(1),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        $this->assertCount(2, $data['top_businesses']);
        $this->assertSame('Bisnis Alpha', $data['top_businesses'][0]['business_name']);
        $this->assertSame(500000, $data['top_businesses'][0]['paid_revenue']);

        $this->assertSame('Bisnis Beta', $data['top_businesses'][1]['business_name']);
        $this->assertSame(200000, $data['top_businesses'][1]['paid_revenue']);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue?date=30days');
        $response->assertOk();
        $response->assertSee('Bisnis Alpha');
        $response->assertSee('Bisnis Beta');
    }

    // ============================================================
    // 13. Security & Sensitive Data Leak Test (Section 76)
    // ============================================================

    public function test_sensitive_payloads_tokens_and_owner_pii_not_exposed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create(['email' => 'supersecretowner@tenant.test']);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 100000,
            'paid_at' => now()->subDays(1),
            'snap_token' => 'snap-token-xyz-secret-12345',
            'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/secret-url',
            'provider_payload' => ['raw_key' => 'midtrans_secret_payload_value'],
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue');

        $response->assertOk();
        $response->assertDontSee('snap-token-xyz-secret-12345');
        $response->assertDontSee('https://app.midtrans.com/snap/v2/vtweb/secret-url');
        $response->assertDontSee('midtrans_secret_payload_value');
        $response->assertDontSee('supersecretowner@tenant.test');
    }

    // ============================================================
    // 14. Read-Only Verification Test (Section 77)
    // ============================================================

    public function test_get_revenue_endpoint_is_strictly_read_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();

        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 150000,
            'paid_at' => now()->subDays(2),
        ]);

        $paymentCountBefore = SubscriptionPayment::count();
        $businessCountBefore = Business::count();
        $subscriptionCountBefore = Subscription::count();

        $this->actingAs($platformAdmin)->get('/platform/revenue')->assertOk();

        $this->assertSame($paymentCountBefore, SubscriptionPayment::count());
        $this->assertSame($businessCountBefore, Business::count());
        $this->assertSame($subscriptionCountBefore, Subscription::count());
    }

    // ============================================================
    // 15. Date Preset & Custom Reversed Dates Test (Section 78)
    // ============================================================

    public function test_date_filters_and_reversed_custom_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $service = app(PlatformRevenueReportsData::class);

        // Preset: today
        $todayData = $service->get(['date' => 'today']);
        $this->assertSame('Hari Ini', $todayData['current_filters']['period_label']);

        // Preset: 7days
        $sevenDaysData = $service->get(['date' => '7days']);
        $this->assertSame('7 Hari Terakhir', $sevenDaysData['current_filters']['period_label']);

        // Custom reversed: start_date later than end_date -> safely swapped
        $reversedData = $service->get([
            'date' => 'custom',
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-05',
        ]);
        $this->assertSame('2026-10-05', $reversedData['current_filters']['start_formatted']);
        $this->assertSame('2026-10-15', $reversedData['current_filters']['end_formatted']);
    }

    // ============================================================
    // 16. Daily Trend Zero-Fill Test (Section 79)
    // ============================================================

    public function test_daily_trend_zero_fills_days_without_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));

        $business = Business::factory()->create();

        // Only 1 payment on 2026-10-03
        SubscriptionPayment::factory()->create([
            'business_id' => $business->id,
            'status' => SubscriptionPayment::STATUS_PAID,
            'amount' => 100000,
            'paid_at' => Carbon::parse('2026-10-03 10:00:00'),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get([
            'date' => 'custom',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);

        $intervals = $data['trend']['intervals'];
        $this->assertCount(5, $intervals);

        // Day 1 (Oct 1) -> 0
        $this->assertSame('2026-10-01', $intervals[0]['date']);
        $this->assertSame(0, $intervals[0]['count']);
        $this->assertSame(0, $intervals[0]['amount']);

        // Day 3 (Oct 3) -> 1 payment, 100000
        $this->assertSame('2026-10-03', $intervals[2]['date']);
        $this->assertSame(1, $intervals[2]['count']);
        $this->assertSame(100000, $intervals[2]['amount']);
    }

    // ============================================================
    // 17. Zero State Test (Section 58)
    // ============================================================

    public function test_zero_state_displays_clean_defaults(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/revenue');

        $response->assertOk();
        $response->assertSee('Rp 0');
        $response->assertSee('Tidak ada pembayaran berstatus paid pada rentang periode yang dipilih.');
    }

    // ============================================================
    // 18. Isolation from Merchant Sales (Section 42)
    // ============================================================

    public function test_merchant_sales_do_not_leak_into_subscription_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));

        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Create a massive merchant sale
        Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'MERCHANT-TRX-999',
            'status' => 'completed',
            'subtotal' => 99999999,
            'total_amount' => 99999999,
            'sold_at' => now()->subDays(2),
        ]);

        $data = app(PlatformRevenueReportsData::class)->get(['date' => '30days']);

        // Revenue must remain 0 because no subscription payments were made
        $this->assertSame(0, $data['summary']['total_paid_revenue']);
    }
}
