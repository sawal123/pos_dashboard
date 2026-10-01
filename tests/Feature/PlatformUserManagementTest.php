<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformUserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A. Platform Admin can access /platform/users with 200 OK.
     */
    public function test_platform_admin_can_access_users_index(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/users');

        $response->assertOk();
        $response->assertSee('Manajemen Pengguna Platform');
        $response->assertSee('Total:');
    }

    /**
     * B. Business owner is forbidden from accessing /platform/users (403).
     */
    public function test_business_owner_is_forbidden_from_users_index(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $this->actingAs($owner)->get('/platform/users')->assertForbidden();
    }

    /**
     * C. Regular user is forbidden from accessing /platform/users (403).
     */
    public function test_regular_user_is_forbidden_from_users_index(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)->get('/platform/users')->assertForbidden();
    }

    /**
     * D. Guest is redirected to login page.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/platform/users')->assertRedirect(route('login'));
    }

    /**
     * E & Section 23: Explicit test of account type semantics.
     * 1 Platform Admin without business -> "Platform Admin"
     * 1 non-admin with business -> "User Bisnis"
     * 1 non-admin without business -> "Belum Terhubung"
     */
    public function test_users_index_displays_correct_account_types_and_data(): void
    {
        $admin = User::factory()->platformAdmin()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@platform.test',
        ]);

        $businessUser = User::factory()->create([
            'name' => 'Merchant Owner Guy',
            'email' => 'owner@merchant.test',
            'is_platform_admin' => false,
        ]);
        $business = Business::factory()->create(['name' => 'Warung Kopi Jaya']);
        $business->users()->attach($businessUser->id, ['role' => Business::ROLE_OWNER]);

        $unconnectedUser = User::factory()->create([
            'name' => 'Solo Customer User',
            'email' => 'solo@client.test',
            'is_platform_admin' => false,
        ]);

        $response = $this->actingAs($admin)->get('/platform/users');

        $response->assertOk();
        $response->assertSee('Super Administrator');
        $response->assertSee('admin@platform.test');
        $response->assertSee('Platform Admin');

        $response->assertSee('Merchant Owner Guy');
        $response->assertSee('owner@merchant.test');
        $response->assertSee('User Bisnis');
        $response->assertSee('Warung Kopi Jaya');
        $response->assertSee('Pemilik');

        $response->assertSee('Solo Customer User');
        $response->assertSee('solo@client.test');
        $response->assertSee('Belum Terhubung');
    }

    /**
     * F. Search by user name, email, or associated business name.
     */
    public function test_search_filters_users_by_name_email_or_business(): void
    {
        $admin = User::factory()->platformAdmin()->create(['name' => 'Chief Admin', 'email' => 'chief@admin.local']);
        $userA = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@tokobudi.test']);
        $userB = User::factory()->create(['name' => 'Siti Rahma', 'email' => 'siti@warungsiti.test']);

        $businessC = Business::factory()->create(['name' => 'Kedai Kopi Melati']);
        $userC = User::factory()->create(['name' => 'Ahmad Dani', 'email' => 'ahmad@music.test']);
        $businessC->users()->attach($userC->id, ['role' => Business::ROLE_MEMBER]);

        // Search by name
        $responseName = $this->actingAs($admin)->get('/platform/users?q=Budi');
        $responseName->assertOk();
        $responseName->assertSee('Budi Santoso');
        $responseName->assertDontSee('Siti Rahma');
        $responseName->assertDontSee('Ahmad Dani');

        // Search by email
        $responseEmail = $this->actingAs($admin)->get('/platform/users?q=warungsiti.test');
        $responseEmail->assertOk();
        $responseEmail->assertSee('Siti Rahma');
        $responseEmail->assertDontSee('Budi Santoso');

        // Search by associated business name
        $responseBusiness = $this->actingAs($admin)->get('/platform/users?q=Melati');
        $responseBusiness->assertOk();
        $responseBusiness->assertSee('Ahmad Dani');
        $responseBusiness->assertDontSee('Budi Santoso');
        $responseBusiness->assertDontSee('Siti Rahma');
    }

    /**
     * G. Filter by account type: platform_admin, business_user, unconnected.
     */
    public function test_filter_by_account_type(): void
    {
        $actingAdmin = User::factory()->platformAdmin()->create(['name' => 'Session Operator', 'email' => 'session@admin.test']);
        $targetAdmin = User::factory()->platformAdmin()->create(['name' => 'Platform Admin Target', 'email' => 'target.admin@platform.test']);
        $bizUser = User::factory()->create(['name' => 'Pengusaha Sejati', 'is_platform_admin' => false]);
        $biz = Business::factory()->create(['name' => 'Resto Sejati']);
        $biz->users()->attach($bizUser->id, ['role' => Business::ROLE_OWNER]);

        $unconnected = User::factory()->create(['name' => 'User Lepas', 'is_platform_admin' => false]);

        // Filter platform_admin
        $resAdmin = $this->actingAs($actingAdmin)->get('/platform/users?type=platform_admin');
        $resAdmin->assertOk();
        $resAdmin->assertSee('Platform Admin Target');
        $resAdmin->assertDontSee('Pengusaha Sejati');
        $resAdmin->assertDontSee('User Lepas');

        // Filter business_user
        $resBiz = $this->actingAs($actingAdmin)->get('/platform/users?type=business_user');
        $resBiz->assertOk();
        $resBiz->assertSee('Pengusaha Sejati');
        $resBiz->assertDontSee('Platform Admin Target');
        $resBiz->assertDontSee('User Lepas');

        // Filter unconnected
        $resUnconnected = $this->actingAs($actingAdmin)->get('/platform/users?type=unconnected');
        $resUnconnected->assertOk();
        $resUnconnected->assertSee('User Lepas');
        $resUnconnected->assertDontSee('Pengusaha Sejati');
        $resUnconnected->assertDontSee('Platform Admin Target');
    }

    /**
     * H. Filter by email verification status: verified vs unverified.
     */
    public function test_filter_by_verification_status(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $verifiedUser = User::factory()->create([
            'name' => 'Akun Terverifikasi',
            'email_verified_at' => now(),
        ]);
        $unverifiedUser = User::factory()->unverified()->create([
            'name' => 'Akun Belum Verifikasi',
        ]);

        // Filter verified
        $resVerified = $this->actingAs($admin)->get('/platform/users?verification=verified');
        $resVerified->assertOk();
        $resVerified->assertSee('Akun Terverifikasi');
        $resVerified->assertDontSee('Akun Belum Verifikasi');

        // Filter unverified
        $resUnverified = $this->actingAs($admin)->get('/platform/users?verification=unverified');
        $resUnverified->assertOk();
        $resUnverified->assertSee('Akun Belum Verifikasi');
        $resUnverified->assertDontSee('Akun Terverifikasi');
    }

    /**
     * I. Pagination > 25 users with preserved query strings.
     */
    public function test_pagination_and_query_string_retention(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        User::factory()->count(30)->create();

        $response = $this->actingAs($admin)->get('/platform/users?page=1&type=unconnected');

        $response->assertOk();
        $response->assertSee('type=unconnected');
    }

    /**
     * J. User detail view displays all required fields:
     * name, email, verification, platform admin flag, business memberships, roles, business status.
     */
    public function test_user_detail_displays_full_profile_and_business_memberships(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $targetUser = User::factory()->create([
            'name' => 'Sawal Target User',
            'email' => 'sawal.target@example.com',
            'email_verified_at' => now(),
            'is_platform_admin' => false,
        ]);

        $businessA = Business::factory()->create([
            'name' => 'Toko Kelontong Sawal',
            'slug' => 'toko-kelontong-sawal',
            'status' => 'active',
        ]);
        $businessA->users()->attach($targetUser->id, ['role' => Business::ROLE_OWNER]);

        $businessB = Business::factory()->create([
            'name' => 'Apotek Sehat Sejahtera',
            'slug' => 'apotek-sehat-sejahtera',
            'status' => 'inactive',
        ]);
        $businessB->users()->attach($targetUser->id, ['role' => Business::ROLE_CASHIER]);

        $response = $this->actingAs($admin)->get("/platform/users/{$targetUser->id}");

        $response->assertOk();
        $response->assertSee('Detail Pengguna: Sawal Target User');
        $response->assertSee('Sawal Target User');
        $response->assertSee('sawal.target@example.com');
        $response->assertSee('#'.$targetUser->id);
        $response->assertSee('Terverifikasi');
        $response->assertSee('User Bisnis');
        $response->assertSee('Toko Kelontong Sawal');
        $response->assertSee('Pemilik');
        $response->assertSee('Apotek Sehat Sejahtera');
        $response->assertSee('Kasir');
        $response->assertSee('Aktif');
        $response->assertSee('Nonaktif');
    }

    /**
     * K. User without business renders 200 cleanly on detail view.
     */
    public function test_user_without_business_renders_cleanly(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $soloUser = User::factory()->create([
            'name' => 'User Mandiri',
            'email' => 'mandiri@test.local',
            'is_platform_admin' => false,
        ]);

        $response = $this->actingAs($admin)->get("/platform/users/{$soloUser->id}");

        $response->assertOk();
        $response->assertSee('User Mandiri');
        $response->assertSee('Belum Terhubung');
        $response->assertSee('Pengguna ini belum terhubung ke entitas bisnis mana pun.');
    }

    /**
     * L. Platform Admin without business renders 200 cleanly on detail view.
     */
    public function test_platform_admin_without_business_renders_cleanly(): void
    {
        $admin = User::factory()->platformAdmin()->create([
            'name' => 'Global Operator Admin',
            'email' => 'operator@nexamedia.test',
        ]);

        $response = $this->actingAs($admin)->get("/platform/users/{$admin->id}");

        $response->assertOk();
        $response->assertSee('Global Operator Admin');
        $response->assertSee('Platform Admin');
        $response->assertSee('Operator Global');
    }

    /**
     * Non-admin users cannot access user detail route.
     */
    public function test_non_admin_cannot_access_user_detail(): void
    {
        $regularUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($regularUser)->get("/platform/users/{$targetUser->id}")->assertForbidden();
    }
}
