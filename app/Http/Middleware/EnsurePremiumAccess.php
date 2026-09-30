<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\Subscription\PremiumPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PREM-D02A — enforce the Premium entitlement on a dashboard route.
 *
 * Every gated route declares the capability it needs, so the policy lives in one
 * place (`config/premium.php` + {@see PremiumPolicy}) instead of being scattered
 * across controllers. RBAC (`business.permission:*`) still applies separately:
 * this middleware only answers "does this business hold an active Cloud
 * entitlement right now?".
 *
 * Denial never locks a business out. A browser request is redirected back to the
 * (ungated) dashboard shell, which shows the real subscription state and links to
 * the billing page, so an expired owner can always see their status and renew. A
 * JSON/API request receives the same stable `CLOUD_SUBSCRIPTION_REQUIRED` code the
 * mobile API already uses.
 */
class EnsurePremiumAccess
{
    public const LOCKED_MESSAGE = 'Fitur ini memerlukan langganan Cloud aktif. Perpanjang langganan dari halaman Langganan.';

    public const CODE_CLOUD_SUBSCRIPTION_REQUIRED = 'CLOUD_SUBSCRIPTION_REQUIRED';

    public function __construct(
        private readonly PremiumPolicy $policy,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $capability = PremiumPolicy::CAPABILITY_WEB_DASHBOARD,
    ): Response {
        $business = $request->attributes->get('dashboard_business');

        // No active business: keep the existing "no business → empty state"
        // behaviour handled by the controllers.
        if ($request->user() === null || ! $business instanceof Business) {
            return $next($request);
        }

        if ($this->policy->allows($business, $capability)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cloud subscription is required.',
                'code' => self::CODE_CLOUD_SUBSCRIPTION_REQUIRED,
            ], 403);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', self::LOCKED_MESSAGE);
    }
}
