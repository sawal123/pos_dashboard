<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\MembershipAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASH-10B2 — dashboard role-based access control.
 *
 * Verifies the documented permission matrix for every dashboard route, that
 * unknown roles are denied by default, that direct URLs are checked (not just
 * the sidebar), and that a role is always evaluated against the *active*
 * business (never globally).
 */
class CashierRbacTest extends TestCase
{
    use RefreshDatabase;

    /** Pages every role with a contract may open. */
    private const OPERATIONAL_ROUTES = [
        'dashboard',
        'transactions.index',
        'products.index',
        'stock.index',
        'cash.index',
        'shifts.index',
        'customers.index',
        'laundry-orders.index',
    ];

    /** Reporting / reference pages: owner + member. */
    private const ANALYTICS_ROUTES = [
        'reports.index',
        'outlets.index',
    ];

    /** Administration pages: owner only. */
    private const ADMIN_ROUTES = [
        'users.index',
        'devices.index',
        'sync.index',
    ];

    public function test_owner_can_access_every_dashboard_route(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $this->actingAs($owner)->withSession(['dashboard.current_business_id' => $business->id]);

        $this->assertRoutesStatus([...self::OPERATIONAL_ROUTES, ...self::ANALYTICS_ROUTES, ...self::ADMIN_ROUTES], 200);
    }

    public function test_member_can_access_operational_and_reporting_but_not_admin(): void
    {
        $business = Business::factory()->create();
        $this->actingAsRole($business, 'member');

        $this->assertRoutesStatus(self::OPERATIONAL_ROUTES, 200);
        $this->assertRoutesStatus(self::ANALYTICS_ROUTES, 200);
        $this->assertRoutesStatus(self::ADMIN_ROUTES, 403);
    }

    public function test_cashier_can_access_only_limited_operational_pages(): void
    {
        $business = Business::factory()->create();
        $this->actingAsRole($business, 'cashier');

        $this->assertRoutesStatus(self::OPERATIONAL_ROUTES, 200);
        $this->assertRoutesStatus(self::ANALYTICS_ROUTES, 403);
        $this->assertRoutesStatus(self::ADMIN_ROUTES, 403);
    }

    public function test_unknown_role_is_denied_by_default(): void
    {
        $business = Business::factory()->create();
        $this->actingAsRole($business, 'supervisor');

        // Deny-by-default: an unmapped role has no permission at all, even for
        // pages a `member` would reach.
        $this->assertRoutesStatus([...self::OPERATIONAL_ROUTES, ...self::ANALYTICS_ROUTES, ...self::ADMIN_ROUTES], 403);
    }

    public function test_direct_url_is_denied_even_when_the_menu_is_hidden(): void
    {
        $business = Business::factory()->create();
        $this->actingAsRole($business, 'cashier');

        // The sidebar hides these, but the server must reject them too.
        $this->get(route('users.index'))->assertForbidden();
        $this->getJson(route('users.detail', 1))->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('devices.index'))->assertForbidden();
        $this->get(route('sync.index'))->assertForbidden();
        $this->get(route('outlets.index'))->assertForbidden();
    }

    public function test_role_is_evaluated_against_the_active_business_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cashierBusiness = Business::factory()->create();
        $ownedBusiness = Business::factory()->create();
        $user->businesses()->attach($cashierBusiness->id, ['role' => 'cashier']);
        $user->businesses()->attach($ownedBusiness->id, ['role' => 'owner']);

        // Cashier in the active business → reporting denied.
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $cashierBusiness->id])
            ->get(route('reports.index'))
            ->assertForbidden();

        // Owner in the active business → reporting allowed.
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $ownedBusiness->id])
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_forged_business_id_parameter_cannot_elevate_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cashierBusiness = Business::factory()->create();
        $ownedBusiness = Business::factory()->create();
        $user->businesses()->attach($cashierBusiness->id, ['role' => 'cashier']);
        $user->businesses()->attach($ownedBusiness->id, ['role' => 'owner']);

        // Active business is the cashier one; a forged business_id is ignored.
        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $cashierBusiness->id])
            ->get(route('users.index', ['business_id' => $ownedBusiness->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('business_user', [
            'business_id' => $cashierBusiness->id,
            'user_id' => $user->id,
            'role' => 'cashier',
        ]);
    }

    public function test_guest_and_unverified_users_are_denied(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get(route('reports.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_member_and_cashier_cannot_manage_invitations_members_or_roles(): void
    {
        foreach (['member', 'cashier'] as $role) {
            $business = Business::factory()->create();
            $actor = $this->actingAsRole($business, $role);
            $target = $this->attachMember($business, 'member', ['email' => $role.'-target@example.com']);

            $this->post(route('users.invitations.store'), ['email' => 'x@example.com', 'role' => 'member'])
                ->assertForbidden();
            $this->delete(route('users.members.destroy', $target->id))->assertForbidden();
            $this->patch(route('users.members.role.update', $target->id), ['role' => 'cashier'])
                ->assertForbidden();

            // Nothing changed.
            $this->assertDatabaseHas('business_user', [
                'business_id' => $business->id,
                'user_id' => $target->id,
                'role' => 'member',
            ]);
            $this->assertSame(0, BusinessInvitation::count());
            $this->assertSame(0, MembershipAuditLog::where('action', 'role_changed')->count());
        }
    }

    public function test_sidebar_mirrors_the_permission_matrix(): void
    {
        $business = Business::factory()->create();
        $this->actingAsRole($business, 'cashier');

        $cashier = $this->get(route('dashboard'));
        $cashier->assertOk();
        $cashier->assertSee(route('transactions.index'), false);
        $cashier->assertDontSee(route('reports.index'), false);
        $cashier->assertDontSee(route('outlets.index'), false);
        $cashier->assertDontSee(route('users.index'), false);
        $cashier->assertDontSee(route('devices.index'), false);
        $cashier->assertDontSee(route('sync.index'), false);

        $ownerBusiness = Business::factory()->create();
        $this->actingAsRole($ownerBusiness, 'owner');

        $owner = $this->get(route('dashboard'));
        $owner->assertOk();
        $owner->assertSee(route('reports.index'), false);
        $owner->assertSee(route('users.index'), false);
        $owner->assertSee(route('devices.index'), false);
        $owner->assertSee(route('sync.index'), false);
    }

    public function test_user_without_business_keeps_empty_state_but_no_member_admin_access(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Operational pages keep the "no business → empty state" contract.
        $this->actingAs($user)->get(route('transactions.index'))->assertOk();

        // The member-administration page still refuses.
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    /**
     * @param  list<string>  $routes
     */
    private function assertRoutesStatus(array $routes, int $expected): void
    {
        foreach ($routes as $route) {
            $this->assertSame(
                $expected,
                $this->get(route($route))->getStatusCode(),
                "Route [{$route}] expected HTTP {$expected}."
            );
        }
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeOwnerWithBusiness(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($owner->id, ['role' => 'owner']);
        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$owner, $business];
    }

    private function actingAsRole(Business $business, string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($user->id, ['role' => $role]);

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function attachMember(Business $business, string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $business->users()->attach($user->id, ['role' => $role]);

        return $user;
    }
}
