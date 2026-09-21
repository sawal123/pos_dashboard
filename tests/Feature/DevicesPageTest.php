<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('devices.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_devices_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_devices_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
    }

    public function test_devices_route_exists_and_renders_header(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Perangkat');
        $response->assertSee('Pantau perangkat POS yang terdaftar pada bisnis dan outlet.');
        $response->assertSee('Dashboard');
    }

    public function test_sidebar_perangkat_uses_devices_index_route(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee(route('devices.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_device_summary_cards_are_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Total Perangkat');
        $response->assertSee('Perangkat Aktif');
        $response->assertSee('Perangkat Nonaktif');
        $response->assertSee('Belum Pernah Terlihat');
    }

    public function test_active_and_inactive_device_fixtures_are_available(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Kasir Utama');
        $response->assertSee('Aktif');
        $response->assertSee('Tablet Waiter Cadangan');
        $response->assertSee('Nonaktif');
    }

    public function test_last_seen_at_nullable_renders_safely(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Perangkat Baru Terdaftar');
        $response->assertSee('Belum Pernah Terlihat');
    }

    public function test_platform_nullable_renders_safely(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Tablet Waiter Cadangan');
        $response->assertSee('Tidak Diketahui');
    }

    public function test_long_device_identifiers_render_safely_with_monospace(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('device-android-kasir-utama-001');
        $response->assertSee('font-mono', false);
        $response->assertSee('break-all', false);
    }

    public function test_machine_readable_dates_are_rendered_in_datasets(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('data-registered-at-raw="2026-09-15 08:00:00"', false);
        $response->assertSee('data-last-seen-at-raw="2026-09-21 10:42:00"', false);
    }

    public function test_production_environment_does_not_render_dummy_devices(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('Kasir Utama');
        $response->assertDontSee('device-android-kasir-utama-001');
        $response->assertDontSee('Tablet Waiter Cadangan');
    }

    public function test_production_renders_clean_empty_state(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Perangkat');
        $response->assertSee('Perangkat yang terdaftar di Cloud akan muncul di sini.');
    }

    public function test_default_fixture_does_not_contain_laundry(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('Laundry', false);
        $response->assertDontSee('laundry', false);
    }

    public function test_devices_page_does_not_use_online_or_offline_status(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        // Pastikan tidak ada label Online atau Offline untuk status perangkat
        $response->assertDontSee('Sedang Online');
        $response->assertDontSee('Online Devices');
        $response->assertDontSee('Offline Devices');
        $response->assertDontSee('>Online<', false);
        $response->assertDontSee('>Offline<', false);
    }

    public function test_unknown_device_status_does_not_mislabeled_as_nonaktif(): void
    {
        $customDevice = [
            'id' => 99,
            'business_id' => 1,
            'outlet_id' => 1,
            'name' => 'POS Audit Terminal',
            'identifier' => 'device-audit-999',
            'platform' => 'Android',
            'status' => 'pending_review',
            'registered_at_raw' => '2026-09-21 00:00:00',
            'registered_at' => '21 Sep 2026 · 00:00',
            'last_seen_at_raw' => null,
            'last_seen_at' => null,
            'notes' => null,
            'outlet_name' => 'Outlet Utama',
        ];

        $view = $this->blade('<x-devices.table :devices="[$device]" />', ['device' => $customDevice]);
        $view->assertSee('Pending Review');
        $view->assertDontSee('Nonaktif');

        $mobileView = $this->blade('<x-devices.mobile-cards :devices="[$device]" />', ['device' => $customDevice]);
        $mobileView->assertSee('Pending Review');
        $mobileView->assertDontSee('Nonaktif');
    }

    public function test_filter_nonaktif_matches_status_not_equal_to_active(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();

        // Verifikasi client script menyelaraskan filter nonaktif dengan status !== 'active'
        $response->assertSee("elStatus !== 'active'", false);
    }
}
