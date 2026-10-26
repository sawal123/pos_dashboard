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
use App\Services\Auth\GoogleOidcVerifier;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
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

    public function test_real_verifier_accepts_google_style_rsa_jwk_and_android_server_audience(): void
    {
        $this->fakeGoogleJwks();
        $this->assertNotNull(JWK::parseKey([
            'kty' => 'RSA',
            'e' => 'AQAB',
            'kid' => 'jwk1',
            'n' => '0Ttga33B1yX4w77NbpKyNYDNSVCo8j-RlZaZ9tI-KfkV1d-tfsvI9ZPAheP11FoN52ceBaY5ltelHW-IKwCfyT0orLdsxLgowaXki9woF1Azvcg2JVxQLv9aVjjAvy3CZFIG_EeN7J3nsyCXGnu1yMEbnvkWxA88__Q6HQ2K9wqfApkQ0LNlsK0YHz_sfjHNvRKxnbAJk7D5fUhZunPZXOPHXFgA5SvLvMaNIXduMKJh4OMfuoLdJowXJAR9j31Mqz_is4FMhm_9Mq7vZZ-uF09htRvIR8tRY28oJuW1gKWyg7cQ',
            'alg' => 'RS256',
        ]));
        $privateKey = openssl_pkey_get_private($this->rsaPrivateKey());
        $this->assertNotFalse($privateKey);
        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $this->assertSame(
            '0Ttga33B1yX4w77NbpKyNYDNSVCo8j-RlZaZ9tI-KfkV1d-tfsvI9ZPAheP11FoN52ceBaY5ltelHW-IKwCfyT0orLdsxLgowaXki9woF1Azvcg2JVxQLv9aVjjAvy3CZFIG_EeN7J3nsyCXGnu1yMEbnvkWxA88__Q6HQ2K9wqfApkQ0LNlsK0YHz_sfjHNvRKxnbAJk7D5fUhZunPZXOPHXFgA5SvLvMaNIXduMKJh4OMfuoLdJowXJAR9j31Mqz_is4FMhm_9Mq7vZZ-uF09htRvIR8tRY28oJuW1gKWyg7cQQpnjHgFyG3XLXWAeXclWqyh_LfjyHQjrYhyeFw',
            rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
        );
        $token = $this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'azp' => 'android-client-id.apps.googleusercontent.com',
            'sub' => 'real-jwk-sub',
            'email' => 'real-jwk@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ]);
        $this->assertNotNull(JWT::decode($token, JWK::parseKey([
            'kty' => 'RSA',
            'e' => 'AQAB',
            'kid' => 'jwk1',
            'n' => '0Ttga33B1yX4w77NbpKyNYDNSVCo8j-RlZaZ9tI-KfkV1d-tfsvI9ZPAheP11FoN52ceBaY5ltelHW-IKwCfyT0orLdsxLgowaXki9woF1Azvcg2JVxQLv9aVjjAvy3CZFIG_EeN7J3nsyCXGnu1yMEbnvkWxA88__Q6HQ2K9wqfApkQ0LNlsK0YHz_sfjHNvRKxnbAJk7D5fUhZunPZXOPHXFgA5SvLvMaNIXduMKJh4OMfuoLdJowXJAR9j31Mqz_is4FMhm_9Mq7vZZ-uF09htRvIR8tRY28oJuW1gKWyg7cQQpnjHgFyG3XLXWAeXclWqyh_LfjyHQjrYhyeFw',
            'alg' => 'RS256',
        ])));

        $claims = app(GoogleOidcVerifier::class)->verify($this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'azp' => 'android-client-id.apps.googleusercontent.com',
            'sub' => 'real-jwk-sub',
            'email' => 'real-jwk@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ]), 'android');

        $this->assertSame('real-jwk-sub', $claims->subject);
        $this->assertSame('real-jwk@example.com', $claims->email);
    }

    public function test_real_verifier_accepts_server_audience_for_web_and_android_without_android_allowlist_breakage(): void
    {
        $this->fakeGoogleJwks();

        $webClaims = app(GoogleOidcVerifier::class)->verify($this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'web-sub',
            'email' => 'web@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ]), 'web');
        $androidClaims = app(GoogleOidcVerifier::class)->verify($this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'android-server-aud-sub',
            'email' => 'android-server-aud@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ]), 'android');

        $this->assertSame('web-sub', $webClaims->subject);
        $this->assertSame('android-server-aud-sub', $androidClaims->subject);
    }

    public function test_real_verifier_rejects_invalid_signature_and_unknown_kid(): void
    {
        $this->fakeGoogleJwks();
        $token = $this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'invalid-signature-sub',
            'email' => 'invalid-signature@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ]);
        $parts = explode('.', $token);
        $parts[2] = rtrim(strtr(base64_encode(random_bytes(256)), '+/', '-_'), '=');

        $this->assertVerifierError(implode('.', $parts), 'GOOGLE_TOKEN_INVALID');
        $this->assertVerifierError($this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'unknown-kid-sub',
            'email' => 'unknown-kid@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ], 'unknown-kid'), 'GOOGLE_TOKEN_INVALID');
    }

    public function test_real_verifier_rejects_wrong_issuer_audience_and_authorized_presenter(): void
    {
        $this->fakeGoogleJwks();

        $base = [
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'policy-sub',
            'email' => 'policy@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 600,
        ];
        $this->assertVerifierError($this->realToken([...$base, 'iss' => 'https://evil.example']), 'GOOGLE_TOKEN_INVALID');
        $this->assertVerifierError($this->realToken([...$base, 'iss' => 'https://accounts.google.com', 'aud' => 'other-client']), 'GOOGLE_AUDIENCE_INVALID');
        $this->assertVerifierError($this->realToken([...$base, 'iss' => 'https://accounts.google.com', 'azp' => 'other-android-client']), 'GOOGLE_AUDIENCE_INVALID');
    }

    public function test_real_verifier_rejects_expired_token(): void
    {
        $this->fakeGoogleJwks();

        $this->assertVerifierError($this->realToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client-id.apps.googleusercontent.com',
            'sub' => 'expired-sub',
            'email' => 'expired@example.com',
            'email_verified' => true,
            'iat' => time() - 1200,
            'exp' => time() - 600,
        ]), 'GOOGLE_TOKEN_EXPIRED');
    }

    private function request(): array
    {
        return [
            'id_token' => 'test-token',
            'client_platform' => 'android',
        ];
    }

    private function fakeGoogleJwks(): void
    {
        Cache::forget('auth-google:oidc-public-keys:v1');
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'e' => 'AQAB',
                    'kid' => 'jwk1',
                    'n' => '0Ttga33B1yX4w77NbpKyNYDNSVCo8j-RlZaZ9tI-KfkV1d-tfsvI9ZPAheP11FoN52ceBaY5ltelHW-IKwCfyT0orLdsxLgowaXki9woF1Azvcg2JVxQLv9aVjjAvy3CZFIG_EeN7J3nsyCXGnu1yMEbnvkWxA88__Q6HQ2K9wqfApkQ0LNlsK0YHz_sfjHNvRKxnbAJk7D5fUhZunPZXOPHXFgA5SvLvMaNIXduMKJh4OMfuoLdJowXJAR9j31Mqz_is4FMhm_9Mq7vZZ-uF09htRvIR8tRY28oJuW1gKWyg7cQQpnjHgFyG3XLXWAeXclWqyh_LfjyHQjrYhyeFw',
                    'alg' => 'RS256',
                    'use' => 'sig',
                ]],
            ], 200, ['cache-control' => 'public, max-age=60']),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function realToken(array $payload, string $keyId = 'jwk1'): string
    {
        return JWT::encode($payload, $this->rsaPrivateKey(), 'RS256', $keyId);
    }

    private function rsaPrivateKey(): string
    {
        return <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA0Ttga33B1yX4w77NbpKyNYDNSVCo8j+RlZaZ9tI+KfkV1d+t
fsvI9ZPAheP11FoN52ceBaY5ltelHW+IKwCfyT0orLdsxLgowaXki9woF1Azvcg2
JVxQLv9aVjjAvy3CZFIG/EeN7J3nsyCXGnu1yMEbnvkWxA88//Q6HQ2K9wqfApkQ
0LNlsK0YHz/sfjHNvRKxnbAJk7D5fUhZunPZXOPHXFgA5SvLvMaNIXduMKJh4OMf
uoLdJowXJAR9j31Mqz/is4FMhm/9Mq7vZZ+uF09htRvIR8tRY28oJuW1gKWyg7cQ
QpnjHgFyG3XLXWAeXclWqyh/LfjyHQjrYhyeFwIDAQABAoIBAHMqdJsWAGEVNIVB
+792HYNXnydQr32PwemNmLeD59WglgU/9jZJoxaROjI4VLKK0wZg+uRvJ1nA3tCB
+Hh7Anh5Im9XExaAq2ZTkqXtC2AxtBktH6iW1EfaI/Y7jNRuMoaXo+Ku3A62p7cw
JBvepiOXL0Xko0RNguz7mBUvxCLPhYhzn7qCbM8uXLcjsXq/YhWQwQmtMqv0sd3W
Hy+8Jb2c18sqDeZIBne4dWD6qPClPEOsrq9gPTkl0DjbT27oVc2u1p4HMNm5BJIh
u3rMSxnZHUd7Axj1FgyLIOHl63UhaiaA1aPe/fLiVIGOA1jBZrpbnjgqDy9Uxyn6
eydbiwECgYEA9mtRydz22idyUOlBCDXk+vdGBvFAucNYaNNUAXUJ2wfPmdGgFCA7
g5eQG8JC6J/FU+2AfIuz6LGr7SxMBYcsWGjFAzGqs/sJib+zzN1dPUSRn4uJNFit
51yQzPgBqHS6S/XBi6YAODeZDl9jiPl3FxxucqLY5NstqZFXbE0SjIECgYEA2V3r
7xnRAK1krY1+zkPof4kcBmjqOXjnl/oRxlXP65lEXmyNJwm/ulOIko9mElWRs8CG
AxSWKaab9Gk6lc8MHjVRbuW52RGLGKq1mp6ENr4d3IBOfrNsTvD3gtNEN1JFLeF1
jIbSsrbi2txr7VZ06Irac0C/ytro0QDOUoXkvpcCgYA8O0EzmToRWsD7e/g0XJAK
s/Q+8CtE/LWYccc/z+7HxeH9lBqPsM07Pgmwb0xRdfQSrqPQTYl9ICiJAWHXnBG/
zmQRgstZ0MulCuGU+qq2thLuL3oq/F4NhjeykhA9r8J1nK1hSAMXuqdDtxcqPOfa
E03/4UQotFY181uuEiytgQKBgHQT+gjHqptH/XnJFCymiySAXdz2bg6fCF5aht95
t/1C7gXWxlJQnHiuX0KVHZcw5wwtBePjPIWlmaceAtE5rmj7ZC9qsqK/AZ78mtql
SEnLoTq9si1rN624dRUCKW25m4Py4MlYvm/9xovGJkSqZOhCLoJZ05JK8QWb/pKH
Oi6lAoGBAOUN6ICpMQvzMGPgIbgS0H/gvRTnpAEs59vdgrkhlCII4tzfgvBQlVae
hRcdM6GTMq5pekBPKu45eanIzwVc88P6coT4qiWYKk2jYoLBa0UV3xEAuqBMymrj
X4nLcSbZtO0tcDGMfMpWF2JGYOEJQNetPozL/ICGVFyIO8yzXm8U
-----END RSA PRIVATE KEY-----
PEM;
    }

    private function assertVerifierError(string $token, string $errorCode): void
    {
        try {
            app(GoogleOidcVerifier::class)->verify($token, 'android');
            $this->fail('Expected Google verifier error '.$errorCode.'.');
        } catch (GoogleAuthenticationException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
        }
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
