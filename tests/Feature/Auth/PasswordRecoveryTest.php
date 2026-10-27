<?php

namespace Tests\Feature\Auth;

use App\Models\AuthIdentity;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_manual_account_can_request_and_complete_api_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'manual-reset@example.com']);
        $otherToken = $user->createToken('phone', ['mobile'])->plainTextToken;

        $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202)
            ->assertJsonPath('message', 'Jika akun memenuhi syarat, instruksi pemulihan telah dikirim.');

        Notification::assertSentTo($user, ResetPassword::class);
        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $user->email,
            'token' => $notification->token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();
        $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'NewStrongPassword123!',
        ])->assertOk();
        $this->assertNotSame('', $otherToken);
    }

    public function test_unknown_and_google_only_accounts_have_the_same_generic_forgot_response(): void
    {
        Notification::fake();
        $googleOnly = User::factory()->create([
            'email' => 'google-only@example.com',
            'password_login_enabled' => false,
        ]);
        AuthIdentity::create([
            'user_id' => $googleOnly->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-only-subject',
        ]);

        $unknown = $this->postJson('/api/v2/auth/password/forgot', ['email' => 'missing@example.com']);
        $google = $this->postJson('/api/v2/auth/password/forgot', ['email' => $googleOnly->email]);

        $unknown->assertStatus(202);
        $google->assertStatus(202);
        $this->assertSame($unknown->json(), $google->json());
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_first_request_has_the_same_generic_response_for_known_and_unknown_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known-first@example.com']);

        $known = $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v2/auth/password/forgot', ['email' => 'missing-first@example.com']);

        $known->assertStatus(202);
        $unknown->assertStatus(202);
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_broker_throttled_known_request_matches_unknown_without_sending_another_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known-repeat@example.com']);

        $first = $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email]);
        $knownRepeat = $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email]);
        $unknownRepeat = $this->postJson('/api/v2/auth/password/forgot', ['email' => 'missing-repeat@example.com']);

        $first->assertStatus(202);
        $knownRepeat->assertStatus(202);
        $unknownRepeat->assertStatus(202);
        $this->assertSame($knownRepeat->json(), $unknownRepeat->json());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_first_reset_token_remains_valid_after_a_throttled_forgot_request(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known-token@example.com']);

        $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202);
        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $user->email,
            'token' => $notification->token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])->assertOk();
    }

    public function test_manual_google_linked_account_can_recover_without_changing_identity(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'linked-reset@example.com']);
        AuthIdentity::create([
            'user_id' => $user->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'linked-subject',
        ]);
        $identityId = $user->authIdentities()->firstOrFail()->id;

        $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email]);
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $user->email,
            'token' => $notification->token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])->assertOk();

        $this->assertDatabaseHas('auth_identities', ['id' => $identityId, 'provider_subject' => 'linked-subject']);
        $this->assertSame($user->id, $user->fresh()->id);
    }

    public function test_expired_and_reused_reset_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $expiredToken = Password::broker('users')->createToken($user);

        DB::table('password_reset_tokens')->update(['created_at' => now()->subHours(2)]);
        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $user->email,
            'token' => $expiredToken,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])->assertStatus(410)->assertJsonPath('code', 'PASSWORD_RESET_EXPIRED');

        $token = Password::broker('users')->createToken($user);
        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ];
        $this->postJson('/api/v2/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/v2/auth/password/reset', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'PASSWORD_RESET_INVALID');
    }

    public function test_password_reset_keeps_confirmed_two_factor_enabled(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'NewStrongPassword123!',
        ])->assertStatus(202)->assertJsonPath('code', 'TWO_FACTOR_REQUIRED');
    }
}
