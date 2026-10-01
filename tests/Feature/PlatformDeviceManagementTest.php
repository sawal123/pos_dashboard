<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\CloudDeviceLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformDeviceManagementTest extends TestCase
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
    public function test_platform_admin_can_access_devices_index(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/devices');

        $response->assertOk();
        $response->assertSee('Manajemen Perangkat Cloud');
        $response->assertSee('Total Perangkat');
        $response->assertSee('Perangkat Aktif');
    }

    public function test_business_owner_is_forbidden_from_devices_index(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/devices')->assertForbidden();
    }

    public function test_regular_user_is_forbidden_from_devices_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/platform/devices')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_devices_index(): void
    {
        $this->get('/platform/devices')->assertRedirect(route('login'));
    }

    public function test_platform_admin_can_access_device_show(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $device = $this->createDevice(['name' => 'Tablet Kasir 1']);

        $response = $this->actingAs($platformAdmin)->get('/platform/devices/'.$device->id);

        $response->assertOk();
        $response->assertSee('Tablet Kasir 1');
        $response->assertSee('Informasi Perangkat');
        $response->assertSee('Konsumsi Kuota Cloud');
    }

    public function test_non_platform_admin_is_forbidden_from_device_show(): void
    {
        $user = User::factory()->create();
        $device = $this->createDevice();

        $this->actingAs($user)->get('/platform/devices/'.$device->id)->assertForbidden();
    }

    /**
     * B. Inventory Listing & Multi-Tenant Association Tests
     */
    public function test_devices_index_lists_devices_across_multiple_businesses_and_outlets(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $businessA = Business::factory()->create(['name' => 'Kopi Mantap']);
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id, 'name' => 'Outlet Pusat']);
        $deviceA = $this->createDevice([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'POS Barista',
            'identifier' => 'DEV-KM-01',
        ]);

        $businessB = Business::factory()->create(['name' => 'Resto Lezat']);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id, 'name' => 'Cabang Mall']);
        $deviceB = $this->createDevice([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'Tablet Waiter',
            'identifier' => 'DEV-RL-01',
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/devices');

        $response->assertOk();
        $response->assertSee('POS Barista');
        $response->assertSee('DEV-KM-01');
        $response->assertSee('Kopi Mantap');
        $response->assertSee('Outlet Pusat');

        $response->assertSee('Tablet Waiter');
        $response->assertSee('DEV-RL-01');
        $response->assertSee('Resto Lezat');
        $response->assertSee('Cabang Mall');
    }

    /**
     * C. Search & Filter Tests
     */
    public function test_devices_index_search_by_name_identifier_business_and_outlet(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['name' => 'Apotek Sehat', 'slug' => 'apotek-sehat']);
        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Farmasi Lt 1']);
        $targetDevice = $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Kasir Utama Apotek',
            'identifier' => 'APOTEK-001',
        ]);

        $otherDevice = $this->createDevice([
            'name' => 'Printer Stand',
            'identifier' => 'OTHER-999',
        ]);

        // 1. Search by device name
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?q=Kasir+Utama');
        $response->assertOk();
        $response->assertSee('APOTEK-001');
        $response->assertDontSee('OTHER-999');

        // 2. Search by identifier
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?q=APOTEK-001');
        $response->assertOk();
        $response->assertSee('Kasir Utama Apotek');
        $response->assertDontSee('OTHER-999');

        // 3. Search by business name
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?q=Apotek+Sehat');
        $response->assertOk();
        $response->assertSee('APOTEK-001');
        $response->assertDontSee('OTHER-999');

        // 4. Search by outlet name
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?q=Farmasi');
        $response->assertOk();
        $response->assertSee('APOTEK-001');
        $response->assertDontSee('OTHER-999');
    }

    public function test_devices_index_filter_by_status(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $activeDevice = $this->createDevice(['name' => 'Perangkat Online', 'status' => 'active']);
        $inactiveDevice = $this->createDevice(['name' => 'Perangkat Rusak', 'status' => 'inactive']);

        // Filter active
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?status=active');
        $response->assertOk();
        $response->assertSee('Perangkat Online');
        $response->assertDontSee('Perangkat Rusak');

        // Filter inactive
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?status=inactive');
        $response->assertOk();
        $response->assertSee('Perangkat Rusak');
        $response->assertDontSee('Perangkat Online');
    }

    public function test_devices_index_filter_by_entitlement(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        // Business with active Cloud subscription
        $businessCloud = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $businessCloud->id,
            'expires_at' => now()->addMonth(),
        ]);
        $deviceCloud = $this->createDevice(['business_id' => $businessCloud->id, 'name' => 'Cloud Tablet']);

        // Business on Free tier
        $businessFree = Business::factory()->create();
        Subscription::factory()->free()->create(['business_id' => $businessFree->id]);
        $deviceFree = $this->createDevice(['business_id' => $businessFree->id, 'name' => 'Free Tablet']);

        // Filter cloud_active
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?entitlement=cloud_active');
        $response->assertOk();
        $response->assertSee('Cloud Tablet');
        $response->assertDontSee('Free Tablet');

        // Filter cloud_denied
        $response = $this->actingAs($platformAdmin)->get('/platform/devices?entitlement=cloud_denied');
        $response->assertOk();
        $response->assertSee('Free Tablet');
        $response->assertDontSee('Cloud Tablet');
    }

    /**
     * D. Pagination
     */
    public function test_devices_index_pagination_retains_query_string(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['name' => 'Kedai Kopi']);

        // Create 28 devices for this business
        for ($i = 1; $i <= 28; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'name' => sprintf('Device Batch %02d', $i),
                'identifier' => sprintf('DEV-BATCH-%02d', $i),
            ]);
        }

        $response = $this->actingAs($platformAdmin)->get('/platform/devices?status=active&page=2');
        $response->assertOk();
        $response->assertSee('status=active');
    }

    /**
     * E. Detail View & Quota Honesty
     */
    public function test_device_detail_displays_quota_and_status_honestly(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['name' => 'Bakery Segar']);
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => now()->addMonth(),
        ]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Dapur Utama']);

        // Create 2 active devices and 1 inactive device
        $active1 = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'status' => 'active']);
        $active2 = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'status' => 'active']);
        $inactive = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'status' => 'inactive']);

        // 1. Check active device detail
        $response = $this->actingAs($platformAdmin)->get('/platform/devices/'.$active1->id);
        $response->assertOk();
        $response->assertSee('Bakery Segar');
        $response->assertSee('Dapur Utama');
        $response->assertSee('Ya (Mengonsumsi 1 Slot)');
        $response->assertSee('2 / 5');
        $response->assertSee('3 Slot');
        $response->assertSee('Nonaktifkan / Cabut Perangkat');

        // 2. Check inactive device detail
        $responseInactive = $this->actingAs($platformAdmin)->get('/platform/devices/'.$inactive->id);
        $responseInactive->assertOk();
        $responseInactive->assertSee('Tidak (Nonaktif)');
        $responseInactive->assertSee('Aktifkan Kembali Perangkat');
    }

    public function test_device_detail_renders_cleanly_when_outlet_is_null_or_subscription_is_missing(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['name' => 'Usaha Lepas']);
        // No subscription row
        Subscription::query()->where('business_id', $business->id)->delete();

        $device = $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Stand Alone POS',
        ]);

        $response = $this->actingAs($platformAdmin)->get('/platform/devices/'.$device->id);

        $response->assertOk();
        $response->assertSee('Stand Alone POS');
        $response->assertSee('Akses Cloud Tidak Aktif');
    }

    /**
     * F. Lifecycle Mutations (Deactivate / Activate)
     */
    public function test_platform_admin_can_deactivate_device(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $device = $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Tablet Kasir A',
            'status' => 'active',
        ]);

        $this->assertSame(1, app(CloudDeviceLimit::class)->activeCount($business));

        $response = $this->actingAs($platformAdmin)->patch('/platform/devices/'.$device->id.'/deactivate');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $freshDevice = $device->fresh();
        $this->assertSame('inactive', $freshDevice->status);
        $this->assertSame(0, app(CloudDeviceLimit::class)->activeCount($business));

        // Business and outlet still exist intact
        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    public function test_platform_admin_can_deactivate_device_even_if_subscription_is_expired_or_free(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);

        $device = $this->createDevice([
            'business_id' => $business->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($platformAdmin)->patch('/platform/devices/'.$device->id.'/deactivate');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('inactive', $device->fresh()->status);
    }

    public function test_platform_admin_can_reactivate_device_when_entitled_and_under_quota(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => now()->addMonth(),
        ]);

        $device = $this->createDevice([
            'business_id' => $business->id,
            'status' => 'inactive',
        ]);

        $this->assertSame(0, app(CloudDeviceLimit::class)->activeCount($business));

        $response = $this->actingAs($platformAdmin)->patch('/platform/devices/'.$device->id.'/activate');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('active', $device->fresh()->status);
        $this->assertSame(1, app(CloudDeviceLimit::class)->activeCount($business));
    }

    public function test_platform_admin_cannot_reactivate_device_when_quota_is_full(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => now()->addMonth(),
        ]);

        // Create 5 active devices (filling the limit)
        for ($i = 1; $i <= 5; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'status' => 'active',
                'identifier' => 'DEV-FULL-'.$i,
            ]);
        }

        $this->assertTrue(app(CloudDeviceLimit::class)->isReached($business));

        // Create 6th inactive device
        $deviceInactive = $this->createDevice([
            'business_id' => $business->id,
            'status' => 'inactive',
            'identifier' => 'DEV-EXTRA',
        ]);

        $response = $this->actingAs($platformAdmin)->patch('/platform/devices/'.$deviceInactive->id.'/activate');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('inactive', $deviceInactive->fresh()->status);
        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));
    }

    public function test_platform_admin_cannot_reactivate_device_when_business_has_no_cloud_access(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        // Free tier (no cloud access)
        Subscription::factory()->free()->create(['business_id' => $business->id]);

        $device = $this->createDevice([
            'business_id' => $business->id,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($platformAdmin)->patch('/platform/devices/'.$device->id.'/activate');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('inactive', $device->fresh()->status);
    }

    /**
     * G. Registration Concurrency & Quota Hardening Tests
     */
    public function test_mobile_device_registration_concurrency_and_quota_limit_enforced(): void
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        $token = $owner->createToken('pos-mobile', ['mobile'])->plainTextToken;

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => now()->addMonth(),
        ]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Pre-create 4 active devices (limit is 5)
        for ($i = 1; $i <= 4; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'identifier' => 'EXISTING-0'.$i,
                'status' => 'active',
            ]);
        }

        $this->assertSame(4, app(CloudDeviceLimit::class)->activeCount($business));

        // 1. 5th device registration succeeds
        $response5 = $this->withToken($token)->postJson('/api/mobile/devices', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'device_identifier' => 'DEVICE-05',
            'name' => 'Device Lima',
            'platform' => 'android',
        ]);

        $response5->assertOk();
        $response5->assertJsonPath('data.status', 'active');
        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));

        // 2. 6th device registration is rejected with CLOUD_DEVICE_LIMIT_REACHED
        $response6 = $this->withToken($token)->postJson('/api/mobile/devices', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'device_identifier' => 'DEVICE-06',
            'name' => 'Device Enam',
            'platform' => 'android',
        ]);

        $response6->assertForbidden();
        $response6->assertJsonPath('code', 'CLOUD_DEVICE_LIMIT_REACHED');
        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));
    }

    public function test_same_device_re_registration_does_not_consume_new_slot_when_limit_is_full(): void
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        $token = $owner->createToken('pos-mobile', ['mobile'])->plainTextToken;

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => now()->addMonth(),
        ]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // Pre-create 5 active devices (filling quota)
        $devices = [];
        for ($i = 1; $i <= 5; $i++) {
            $devices[] = $this->createDevice([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'identifier' => 'DEV-FULL-'.$i,
                'status' => 'active',
                'last_seen_at' => now()->subDay(),
            ]);
        }

        $this->assertTrue(app(CloudDeviceLimit::class)->isReached($business));

        // Same device DEV-FULL-1 calls register again
        $response = $this->withToken($token)->postJson('/api/mobile/devices', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'device_identifier' => 'DEV-FULL-1',
            'name' => 'Device 1 Reconnect',
            'platform' => 'android',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.identifier', 'DEV-FULL-1');
        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));
    }

    public function test_inactive_device_does_not_consume_slot_allowing_new_device(): void
    {
        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);
        $token = $owner->createToken('pos-mobile', ['mobile'])->plainTextToken;

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'expires_at' => now()->addMonth(),
        ]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        // 4 active devices + 1 inactive device = 5 total rows
        for ($i = 1; $i <= 4; $i++) {
            $this->createDevice([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'identifier' => 'ACTIVE-0'.$i,
                'status' => 'active',
            ]);
        }
        $this->createDevice([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'identifier' => 'INACTIVE-01',
            'status' => 'inactive',
        ]);

        // Active count is 4, not 5
        $this->assertSame(4, app(CloudDeviceLimit::class)->activeCount($business));

        // New device registers -> succeeds as 5th active device
        $response = $this->withToken($token)->postJson('/api/mobile/devices', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'device_identifier' => 'NEW-ACTIVE-05',
            'name' => 'New Active Device',
            'platform' => 'android',
        ]);

        $response->assertOk();
        $this->assertSame(5, app(CloudDeviceLimit::class)->activeCount($business));
        $this->assertDatabaseCount('devices', 6);
    }

    /**
     * Helper to create device with default attributes.
     *
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
