<?php

namespace App\Services\Resilience;

final class HttpStatusRetryDecider
{
    /** @var list<int> */
    private const array RETRIABLE_STATUSES = [408, 429, 502, 503, 504];

    public function isRetriable(int $status): bool
    {
        return in_array($status, self::RETRIABLE_STATUSES, true);
    }
}
