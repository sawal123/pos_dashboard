<?php

namespace App\Models;

use App\Services\Auth\PasswordRecoveryPolicy;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool|null $password_login_enabled
 * @property bool $is_platform_admin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Business> $businesses
 * @property-read Collection<int, AuthIdentity> $authIdentities
 */
#[Fillable(['name', 'email', 'password', 'password_login_enabled'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_login_enabled' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * The businesses that the user belongs to.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * External authentication identities linked to this user.
     *
     * @return HasMany<AuthIdentity, $this>
     */
    public function authIdentities(): HasMany
    {
        return $this->hasMany(AuthIdentity::class);
    }

    /**
     * Determine if the user belongs to the given business.
     */
    public function belongsToBusiness(Business $business): bool
    {
        return $this->businesses()->where('businesses.id', $business->id)->exists();
    }

    /**
     * Determine if the user owns the given business.
     */
    public function ownsBusiness(Business $business): bool
    {
        return $this->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('role', Business::ROLE_OWNER)
            ->exists();
    }

    /**
     * Determine if the user is a global platform admin.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function passwordLoginIsEnabled(): bool
    {
        return $this->password_login_enabled === true;
    }

    public function sendPasswordResetNotification($token): void
    {
        if (! app(PasswordRecoveryPolicy::class)->isEligible($this)) {
            return;
        }

        $this->notify(new ResetPassword($token));
    }
}
