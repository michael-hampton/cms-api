<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\MiddlewareInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;

/**
 * Ensures the current user is authenticated as a contributor.
 * Used on contributor-facing OpenCollab routes.
 */
class RequireContributorAuth implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        if (!Auth::check()) {
            $intendedUrl = urlencode($request->getPath());

            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return Response::redirect('/login?redirect=' . $intendedUrl);
        }

        return $next($request);
    }
}