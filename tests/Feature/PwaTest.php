<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_web_app_manifest_is_served_with_correct_json_headers_and_schema(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');

        $data = $response->json();
        $this->assertSame('NexaPOS - Sistem Kasir & Manajemen Bisnis', $data['name']);
        $this->assertSame('NexaPOS', $data['short_name']);
        $this->assertSame('/dashboard', $data['start_url']);
        $this->assertSame('/', $data['scope']);
        $this->assertSame('standalone', $data['display']);
        $this->assertSame('#4f46e5', $data['theme_color']);
        $this->assertIsArray($data['icons']);
        $this->assertNotEmpty($data['icons']);
        $this->assertIsArray($data['shortcuts']);
        $this->assertNotEmpty($data['shortcuts']);
    }

    public function test_02_manifest_json_alias_also_returns_valid_manifest(): void
    {
        $response = $this->get('/manifest.json');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');
        $this->assertSame('NexaPOS', $response->json('short_name'));
    }

    public function test_03_offline_page_is_publicly_accessible(): void
    {
        $response = $this->get('/offline');

        $response->assertOk();
        $response->assertSee('Mode Offline');
        $response->assertSee('Koneksi Terputus');
        $response->assertSee('Zona Kasir (POS)');
        $response->assertSee('Dashboard');
    }

    public function test_04_service_worker_asset_exists_and_contains_pwa_lifecycle_and_caching(): void
    {
        $swPath = public_path('sw.js');
        $this->assertFileExists($swPath);

        $content = file_get_contents($swPath);
        $this->assertStringContainsString('nexapos-pwa-v1.0.0', $content);
        $this->assertStringContainsString('/offline', $content);
        $this->assertStringContainsString('/manifest.webmanifest', $content);
        $this->assertStringContainsString("request.mode === 'navigate'", $content);
    }

    public function test_05_service_worker_route_serves_script_with_correct_headers(): void
    {
        $response = $this->get('/sw.js');

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $response->assertHeader('Service-Worker-Allowed', '/');
    }

    public function test_06_pwa_icons_and_assets_exist_in_public(): void
    {
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $this->assertFileExists(public_path('icons/icon-maskable-192.png'));
        $this->assertFileExists(public_path('icons/icon-maskable-512.png'));
        $this->assertFileExists(public_path('icons/icon.svg'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertFileExists(public_path('manifest.webmanifest'));
    }

    public function test_07_dashboard_includes_pwa_meta_tags_and_components(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('rel="manifest" href="/manifest.webmanifest"', false);
        $response->assertSee('name="theme-color"', false);
        $response->assertSee('name="mobile-web-app-capable" content="yes"', false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
        $response->assertSee('pwaContainer', false);
        $response->assertSee('sidebarPwaInstallRow', false);
    }

    public function test_08_pos_cashier_includes_pwa_banner(): void
    {
        [$business, $user] = $this->actingAsRole('owner');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.index'));

        $response->assertOk();
        $response->assertSee('rel="manifest" href="/manifest.webmanifest"', false);
        $response->assertSee('pwaContainer', false);
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        return [$business, $user];
    }
}
