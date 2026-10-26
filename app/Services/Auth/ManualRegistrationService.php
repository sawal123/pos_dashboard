<?php

namespace App\Services\Auth;

use App\Models\AuthOtpChallenge;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ManualRegistrationService
{
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
     * @return array{registration: PendingRegistration, challenge: OtpChallengeResult}
     */
    public function resend(string $registrationId, ?string $ipAddress = null): array
    {
        $registration = PendingRegistration::query()->find($registrationId);

        if ($registration === null || ! $registration->isPending()) {
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
     * Validate outside provisioning, then consume and provision atomically.
     *
     * @return array{state: string, provisioning: string}
     */
    public function verify(string $registrationId, string $code, ?string $ipAddress = null): array
    {
        $registration = PendingRegistration::query()->find($registrationId);

        if ($registration === null) {
            throw new ManualRegistrationException('PENDING_EMAIL', 'The pending registration is not available.');
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

                if ($user === null || (string) $user->email !== (string) $lockedRegistration->normalized_email) {
                    throw new ManualRegistrationException('AUTH_PROVISIONING_PENDING', 'Registration recovery is required.');
                }

                if ($user->email_verified_at !== null) {
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

        return $base.'-'.Str::lower(Str::random(12));
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
}
