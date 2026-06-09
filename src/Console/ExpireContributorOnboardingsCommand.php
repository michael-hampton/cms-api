<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Services\OpenCollab\ContributorOnboardingExpiryService;

/**
 * Scheduled daily — finds incomplete onboarding records past their expiry
 * deadline and marks them as expired.
 *
 * Usage:
 *   php artisan open-collab:onboarding:expire
 *
 * Output example:
 *   Expired 12 contributor onboarding records.
 */
class ExpireContributorOnboardingsCommand extends Command
{
    protected $signature = 'open-collab:onboarding:expire';
    public $description = 'Expire stale incomplete contributor onboarding records.';

    public function __construct(
        private readonly ContributorOnboardingExpiryService $expiryService,
    ) {}

    public function handle(): int
    {
        $count = $this->expiryService->expireStaleOnboardings();

        $this->info("Expired {$count} contributor onboarding records.");

        return 0;
    }
}