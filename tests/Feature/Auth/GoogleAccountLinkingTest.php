<?php

namespace Tests\Feature\Auth;

use App\Models\AuthIdentity;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\PendingRegistration;
use App\Models\ReauthenticationGrant;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\GoogleAuthenticationException;
use App\Services\Auth\GoogleIdTokenClaims;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class GoogleAccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    private GoogleAccountLinkingFakeVerifier $fakeVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeVerifier = new GoogleAccountLinkingFakeVerifier(
            new GoogleIdTokenClaims('google-link-sub', 'google@example.com', time() + 600, time()),
        );
        $this->app->instance(GoogleIdTokenVerifier::class, $this->fakeVerifier);
    }

    public function test_manual_user_can_reauthenticate_and_link_without_mutating_tenant_state(): void
    {
        $user = $this->manualUser();
        $this->tenantSnapshot($user);
        [$bearer] = $this->mobileToken($user);

        $before = [User::query()->count(), Business::query()->count(), Outlet::query()->count(), Subscription::query()->count()];
        $grant = $this->reauthenticate($bearer);

        $grant->assertOk()->assertJsonPath('data.expires_in', 300);
        $rawGrant = (string) $grant->json('data.reauthentication_token');
        $this->assertNotSame('', $rawGrant);
        $this->assertDatabaseCount('reauthentication_grants', 1);
        $this->assertDatabaseMissing('reauthentication_grants', ['token_digest' => $rawGrant]);

        $link = $this->link($bearer, $rawGrant);
        $link->assertOk()->assertJsonPath('data.provider', 'google')->assertJsonPath('data.linked', true);

        $this->assertSame($before, [User::query()->count(), Business::query()->count(), Outlet::query()->count(), Subscription::query()->count()]);
        $this->assertDatabaseHas('auth_identities', [
            'user_id' => $user->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-link-sub',
        ]);
        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_reauthentication_requires_a_mobile_token_and_rejects_invalid_credentials(): void
    {
        $user = $this->manualUser();
        [$webBearer] = $this->mobileToken($user, ['web']);
        $this->requestWithBearer('/api/v2/auth/reauthenticate', ['password' => 'password'], $webBearer)
            ->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');

        [$mobileBearer] = $this->mobileToken($user);
        $this->reauthenticate($mobileBearer, 'wrong-password')
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->assertDatabaseCount('reauthentication_grants', 0);
    }

    public function test_grant_is_bound_to_user_and_current_token_and_is_single_use(): void
    {
        $user = $this->manualUser();
        [$firstBearer] = $this->mobileToken($user);
        [$secondBearer] = $this->mobileToken($user);
        $rawGrant = (string) $this->reauthenticate($firstBearer)->json('data.reauthentication_token');

        $this->link($secondBearer, $rawGrant)->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');
        $this->link($firstBearer, $rawGrant)->assertOk();
        $this->link($firstBearer, $rawGrant)->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_ALREADY_USED');

        $otherUser = $this->manualUser('other@example.com');
        [$otherBearer] = $this->mobileToken($otherUser);
        $otherRaw = (string) $this->reauthenticate($otherBearer)->json('data.reauthentication_token');
        $this->link($firstBearer, $otherRaw)->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');
    }

    public function test_expired_grant_is_rejected_without_linking(): void
    {
        $user = $this->manualUser();
        [$bearer] = $this->mobileToken($user);
        $rawGrant = (string) $this->reauthenticate($bearer)->json('data.reauthentication_token');
        ReauthenticationGrant::query()->update(['expires_at' => now()->subSecond()]);

        $this->link($bearer, $rawGrant)->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_EXPIRED');
        $this->assertDatabaseCount('auth_identities', 0);
    }

    public function test_confirmed_two_factor_requires_a_valid_existing_totp(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => '2fa-link@example.com',
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);
        [$bearer] = $this->mobileToken($user);

        $this->reauthenticate($bearer)->assertStatus(403)->assertJsonPath('code', 'TWO_FACTOR_REQUIRED');
        $code = (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');
        $this->reauthenticate($bearer, 'password', $code)->assertOk();
    }

    public function test_pending_registration_cannot_bypass_otp_with_reauthentication(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'pending-link@example.com']);
        PendingRegistration::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'normalized_email' => $user->email,
            'idempotency_key_hash' => hash('sha256', 'pending-link'),
            'request_fingerprint' => hash('sha256', 'pending-link-payload'),
            'business_name_intent' => 'Pending Store',
            'status' => PendingRegistration::STATUS_PENDING_EMAIL,
            'provisioning_status' => PendingRegistration::PROVISIONING_PENDING,
        ]);
        [$bearer] = $this->mobileToken($user);

        $this->reauthenticate($bearer)->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');
        $this->assertDatabaseCount('reauthentication_grants', 0);
    }

    public function test_identity_ownership_conflicts_are_fail_closed(): void
    {
        $owner = $this->manualUser('owner@example.com');
        AuthIdentity::create(['user_id' => $owner->id, 'provider' => 'google', 'provider_subject' => 'taken-sub']);
        $current = $this->manualUser('current@example.com');
        [$bearer] = $this->mobileToken($current);
        $rawGrant = (string) $this->reauthenticate($bearer)->json('data.reauthentication_token');
        $this->fakeClaims(new GoogleIdTokenClaims('taken-sub', 'different@example.com', time() + 600, time()));

        $this->link($bearer, $rawGrant)->assertStatus(409)->assertJsonPath('code', 'IDENTITY_ALREADY_LINKED');
        $this->assertSame($owner->id, AuthIdentity::query()->where('provider_subject', 'taken-sub')->value('user_id'));

        AuthIdentity::create(['user_id' => $current->id, 'provider' => 'google', 'provider_subject' => 'existing-sub']);
        $secondGrant = (string) $this->reauthenticate($bearer)->json('data.reauthentication_token');
        $this->fakeClaims(new GoogleIdTokenClaims('new-sub', 'another@example.com', time() + 600, time()));
        $this->link($bearer, $secondGrant)->assertStatus(409)->assertJsonPath('code', 'IDENTITY_CONFLICT');
        $this->assertSame(2, AuthIdentity::query()->count());
    }

    public function test_same_identity_is_idempotent_and_matching_email_is_not_required(): void
    {
        $user = $this->manualUser('google@example.com');
        AuthIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'provider_subject' => 'google-link-sub']);
        [$bearer] = $this->mobileToken($user);
        $rawGrant = (string) $this->reauthenticate($bearer)->json('data.reauthentication_token');

        $this->link($bearer, $rawGrant)->assertOk()->assertJsonPath('data.linked', false);
        $this->assertDatabaseCount('auth_identities', 1);
        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        $newUser = $this->manualUser('manual@example.com');
        [$newBearer] = $this->mobileToken($newUser);
        $newGrant = (string) $this->reauthenticate($newBearer)->json('data.reauthentication_token');
        $this->fakeClaims(new GoogleIdTokenClaims('different-email-sub', 'unrelated@example.com', time() + 600, time()));
        $this->link($newBearer, $newGrant)->assertOk();
        $this->assertSame('manual@example.com', $newUser->fresh()->email);
        $this->assertDatabaseHas('auth_identities', ['user_id' => $newUser->id, 'provider_subject' => 'different-email-sub']);
    }

    public function test_duplicate_subject_is_not_created_for_a_second_user(): void
    {
        $first = $this->manualUser('first@example.com');
        [$firstBearer] = $this->mobileToken($first);
        $firstGrant = (string) $this->reauthenticate($firstBearer)->json('data.reauthentication_token');
        $this->link($firstBearer, $firstGrant)->assertOk();

        $second = $this->manualUser('second@example.com');
        [$secondBearer] = $this->mobileToken($second);
        $secondGrant = (string) $this->reauthenticate($secondBearer)->json('data.reauthentication_token');
        $this->link($secondBearer, $secondGrant)->assertStatus(409)->assertJsonPath('code', 'IDENTITY_ALREADY_LINKED');
        $this->assertSame(1, AuthIdentity::query()->where('provider_subject', 'google-link-sub')->count());
    }

    public function test_invalid_bearer_and_google_token_do_not_mutate_state(): void
    {
        $this->postJson('/api/v2/auth/reauthenticate', ['password' => 'password'])->assertUnauthorized();

        $user = $this->manualUser();
        [$bearer] = $this->mobileToken($user);
        $rawGrant = (string) $this->reauthenticate($bearer)->json('data.reauthentication_token');
        $this->fakeVerifier->setException(new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'invalid'));

        $this->link($bearer, $rawGrant)->assertStatus(401)->assertJsonPath('code', 'GOOGLE_TOKEN_INVALID');
        $this->assertDatabaseCount('auth_identities', 0);
        $this->assertDatabaseHas('reauthentication_grants', ['consumed_at' => null]);
    }

    private function manualUser(string $email = 'manual@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function tenantSnapshot(User $user): void
    {
        $business = Business::factory()->active()->create();
        $user->businesses()->attach($business->id, ['role' => Business::ROLE_OWNER]);
        Outlet::factory()->create(['business_id' => $business->id]);
        Subscription::factory()->free()->create(['business_id' => $business->id]);
    }

    /** @return array{0: string, 1: int} */
    private function mobileToken(User $user, array $abilities = ['mobile']): array
    {
        $access = $user->createToken('mobile-api', $abilities);

        return [$access->plainTextToken, $access->accessToken->id];
    }

    private function reauthenticate(string $bearer, string $password = 'password', ?string $totp = null)
    {
        $payload = ['password' => $password];
        if ($totp !== null) {
            $payload['totp_code'] = $totp;
        }

        return $this->requestWithBearer('/api/v2/auth/reauthenticate', $payload, $bearer);
    }

    private function link(string $bearer, string $rawGrant)
    {
        return $this->requestWithBearer('/api/v2/auth/google/link', [
            'id_token' => 'fake-google-token',
            'client_platform' => 'android',
            'reauthentication_token' => $rawGrant,
        ], $bearer);
    }

    private function requestWithBearer(string $uri, array $payload, string $bearer)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson($uri, $payload, ['Authorization' => 'Bearer '.$bearer]);
    }

    private function fakeClaims(GoogleIdTokenClaims $claims): void
    {
        $this->fakeVerifier->setClaims($claims);
    }
}

final class GoogleAccountLinkingFakeVerifier implements GoogleIdTokenVerifier
{
    public function __construct(
        private ?GoogleIdTokenClaims $claims = null,
        private ?\Throwable $exception = null,
    ) {}

    public function setClaims(GoogleIdTokenClaims $claims): void
    {
        $this->claims = $claims;
        $this->exception = null;
    }

    public function setException(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->claims ?? throw new \LogicException('Test claims are missing.');
    }
}
