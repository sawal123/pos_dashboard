<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailChangeOtpMail;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\Auth\GoogleIdTokenClaims;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth_otp.mailer' => 'smtp']);
    }

    public function test_manual_user_receives_otp_at_new_email_and_changes_email_once(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('CurrentPassword123!'),
        ]);
        $token = $user->createToken('phone', ['mobile'])->plainTextToken;

        $response = $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'new@example.com',
            'current_password' => 'CurrentPassword123!',
        ]);

        $response->assertStatus(202)->assertJsonPath('data.state', 'pending_verification');
        Mail::assertSent(EmailChangeOtpMail::class, function (EmailChangeOtpMail $mail): bool {
            return $mail->to[0]['address'] === 'new@example.com';
        });
        $mail = Mail::sent(EmailChangeOtpMail::class)->first();

        $verification = $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/verify', [
            'change_id' => $response->json('data.change_id'),
            'code' => $mail->code,
        ]);
        $verification->assertOk()
            ->assertJsonPath('data.reauthentication_required', true)
            ->assertJsonMissingPath('data.token');

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_proof_two_factor_and_email_collision_are_rejected_without_mutation(): void
    {
        Mail::fake();
        $user = User::factory()->withTwoFactor()->create([
            'email' => 'twofactor-old@example.com',
            'password' => Hash::make('CurrentPassword123!'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);
        User::factory()->create(['email' => 'taken@example.com']);
        $token = $user->createToken('phone', ['mobile'])->plainTextToken;

        $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'new-twofactor@example.com',
            'current_password' => 'wrong',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'new-twofactor@example.com',
            'current_password' => 'CurrentPassword123!',
        ])->assertStatus(403)->assertJsonPath('code', 'TWO_FACTOR_REQUIRED');

        $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'taken@example.com',
            'current_password' => 'CurrentPassword123!',
            'totp_code' => (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret)),
        ])->assertStatus(409)->assertJsonPath('code', 'EMAIL_ALREADY_USED');

        $this->assertSame('twofactor-old@example.com', $user->fresh()->email);
    }

    public function test_mixed_case_email_change_invalidates_case_sensitive_password_reset_tokens(): void
    {
        Mail::fake();
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'User@Example.com',
            'password' => Hash::make('CurrentPassword123!'),
        ]);
        $token = $user->createToken('phone', ['mobile'])->plainTextToken;

        $this->postJson('/api/v2/auth/password/forgot', ['email' => $user->email])
            ->assertStatus(202);
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'User@Example.com']);

        $request = $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'new@example.com',
            'current_password' => 'CurrentPassword123!',
        ])->assertStatus(202);
        $mail = Mail::sent(EmailChangeOtpMail::class)->first();

        $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/verify', [
            'change_id' => $request->json('data.change_id'),
            'code' => $mail->code,
        ])->assertOk();

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'User@Example.com']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'user@example.com']);

        $this->postJson('/api/v2/auth/password/reset', [
            'email' => 'User@Example.com',
            'token' => $notification->token,
            'password' => 'CompromisedPassword123!',
            'password_confirmation' => 'CompromisedPassword123!',
        ])->assertStatus(422);

        $reassigned = User::factory()->create(['email' => 'User@Example.com']);
        $this->postJson('/api/v2/auth/password/reset', [
            'email' => $reassigned->email,
            'token' => $notification->token,
            'password' => 'CompromisedPassword123!',
            'password_confirmation' => 'CompromisedPassword123!',
        ])->assertStatus(422);
    }

    public function test_wrong_otp_does_not_change_email_and_google_only_requires_own_subject(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'otp-old@example.com']);
        $token = $user->createToken('phone', ['mobile'])->plainTextToken;
        $response = $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'otp-new@example.com',
            'current_password' => 'password',
        ]);

        $this->requestWithToken($token)->postJson('/api/v2/auth/email/change/verify', [
            'change_id' => $response->json('data.change_id'),
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('code', 'EMAIL_CHANGE_INVALID_OTP');
        $this->assertSame('otp-old@example.com', $user->fresh()->email);

        $google = User::factory()->create([
            'email' => 'google-change@example.com',
            'password_login_enabled' => false,
        ]);
        AuthIdentity::create([
            'user_id' => $google->id,
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-change-subject',
        ]);
        $googleToken = $google->createToken('phone', ['mobile'])->plainTextToken;
        $this->app->instance(GoogleIdTokenVerifier::class, new class implements GoogleIdTokenVerifier
        {
            public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims
            {
                return new GoogleIdTokenClaims(
                    $idToken === 'own' ? 'google-change-subject' : 'other-subject',
                    'not-used-as-identity@example.com',
                    time() + 600,
                    time(),
                );
            }
        });

        $this->requestWithToken($googleToken)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'google-new@example.com',
            'id_token' => 'other',
            'client_platform' => 'android',
        ])->assertStatus(403)->assertJsonPath('code', 'REAUTHENTICATION_REQUIRED');

        $googleResponse = $this->requestWithToken($googleToken)->postJson('/api/v2/auth/email/change/request', [
            'new_email' => 'google-new@example.com',
            'id_token' => 'own',
            'client_platform' => 'android',
        ]);
        $googleResponse->assertStatus(202);
    }

    private function requestWithToken(string $token): self
    {
        auth()->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
