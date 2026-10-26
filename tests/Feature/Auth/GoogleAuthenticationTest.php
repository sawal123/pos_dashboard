<?php

namespace Tests\Feature\Auth;

use App\Models\AuthIdentity;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\GoogleAuthenticationException;
use App\Services\Auth\GoogleIdTokenClaims;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'web-client-id.apps.googleusercontent.com',
            'services.google.android_client_ids' => ['android-client-id.apps.googleusercontent.com'],
        ]);
    }

    public function test_new_google_account_is_verified_provisioned_and_issued_a_mobile_token(): void
    {
        $this->fakeVerifier($this->claims('google-sub-1', 'new-google@example.com', 'Google User'));

        $response = $this->postJson('/api/v2/auth/google', $this->request());

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'new-google@example.com');

        $user = User::query()->where('email', 'new-google@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $user->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-sub-1',
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
        $this->assertCount(1, $user->businesses()->get());
        $this->assertCount(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->get());
    }

    public function test_repeated_google_login_uses_the_same_identity_and_does_not_duplicate_provisioning(): void
    {
        $claims = $this->claims('google-sub-repeat', 'repeat-google@example.com', 'Repeat User');
        $this->fakeVerifier($claims);

        $first = $this->postJson('/api/v2/auth/google', $this->request());
        $second = $this->postJson('/api/v2/auth/google', $this->request());

        $first->assertOk();
        $second->assertOk();
        $user = User::query()->where('email', 'repeat-google@example.com')->firstOrFail();

        $this->assertSame($user->id, $second->json('data.user.id'));
        $this->assertSame(1, User::query()->where('email', 'repeat-google@example.com')->count());
        $this->assertSame(1, AuthIdentity::query()->where('provider_subject', 'google-sub-repeat')->count());
        $this->assertSame(1, $user->businesses()->count());
        $this->assertSame(1, Business::query()->count());
        $this->assertSame(1, Outlet::query()->count());
        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame(2, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_google_email_collision_requires_account_linking_without_mutating_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'used@example.com', 'name' => 'Original Name']);
        $this->fakeVerifier($this->claims('google-sub-collision', 'used@example.com', 'Google Collision'));

        $response = $this->postJson('/api/v2/auth/google', $this->request());

        $response->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_LINK_REQUIRED');
        $this->assertDatabaseMissing('auth_identities', ['provider_subject' => 'google-sub-collision']);
        $this->assertSame('Original Name', $existing->fresh()->name);
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertSame(0, Business::query()->count());
    }

    public function test_different_google_subject_cannot_take_over_an_existing_email(): void
    {
        $existing = User::factory()->create(['email' => 'same@example.com', 'name' => 'Existing']);
        AuthIdentity::create([
            'user_id' => $existing->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'original-subject',
        ]);
        $this->fakeVerifier($this->claims('different-subject', 'same@example.com', 'Attacker'));

        $response = $this->postJson('/api/v2/auth/google', $this->request());

        $response->assertStatus(409)->assertJsonPath('code', 'ACCOUNT_LINK_REQUIRED');
        $this->assertSame('Existing', $existing->fresh()->name);
        $this->assertSame(1, AuthIdentity::query()->count());
    }

    public function test_confirmed_two_factor_blocks_google_token_issuance(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => '2fa@example.com']);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-sub-2fa',
        ]);
        $this->fakeVerifier($this->claims('google-sub-2fa', '2fa@example.com', '2FA User'));

        $response = $this->postJson('/api/v2/auth/google', $this->request());

        $response->assertStatus(403)->assertJsonPath('code', 'TWO_FACTOR_REQUIRED');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_verifier_failures_use_stable_error_contract(): void
    {
        $this->app->instance(GoogleIdTokenVerifier::class, new FakeGoogleIdTokenVerifier(
            exception: new GoogleAuthenticationException('GOOGLE_AUDIENCE_INVALID', 'internal'),
        ));

        $response = $this->postJson('/api/v2/auth/google', $this->request());

        $response->assertStatus(401)
            ->assertJson(['code' => 'GOOGLE_AUDIENCE_INVALID'])
            ->assertJsonMissingPath('exception');
    }

    public function test_missing_google_configuration_fails_closed_without_database_mutation(): void
    {
        config(['services.google.client_id' => null, 'services.google.android_client_ids' => []]);

        $response = $this->postJson('/api/v2/auth/google', $this->request());

        $response->assertStatus(503)->assertJsonPath('code', 'GOOGLE_AUTH_NOT_CONFIGURED');
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, AuthIdentity::query()->count());
    }

    public function test_google_oidc_key_retrieval_and_signature_fail_closed(): void
    {
        Cache::forget('auth-google:oidc-public-keys:v1');
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
                'keys' => [],
            ], 200, ['cache-control' => 'public, max-age=60']),
        ]);

        $invalidSignature = $this->unsignedToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'crypto-sub',
            'email' => 'crypto@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ]);

        $this->expectException(GoogleAuthenticationException::class);
        $this->expectExceptionMessage('safely');
        app(GoogleIdTokenVerifier::class)->verify($invalidSignature, 'web');
    }

    private function request(): array
    {
        return [
            'id_token' => 'test-token',
            'client_platform' => 'android',
        ];
    }

    private function fakeVerifier(GoogleIdTokenClaims $claims): void
    {
        $this->app->instance(GoogleIdTokenVerifier::class, new FakeGoogleIdTokenVerifier($claims));
    }

    private function claims(string $subject, string $email, string $name): GoogleIdTokenClaims
    {
        return new GoogleIdTokenClaims($subject, $email, time() + 600, time(), $name);
    }

    /** @param array<string, mixed> $payload */
    private function unsignedToken(array $payload): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'kid' => 'test-kid', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR));

        return $header.'.'.$body.'.invalid-signature';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public function __construct(
        private readonly ?GoogleIdTokenClaims $claims = null,
        private readonly ?GoogleAuthenticationException $exception = null,
    ) {}

    public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        if ($this->claims === null) {
            throw new \LogicException('Fake verifier claims were not configured.');
        }

        return $this->claims;
    }
}
