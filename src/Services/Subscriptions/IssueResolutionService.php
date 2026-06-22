<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\ReplacementResolution;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\IssueDeliveryStockRepository;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class IssueResolutionService
{
    private SubscriptionRepository $subscriptionRepository;
    private FulfilmentReplacementEligibilityService $eligibilityService;
    private FulfilmentReplacementService $replacementService;
    private SubscriptionIssueExtensionService $extensionService;
    private IssueDeliveryStockRepository $stockRepository;
    private SubscriptionIssueResolutionRepository $resolutionRepository;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        FulfilmentReplacementEligibilityService $eligibilityService,
        FulfilmentReplacementService $replacementService,
        SubscriptionIssueExtensionService $extensionService,
        IssueDeliveryStockRepository $stockRepository,
        SubscriptionIssueResolutionRepository $resolutionRepository
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->eligibilityService = $eligibilityService;
        $this->replacementService = $replacementService;
        $this->extensionService = $extensionService;
        $this->stockRepository = $stockRepository;
        $this->resolutionRepository = $resolutionRepository;
    }

    public function resolve(
        int $subscriptionId,
        int $issueId,
        ReplacementResolution $decision,
        string $reason,
        int $agentId,
        int $siteId,
        bool $businessDecision = false
    ): object {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('reason is required.');
        }

        $eligibility = $this->eligibilityService->canRequest($subscriptionId, $issueId, $siteId);

        if (!$eligibility->canRequestReplacement) {
            throw new \InvalidArgumentException($eligibility->blockedReason);
        }

        if ($this->resolutionRepository->hasOpenResolution($subscriptionId, $issueId)) {
            throw new \InvalidArgumentException('A resolution is already recorded for this issue.');
        }

        if ($decision === ReplacementResolution::REPLACE) {
            return $this->replace($subscriptionId, $issueId, $reason, $agentId, $siteId, $businessDecision);
        }

        return $this->extend($subscriptionId, $issueId, $reason, $agentId, $siteId, $businessDecision);
    }

    private function replace(
        int $subscriptionId,
        int $issueId,
        string $reason,
        int $agentId,
        int $siteId,
        bool $businessDecision
    ): object {
        return Database::runTransaction(function () use ($subscriptionId, $issueId, $reason, $agentId, $siteId, $businessDecision) {
            if (!$this->stockRepository->decrementIfAvailable($issueId)) {
                throw new \InvalidArgumentException('This issue has no stock available for replacement.');
            }

            $replacement = $this->replacementService->requestReplacement(
                $subscriptionId,
                $issueId,
                $reason,
                $agentId,
                $siteId
            );

            $resolution = $this->resolutionRepository->createReplacementResolution(
                $siteId,
                $subscriptionId,
                $issueId,
                ReplacementResolution::REPLACE,
                $reason,
                $businessDecision,
                $agentId,
                (int) $replacement->id,
                null,
                ['stock_decremented' => true]
            );

            Logger::info('Issue resolved with replacement copy', [
                'subscription_id' => $subscriptionId,
                'issue_id' => $issueId,
                'replacement_id' => $replacement->id,
                'resolution_id' => $resolution->id,
            ]);

            return (object) [
                'decision' => ReplacementResolution::REPLACE->value,
                'replacement' => $replacement,
                'resolution' => $resolution,
            ];
        });
    }

    private function extend(
        int $subscriptionId,
        int $issueId,
        string $reason,
        int $agentId,
        int $siteId,
        bool $businessDecision
    ): object {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException('Subscription not found.');
        }

        $fulfilment = $this->extensionService->extendByOneIssue($subscription);

        $resolution = $this->resolutionRepository->createReplacementResolution(
            $siteId,
            $subscriptionId,
            $issueId,
            ReplacementResolution::EXTEND,
            $reason,
            $businessDecision,
            $agentId,
            null,
            (int) $fulfilment->id,
            $this->buildExtensionMetadata($fulfilment)
        );

        Logger::info('Issue resolved with subscription extension', [
            'subscription_id' => $subscriptionId,
            'issue_id' => $issueId,
            'extension_fulfilment_id' => $fulfilment->id,
            'resolution_id' => $resolution->id,
        ]);

        return (object) [
            'decision' => ReplacementResolution::EXTEND->value,
            'extension_fulfilment' => $fulfilment,
            'resolution' => $resolution,
        ];
    }

    private function buildExtensionMetadata(SubscriptionIssueFulfilment $fulfilment): array
    {
        return [
            'extra_issue_delivery_id' => (int) $fulfilment->issue_delivery_id,
            'scheduled_for' => $fulfilment->scheduled_for instanceof \DateTimeInterface
                ? $fulfilment->scheduled_for->format('Y-m-d H:i:s')
                : null,
        ];
    }
}
