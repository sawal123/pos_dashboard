<?php

namespace App\Services\Auth;

use App\Models\AuthIdentity;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves a verified Google subject to a local user, creating and provisioning
 * a Google-only account exactly once when the subject is new.
 *
 * When the verified Google email already belongs to an account whose own email
 * is verified, the identity is linked to that account instead of being refused:
 * both sides have proven control of the same address. Unverified or pending
 * local accounts are never linked automatically.
 *
 * Shared by the mobile token flow (AUTH-D04) and the web session login so both
 * entry points enforce the same identity and collision policy.
 */
final class GoogleAccountService
{
    public function resolveOrProvision(GoogleIdTokenClaims $claims): User
    {
        try {
            return DB::transaction(function () use ($claims): User {
                $identity = AuthIdentity::query()
                    ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                    ->where('provider_subject', $claims->subject)
                    ->lockForUpdate()
                    ->first();

                if ($identity !== null) {
                    $user = User::query()->lockForUpdate()->find($identity->user_id);

                    if ($user === null) {
                        throw new GoogleAuthenticationException(
                            'IDENTITY_CONFLICT',
                            'Google identity ownership is inconsistent.',
                        );
                    }

                    return $user;
                }

                $emailUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [$claims->email])
                    ->lockForUpdate()
                    ->first();

                if ($emailUser !== null) {
                    return $this->linkExistingAccount($emailUser, $claims);
                }

                $user = User::create([
                    'name' => $this->safeName($claims),
                    'email' => $claims->email,
                    'password' => Hash::make(Str::random(96)),
                    'password_login_enabled' => false,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                AuthIdentity::create([
                    'user_id' => $user->id,
                    'provider' => AuthIdentity::PROVIDER_GOOGLE,
                    'provider_subject' => $claims->subject,
                ]);

                $this->provisionDefaultAccount($user);

                return $user;
            }, 3);
        } catch (GoogleAuthenticationException $exception) {
            throw $exception;
        } catch (UniqueConstraintViolationException $exception) {
            $identity = AuthIdentity::query()
                ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                ->where('provider_subject', $claims->subject)
                ->first();

            if ($identity !== null) {
                $user = User::query()->find($identity->user_id);
                if ($user !== null) {
                    return $user;
                }
            }

            $emailUser = User::query()->whereRaw('LOWER(email) = ?', [$claims->email])->first();
            if ($emailUser !== null) {
                return $this->linkExistingAccountSafely($emailUser, $claims);
            }

            throw new GoogleAuthenticationException(
                'IDENTITY_CONFLICT',
                'Google identity could not be linked safely.',
                $exception,
            );
        } catch (Throwable $exception) {
            throw new GoogleAuthenticationException(
                'GOOGLE_PROVISIONING_FAILED',
                'Google account provisioning could not be completed safely.',
                $exception,
            );
        }
    }

    private function linkExistingAccount(User $user, GoogleIdTokenClaims $claims): User
    {
        if (! $user->hasVerifiedEmail() || $this->hasUnprovisionedRegistration($user)) {
            throw new GoogleAuthenticationException(
                'ACCOUNT_LINK_REQUIRED',
                'This email is already used by an unverified account.',
            );
        }

        $identity = AuthIdentity::query()
            ->where('user_id', $user->getKey())
            ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
            ->lockForUpdate()
            ->first();

        if ($identity !== null) {
            if (! hash_equals((string) $identity->provider_subject, $claims->subject)) {
                throw new GoogleAuthenticationException(
                    'IDENTITY_CONFLICT',
                    'Another Google identity is already linked to this account.',
                );
            }

            return $user;
        }

        AuthIdentity::create([
            'user_id' => $user->getKey(),
            'provider' => AuthIdentity::PROVIDER_GOOGLE,
            'provider_subject' => $claims->subject,
        ]);

        return $user;
    }

    private function linkExistingAccountSafely(User $user, GoogleIdTokenClaims $claims): User
    {
        try {
            return DB::transaction(function () use ($user, $claims): User {
                $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                if (! $locked instanceof User) {
                    throw new GoogleAuthenticationException(
                        'IDENTITY_CONFLICT',
                        'Account ownership is inconsistent.',
                    );
                }

                return $this->linkExistingAccount($locked, $claims);
            }, 3);
        } catch (GoogleAuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // A concurrent request may have linked this subject in the meantime.
            $identity = AuthIdentity::query()
                ->where('provider', AuthIdentity::PROVIDER_GOOGLE)
                ->where('provider_subject', $claims->subject)
                ->first();

            if ($identity !== null && (int) $identity->user_id === (int) $user->getKey()) {
                return $user;
            }

            throw new GoogleAuthenticationException(
                'IDENTITY_CONFLICT',
                'Google identity could not be linked safely.',
                $exception,
            );
        }
    }

    private function hasUnprovisionedRegistration(User $user): bool
    {
        $pending = PendingRegistration::query()->where('user_id', $user->getKey())->first();

        return $pending !== null && ! $pending->isProvisioned();
    }

    private function safeName(GoogleIdTokenClaims $claims): string
    {
        if ($claims->name !== null && trim($claims->name) !== '') {
            return trim($claims->name);
        }

        $localPart = strstr($claims->email, '@', true);

        return is_string($localPart) && $localPart !== '' ? $localPart : 'Google User';
    }

    private function provisionDefaultAccount(User $user): void
    {
        $businessName = $user->name.' Store';
        $slugBase = Str::slug($businessName) ?: 'toko';
        $slug = $slugBase.'-'.Str::lower(Str::random(6));

        $business = Business::create([
            'name' => $businessName,
            'slug' => $slug,
            'status' => 'active',
            'business_type' => 'retail',
        ]);

        $user->businesses()->attach($business->id, [
            'role' => Business::ROLE_OWNER,
        ]);

        Outlet::create([
            'business_id' => $business->id,
            'name' => 'Outlet Utama',
            'code' => 'OUT-1',
            'status' => 'active',
        ]);

        Subscription::create([
            'business_id' => $business->id,
            'plan' => Subscription::PLAN_FREE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }
}
