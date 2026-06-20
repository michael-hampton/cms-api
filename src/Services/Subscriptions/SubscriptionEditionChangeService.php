<?php

declare(strict_types=1);
namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionEditionChanged;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionChangeRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionEditionChangeService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionChangeRepository $changeRepository,
        private readonly SubscriptionIssueDeliveryRebuildService $rebuildService,
        private readonly Database $database,
    ) {}

    public function changeEdition(
        int $subscriptionId,
        int $newEditionId,
        int $siteId,
        int $agentId,
        ?string $reason = null,
    ): object {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        if ((int) $subscription->site_id !== $siteId) {
            throw new \InvalidArgumentException('Subscription does not belong to this site.');
        }

        if ($subscription->status !== SubscriptionStatus::ACTIVE->value) {
            throw new \InvalidArgumentException(
                'Only active subscriptions can have their edition changed.'
            );
        }

        $planId = (int) $subscription->plan_id;
        $currentPlan = $this->planRepository->find($planId);

        if (!$currentPlan) {
            throw new \InvalidArgumentException('Current subscription plan record not found.');
        }

        if ((int) $currentPlan->site_id !== $siteId) {
            throw new \InvalidArgumentException(
                'Current subscription plan does not belong to this site.'
            );
        }

        $newEdition = $this->issueDeliveryRepository->find($newEditionId);

        if (!$newEdition) {
            throw new \InvalidArgumentException("Edition #{$newEditionId} not found.");
        }

        if ((int) $newEdition->subscription_plan_id !== $planId) {
            throw new \InvalidArgumentException(
                'The selected edition does not belong to the subscription plan.'
            );
        }

        if (
            isset($newEdition->status)
            && $newEdition->status !== IssueScheduleStatus::ACTIVE->value
        ) {
            throw new \InvalidArgumentException('The selected edition is not active.');
        }

        $oldEditionId = $this->rebuildService->resolveCurrentFutureEditionId($subscriptionId);

        if (!$oldEditionId) {
            throw new \InvalidArgumentException(
                'This subscription has no future issues available to change.'
            );
        }

        if ($oldEditionId === $newEditionId) {
            throw new \InvalidArgumentException(
                'The selected edition is the same as the current next edition. No change required.'
            );
        }

        $remainingIssueCount = $this->rebuildService->countRemainingIssues($subscriptionId);

        $result = $this->database->transaction(function () use (
            $subscriptionId,
            $planId,
            $oldEditionId,
            $newEditionId,
            $remainingIssueCount,
            $agentId,
            $reason,
        ): array {
            $this->rebuildService->rebuildForEditionChange(
                subscriptionId: $subscriptionId,
                subscriptionPlanId: $planId,
                startingEditionId: $newEditionId,
                remainingIssueCount: $remainingIssueCount,
            );

            $this->changeRepository->recordEditionChange(
                subscriptionId: $subscriptionId,
                oldEditionId: $oldEditionId,
                newEditionId: $newEditionId,
                createdBy: $agentId,
                reason: $reason,
            );

            return [
                'subscription_id' => $subscriptionId,
                'old_edition_id' => $oldEditionId,
                'new_edition_id' => $newEditionId,
            ];
        });

        event(new SubscriptionEditionChanged(
            subscriptionId: $subscriptionId,
            oldEditionId: $oldEditionId,
            newEditionId: $newEditionId,
            agentId: $agentId,
            timestamp: now_datetime()->format('Y-m-d H:i:s'),
        ));

        Logger::info('Subscription edition changed', [
            'subscription_id' => $subscriptionId,
            'plan_id' => $planId,
            'old_edition_id' => $oldEditionId,
            'new_edition_id' => $newEditionId,
            'agent_id' => $agentId,
        ]);

        return (object) array_merge($result, [
            'message' => 'Subscription edition changed successfully.',
        ]);
    }
}
