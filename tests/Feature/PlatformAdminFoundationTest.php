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
     * Keamanan: atribut is_platform_admin tidak boleh lolos dari mass assignment.
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
    }

    /**
     * Seeder: UserSeeder menyediakan akun platform admin idempotent dan tidak mengorbankan akun owner existing.
     */
    public function test_user_seeder_creates_platform_admin_and_preserves_owner(): void
    {
        $this->seed(UserSeeder::class);

        $platformUser = User::where('email', 'platform@admin.com')->firstOrFail();
        $this->assertTrue($platformUser->isPlatformAdmin());
        $this->assertNotNull($platformUser->email_verified_at);

        $adminOwner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->assertFalse($adminOwner->isPlatformAdmin());
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
