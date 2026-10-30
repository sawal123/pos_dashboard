<?php

namespace Tests\Feature\Auth;

use App\Models\AuthIdentity;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\GoogleAuthenticationException;
use App\Services\Auth\GoogleIdTokenClaims;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'web-client-id.apps.googleusercontent.com',
            'services.google.android_client_ids' => [],
        ]);
    }

    public function test_login_page_renders_the_google_button_when_configured(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="google-signin"', false)
            ->assertSee('accounts.google.com/gsi/client', false);
    }

    public function test_login_page_hides_the_google_button_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('id="google-signin"', false);
    }

    public function test_google_login_creates_and_provisions_a_new_account(): void
    {
        $this->fakeVerifier($this->claims('web-sub-new', 'web-new@example.com', 'Web User'));

        $response = $this->post(route('auth.google.login'), ['credential' => 'signed-id-token']);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'web-new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->passwordLoginIsEnabled());
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $user->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'web-sub-new',
        ]);
        $business = $user->businesses()->firstOrFail();
        $this->assertSame(Business::ROLE_OWNER, $business->pivot->role);
        $this->assertDatabaseHas('outlets', [
            'business_id' => $business->id,
            'name' => 'Outlet Utama',
            'code' => 'OUT-1',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_google_login_uses_the_web_platform_audience(): void
    {
        $verifier = new RecordingWebGoogleVerifier($this->claims('web-sub-platform', 'platform@example.com', 'Platform'));
        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);

        $this->post(route('auth.google.login'), ['credential' => 'signed-id-token'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame('web', $verifier->clientPlatform);
    }

    public function test_google_login_links_a_verified_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'used@example.com']);
        $this->fakeVerifier($this->claims('web-sub-collision', 'used@example.com', 'Collision'));

        $response = $this->post(route('auth.google.login'), ['credential' => 'signed-id-token']);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($existing);
        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $existing->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'web-sub-collision',
        ]);
    }

    public function test_google_login_does_not_link_an_unverified_existing_account(): void
    {
        $existing = User::factory()->unverified()->create(['email' => 'unverified@example.com']);
        $this->fakeVerifier($this->claims('web-sub-unverified', 'unverified@example.com', 'Unverified'));

        $response = $this->post(route('auth.google.login'), ['credential' => 'signed-id-token']);

        $response->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseMissing('auth_identities', [
            'provider_subject' => 'web-sub-unverified',
        ]);
        $this->assertSame(0, $existing->businesses()->count());
    }

    public function test_google_login_with_confirmed_two_factor_routes_through_fortify_challenge(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'web-2fa@example.com']);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'web-sub-2fa',
        ]);
        $this->fakeVerifier($this->claims('web-sub-2fa', 'web-2fa@example.com', 'Two Factor'));

        $response = $this->post(route('auth.google.login'), ['credential' => 'signed-id-token']);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_google_login_fails_closed_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $response = $this->post(route('auth.google.login'), ['credential' => 'signed-id-token']);

        $response->assertSessionHasErrors(['google' => 'Login Google belum dikonfigurasi.']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('auth_identities', 0);
    }

    public function test_google_login_requires_a_credential(): void
    {
        $response = $this->post(route('auth.google.login'));

        $response->assertSessionHasErrors('credential');
        $this->assertGuest();
    }

    private function fakeVerifier(GoogleIdTokenClaims $claims): void
    {
        $this->app->instance(GoogleIdTokenVerifier::class, new FakeWebGoogleIdTokenVerifier($claims));
    }

    private function claims(string $subject, string $email, string $name): GoogleIdTokenClaims
    {
        return new GoogleIdTokenClaims($subject, $email, time() + 600, time(), $name);
    }
}

final class FakeWebGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public function __construct(private readonly GoogleIdTokenClaims $claims) {}

    public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims
    {
        if ($clientPlatform !== 'web') {
            throw new GoogleAuthenticationException('GOOGLE_AUDIENCE_INVALID', 'Unexpected client platform.');
        }

        return $this->claims;
    }
}

final class RecordingWebGoogleVerifier implements GoogleIdTokenVerifier
{
    public ?string $clientPlatform = null;

    public function __construct(private readonly GoogleIdTokenClaims $claims) {}

    public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims
    {
        $this->clientPlatform = $clientPlatform;

        return $this->claims;
    }
}
