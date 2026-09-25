<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;

/**
 * Session middleware that keeps the one-time invitation token out of the
 * session store.
 *
 * Normally every GET request records its own full URL as `_previous.url`, and
 * an unauthenticated POST records `url.intended`. Because an invitation link
 * carries a one-time secret in its path, either value would write the plaintext
 * token into the (database-backed) session. Invitation routes skip that
 * bookkeeping instead; a user resumes by reopening the emailed link.
 */
class StartSession extends BaseStartSession
{
    protected function storeCurrentUrl(Request $request, $session)
    {
        if (! $request->routeIs('invitations.*')) {
            parent::storeCurrentUrl($request, $session);

            return;
        }

        $intended = $session->get('url.intended');

        if (is_string($intended) && str_contains($intended, '/invitations/')) {
            $session->forget('url.intended');
        }
    }
}
