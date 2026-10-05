<?php

namespace Tests\Feature;

use App\Livewire\Settings\Profile;
use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Support\PlatformSettingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('platform-admin-mutations');
        RateLimiter::clear('login');
    }

    /**
     * 1. Authorization Matrix Regression: Guest redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/platform');

        $response->assertRedirect(route('login'));
    }

    /**
     * 2. Authorization Matrix Regression: Unverified Platform Admin redirected to verification notice.
     */
    public function test_unverified_platform_admin_is_redirected_to_verification_notice(): void
    {
        $unverifiedAdmin = User::factory()->platformAdmin()->unverified()->create();

        $response = $this->actingAs($unverifiedAdmin)->get('/platform');

        $response->assertRedirect(route('verification.notice'));
    }

    /**
     * 3. Authorization Matrix Regression: Verified non-platform user rejected with 403.
     */
    public function test_verified_non_platform_user_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform')->assertForbidden();
    }

    /**
     * 4. Authorization Matrix Regression: Business owner rejected with 403.
     */
    public function test_business_owner_is_forbidden_from_platform_console(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform')->assertForbidden();
        $this->actingAs($owner)->get('/platform/businesses')->assertForbidden();
        $this->actingAs($owner)->get('/platform/settings')->assertForbidden();
    }

    /**
     * 5. Authorization Matrix Regression: Business member and cashier rejected with 403.
     */
    public function test_business_members_and_cashiers_are_forbidden(): void
    {
        $business = Business::factory()->create();
        $member = User::factory()->create();
        $cashier = User::factory()->create();
        $business->users()->attach($member->id, ['role' => Business::ROLE_MEMBER]);
        $business->users()->attach($cashier->id, ['role' => Business::ROLE_CASHIER]);

        $this->actingAs($member)->get('/platform')->assertForbidden();
        $this->actingAs($cashier)->get('/platform')->assertForbidden();
    }

    /**
     * 6. Authorization Matrix Regression: Verified Platform Admin without 2FA is redirected to security setup.
     */
    public function test_platform_admin_without_2fa_is_redirected_to_security_settings(): void
    {
        $adminWithout2fa = User::factory()->platformAdmin()->withoutTwoFactor()->create();

        $response = $this->actingAs($adminWithout2fa)->get('/platform');

        $response->assertRedirect(route('security.edit'));
        $response->assertSessionHas('status', 'Platform Admin wajib mengaktifkan autentikasi dua faktor sebelum mengakses konsol platform.');
    }

    /**
     * 7. Authorization Matrix Regression: Platform Admin with unconfirmed 2FA is redirected to security setup.
     */
    public function test_platform_admin_with_unconfirmed_2fa_is_redirected_to_security_settings(): void
    {
        $adminUnconfirmed = User::factory()->platformAdmin()->create([
            'two_factor_secret' => encrypt('secret-key'),
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->actingAs($adminUnconfirmed)->get('/platform');

        $response->assertRedirect(route('security.edit'));
        $response->assertSessionHas('status', 'Platform Admin wajib mengaktifkan autentikasi dua faktor sebelum mengakses konsol platform.');
    }

    /**
     * 8. Authorization Matrix Regression: Confirmed 2FA Platform Admin accesses console successfully (200 OK).
     */
    public function test_confirmed_2fa_platform_admin_can_access_platform(): void
    {
        $confirmedAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($confirmedAdmin)->get('/platform');

        $response->assertOk();
    }

    /**
     * 9. 2FA Setup Path: Admin without 2FA can reach /settings/security without redirect loops.
     */
    public function test_admin_without_2fa_can_access_security_settings_without_redirect_loop(): void
    {
        $adminWithout2fa = User::factory()->platformAdmin()->withoutTwoFactor()->create();

        $response = $this->actingAs($adminWithout2fa)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();
        $response->assertSee('Two-factor authentication');
    }

    /**
     * 10. 2FA Revocation: Disabling 2FA immediately blocks access to /platform on the next request.
     */
    public function test_disabling_2fa_immediately_revokes_platform_access(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get('/platform')->assertOk();

        // Simulate operator disabling 2FA
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $response = $this->actingAs($admin)->get('/platform');

        $response->assertRedirect(route('security.edit'));
        $response->assertSessionHas('status', 'Platform Admin wajib mengaktifkan autentikasi dua faktor sebelum mengakses konsol platform.');
    }

    /**
     * 11. Privilege Demotion: Revoking is_platform_admin immediately 403s on the next request.
     */
    public function test_demoted_platform_admin_is_immediately_forbidden(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get('/platform')->assertOk();

        // Demote admin
        $admin->forceFill(['is_platform_admin' => false])->save();

        $this->actingAs($admin)->get('/platform')->assertForbidden();
    }

    /**
     * 12. Privilege Grant: Regular user granted is_platform_admin still requires confirmed 2FA.
     */
    public function test_newly_granted_platform_admin_still_requires_2fa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform')->assertForbidden();

        // Grant platform admin without 2FA
        $user->forceFill(['is_platform_admin' => true])->save();

        $response = $this->actingAs($user)->get('/platform');

        $response->assertRedirect(route('security.edit'));
    }

    /**
     * 13. Mutation Authorization: Business Owner cannot execute Platform mutations.
     */
    public function test_business_owner_cannot_execute_platform_mutations(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create(['status' => 'active']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $auditCountBefore = PlatformAuditLog::count();

        $response = $this->actingAs($owner)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
        $this->assertSame('active', $business->fresh()->status);
        $this->assertSame($auditCountBefore, PlatformAuditLog::count());
    }

    /**
     * 14. Rate Limiting: High-risk mutation throttles on the 31st attempt in a minute.
     */
    public function test_platform_admin_mutation_rate_limiter_throttles_at_limit(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);

        RateLimiter::clear('platform-admin-mutations');

        // Execute 30 mutations (within limit) alternating between active and inactive
        for ($i = 0; $i < 30; $i++) {
            $newStatus = ($i % 2 === 0) ? 'inactive' : 'active';
            $response = $this->actingAs($admin)->patch("/platform/businesses/{$business->id}/status", [
                'status' => $newStatus,
            ]);
            $response->assertRedirect();
        }

        $auditCountAtLimit = PlatformAuditLog::count();

        // 31st mutation must be throttled with 429
        $responseThrottled = $this->actingAs($admin)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'inactive',
        ]);

        $responseThrottled->assertStatus(429);

        // Assert 429 rejection creates NO new audit log
        $this->assertSame($auditCountAtLimit, PlatformAuditLog::count());
    }

    /**
     * 15. Rate Limiting: GET routes are unaffected by mutation rate limiter.
     */
    public function test_get_routes_are_not_affected_by_mutation_rate_limiter(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // Exhaust the mutation rate limiter
        for ($i = 0; $i < 31; $i++) {
            RateLimiter::hit('platform-admin-mutations:user:'.$admin->id, 60);
        }

        // GET /platform/settings must still be 200 OK
        $response = $this->actingAs($admin)->get('/platform/settings');

        $response->assertOk();
    }

    /**
     * 16. IDOR Protection: Nested Price mutation under unrelated Plan returns 404.
     */
    public function test_nested_price_mutation_under_unrelated_plan_returns_404(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $planA = SubscriptionPlan::create([
            'name' => 'Cloud POS',
            'code' => Subscription::PLAN_CLOUD,
            'is_active' => true,
        ]);
        $planB = SubscriptionPlan::create([
            'name' => 'Other Plan',
            'code' => 'other_plan',
            'is_active' => true,
        ]);

        $priceA = SubscriptionPlanPrice::create([
            'subscription_plan_id' => $planA->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 150000,
            'is_active' => true,
        ]);

        $auditCountBefore = PlatformAuditLog::count();

        // Attempt mutating Price A via Plan B URL
        $response = $this->actingAs($admin)->patch("/platform/subscription-plans/{$planB->id}/prices/{$priceA->id}", [
            'price' => 200000,
            'is_active' => true,
        ]);

        $response->assertNotFound();
        $this->assertSame(150000, (int) $priceA->fresh()->price_minor);
        $this->assertSame($auditCountBefore, PlatformAuditLog::count());
    }

    /**
     * 17. IDOR Protection: Non-canonical plan access returns 404.
     */
    public function test_non_canonical_plan_access_returns_404(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $nonCloudPlan = SubscriptionPlan::create([
            'name' => 'Free Plan',
            'code' => Subscription::PLAN_FREE,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get("/platform/subscription-plans/{$nonCloudPlan->id}");

        $response->assertNotFound();
    }

    /**
     * 18. IDOR Protection: Unknown platform setting slug returns 404 with no DB write.
     */
    public function test_unknown_platform_setting_slug_returns_404(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $auditCountBefore = PlatformAuditLog::count();

        $response = $this->actingAs($admin)->patch('/platform/settings/unknown_secret_key', [
            'value' => 'evil_value',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('platform_settings', ['key' => 'unknown_secret_key']);
        $this->assertSame($auditCountBefore, PlatformAuditLog::count());
    }

    /**
     * 19. IDOR Protection: Non-platform user cannot view arbitrary device ID.
     */
    public function test_non_platform_user_cannot_access_arbitrary_device(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Cashier POS',
            'identifier' => 'dev-idor-test-01',
            'status' => Device::STATUS_ACTIVE,
            'registered_at' => now(),
        ]);

        $this->actingAs($user)->get("/platform/devices/{$device->id}")->assertForbidden();
    }

    /**
     * 20. Sensitive Data Exposure: Critical secrets are absent from Platform views.
     */
    public function test_sensitive_credentials_and_tokens_are_not_exposed_in_responses(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // Seed markers
        config([
            'services.midtrans.server_key' => 'TOPSECRET_SERVER_KEY_123',
            'services.midtrans.client_key' => 'TOPSECRET_CLIENT_KEY_123',
        ]);

        $business = Business::factory()->create();
        $payment = SubscriptionPayment::create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'provider' => SubscriptionPayment::PROVIDER_MIDTRANS,
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => SubscriptionPayment::STATUS_PAID,
            'provider_order_id' => 'ORDER-SECRET-TEST-1',
            'idempotency_key' => 'IDEMP-SECRET-TEST-1',
            'snap_token' => 'SNAPTOKEN_UNIQUE_9Z8Y7X',
            'provider_payload' => [
                'transaction_status' => 'settlement',
                'signature_key' => 'TOPSECRET_SIGNATURE_KEY_123',
                'server_key' => 'TOPSECRET_SERVER_KEY_123',
            ],
            'activated_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // 1. Settings page does not leak server key
        $responseSettings = $this->actingAs($admin)->get('/platform/settings');
        $responseSettings->assertOk();
        $responseSettings->assertDontSee('TOPSECRET_SERVER_KEY_123');
        $responseSettings->assertDontSee('TOPSECRET_CLIENT_KEY_123');

        // 2. Payment detail does not leak raw snap token, partial suffix, or signature
        $responsePayment = $this->actingAs($admin)->get("/platform/payments/{$payment->id}");
        $responsePayment->assertOk();
        $responsePayment->assertSee('Snap Token');
        $responsePayment->assertSee('Tersedia');
        $responsePayment->assertDontSee('SNAPTOKEN_UNIQUE_9Z8Y7X');
        $responsePayment->assertDontSee('9Z8Y7X');
        $responsePayment->assertDontSee('TOPSECRET_SIGNATURE_KEY_123');
        $responsePayment->assertDontSee('TOPSECRET_SERVER_KEY_123');

        // 3. User detail does not leak password hash or 2FA secret
        $responseUser = $this->actingAs($admin)->get("/platform/users/{$admin->id}");
        $responseUser->assertOk();
        $responseUser->assertDontSee($admin->password);
        $responseUser->assertDontSee((string) $admin->two_factor_secret);
    }

    /**
     * 21. Stored XSS Protection: Dynamic user content is HTML-escaped.
     */
    public function test_stored_xss_payload_is_properly_escaped(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $maliciousName = '<script>alert("xss")</script>';

        $business = Business::factory()->create([
            'name' => $maliciousName,
        ]);

        $responseIndex = $this->actingAs($admin)->get('/platform/businesses');
        $responseIndex->assertOk();
        $responseIndex->assertDontSee('<script>alert("xss")</script>', false);
        $responseIndex->assertSee(e($maliciousName), false);

        $responseShow = $this->actingAs($admin)->get("/platform/businesses/{$business->id}");
        $responseShow->assertOk();
        $responseShow->assertDontSee('<script>alert("xss")</script>', false);
        $responseShow->assertSee(e($maliciousName), false);
    }

    /**
     * 22. Security Headers: Responses contain nosniff, DENY, strict-origin, and no-store.
     */
    public function test_platform_responses_contain_security_and_cache_headers(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->get('/platform');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cache-Control', 'no-store, private');

        $responseSettings = $this->actingAs($admin)->get('/platform/settings');
        $responseSettings->assertOk();
        $responseSettings->assertHeader('X-Content-Type-Options', 'nosniff');
        $responseSettings->assertHeader('X-Frame-Options', 'DENY');
        $responseSettings->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $responseSettings->assertHeader('Cache-Control', 'no-store, private');
    }

    /**
     * 23. Audit Integrity: Actor spoofing through request inputs is blocked.
     */
    public function test_audit_log_actor_cannot_be_spoofed_via_request_payload(): void
    {
        $admin = User::factory()->platformAdmin()->create(['email' => 'legit.admin@example.com']);
        $victim = User::factory()->platformAdmin()->create(['email' => 'victim.admin@example.com']);
        $business = Business::factory()->create(['status' => 'active']);

        $response = $this->actingAs($admin)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'inactive',
            'actor_user_id' => $victim->id,
            'actor_email' => 'victim.admin@example.com',
            'actor_name' => 'Victim Admin',
        ]);

        $response->assertRedirect();

        $latestAudit = PlatformAuditLog::latest('id')->firstOrFail();
        $this->assertSame($admin->id, $latestAudit->actor_user_id);
        $this->assertSame('legit.admin@example.com', $latestAudit->actor_email);
        $this->assertSame($admin->name, $latestAudit->actor_name);
    }

    /**
     * 24. Audit Sanitization: Nested secret keys and variations are stripped.
     */
    public function test_audit_logger_sanitizes_nested_keys_and_case_variants(): void
    {
        $logger = app(PlatformAuditLogger::class);
        $admin = User::factory()->platformAdmin()->create();

        $dirtyData = [
            'safe_status' => 'active',
            'api_key' => 'raw-api-key',
            'API_KEY' => 'uppercase-key',
            'access-token' => 'access-token-val',
            'private_key' => 'private-key-val',
            'nested' => [
                'safe_count' => 5,
                'Authorization' => 'Bearer token123',
                'cookie' => 'session_cookie_secret',
                'password' => 'secret_pass',
            ],
        ];

        $log = $logger->record(
            actor: $admin,
            action: 'test.sanitize',
            targetType: 'test',
            targetId: 1,
            targetLabel: 'Test Sanitizer',
            businessId: null,
            before: $dirtyData,
            after: $dirtyData,
            metadata: $dirtyData,
        );

        $sanitized = $log->before_state;

        $this->assertArrayHasKey('safe_status', $sanitized);
        $this->assertArrayNotHasKey('api_key', $sanitized);
        $this->assertArrayNotHasKey('API_KEY', $sanitized);
        $this->assertArrayNotHasKey('access-token', $sanitized);
        $this->assertArrayNotHasKey('private_key', $sanitized);

        $this->assertArrayHasKey('nested', $sanitized);
        $this->assertArrayHasKey('safe_count', $sanitized['nested']);
        $this->assertArrayNotHasKey('Authorization', $sanitized['nested']);
        $this->assertArrayNotHasKey('cookie', $sanitized['nested']);
        $this->assertArrayNotHasKey('password', $sanitized['nested']);
    }

    /**
     * 25. Method Protection: Read-only routes reject mutation verbs with 405.
     */
    public function test_read_only_routes_reject_mutation_verbs_with_405(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post('/platform/audit-logs')->assertStatus(405);
        $this->actingAs($admin)->delete('/platform/audit-logs/1')->assertStatus(405);
        $this->actingAs($admin)->post('/platform/alerts')->assertStatus(405);
        $this->actingAs($admin)->delete('/platform/revenue')->assertStatus(405);
        $this->actingAs($admin)->post('/platform/backups')->assertStatus(405);
        $this->actingAs($admin)->post('/platform/sync')->assertStatus(405);
    }

    /**
     * 26. Business Context Decoupling: Malicious business session does not alter platform authorization.
     */
    public function test_business_context_session_does_not_affect_platform_authorization(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $victimBusiness = Business::factory()->create();

        $response = $this->actingAs($admin)
            ->withSession(['dashboard.current_business_id' => $victimBusiness->id])
            ->get('/platform');

        $response->assertOk();
    }

    /**
     * 27. CSRF Audit: Platform mutation routes belong to the web middleware group.
     */
    public function test_platform_routes_belong_to_web_middleware_group(): void
    {
        $routes = Route::getRoutes()->getRoutesByName();

        $this->assertArrayHasKey('platform.businesses.status.update', $routes);
        $this->assertArrayHasKey('platform.settings.update', $routes);

        $statusRouteMiddleware = $routes['platform.businesses.status.update']->gatherMiddleware();
        $this->assertContains('web', $statusRouteMiddleware);
        $this->assertContains('platform.admin', $statusRouteMiddleware);
        $this->assertContains('platform.admin.2fa', $statusRouteMiddleware);
        $this->assertContains('platform.security.headers', $statusRouteMiddleware);
        $this->assertContains('throttle:platform-admin-mutations', $statusRouteMiddleware);
    }

    /**
     * 28. Mass Assignment Protection: Settings mutation ignores unauthorized keys.
     */
    public function test_platform_settings_mutation_ignores_unauthorized_keys(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->patch('/platform/settings/device-limit', [
            'value' => 10,
            'key' => 'MIDTRANS_SERVER_KEY',
            'is_platform_admin' => true,
            'updated_by' => 9999,
        ]);

        $response->assertRedirect();
        $setting = PlatformSetting::where('key', PlatformSettingDefinition::KEY_DEVICE_LIMIT)->firstOrFail();
        $this->assertSame(PlatformSettingDefinition::KEY_DEVICE_LIMIT, $setting->key);
        $this->assertSame(10, $setting->value);
        $this->assertSame($admin->id, $setting->updated_by);
    }

    /**
     * 29. Login Throttling: Repeated invalid login attempts trigger throttle.
     */
    public function test_login_attempts_are_throttled_by_fortify(): void
    {
        $user = User::factory()->create();

        $throttleKey = Str::transliterate(Str::lower($user->email).'|127.0.0.1');
        RateLimiter::clear($throttleKey);

        // Fortify configured limit is 5 attempts per minute
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
            $response->assertSessionHasErrors('email');
        }

        // 6th attempt should be throttled (either 429 status or redirect with throttle error message)
        $throttledResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertTrue(
            $throttledResponse->status() === 429 || $throttledResponse->isRedirect(),
            'Expected throttled response (429 or redirect).'
        );

        if ($throttledResponse->isRedirect()) {
            $throttledResponse->assertSessionHasErrors('email');
            $errorMessage = session('errors')->getBag('default')->first('email');
            $this->assertStringContainsString('Too many login attempts', (string) $errorMessage);
        } else {
            $this->assertSame(429, $throttledResponse->status());
        }
    }

    /**
     * 30. Privilege Boundaries: Profile update cannot escalate regular user to Platform Admin.
     */
    public function test_user_profile_update_cannot_escalate_to_platform_admin(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isPlatformAdmin());

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('name', 'Hacker Name')
            ->set('email', 'hacker@example.com')
            ->call('updateProfileInformation');

        $this->assertFalse($user->fresh()->isPlatformAdmin());
        $this->assertFalse((bool) $user->fresh()->is_platform_admin);
    }

    /**
     * 31. Pricing Immutability: Price update cannot alter prohibited canonical attributes.
     */
    public function test_price_mutation_blocks_unauthorized_fields(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $plan = SubscriptionPlan::create([
            'name' => 'Cloud Plan',
            'code' => Subscription::PLAN_CLOUD,
            'is_active' => true,
        ]);

        $price = SubscriptionPlanPrice::create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'currency' => 'IDR',
            'price_minor' => 150000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch("/platform/subscription-plans/{$plan->id}/prices/{$price->id}", [
            'price_minor' => 199000,
            'billing_period' => 'yearly',
            'currency' => 'USD',
            'code' => 'hacked_code',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['code', 'billing_period', 'currency']);
        $this->assertSame(150000, (int) $price->fresh()->price_minor);
        $this->assertSame('monthly', $price->fresh()->billing_period);
        $this->assertSame('IDR', $price->fresh()->currency);
    }
}
