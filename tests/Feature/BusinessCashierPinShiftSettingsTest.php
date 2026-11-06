<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessCashierPinShiftSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_pin_and_shift_columns_to_businesses(): void
    {
        $this->assertTrue(Schema::hasColumn('businesses', 'cashier_pin'));
        $this->assertTrue(Schema::hasColumn('businesses', 'default_opening_cash'));
        $this->assertTrue(Schema::hasColumn('businesses', 'auto_open_shift'));

        $business = Business::factory()->create();
        $this->assertSame('123456', $business->cashier_pin);
        $this->assertSame(0, $business->default_opening_cash);
        $this->assertTrue($business->auto_open_shift);
    }

    public function test_owner_can_view_pin_and_shift_form_in_business_settings(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('business-settings.edit'));

        $response->assertOk();
        $response->assertSee('Pengaturan PIN Kasir &amp; Shift', false);
        $response->assertSee('name="cashier_pin"', false);
        $response->assertSee('name="default_opening_cash"', false);
        $response->assertSee('name="auto_open_shift"', false);
    }

    public function test_owner_can_update_pin_and_shift_settings(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.cashier-pin-shift.update'), [
                'cashier_pin' => '654321',
                'default_opening_cash' => 50000,
                'auto_open_shift' => '1',
            ]);

        $response->assertRedirect(route('business-settings.edit'));
        $response->assertSessionHas('status', 'Pengaturan PIN kasir dan Shift berhasil disimpan.');

        $business->refresh();
        $this->assertSame('654321', $business->cashier_pin);
        $this->assertSame(50000, $business->default_opening_cash);
        $this->assertTrue($business->auto_open_shift);
    }

    public function test_pin_must_be_exactly_six_numeric_digits(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.cashier-pin-shift.update'), [
                'cashier_pin' => '1234', // Only 4 digits
                'default_opening_cash' => 10000,
                'auto_open_shift' => '1',
            ]);

        $response->assertSessionHasErrors('cashier_pin');

        $responseAlpha = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->patch(route('business-settings.cashier-pin-shift.update'), [
                'cashier_pin' => 'abcdef', // Non-numeric
                'default_opening_cash' => 10000,
                'auto_open_shift' => '1',
            ]);

        $responseAlpha->assertSessionHasErrors('cashier_pin');
    }

    public function test_mobile_context_returns_cashier_pin_and_shift_settings(): void
    {
        [$user, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);
        $business->update([
            'cashier_pin' => '987654',
            'default_opening_cash' => 100000,
            'auto_open_shift' => true,
        ]);

        Subscription::factory()->create([
            'business_id' => $business->id,
            'plan' => 'pro',
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Pusat',
        ]);

        $token = $user->createToken('mobile-test', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/context');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.cashier_pin', '987654');
        $response->assertJsonPath('data.businesses.0.shift_settings.auto_open', true);
        $response->assertJsonPath('data.businesses.0.shift_settings.default_opening_cash', 100000);
    }

    private function setupBusinessWithRole(string $role): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();

        $business->users()->attach($user->id, [
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $business];
    }
}
