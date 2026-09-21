<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncMonitoringPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('sync.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unverified_users_cannot_access_sync_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_authenticated_users_can_access_sync_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
    }

    public function test_sync_route_exists_and_renders_header(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Sinkronisasi');
        $response->assertSee('Pantau sequence server dan push request yang telah diproses.');
        $response->assertSee('Dashboard');
    }

    public function test_sidebar_sinkronisasi_uses_sync_index_route(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee(route('sync.index'));
        $response->assertSee('aria-current="page"', false);
    }

    public function test_server_sync_sequence_card_is_available(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Server Sync Sequence');
        $response->assertSee('1.284'); // formatted 1284
    }

    public function test_push_request_tercatat_card_is_available(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Push Request Tercatat');
    }

    public function test_request_fixture_renders_request_id_in_monospace(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('6d736a0a-552a-4fa5-889c-01b61e430001');
        $response->assertSee('font-mono', false);
    }

    public function test_processed_at_display_is_available(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('21 Sep 2026 · 10:42');
    }

    public function test_machine_readable_processed_at_raw_is_available(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('data-processed-at-raw="2026-09-21 10:42:00"', false);
    }

    public function test_device_relation_presentation_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Kasir Utama');
        $response->assertSee('device-android-kasir-utama-001');
        $response->assertSee('Outlet Utama');
    }

    public function test_production_environment_does_not_render_dummy_requests(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('6d736a0a-552a-4fa5-889c-01b61e430001');
        $response->assertDontSee('device-android-kasir-utama-001');
    }

    public function test_production_renders_clean_empty_state(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        app()->detectEnvironment(fn () => 'production');

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Riwayat Sinkronisasi');
        $response->assertSee('Push request yang telah diproses dan tercatat di Cloud akan muncul di sini.');
    }

    public function test_info_callout_limitation_is_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Informasi Monitoring Sinkronisasi');
        $response->assertSee('Riwayat pull, pending outbox, conflict, retry, dan device cursor belum tersedia');
    }

    public function test_page_does_not_render_fake_pending_count(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Pending Sync');
        $response->assertDontSee('Pending Sequence');
        $response->assertDontSee('Pending Outbox');
    }

    public function test_page_does_not_render_fake_error_or_conflict_count(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Conflict Count');
        $response->assertDontSee('Failed Sync');
        $response->assertDontSee('Error Count');
    }

    public function test_page_does_not_render_success_rate(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Success Rate');
        $response->assertDontSee('Sync Health %');
        $response->assertDontSee('All Synced');
    }

    public function test_page_does_not_render_device_lag(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Device Lag');
        $response->assertDontSee('Sync Lag');
        $response->assertDontSee('Queue Length');
    }

    public function test_page_does_not_render_online_or_offline_status(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Online Devices');
        $response->assertDontSee('Offline Devices');
        $response->assertDontSee('Realtime Connected');
    }

    public function test_page_does_not_render_action_buttons_like_sinkronkan_sekarang(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Sinkronkan Sekarang');
        $response->assertDontSee('Force Sync');
        $response->assertDontSee('Retry');
        $response->assertDontSee('Pull Now');
        $response->assertDontSee('Push Now');
    }

    public function test_filter_does_not_use_hardcoded_reference_date(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        // Pastikan tidak ada hardcoded reference date string di client script
        $response->assertDontSee("new Date('2026/09/21", false);
        $response->assertDontSee("new Date('2026-09-21", false);
    }

    public function test_fixture_requests_use_valid_uuids(): void
    {
        $fixtureLoader = require resource_path('views/sync/fixtures.php');
        $fixture = $fixtureLoader();
        $this->assertNotEmpty($fixture['requests']);

        foreach ($fixture['requests'] as $req) {
            $this->assertTrue(
                Str::isUuid($req['request_id']),
                "Request ID {$req['request_id']} must be a valid UUID"
            );
        }
    }
}
