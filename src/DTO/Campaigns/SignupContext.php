<?php

namespace App\DTO\Campaigns;

class SignupContext
{
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $referrer = null,
    )
    {
    }
}
