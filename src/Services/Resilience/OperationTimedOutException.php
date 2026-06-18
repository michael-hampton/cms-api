<?php

namespace App\Services\Resilience;

use RuntimeException;

final class OperationTimedOutException extends RuntimeException
{
    public function __construct(int $timeoutMilliseconds)
    {
        parent::__construct(sprintf(
            'Operation exceeded its %dms deadline.',
            $timeoutMilliseconds,
        ));
    }
}
