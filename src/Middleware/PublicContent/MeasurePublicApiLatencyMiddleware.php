<?php

namespace App\Middleware\PublicContent;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use App\Framework\Support\Logger;
use Closure;
use Throwable;

final class MeasurePublicApiLatencyMiddleware implements MiddlewareInterface
{
    private const float SLOW_REQUEST_THRESHOLD_MS = 500.0;

    public function handle(Request $request, Closure|callable $next)
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Logger::error('Public API request failed', [
                'method' => $request->method(),
                'path' => $request->getOriginalPath(),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $durationMs = $this->elapsedMilliseconds($startedAt);
        $statusCode = $response instanceof Response
            ? $response->getStatusCode()
            : null;

        $context = [
            'method' => $request->method(),
            'path' => $request->getOriginalPath(),
            'status' => $statusCode,
            'duration_ms' => $durationMs,
        ];

        if ($durationMs >= self::SLOW_REQUEST_THRESHOLD_MS) {
            Logger::warning('Slow public API request', $context);
        } else {
            Logger::info('Public API request completed', $context);
        }

        if ($response instanceof Response) {
            $response
                ->setHeader('Server-Timing', sprintf('app;dur=%.2f', $durationMs))
                ->setHeader('X-Response-Time', sprintf('%.2fms', $durationMs));
        }

        return $response;
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }
}
