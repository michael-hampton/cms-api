<?php

namespace App\Framework\Middleware;

use App\Framework\Exceptions\CsrfTokenMismatchException;
use App\Framework\Http\Request;
use App\Framework\Security\Csrf;
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
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($this->inExceptArray($request) || ($_ENV['APP_ENV'] ?? null) === 'testing') {
            return $next($request);
        }

        $token = $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-Csrf-Token')
            ?? $request->header('X-XSRF-TOKEN')
            ?? $request->header('X-Xsrf-Token')
            ?? $request->input('_token');

        if (!is_string($token) || $token === '' || !Csrf::validateToken($token)) {
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
            $pattern = str_replace('*', '.*', $except);
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return true;
            }
        }

        return false;
    }
}
