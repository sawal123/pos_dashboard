<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiningTablesPageTest extends TestCase
{
    use RefreshDatabase;

    private function setupBusinessWithRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $user->businesses()->attach($business->id, ['role' => $role]);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Utama',
            'code' => 'OUT-1',
            'status' => 'active',
        ]);

        return [$user, $business, $outlet];
    }

    public function test_owner_can_view_tables_page(): void
    {
        [$owner, $business, $outlet] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        DiningTable::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Meja 01',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('tables.index'));

        $response->assertOk();
        $response->assertSee('Pengaturan Nomor Meja');
        $response->assertSee('Meja 01');
        $response->assertSee($outlet->name);
        $response->assertSee('Tambah Banyak (Bulk)');
    }

    public function test_owner_can_toggle_table_management(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);
        $this->assertFalse((bool) $business->table_management_enabled);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('tables.toggle'), [
                'table_management_enabled' => '1',
            ]);

        $response->assertRedirect(route('tables.index'));
        $response->assertSessionHas('status', 'Fitur nomor meja berhasil diaktifkan untuk kasir (POS).');
        $this->assertTrue((bool) $business->fresh()->table_management_enabled);

        // Toggle back off
        $response2 = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('tables.toggle'), [
                'table_management_enabled' => '0',
            ]);

        $response2->assertRedirect(route('tables.index'));
        $response2->assertSessionHas('status', 'Fitur nomor meja dinonaktifkan dari kasir (POS).');
        $this->assertFalse((bool) $business->fresh()->table_management_enabled);
    }

    public function test_owner_can_create_a_dining_table(): void
    {
        [$owner, $business, $outlet] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('tables.store'), [
                'name' => 'VIP 1',
                'outlet_id' => $outlet->id,
                'capacity' => 8,
                'status' => 'available',
            ]);

        $response->assertRedirect(route('tables.index'));
        $response->assertSessionHas('status', 'Nomor meja "VIP 1" berhasil ditambahkan.');

        $this->assertDatabaseHas('dining_tables', [
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'VIP 1',
            'capacity' => 8,
            'status' => 'available',
        ]);
    }

    public function test_owner_can_bulk_create_dining_tables(): void
    {
        [$owner, $business, $outlet] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('tables.bulk'), [
                'prefix' => 'Meja',
                'start_number' => 1,
                'end_number' => 5,
                'outlet_id' => $outlet->id,
                'capacity' => 4,
                'pad_zeros' => '1',
            ]);

        $response->assertRedirect(route('tables.index'));
        $this->assertDatabaseCount('dining_tables', 5);
        $this->assertDatabaseHas('dining_tables', ['name' => 'Meja 01', 'business_id' => $business->id]);
        $this->assertDatabaseHas('dining_tables', ['name' => 'Meja 05', 'business_id' => $business->id]);
    }

    public function test_owner_can_update_a_dining_table(): void
    {
        [$owner, $business, $outlet] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $table = DiningTable::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Meja Lama',
            'capacity' => 2,
            'status' => 'available',
        ]);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('tables.update', $table->id), [
                'name' => 'Meja Baru VIP',
                'outlet_id' => $outlet->id,
                'capacity' => 6,
                'status' => 'occupied',
            ]);

        $response->assertRedirect(route('tables.index'));
        $response->assertSessionHas('status', 'Nomor meja "Meja Baru VIP" berhasil diperbarui.');

        $this->assertDatabaseHas('dining_tables', [
            'id' => $table->id,
            'name' => 'Meja Baru VIP',
            'capacity' => 6,
            'status' => 'occupied',
        ]);
    }

    public function test_owner_can_delete_a_dining_table(): void
    {
        [$owner, $business, $outlet] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $table = DiningTable::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Meja Dihapus',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->delete(route('tables.destroy', $table->id));

        $response->assertRedirect(route('tables.index'));
        $response->assertSessionHas('status', 'Nomor meja "Meja Dihapus" berhasil dihapus.');

        $this->assertDatabaseMissing('dining_tables', [
            'id' => $table->id,
        ]);
    }

    public function test_cashier_cannot_access_or_manage_tables(): void
    {
        [$cashier, $business] = $this->setupBusinessWithRole(Business::ROLE_CASHIER);

        $response = $this->actingAs($cashier)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('tables.index'));

        $response->assertForbidden();
    }

    public function test_business_settings_page_links_to_dedicated_tables_page(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('business-settings.edit'));

        $response->assertOk();
        $response->assertSee(route('tables.index'));
        $response->assertSee('Buka Pengaturan Meja');
    }

    public function test_mobile_context_returns_table_settings_and_dining_tables(): void
    {
        [$owner, $business, $outlet] = $this->setupBusinessWithRole(Business::ROLE_OWNER);
        $business->update(['table_management_enabled' => true]);

        DiningTable::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Meja VIP 1',
            'capacity' => 6,
            'status' => 'available',
        ]);

        $token = $owner->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/context');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.table_settings.enabled', true);
        $response->assertJsonPath('data.businesses.0.dining_tables.0.name', 'Meja VIP 1');
    }

    public function test_scoped_cashier_only_sees_assigned_outlet_and_global_tables(): void
    {
        [$owner, $business, $outlet1] = $this->setupBusinessWithRole(Business::ROLE_OWNER);
        $outlet2 = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet 2']);

        $cashier = User::factory()->create(['email_verified_at' => now()]);
        $cashier->businesses()->attach($business->id, [
            'role' => Business::ROLE_CASHIER,
            'outlet_id' => $outlet1->id,
        ]);

        DiningTable::create(['business_id' => $business->id, 'outlet_id' => $outlet1->id, 'name' => 'Meja Outlet 1']);
        DiningTable::create(['business_id' => $business->id, 'outlet_id' => $outlet2->id, 'name' => 'Meja Outlet 2']);
        DiningTable::create(['business_id' => $business->id, 'outlet_id' => null, 'name' => 'Meja Global']);

        $token = $cashier->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/context');

        $response->assertOk();
        $tableNames = collect($response->json('data.businesses.0.dining_tables'))->pluck('name')->all();

        $this->assertContains('Meja Outlet 1', $tableNames);
        $this->assertContains('Meja Global', $tableNames);
        $this->assertNotContains('Meja Outlet 2', $tableNames);
    }
}
