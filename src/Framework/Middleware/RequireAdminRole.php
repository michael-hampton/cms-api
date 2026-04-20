<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\MiddlewareInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;

/**
 * Ensures the current user holds the admin or agent role.
 * Used on OpenCollab admin page and API routes.
 */
class RequireAdminRole implements MiddlewareInterface
{
    private const ALLOWED_ROLES = ['admin', 'agent', 'editor'];

    public function handle(Request $request, callable $next)
    {
        $user = Auth::getUser();

        if (!$user) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return Response::redirect('/login');
        }

        $role = is_array($user) ? ($user['role'] ?? '') : ($user->role ?? '');

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'Forbidden.'], 403);
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
}