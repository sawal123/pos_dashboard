<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DevicesPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1-3. Auth & Business Context
    // ============================================================

    public function test_1_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('devices.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_2_unverified_users_cannot_access_devices_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_3_verified_user_without_business_gets_200_and_empty_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Perangkat');
        $response->assertSee('Belum Ada Perangkat');
        $response->assertSee('id="deviceSummaryTotal"', false);
    }

    // ============================================================
    // 4-6. Tenant Scoping & Switch Business
    // ============================================================

    public function test_4_current_business_device_is_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $device = $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Terminal POS Kasir Depan',
            'identifier' => 'pos-terminal-001',
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Terminal POS Kasir Depan');
        $response->assertSee('pos-terminal-001');
    }

    public function test_5_foreign_business_device_is_not_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreignBusiness = Business::factory()->create();
        $this->createDevice([
            'business_id' => $foreignBusiness->id,
            'name' => 'Foreign Terminal POS',
            'identifier' => 'foreign-pos-999',
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('Foreign Terminal POS');
        $response->assertDontSee('foreign-pos-999');
    }

    public function test_6_business_switch_changes_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['name' => 'Bisnis Beta']);
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $this->createDevice([
            'business_id' => $businessA->id,
            'name' => 'Terminal Alpha Only',
        ]);
        $this->createDevice([
            'business_id' => $businessB->id,
            'name' => 'Terminal Beta Only',
        ]);

        $responseA = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('devices.index'));

        $responseA->assertOk();
        $responseA->assertSee('Terminal Alpha Only');
        $responseA->assertDontSee('Terminal Beta Only');

        $responseB = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('devices.index'));

        $responseB->assertOk();
        $responseB->assertSee('Terminal Beta Only');
        $responseB->assertDontSee('Terminal Alpha Only');
    }

    // ============================================================
    // 7-11. Summary Metrics & Status Counts
    // ============================================================

    public function test_7_summary_total_devices_is_tenant_scoped(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreignBusiness = Business::factory()->create();

        $this->createDevice(['business_id' => $business->id, 'name' => 'Dev A1']);
        $this->createDevice(['business_id' => $business->id, 'name' => 'Dev A2']);
        $this->createDevice(['business_id' => $foreignBusiness->id, 'name' => 'Dev Foreign']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['total_devices'] === 2;
        });
    }

    public function test_8_active_count_only_counts_active_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createDevice(['business_id' => $business->id, 'status' => 'active']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'active']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'inactive']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['active_devices'] === 2;
        });
    }

    public function test_9_inactive_count_only_counts_inactive_status(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createDevice(['business_id' => $business->id, 'status' => 'active']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'inactive']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'inactive']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['inactive_devices'] === 2;
        });
    }

    public function test_10_unknown_status_is_not_counted_as_inactive(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createDevice(['business_id' => $business->id, 'status' => 'pending_review']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'decommissioned']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'inactive']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['total_devices'] === 3
                && $summary['inactive_devices'] === 1
                && $summary['active_devices'] === 0;
        });
    }

    public function test_11_last_seen_at_null_is_counted_as_never_seen(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createDevice(['business_id' => $business->id, 'last_seen_at' => null]);
        $this->createDevice(['business_id' => $business->id, 'last_seen_at' => now()]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['never_seen_devices'] === 1;
        });
    }

    // ============================================================
    // 12-16. Semantics, Fallbacks & Presentation
    // ============================================================

    public function test_12_active_is_not_displayed_as_online(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice(['business_id' => $business->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Aktif');
        $response->assertDontSee('>Online<', false);
        $response->assertDontSee('Perangkat Online');
        $response->assertDontSee('Perangkat Offline');
    }

    public function test_13_recent_last_seen_at_does_not_infer_online(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Device Just Seen',
            'last_seen_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('Online');
    }

    public function test_14_nullable_platform_renders_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Terminal Without Platform',
            'platform' => null,
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Tidak Diketahui');
    }

    public function test_15_nullable_notes_renders_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Terminal Without Notes',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Terminal Without Notes');
    }

    public function test_16_long_identifier_renders_completely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $longId = 'device-android-pos-terminal-jakarta-selatan-utama-extra-long-identifier-999888';
        $this->createDevice([
            'business_id' => $business->id,
            'identifier' => $longId,
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee($longId);
        $response->assertSee('break-all', false);
    }

    public function test_17_outlet_relation_renders_safely_or_neutral_fallback(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Tebet']);

        $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device Tebet',
        ]);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Cabang Tebet');
    }

    // ============================================================
    // 18-24. Search & Filters
    // ============================================================

    public function test_18_search_by_name(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice(['business_id' => $business->id, 'name' => 'Kasir Alpha']);
        $this->createDevice(['business_id' => $business->id, 'name' => 'Kasir Bravo']);

        $response = $this->actingAs($user)->get(route('devices.index', ['q' => 'Alpha']));
        $response->assertOk();
        $response->assertSee('Kasir Alpha');
        $response->assertDontSee('Kasir Bravo');
    }

    public function test_19_search_by_identifier(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice(['business_id' => $business->id, 'identifier' => 'ident-apple-111', 'name' => 'Terminal 1']);
        $this->createDevice(['business_id' => $business->id, 'identifier' => 'ident-zebra-222', 'name' => 'Terminal 2']);

        $response = $this->actingAs($user)->get(route('devices.index', ['q' => 'zebra']));
        $response->assertOk();
        $response->assertSee('Terminal 2');
        $response->assertDontSee('Terminal 1');
    }

    public function test_20_search_does_not_leak_foreign_tenant(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createDevice(['business_id' => $business->id, 'name' => 'Kasir Target']);
        $this->createDevice(['business_id' => $foreign->id, 'name' => 'Kasir Target Foreign']);

        $response = $this->actingAs($user)->get(route('devices.index', ['q' => 'Target']));
        $response->assertOk();
        $response->assertSee('Kasir Target');
        $response->assertDontSee('Kasir Target Foreign');
    }

    public function test_21_outlet_id_filter_exact(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet A']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet B']);

        $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'name' => 'POS Di Outlet A']);
        $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletB->id, 'name' => 'POS Di Outlet B']);

        $response = $this->actingAs($user)->get(route('devices.index', ['outlet_id' => $outletA->id]));
        $response->assertOk();
        $response->assertSee('POS Di Outlet A');
        $response->assertDontSee('POS Di Outlet B');
    }

    public function test_22_foreign_outlet_id_does_not_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();
        $foreignOutlet = Outlet::factory()->create(['business_id' => $foreign->id]);

        $this->createDevice(['business_id' => $foreign->id, 'outlet_id' => $foreignOutlet->id, 'name' => 'Foreign POS']);

        $response = $this->actingAs($user)->get(route('devices.index', ['outlet_id' => $foreignOutlet->id]));
        $response->assertOk();
        $response->assertDontSee('Foreign POS');
    }

    public function test_23_status_filter_exact(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createDevice(['business_id' => $business->id, 'status' => 'active', 'name' => 'Active POS']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'inactive', 'name' => 'Inactive POS']);
        $this->createDevice(['business_id' => $business->id, 'status' => 'pending_review', 'name' => 'Pending POS']);

        $response = $this->actingAs($user)->get(route('devices.index', ['status' => 'active']));
        $response->assertOk();
        $response->assertSee('Active POS');
        $response->assertDontSee('Inactive POS');
        $response->assertDontSee('Pending POS');
    }

    public function test_24_platform_filter_raw_value(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $this->createDevice(['business_id' => $business->id, 'platform' => 'Android', 'name' => 'POS Android']);
        $this->createDevice(['business_id' => $business->id, 'platform' => 'iOS', 'name' => 'POS iOS']);

        $response = $this->actingAs($user)->get(route('devices.index', ['platform' => 'Android']));
        $response->assertOk();
        $response->assertSee('POS Android');
        $response->assertDontSee('POS iOS');
    }

    // ============================================================
    // 25-27. Pagination & Preserved Query String
    // ============================================================

    public function test_25_pagination_25_records_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'name' => "Device #{$i}",
                'registered_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertViewHas('devices', function ($devices) {
            return $devices->count() === 25 && $devices->total() === 30;
        });
    }

    public function test_26_query_string_preserved_in_pagination(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'name' => "Tablet {$i}",
                'status' => 'active',
                'registered_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('devices.index', ['status' => 'active', 'page' => 2]));
        $response->assertOk();
        $response->assertSee('status=active', false);
    }

    public function test_27_summary_identical_on_page_1_and_page_2(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'name' => "Tablet {$i}",
                'status' => $i <= 10 ? 'inactive' : 'active',
                'registered_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $res1 = $this->actingAs($user)->get(route('devices.index', ['page' => 1]));
        $res2 = $this->actingAs($user)->get(route('devices.index', ['page' => 2]));

        $res1->assertOk();
        $res2->assertOk();

        $this->assertSame(
            $res1->viewData('summary'),
            $res2->viewData('summary')
        );
    }

    // ============================================================
    // 28-34. Empty States, Fixture Absence & Placeholders
    // ============================================================

    public function test_28_initial_empty_state_when_business_has_no_devices(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Perangkat');
        $response->assertDontSee('Perangkat Tidak Ditemukan');
    }

    public function test_29_filtered_empty_state_with_reset_link(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice(['business_id' => $business->id, 'name' => 'Existing Device']);

        $response = $this->actingAs($user)->get(route('devices.index', ['q' => 'NonExistentDevice123']));
        $response->assertOk();
        $response->assertSee('Perangkat Tidak Ditemukan');
        $response->assertSee(route('devices.index'));
    }

    public function test_30_fixture_kasir_utama_does_not_appear_on_empty_db(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('Kasir Utama');
        $response->assertDontSee('device-android-kasir-utama-001');
    }

    public function test_31_production_and_testing_both_use_real_database(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice(['business_id' => $business->id, 'name' => 'Real Device DB']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Real Device DB');
    }

    public function test_32_page_does_not_require_fixtures(): void
    {
        $viewContent = file_get_contents(resource_path('views/devices/index.blade.php'));
        $this->assertStringNotContainsString('views/devices/fixtures.php', $viewContent);
    }

    public function test_33_drawer_does_not_expose_sync_internals(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createDevice(['business_id' => $business->id, 'name' => 'Device Info Test']);

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('sync_sequence');
        $response->assertDontSee('sync_version');
        $response->assertDontSee('sync_cursor');
    }

    public function test_34_device_registration_button_remains_read_only_placeholder(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Daftarkan Perangkat');
        $response->assertSee('Registrasi perangkat dari dashboard belum tersedia.');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(array $attributes = []): Device
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Device::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'name' => 'Device '.Str::random(6),
            'identifier' => 'device-'.Str::random(10),
            'platform' => 'Android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => now(),
            'notes' => null,
        ], $attributes));
    }
}
