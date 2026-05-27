<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\MiddlewareInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\SitePermissionResolver;

/**
 * Ensures the current user holds the admin or agent role.
 * Used on OpenCollab admin page and API routes.
 */
class RequireAdminRole implements MiddlewareInterface
{
    private const ALLOWED_ROLES = ['admin', 'agent', 'editor'];
    private const ADMIN_PERMISSIONS = [
        'site.manage',
        'site.members',
        'site.roles.manage',
        'site.permissions.manage',
        'creator.manage_roles',
        'payout.approve',
        'contract.publish',
        'guideline.publish',
        'content.review',
    ];

    public function __construct(
        private readonly SitePermissionResolver $permissionResolver,
    ) {
    }

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
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : (int) ($user->id ?? 0);
        $siteId = (int) SiteContext::getId();

        if ($userId > 0 && $siteId > 0) {
            foreach (self::ADMIN_PERMISSIONS as $permission) {
                if ($this->permissionResolver->allows($userId, $siteId, $permission)) {
                    return $next($request);
                }
            }
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'message' => 'Forbidden.'], 403);
            }
            return Response::html('Forbidden.', 403);
        }

        return $next($request);
    }
}
