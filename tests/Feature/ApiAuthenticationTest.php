<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // 1. LOGIN SUCCESS TESTS
    // ==========================================

    public function test_verified_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'kasir@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'kasir@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'token_type',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ]);

        $response->assertJson([
            'data' => [
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-api',
        ]);

        $this->assertTrue($user->fresh()->tokens->first()->can('mobile'));
    }

    // ==========================================
    // 2. INVALID CREDENTIALS TESTS
    // ==========================================

    public function test_login_with_wrong_password_returns_401_generic_message(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials.',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_unregistered_email_returns_401_generic_message(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'some-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid credentials.',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_password_and_unknown_email_return_same_generic_message(): void
    {
        User::factory()->create([
            'email' => 'known@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $res1 = $this->postJson('/api/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrong',
        ]);

        $res2 = $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong',
        ]);

        $this->assertSame($res1->json('message'), $res2->json('message'));
        $this->assertSame('Invalid credentials.', $res1->json('message'));
    }

    // ==========================================
    // 3. EMAIL VERIFICATION TESTS
    // ==========================================

    public function test_unverified_user_cannot_login_and_receives_403(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Email address is not verified.',
            'code' => 'EMAIL_NOT_VERIFIED',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ==========================================
    // 4. TWO-FACTOR AUTH FAIL-CLOSED TESTS
    // ==========================================

    public function test_two_factor_confirmed_user_fails_closed_and_receives_403(): void
    {
        User::factory()->create([
            'email' => '2fa-user@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => '2fa-user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Two-factor authentication is required.',
            'code' => 'TWO_FACTOR_REQUIRED',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ==========================================
    // 5. CURRENT USER (/ME) TESTS
    // ==========================================

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $response->assertJsonMissing(['password']);
        $response->assertJsonMissing(['remember_token']);
        $response->assertJsonMissing(['two_factor_secret']);
        $response->assertJsonMissing(['two_factor_recovery_codes']);
    }

    public function test_unauthenticated_request_to_me_returns_401_json(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }

    // ==========================================
    // 6. LOGOUT TESTS
    // ==========================================

    public function test_authenticated_user_can_logout_current_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        auth()->forgetGuards();

        // Token can no longer access /me
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(401);
    }

    public function test_logout_only_revokes_current_token_leaving_other_tokens_active(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $tokenA = $user->createToken('device-phone', ['mobile'])->plainTextToken;
        $tokenB = $user->createToken('device-tablet', ['mobile'])->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 2);

        // Logout using Token A
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        auth()->forgetGuards();

        // Token A is revoked
        $resA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/auth/me');
        $resA->assertStatus(401);

        auth()->forgetGuards();

        // Token B remains valid
        $resB = $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/auth/me');
        $resB->assertStatus(200);
        $resB->assertJson(['data' => ['id' => $user->id]]);
    }

    // ==========================================
    // 7. RATE LIMITING TESTS
    // ==========================================

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'invalid-password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'invalid-password',
        ]);

        $response->assertStatus(429);
    }
}
