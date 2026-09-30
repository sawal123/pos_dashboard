<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DASH-17 — dashboard device registration & management.
 *
 * Covers owner-only mutation, tenant isolation, identifier idempotency,
 * outlet mismatch, status lifecycle, last_seen_at honesty, concurrency safety
 * and the mobile API/sync contract.
 */
class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Authorization / RBAC
    // ============================================================

    public function test_01_owner_can_register_a_device(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.store'), [
            'name' => 'Kasir Depan',
            'identifier' => 'POS-01',
            'outlet_id' => $outlet->id,
            'platform' => 'android',
            'notes' => 'Tablet',
        ])->assertRedirect(route('devices.index'));

        $device = Device::where('business_id', $business->id)->where('identifier', 'POS-01')->firstOrFail();
        $this->assertSame('active', $device->status);
        $this->assertSame($outlet->id, $device->outlet_id);
        $this->assertNull($device->last_seen_at);
        $this->assertNotNull($device->registered_at);
        $this->assertSame('Tablet', $device->notes);
    }

    public function test_02_member_cannot_register_from_dashboard(): void
    {
        [$business] = $this->actingAsRole('member');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.store'), [
            'name' => 'Member Device',
            'identifier' => 'MEMBER-01',
            'outlet_id' => $outlet->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_03_cashier_is_denied(): void
    {
        [$business] = $this->actingAsRole('cashier');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.store'), [
            'name' => 'Cashier Device',
            'identifier' => 'CASHIER-01',
            'outlet_id' => $outlet->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_04_unknown_role_is_denied(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($user->id, ['role' => 'supervisor']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('devices.store'), [
                'name' => 'Unknown Device',
                'identifier' => 'UNKNOWN-01',
                'outlet_id' => $outlet->id,
            ])->assertForbidden();

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_05_guest_and_unverified_users_are_denied(): void
    {
        $this->post(route('devices.store'), [
            'name' => 'Guest Device',
            'identifier' => 'GUEST-01',
            'outlet_id' => 1,
        ])->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('devices.index'))
            ->assertRedirect(route('verification.notice'));
    }

    // ============================================================
    // Tenant isolation
    // ============================================================

    public function test_06_active_business_defines_the_tenant(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $businessA->id]);
        $businessB = Business::factory()->create();
        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->post(route('devices.store'), [
                'name' => 'Scoped Device',
                'identifier' => 'SCOPE-01',
                'outlet_id' => $outletA->id,
            ])->assertRedirect();

        $this->assertDatabaseHas('devices', ['identifier' => 'SCOPE-01', 'business_id' => $businessA->id]);
        $this->assertDatabaseMissing('devices', ['identifier' => 'SCOPE-01', 'business_id' => $businessB->id]);
    }

    public function test_07_forged_business_id_is_ignored(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $businessB = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);

        $this->post(route('devices.store'), [
            'name' => 'Forged Device',
            'identifier' => 'FORGED-01',
            'outlet_id' => $outletA->id,
            'business_id' => $businessB->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('devices', ['identifier' => 'FORGED-01', 'business_id' => $businessA->id]);
        $this->assertDatabaseMissing('devices', ['identifier' => 'FORGED-01', 'business_id' => $businessB->id]);
    }

    public function test_08_cross_tenant_outlet_is_rejected(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $foreignOutlet = Outlet::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->from(route('devices.index'))
            ->post(route('devices.store'), [
                'name' => 'Cross Outlet Device',
                'identifier' => 'CROSS-01',
                'outlet_id' => $foreignOutlet->id,
            ])
            ->assertSessionHasErrors('outlet_id');

        $this->assertDatabaseCount('devices', 0);
    }

    // ============================================================
    // Identifier idempotency & duplicates
    // ============================================================

    public function test_09_duplicate_identifier_same_outlet_creates_no_new_row(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'identifier' => 'DUP-01', 'name' => 'Asli']);

        $this->post(route('devices.store'), [
            'name' => 'Nama Berbeda',
            'identifier' => 'DUP-01',
            'outlet_id' => $outlet->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('devices', 1);
        // Re-submitting never overwrites the existing row's metadata.
        $this->assertSame('Asli', $device->fresh()->name);
    }

    public function test_10_same_identifier_is_allowed_in_another_business(): void
    {
        [$businessA] = $this->actingAsRole('owner');
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);

        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $this->createDevice(['business_id' => $businessB->id, 'outlet_id' => $outletB->id, 'identifier' => 'SHARED-01']);

        $this->post(route('devices.store'), [
            'name' => 'Shared A',
            'identifier' => 'SHARED-01',
            'outlet_id' => $outletA->id,
        ])->assertRedirect();

        $this->assertSame(1, Device::where('business_id', $businessA->id)->where('identifier', 'SHARED-01')->count());
    }

    public function test_11_outlet_mismatch_is_rejected_without_moving_the_device(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outletA = Outlet::factory()->create(['business_id' => $business->id]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'identifier' => 'MISMATCH-01']);

        $this->from(route('devices.index'))
            ->post(route('devices.store'), [
                'name' => 'Moved',
                'identifier' => 'MISMATCH-01',
                'outlet_id' => $outletB->id,
            ])
            ->assertSessionHasErrors('identifier');

        $this->assertDatabaseCount('devices', 1);
        $this->assertSame($outletA->id, $device->fresh()->outlet_id);
    }

    public function test_12_inactive_device_is_not_reactivated_by_reregistration(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'identifier' => 'INACTIVE-01',
            'status' => 'inactive',
            'last_seen_at' => null,
        ]);

        $this->post(route('devices.store'), [
            'name' => 'Re-submit',
            'identifier' => 'INACTIVE-01',
            'outlet_id' => $outlet->id,
        ])->assertRedirect();

        $this->assertSame('inactive', $device->fresh()->status);
        $this->assertNull($device->fresh()->last_seen_at);
        $this->assertDatabaseCount('devices', 1);
    }

    // ============================================================
    // Status management
    // ============================================================

    public function test_13_owner_can_deactivate_a_device(): void
    {
        [$business] = $this->actingAsRole('owner');
        $device = $this->createDevice(['business_id' => $business->id, 'status' => 'active']);

        $this->patch(route('devices.status.update', ['deviceId' => $device->id]), ['status' => 'inactive'])
            ->assertRedirect(route('devices.index'));

        $this->assertSame('inactive', $device->fresh()->status);
    }

    public function test_14_owner_can_activate_a_device(): void
    {
        [$business] = $this->actingAsRole('owner');
        $device = $this->createDevice(['business_id' => $business->id, 'status' => 'inactive']);

        $this->patch(route('devices.status.update', ['deviceId' => $device->id]), ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame('active', $device->fresh()->status);
    }

    public function test_15_non_owner_cannot_change_status_or_metadata(): void
    {
        foreach (['member', 'cashier'] as $role) {
            [$business] = $this->actingAsRole($role);
            $device = $this->createDevice(['business_id' => $business->id, 'status' => 'active']);

            $this->patch(route('devices.status.update', ['deviceId' => $device->id]), ['status' => 'inactive'])
                ->assertForbidden();
            $this->patch(route('devices.update', ['deviceId' => $device->id]), ['name' => 'Hijack'])
                ->assertForbidden();

            $this->assertSame('active', $device->fresh()->status);
        }
    }

    public function test_16_foreign_device_mutation_returns_404(): void
    {
        $this->actingAsRole('owner');
        $foreign = $this->createDevice([
            'business_id' => Business::factory()->create()->id,
            'identifier' => 'FOREIGN-01',
        ]);

        $this->patch(route('devices.update', ['deviceId' => $foreign->id]), ['name' => 'Hijack'])->assertNotFound();
        $this->patch(route('devices.status.update', ['deviceId' => $foreign->id]), ['status' => 'inactive'])->assertNotFound();

        $this->assertSame('active', $foreign->fresh()->status);
    }

    public function test_27_owner_can_update_name_and_notes_but_not_identifier_or_outlet(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'identifier' => 'EDIT-01',
            'name' => 'Lama',
        ]);

        $this->patch(route('devices.update', ['deviceId' => $device->id]), [
            'name' => 'Baru',
            'notes' => 'Catatan baru',
            // Attempted immutable fields must be ignored.
            'identifier' => 'HACKED',
            'outlet_id' => Outlet::factory()->create(['business_id' => $business->id])->id,
            'status' => 'inactive',
        ])->assertRedirect();

        $device->refresh();
        $this->assertSame('Baru', $device->name);
        $this->assertSame('Catatan baru', $device->notes);
        $this->assertSame('EDIT-01', $device->identifier);
        $this->assertSame($outlet->id, $device->outlet_id);
        $this->assertSame('active', $device->status);
    }

    // ============================================================
    // Idempotency / concurrency / timestamps
    // ============================================================

    public function test_17_concurrent_registration_race_resolves_without_500(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // A concurrent request wins the INSERT race: the trigger inserts the
        // "winner" row then aborts the outer INSERT with a UNIQUE error.
        DB::unprepared(sprintf("
            CREATE TRIGGER simulate_device_race
            BEFORE INSERT ON \"devices\"
            WHEN NEW.\"identifier\" = 'RACE-01'
            BEGIN
                INSERT INTO \"devices\"
                    (\"business_id\", \"outlet_id\", \"name\", \"identifier\", \"platform\",
                     \"status\", \"notes\", \"registered_at\", \"last_seen_at\",
                     \"created_at\", \"updated_at\")
                VALUES
                    (NEW.\"business_id\", NEW.\"outlet_id\", 'Race Winner', NEW.\"identifier\",
                     NULL, 'active', NULL,
                     NEW.\"registered_at\", NULL, NEW.\"created_at\", NEW.\"updated_at\");
                SELECT RAISE(FAIL, 'UNIQUE constraint failed: devices.business_id, devices.identifier');
            END
        "));

        try {
            $this->post(route('devices.store'), [
                'name' => 'Race Loser',
                'identifier' => 'RACE-01',
                'outlet_id' => $outlet->id,
            ])->assertRedirect();

            $this->assertDatabaseCount('devices', 1);
            $this->assertDatabaseHas('devices', ['identifier' => 'RACE-01', 'name' => 'Race Winner']);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS "simulate_device_race"');
        }
    }

    public function test_18_dashboard_registration_does_not_fake_last_seen_at(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.store'), [
            'name' => 'Never Connected',
            'identifier' => 'NOSEEN-01',
            'outlet_id' => $outlet->id,
        ])->assertRedirect();

        $device = Device::where('identifier', 'NOSEEN-01')->firstOrFail();
        $this->assertNull($device->last_seen_at);
        $this->assertNotNull($device->registered_at);
    }

    // ============================================================
    // Sync & mobile API compatibility
    // ============================================================

    public function test_19_inactive_device_is_rejected_by_sync_push(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'identifier' => 'SYNC-INACTIVE-PUSH',
            'status' => 'inactive',
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/sync/push', [
                'business_id' => $business->id,
                'device_identifier' => 'SYNC-INACTIVE-PUSH',
                'request_id' => (string) Str::uuid(),
                'changes' => [],
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_INACTIVE']);
    }

    public function test_20_inactive_device_is_rejected_by_sync_pull(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'identifier' => 'SYNC-INACTIVE-PULL',
            'status' => 'inactive',
        ]);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/sync/pull?'.http_build_query([
                'business_id' => $business->id,
                'device_identifier' => 'SYNC-INACTIVE-PULL',
                'after' => 0,
                'limit' => 10,
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_INACTIVE']);
    }

    public function test_21_mobile_api_recognises_a_dashboard_registered_device(): void
    {
        [$business, $user] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->post(route('devices.store'), [
            'name' => 'Pre Registered',
            'identifier' => 'PRE-01',
            'outlet_id' => $outlet->id,
            'platform' => 'android',
        ])->assertRedirect();

        $device = Device::where('identifier', 'PRE-01')->firstOrFail();

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'PRE-01',
                'name' => 'Pre Registered',
            ]);

        $response->assertOk();
        $response->assertJson(['data' => ['id' => $device->id, 'identifier' => 'PRE-01', 'status' => 'active']]);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_22_mobile_api_still_denies_cashier(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($cashier->id, ['role' => 'cashier']);

        $this->withToken($this->tokenFor($cashier))
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'CASHIER-API-01',
                'name' => 'Cashier API',
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);

        $this->assertDatabaseMissing('devices', ['identifier' => 'CASHIER-API-01']);
    }

    public function test_23_cloud_entitlement_is_still_enforced_on_the_api(): void
    {
        [$business, $user] = $this->actingAsRole('owner', cloud: false);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/mobile/devices', [
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'device_identifier' => 'NO-CLOUD-01',
                'name' => 'No Cloud',
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    // ============================================================
    // Listing integration
    // ============================================================

    public function test_24_filters_and_pagination_still_work(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        for ($i = 1; $i <= 30; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'identifier' => 'FILTER-'.$i,
                'status' => $i <= 5 ? 'inactive' : 'active',
                'registered_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $page = $this->get(route('devices.index', ['status' => 'active', 'page' => 2]));
        $page->assertOk();
        $page->assertViewHas('devices', fn ($devices) => $devices->perPage() === 25 && $devices->total() === 25);
    }

    public function test_25_placeholder_is_replaced_by_a_real_modal(): void
    {
        [$business] = $this->actingAsRole('owner');

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('id="deviceModal"', false);
        $response->assertDontSee('Registrasi perangkat dari dashboard belum tersedia.');
    }

    public function test_26_foreign_devices_never_leak_into_the_listing(): void
    {
        [$business] = $this->actingAsRole('owner');
        $foreignBusiness = Business::factory()->create();
        $this->createDevice([
            'business_id' => $foreignBusiness->id,
            'identifier' => 'FOREIGN-LEAK-01',
            'name' => 'Foreign Secret Device',
        ]);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertDontSee('Foreign Secret Device');
        $response->assertDontSee('FOREIGN-LEAK-01');
    }

    // ============================================================
    // Validation error recovery & status wording (DASH-17 review)
    // ============================================================

    public function test_28_invalid_registration_reopens_the_registration_modal_with_input(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $this->from(route('devices.index'))
            ->post(route('devices.store'), [
                'device_form' => 'create',
                'name' => '',
                'identifier' => 'KEEP-ME-01',
                'outlet_id' => $outlet->id,
            ])
            ->assertSessionHasErrors('name');

        $page = $this->get(route('devices.index'));
        $page->assertOk();
        $page->assertSee('data-restore-mode="create"', false);
        $page->assertSee('name="device_form" value="create"', false);
        // The user's input is preserved.
        $page->assertSee('KEEP-ME-01');
        // It must not reopen the edit form.
        $page->assertDontSee('data-restore-mode="edit"', false);
        $this->assertDatabaseCount('devices', 0);
    }

    public function test_29_invalid_edit_reopens_the_edit_modal_for_the_same_device(): void
    {
        [$business] = $this->actingAsRole('owner');
        $device = $this->createDevice([
            'business_id' => $business->id,
            'identifier' => 'EDIT-TARGET-01',
            'name' => 'Nama Lama',
        ]);

        $this->from(route('devices.index'))
            ->patch(route('devices.update', ['deviceId' => $device->id]), [
                'device_form' => 'edit',
                'device_id' => $device->id,
                'name' => '',
                'notes' => 'Catatan dipertahankan',
            ])
            ->assertSessionHasErrors('name');

        $page = $this->get(route('devices.index'));
        $page->assertOk();
        $page->assertSee('data-restore-mode="edit"', false);
        $page->assertSee('data-restore-id="'.$device->id.'"', false);
        // PATCH + the same device endpoint are preserved.
        $page->assertSee('name="device_form" value="edit"', false);
        $page->assertSee(route('devices.update', ['deviceId' => $device->id]), false);
        $page->assertSee('EDIT-TARGET-01');
        $page->assertSee('Catatan dipertahankan');
        // It must not reopen the registration form.
        $page->assertDontSee('data-restore-mode="create"', false);

        $this->assertSame('Nama Lama', $device->fresh()->name);
    }

    public function test_30_invalid_edit_keeps_identifier_and_outlet_immutable(): void
    {
        [$business] = $this->actingAsRole('owner');
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $otherOutlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'identifier' => 'IMMUTABLE-01',
        ]);

        $this->from(route('devices.index'))
            ->patch(route('devices.update', ['deviceId' => $device->id]), [
                'device_form' => 'edit',
                'device_id' => $device->id,
                'name' => '', // invalid → validation failure
                'identifier' => 'HACKED',
                'outlet_id' => $otherOutlet->id,
            ])
            ->assertSessionHasErrors('name');

        $device->refresh();
        $this->assertSame('IMMUTABLE-01', $device->identifier);
        $this->assertSame($outlet->id, $device->outlet_id);

        $page = $this->get(route('devices.index'));
        $page->assertOk();
        // The edit form renders the immutable outlet mirror and reopens as edit.
        $page->assertSee('id="deviceFormOutletMirror"', false);
        $page->assertSee('data-restore-mode="edit"', false);
    }

    public function test_31_invalid_status_change_shows_error_without_opening_the_modal(): void
    {
        [$business] = $this->actingAsRole('owner');
        $device = $this->createDevice(['business_id' => $business->id, 'status' => 'active']);

        $page = $this->followingRedirects()
            ->from(route('devices.index'))
            ->patch(route('devices.status.update', ['deviceId' => $device->id]), ['status' => 'deleted']);

        $page->assertOk();
        $page->assertSee('Status perangkat hanya dapat Aktif atau Nonaktif.');
        // No modal is reopened for a failed status toggle.
        $page->assertSee('data-restore-mode=""', false);
        $page->assertDontSee('data-restore-mode="create"', false);
        $page->assertDontSee('data-restore-mode="edit"', false);

        $this->assertSame('active', $device->fresh()->status);
    }

    public function test_32_active_device_without_last_seen_is_not_shown_as_online(): void
    {
        [$business] = $this->actingAsRole('owner');
        $this->createDevice([
            'business_id' => $business->id,
            'status' => 'active',
            'last_seen_at' => null,
        ]);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('Aktif');
        $response->assertSee('Belum Pernah Akses API');
        $response->assertDontSee('>Online<', false);
        $response->assertDontSee('Perangkat Online');
    }

    public function test_33_modal_and_listing_clarify_active_is_not_online(): void
    {
        [$business] = $this->actingAsRole('owner');
        $this->createDevice(['business_id' => $business->id, 'status' => 'active', 'last_seen_at' => null]);

        $response = $this->get(route('devices.index'));
        $response->assertOk();
        $response->assertSee('diizinkan mengakses API');
        $response->assertSee('bukan berarti perangkat sedang online');
        $response->assertSee('Akses API Terakhir');
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role, bool $cloud = true): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);

        if ($cloud) {
            Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        }

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('mobile-api', ['mobile'])->plainTextToken;
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
            'platform' => 'android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => null,
            'notes' => null,
        ], $attributes));
    }
}
