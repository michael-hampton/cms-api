<?php

namespace App\Framework\Authorization;

class SecureTokenGenerator implements TokenGeneratorInterface
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}