<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class FulfilmentReplacementEligibilityService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly FulfilmentReplacementRepository $replacementRepository,
    ) {}

    public function canRequest(
        int $subscriptionId,
        int $issueId,
        int $siteId,
    ): ReplacementEligibilityResult {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return ReplacementEligibilityResult::denied('Subscription not found.');
        }

        if ($subscription->site_id !== $siteId) {
            return ReplacementEligibilityResult::denied('Subscription does not belong to this site.');
        }

        if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
            return ReplacementEligibilityResult::denied(
                'Only active subscriptions can have issues replaced.'
            );
        }

        if ($subscription->delivery_type !== SubscriptionType::PRINTED->value) {
            return ReplacementEligibilityResult::denied(
                'Issue replacement is only available for print subscriptions.'
            );
        }

        if (!$this->replacementRepository->issueExistsForSubscription($issueId, $subscriptionId)) {
            return ReplacementEligibilityResult::denied(
                "Issue #{$issueId} does not belong to subscription #{$subscriptionId}."
            );
        }

        if (!$this->replacementRepository->issueDeliveryWasDispatched($issueId, $subscriptionId)) {
            return ReplacementEligibilityResult::denied(
                'Only dispatched issues can be replaced.'
            );
        }

        if ($this->replacementRepository->hasOpenReplacement($subscriptionId, $issueId)) {
            return ReplacementEligibilityResult::denied(
                'A replacement is already in progress for this issue.'
            );
        }

        return ReplacementEligibilityResult::allowed();
    }

    public function canRequestForIssues(
        int $subscriptionId,
        array $issueIds,
        int $siteId,
    ): array {
        if (empty($issueIds)) {
            return [];
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return $this->denyAll($issueIds, 'Subscription not found.');
        }

        if ($subscription->site_id !== $siteId) {
            return $this->denyAll($issueIds, 'Subscription does not belong to this site.');
        }

        if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
            return $this->denyAll($issueIds, 'Only active subscriptions can have issues replaced.');
        }

        if ($subscription->delivery_type !== SubscriptionType::PRINTED->value) {
            return $this->denyAll($issueIds, 'Issue replacement is only available for print subscriptions.');
        }

        $openReplacements = $this->replacementRepository
            ->findOpenReplacementsForIssues($subscriptionId, $issueIds);

        $blockedIssueIds = [];
        foreach ($openReplacements as $replacement) {
            $blockedIssueIds[$replacement->issue_delivery_id] = true;
        }

        $results = [];

        foreach ($issueIds as $issueId) {
            if (!$this->replacementRepository->issueExistsForSubscription($issueId, $subscriptionId)) {
                $results[$issueId] = ReplacementEligibilityResult::denied(
                    "Issue #{$issueId} does not belong to subscription #{$subscriptionId}."
                );
                continue;
            }

            if (!$this->replacementRepository->issueDeliveryWasDispatched($issueId, $subscriptionId)) {
                $results[$issueId] = ReplacementEligibilityResult::denied(
                    'Only dispatched issues can be replaced.'
                );
                continue;
            }

            if (isset($blockedIssueIds[$issueId])) {
                $results[$issueId] = ReplacementEligibilityResult::denied(
                    'A replacement is already in progress for this issue.'
                );
                continue;
            }

            $results[$issueId] = ReplacementEligibilityResult::allowed();
        }

        return $results;
    }

    private function denyAll(array $issueIds, string $reason): array
    {
        $results = [];

        foreach ($issueIds as $issueId) {
            $results[$issueId] = ReplacementEligibilityResult::denied($reason);
        }

        return $results;
    }
}
