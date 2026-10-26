<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ManualRegistrationOtpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth_otp.key' => 'test-auth-d03-key',
            'auth_otp.mailer' => 'smtp',
        ]);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_register_creates_pending_account_without_token_or_provisioning(): void
    {
        $response = $this->register();

        $response->assertStatus(202)
            ->assertJsonPath('data.state', 'pending_email')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'new-user@example.com',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('outlets', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertSent(EmailVerificationOtpMail::class);
    }

    public function test_same_idempotency_key_and_payload_reuses_pending_registration_and_otp(): void
    {
        $first = $this->register();
        $second = $this->register();

        $this->assertSame($first->json('data.registration_id'), $second->json('data.registration_id'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pending_registrations', 1);
        $this->assertDatabaseCount('auth_otp_challenges', 1);
        Mail::assertSent(EmailVerificationOtpMail::class, 1);
    }

    public function test_idempotency_key_reuse_with_different_payload_is_rejected(): void
    {
        $this->register();

        $response = $this->postJson('/api/v2/auth/register', [
            ...$this->payload(),
            'name' => 'Different Name',
        ], ['Idempotency-Key' => 'd03-registration-key-0001']);

        $response->assertStatus(409)->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_email_collision_is_generic_and_does_not_create_or_overwrite_account(): void
    {
        $this->register();

        $response = $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => 'd03-registration-key-0002',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.state', 'pending_email');
        $this->assertNotSame(
            $this->jsonRegistrationId(),
            $response->json('data.registration_id'),
        );
        $this->assertDatabaseCount('users', 1);
        Mail::assertSent(EmailVerificationOtpMail::class, 1);
    }

    public function test_valid_otp_verifies_and_provisions_exactly_one_default_tenant(): void
    {
        $registrationId = $this->jsonRegistrationId();
        $code = $this->sentCode();

        $response = $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $code,
        ]);

        $response->assertOk()->assertJson([
            'data' => [
                'state' => 'verified',
                'provisioning' => 'complete',
            ],
        ]);

        $this->assertNotNull(User::query()->firstOrFail()->fresh()->email_verified_at);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('business_user', 1);
        $this->assertDatabaseHas('business_user', ['role' => Business::ROLE_OWNER]);
        $this->assertDatabaseCount('outlets', 1);
        $this->assertDatabaseHas('outlets', ['code' => 'OUT-1', 'name' => 'Outlet Utama']);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_is_idempotent_after_success(): void
    {
        $registrationId = $this->jsonRegistrationId();
        $code = $this->sentCode();

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => '000000',
        ])->assertOk();

        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('outlets', 1);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_invalid_otp_is_durable_and_five_failures_lock_the_challenge(): void
    {
        $registrationId = $this->jsonRegistrationId();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v2/auth/otp/verify', [
                'registration_id' => $registrationId,
                'code' => '000000',
            ])->assertStatus(422)->assertJson(['code' => 'OTP_INVALID']);
        }

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => '000000',
        ])->assertStatus(422)->assertJson(['code' => 'OTP_MAX_ATTEMPTS']);

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $this->sentCode(),
        ])->assertStatus(422)->assertJson(['code' => 'OTP_MAX_ATTEMPTS']);

        $this->assertDatabaseHas('auth_otp_challenges', [
            'failed_attempts' => 5,
        ]);
        $this->assertNull(User::query()->firstOrFail()->fresh()->email_verified_at);
    }

    public function test_resend_replaces_old_code_after_cooldown(): void
    {
        $registrationId = $this->jsonRegistrationId();
        $oldCode = $this->sentCode();

        $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $registrationId,
        ])->assertStatus(429)->assertJson(['code' => 'OTP_RESEND_COOLDOWN']);

        Carbon::setTestNow(now()->addSeconds(61));

        $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $registrationId,
        ])->assertStatus(202);

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $oldCode,
        ])->assertStatus(422)->assertJson(['code' => 'OTP_INVALID']);
    }

    public function test_pending_user_is_rejected_by_existing_login_until_verified(): void
    {
        $this->register();

        $this->postJson('/api/auth/login', [
            'email' => 'new-user@example.com',
            'password' => 'ValidPassword123!',
        ])->assertStatus(403)->assertJson(['code' => 'EMAIL_NOT_VERIFIED']);

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $this->jsonRegistrationId(),
            'code' => $this->sentCode(),
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'new-user@example.com',
            'password' => 'ValidPassword123!',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_invalid_registration_input_is_rejected(): void
    {
        $this->postJson('/api/v2/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
            'business_name' => '',
        ], ['Idempotency-Key' => 'd03-registration-key-0003'])
            ->assertStatus(422);
    }

    private function register(): TestResponse
    {
        return $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => 'd03-registration-key-0001',
        ]);
    }

    /** @return array{name: string, email: string, password: string, password_confirmation: string, business_name: string} */
    private function payload(): array
    {
        return [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'business_name' => 'Toko Baru',
        ];
    }

    private function jsonRegistrationId(): string
    {
        return (string) $this->register()->json('data.registration_id');
    }

    private function sentCode(): string
    {
        $code = null;

        Mail::assertSent(EmailVerificationOtpMail::class, function (EmailVerificationOtpMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }
}
