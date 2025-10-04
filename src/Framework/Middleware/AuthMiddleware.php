<?php

namespace App\Framework\Middleware;

use App\Framework\Http\Request;
use App\Framework\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function isAuthenticated(Request $request): bool
    {
        // Your auth logic here
        return $request->header('Authorization') !== null;
    }
}