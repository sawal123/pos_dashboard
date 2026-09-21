<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBusinessContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_with_one_business_automatically_gets_that_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Kopi Senja']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $current = $context->current($user);

        $this->assertNotNull($current);
        $this->assertEquals($business->id, $current->id);
        $this->assertEquals('Kopi Senja', $current->name);
        $this->assertEquals($business->id, session(DashboardBusinessContext::SESSION_KEY));
    }

    public function test_user_with_multiple_businesses_gets_deterministic_first_business_without_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessB = Business::factory()->create(['id' => 20, 'name' => 'Bisnis Beta']);
        $businessA = Business::factory()->create(['id' => 10, 'name' => 'Bisnis Alpha']);

        $user->businesses()->attach($businessB->id, ['role' => 'owner']);
        $user->businesses()->attach($businessA->id, ['role' => 'member']);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $current = $context->current($user);

        // Deterministic lowest ID first: Bisnis Alpha (10)
        $this->assertNotNull($current);
        $this->assertEquals(10, $current->id);
        $this->assertEquals('Bisnis Alpha', $current->name);
        $this->assertEquals(10, session(DashboardBusinessContext::SESSION_KEY));
    }

    public function test_valid_session_business_is_maintained(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['id' => 10, 'name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['id' => 20, 'name' => 'Bisnis Beta']);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($user);
        session([DashboardBusinessContext::SESSION_KEY => 20]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $current = $context->current($user);

        $this->assertNotNull($current);
        $this->assertEquals(20, $current->id);
        $this->assertEquals('Bisnis Beta', $current->name);
    }

    public function test_switching_to_another_accessible_business_via_post_succeeds(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['id' => 10, 'name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['id' => 20, 'name' => 'Bisnis Beta']);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($user);
        session([DashboardBusinessContext::SESSION_KEY => 10]);

        $response = $this->from(route('dashboard'))
            ->post(route('dashboard.business-context.update'), [
                'business_id' => 20,
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(20, session(DashboardBusinessContext::SESSION_KEY));

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $this->assertEquals(20, $context->current($user)->id);
    }

    public function test_switch_persists_session_key(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['id' => 5, 'name' => 'Kedai Kopi']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->actingAs($user);

        $this->post(route('dashboard.business-context.update'), [
            'business_id' => 5,
        ]);

        $this->assertTrue(session()->has(DashboardBusinessContext::SESSION_KEY));
        $this->assertEquals(5, session(DashboardBusinessContext::SESSION_KEY));
    }

    public function test_unauthorized_switch_to_another_users_business_is_forbidden(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['name' => 'Bisnis User A']);
        $userA->businesses()->attach($businessA->id, ['role' => 'owner']);

        $userB = User::factory()->create(['email_verified_at' => now()]);
        $businessB = Business::factory()->create(['name' => 'Bisnis User B']);
        $userB->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($userA);

        $response = $this->post(route('dashboard.business-context.update'), [
            'business_id' => $businessB->id,
        ]);

        $response->assertForbidden();
        $this->assertNotEquals($businessB->id, session(DashboardBusinessContext::SESSION_KEY));
    }

    public function test_forged_or_stale_session_belonging_to_another_user_is_not_used(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['id' => 10, 'name' => 'Bisnis User A']);
        $userA->businesses()->attach($businessA->id, ['role' => 'owner']);

        $userB = User::factory()->create(['email_verified_at' => now()]);
        $businessB = Business::factory()->create(['id' => 999, 'name' => 'Bisnis User B']);
        $userB->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($userA);
        // Simulate forged/stale session containing User B's business ID
        session([DashboardBusinessContext::SESSION_KEY => 999]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $current = $context->current($userA);

        // Must fallback to User A's accessible business
        $this->assertNotNull($current);
        $this->assertEquals($businessA->id, $current->id);
        $this->assertEquals('Bisnis User A', $current->name);
        $this->assertEquals($businessA->id, session(DashboardBusinessContext::SESSION_KEY));
    }

    public function test_revoked_membership_recovers_to_accessible_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['id' => 10, 'name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['id' => 20, 'name' => 'Bisnis Beta']);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->actingAs($user);
        session([DashboardBusinessContext::SESSION_KEY => 10]);

        // Revoke membership from business A
        $user->businesses()->detach($businessA->id);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $current = $context->current($user);

        // Must fallback to remaining business B
        $this->assertNotNull($current);
        $this->assertEquals(20, $current->id);
        $this->assertEquals(20, session(DashboardBusinessContext::SESSION_KEY));
    }

    public function test_user_without_business_does_not_crash_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Belum Ada Bisnis');
        $response->assertSee('Bisnis yang dapat Anda akses akan muncul di sini.');

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $this->assertNull($context->current($user));
    }

    public function test_business_switcher_renders_current_business_name(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Cafe Nusantara']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Cafe Nusantara');
        $response->assertSee('Bisnis Aktif');
    }

    public function test_switcher_renders_all_accessible_businesses_and_excludes_others(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now()]);
        $business1 = Business::factory()->create(['name' => 'Outlet Satu']);
        $business2 = Business::factory()->create(['name' => 'Outlet Dua']);
        $userA->businesses()->attach($business1->id, ['role' => 'owner']);
        $userA->businesses()->attach($business2->id, ['role' => 'owner']);

        $userB = User::factory()->create(['email_verified_at' => now()]);
        $businessOther = Business::factory()->create(['name' => 'Outlet Rahasia']);
        $userB->businesses()->attach($businessOther->id, ['role' => 'owner']);

        $this->actingAs($userA);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Outlet Satu');
        $response->assertSee('Outlet Dua');
        $response->assertDontSee('Outlet Rahasia');
    }

    public function test_active_cloud_subscription_maps_to_subscriber(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Cloud Business']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $state = $context->subscriptionState($context->current($user));

        $this->assertEquals('subscriber', $state);
        // Sidebar renders subscriber badge without animate-pulse and with neutral cloud access wording
        $response->assertSee('Paket Cloud');
        $response->assertSee('Akses sinkronisasi Cloud aktif');
        $response->assertDontSee('Sinkronisasi otomatis aktif');

        $sidebarView = $this->blade('<x-ui.sidebar subscription="subscriber" />');
        $sidebarView->assertDontSee('animate-pulse');
        $sidebarView->assertSee('Akses sinkronisasi Cloud aktif');
        $sidebarView->assertDontSee('Sinkronisasi otomatis aktif');
    }

    public function test_free_subscription_maps_to_free(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Free Business']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        Subscription::factory()->free()->create([
            'business_id' => $business->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $state = $context->subscriptionState($context->current($user));

        $this->assertEquals('free', $state);
        $response->assertSee('Mode lokal tanpa sinkronisasi Cloud');
        $response->assertDontSee('Fitur sinkronisasi terbatas');
        $response->assertSee('Tingkatkan Paket');
    }

    public function test_expired_cloud_subscription_does_not_map_to_subscriber(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Expired Cloud Business']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        Subscription::factory()->cloud()->expired()->create([
            'business_id' => $business->id,
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $state = $context->subscriptionState($context->current($user));

        $this->assertNotEquals('subscriber', $state);
        $this->assertEquals('unknown', $state);
        $response->assertDontSee('Akses sinkronisasi Cloud aktif');
        $response->assertDontSee('Sinkronisasi otomatis aktif');
    }

    public function test_no_subscription_maps_to_unknown(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'No Sub Business']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        /** @var DashboardBusinessContext $context */
        $context = app(DashboardBusinessContext::class);
        $state = $context->subscriptionState($context->current($user));

        $this->assertEquals('unknown', $state);
        $response->assertSee('Status belum terhubung');
    }

    public function test_navbar_cloud_status_does_not_fake_online_even_with_active_cloud_subscription(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Cloud POS']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // Navbar cloudStatus remains neutral/unknown, not fake 'Online' or 'Terhubung'
        $response->assertDontSee('Status Cloud: Terhubung ke Cloud (Online)');
        $response->assertDontSee('Status: Terhubung (Online)');
    }

    public function test_existing_dashboard_routes_remain_accessible_with_context(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create(['name' => 'Multi Route Cafe']);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->actingAs($user);

        $routes = [
            'dashboard',
            'transactions.index',
            'products.index',
            'stock.index',
            'cash.index',
            'reports.index',
            'devices.index',
            'sync.index',
        ];

        foreach ($routes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertOk();
            $response->assertSee('Multi Route Cafe');
        }
    }
}
