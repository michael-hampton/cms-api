<?php

namespace App\Controllers\OpenCollab\Concerns;

use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;

trait AuthorizesSitePermissions
{
    protected function authorizeSitePermissions(array $permissions): ?JsonResponse
    {
        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), $permissions);

            return null;
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), 403);
        }
    }
}
