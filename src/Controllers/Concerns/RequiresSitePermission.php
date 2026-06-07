<?php

namespace App\Controllers\Concerns;

use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\SitePermissionResolver;

trait RequiresSitePermission
{
    protected function requireSitePermission(string $permission): ?JsonResponse
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();

        if (!$userId || !$siteId) {
            return $this->errorResponse('Unauthorized', 401);
        }

        if (!app(SitePermissionResolver::class)->allows((int) $userId, (int) $siteId, $permission)) {
            return $this->errorResponse('Forbidden', 403);
        }

        return null;
    }
}
