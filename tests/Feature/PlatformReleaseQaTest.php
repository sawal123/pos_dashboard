<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformReleaseQaTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => 'encrypted-secret',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_all_fourteen_primary_platform_pages_render_cleanly_on_empty_database(): void
    {
        $primaryRoutes = [
            'platform.dashboard',
            'platform.alerts.index',
            'platform.businesses.index',
            'platform.users.index',
            'platform.subscriptions.index',
            'platform.subscription-plans.index',
            'platform.payments.index',
            'platform.devices.index',
            'platform.sync.index',
            'platform.backups.index',
            'platform.analytics.index',
            'platform.revenue.index',
            'platform.audit-logs.index',
            'platform.settings.index',
        ];

        foreach ($primaryRoutes as $routeName) {
            $response = $this->actingAs($this->platformAdmin)->get(route($routeName));
            $response->assertOk();
            $response->assertSee('Platform Admin');
        }
    }

    public function test_sidebar_navigation_contains_all_primary_module_links_without_broken_routes(): void
    {
        $response = $this->actingAs($this->platformAdmin)->get(route('platform.dashboard'));

        $response->assertOk();

        $expectedUrls = [
            route('platform.dashboard'),
            route('platform.alerts.index'),
            route('platform.businesses.index'),
            route('platform.users.index'),
            route('platform.subscriptions.index'),
            route('platform.subscription-plans.index'),
            route('platform.payments.index'),
            route('platform.devices.index'),
            route('platform.sync.index'),
            route('platform.backups.index'),
            route('platform.analytics.index'),
            route('platform.revenue.index'),
            route('platform.audit-logs.index'),
            route('platform.settings.index'),
        ];

        foreach ($expectedUrls as $url) {
            $response->assertSee($url, false);
        }
    }

    public function test_platform_search_filters_handle_special_and_malicious_characters_safely(): void
    {
        $edgeStrings = [
            "%' OR 1=1 --",
            "%' UNION SELECT --",
            "'\"><script>alert(1)</script>",
            '100%_guaranteed',
            "O'Reilly",
            str_repeat('a', 255),
        ];

        foreach ($edgeStrings as $edgeString) {
            $businessSearch = $this->actingAs($this->platformAdmin)
                ->get(route('platform.businesses.index', ['search' => $edgeString]));
            $businessSearch->assertOk();

            $userSearch = $this->actingAs($this->platformAdmin)
                ->get(route('platform.users.index', ['search' => $edgeString]));
            $userSearch->assertOk();

            $paymentSearch = $this->actingAs($this->platformAdmin)
                ->get(route('platform.payments.index', ['search' => $edgeString]));
            $paymentSearch->assertOk();

            $deviceSearch = $this->actingAs($this->platformAdmin)
                ->get(route('platform.devices.index', ['search' => $edgeString]));
            $deviceSearch->assertOk();

            $auditSearch = $this->actingAs($this->platformAdmin)
                ->get(route('platform.audit-logs.index', ['search' => $edgeString]));
            $auditSearch->assertOk();
        }
    }

    public function test_platform_route_model_binding_returns_404_for_non_existent_or_invalid_entities(): void
    {
        $invalidId = 999999999;

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.businesses.show', $invalidId))
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.users.show', $invalidId))
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.subscriptions.show', $invalidId))
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.payments.show', $invalidId))
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.devices.show', $invalidId))
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.sync.show', $invalidId))
            ->assertNotFound();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.audit-logs.show', $invalidId))
            ->assertNotFound();
    }

    public function test_monitoring_and_get_routes_reject_unsupported_http_methods(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.dashboard'))
            ->assertStatus(405);

        $this->actingAs($this->platformAdmin)
            ->delete(route('platform.analytics.index'))
            ->assertStatus(405);

        $this->actingAs($this->platformAdmin)
            ->patch(route('platform.revenue.index'))
            ->assertStatus(405);

        $this->actingAs($this->platformAdmin)
            ->put(route('platform.audit-logs.index'))
            ->assertStatus(405);
    }
}
