<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_can_see_dashboard_menus_for_active_business(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();

        $this->assertNotNull($admin->email_verified_at);
        $this->assertGreaterThan(0, $admin->businesses()->count());
        $this->assertDatabaseHas('business_user', [
            'user_id' => $admin->id,
            'role' => Business::ROLE_OWNER,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $kopi = Business::where('slug', 'kopi-nusantara')->firstOrFail();

        $response->assertOk();
        $response->assertDontSee('Belum Ada Bisnis');
        $response->assertSee('Kopi Nusantara');
        $response->assertSee(route('products.index'), false);
        $response->assertSee(route('business-settings.edit'), false);
        $response->assertSee(route('subscriptions.index'), false);

        $this->assertSame($kopi->id, session(DashboardBusinessContext::SESSION_KEY));
    }
}
