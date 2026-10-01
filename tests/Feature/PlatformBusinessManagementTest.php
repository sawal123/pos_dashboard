<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformBusinessManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A. Platform Admin berhasil membuka halaman index /platform/businesses dengan status 200.
     */
    public function test_platform_admin_can_access_business_index(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin)->get('/platform/businesses');

        $response->assertOk();
        $response->assertSee('Manajemen Bisnis / Merchant');
        $response->assertSee('Total:');
    }

    /**
     * B. Owner biasa ditolak mengakses /platform/businesses dengan status 403.
     */
    public function test_business_owner_is_forbidden_from_business_index(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $response = $this->actingAs($owner)->get('/platform/businesses');

        $response->assertForbidden();
    }

    /**
     * C. Regular user ditolak dari /platform/businesses dengan status 403.
     */
    public function test_regular_user_is_forbidden_from_business_index(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)->get('/platform/businesses')->assertForbidden();
    }

    /**
     * Guest / unauthenticated diarahkan ke halaman login.
     */
    public function test_guest_is_redirected_to_login_page(): void
    {
        $this->get('/platform/businesses')->assertRedirect(route('login'));
    }

    /**
     * D. Index menampilkan data nyata bisnis dari database.
     */
    public function test_business_index_lists_real_database_records(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create([
            'name' => 'Kedai Kopi Harapan',
            'slug' => 'kedai-kopi-harapan',
            'status' => 'active',
        ]);

        $owner = User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@kopi.com']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Outlet Utama']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $response = $this->actingAs($platformAdmin)->get('/platform/businesses');

        $response->assertOk();
        $response->assertSee('Kedai Kopi Harapan');
        $response->assertSee('kedai-kopi-harapan');
        $response->assertSee('Siti Aminah');
        $response->assertSee('siti@kopi.com');
        $response->assertSee('Cloud');
        $response->assertSee('Aktif');
    }

    /**
     * E. Search berdasarkan nama bisnis, slug, atau nama/email owner.
     */
    public function test_search_filters_businesses_correctly(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $kopi = Business::factory()->create(['name' => 'Kopi Nusantara', 'slug' => 'kopi-nusantara']);
        $laundry = Business::factory()->create(['name' => 'Laundry Bersih', 'slug' => 'laundry-bersih']);

        // Search "kopi"
        $response = $this->actingAs($platformAdmin)->get('/platform/businesses?q=kopi');
        $response->assertOk();
        $response->assertSee('Kopi Nusantara');
        $response->assertDontSee('Laundry Bersih');

        // Search "laundry"
        $response2 = $this->actingAs($platformAdmin)->get('/platform/businesses?q=laundry');
        $response2->assertOk();
        $response2->assertDontSee('Kopi Nusantara');
        $response2->assertSee('Laundry Bersih');
    }

    /**
     * Search juga dapat menemukan bisnis melalui nama atau email owner.
     */
    public function test_search_by_owner_name_or_email(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $bizA = Business::factory()->create(['name' => 'Toko Kelontong Berkah']);
        $ownerA = User::factory()->create(['name' => 'Ahmad Dahlan', 'email' => 'ahmad@berkah.com']);
        $bizA->users()->attach($ownerA->id, ['role' => Business::ROLE_OWNER]);

        $bizB = Business::factory()->create(['name' => 'Bengkel Motor Jaya']);
        $ownerB = User::factory()->create(['name' => 'Bambang Sudirman', 'email' => 'bambang@jaya.com']);
        $bizB->users()->attach($ownerB->id, ['role' => Business::ROLE_OWNER]);

        // Search nama owner "Ahmad"
        $response = $this->actingAs($platformAdmin)->get('/platform/businesses?q=Ahmad');
        $response->assertOk();
        $response->assertSee('Toko Kelontong Berkah');
        $response->assertDontSee('Bengkel Motor Jaya');

        // Search email owner "bambang@"
        $response2 = $this->actingAs($platformAdmin)->get('/platform/businesses?q=bambang@jaya.com');
        $response2->assertOk();
        $response2->assertDontSee('Toko Kelontong Berkah');
        $response2->assertSee('Bengkel Motor Jaya');
    }

    /**
     * F. Filter status (active / inactive).
     */
    public function test_status_filter_filters_businesses(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $activeBiz = Business::factory()->create(['name' => 'Bisnis Sangat Aktif', 'status' => 'active']);
        $inactiveBiz = Business::factory()->create(['name' => 'Bisnis Sedang Tutup', 'status' => 'inactive']);

        // Filter status=active
        $responseActive = $this->actingAs($platformAdmin)->get('/platform/businesses?status=active');
        $responseActive->assertOk();
        $responseActive->assertSee('Bisnis Sangat Aktif');
        $responseActive->assertDontSee('Bisnis Sedang Tutup');

        // Filter status=inactive
        $responseInactive = $this->actingAs($platformAdmin)->get('/platform/businesses?status=inactive');
        $responseInactive->assertOk();
        $responseInactive->assertDontSee('Bisnis Sangat Aktif');
        $responseInactive->assertSee('Bisnis Sedang Tutup');
    }

    /**
     * Filter plan (free / cloud).
     */
    public function test_plan_filter_filters_businesses(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $cloudBiz = Business::factory()->create(['name' => 'Bisnis Cloud VIP']);
        Subscription::factory()->cloud()->create(['business_id' => $cloudBiz->id]);

        $freeBiz = Business::factory()->create(['name' => 'Bisnis Free Basic']);
        Subscription::factory()->free()->create(['business_id' => $freeBiz->id]);

        $responseCloud = $this->actingAs($platformAdmin)->get('/platform/businesses?plan=cloud');
        $responseCloud->assertOk();
        $responseCloud->assertSee('Bisnis Cloud VIP');
        $responseCloud->assertDontSee('Bisnis Free Basic');

        $responseFree = $this->actingAs($platformAdmin)->get('/platform/businesses?plan=free');
        $responseFree->assertOk();
        $responseFree->assertDontSee('Bisnis Cloud VIP');
        $responseFree->assertSee('Bisnis Free Basic');
    }

    /**
     * G. Pagination bekerja untuk lebih dari 25 bisnis dan query filter dipertahankan.
     */
    public function test_pagination_works_and_preserves_query_string(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        // Buat 30 businesses aktif
        Business::factory()->count(30)->create(['status' => 'active']);

        $response = $this->actingAs($platformAdmin)->get('/platform/businesses?status=active&page=2');

        $response->assertOk();
        // Cek link pagination membawa query status=active
        $response->assertSee('status=active');
    }

    /**
     * H. Detail business menampilkan informasi lengkap identitas, owner, outlet, device, subscription.
     */
    public function test_platform_admin_can_view_business_detail(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create([
            'name' => 'PT Kuliner Jaya Abadi',
            'slug' => 'kuliner-jaya-abadi',
            'status' => 'active',
        ]);

        $owner = User::factory()->create(['name' => 'Dewi Sartika', 'email' => 'dewi@kuliner.com']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id, 'name' => 'Cabang Tebet']);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'Tablet Kasir Tebet',
            'identifier' => 'TAB-TEBET-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        SyncRequest::create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ]);

        Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/businesses/{$business->id}");

        $response->assertOk();
        $response->assertSee('PT Kuliner Jaya Abadi');
        $response->assertSee('kuliner-jaya-abadi');
        $response->assertSee('Dewi Sartika');
        $response->assertSee('dewi@kuliner.com');
        $response->assertSee('Cabang Tebet');
        $response->assertSee('Cloud');
        $response->assertSee('Suspend Bisnis');
    }

    /**
     * I. Missing relations: Bisnis tanpa owner, device, subscription, dan sync requests tetap render 200.
     */
    public function test_business_detail_renders_cleanly_with_missing_relations(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $bareBusiness = Business::factory()->create([
            'name' => 'Bisnis Baru Kosong',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($platformAdmin)->get("/platform/businesses/{$bareBusiness->id}");

        $response->assertOk();
        $response->assertSee('Bisnis Baru Kosong');
        $response->assertSee('Belum ada akun pemilik yang terhubung');
        $response->assertSee('Belum ada outlet terdaftar');
        $response->assertSee('Aktifkan Bisnis');
    }

    /**
     * J. Suspend & Reactivate: Platform Admin dapat mengubah status active <-> inactive via PATCH.
     */
    public function test_platform_admin_can_toggle_business_status(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $business = Business::factory()->create(['status' => 'active']);

        // 1. Suspend: active -> inactive
        $responseSuspend = $this->actingAs($platformAdmin)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'inactive',
        ]);

        $responseSuspend->assertRedirect();
        $this->assertSame('inactive', $business->fresh()->status);

        // 2. Reactivate: inactive -> active
        $responseReactivate = $this->actingAs($platformAdmin)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'active',
        ]);

        $responseReactivate->assertRedirect();
        $this->assertSame('active', $business->fresh()->status);
    }

    /**
     * Keamanan status mutation: Non-admin ditolak saat mencoba mengubah status bisnis.
     */
    public function test_non_admin_cannot_mutate_business_status(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create(['status' => 'active']);
        $business->users()->attach($owner->id, ['role' => Business::ROLE_OWNER]);

        $response = $this->actingAs($owner)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
        $this->assertSame('active', $business->fresh()->status);
    }

    /**
     * Validasi status mutation: Status tidak valid ditolak dengan validasi error (422 / redirect with error).
     */
    public function test_invalid_status_value_is_rejected(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);

        $response = $this->actingAs($platformAdmin)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'destroyed',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('active', $business->fresh()->status);
    }

    /**
     * Method safety: Status update tidak dapat dipicu melalui metode GET.
     */
    public function test_status_cannot_be_mutated_via_get(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);

        $response = $this->actingAs($platformAdmin)->get("/platform/businesses/{$business->id}/status?status=inactive");

        $response->assertMethodNotAllowed();
        $this->assertSame('active', $business->fresh()->status);
    }

    /**
     * Isolasi: Perubahan status business tidak memiliki side effect pada status subscription atau devices.
     */
    public function test_business_status_mutation_does_not_mutate_subscription_or_devices(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['status' => 'active']);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS 1',
            'identifier' => 'POS-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $subscription = Subscription::factory()->cloud()->create([
            'business_id' => $business->id,
            'status' => 'active',
        ]);

        $this->actingAs($platformAdmin)->patch("/platform/businesses/{$business->id}/status", [
            'status' => 'inactive',
        ]);

        $this->assertSame('inactive', $business->fresh()->status);
        // Subscription dan Device tetap tidak berubah (tidak ada side effect)
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('active', $device->fresh()->status);
    }
}
