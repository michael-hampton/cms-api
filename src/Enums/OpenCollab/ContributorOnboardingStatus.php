<?php

namespace App\Enums\OpenCollab;

enum ContributorOnboardingStatus: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Invalidated = 'invalidated';
    case Expired    = 'expired';

    /**
     * Statuses that represent an incomplete / blocked onboarding.
     * Expired is treated as incomplete for access-check purposes.
     */
    public function isIncomplete(): bool
    {
        return match ($this) {
            self::Completed => false,
            default         => true,
        };
    }
}