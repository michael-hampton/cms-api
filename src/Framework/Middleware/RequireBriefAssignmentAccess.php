<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\OpenCollabBriefAccessService;

class RequireBriefAssignmentAccess
{
    public function __construct(
        private readonly OpenCollabBriefAccessService $access,
    )
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $briefId = (int)$request->route('brief', 0);

        if ($briefId <= 0) {
            return $next($request);
        }

        if (!$this->access->assignmentForBrief($briefId, (int)Auth::id(), (int)SiteContext::getId())) {
            return Response::json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
