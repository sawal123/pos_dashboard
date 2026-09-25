<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * DASH-14 — persisted business type.
 *
 * Covers the migration, the canonical/legacy/unknown contract, the owner-only
 * setup flow, tenant isolation and the guarantee that changing the type never
 * mutates historical or commercial data.
 */
class BusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function businessWithRole(string $role, ?string $type = null): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        if ($type !== null) {
            $business->forceFill(['business_type' => $type])->save();
        }

        $user->businesses()->attach($business->id, ['role' => $role]);

        return [$user, $business];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submitType(User $user, Business $business, array $payload): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.business-type.update'), $payload);
    }

    // =========================================================================
    // 1-4. Migration & NULL contract
    // =========================================================================

    public function test_migration_adds_the_business_type_column(): void
    {
        $this->assertTrue(Schema::hasColumn('businesses', 'business_type'));
    }

    public function test_migration_is_compatible_with_existing_businesses(): void
    {
        // A business row inserted without the new column must keep working and
        // simply resolve to "unknown".
        DB::table('businesses')->insert([
            'name' => 'Legacy Business',
            'slug' => 'legacy-business',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacy = Business::query()->where('slug', 'legacy-business')->firstOrFail();

        $this->assertNull($legacy->business_type);
        $this->assertNull($legacy->normalizedBusinessType());
        $this->assertSame('Belum ditentukan', $legacy->businessTypeLabel());
    }

    public function test_existing_business_keeps_a_null_type(): void
    {
        $business = Business::factory()->create();

        $this->assertNull($business->fresh()->business_type);
        $this->assertNull($business->fresh()->normalizedBusinessType());
    }

    public function test_there_is_no_hidden_cafe_default(): void
    {
        $business = Business::factory()->create();

        $this->assertNull($business->business_type);
        $this->assertNotSame(BusinessType::Cafe->value, $business->normalizedBusinessType());

        // The factory's explicit states are the only way to get a type.
        $this->assertSame('cafe', Business::factory()->cafe()->create()->normalizedBusinessType());
        $this->assertNull(Business::factory()->create()->normalizedBusinessType());
    }

    // =========================================================================
    // 5-7. Canonical values, legacy normalization, unknown state
    // =========================================================================

    public function test_the_three_canonical_types_are_the_allowlist(): void
    {
        $this->assertSame(['cafe', 'laundry', 'grosir'], BusinessType::values());

        $labels = collect(BusinessType::options())->pluck('label', 'value')->all();
        $this->assertSame([
            'cafe' => 'Cafe / UMKM',
            'laundry' => 'Laundry',
            'grosir' => 'Grosir / Toko Kelontong',
        ], $labels);
    }

    public function test_legacy_explicit_values_are_normalized(): void
    {
        $this->assertSame(BusinessType::Cafe, BusinessType::tryFromInput('restoran'));
        $this->assertSame(BusinessType::Cafe, BusinessType::tryFromInput('Restaurant'));
        $this->assertSame(BusinessType::Cafe, BusinessType::tryFromInput('cafe / umkm'));
        $this->assertSame(BusinessType::Cafe, BusinessType::tryFromInput('UMKM'));
        $this->assertSame(BusinessType::Grosir, BusinessType::tryFromInput('retail'));
        $this->assertSame(BusinessType::Grosir, BusinessType::tryFromInput('toko kelontong'));
        $this->assertSame(BusinessType::Laundry, BusinessType::tryFromInput('Laundry'));
    }

    public function test_empty_or_unknown_input_is_never_guessed_as_cafe(): void
    {
        foreach ([null, '', '   ', 'salon', 'bengkel', 'cafe-umkm'] as $input) {
            $this->assertNull(BusinessType::tryFromInput($input), "Input [{$input}] must stay unknown.");
        }
    }

    public function test_legacy_stored_values_are_normalized_on_read(): void
    {
        [, $legacyCafe] = $this->businessWithRole('owner', 'restoran');
        [, $legacyGrosir] = $this->businessWithRole('owner', 'retail');

        $this->assertSame('cafe', $legacyCafe->normalizedBusinessType());
        $this->assertSame('Cafe / UMKM', $legacyCafe->businessTypeLabel());
        $this->assertSame('grosir', $legacyGrosir->normalizedBusinessType());
        $this->assertSame('Grosir / Toko Kelontong', $legacyGrosir->businessTypeLabel());

        // Raw stored value is untouched (normalization happens on read only).
        $this->assertSame('restoran', $legacyCafe->fresh()->business_type);
    }

    // =========================================================================
    // 8, 9. Validation & owner happy path
    // =========================================================================

    public function test_owner_can_set_the_business_type_when_it_is_unset(): void
    {
        [$owner, $business] = $this->businessWithRole('owner');

        $response = $this->submitType($owner, $business, ['business_type' => 'laundry']);

        $response->assertRedirect(route('business-settings.edit'));
        $response->assertSessionHasNoErrors();
        $this->assertSame('laundry', $business->fresh()->business_type);
    }

    public function test_owner_can_change_an_existing_type_after_confirming(): void
    {
        [$owner, $business] = $this->businessWithRole('owner', 'cafe');

        // Without the confirmation checkbox the change is rejected.
        $blocked = $this->submitType($owner, $business, ['business_type' => 'laundry']);
        $blocked->assertSessionHasErrors('confirm_change');
        $this->assertSame('cafe', $business->fresh()->business_type);

        // With confirmation it succeeds.
        $allowed = $this->submitType($owner, $business, [
            'business_type' => 'laundry',
            'confirm_change' => '1',
        ]);
        $allowed->assertSessionHasNoErrors();
        $this->assertSame('laundry', $business->fresh()->business_type);
    }

    public function test_arbitrary_or_non_canonical_types_are_rejected(): void
    {
        [$owner, $business] = $this->businessWithRole('owner', 'cafe');

        foreach (['salon', 'restoran', 'Cafe', 'grosir / toko kelontong'] as $value) {
            $response = $this->submitType($owner, $business, [
                'business_type' => $value,
                'confirm_change' => '1',
            ]);

            $response->assertSessionHasErrors('business_type');
            $this->assertSame('cafe', $business->fresh()->business_type);
        }

        // Missing value is rejected too.
        $this->submitType($owner, $business, ['confirm_change' => '1'])
            ->assertSessionHasErrors('business_type');
    }

    // =========================================================================
    // 10-12. Role authorization on the setup flow
    // =========================================================================

    public function test_only_owners_can_read_the_business_settings_page(): void
    {
        [$owner, $business] = $this->businessWithRole('owner', 'cafe');

        $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('business-settings.edit'))
            ->assertOk()
            ->assertSee('Cafe / UMKM')
            ->assertSee('Laundry')
            ->assertSee('Grosir / Toko Kelontong')
            ->assertSee('Simpan Tipe Bisnis');

        foreach (['member', 'cashier', 'supervisor'] as $role) {
            [$actor] = $this->businessWithRole($role, 'cafe');

            // Actor needs access to the same business to be a fair test.
            $actor->businesses()->attach($business->id, ['role' => $role]);

            $this->actingAs($actor)
                ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
                ->get(route('business-settings.edit'))
                ->assertForbidden();
        }
    }

    public function test_member_and_cashier_and_unknown_roles_cannot_change_the_type(): void
    {
        foreach (['member', 'cashier', 'supervisor'] as $role) {
            [$actor, $business] = $this->businessWithRole($role, 'cafe');

            $response = $this->submitType($actor, $business, [
                'business_type' => 'laundry',
                'confirm_change' => '1',
            ]);

            $response->assertForbidden();
            $this->assertSame('cafe', $business->fresh()->business_type);
        }
    }

    // =========================================================================
    // 13, 14. Tenant isolation
    // =========================================================================

    public function test_a_user_without_an_active_business_cannot_change_any_type(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $target = Business::factory()->cafe()->create();

        // No membership at all: no active business, so nothing may be changed.
        $this->actingAs($stranger)
            ->patch(route('business-settings.business-type.update'), [
                'business_type' => 'laundry',
                'confirm_change' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('business-settings.edit'))
            ->assertForbidden();

        $this->assertSame('cafe', $target->fresh()->business_type);
    }

    public function test_a_foreign_owner_only_changes_their_own_business(): void
    {
        [$foreignOwner, $foreignBusiness] = $this->businessWithRole('owner', 'cafe');
        [, $target] = $this->businessWithRole('owner', 'grosir');

        // The foreign owner's active business is their own, so the write lands
        // there — the unrelated tenant is never touched.
        $response = $this->actingAs($foreignOwner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $foreignBusiness->id])
            ->patch(route('business-settings.business-type.update'), [
                'business_type' => 'laundry',
                'business_id' => $target->id,
                'confirm_change' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('laundry', $foreignBusiness->fresh()->business_type);
        $this->assertSame('grosir', $target->fresh()->business_type);
    }

    public function test_forged_business_id_is_ignored_and_role_follows_the_active_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $ownedBusiness = Business::factory()->create(['business_type' => 'cafe']);
        $memberBusiness = Business::factory()->create(['business_type' => 'grosir']);
        $user->businesses()->attach($ownedBusiness->id, ['role' => 'owner']);
        $user->businesses()->attach($memberBusiness->id, ['role' => 'member']);

        // Active business = member one; forging business_id cannot elevate.
        $blocked = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $memberBusiness->id])
            ->patch(route('business-settings.business-type.update'), [
                'business_type' => 'laundry',
                'business_id' => $ownedBusiness->id,
                'confirm_change' => '1',
            ]);

        $blocked->assertForbidden();
        $this->assertSame('cafe', $ownedBusiness->fresh()->business_type);
        $this->assertSame('grosir', $memberBusiness->fresh()->business_type);

        // Active business = owned one; only it is updated, even when a foreign
        // business_id is supplied.
        $allowed = $this->actingAs($user)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $ownedBusiness->id])
            ->patch(route('business-settings.business-type.update'), [
                'business_type' => 'laundry',
                'business_id' => $memberBusiness->id,
                'confirm_change' => '1',
            ]);

        $allowed->assertSessionHasNoErrors();
        $this->assertSame('laundry', $ownedBusiness->fresh()->business_type);
        $this->assertSame('grosir', $memberBusiness->fresh()->business_type);
    }

    public function test_business_type_is_independent_per_business(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $cafe = Business::factory()->cafe()->create();
        $laundry = Business::factory()->laundry()->create();
        $unknown = Business::factory()->create();
        $user->businesses()->attach($cafe->id, ['role' => 'owner']);
        $user->businesses()->attach($laundry->id, ['role' => 'owner']);
        $user->businesses()->attach($unknown->id, ['role' => 'owner']);

        $this->assertSame('cafe', $cafe->fresh()->normalizedBusinessType());
        $this->assertSame('laundry', $laundry->fresh()->normalizedBusinessType());
        $this->assertNull($unknown->fresh()->normalizedBusinessType());
    }

    // =========================================================================
    // 19, 20. Historical / commercial data is never touched
    // =========================================================================

    public function test_changing_the_type_does_not_alter_data_or_subscription(): void
    {
        [$owner, $business] = $this->businessWithRole('owner', 'cafe');
        $business->update(['name' => 'Toko Uji', 'status' => 'active']);
        $product = Product::factory()->create(['business_id' => $business->id, 'name' => 'Produk Uji']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $before = [
            'products' => DB::table('products')->where('business_id', $business->id)->count(),
            'subscriptions' => DB::table('subscriptions')->where('business_id', $business->id)->count(),
            'name' => $business->fresh()->name,
            'status' => $business->fresh()->status,
            'subscription_plan' => $business->fresh()->subscription?->plan,
        ];

        $this->submitType($owner, $business, [
            'business_type' => 'laundry',
            'confirm_change' => '1',
        ])->assertSessionHasNoErrors();

        $business->refresh();

        $this->assertSame('laundry', $business->business_type);
        $this->assertSame($before['products'], DB::table('products')->where('business_id', $business->id)->count());
        $this->assertSame($before['subscriptions'], DB::table('subscriptions')->where('business_id', $business->id)->count());
        $this->assertSame($before['name'], $business->name);
        $this->assertSame($before['status'], $business->status);
        $this->assertSame($before['subscription_plan'], $business->subscription?->plan);
        $this->assertSame('Produk Uji', $product->fresh()->name);
        $this->assertTrue($business->hasCloudAccess());
    }

    // =========================================================================
    // Guests / unverified
    // =========================================================================

    public function test_guest_and_unverified_users_are_denied_the_setup_flow(): void
    {
        $this->get(route('business-settings.edit'))->assertRedirect(route('login'));
        $this->patch(route('business-settings.business-type.update'), ['business_type' => 'cafe'])
            ->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->get(route('business-settings.edit'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)
            ->patch(route('business-settings.business-type.update'), ['business_type' => 'cafe'])
            ->assertRedirect(route('verification.notice'));
    }
}
