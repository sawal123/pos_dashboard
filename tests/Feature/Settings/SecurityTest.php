<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Security;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);
    }

    public function test_security_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();

        $response->assertSee('Passkeys');
        $response->assertSee('No passkeys yet');
        $response->assertSee('Two-factor authentication');
        $response->assertSee('Enable 2FA');
    }

    public function test_security_settings_page_requires_password_confirmation_when_enabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_settings_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Update password')
            ->assertDontSee('Manage your passkeys for passwordless sign-in')
            ->assertDontSee('Add a passkey to sign in without a password')
            ->assertDontSee('Two-factor authentication');
    }

    public function test_two_factor_authentication_disabled_when_confirmation_abandoned_between_requests(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($user);

        $component = Livewire::test(Security::class);

        $component->assertSet('twoFactorEnabled', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(Security::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(Security::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasErrors(['current_password']);
    }

    public function test_security_settings_page_renders_flux_runtime_scripts_and_appearance(): void
    {
        $admin = User::factory()->platformAdmin()->withoutTwoFactor()->create();

        $response = $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();
        $response->assertSee('/flux/flux', false);
        $response->assertSee('applyAppearance', false);
        $response->assertSee('data-flux-modal', false);
    }

    public function test_platform_admin_can_complete_full_2fa_setup_flow_and_access_platform(): void
    {
        $admin = User::factory()->platformAdmin()->withoutTwoFactor()->create();

        // 1. Initial attempt to access /platform redirects to security.edit
        $this->actingAs($admin)
            ->get('/platform')
            ->assertRedirect(route('security.edit'));

        $this->actingAs($admin);

        // 2. Enable 2FA generates QR code and modal state
        $component = Livewire::test(Security::class)
            ->call('enable');

        $component->assertSet('showModal', true);
        $this->assertNotEmpty($component->get('qrCodeSvg'));
        $this->assertNotEmpty($component->get('manualSetupKey'));
        $this->assertNotNull($admin->fresh()->two_factor_secret);
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);

        // 3. Move to verification step
        $component->call('showVerificationIfNecessary')
            ->assertSet('showVerificationStep', true);

        // 4. Generate valid TOTP from user's secret
        $google2fa = app(Google2FA::class);
        $validOtp = $google2fa->getCurrentOtp(decrypt($admin->fresh()->two_factor_secret));

        // 5. Confirm with valid code
        $component->set('code', $validOtp)
            ->call('confirmTwoFactor')
            ->assertHasNoErrors()
            ->assertSet('twoFactorEnabled', true)
            ->assertSet('showModal', false);

        // 6. DB shows confirmed
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertNotNull($admin->two_factor_recovery_codes);

        // 7. Platform Admin can now reach /platform without redirect
        $this->actingAs($admin)
            ->get('/platform')
            ->assertOk();
    }

    public function test_2fa_confirmation_fails_with_invalid_code(): void
    {
        $admin = User::factory()->platformAdmin()->withoutTwoFactor()->create();

        $this->actingAs($admin);

        $component = Livewire::test(Security::class)
            ->call('enable')
            ->call('showVerificationIfNecessary')
            ->set('code', '000000')
            ->call('confirmTwoFactor');

        $component->assertHasErrors(['code']);
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);

        // Platform access remains blocked
        $this->actingAs($admin)
            ->get('/platform')
            ->assertRedirect(route('security.edit'));
    }
}
