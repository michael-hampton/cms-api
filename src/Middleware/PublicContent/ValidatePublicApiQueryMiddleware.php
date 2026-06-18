<?php

namespace App\Middleware\PublicContent;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\MiddlewareInterface;
use Closure;

final class ValidatePublicApiQueryMiddleware implements MiddlewareInterface
{
    private const array ALLOWED_QUERY_KEYS = [
        'country',
        'region',
        'geo_source',
        'page',
        'per_page',
    ];

    public function handle(Request $request, Closure|callable $next)
    {
        $query = $request->query();
        $unknown = array_diff(array_keys($query), self::ALLOWED_QUERY_KEYS);

        if ($unknown !== []) {
            return $this->invalid('Unknown query parameter: ' . reset($unknown));
        }

        if (isset($query['page']) && !$this->isBoundedInteger($query['page'], 1, 10_000)) {
            return $this->invalid('page must be an integer between 1 and 10000.');
        }

        if (isset($query['per_page']) && !$this->isBoundedInteger($query['per_page'], 1, 50)) {
            return $this->invalid('per_page must be an integer between 1 and 50.');
        }

        foreach (['slug', 'pageId', 'regionSlug'] as $key) {
            $value = $request->route($key);

            if (is_string($value) && (strlen($value) > 200 || !preg_match('/^[A-Za-z0-9_-]+$/', $value))) {
                return $this->invalid($key . ' is invalid.');
            }
        }

        return $next($request);
    }

    private function isBoundedInteger(mixed $value, int $minimum, int $maximum): bool
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }

        $integer = (int) $value;

        return $integer >= $minimum && $integer <= $maximum;
    }

    private function invalid(string $message): Response
    {
        return Response::json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
