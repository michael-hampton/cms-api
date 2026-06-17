<?php

namespace App\Services\Authorization;

final readonly class AccessGrantResult
{
    public function __construct(
        public bool $granted,
    ) {
    }
}
