<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\UserSiteRepository;

class RequireSiteMembership
{
    public function __construct(
        private readonly UserSiteRepository $userSiteRepository,
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            return $request->wantsJson()
                ? Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401)
                : Response::redirect('/login');
        }

        $siteId = (int) SiteContext::getId();
        $site = $siteId > 0 ? $this->siteRepository->find($siteId) : null;

        if (!$site || !(bool) ($site->is_active ?? true)) {
            return $request->wantsJson()
                ? Response::json(['success' => false, 'message' => 'Site unavailable.'], 403)
                : Response::redirect('/login');
        }

        if (!$this->userSiteRepository->hasAccess(Auth::id(), $siteId)) {
            return $request->wantsJson()
                ? Response::json(['success' => false, 'message' => 'You do not have access to this site.'], 403)
                : Response::redirect('/login');
        }

        $request->setAttribute('site', $site);

        return $next($request);
    }
}
