<?php

namespace App\Services\Auth;

use App\Models\AuthOtpChallenge;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class ManualRegistrationService
{
    private const IDEMPOTENCY_RESERVATION_TTL_SECONDS = 315_360_000;

    public function __construct(
        private readonly EmailOtpPolicy $policy,
        private readonly EmailOtpService $otpService,
    ) {}

    /**
     * Create or resume one pending registration, then issue its first OTP.
     * User and registration state is committed before mail delivery so a
     * provider outage leaves a recoverable pending account.
     *
     * @param  array{name: string, email: string, password: string, business_name: string}  $payload
     * @return array{registration: PendingRegistration|null, challenge: OtpChallengeResult|null, collision: bool}
     */
    public function initiate(array $payload, string $idempotencyKey, ?string $ipAddress = null): array
    {
        $email = $this->policy->normalizeEmail($payload['email']);
        $keyHash = $this->hashIdentifier('idempotency|'.$idempotencyKey);
        $fingerprint = $this->fingerprint($payload);

        $existingRegistration = PendingRegistration::query()
            ->where('idempotency_key_hash', $keyHash)
            ->first();

        if ($existingRegistration !== null && ! hash_equals((string) $existingRegistration->request_fingerprint, $fingerprint)) {
            throw new ManualRegistrationException(
                'IDEMPOTENCY_KEY_REUSED',
                'The idempotency key was already used with a different request.',
            );
        }

        // This reservation is deliberately outside the database transaction.
        // Database-backed Cache::add uses the cache key's unique constraint as
        // the cross-request race barrier without nesting cache SQL in the
        // registration transaction.
        $this->reserveIdempotencyFingerprint($keyHash, $fingerprint);

        try {
            [$registration, $collision] = DB::transaction(function () use ($payload, $email, $keyHash, $fingerprint): array {
                $existing = PendingRegistration::query()
                    ->where('idempotency_key_hash', $keyHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                        throw new ManualRegistrationException(
                            'IDEMPOTENCY_KEY_REUSED',
                            'The idempotency key was already used with a different request.',
                        );
                    }

                    return [$existing, false];
                }

                // A collision is intentionally resolved before a new user is
                // created. The caller receives only a generic accepted shape.
                if (User::query()->where('email', $email)->lockForUpdate()->exists()) {
                    return [null, true];
                }

                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $email,
                    'password' => Hash::make($payload['password']),
                    'password_login_enabled' => true,
                ]);

                $registration = PendingRegistration::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'normalized_email' => $email,
                    'idempotency_key_hash' => $keyHash,
                    'request_fingerprint' => $fingerprint,
                    'business_name_intent' => $payload['business_name'],
                    'status' => PendingRegistration::STATUS_PENDING_EMAIL,
                    'provisioning_status' => PendingRegistration::PROVISIONING_PENDING,
                ]);

                return [$registration, false];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $winner = PendingRegistration::query()
                ->where('idempotency_key_hash', $keyHash)
                ->first();

            if ($winner !== null) {
                if (! hash_equals((string) $winner->request_fingerprint, $fingerprint)) {
                    throw new ManualRegistrationException(
                        'IDEMPOTENCY_KEY_REUSED',
                        'The idempotency key was already used with a different request.',
                        $exception,
                    );
                }

                $registration = $winner;
                $collision = false;
            } else {
                return ['registration' => null, 'challenge' => null, 'collision' => true];
            }
        }

        if ($collision || $registration === null || ! $registration->isPending()) {
            return [
                'registration' => $registration,
                'challenge' => null,
                'collision' => $collision,
            ];
        }

        try {
            $challenge = $this->otpService->issue(
                (string) $registration->getKey(),
                (string) $registration->normalized_email,
                EmailOtpService::PURPOSE_EMAIL_VERIFICATION,
                $ipAddress,
            );
        } catch (OtpException $exception) {
            throw new ManualRegistrationException($exception->errorCode, $exception->getMessage(), $exception);
        }

        return [
            'registration' => $registration,
            'challenge' => $challenge,
            'collision' => false,
        ];
    }

    /**
     * @return array{registration: PendingRegistration|null, challenge: OtpChallengeResult}
     */
    public function resend(string $registrationId, ?string $ipAddress = null): array
    {
        $registration = PendingRegistration::query()->find($registrationId);

        if ($registration === null) {
            return ['registration' => null, 'challenge' => $this->resendDecoy($registrationId, $ipAddress)];
        }

        if (! $registration->isPending()) {
            throw new ManualRegistrationException('PENDING_EMAIL', 'The pending registration is not available.');
        }

        try {
            $challenge = $this->otpService->resend(
                (string) $registration->getKey(),
                (string) $registration->normalized_email,
                EmailOtpService::PURPOSE_EMAIL_VERIFICATION,
                $ipAddress,
            );
        } catch (OtpException $exception) {
            throw new ManualRegistrationException($exception->errorCode, $exception->getMessage(), $exception);
        }

        return ['registration' => $registration, 'challenge' => $challenge];
    }

    /**
     * Return a stable, non-persisted collision response. It is keyed only by
     * the caller's idempotency key and contains no account or email data.
     *
     * @return array{registration_id: string, state: string, otp_expires_at: string, resend_available_at: string}
     */
    public function collisionResponse(string $idempotencyKey): array
    {
        $keyHash = $this->hashIdentifier('idempotency|'.$idempotencyKey);

        $data = Cache::rememberForever('auth-registration:collision-response:'.$keyHash, function () use ($keyHash): array {
            $decoyHash = $this->hashIdentifier('collision-decoy|'.$keyHash);
            $decoyUuid = $this->uuidV4FromHash($decoyHash);

            return [
                'registration_id' => $decoyUuid,
                'state' => 'pending_email',
                'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
                'resend_available_at' => now()->addSeconds(60)->toIso8601String(),
            ];
        });

        $this->seedDecoyState($data);

        return $data;
    }

    /**
     * Validate outside provisioning, then consume and provision atomically.
     *
     * @return array{state: string, provisioning: string}
     */
    public function verify(string $registrationId, string $code, ?string $ipAddress = null): array
    {
        $registration = PendingRegistration::query()->find($registrationId);

        if ($registration === null) {
            $this->verifyDecoy($registrationId, $code, $ipAddress);
        }

        if ($registration->isProvisioned()) {
            return ['state' => 'verified', 'provisioning' => 'complete'];
        }

        if (! $registration->isPending()) {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        $challenge = AuthOtpChallenge::query()
            ->where('subject_reference', (string) $registration->getKey())
            ->where('purpose', EmailOtpService::PURPOSE_EMAIL_VERIFICATION)
            ->latest('created_at')
            ->first();

        if ($challenge === null || (string) $challenge->email !== (string) $registration->normalized_email) {
            throw new ManualRegistrationException('PENDING_EMAIL', 'The pending registration is not available.');
        }

        $validation = $this->otpService->validate(
            (string) $challenge->getKey(),
            (string) $registration->getKey(),
            EmailOtpService::PURPOSE_EMAIL_VERIFICATION,
            $code,
            $ipAddress,
        );

        try {
            DB::transaction(function () use ($registrationId, $validation): void {
                $lockedRegistration = PendingRegistration::query()
                    ->whereKey($registrationId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedRegistration === null) {
                    throw new ManualRegistrationException('PENDING_EMAIL', 'The pending registration is not available.');
                }

                if ($lockedRegistration->isProvisioned()) {
                    return;
                }

                if (! $lockedRegistration->isPending()) {
                    throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
                }

                $user = User::query()->whereKey($lockedRegistration->user_id)->lockForUpdate()->first();

                if ($user === null || $this->policy->normalizeEmail((string) $user->email) !== (string) $lockedRegistration->normalized_email) {
                    throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
                }

                // AUTH-D02 consumption is deliberately inside this same outer
                // transaction. A provisioning exception rolls it back.
                $this->otpService->consume($validation);

                $user->forceFill(['email_verified_at' => now()])->save();
                $this->provision($lockedRegistration, $user);

                $lockedRegistration->forceFill([
                    'status' => PendingRegistration::STATUS_VERIFIED,
                    'provisioning_status' => PendingRegistration::PROVISIONING_COMPLETE,
                    'verified_at' => now(),
                ])->save();
            }, 3);
        } catch (ManualRegistrationException $exception) {
            throw $exception;
        } catch (OtpException $exception) {
            throw new ManualRegistrationException($exception->errorCode, $exception->getMessage(), $exception);
        } catch (QueryException $exception) {
            throw new ManualRegistrationException(
                'AUTH_PROVISIONING_PENDING',
                'Registration provisioning could not be completed safely.',
                $exception,
            );
        }

        return ['state' => 'verified', 'provisioning' => 'complete'];
    }

    private function resendDecoy(string $registrationId, ?string $ipAddress): OtpChallengeResult
    {
        $this->throttleDecoy('resend', $registrationId, $ipAddress);
        $state = $this->decoyState($registrationId);
        $now = now();

        if ($state['resend_available_at']->gt($now)) {
            throw new OtpException(
                'OTP_RESEND_COOLDOWN',
                'The OTP resend cooldown is still active.',
                (int) max(1, $now->diffInSeconds($state['resend_available_at'])),
            );
        }

        $state = [
            'failed_attempts' => 0,
            'locked' => false,
            'expires_at' => $now->copy()->addMinutes($this->policy->ttlMinutes()),
            'resend_available_at' => $now->copy()->addSeconds($this->policy->resendCooldownSeconds()),
        ];
        $this->putDecoyState($registrationId, $state);

        return new OtpChallengeResult(
            challengeId: $registrationId,
            purpose: EmailOtpService::PURPOSE_EMAIL_VERIFICATION,
            expiresAt: $state['expires_at'],
            resendAvailableAt: $state['resend_available_at'],
            created: true,
        );
    }

    private function verifyDecoy(string $registrationId, string $code, ?string $ipAddress): never
    {
        $this->throttleDecoy('verify', $registrationId, $ipAddress);
        $state = $this->decoyState($registrationId);
        $now = now();

        if ($state['locked'] || $state['failed_attempts'] >= $this->policy->maxAttempts()) {
            throw new OtpException('OTP_MAX_ATTEMPTS', 'The OTP challenge is locked.');
        }

        if ($state['expires_at']->lte($now)) {
            throw new OtpException('OTP_EXPIRED', 'The OTP challenge has expired.');
        }

        $state['failed_attempts']++;

        if ($state['failed_attempts'] >= $this->policy->maxAttempts()) {
            $state['locked'] = true;
            $this->putDecoyState($registrationId, $state);

            throw new OtpException('OTP_MAX_ATTEMPTS', 'The OTP challenge is locked.');
        }

        $this->putDecoyState($registrationId, $state);

        throw new OtpException('OTP_INVALID', 'The OTP code is invalid.');
    }

    /**
     * @return array{failed_attempts: int, locked: bool, expires_at: CarbonInterface, resend_available_at: CarbonInterface}
     */
    private function decoyState(string $registrationId): array
    {
        $cacheKey = $this->decoyStateKey($registrationId);
        $state = Cache::get($cacheKey);

        if (is_array($state) && isset($state['expires_at'], $state['resend_available_at'])) {
            return [
                'failed_attempts' => (int) ($state['failed_attempts'] ?? 0),
                'locked' => (bool) ($state['locked'] ?? false),
                'expires_at' => $this->parseCachedTime($state['expires_at']),
                'resend_available_at' => $this->parseCachedTime($state['resend_available_at']),
            ];
        }

        $now = now();
        $fresh = [
            'failed_attempts' => 0,
            'locked' => false,
            'expires_at' => $now->copy()->addMinutes($this->policy->ttlMinutes()),
            'resend_available_at' => $now->copy()->addSeconds($this->policy->resendCooldownSeconds()),
        ];
        $this->putDecoyState($registrationId, $fresh);

        return $fresh;
    }

    /**
     * @param  array{failed_attempts: int, locked: bool, expires_at: CarbonInterface, resend_available_at: CarbonInterface}  $state
     */
    private function putDecoyState(string $registrationId, array $state): void
    {
        Cache::put($this->decoyStateKey($registrationId), [
            'failed_attempts' => $state['failed_attempts'],
            'locked' => $state['locked'],
            'expires_at' => $state['expires_at']->toIso8601String(),
            'resend_available_at' => $state['resend_available_at']->toIso8601String(),
        ], self::IDEMPOTENCY_RESERVATION_TTL_SECONDS);
    }

    private function parseCachedTime(mixed $value): CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : CarbonImmutable::parse((string) $value);
    }

    /**
     * @param  array{registration_id: string, state: string, otp_expires_at: string, resend_available_at: string}  $data
     */
    private function seedDecoyState(array $data): void
    {
        Cache::add($this->decoyStateKey($data['registration_id']), [
            'failed_attempts' => 0,
            'locked' => false,
            'expires_at' => $data['otp_expires_at'],
            'resend_available_at' => $data['resend_available_at'],
        ], self::IDEMPOTENCY_RESERVATION_TTL_SECONDS);
    }

    private function decoyStateKey(string $registrationId): string
    {
        return 'auth-registration:decoy-state:'.$this->hashIdentifier('decoy-state|'.$registrationId);
    }

    private function throttleDecoy(string $operation, string $registrationId, ?string $ipAddress): void
    {
        $this->hitDecoyLimit($operation.'_subject', $registrationId);

        if ($operation === 'resend') {
            $this->hitDecoyLimit($operation.'_email', $registrationId);
        }

        $this->hitDecoyLimit($operation.'_ip', $ipAddress ?: 'unknown');
    }

    private function hitDecoyLimit(string $bucket, string $identifier): void
    {
        $limit = (array) config('auth_otp.rate_limits.'.$bucket, [
            'max_attempts' => 5,
            'decay_seconds' => 600,
        ]);
        $key = 'auth-otp:'.$bucket.':'.$this->policy->identifierKey('decoy|'.$identifier);
        $maxAttempts = (int) $limit['max_attempts'];
        $decaySeconds = (int) $limit['decay_seconds'];

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new OtpException(
                'OTP_RATE_LIMITED',
                'The OTP operation is rate limited.',
                RateLimiter::availableIn($key),
            );
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function provision(PendingRegistration $registration, User $user): void
    {
        $business = $registration->business_id !== null
            ? Business::query()->whereKey($registration->business_id)->lockForUpdate()->first()
            : null;

        if ($registration->business_id !== null && $business === null) {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        if ($business === null) {
            $business = Business::create([
                'name' => $registration->business_name_intent,
                'slug' => $this->uniqueBusinessSlug($registration->business_name_intent),
                'status' => 'active',
                'business_type' => 'retail',
            ]);

            $registration->forceFill(['business_id' => $business->id])->save();
        } elseif ($business->status !== 'active') {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        $membership = DB::table('business_user')
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            DB::table('business_user')->insert([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'role' => Business::ROLE_OWNER,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ((string) $membership->role !== Business::ROLE_OWNER) {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        $outlet = $registration->outlet_id !== null
            ? Outlet::query()->whereKey($registration->outlet_id)->lockForUpdate()->first()
            : Outlet::query()->where('business_id', $business->id)->where('code', 'OUT-1')->lockForUpdate()->first();

        if ($outlet !== null && (int) $outlet->business_id !== (int) $business->id) {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        if ($outlet === null) {
            $outlet = Outlet::create([
                'business_id' => $business->id,
                'name' => 'Outlet Utama',
                'code' => 'OUT-1',
                'status' => 'active',
            ]);
        }

        $registration->forceFill(['outlet_id' => $outlet->id])->save();

        $subscription = $registration->subscription_id !== null
            ? Subscription::query()->whereKey($registration->subscription_id)->lockForUpdate()->first()
            : Subscription::query()->where('business_id', $business->id)->lockForUpdate()->first();

        if ($subscription !== null && (int) $subscription->business_id !== (int) $business->id) {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        if ($subscription === null) {
            $subscription = Subscription::create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
            ]);
        } elseif (! $subscription->isFree() || $subscription->status !== Subscription::STATUS_ACTIVE) {
            throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
        }

        $registration->forceFill(['subscription_id' => $subscription->id])->save();
    }

    private function uniqueBusinessSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'toko';
        $suffix = Str::lower(Str::random(12));
        $base = Str::substr($base, 0, 255 - Str::length($suffix) - 1);

        return $base.'-'.$suffix;
    }

    private function reserveIdempotencyFingerprint(string $keyHash, string $fingerprint): void
    {
        $cacheKey = 'auth-registration:idempotency-fingerprint:'.$keyHash;
        $stored = Cache::get($cacheKey);

        // Promote reservations written by the previous collision-only
        // implementation so an already-used key cannot become reusable after
        // this release.
        if ($stored === null) {
            $legacyStored = Cache::get('auth-registration:collision-fingerprint:'.$keyHash);

            if ($legacyStored !== null) {
                if (! is_string($legacyStored) || ! hash_equals($legacyStored, $fingerprint)) {
                    $this->throwIdempotencyConflict();
                }

                if (Cache::add($cacheKey, $legacyStored, self::IDEMPOTENCY_RESERVATION_TTL_SECONDS)) {
                    return;
                }

                $stored = Cache::get($cacheKey);
            }
        }

        if (is_string($stored) && hash_equals($stored, $fingerprint)) {
            return;
        }

        if ($stored === null) {
            if (Cache::add($cacheKey, $fingerprint, self::IDEMPOTENCY_RESERVATION_TTL_SECONDS)) {
                return;
            }

            $stored = Cache::get($cacheKey);

            if (is_string($stored) && hash_equals($stored, $fingerprint)) {
                return;
            }
        }

        $this->throwIdempotencyConflict();
    }

    private function throwIdempotencyConflict(): never
    {
        throw new ManualRegistrationException(
            'IDEMPOTENCY_KEY_REUSED',
            'The idempotency key was already used with a different request.',
        );
    }

    /**
     * @param  array{name: string, email: string, password: string, business_name: string}  $payload
     */
    private function fingerprint(array $payload): string
    {
        $canonical = json_encode([
            'name' => $payload['name'],
            'email' => $this->policy->normalizeEmail($payload['email']),
            'password' => $payload['password'],
            'business_name' => $payload['business_name'],
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $canonical, $this->policy->key());
    }

    private function hashIdentifier(string $value): string
    {
        return hash_hmac('sha256', $value, $this->policy->key());
    }

    private function uuidV4FromHash(string $hex): string
    {
        $hex = substr_replace($hex, '4', 12, 1);
        $variant = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        $hex = substr_replace($hex, $variant, 16, 1);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
