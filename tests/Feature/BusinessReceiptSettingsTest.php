<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Dashboard\DashboardBusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessReceiptSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_receipt_columns_to_businesses(): void
    {
        $this->assertTrue(Schema::hasColumn('businesses', 'receipt_logo'));
        $this->assertTrue(Schema::hasColumn('businesses', 'receipt_address'));
        $this->assertTrue(Schema::hasColumn('businesses', 'receipt_footer'));
    }

    public function test_owner_can_view_business_settings_with_receipt_fields(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->get(route('business-settings.edit'));

        $response->assertOk();
        $response->assertSee('Pengaturan Struk &amp; Logo', false);
        $response->assertSee('name="receipt_logo"', false);
        $response->assertSee('name="receipt_address"', false);
        $response->assertSee('name="receipt_footer"', false);
    }

    public function test_owner_can_update_receipt_settings_with_logo_upload(): void
    {
        Storage::fake('public');
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('business-settings.receipt.update'), [
                'receipt_logo' => $file,
                'receipt_address' => 'Jl. Mawar No. 12',
                'receipt_footer' => 'Terima kasih telah berkunjung.',
            ]);

        $response->assertRedirect(route('business-settings.edit'));
        $response->assertSessionHas('status', 'Pengaturan struk berhasil disimpan.');

        $business->refresh();
        $this->assertNotNull($business->receipt_logo);
        $this->assertSame('Jl. Mawar No. 12', $business->receipt_address);
        $this->assertSame('Terima kasih telah berkunjung.', $business->receipt_footer);
        Storage::disk('public')->assertExists($business->receipt_logo);
    }

    public function test_owner_can_remove_receipt_logo(): void
    {
        Storage::fake('public');
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);

        $business->update(['receipt_logo' => 'receipt_logos/old.png']);

        $response = $this->actingAs($owner)
            ->withSession([DashboardBusinessContext::SESSION_KEY => $business->id])
            ->post(route('business-settings.receipt.update'), [
                'remove_logo' => '1',
            ]);

        $response->assertRedirect(route('business-settings.edit'));
        $business->refresh();
        $this->assertNull($business->receipt_logo);
    }

    public function test_mobile_api_can_sync_receipt_settings(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);
        Sanctum::actingAs($owner, ['mobile']);

        $response = $this->patchJson("/api/mobile/businesses/{$business->id}/receipt-settings", [
            'logo' => [
                'width' => 16,
                'height' => 8,
                'data' => base64_encode(str_repeat("\xFF", 16)),
            ],
            'address' => 'Jl. Sudirman 45',
            'footer' => 'Barang yang dibeli tidak dapat ditukar',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.receipt_settings.address', 'Jl. Sudirman 45');

        $business->refresh();
        $this->assertSame('Jl. Sudirman 45', $business->receipt_address);
        $this->assertNotNull($business->receipt_logo);
        $this->assertNotNull($business->receiptLogoUrl());
    }

    public function test_mobile_context_includes_receipt_settings(): void
    {
        [$owner, $business] = $this->setupBusinessWithRole(Business::ROLE_OWNER);
        $business->update([
            'receipt_address' => 'Jl. Ahmad Yani No. 9',
            'receipt_footer' => 'Semoga hari Anda menyenangkan',
        ]);
        Sanctum::actingAs($owner, ['mobile']);

        $response = $this->getJson('/api/mobile/context');

        $response->assertOk();
        $response->assertJsonPath('data.businesses.0.receipt_settings.address', 'Jl. Ahmad Yani No. 9');
        $response->assertJsonPath('data.businesses.0.receipt_settings.footer', 'Semoga hari Anda menyenangkan');
    }

    public function test_cashier_cannot_update_receipt_settings_via_api(): void
    {
        [$cashier, $business] = $this->setupBusinessWithRole(Business::ROLE_CASHIER);
        Sanctum::actingAs($cashier, ['mobile']);

        $response = $this->patchJson("/api/mobile/businesses/{$business->id}/receipt-settings", [
            'address' => 'Hacker Struk',
        ]);

        $response->assertForbidden();
    }

    private function setupBusinessWithRole(string $role): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $user->businesses()->attach($business->id, ['role' => $role]);

        return [$user, $business, $outlet];
    }
}
