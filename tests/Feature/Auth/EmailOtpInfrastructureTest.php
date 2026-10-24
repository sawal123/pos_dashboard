<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\AuthOtpChallenge;
use App\Services\Auth\EmailOtpService;
use App\Services\Auth\OtpException;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailOtpInfrastructureTest extends TestCase
{
    use DatabaseMigrations;

    private ?string $isolatedOtpDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth_otp.key' => 'test-only-auth-otp-key-that-is-not-a-secret',
            'auth_otp.mailer' => 'smtp',
        ]);

        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        $isolatedOtpDatabase = $this->isolatedOtpDatabase;
        if ($this->isolatedOtpDatabase !== null) {
            DB::purge((string) config('database.default'));
            DB::purge('auth_otp_test');
        }

        parent::tearDown();

        if ($isolatedOtpDatabase !== null) {
            @unlink($isolatedOtpDatabase);
        }
    }

    public function test_issue_sends_a_mail_and_persists_only_a_keyed_digest(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('a', 16);

        $result = $service->issue($subject, 'SARI@example.com', ipAddress: '192.0.2.10');
        $code = $this->sentCode();

        $this->assertTrue($result->created);
        $this->assertSame('email_verification', $result->purpose);
        $this->assertSame(1, $this->challengeQuery()->count());

        $challenge = $this->challengeQuery()->findOrFail($result->challengeId);
        $this->assertSame('sari@example.com', $challenge->email);
        $this->assertNotSame($code, $challenge->otp_digest);
        $this->assertSame(64, strlen($challenge->otp_digest));
        $this->assertStringNotContainsString($code, json_encode($challenge->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($code, json_encode($result->toArray(), JSON_THROW_ON_ERROR));

        Mail::assertSent(EmailVerificationOtpMail::class, function (EmailVerificationOtpMail $mail) use ($code, $result): bool {
            return $mail->code === $code
                && $mail->expiresAt->equalTo($result->expiresAt);
        });
    }

    public function test_initial_issue_retry_returns_the_existing_challenge_without_a_second_mail(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('b', 16);

        $first = $service->issue($subject, 'retry@example.com', ipAddress: '192.0.2.11');
        $second = $service->issue($subject, 'retry@example.com', ipAddress: '192.0.2.11');

        $this->assertTrue($first->created);
        $this->assertFalse($second->created);
        $this->assertSame($first->challengeId, $second->challengeId);
        $this->assertSame(1, $this->challengeQuery()->count());
        Mail::assertSentCount(1);
    }

    public function test_valid_code_is_consumed_once(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('c', 16);
        $result = $service->issue($subject, 'valid@example.com', ipAddress: '192.0.2.12');
        $code = $this->sentCode();

        $verified = $service->verify($result->challengeId, $subject, 'email_verification', $code, '192.0.2.12');

        $this->assertSame($result->challengeId, $verified->challengeId);
        $this->assertDatabaseHas('auth_otp_challenges', [
            'id' => $result->challengeId,
        ]);
        $this->assertNotNull($this->challengeQuery()->findOrFail($result->challengeId)->consumed_at);

        $this->expectOtp('OTP_ALREADY_USED', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'email_verification',
            $code,
            '192.0.2.12',
        ));
    }

    public function test_wrong_attempts_one_through_five_are_enforced(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('d', 16);
        $result = $service->issue($subject, 'attempts@example.com', ipAddress: '192.0.2.13');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->expectOtp('OTP_INVALID', fn () => $service->verify(
                $result->challengeId,
                $subject,
                'email_verification',
                '000000',
                '192.0.2.13',
            ));

            $this->assertSame($attempt, $this->challengeQuery()->findOrFail($result->challengeId)->failed_attempts);
        }

        $this->expectOtp('OTP_MAX_ATTEMPTS', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'email_verification',
            '000000',
            '192.0.2.13',
        ));

        $challenge = $this->challengeQuery()->findOrFail($result->challengeId);
        $this->assertSame(5, $challenge->failed_attempts);
        $this->assertNotNull($challenge->locked_at);
        $this->expectOtp('OTP_MAX_ATTEMPTS', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'email_verification',
            $this->sentCode(),
            '192.0.2.13',
        ));
    }

    public function test_expired_code_is_rejected(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('e', 16);
        $result = $service->issue($subject, 'expired@example.com', ipAddress: '192.0.2.14');
        $code = $this->sentCode();

        Carbon::setTestNow(now()->addMinutes(10)->addSecond());

        $this->expectOtp('OTP_EXPIRED', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'email_verification',
            $code,
            '192.0.2.14',
        ));
    }

    public function test_resend_requires_cooldown_and_replaces_old_challenge(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('f', 16);
        $first = $service->issue($subject, 'resend@example.com', ipAddress: '192.0.2.15');
        $oldCode = $this->sentCode();

        $this->expectOtp('OTP_RESEND_COOLDOWN', fn () => $service->resend(
            $subject,
            'resend@example.com',
            ipAddress: '192.0.2.15',
        ));

        Carbon::setTestNow(now()->addSeconds(61));
        $second = $service->resend($subject, 'resend@example.com', ipAddress: '192.0.2.15');
        $newCode = $this->sentCode();

        $this->assertNotSame($first->challengeId, $second->challengeId);
        $this->expectOtp('OTP_REPLACED', fn () => $service->verify(
            $first->challengeId,
            $subject,
            'email_verification',
            $oldCode,
            '192.0.2.15',
        ));
        $service->verify($second->challengeId, $subject, 'email_verification', $newCode, '192.0.2.15');
    }

    public function test_failed_replacement_delivery_keeps_the_old_challenge_active(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('l', 16);
        $first = $service->issue($subject, 'delivery-failure@example.com', ipAddress: '192.0.2.20');

        Carbon::setTestNow(now()->addSeconds(61));

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('to')
            ->once()
            ->with('delivery-failure@example.com')
            ->andReturnSelf();
        $mailer->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Simulated mail provider outage.'));

        $factory = Mockery::mock(MailFactory::class);
        $factory->shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);
        Mail::swap($factory);

        $this->expectOtp('OTP_DELIVERY_FAILED', fn () => $service->resend(
            $subject,
            'delivery-failure@example.com',
            ipAddress: '192.0.2.20',
        ));

        $active = $this->challengeQuery()->findOrFail($first->challengeId);
        $this->assertNotNull($active->active_key);
        $this->assertNull($active->replaced_at);
        $this->assertSame(1, $this->challengeQuery()->count());
    }

    public function test_subject_and_purpose_mismatch_do_not_mutate_challenge(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('g', 16);
        $result = $service->issue($subject, 'mismatch@example.com', ipAddress: '192.0.2.16');
        $code = $this->sentCode();

        $this->expectOtp('OTP_CHALLENGE_MISMATCH', fn () => $service->verify(
            $result->challengeId,
            'registration-reference-'.str_repeat('h', 16),
            'email_verification',
            $code,
            '192.0.2.16',
        ));

        $challenge = $this->challengeQuery()->findOrFail($result->challengeId);
        $this->assertSame(0, $challenge->failed_attempts);
        $this->assertNull($challenge->consumed_at);

        $this->expectOtp('OTP_CHALLENGE_MISMATCH', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'password_reset',
            $code,
            '192.0.2.16',
        ));
        $this->assertNull($this->challengeQuery()->findOrFail($result->challengeId)->consumed_at);
    }

    public function test_downstream_transaction_rollback_restores_the_challenge(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('i', 16);
        $result = $service->issue($subject, 'rollback@example.com', ipAddress: '192.0.2.17');
        $code = $this->sentCode();
        $validation = $service->validate($result->challengeId, $subject, 'email_verification', $code, '192.0.2.17');

        try {
            DB::transaction(function () use ($service, $validation): void {
                $service->consume($validation);

                throw new RuntimeException('Simulated downstream provisioning failure.');
            });
        } catch (RuntimeException) {
            // Expected: the outer transaction must roll back the nested consume.
        }

        $this->assertNull($this->challengeQuery()->findOrFail($result->challengeId)->consumed_at);
        $service->consume($validation);
    }

    public function test_invalid_code_inside_outer_transaction_keeps_attempt_durable(): void
    {
        $this->useIsolatedOtpConnection();
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('m', 16);
        $result = $service->issue($subject, 'outer-invalid@example.com', ipAddress: '192.0.2.21');

        try {
            DB::transaction(function () use ($service, $result, $subject): void {
                $service->verify($result->challengeId, $subject, 'email_verification', '000000', '192.0.2.21');
            });

            $this->fail('Expected invalid OTP exception.');
        } catch (OtpException $exception) {
            $this->assertSame('OTP_INVALID', $exception->errorCode);
        }

        $this->assertSame(1, $this->challengeQuery()->findOrFail($result->challengeId)->failed_attempts);
    }

    public function test_five_invalid_codes_inside_rolled_back_transactions_lock_the_challenge(): void
    {
        $this->useIsolatedOtpConnection();
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('n', 16);
        $result = $service->issue($subject, 'outer-five@example.com', ipAddress: '192.0.2.22');
        $validCode = $this->sentCode();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                DB::transaction(function () use ($service, $result, $subject): void {
                    $service->verify($result->challengeId, $subject, 'email_verification', '000000', '192.0.2.22');
                });

                $this->fail('Expected invalid OTP exception.');
            } catch (OtpException $exception) {
                $this->assertSame($attempt === 5 ? 'OTP_MAX_ATTEMPTS' : 'OTP_INVALID', $exception->errorCode);
            }
        }

        $challenge = $this->challengeQuery()->findOrFail($result->challengeId);
        $this->assertSame(5, $challenge->failed_attempts);
        $this->assertNotNull($challenge->locked_at);

        $this->expectOtp('OTP_MAX_ATTEMPTS', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'email_verification',
            $validCode,
            '192.0.2.22',
        ));
    }

    public function test_one_validation_result_cannot_be_consumed_twice(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('o', 16);
        $result = $service->issue($subject, 'replay@example.com', ipAddress: '192.0.2.23');
        $validation = $service->validate($result->challengeId, $subject, 'email_verification', $this->sentCode(), '192.0.2.23');

        $service->consume($validation);

        $this->expectOtp('OTP_ALREADY_USED', fn () => $service->consume($validation));
    }

    public function test_unique_active_key_rejects_duplicate_active_state(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('j', 16);
        $result = $service->issue($subject, 'unique@example.com', ipAddress: '192.0.2.18');
        $active = $this->challengeQuery()->findOrFail($result->challengeId);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->challengeQuery()->create([
            'id' => (string) Str::uuid(),
            'subject_reference' => $subject,
            'purpose' => 'email_verification',
            'email' => 'unique@example.com',
            'otp_digest' => str_repeat('a', 64),
            'challenge_nonce' => str_repeat('b', 48),
            'active_key' => $active->active_key,
            'failed_attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'resend_available_at' => now()->addSeconds(60),
            'last_sent_at' => now(),
        ]);
    }

    public function test_unsafe_log_mailer_fails_before_persisting_a_challenge(): void
    {
        Mail::swap(app('mail.manager'));
        config(['auth_otp.mailer' => 'log']);

        $service = app(EmailOtpService::class);

        $this->expectOtp('OTP_MAIL_DELIVERY_UNSAFE', fn () => $service->issue(
            'registration-reference-'.str_repeat('k', 16),
            'unsafe@example.com',
            ipAddress: '192.0.2.19',
        ));

        $this->assertDatabaseCount('auth_otp_challenges', 0);
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

    private function challengeQuery(): Builder
    {
        $connection = config('auth_otp.connection') ?: config('database.default');

        return AuthOtpChallenge::on((string) $connection)->newQuery();
    }

    private function useIsolatedOtpConnection(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pos-auth-otp-');

        if ($path === false) {
            $this->fail('Could not create an isolated OTP test database.');
        }

        $this->isolatedOtpDatabase = $path;
        $defaultConnection = (string) config('database.default');

        config([
            "database.connections.{$defaultConnection}.database" => $path,
            'database.connections.auth_otp_test' => array_merge(
                (array) config("database.connections.{$defaultConnection}"),
                ['database' => $path],
            ),
            'auth_otp.connection' => 'auth_otp_test',
        ]);
        DB::purge($defaultConnection);
        DB::purge('auth_otp_test');

        Artisan::call('migrate', [
            '--database' => $defaultConnection,
            '--path' => database_path('migrations/2026_10_24_000001_create_auth_otp_challenges_table.php'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function expectOtp(string $code, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected OTP exception '.$code.'.');
        } catch (OtpException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
    }
}
