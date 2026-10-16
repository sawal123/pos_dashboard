<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));
    }

    public function test_two_factor_challenge_view_renders_pos_auth_ui_elements(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this->withSession(['login.id' => $user->id])
            ->get(route('two-factor.login'));

        $response->assertOk();
        $response->assertSee('Nexa', false);
        $response->assertSee('POS', false);
        $response->assertSee(__('Verifikasi Identitas Anda'), false);
        $response->assertSee(__('Kode Autentikasi (6 Digit)'), false);
        $response->assertSee('name="code"', false);
        $response->assertSee('name="recovery_code"', false);
        $response->assertSee('data-test="confirm-two-factor-button"', false);
        $response->assertSee(route('login'), false);
    }

    public function test_user_can_authenticate_via_valid_totp_code(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), [
                'code' => $validCode,
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_can_authenticate_via_valid_recovery_code(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['valid-recovery-code-123'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), [
                'recovery_code' => 'valid-recovery-code-123',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_two_factor_challenge_fails_with_invalid_code(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['login.id' => $user->id])
            ->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), [
                'code' => '000000',
            ]);

        $this->assertGuest();
        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHasErrors(['code']);
    }

    public function test_platform_admin_can_authenticate_via_2fa_and_is_redirected_to_platform_dashboard(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->withSession(['login.id' => $admin->id])
            ->post(route('two-factor.login.store'), [
                'code' => $validCode,
            ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('platform.dashboard'));
    }
}
