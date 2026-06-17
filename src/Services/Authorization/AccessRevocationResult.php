<?php

namespace App\Services\Authorization;

final readonly class AccessRevocationResult
{
    public function __construct(
        public bool $revoked,
    ) {
    }
}
