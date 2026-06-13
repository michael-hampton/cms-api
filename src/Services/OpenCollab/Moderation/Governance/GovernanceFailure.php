<?php

namespace App\Services\OpenCollab\Moderation\Governance;

class GovernanceFailure
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
    ) {
    }
}