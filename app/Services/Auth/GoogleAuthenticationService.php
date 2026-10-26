<?php

namespace App\Services\Auth;

use App\Models\AuthIdentity;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

final class GoogleAuthenticationService
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $verifier,
    ) {}

    /**
     * Verify the assertion, atomically create a Google-only account when
     * required, and issue a mobile Sanctum token only after policy checks.
     *
     * @return array{user: User, token: string}
     */
    public function authenticate(string $idToken, string $clientPlatform): array
    {
        $claims = $this->verifier->verify($idToken, $clientPlatform);

        try {
            $user = DB::transaction(function () use ($claims): User {
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
                    throw new GoogleAuthenticationException(
                        'ACCOUNT_LINK_REQUIRED',
                        'This email is already used by another account.',
                    );
                }

                $user = User::create([
                    'name' => $this->safeName($claims),
                    'email' => $claims->email,
                    'password' => Hash::make(Str::random(96)),
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
                    return $this->issueToken($user);
                }
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [$claims->email])->exists()) {
                throw new GoogleAuthenticationException(
                    'ACCOUNT_LINK_REQUIRED',
                    'This email is already used by another account.',
                    $exception,
                );
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

        return $this->issueToken($user);
    }

    /** @return array{user: User, token: string} */
    private function issueToken(User $user): array
    {
        if ($user->two_factor_confirmed_at !== null) {
            throw new GoogleAuthenticationException(
                'TWO_FACTOR_REQUIRED',
                'Two-factor authentication is required.',
            );
        }

        return [
            'user' => $user,
            'token' => $user->createToken('mobile-api', ['mobile'])->plainTextToken,
        ];
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
