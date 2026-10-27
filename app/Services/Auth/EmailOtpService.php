<?php

namespace App\Services\Auth;

use App\Mail\EmailChangeOtpMail;
use App\Mail\EmailVerificationOtpMail;
use App\Models\AuthOtpChallenge;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;
use Throwable;

/**
 * Internal email OTP infrastructure for AUTH-D02.
 *
 * This service intentionally has no User, registration, business, token, or
 * route dependency. AUTH-D03 supplies an opaque pending-registration reference
 * and owns the transaction that updates the account after successful consume.
 */
final class EmailOtpService
{
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    public function __construct(
        private readonly EmailOtpPolicy $policy,
    ) {}

    /**
     * Issue the first challenge. If an unexpired challenge already exists for
     * the same subject/purpose/email, return its safe state without sending a
     * duplicate email. This makes an initial registration retry idempotent.
     */
    public function issue(
        string $subjectReference,
        string $email,
        ?string $purpose = null,
        ?string $ipAddress = null,
    ): OtpChallengeResult {
        $subjectReference = $this->policy->validateSubjectReference($subjectReference);
        $email = $this->policy->normalizeEmail($email);
        $purpose = $this->policy->validatePurpose($purpose ?? $this->purpose());
        $this->ensureSafeDelivery();
        $this->throttleIssue($subjectReference, $email, $ipAddress);

        $activeKey = $this->activeKey($subjectReference, $purpose);
        $connectionName = $this->databaseConnectionName();

        try {
            return DB::connection($connectionName)->transaction(function () use ($subjectReference, $email, $purpose, $activeKey, $connectionName): OtpChallengeResult {
                $now = now();
                $active = AuthOtpChallenge::on($connectionName)
                    ->where('active_key', $activeKey)
                    ->lockForUpdate()
                    ->first();

                if ($active !== null) {
                    if ($active->email !== $email) {
                        throw new OtpException(
                            'OTP_SUBJECT_EMAIL_MISMATCH',
                            'The OTP subject is already bound to a different email.',
                        );
                    }

                    if (! $active->isExpired($now)) {
                        return $this->result($active, false);
                    }

                    $this->replaceActive($active, $now);
                }

                $challenge = $this->newChallenge(
                    subjectReference: $subjectReference,
                    email: $email,
                    purpose: $purpose,
                    activeKey: $activeKey,
                    now: $now,
                    connectionName: $connectionName,
                );

                $this->send($email, $this->plainCode, $challenge->expires_at, $purpose);

                return $this->result($challenge, true);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // A concurrent initial issue may win the nullable-unique active_key
            // race. Resolve the committed winner instead of sending another OTP.
            $winner = AuthOtpChallenge::on($connectionName)
                ->where('active_key', $activeKey)
                ->first();

            if ($winner !== null && $winner->email === $email) {
                return $this->result($winner, false);
            }

            throw new OtpException('OTP_ISSUE_CONFLICT', 'The OTP challenge could not be issued safely.');
        }
    }

    /**
     * Replace the current challenge only after cooldown. The old challenge is
     * kept active until the replacement email send succeeds; if the mail
     * provider throws, the transaction rolls back and the old code remains
     * usable. A commit failure after SMTP acceptance remains an external
     * database/mail consistency limitation documented for AUTH-D03.
     */
    public function resend(
        string $subjectReference,
        string $email,
        ?string $purpose = null,
        ?string $ipAddress = null,
    ): OtpChallengeResult {
        $subjectReference = $this->policy->validateSubjectReference($subjectReference);
        $email = $this->policy->normalizeEmail($email);
        $purpose = $this->policy->validatePurpose($purpose ?? $this->purpose());
        $this->ensureSafeDelivery();
        $this->throttleResend($subjectReference, $email, $ipAddress);

        $activeKey = $this->activeKey($subjectReference, $purpose);
        $connectionName = $this->databaseConnectionName();

        try {
            return DB::connection($connectionName)->transaction(function () use ($subjectReference, $email, $purpose, $activeKey, $connectionName): OtpChallengeResult {
                $now = now();
                $active = AuthOtpChallenge::on($connectionName)
                    ->where('active_key', $activeKey)
                    ->lockForUpdate()
                    ->first();

                if ($active === null) {
                    /** @var AuthOtpChallenge|null $latest */
                    $latest = AuthOtpChallenge::on($connectionName)
                        ->where('subject_reference', $subjectReference)
                        ->where('purpose', $purpose)
                        ->latest('created_at')
                        ->lockForUpdate()
                        ->first();

                    if ($latest === null || ! $latest->isLocked()) {
                        throw new OtpException('OTP_NO_ACTIVE_CHALLENGE', 'No active OTP challenge exists.');
                    }

                    if ($latest->resend_available_at->gt($now)) {
                        throw new OtpException(
                            'OTP_RESEND_COOLDOWN',
                            'The OTP resend cooldown is still active.',
                            (int) max(1, $now->diffInSeconds($latest->resend_available_at)),
                        );
                    }

                    // A locked challenge has no active_key by design. Create a
                    // fresh challenge only after resend throttles and the
                    // persisted cooldown above have both allowed recovery.
                    $replacement = $this->newChallenge(
                        subjectReference: $subjectReference,
                        email: $email,
                        purpose: $purpose,
                        activeKey: null,
                        now: $now,
                        connectionName: $connectionName,
                    );

                    $this->send($email, $this->plainCode, $replacement->expires_at, $purpose);
                    $replacement->forceFill(['active_key' => $activeKey])->save();

                    return $this->result($replacement, true);
                }

                if ($active->email !== $email) {
                    throw new OtpException(
                        'OTP_SUBJECT_EMAIL_MISMATCH',
                        'The OTP subject is already bound to a different email.',
                    );
                }

                if (! $active->isExpired($now) && $active->resend_available_at->gt($now)) {
                    throw new OtpException(
                        'OTP_RESEND_COOLDOWN',
                        'The OTP resend cooldown is still active.',
                        (int) max(1, $now->diffInSeconds($active->resend_available_at)),
                    );
                }

                $replacement = $this->newChallenge(
                    subjectReference: $subjectReference,
                    email: $email,
                    purpose: $purpose,
                    activeKey: null,
                    now: $now,
                    connectionName: $connectionName,
                );

                // Send while the old active row is locked and the new row is
                // not active. A delivery failure rolls back both rows.
                $this->send($email, $this->plainCode, $replacement->expires_at, $purpose);

                $this->replaceActive($active, $now);
                $replacement->forceFill(['active_key' => $activeKey])->save();

                return $this->result($replacement, true);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new OtpException('OTP_RESEND_CONFLICT', 'The OTP challenge could not be replaced safely.');
        }
    }

    /**
     * Validate a code and durably account for invalid attempts.
     *
     * A valid code is not consumed here. AUTH-D03 must call this method before
     * opening its provisioning transaction, then pass the returned validation
     * result to consume() inside that transaction. This boundary is required
     * because rate limiting may use the database-backed cache store.
     */
    public function validate(
        string $challengeId,
        string $subjectReference,
        string $purpose,
        string $code,
        ?string $ipAddress = null,
    ): OtpValidationResult {
        $code = $this->policy->validateCode($code);
        $subjectReference = $this->policy->validateSubjectReference($subjectReference);
        $purpose = $this->policy->validatePurpose($purpose);
        $this->ensureValidationBoundary();
        $this->throttleVerify($subjectReference, $ipAddress);

        $connectionName = $this->databaseConnectionName();
        $result = DB::connection($connectionName)->transaction(function () use ($challengeId, $subjectReference, $purpose, $code, $connectionName): OtpValidationResult|OtpException {
            /** @var AuthOtpChallenge|null $challenge */
            $challenge = AuthOtpChallenge::on($connectionName)
                ->whereKey($challengeId)
                ->lockForUpdate()
                ->first();

            $challenge = $this->verifiableChallenge($challenge, $subjectReference, $purpose);

            $expectedDigest = $this->policy->digest(
                $challenge->subject_reference,
                $challenge->purpose,
                $challenge->challenge_nonce,
                $code,
            );

            if (! hash_equals($challenge->otp_digest, $expectedDigest)) {
                $failedAttempts = $challenge->failed_attempts + 1;
                $challenge->failed_attempts = $failedAttempts;

                if ($failedAttempts >= $challenge->max_attempts) {
                    $challenge->locked_at = now();
                    $challenge->active_key = null;
                    $challenge->save();

                    return new OtpException('OTP_MAX_ATTEMPTS', 'The OTP challenge is locked.');
                }

                $challenge->save();

                return new OtpException('OTP_INVALID', 'The OTP code is invalid.');
            }

            return new OtpValidationResult(
                challengeId: $challenge->id,
                subjectReference: $challenge->subject_reference,
                purpose: $challenge->purpose,
                digest: $expectedDigest,
                validatedAt: now(),
            );
        }, 3);

        if ($result instanceof OtpException) {
            throw $result;
        }

        return $result;
    }

    /**
     * Compatibility helper for standalone callers. Provisioning integrations
     * must call validate() before opening their outer transaction.
     */
    public function verify(
        string $challengeId,
        string $subjectReference,
        string $purpose,
        string $code,
        ?string $ipAddress = null,
    ): OtpVerificationResult {
        $validation = $this->validate($challengeId, $subjectReference, $purpose, $code, $ipAddress);

        return $this->consume($validation);
    }

    /**
     * Validate, run the caller's state transition, and consume the OTP in one
     * transaction. This is used when the protected account mutation must be
     * atomic with OTP consumption.
     */
    public function verifyAndConsume(
        string $challengeId,
        string $subjectReference,
        string $purpose,
        string $code,
        ?string $ipAddress,
        Closure $onValid,
    ): mixed {
        $code = $this->policy->validateCode($code);
        $subjectReference = $this->policy->validateSubjectReference($subjectReference);
        $purpose = $this->policy->validatePurpose($purpose);
        $this->throttleVerify($subjectReference, $ipAddress);
        $connectionName = $this->databaseConnectionName();

        $result = DB::connection($connectionName)->transaction(function () use ($challengeId, $subjectReference, $purpose, $code, $connectionName, $onValid): mixed {
            /** @var AuthOtpChallenge|null $challenge */
            $challenge = AuthOtpChallenge::on($connectionName)
                ->whereKey($challengeId)
                ->lockForUpdate()
                ->first();

            $challenge = $this->verifiableChallenge($challenge, $subjectReference, $purpose);
            $expectedDigest = $this->policy->digest(
                $challenge->subject_reference,
                $challenge->purpose,
                $challenge->challenge_nonce,
                $code,
            );

            if (! hash_equals($challenge->otp_digest, $expectedDigest)) {
                $failedAttempts = $challenge->failed_attempts + 1;
                $challenge->failed_attempts = $failedAttempts;

                if ($failedAttempts >= $challenge->max_attempts) {
                    $challenge->locked_at = now();
                    $challenge->active_key = null;
                    $challenge->save();

                    return new OtpException('OTP_MAX_ATTEMPTS', 'The OTP challenge is locked.');
                }

                $challenge->save();

                return new OtpException('OTP_INVALID', 'The OTP code is invalid.');
            }

            $value = $onValid($challenge);
            $challenge->consumed_at = now();
            $challenge->active_key = null;
            $challenge->save();

            return $value;
        }, 3);

        if ($result instanceof OtpException) {
            throw $result;
        }

        return $result;
    }

    /**
     * Consume only a result produced by validate().
     *
     * The re-check is intentionally performed under a row lock. It prevents a
     * replaced, expired, locked, or concurrently consumed challenge from being
     * accepted after the separate validation phase.
     */
    public function consume(OtpValidationResult $validation): OtpVerificationResult
    {
        $connectionName = $this->databaseConnectionName();

        return DB::connection($connectionName)->transaction(function () use ($validation, $connectionName): OtpVerificationResult {
            /** @var AuthOtpChallenge|null $challenge */
            $challenge = AuthOtpChallenge::on($connectionName)
                ->whereKey($validation->challengeId)
                ->lockForUpdate()
                ->first();

            $challenge = $this->verifiableChallenge($challenge, $validation->subjectReference, $validation->purpose);

            if (! hash_equals($challenge->otp_digest, $validation->digest)) {
                throw new OtpException('OTP_VALIDATION_STALE', 'The OTP validation result is no longer valid.');
            }

            $consumedAt = now();
            $challenge->consumed_at = $consumedAt;
            $challenge->active_key = null;
            $challenge->save();

            return new OtpVerificationResult(
                challengeId: $challenge->id,
                purpose: $challenge->purpose,
                consumedAt: $consumedAt,
            );
        }, 3);
    }

    /**
     * Return non-secret state for an authorized integration caller.
     *
     * @return array{challenge_id: string, purpose: string, status: string, failed_attempts: int, attempts_remaining: int, expires_at: string, resend_available_at: string}
     */
    public function state(string $challengeId, string $subjectReference, string $purpose): array
    {
        $subjectReference = $this->policy->validateSubjectReference($subjectReference);
        $purpose = $this->policy->validatePurpose($purpose);

        /** @var AuthOtpChallenge|null $challenge */
        $challenge = AuthOtpChallenge::on($this->databaseConnectionName())->whereKey($challengeId)->first();

        if ($challenge === null || $challenge->subject_reference !== $subjectReference || $challenge->purpose !== $purpose) {
            throw new OtpException('OTP_CHALLENGE_NOT_FOUND', 'The OTP challenge is invalid.');
        }

        $status = match (true) {
            $challenge->isConsumed() => 'consumed',
            $challenge->isReplaced() => 'replaced',
            $challenge->isLocked() => 'locked',
            $challenge->isExpired() => 'expired',
            default => 'pending',
        };

        return [
            'challenge_id' => $challenge->id,
            'purpose' => $challenge->purpose,
            'status' => $status,
            'failed_attempts' => $challenge->failed_attempts,
            'attempts_remaining' => max(0, $challenge->max_attempts - $challenge->failed_attempts),
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'resend_available_at' => $challenge->resend_available_at->toIso8601String(),
        ];
    }

    private string $plainCode;

    private function verifiableChallenge(
        ?AuthOtpChallenge $challenge,
        string $subjectReference,
        string $purpose,
    ): AuthOtpChallenge {
        if ($challenge === null) {
            throw new OtpException('OTP_CHALLENGE_NOT_FOUND', 'The OTP challenge is invalid.');
        }

        if ($challenge->subject_reference !== $subjectReference || $challenge->purpose !== $purpose) {
            throw new OtpException('OTP_CHALLENGE_MISMATCH', 'The OTP challenge is invalid.');
        }

        if ($challenge->isConsumed()) {
            throw new OtpException('OTP_ALREADY_USED', 'The OTP challenge has already been consumed.');
        }

        if ($challenge->isReplaced()) {
            throw new OtpException('OTP_REPLACED', 'The OTP challenge has been replaced.');
        }

        if ($challenge->isLocked()) {
            throw new OtpException('OTP_MAX_ATTEMPTS', 'The OTP challenge is locked.');
        }

        if ($challenge->isExpired()) {
            throw new OtpException('OTP_EXPIRED', 'The OTP challenge has expired.');
        }

        return $challenge;
    }

    private function newChallenge(
        string $subjectReference,
        string $email,
        string $purpose,
        ?string $activeKey,
        CarbonInterface $now,
        string $connectionName,
    ): AuthOtpChallenge {
        $this->plainCode = $this->policy->generateCode();
        $nonce = Str::random(48);
        $expiresAt = $now->copy()->addMinutes($this->policy->ttlMinutes());

        return AuthOtpChallenge::on($connectionName)->create([
            'id' => (string) Str::uuid(),
            'subject_reference' => $subjectReference,
            'purpose' => $purpose,
            'email' => $email,
            'otp_digest' => $this->policy->digest($subjectReference, $purpose, $nonce, $this->plainCode),
            'challenge_nonce' => $nonce,
            'active_key' => $activeKey,
            'failed_attempts' => 0,
            'max_attempts' => $this->policy->maxAttempts(),
            'expires_at' => $expiresAt,
            'resend_available_at' => $now->copy()->addSeconds($this->policy->resendCooldownSeconds()),
            'last_sent_at' => $now,
        ]);
    }

    private function replaceActive(AuthOtpChallenge $challenge, CarbonInterface $now): void
    {
        $challenge->forceFill([
            'active_key' => null,
            'replaced_at' => $now,
        ])->save();
    }

    private function result(AuthOtpChallenge $challenge, bool $created): OtpChallengeResult
    {
        return new OtpChallengeResult(
            challengeId: $challenge->id,
            purpose: $challenge->purpose,
            expiresAt: $challenge->expires_at,
            resendAvailableAt: $challenge->resend_available_at,
            created: $created,
        );
    }

    private function send(string $email, string $code, CarbonInterface $expiresAt, string $purpose): void
    {
        $mailer = (string) config('auth_otp.mailer', 'log');
        $mailable = $purpose === EmailChangeService::PURPOSE
            ? new EmailChangeOtpMail($code, $expiresAt)
            : new EmailVerificationOtpMail($code, $expiresAt);

        try {
            Mail::mailer($mailer)->to($email)->send($mailable);
        } catch (Throwable $exception) {
            app(AuthSecurityAuditService::class)->record('auth.otp.delivery_failed', [
                'status' => 'failed',
                'error_category' => 'mail_provider',
                'provider' => $purpose,
            ]);

            throw new OtpException('OTP_DELIVERY_FAILED', 'The OTP email could not be delivered safely.', 0, $exception);
        }
    }

    private function ensureSafeDelivery(): void
    {
        $mailer = (string) config('auth_otp.mailer', 'log');
        $allowedMailers = (array) config('auth_otp.allowed_mailers', []);

        if (! in_array($mailer, $allowedMailers, true)) {
            throw new OtpException(
                'OTP_MAIL_DELIVERY_UNSAFE',
                'The configured OTP mailer is not safe for delivery.',
            );
        }

        if (Mail::getFacadeRoot() instanceof MailFake) {
            return;
        }
    }

    private function throttleIssue(string $subjectReference, string $email, ?string $ipAddress): void
    {
        $this->hitLimit('issue_subject', $subjectReference);
        $this->hitLimit('issue_email', $email);
        $this->hitLimit('issue_ip', $ipAddress ?: 'unknown');
    }

    private function throttleResend(string $subjectReference, string $email, ?string $ipAddress): void
    {
        $this->hitLimit('resend_subject', $subjectReference);
        $this->hitLimit('resend_email', $email);
        $this->hitLimit('resend_ip', $ipAddress ?: 'unknown');
    }

    private function throttleVerify(string $subjectReference, ?string $ipAddress): void
    {
        $this->hitLimit('verify_subject', $subjectReference);
        $this->hitLimit('verify_ip', $ipAddress ?: 'unknown');
    }

    private function hitLimit(string $bucket, string $identifier): void
    {
        /** @var array{max_attempts: int, decay_seconds: int} $limit */
        $limit = (array) config('auth_otp.rate_limits.'.$bucket, [
            'max_attempts' => 5,
            'decay_seconds' => 600,
        ]);
        $key = 'auth-otp:'.$bucket.':'.$this->policy->identifierKey($identifier);
        $maxAttempts = (int) $limit['max_attempts'];
        $decaySeconds = (int) $limit['decay_seconds'];

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            app(AuthSecurityAuditService::class)->record('auth.otp.rate_limited', [
                'status' => 'rejected',
                'error_category' => $bucket,
                'provider' => 'otp',
                'retry_after' => RateLimiter::availableIn($key),
            ]);

            throw new OtpException(
                'OTP_RATE_LIMITED',
                'The OTP operation is rate limited.',
                RateLimiter::availableIn($key),
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function activeKey(string $subjectReference, string $purpose): string
    {
        return $this->policy->identifierKey($subjectReference.'|'.$purpose);
    }

    private function databaseConnectionName(): string
    {
        return (string) config('database.default', 'sqlite');
    }

    private function ensureValidationBoundary(): void
    {
        if (DB::connection($this->databaseConnectionName())->transactionLevel() > 0) {
            throw new OtpException(
                'OTP_VALIDATION_TRANSACTION_ACTIVE',
                'OTP validation must run before the provisioning transaction starts.',
            );
        }
    }

    private function purpose(): string
    {
        return (string) config('auth_otp.purpose', self::PURPOSE_EMAIL_VERIFICATION);
    }
}
