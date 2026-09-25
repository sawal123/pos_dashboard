<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class DashboardBusinessContext
{
    public const SESSION_KEY = 'dashboard.current_business_id';

    /**
     * Cache for accessible businesses per user within request lifecycle.
     *
     * @var array<int, Collection<int, Business>>
     */
    protected array $businessesCache = [];

    /**
     * Cache for current active business per user within request lifecycle.
     *
     * @var array<int, Business|null>
     */
    protected array $currentCache = [];

    /**
     * Retrieve all accessible businesses for the given user, ordered deterministically by ID.
     *
     * @return Collection<int, Business>
     */
    public function businesses(User $user): Collection
    {
        if (isset($this->businessesCache[$user->id])) {
            return $this->businessesCache[$user->id];
        }

        /** @var Collection<int, Business> $businesses */
        $businesses = $user->businesses()
            ->with('subscription')
            ->orderBy('businesses.id', 'asc')
            ->get();

        $this->businessesCache[$user->id] = $businesses;

        return $businesses;
    }

    /**
     * Resolve the current active business for the given user.
     *
     * Validates against user membership and recovers safely from stale or forged sessions.
     */
    public function current(User $user): ?Business
    {
        if (array_key_exists($user->id, $this->currentCache)) {
            return $this->currentCache[$user->id];
        }

        $accessibleBusinesses = $this->businesses($user);

        if ($accessibleBusinesses->isEmpty()) {
            if (session()->has(self::SESSION_KEY)) {
                session()->forget(self::SESSION_KEY);
            }

            $this->currentCache[$user->id] = null;

            return null;
        }

        $sessionId = session(self::SESSION_KEY);

        if ($sessionId !== null) {
            $matched = $accessibleBusinesses->firstWhere('id', (int) $sessionId);

            if ($matched !== null) {
                $this->currentCache[$user->id] = $matched;

                return $matched;
            }
        }

        // Fallback: deterministic first business (businesses.id ASC)
        /** @var Business $fallback */
        $fallback = $accessibleBusinesses->first();

        session([self::SESSION_KEY => $fallback->id]);
        $this->currentCache[$user->id] = $fallback;

        return $fallback;
    }

    /**
     * Switch current business for the given user after authorization.
     */
    public function switchTo(User $user, int|Business $business): Business
    {
        $businessId = $business instanceof Business ? $business->id : (int) $business;

        /** @var Business|null $targetBusiness */
        $targetBusiness = $user->businesses()
            ->with('subscription')
            ->where('businesses.id', $businessId)
            ->first();

        if ($targetBusiness === null) {
            abort(403, 'Anda tidak memiliki akses ke bisnis ini.');
        }

        Gate::forUser($user)->authorize('view', $targetBusiness);

        session([self::SESSION_KEY => $targetBusiness->id]);
        $this->currentCache[$user->id] = $targetBusiness;

        return $targetBusiness;
    }

    /**
     * Map current business subscription to presentation state.
     *
     * @return 'subscriber'|'free'|'unknown'
     */
    public function subscriptionState(?Business $business): string
    {
        if ($business === null) {
            return 'unknown';
        }

        if ($business->hasCloudAccess()) {
            return 'subscriber';
        }

        if ($business->subscription !== null && $business->subscription->isFree()) {
            return 'free';
        }

        return 'unknown';
    }

    /**
     * Check if the business currently has active cloud access.
     */
    public function hasCloudAccess(?Business $business): bool
    {
        return (bool) $business?->hasCloudAccess();
    }

    /**
     * Canonical business type of the given business (`cafe` / `laundry` /
     * `grosir`), or null when the type is not determined. Legacy explicit
     * values are normalized; NULL is never coerced to a default.
     */
    public function businessType(?Business $business): ?string
    {
        return $business?->normalizedBusinessType();
    }
}
