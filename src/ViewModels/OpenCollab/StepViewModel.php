<?php

namespace App\ViewModels\OpenCollab;

/**
 * Immutable value object representing a single onboarding step for display.
 *
 * Constructed exclusively by OnboardingPageViewModel — do not instantiate directly.
 */
final readonly class StepViewModel
{
    public function __construct(
        public string $name,
        public string $label,
        public int    $oneBasedIndex,
        public int    $totalSteps,
        public bool   $isDone,
        public bool   $isActive,
        public string $icon,
    )
    {
    }

    public function isPending(): bool
    {
        return !$this->isDone && !$this->isActive;
    }

    /**
     * CSS modifier for oc-step: 'done' | 'active' | 'pending'
     */
    public function cssState(): string
    {
        if ($this->isDone) return 'done';
        if ($this->isActive) return 'active';
        return 'pending';
    }
}