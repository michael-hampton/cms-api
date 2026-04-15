<?php

namespace App\Exceptions\OpenCollab;

/**
 * Thrown when a contributor attempts a high-risk action (publish, submit, withdraw)
 * before completing required onboarding steps.
 *
 * $pendingSteps carries structured data:
 * [
 *   ['step' => 'contract', 'reason' => 'New contract version requires signature', 'meta' => ['contract_id' => 5]],
 * ]
 */
class OnboardingIncompleteException extends \RuntimeException
{
    /**
     * @param array<int, array{step: string, reason: string, meta: array<string, mixed>}> $pendingSteps
     */
    public function __construct(
        private readonly array $pendingSteps,
        string                 $message = 'Onboarding is not complete.',
        int                    $code = 0,
        ?\Throwable            $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<int, array{step: string, reason: string, meta: array<string, mixed>}>
     */
    public function getPendingSteps(): array
    {
        return $this->pendingSteps;
    }
}