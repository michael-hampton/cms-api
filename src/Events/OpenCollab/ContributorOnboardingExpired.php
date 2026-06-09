<?php

namespace App\Events\OpenCollab;

use App\Models\ContributorOnboarding;

/**
 * Fired when a contributor's incomplete onboarding record is marked expired
 * by the scheduled expiry job.
 *
 * Listeners may use this for audit logging, notifications, or reporting.
 */
final class ContributorOnboardingExpired
{
    public function __construct(
        public readonly ContributorOnboarding $onboarding,
        public readonly string                $reason,
    ) {}
}