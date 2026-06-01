<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Jobs\MemberInsights\Newsletters\BuildNewsletterRelationshipsJob;
use App\Jobs\ProcessSubscriptionCommunicationsJob;
use App\Repositories\Subscriptions\SubscriptionRepository;

class ProcessSubscriptionCommunicationsCommand extends Command
{
    const SUCCESS = 1;
    const FAILURE = 0;
    protected $signature = 'subscriptions:process-communications
        {--date= : Date to process for (Y-m-d). Defaults to today.}
        {--plan= : Only process subscriptions for this plan ID.}
        {--segment= : Only process subscriptions in this segment ID.}
        {--dry-run : Count only — do not dispatch any jobs.}';

    public $description = 'Dispatch subscription communication jobs for all active subscriptions due today.';

    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function handle(): int
    {
        $date      = $this->option('date');
        $planId    = $this->option('plan') ? (int) $this->option('plan') : null;
        $segmentId = $this->option('segment') ? (int) $this->option('segment') : null;
        $dryRun    = (bool) $this->option('dry-run');

        $dispatched = 0;

        $this->subscriptionRepository->chunkActive(
            self::CHUNK_SIZE,
            function ($subscriptions) use ($date, $dryRun, &$dispatched) {
                foreach ($subscriptions as $subscription) {
                    if (!$dryRun) {
                        dispatch(ProcessSubscriptionCommunicationsJob::for(
                            $subscription->id,
                            $date,
                        ));
                    }
                    $dispatched++;
                }
            },
            planId:    $planId,
            segmentId: $segmentId,
        );

        $this->info(
            $dryRun
                ? "[dry-run] Would dispatch {$dispatched} jobs."
                : "Dispatched {$dispatched} jobs."
        );

        return self::SUCCESS;
    }
}