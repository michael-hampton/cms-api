<?php

namespace App\Framework\Authorization;

class LoginRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly int $siteId
    ) {}
}