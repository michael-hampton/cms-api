<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Jobs\Subscriptions\ResumeScheduledSubscriptionJob;
use App\Repositories\Subscriptions\SubscriptionRepository;

class ProcessScheduledSubscriptionResumesCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'subscriptions:process-scheduled-resumes';
    public $description = 'Resumes paused subscriptions and deliveries whose scheduled resume date has passed.';

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {}

    public function handle(): int
    {
        $result = $this->createResult('subscriptions:process-scheduled-resumes');
        $due = $this->subscriptionRepository->findDueForScheduledResume(new \DateTime());

        foreach ($due as $subscription) {
            try {
                dispatch(ResumeScheduledSubscriptionJob::for((int) $subscription->id))->onQueue('subscriptions');
                $result->incrementSucceeded();
                $result->addMessage("Dispatched resume job for subscription #{$subscription->id}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to dispatch ResumeScheduledSubscriptionJob for #{$subscription->id}: {$e->getMessage()}",
                    context: ['subscription_id' => $subscription->id],
                    throwable: $e,
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}