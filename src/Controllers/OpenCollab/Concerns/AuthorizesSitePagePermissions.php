<?php

namespace App\Controllers\OpenCollab\Concerns;

use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;

trait AuthorizesSitePagePermissions
{
    protected function authorizeSitePagePermissions(array $permissions): ?Response
    {
        try {
            $this->authorization->assertAny(Auth::id(), SiteContext::getId(), $permissions);

            return null;
        } catch (UnauthorizedException) {
            return Response::html('Forbidden.', 403);
        }
    }
}
