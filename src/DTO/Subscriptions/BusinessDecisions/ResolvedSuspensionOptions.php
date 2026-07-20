<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

final class ResolvedSuspensionOptions
{
    public function __construct(
        public readonly bool $allowSuspend,
        public readonly bool $requiresNote,
    ) {
    }
}
