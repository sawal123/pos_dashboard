<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_can_be_created_for_business(): void
    {
        $business = Business::factory()->create();

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'address' => 'Jl. Sudirman No. 123',
        ]);

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'business_id' => $business->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'address' => 'Jl. Sudirman No. 123',
        ]);
        $this->assertNotNull($outlet->id);
    }

    public function test_outlet_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
        ]);

        $this->assertNotNull($outlet->business);
        $this->assertSame($business->id, $outlet->business->id);
    }

    public function test_business_can_have_multiple_outlets(): void
    {
        $business = Business::factory()->create();

        $outlet1 = Outlet::factory()->create([
            'business_id' => $business->id,
            'code' => 'CAB-01',
        ]);
        $outlet2 = Outlet::factory()->create([
            'business_id' => $business->id,
            'code' => 'CAB-02',
        ]);

        $this->assertCount(2, $business->outlets);
        $this->assertTrue($business->outlets->contains($outlet1));
        $this->assertTrue($business->outlets->contains($outlet2));
    }

    public function test_outlets_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create(['name' => 'Business A']);
        $businessB = Business::factory()->create(['name' => 'Business B']);

        $outletA = Outlet::factory()->create([
            'business_id' => $businessA->id,
            'code' => 'MAIN',
        ]);
        $outletB = Outlet::factory()->create([
            'business_id' => $businessB->id,
            'code' => 'MAIN',
        ]);

        $this->assertCount(1, $businessA->outlets);
        $this->assertTrue($businessA->outlets->contains($outletA));
        $this->assertFalse($businessA->outlets->contains($outletB));

        $this->assertCount(1, $businessB->outlets);
        $this->assertTrue($businessB->outlets->contains($outletB));
        $this->assertFalse($businessB->outlets->contains($outletA));
    }

    public function test_code_must_be_unique_within_the_same_business(): void
    {
        $business = Business::factory()->create();

        Outlet::create([
            'business_id' => $business->id,
            'name' => 'Outlet Satu',
            'code' => 'MAIN',
        ]);

        $this->expectException(QueryException::class);

        Outlet::create([
            'business_id' => $business->id,
            'name' => 'Outlet Dua',
            'code' => 'MAIN',
        ]);
    }

    public function test_same_code_can_be_used_by_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::create([
            'business_id' => $businessA->id,
            'name' => 'Main Outlet A',
            'code' => 'MAIN',
        ]);

        $outletB = Outlet::create([
            'business_id' => $businessB->id,
            'name' => 'Main Outlet B',
            'code' => 'MAIN',
        ]);

        $this->assertDatabaseHas('outlets', [
            'id' => $outletA->id,
            'business_id' => $businessA->id,
            'code' => 'MAIN',
        ]);
        $this->assertDatabaseHas('outlets', [
            'id' => $outletB->id,
            'business_id' => $businessB->id,
            'code' => 'MAIN',
        ]);
    }

    public function test_default_status_of_outlet_is_active(): void
    {
        $business = Business::factory()->create();

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'name' => 'New Outlet',
            'code' => 'NEW-01',
        ]);

        $this->assertSame('active', $outlet->status);
        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'status' => 'active',
        ]);
    }

    public function test_inactive_outlet_can_be_created(): void
    {
        $business = Business::factory()->create();

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'name' => 'Closed Outlet',
            'code' => 'CLOSED-01',
            'status' => 'inactive',
        ]);

        $this->assertSame('inactive', $outlet->status);
        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'status' => 'inactive',
        ]);
    }

    public function test_outlet_address_can_be_null(): void
    {
        $business = Business::factory()->create();

        $outlet = Outlet::create([
            'business_id' => $business->id,
            'name' => 'Online Outlet',
            'code' => 'ONLINE-01',
            'address' => null,
        ]);

        $this->assertNull($outlet->address);
        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'address' => null,
        ]);
    }

    public function test_deleting_business_cascades_and_deletes_its_outlets(): void
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
        ]);

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'business_id' => $business->id,
        ]);

        $business->delete();

        $this->assertDatabaseMissing('outlets', [
            'id' => $outlet->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_deleting_business_a_does_not_delete_outlets_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $outletA = Outlet::factory()->create([
            'business_id' => $businessA->id,
        ]);
        $outletB = Outlet::factory()->create([
            'business_id' => $businessB->id,
        ]);

        $businessA->delete();

        $this->assertDatabaseMissing('outlets', [
            'id' => $outletA->id,
            'business_id' => $businessA->id,
        ]);
        $this->assertDatabaseHas('outlets', [
            'id' => $outletB->id,
            'business_id' => $businessB->id,
        ]);
    }

    public function test_factory_can_create_valid_active_and_inactive_outlets(): void
    {
        $outletActive = Outlet::factory()->create();
        $outletInactive = Outlet::factory()->inactive()->create();

        $this->assertNotNull($outletActive->id);
        $this->assertSame('active', $outletActive->status);

        $this->assertNotNull($outletInactive->id);
        $this->assertSame('inactive', $outletInactive->status);
    }
}
