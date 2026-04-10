<?php

namespace App\Exceptions\OpenCollab;

class OnboardingIncompleteException extends \RuntimeException
{
    public function __construct(private readonly array $pendingSteps = [])
    {
        parent::__construct('Contributor onboarding is not complete.');
    }

    public function getPendingSteps(): array
    {
        return $this->pendingSteps;
    }
}