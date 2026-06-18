<?php

namespace App\Middleware\PublicContent;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use App\Framework\Support\Cache\Cache;
use App\Framework\Support\SiteContext;
use Closure;

final class PublicApiRateLimitMiddleware implements MiddlewareInterface
{
    private const int MAX_ATTEMPTS = 120;
    private const int DECAY_SECONDS = 60;

    public function handle(Request $request, Closure|callable $next)
    {
        $now = time();
        $key = sprintf(
            'public-content-api:%d:%s',
            SiteContext::getId(),
            hash('sha256', $request->ip()),
        );

        $state = Cache::get($key, [
            'attempts' => 0,
            'reset_at' => $now + self::DECAY_SECONDS,
        ]);

        if (!is_array($state) || (int) ($state['reset_at'] ?? 0) <= $now) {
            $state = [
                'attempts' => 0,
                'reset_at' => $now + self::DECAY_SECONDS,
            ];
        }

        $attempts = (int) ($state['attempts'] ?? 0);
        $resetAt = (int) ($state['reset_at'] ?? ($now + self::DECAY_SECONDS));

        if ($attempts >= self::MAX_ATTEMPTS) {
            return Response::json([
                'success' => false,
                'message' => 'Too many requests.',
            ], 429)
                ->setHeader('Retry-After', (string) max(1, $resetAt - $now))
                ->setHeader('X-RateLimit-Limit', (string) self::MAX_ATTEMPTS)
                ->setHeader('X-RateLimit-Remaining', '0');
        }

        $attempts++;
        Cache::put($key, [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
        ], max(1, $resetAt - $now));

        $response = $next($request);

        if ($response instanceof Response) {
            $response
                ->setHeader('X-RateLimit-Limit', (string) self::MAX_ATTEMPTS)
                ->setHeader('X-RateLimit-Remaining', (string) max(0, self::MAX_ATTEMPTS - $attempts));
        }

        return $response;
    }
}
