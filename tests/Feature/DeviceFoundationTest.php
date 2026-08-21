<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeviceFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // 1. DEVICE FOUNDATION TESTS
    // ==========================================

    public function test_device_can_be_created_for_valid_business_and_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Kasir Depan',
            'identifier' => 'POS-MEDAN-01',
            'platform' => 'android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => null,
            'notes' => 'Tablet Samsung A9',
        ]);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Kasir Depan',
            'identifier' => 'POS-MEDAN-01',
            'platform' => 'android',
            'status' => 'active',
        ]);
        $this->assertNotNull($device->id);
    }

    public function test_device_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'DEV-001',
            'registered_at' => now(),
        ]);

        $this->assertNotNull($device->business);
        $this->assertSame($business->id, $device->business->id);
    }

    public function test_device_belongs_to_outlet(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'DEV-001',
            'registered_at' => now(),
        ]);

        $this->assertNotNull($device->outlet);
        $this->assertSame($outlet->id, $device->outlet->id);
    }

    public function test_business_can_have_multiple_devices(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $dev1 = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device 1',
            'identifier' => 'DEV-001',
            'registered_at' => now(),
        ]);
        $dev2 = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device 2',
            'identifier' => 'DEV-002',
            'registered_at' => now(),
        ]);

        $this->assertCount(2, $business->devices);
        $this->assertTrue($business->devices->contains($dev1));
        $this->assertTrue($business->devices->contains($dev2));
    }

    public function test_outlet_can_have_multiple_devices(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $dev1 = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device 1',
            'identifier' => 'DEV-001',
            'registered_at' => now(),
        ]);
        $dev2 = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device 2',
            'identifier' => 'DEV-002',
            'registered_at' => now(),
        ]);

        $this->assertCount(2, $outlet->devices);
        $this->assertTrue($outlet->devices->contains($dev1));
        $this->assertTrue($outlet->devices->contains($dev2));
    }

    public function test_devices_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $devA = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device A',
            'identifier' => 'DEV-A',
            'registered_at' => now(),
        ]);
        $devB = Device::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'Device B',
            'identifier' => 'DEV-B',
            'registered_at' => now(),
        ]);

        $this->assertCount(1, $businessA->devices);
        $this->assertTrue($businessA->devices->contains($devA));
        $this->assertFalse($businessA->devices->contains($devB));

        $this->assertCount(1, $businessB->devices);
        $this->assertTrue($businessB->devices->contains($devB));
        $this->assertFalse($businessB->devices->contains($devA));
    }

    public function test_devices_of_outlet_a_do_not_appear_in_outlet_b(): void
    {
        $business = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-A']);
        $outletB = Outlet::factory()->create(['business_id' => $business->id, 'code' => 'OUT-B']);

        $devA = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device A',
            'identifier' => 'DEV-A',
            'registered_at' => now(),
        ]);
        $devB = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'name' => 'Device B',
            'identifier' => 'DEV-B',
            'registered_at' => now(),
        ]);

        $this->assertCount(1, $outletA->devices);
        $this->assertTrue($outletA->devices->contains($devA));
        $this->assertFalse($outletA->devices->contains($devB));

        $this->assertCount(1, $outletB->devices);
        $this->assertTrue($outletB->devices->contains($devB));
        $this->assertFalse($outletB->devices->contains($devA));
    }

    // ==========================================
    // 2. TENANT SAFETY TESTS
    // ==========================================

    public function test_device_of_business_a_cannot_use_outlet_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->expectException(QueryException::class);

        Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletB->id,
            'name' => 'Cross Biz Device',
            'identifier' => 'DEV-CROSS',
            'registered_at' => now(),
        ]);
    }

    // ==========================================
    // 3. IDENTIFIER & NAME TESTS
    // ==========================================

    public function test_identifier_must_be_unique_within_same_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device 1',
            'identifier' => 'DEV-DUP',
            'registered_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Device 2',
            'identifier' => 'DEV-DUP',
            'registered_at' => now(),
        ]);
    }

    public function test_same_identifier_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);

        $devA = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Kasir A',
            'identifier' => 'POS-01',
            'registered_at' => now(),
        ]);
        $devB = Device::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'Kasir B',
            'identifier' => 'POS-01',
            'registered_at' => now(),
        ]);

        $this->assertDatabaseHas('devices', ['id' => $devA->id, 'business_id' => $businessA->id]);
        $this->assertDatabaseHas('devices', ['id' => $devB->id, 'business_id' => $businessB->id]);
    }

    public function test_duplicate_device_names_in_same_business_are_allowed(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $dev1 = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Kasir Utama',
            'identifier' => 'DEV-NAME-1',
            'registered_at' => now(),
        ]);
        $dev2 = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Kasir Utama',
            'identifier' => 'DEV-NAME-2',
            'registered_at' => now(),
        ]);

        $this->assertDatabaseHas('devices', ['id' => $dev1->id, 'name' => 'Kasir Utama']);
        $this->assertDatabaseHas('devices', ['id' => $dev2->id, 'name' => 'Kasir Utama']);
    }

    // ==========================================
    // 4. STATUS TESTS
    // ==========================================

    public function test_default_status_of_device_is_active(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Active Device',
            'identifier' => 'DEV-ACTIVE',
            'registered_at' => now(),
        ]);

        $this->assertSame('active', $device->status);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'status' => 'active']);
    }

    public function test_inactive_device_can_be_created(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Inactive Device',
            'identifier' => 'DEV-INACTIVE',
            'status' => 'inactive',
            'registered_at' => now(),
        ]);

        $this->assertSame('inactive', $device->status);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'status' => 'inactive']);
    }

    // ==========================================
    // 5. PLATFORM TESTS
    // ==========================================

    public function test_platform_can_be_null(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Unknown Platform Device',
            'identifier' => 'DEV-NOPLAT',
            'platform' => null,
            'registered_at' => now(),
        ]);

        $this->assertNull($device->platform);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'platform' => null]);
    }

    public function test_platform_string_can_be_stored(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Android Device',
            'identifier' => 'DEV-ANDROID',
            'platform' => 'android',
            'registered_at' => now(),
        ]);

        $this->assertSame('android', $device->platform);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'platform' => 'android']);
    }

    // ==========================================
    // 6. TIMESTAMP DOMAIN TESTS
    // ==========================================

    public function test_registered_at_is_cast_to_datetime(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $registeredAt = Carbon::parse('2026-08-21 10:00:00');

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Registered Device',
            'identifier' => 'DEV-REGDATE',
            'registered_at' => $registeredAt,
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $device->registered_at);
        $this->assertSame('2026-08-21 10:00:00', $device->registered_at->format('Y-m-d H:i:s'));
    }

    public function test_last_seen_at_can_be_null(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Unseen Device',
            'identifier' => 'DEV-NOSEEN',
            'registered_at' => now(),
            'last_seen_at' => null,
        ]);

        $this->assertNull($device->last_seen_at);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'last_seen_at' => null]);
    }

    public function test_last_seen_at_is_cast_to_datetime_when_provided(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $lastSeenAt = Carbon::parse('2026-08-21 14:45:00');

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Seen Device',
            'identifier' => 'DEV-SEEN',
            'registered_at' => now(),
            'last_seen_at' => $lastSeenAt,
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $device->last_seen_at);
        $this->assertSame('2026-08-21 14:45:00', $device->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_notes_can_be_null(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'No Notes Device',
            'identifier' => 'DEV-NONOTES',
            'registered_at' => now(),
            'notes' => null,
        ]);

        $this->assertNull($device->notes);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'notes' => null]);
    }

    // ==========================================
    // 7. DELETE SAFETY TESTS
    // ==========================================

    public function test_direct_delete_device_succeeds(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Direct Delete Device',
            'identifier' => 'DEV-DEL',
            'registered_at' => now(),
        ]);

        $this->assertDatabaseHas('devices', ['id' => $device->id]);

        $device->delete();

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_deleting_outlet_with_device_is_restricted(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Outlet Device',
            'identifier' => 'DEV-OUTLET-DEL',
            'registered_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $outlet->delete();
    }

    public function test_deleting_business_a_cleans_up_device_a(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Business Cleanup Device',
            'identifier' => 'DEV-BIZ-CLEAN',
            'registered_at' => now(),
        ]);

        $business->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_deleting_business_a_cleans_all_tenant_dependencies_including_devices(): void
    {
        $businessA = Business::factory()->create();
        $categoryA = Category::factory()->create(['business_id' => $businessA->id]);
        $productA = Product::factory()->create(['business_id' => $businessA->id, 'category_id' => $categoryA->id]);
        $customerA = Customer::factory()->create(['business_id' => $businessA->id]);
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);

        $deviceA = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device Full A',
            'identifier' => 'DEV-FULL-A',
            'registered_at' => now(),
        ]);

        $saleA = Sale::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'customer_id' => $customerA->id,
            'transaction_number' => 'TRX-FULL-DEV-A',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'sold_at' => now(),
        ]);

        $itemA = SaleItem::create([
            'business_id' => $businessA->id,
            'sale_id' => $saleA->id,
            'product_id' => $productA->id,
            'product_name' => $productA->name,
            'product_sku' => $productA->sku,
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $businessA->id]);
        $this->assertDatabaseMissing('categories', ['id' => $categoryA->id]);
        $this->assertDatabaseMissing('products', ['id' => $productA->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customerA->id]);
        $this->assertDatabaseMissing('outlets', ['id' => $outletA->id]);
        $this->assertDatabaseMissing('devices', ['id' => $deviceA->id]);
        $this->assertDatabaseMissing('sales', ['id' => $saleA->id]);
        $this->assertDatabaseMissing('sale_items', ['id' => $itemA->id]);
    }

    public function test_deleting_business_a_does_not_delete_devices_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $outletA = Outlet::factory()->create(['business_id' => $businessA->id]);
        $devA = Device::create([
            'business_id' => $businessA->id,
            'outlet_id' => $outletA->id,
            'name' => 'Device A',
            'identifier' => 'DEV-ISO-A',
            'registered_at' => now(),
        ]);

        $businessB = Business::factory()->create();
        $outletB = Outlet::factory()->create(['business_id' => $businessB->id]);
        $devB = Device::create([
            'business_id' => $businessB->id,
            'outlet_id' => $outletB->id,
            'name' => 'Device B',
            'identifier' => 'DEV-ISO-B',
            'registered_at' => now(),
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('businesses', ['id' => $businessA->id]);
        $this->assertDatabaseMissing('devices', ['id' => $devA->id]);

        $this->assertDatabaseHas('businesses', ['id' => $businessB->id]);
        $this->assertDatabaseHas('outlets', ['id' => $outletB->id]);
        $this->assertDatabaseHas('devices', ['id' => $devB->id]);
    }
}
