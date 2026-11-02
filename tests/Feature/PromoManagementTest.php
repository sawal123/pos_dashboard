<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_owner_can_create_a_promo(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload())
            ->assertRedirect(route('promos.index'));

        $promo = Promo::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('Diskon Akhir Pekan', $promo->name);
        $this->assertSame(1000, (int) $promo->discount_value);
    }

    public function test_02_member_can_view_but_not_mutate(): void
    {
        [$business, $member] = $this->actingAsRole('member');

        $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('promos.index'))
            ->assertOk();

        $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_03_cashier_cannot_open_promos(): void
    {
        [$business, $cashier] = $this->actingAsRole('cashier');

        $this->actingAs($cashier)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('promos.index'))
            ->assertForbidden();
    }

    public function test_04_percent_above_one_hundred_is_rejected(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload(['discount_value' => 10001]))
            ->assertSessionHasErrors('discount_value');

        $this->assertSame(0, Promo::query()->count());
    }

    public function test_05_end_before_start_is_rejected(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload([
                'starts_at' => '2026-10-10',
                'ends_at' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_06_code_is_unique_per_business(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload(['code' => 'WEEKEND']));

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload(['code' => 'WEEKEND']))
            ->assertSessionHasErrors('code');
    }

    public function test_07_foreign_tenant_promo_returns_404(): void
    {
        $foreignBusiness = Business::factory()->create();
        $foreignPromo = Promo::create([
            'business_id' => $foreignBusiness->id,
            'name' => 'Promo Tenant Lain',
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'scope' => 'all',
            'status' => 'draft',
        ]);

        [$business, $owner] = $this->actingAsRole('owner');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->patch(route('promos.update', ['promoId' => $foreignPromo->id]), $this->payload())
            ->assertNotFound();
    }

    public function test_08_conflicting_active_promo_is_rejected(): void
    {
        [$business, $owner] = $this->actingAsRole('owner');

        Promo::create([
            'business_id' => $business->id,
            'name' => 'Promo Lama',
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'scope' => 'all',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->post(route('promos.store'), $this->payload(['status' => 'active']))
            ->assertSessionHasErrors('name');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Diskon Akhir Pekan',
            'code' => 'WEEKEND',
            'discount_type' => 'percent',
            'discount_value' => 1000,
            'scope' => 'all',
            'min_purchase' => 0,
            'status' => 'draft',
        ], $overrides);
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function actingAsRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();

        $business->users()->attach($user->id, ['role' => $role]);

        if (! Subscription::query()->where('business_id', $business->id)->exists()) {
            Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        }

        $this->actingAs($user)->withSession(['dashboard.current_business_id' => $business->id]);

        return [$business, $user];
    }
}
