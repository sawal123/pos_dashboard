<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
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

    public function test_email_collision_response_is_stable_and_decoy_cannot_verify_or_resend(): void
    {
        $realRegistration = $this->register();
        $realId = (string) $realRegistration->json('data.registration_id');
        $key = 'd03-collision-stable-key';

        $first = $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => $key,
        ]);
        $second = $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => $key,
        ]);

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(array_keys($first->json('data')), array_keys($second->json('data')));

        $decoyId = (string) $first->json('data.registration_id');
        $realVerify = $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $realId,
            'code' => '000000',
        ]);
        $decoyVerify = $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $decoyId,
            'code' => '000000',
        ]);
        $this->assertSame($realVerify->status(), $decoyVerify->status());
        $this->assertSame($realVerify->json(), $decoyVerify->json());
        $this->assertNull($realVerify->headers->get('Retry-After'));
        $this->assertNull($decoyVerify->headers->get('Retry-After'));

        $realCooldown = $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $realId,
        ]);
        $decoyCooldown = $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $decoyId,
        ]);
        $this->assertSame($realCooldown->status(), $decoyCooldown->status());
        $this->assertSame($realCooldown->json(), $decoyCooldown->json());
        $this->assertNotNull($realCooldown->headers->get('Retry-After'));
        $this->assertNotNull($decoyCooldown->headers->get('Retry-After'));

        Carbon::setTestNow(now()->addSeconds(61));

        $realResend = $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $realId,
        ]);
        $decoyResend = $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $decoyId,
        ]);
        $this->assertSame($realResend->status(), $decoyResend->status());
        $this->assertSame($realResend->json('data.state'), $decoyResend->json('data.state'));
        $this->assertSame($realResend->json('data.otp_expires_at'), $decoyResend->json('data.otp_expires_at'));
        $this->assertSame($realResend->json('data.resend_available_at'), $decoyResend->json('data.resend_available_at'));

        $realCooldownAfterRecovery = $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $realId,
        ]);
        $decoyCooldownAfterRecovery = $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $decoyId,
        ]);
        $this->assertSame($realCooldownAfterRecovery->status(), $decoyCooldownAfterRecovery->status());
        $this->assertSame($realCooldownAfterRecovery->json(), $decoyCooldownAfterRecovery->json());
        $this->assertNotNull($realCooldownAfterRecovery->headers->get('Retry-After'));
        $this->assertNotNull($decoyCooldownAfterRecovery->headers->get('Retry-After'));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('outlets', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Mail::assertSent(EmailVerificationOtpMail::class, 2);
    }

    public function test_real_and_collision_registration_ids_are_deterministic_uuid_v4_values(): void
    {
        $realId = $this->jsonRegistrationId();
        $firstDecoy = $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => 'd03-uuid-decoy-key-0001',
        ])->json('data.registration_id');
        $sameDecoy = $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => 'd03-uuid-decoy-key-0001',
        ])->json('data.registration_id');
        $secondDecoy = $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => 'd03-uuid-decoy-key-0002',
        ])->json('data.registration_id');

        $this->assertUuidV4((string) $realId);
        $this->assertUuidV4((string) $firstDecoy);
        $this->assertSame($firstDecoy, $sameDecoy);
        $this->assertNotSame($firstDecoy, $secondDecoy);
        $this->assertNotSame($realId, $firstDecoy);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('outlets', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_collision_idempotency_key_conflict_is_consistent_for_changed_payload(): void
    {
        $this->register();
        $key = 'd03-collision-conflict-key';

        $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => $key,
        ])->assertStatus(202);

        $this->postJson('/api/v2/auth/register', [
            ...$this->payload(),
            'name' => 'Different Name',
        ], ['Idempotency-Key' => $key])
            ->assertStatus(409)
            ->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);
    }

    public function test_collision_key_cannot_be_reused_for_a_new_email(): void
    {
        $this->register();

        $this->postJson('/api/v2/auth/register', $this->payload(), [
            'Idempotency-Key' => 'd03-registration-key-0002',
        ])->assertStatus(202);

        $response = $this->postJson('/api/v2/auth/register', [
            ...$this->payload(),
            'email' => 'new-email@example.com',
        ], ['Idempotency-Key' => 'd03-registration-key-0002']);

        $response->assertStatus(409)->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pending_registrations', 1);
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_real_registration_key_cannot_be_reused_for_a_new_email(): void
    {
        $this->register();

        $response = $this->postJson('/api/v2/auth/register', [
            ...$this->payload(),
            'email' => 'another-new-email@example.com',
        ], ['Idempotency-Key' => 'd03-registration-key-0001']);

        $response->assertStatus(409)->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pending_registrations', 1);
    }

    public function test_idempotency_reservation_works_with_database_backed_cache(): void
    {
        config([
            'cache.default' => 'database',
            'cache.stores.database.connection' => (string) config('database.default'),
        ]);
        Cache::purge('database');

        $this->register();

        $this->postJson('/api/v2/auth/register', [
            ...$this->payload(),
            'email' => 'database-cache-new-email@example.com',
        ], ['Idempotency-Key' => 'd03-registration-key-0001'])
            ->assertStatus(409)
            ->assertJson(['code' => 'IDEMPOTENCY_KEY_REUSED']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pending_registrations', 1);
        $this->assertDatabaseCount('businesses', 0);
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

    public function test_locked_challenge_can_be_recovered_after_cooldown_without_duplicate_provisioning(): void
    {
        $registrationId = $this->jsonRegistrationId();
        $oldCode = $this->sentCode();

        $this->lockRegistration($registrationId);

        $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $registrationId,
        ])->assertStatus(429)->assertJson(['code' => 'OTP_RESEND_COOLDOWN']);

        Carbon::setTestNow(now()->addSeconds(61));

        $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $registrationId,
        ])->assertStatus(202);

        $newCode = $this->latestSentCode();

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $oldCode,
        ])->assertStatus(422)->assertJson(['code' => 'OTP_INVALID']);

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $newCode,
        ])->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('outlets', 1);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_recovery_rate_limit_remains_enforced_after_lockout(): void
    {
        config([
            'auth_otp.rate_limits.resend_subject' => ['max_attempts' => 1, 'decay_seconds' => 600],
            'auth_otp.rate_limits.resend_email' => ['max_attempts' => 1, 'decay_seconds' => 600],
            'auth_otp.rate_limits.resend_ip' => ['max_attempts' => 1, 'decay_seconds' => 600],
        ]);

        $registrationId = $this->jsonRegistrationId();
        $this->lockRegistration($registrationId);
        Carbon::setTestNow(now()->addSeconds(61));

        $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $registrationId,
        ])->assertStatus(202);

        $this->postJson('/api/v2/auth/otp/resend', [
            'registration_id' => $registrationId,
        ])->assertStatus(429)->assertJson(['code' => 'OTP_RATE_LIMITED']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_business_slug_is_bounded_for_long_and_unicode_names(): void
    {
        $names = [
            str_repeat('a', 255),
            'Toko 東京',
            '東京',
        ];

        foreach ($names as $index => $businessName) {
            $payload = [
                ...$this->payload(),
                'name' => 'Slug User '.$index,
                'email' => 'slug-'.$index.'@example.com',
                'business_name' => $businessName,
            ];
            $registrationId = (string) $this->postJson('/api/v2/auth/register', $payload, [
                'Idempotency-Key' => 'd03-slug-key-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            ])->json('data.registration_id');

            $this->postJson('/api/v2/auth/otp/verify', [
                'registration_id' => $registrationId,
                'code' => $this->latestSentCode(),
            ])->assertOk();

            $business = Business::query()->latest('id')->firstOrFail();
            $this->assertLessThanOrEqual(255, strlen((string) $business->slug));
            $this->assertMatchesRegularExpression('/-[a-z0-9]{12}$/', (string) $business->slug);
        }

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('businesses', 3);
        $this->assertDatabaseCount('outlets', 3);
        $this->assertDatabaseCount('subscriptions', 3);
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

    public function test_fortify_verification_does_not_bypass_pending_otp_or_api_login(): void
    {
        $this->skipUnlessFortifyHas(Features::emailVerification());

        $registrationId = $this->jsonRegistrationId();
        $user = User::query()->firstOrFail();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/api/auth/login', [
            'email' => 'new-user@example.com',
            'password' => 'ValidPassword123!',
        ])->assertStatus(403)->assertJson(['code' => 'EMAIL_NOT_VERIFIED']);

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => '000000',
        ])->assertStatus(422)->assertJson(['code' => 'OTP_INVALID']);
        $this->assertDatabaseCount('businesses', 0);

        $this->postJson('/api/v2/auth/otp/verify', [
            'registration_id' => $registrationId,
            'code' => $this->sentCode(),
        ])->assertOk();

        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('business_user', 1);
        $this->assertDatabaseCount('outlets', 1);
        $this->assertDatabaseCount('subscriptions', 1);

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

    private function latestSentCode(): string
    {
        return (string) Mail::sent(EmailVerificationOtpMail::class)->last()->code;
    }

    private function lockRegistration(string $registrationId): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v2/auth/otp/verify', [
                'registration_id' => $registrationId,
                'code' => '000000',
            ]);
        }
    }

    private function assertUuidV4(string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
            $value,
        );
    }
}
