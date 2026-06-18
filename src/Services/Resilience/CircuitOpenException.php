<?php

namespace App\Services\Resilience;

use RuntimeException;

final class CircuitOpenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('service_unavailable');
    }
}
