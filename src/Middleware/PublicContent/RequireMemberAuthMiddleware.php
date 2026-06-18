<?php

namespace App\Middleware\PublicContent;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use Closure;

final class RequireMemberAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure|callable $next)
    {
        if (!MemberAuth::check()) {
            return Response::json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        return $next($request);
    }
}
