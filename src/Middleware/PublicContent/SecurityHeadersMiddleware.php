<?php

namespace App\Middleware\PublicContent;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use Closure;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure|callable $next)
    {
        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        return $response
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->setHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
            ->setHeader('X-Frame-Options', 'DENY')
            ->setHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }
}
