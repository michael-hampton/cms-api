<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\SitePermissionResolver;

class RequireOpenCollabPagePermission
{
    private const PAGE_PERMISSIONS = [
        '#^/[^/]+/open-collab/admin/articles/pending$#' => ['content.review', 'content.approve', 'content.reject'],
        '#^/[^/]+/open-collab/admin/contracts$#' => ['contract.view', 'contract.create', 'contract.publish'],
        '#^/[^/]+/open-collab/admin/guidelines$#' => ['guideline.edit', 'guideline.publish', 'guideline.archive'],
        '#^/[^/]+/open-collab/admin/contributors(?:/\d+)?$#' => ['creator.manage_roles', 'site.members'],
        '#^/[^/]+/open-collab/admin/contributors/\d+/violations$#' => ['violation.view'],
        '#^/[^/]+/open-collab/admin/violations$#' => ['violation.view'],
        '#^/[^/]+/open-collab/admin/contributor-requests$#' => ['site.members', 'creator.manage_roles'],
        '#^/[^/]+/open-collab/admin/disputes$#' => ['site.manage'],
        '#^/[^/]+/open-collab/admin/payment-terms$#' => ['site.manage'],
        '#^/[^/]+/open-collab/admin/payouts(?:/scheduled)?$#' => ['payout.view', 'payout.approve'],
        '#^/[^/]+/open-collab/admin/invitations$#' => ['creator.invite', 'site.members'],
        '#^/[^/]+/open-collab/admin/activity$#' => ['site.manage'],
        '#^/[^/]+/open-collab/admin/sites(?:/settings)?$#' => ['site.manage', 'site.members', 'site.roles.manage', 'site.permissions.manage'],
        '#^/[^/]+/open-collab/onboarding(?:/dashboard)?$#' => ['onboarding.view'],
        '#^/[^/]+/open-collab/articles/create$#' => ['content.create'],
        '#^/[^/]+/open-collab/articles/edit/\d+$#' => ['content.edit_own'],
        '#^/[^/]+/open-collab/payouts$#' => ['payout.request', 'payout.view'],
        '#^/[^/]+/open-collab/earnings$#' => ['payout.request', 'ledger.view'],
    ];

    public function __construct(
        private readonly SitePermissionResolver $permissionResolver,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $permissions = $this->permissionsForPath($request->getPath());

        if ($permissions === []) {
            return $next($request);
        }

        $userId = Auth::id();
        $siteId = (int) SiteContext::getId();

        foreach ($permissions as $permission) {
            if ($this->permissionResolver->allows($userId, $siteId, $permission)) {
                return $next($request);
            }
        }

        return Response::html('Forbidden.', 403);
    }

    private function permissionsForPath(string $path): array
    {
        foreach (self::PAGE_PERMISSIONS as $pattern => $permissions) {
            if (preg_match($pattern, $path) === 1) {
                return $permissions;
            }
        }

        return [];
    }
}
