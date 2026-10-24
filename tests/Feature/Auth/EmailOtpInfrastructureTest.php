<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\AuthOtpChallenge;
use App\Services\Auth\EmailOtpService;
use App\Services\Auth\OtpException;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailOtpInfrastructureTest extends TestCase
{
    use RefreshDatabase;

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

        parent::tearDown();
    }

    public function test_issue_sends_a_mail_and_persists_only_a_keyed_digest(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('a', 16);

        $result = $service->issue($subject, 'SARI@example.com', ipAddress: '192.0.2.10');
        $code = $this->sentCode();

        $this->assertTrue($result->created);
        $this->assertSame('email_verification', $result->purpose);
        $this->assertSame(1, AuthOtpChallenge::query()->count());

        $challenge = AuthOtpChallenge::query()->findOrFail($result->challengeId);
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
        $this->assertSame(1, AuthOtpChallenge::query()->count());
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
        $this->assertNotNull(AuthOtpChallenge::query()->findOrFail($result->challengeId)->consumed_at);

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

            $this->assertSame($attempt, AuthOtpChallenge::query()->findOrFail($result->challengeId)->failed_attempts);
        }

        $this->expectOtp('OTP_MAX_ATTEMPTS', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'email_verification',
            '000000',
            '192.0.2.13',
        ));

        $challenge = AuthOtpChallenge::query()->findOrFail($result->challengeId);
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

        $active = AuthOtpChallenge::query()->findOrFail($first->challengeId);
        $this->assertNotNull($active->active_key);
        $this->assertNull($active->replaced_at);
        $this->assertSame(1, AuthOtpChallenge::query()->count());
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

        $challenge = AuthOtpChallenge::query()->findOrFail($result->challengeId);
        $this->assertSame(0, $challenge->failed_attempts);
        $this->assertNull($challenge->consumed_at);

        $this->expectOtp('OTP_CHALLENGE_MISMATCH', fn () => $service->verify(
            $result->challengeId,
            $subject,
            'password_reset',
            $code,
            '192.0.2.16',
        ));
        $this->assertNull(AuthOtpChallenge::query()->findOrFail($result->challengeId)->consumed_at);
    }

    public function test_downstream_transaction_rollback_restores_the_challenge(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('i', 16);
        $result = $service->issue($subject, 'rollback@example.com', ipAddress: '192.0.2.17');
        $code = $this->sentCode();

        try {
            DB::transaction(function () use ($service, $result, $subject, $code): void {
                $service->consume($result->challengeId, $subject, 'email_verification', $code);

                throw new RuntimeException('Simulated downstream provisioning failure.');
            });
        } catch (RuntimeException) {
            // Expected: the outer transaction must roll back the nested consume.
        }

        $this->assertNull(AuthOtpChallenge::query()->findOrFail($result->challengeId)->consumed_at);
        $service->verify($result->challengeId, $subject, 'email_verification', $code, '192.0.2.17');
    }

    public function test_unique_active_key_rejects_duplicate_active_state(): void
    {
        $service = app(EmailOtpService::class);
        $subject = 'registration-reference-'.str_repeat('j', 16);
        $result = $service->issue($subject, 'unique@example.com', ipAddress: '192.0.2.18');
        $active = AuthOtpChallenge::query()->findOrFail($result->challengeId);

        $this->expectException(UniqueConstraintViolationException::class);

        AuthOtpChallenge::query()->create([
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
