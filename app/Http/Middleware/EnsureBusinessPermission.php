<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\Authorization\BusinessAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DASH-10B2 — enforce a single permission against the *active* business.
 *
 * The active business always comes from the shared dashboard context (session
 * backed, never a request parameter). A user with no active business is left to
 * the controller, which preserves the existing "no business → empty state"
 * behaviour; a user who does have a business must hold the permission or the
 * request is rejected with 403 (deny-by-default for cashier and unknown roles).
 */
class EnsureBusinessPermission
{
    public function __construct(
        private readonly BusinessAuthorizer $authorizer,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $business = $request->attributes->get('dashboard_business');
        $user = $request->user();

        if (! $business instanceof Business || $user === null) {
            return $next($request);
        }

        $this->authorizer->authorize($user, $business, $permission);

        return $next($request);
    }
}
