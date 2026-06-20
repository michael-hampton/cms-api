<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Events\Subscriptions\IssueDeliveryDispatchFailed;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use App\Services\Subscriptions\IssueFulfilmentDispatchCoordinator;
use App\Services\Subscriptions\IssueFulfilmentPlanner;
use DomainException;

class GenerateIssueDeliveriesJob extends BaseJob
{
    private IssueDeliveryRepository $issueDeliveryRepository;
    private IssueDeliveryEligibilityService $eligibilityService;
    private IssueFulfilmentPlanner $fulfilmentPlanner;
    private IssueFulfilmentDispatchCoordinator $dispatchCoordinator;
    private Database $database;
    private Logger $logger;

    public function __construct(
        private readonly int $issueDeliveryId,
    )
    {
    }

    public function handle(): array
    {
        $issueDelivery = $this->issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery->isActive()) {
            $this->logger->info('IssueDelivery skipped — not active', [
                'issue_delivery_id' => $issueDelivery->id,
                'status' => $issueDelivery->status,
            ]);
            return [];
        }

        try {
            $eligibleSubscriptions = $this->eligibilityService->getEligibleSubscriptions($issueDelivery);
        } catch (DomainException $exception) {
            $this->logger->error('IssueDelivery eligibility resolution failed', [
                'issue_delivery_id' => $issueDelivery->id,
                'error' => $exception->getMessage(),
            ]);

            $issueDelivery->markDispatchFailed($exception->getMessage());
            event(new IssueDeliveryDispatchFailed($issueDelivery, $exception->getMessage()));

            return [];
        }

        $plan = $this->database->transaction(function () use ($issueDelivery, $eligibleSubscriptions) {
            return $this->fulfilmentPlanner->plan($issueDelivery, $eligibleSubscriptions);
        });

        $summary = $this->dispatchCoordinator->dispatch($issueDelivery, $plan);
        $summary['eligible_subscriptions'] = $eligibleSubscriptions->count();

        return $summary;
    }
}
