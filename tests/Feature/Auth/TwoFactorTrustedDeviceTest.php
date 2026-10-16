<?php

namespace Tests\Feature\Auth;

use App\Livewire\Settings\Security;
use App\Models\User;
use App\Services\Auth\TwoFactorDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_login_challenges_user_when_device_is_untrusted(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_completing_2fa_sets_trusted_device_cookie(): void
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
                'remember_device' => true,
            ]);

        $this->assertAuthenticatedAs($user);
        $deviceService = app(TwoFactorDeviceService::class);
        $response->assertCookie($deviceService->cookieName($user));
    }

    public function test_subsequent_login_from_trusted_device_bypasses_2fa(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $deviceService = app(TwoFactorDeviceService::class);
        $cookie = $deviceService->createDeviceCookie($user);

        // Simulate login from the same device (cookie present in request)
        $response = $this->withCookie($deviceService->cookieName($user), $cookie->getValue())
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertSessionHasNoErrors();
        // Since device is trusted, 2FA challenge is bypassed and user goes straight to dashboard
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_subsequent_login_from_new_device_still_requires_2fa(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        // Login without any device cookie (new device / browser)
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_trusted_device_cookie_is_invalidated_when_password_changes(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $deviceService = app(TwoFactorDeviceService::class);
        $oldCookie = $deviceService->createDeviceCookie($user);

        // User updates password
        $user->forceFill(['password' => Hash::make('new-password')])->save();

        // Login using old device cookie should reject the cookie and require 2FA
        $response = $this->withCookie($deviceService->cookieName($user), $oldCookie->getValue())
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'new-password',
            ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_trusted_device_cookie_is_invalidated_when_two_factor_secret_changes(): void
    {
        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $deviceService = app(TwoFactorDeviceService::class);
        $oldCookie = $deviceService->createDeviceCookie($user);

        // User re-generates 2FA secret
        $user->forceFill(['two_factor_secret' => encrypt($google2fa->generateSecretKey())])->save();

        $response = $this->withCookie($deviceService->cookieName($user), $oldCookie->getValue())
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_setting_up_2fa_in_security_settings_queues_trusted_device_cookie(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->actingAs($user);

        $component = Livewire::test(Security::class)
            ->call('enable');

        $google2fa = app(Google2FA::class);
        $validOtp = $google2fa->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));

        $component->call('showVerificationIfNecessary')
            ->set('code', $validOtp)
            ->call('confirmTwoFactor')
            ->assertHasNoErrors();

        $deviceService = app(TwoFactorDeviceService::class);
        $cookieName = $deviceService->cookieName($user);

        $this->assertNotNull(Cookie::queued($cookieName), 'Trusted device cookie was not queued upon 2FA setup');
    }
}
