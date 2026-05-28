<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\UserSiteRepository;

/**
 * Guards non-admin OpenCollab contributor routes.
 *
 * Ensures the authenticated user:
 *   1. Is logged in (redirects to contributor login otherwise).
 *   2. Has been granted access to the current site via the user_sites table.
 *
 * This is intentionally separate from RequireAdminRole — admin routes
 * already carry their own middleware, and contributors must not be
 * implicitly treated as site-admins even if they hold 'agent' role.
 *
 * Register in web.php (replaces RequireContributorAuth where site-scoping
 * is needed):
 *
 *   $router->group(['middleware' => [CheckContributorSiteAccess::class]], function ($router) {
 *       $router->get('/{site}/open-collab/dashboard', [...]);
 *       ...
 *   });
 */
class CheckContributorSiteAccess extends RequireSiteMembership
{
    public function handle(Request $request, callable $next): Response
    {
        $response = parent::handle($request, $next);

        if ($response->getStatusCode() !== 403 || $request->wantsJson()) {
            return $response;
        }

        if (str_contains((string) $response->getContent(), '/login')) {
            die('no');
            return Response::redirect($this->buildLoginPath($request) . '?error=no_site_access');
        }

        return $response;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Build the contributor login URL for the current site slug.
     *
     * Falls back to a generic path so the redirect never hard-errors even
     * if the site context is somehow missing.
     */
    private function buildLoginPath(Request $request): string
    {
        $site = $request->getSite();
        $slug = $site?->slug ?? SiteContext::slug() ?? 'open-collab';

        return '/' . $slug . '/open-collab/login';
    }
}
