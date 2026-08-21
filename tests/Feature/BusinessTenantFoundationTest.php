<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_be_created(): void
    {
        $business = Business::create([
            'name' => 'Warung Kopi Bahagia',
            'slug' => 'warung-kopi-bahagia',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('businesses', [
            'name' => 'Warung Kopi Bahagia',
            'slug' => 'warung-kopi-bahagia',
        ]);
        $this->assertNotNull($business->id);
    }

    public function test_business_slug_must_be_unique(): void
    {
        Business::create([
            'name' => 'Toko A',
            'slug' => 'toko-a',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        Business::create([
            'name' => 'Toko A Duplikat',
            'slug' => 'toko-a',
            'status' => 'active',
        ]);
    }

    public function test_business_default_status_is_inactive(): void
    {
        $business = Business::create([
            'name' => 'Toko Baru',
            'slug' => 'toko-baru',
        ]);

        $this->assertSame('inactive', $business->status);
        $this->assertDatabaseHas('businesses', [
            'slug' => 'toko-baru',
            'status' => 'inactive',
        ]);
    }

    public function test_business_factory_can_create_valid_business(): void
    {
        $business = Business::factory()->create();

        $this->assertNotNull($business->id);
        $this->assertNotEmpty($business->name);
        $this->assertNotEmpty($business->slug);
        $this->assertSame('inactive', $business->status);
        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
    }

    public function test_business_factory_active_state_sets_active_status(): void
    {
        $business = Business::factory()->active()->create();

        $this->assertSame('active', $business->status);
    }

    public function test_business_factory_generates_unique_slugs(): void
    {
        $businesses = Business::factory()->count(5)->create();

        $slugs = $businesses->pluck('slug');
        $this->assertCount(5, $slugs->unique());
    }
}
