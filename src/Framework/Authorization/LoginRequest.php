<?php

namespace App\Framework\Authorization;

use DateTime;

class LoginRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly int $siteId,
        public readonly ?array $abilities = null,
        public readonly ?DateTime $expiresAt = null,
    ) {}
}
