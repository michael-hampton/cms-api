<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Notifications\AbstractNotification;
use App\Models\User;

final class OnboardingStepCompletedNotification extends AbstractNotification
{
    public function __construct(
        public readonly User   $contributor,
        public readonly string $step,
        public readonly array  $remainingSteps,
    )
    {
        parent::__construct(userId: $contributor->id, email: $contributor->email);
    }

    public function subject(): string
    {
        return "Onboarding step completed: " . ucfirst($this->step);
    }
}