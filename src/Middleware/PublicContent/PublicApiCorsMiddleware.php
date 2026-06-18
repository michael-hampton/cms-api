<?php

namespace App\Middleware\PublicContent;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use Closure;

final class PublicApiCorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure|callable $next)
    {
        $origin = $request->header('Origin');

        if ($origin !== null && !$this->isSameOrigin($origin, $request)) {
            return Response::json([
                'success' => false,
                'message' => 'Origin not allowed.',
            ], 403);
        }

        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        $response
            ->setHeader('Vary', 'Origin')
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Accept, Content-Type, X-CSRF-TOKEN, X-Requested-With');

        if ($origin !== null) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }

    private function isSameOrigin(string $origin, Request $request): bool
    {
        $parts = parse_url($origin);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $originHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));

        return strtolower($parts['scheme']) === strtolower($request->getScheme())
            && $originHost === strtolower($request->getHost());
    }
}
