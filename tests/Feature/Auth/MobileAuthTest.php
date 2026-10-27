<?php

namespace Tests\Feature\Auth;

use App\Models\MobileAuthChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_v2_login_issues_an_expiring_mobile_token(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.expires_at', fn (mixed $value): bool => is_string($value));

        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertTrue($token->can('mobile'));
        $this->assertNotNull($token->expires_at);
    }

    public function test_manual_v2_login_returns_a_challenge_for_confirmed_two_factor(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'email' => 'two-factor@example.com',
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('code', 'TWO_FACTOR_REQUIRED')
            ->assertJsonPath('data.methods', ['totp', 'recovery_code'])
            ->assertJsonMissingPath('data.token');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $challenge = (string) $response->json('data.challenge_id');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $verified = $this->postJson('/api/v2/auth/2fa/verify', [
            'challenge_id' => $challenge,
            'method' => 'totp',
            'code' => $code,
        ]);

        $verified->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull(MobileAuthChallenge::query()->firstOrFail()->consumed_at);
    }

    public function test_recovery_code_is_consumed_once_and_challenge_is_single_use(): void
    {
        $user = User::factory()->create([
            'email' => 'recovery@example.com',
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $challenge = (string) $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data.challenge_id');

        $response = $this->postJson('/api/v2/auth/2fa/verify', [
            'challenge_id' => $challenge,
            'method' => 'recovery_code',
            'code' => 'recovery-code',
        ]);

        $response->assertOk();
        $this->assertSame([], $user->fresh()->recoveryCodes());

        $this->postJson('/api/v2/auth/2fa/verify', [
            'challenge_id' => $challenge,
            'method' => 'recovery_code',
            'code' => 'recovery-code',
        ])->assertStatus(410)->assertJsonPath('code', 'TWO_FACTOR_CHALLENGE_USED');
    }

    public function test_v2_me_and_logout_require_mobile_ability_and_revoke_only_current_token(): void
    {
        $user = User::factory()->create();
        $mobile = $user->createToken('phone', ['mobile'])->plainTextToken;
        $other = $user->createToken('tablet', ['mobile'])->plainTextToken;
        $web = $user->createToken('web', ['web'])->plainTextToken;

        $this->requestWithToken($web)->getJson('/api/v2/auth/me')->assertStatus(403);

        auth()->forgetGuards();
        $this->requestWithToken($mobile)->getJson('/api/v2/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.two_factor_enabled', false)
            ->assertJsonPath('data.authentication_methods', ['manual']);

        $this->requestWithToken($mobile)->deleteJson('/api/v2/auth/logout')->assertOk();
        $this->assertTokenUnauthorized($mobile);
        auth()->forgetGuards();
        $this->requestWithToken($other)->getJson('/api/v2/auth/me')->assertOk();
    }

    private function requestWithToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function assertTokenUnauthorized(string $token): void
    {
        auth()->forgetGuards();
        $this->requestWithToken($token)->getJson('/api/v2/auth/me')->assertUnauthorized();
    }
}
