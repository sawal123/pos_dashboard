<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASH-14 — dynamic navigation.
 *
 * The business type decides which menus are *relevant*; the DASH-10B2
 * permission matrix still decides *authorization*, and routes stay enforced
 * server-side. Every case therefore checks both layers.
 */
class BusinessTypeNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(?string $type, string $role = 'owner'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        if ($type !== null) {
            $business->forceFill(['business_type' => $type])->save();
        }

        $user->businesses()->attach($business->id, ['role' => $role]);

        return [$user, $business];
    }

    private function dashboardFor(User $user, Business $business)
    {
        return $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('dashboard'));
    }

    // =========================================================================
    // Per-type relevance
    // =========================================================================

    public function test_laundry_business_shows_the_laundry_menu(): void
    {
        [$owner, $business] = $this->makeUserWithBusiness('laundry');

        $response = $this->dashboardFor($owner, $business);

        $response->assertOk();
        $response->assertSee('Pesanan Laundry');
        $response->assertSee(route('laundry-orders.index'), false);
        $response->assertSee('data-business-context="laundry"', false);
        // Transaksi + Produk & Layanan stay available.
        $response->assertSee(route('transactions.index'), false);
        $response->assertSee(route('products.index'), false);
    }

    public function test_cafe_business_hides_the_laundry_menu_but_keeps_operational_menus(): void
    {
        [$owner, $business] = $this->makeUserWithBusiness('cafe');

        $response = $this->dashboardFor($owner, $business);

        $response->assertOk();
        $response->assertDontSee('Pesanan Laundry');
        $response->assertDontSee(route('laundry-orders.index'), false);
        $response->assertSee('data-business-context="cafe"', false);
        $response->assertSee(route('transactions.index'), false);
        $response->assertSee(route('products.index'), false);
        $response->assertSee('Operasional');
    }

    public function test_grosir_business_hides_the_laundry_menu_and_keeps_stock(): void
    {
        [$owner, $business] = $this->makeUserWithBusiness('grosir');

        $response = $this->dashboardFor($owner, $business);

        $response->assertOk();
        $response->assertDontSee('Pesanan Laundry');
        $response->assertDontSee(route('laundry-orders.index'), false);
        $response->assertSee('data-business-context="grosir"', false);
        $response->assertSee(route('transactions.index'), false);
        $response->assertSee(route('products.index'), false);
        $response->assertSee(route('stock.index'), false);
    }

    public function test_unknown_business_shows_safe_general_navigation_and_an_owner_prompt(): void
    {
        [$owner, $business] = $this->makeUserWithBusiness(null);

        $response = $this->dashboardFor($owner, $business);

        $response->assertOk();
        // Never guess Cafe: no laundry menu, and an explicit unknown marker.
        $response->assertDontSee('Pesanan Laundry');
        $response->assertSee('data-business-context="unknown"', false);
        // General navigation is not locked out.
        $response->assertSee('Operasional');
        $response->assertSee(route('transactions.index'), false);
        $response->assertSee(route('products.index'), false);
        // Owner gets a prompt + link to pick a type.
        $response->assertSee('Tipe bisnis belum ditentukan');
        $response->assertSee(route('business-settings.edit'), false);
    }

    public function test_member_does_not_see_the_owner_only_business_settings_menu(): void
    {
        [$member, $business] = $this->makeUserWithBusiness(null, 'member');

        $response = $this->dashboardFor($member, $business);

        $response->assertOk();
        $response->assertDontSee('Pengaturan Bisnis');
        $response->assertDontSee(route('business-settings.edit'), false);
        $response->assertDontSee('Tipe bisnis belum ditentukan');
    }

    // =========================================================================
    // Switching the active business
    // =========================================================================

    public function test_switching_business_updates_the_business_type_in_the_sidebar(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cafe = Business::factory()->cafe()->create();
        $laundry = Business::factory()->laundry()->create();
        $grosir = Business::factory()->grosir()->create();
        $user->businesses()->attach($cafe->id, ['role' => 'owner']);
        $user->businesses()->attach($laundry->id, ['role' => 'owner']);
        $user->businesses()->attach($grosir->id, ['role' => 'owner']);

        $cafeView = $this->dashboardFor($user, $cafe);
        $cafeView->assertDontSee('Pesanan Laundry');
        $cafeView->assertSee('data-business-context="cafe"', false);
        $cafeView->assertDontSee('data-business-context="laundry"', false);

        $laundryView = $this->dashboardFor($user, $laundry);
        $laundryView->assertSee('Pesanan Laundry');
        $laundryView->assertSee('data-business-context="laundry"', false);

        $grosirView = $this->dashboardFor($user, $grosir);
        $grosirView->assertDontSee('Pesanan Laundry');
        $grosirView->assertSee('data-business-context="grosir"', false);
    }

    public function test_switching_business_updates_the_role_and_permissions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $owned = Business::factory()->cafe()->create();
        $cashierBusiness = Business::factory()->laundry()->create();
        $user->businesses()->attach($owned->id, ['role' => 'owner']);
        $user->businesses()->attach($cashierBusiness->id, ['role' => 'cashier']);

        // Owner in the active (cafe) business: owner-only menus are present.
        $ownerView = $this->dashboardFor($user, $owned);
        $ownerView->assertSee(route('users.index'), false);
        $ownerView->assertSee(route('business-settings.edit'), false);
        $ownerView->assertSee(route('subscriptions.index'), false);

        // Cashier in the active (laundry) business: laundry menu appears by type,
        // but every owner-only menu disappears by permission.
        $cashierView = $this->dashboardFor($user, $cashierBusiness);
        $cashierView->assertSee('Pesanan Laundry');
        $cashierView->assertSee(route('laundry-orders.index'), false);
        $cashierView->assertDontSee(route('users.index'), false);
        $cashierView->assertDontSee(route('business-settings.edit'), false);
        $cashierView->assertDontSee(route('subscriptions.index'), false);
    }

    // =========================================================================
    // Type ≠ authorization
    // =========================================================================

    public function test_member_and_cashier_stay_permission_limited_on_a_laundry_business(): void
    {
        [$member, $business] = $this->makeUserWithBusiness('laundry', 'member');

        $memberView = $this->dashboardFor($member, $business);
        $memberView->assertSee('Pesanan Laundry');
        $memberView->assertSee(route('reports.index'), false);
        $memberView->assertDontSee(route('users.index'), false);
        $memberView->assertDontSee(route('business-settings.edit'), false);

        [$cashier, $cashierBusiness] = $this->makeUserWithBusiness('laundry', 'cashier');

        $cashierView = $this->dashboardFor($cashier, $cashierBusiness);
        $cashierView->assertSee('Pesanan Laundry');
        // Cashier has no reporting/admin permission regardless of business type.
        $cashierView->assertDontSee(route('reports.index'), false);
        $cashierView->assertDontSee(route('users.index'), false);
        $cashierView->assertDontSee(route('business-settings.edit'), false);
    }

    /**
     * Documented behaviour: menus hide by type, but a direct URL is governed by
     * the permission matrix — never by the business type alone.
     */
    public function test_direct_url_is_governed_by_permission_not_by_business_type(): void
    {
        // Cafe owner: menu hidden, page still reachable by permission.
        [$owner, $cafe] = $this->makeUserWithBusiness('cafe');

        $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $cafe->id])
            ->get(route('laundry-orders.index'))
            ->assertOk();

        // Cashier on a grosir business: same permission-based outcome.
        [$cashier, $grosir] = $this->makeUserWithBusiness('grosir', 'cashier');

        $this->actingAs($cashier)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $grosir->id])
            ->get(route('laundry-orders.index'))
            ->assertOk();

        // Unknown role is denied even on a laundry business (deny-by-default).
        [$unknown, $laundry] = $this->makeUserWithBusiness('laundry', 'supervisor');

        $this->actingAs($unknown)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $laundry->id])
            ->get(route('laundry-orders.index'))
            ->assertForbidden();

        $this->actingAs($unknown)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $laundry->id])
            ->get(route('business-settings.edit'))
            ->assertForbidden();
    }
}
