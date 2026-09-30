<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A. Platform Admin berhasil mengakses /platform dengan status 200.
     */
    public function test_platform_admin_can_access_platform_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $response->assertSee('Platform Admin Overview');
        $response->assertSee('ADMIN-01');
        $response->assertSee($platformAdmin->name);
        $response->assertSee($platformAdmin->email);
        $response->assertSee('Super Admin');
        $response->assertSee('Decoupled');
    }

    /**
     * Platform Admin juga dapat mengakses alias /platform/dashboard dengan status 200.
     */
    public function test_platform_admin_can_access_platform_dashboard_alias(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/dashboard');

        $response->assertOk();
        $response->assertSee('Platform Admin Overview');
    }

    /**
     * B. User bisnis dengan role owner ditolak dengan status 403.
     */
    public function test_business_owner_is_forbidden_from_platform_dashboard(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->assertFalse($owner->isPlatformAdmin());

        $response = $this->actingAs($owner)->get('/platform');

        $response->assertForbidden();
    }

    /**
     * C. Member bisnis (kasir / manager) ditolak dengan status 403.
     */
    public function test_business_members_are_forbidden_from_platform_dashboard(): void
    {
        $cashier = User::factory()->create();
        $member = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($cashier->id, ['role' => Business::ROLE_CASHIER]);
        $business->users()->attach($member->id, ['role' => Business::ROLE_MEMBER]);

        $this->assertFalse($cashier->isPlatformAdmin());
        $this->assertFalse($member->isPlatformAdmin());

        $this->actingAs($cashier)->get('/platform')->assertForbidden();
        $this->actingAs($member)->get('/platform')->assertForbidden();
    }

    /**
     * D. User biasa / non-platform-admin tanpa bisnis ditolak dengan status 403.
     */
    public function test_regular_authenticated_user_without_business_is_forbidden(): void
    {
        $regularUser = User::factory()->create();

        $this->assertFalse($regularUser->isPlatformAdmin());
        $this->assertSame(0, $regularUser->businesses()->count());

        $response = $this->actingAs($regularUser)->get('/platform');

        $response->assertForbidden();
    }

    /**
     * E. Guest / unauthenticated diarahkan ke halaman login.
     */
    public function test_guest_is_redirected_to_login_page(): void
    {
        $response = $this->get('/platform');

        $response->assertRedirect(route('login'));
    }

    /**
     * Platform Admin yang belum verified diarahkan ke notice verifikasi email.
     */
    public function test_unverified_platform_admin_is_redirected_to_verification_notice(): void
    {
        $unverifiedAdmin = User::factory()->platformAdmin()->unverified()->create();

        $response = $this->actingAs($unverifiedAdmin)->get('/platform');

        $response->assertRedirect(route('verification.notice'));
    }

    /**
     * F. Platform Admin tidak memerlukan relasi business_user maupun business context.
     */
    public function test_platform_admin_does_not_require_business_context_or_membership(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->assertSame(0, $platformAdmin->businesses()->count());
        $this->assertDatabaseMissing('business_user', ['user_id' => $platformAdmin->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform');

        $response->assertOk();
        $response->assertSessionMissing('dashboard.current_business_id');
    }

    /**
     * Keamanan: atribut is_platform_admin tidak boleh lolos dari mass assignment (create maupun update).
     */
    public function test_is_platform_admin_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Escalation Attempt',
            'email' => 'escalate@example.com',
            'password' => 'secret123',
            'is_platform_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->isPlatformAdmin());
        $this->assertFalse((bool) $user->fresh()->is_platform_admin);

        $user->update([
            'is_platform_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->isPlatformAdmin());
        $this->assertFalse((bool) $user->fresh()->is_platform_admin);
    }

    /**
     * Seeder: UserSeeder menyediakan akun platform admin idempotent di testing/local dan menjaga akun owner existing.
     */
    public function test_user_seeder_creates_platform_admin_idempotently_in_testing(): void
    {
        // Dijalankan dua kali untuk menguji idempotensi seeder
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $platformUser = User::where('email', 'platform@admin.com')->firstOrFail();
        $this->assertTrue($platformUser->isPlatformAdmin());
        $this->assertNotNull($platformUser->email_verified_at);
        $this->assertSame(1, User::where('email', 'platform@admin.com')->count());

        $adminOwner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->assertFalse($adminOwner->isPlatformAdmin());
        $this->assertSame(1, User::where('email', 'admin@gmail.com')->count());
    }

    /**
     * Keamanan: UserSeeder tidak boleh membuat akun dummy Platform Admin pada environment production.
     */
    public function test_user_seeder_does_not_create_dummy_platform_admin_in_production(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            app(UserSeeder::class)->run();

            $this->assertDatabaseMissing('users', [
                'email' => 'platform@admin.com',
            ]);
            $this->assertDatabaseMissing('users', [
                'is_platform_admin' => true,
            ]);

            // Owner demo tetap dibuat di database
            $this->assertDatabaseHas('users', [
                'email' => 'admin@gmail.com',
                'is_platform_admin' => false,
            ]);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    /**
     * Keamanan: artisan db:seed --force pada production tidak membuat akun dummy Platform Admin.
     */
    public function test_artisan_db_seed_force_in_production_does_not_create_dummy_platform_admin(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])
                ->assertSuccessful();

            $this->assertDatabaseMissing('users', [
                'email' => 'platform@admin.com',
            ]);
            $this->assertDatabaseMissing('users', [
                'is_platform_admin' => true,
            ]);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    /**
     * G. Regression: Dashboard Owner existing tetap berfungsi normal dengan business context.
     */
    public function test_owner_dashboard_remains_functional_regression(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create(['name' => 'Toko Regression']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Toko Regression');
    }
}
