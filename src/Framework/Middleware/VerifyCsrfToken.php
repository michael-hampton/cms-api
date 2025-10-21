<?php

namespace App\Framework\Middleware;

use App\Framework\Http\Request;
use App\Framework\Security\Csrf;
use App\Framework\Exceptions\CsrfTokenMismatchException;
use Closure;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * URIs that should be excluded from CSRF verification
     */
    protected array $except = [
        // Add routes to exclude, e.g., '/api/*'
    ];

    public function handle(Request $request, Closure|callable $next)
    {
        // Skip CSRF for GET, HEAD, OPTIONS
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Check if route is excluded
        if ($this->inExceptArray($request) || $_ENV['APP_ENV'] === 'testing') {
            return $next($request);
        }

        // Verify CSRF token
        $token = $request->input('_token');

        if (!$token || !Csrf::validateToken($token)) {
            throw new CsrfTokenMismatchException('CSRF token mismatch');
        }

        return $next($request);
    }

    /**
     * Check if the request has a URI that should pass through CSRF verification
     */
    protected function inExceptArray(Request $request): bool
    {
        $path = $request->getPath();

        foreach ($this->except as $except) {
            // Convert wildcards to regex
            $pattern = str_replace('*', '.*', $except);
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return true;
            }
        }

        return false;
    }
}