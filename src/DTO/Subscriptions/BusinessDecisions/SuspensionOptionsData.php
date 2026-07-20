<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

/**
 * Top-level shape for GET .../suspension-options.
 */
final class SuspensionOptionsData
{
    /** @param array<int, array{id: int, code: string, label: string, requires_note: bool}> $reasons */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly int $planId,
        public readonly bool $allowSuspend,
        public readonly bool $requiresNote,
        public readonly array $reasons,
    ) {
    }

    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'plan_id' => $this->planId,
            'allow_suspend' => $this->allowSuspend,
            'requires_note' => $this->requiresNote,
            'reasons' => $this->reasons,
        ];
    }
}
