<?php

namespace App\Events\OpenCollab;

use App\Models\ContributorOnboarding;

/**
 * Fired when a contributor restarts an expired onboarding flow.
 *
 * Listeners may use this for audit logging and analytics.
 */
final class ContributorOnboardingRestarted
{
    public function __construct(
        public readonly ContributorOnboarding $onboarding,
        public readonly int                   $userId,
    ) {}
}