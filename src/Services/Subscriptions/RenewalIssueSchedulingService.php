<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;

class RenewalIssueSchedulingService
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository,
    ) {
    }

    public function replaceFutureFulfilmentsForRenewal(
        Subscription $oldSubscription,
        Subscription $newSubscription,
    ): array {
        $oldSuperseded = $this->subscriptionIssueFulfilmentRepository
            ->supersedeFutureForSubscription((int) $oldSubscription->id);

        $scheduleSummary = $this->scheduleForSubscription($newSubscription);

        return [
            'old_superseded' => $oldSuperseded,
            'new_created' => $scheduleSummary['created'],
            'new_existing' => $scheduleSummary['existing'],
            'new_skipped' => $scheduleSummary['skipped'],
        ];
    }

    public function scheduleForSubscription(Subscription $subscription): array
    {
        $fromDate = $this->normaliseDate($subscription->start_date ?? null)
            ?? new \DateTimeImmutable();

        $issues = $this->issueDeliveryRepository->findAvailableEditionsForSubscriptionPlan(
            (int) $subscription->plan_id,
            $fromDate,
        );

        $created = 0;
        $existing = 0;
        $skipped = 0;

        foreach ($issues as $issue) {
            if (!$this->issueCanBeScheduledForSubscription($subscription, $issue)) {
                $skipped++;
                continue;
            }

            $alreadyExists = $this->subscriptionIssueFulfilmentRepository
                ->existsForSubscriptionAndSchedule((int) $subscription->id, (int) $issue->id);

            $this->subscriptionIssueFulfilmentRepository
                ->createFromSchedule((int) $subscription->id, $issue);

            if ($alreadyExists) {
                $existing++;
                continue;
            }

            $created++;
        }

        return compact('created', 'existing', 'skipped');
    }

    /**
     * Schedule the next period's issue fulfilments on an in-place Stripe renewal.
     *
     * Unlike hard-replace renewal, this does not supersede existing rows and
     * does not change subscription id — it only creates missing fulfilments
     * for the renewed entitlement window.
     *
     * @return array{created: int, existing: int, skipped: int}
     */
    public function extendForInPlaceRenewal(
        Subscription $subscription,
        \DateTimeImmutable $periodStart,
        int $issueCount,
    ): array {
        if ($issueCount < 1 || !$subscription->isPrint()) {
            return ['created' => 0, 'existing' => 0, 'skipped' => 0];
        }

        $issues = $this->issueDeliveryRepository->findFutureIssuesForPlan(
            subscriptionPlanId: (int) $subscription->plan_id,
            fromDate: $periodStart,
            limit: $issueCount,
        );

        $created = 0;
        $existing = 0;
        $skipped = 0;

        foreach ($issues as $issue) {
            if (!$this->issueCanBeScheduledForSubscription($subscription, $issue)) {
                $skipped++;
                continue;
            }

            $alreadyExists = $this->subscriptionIssueFulfilmentRepository
                ->existsForSubscriptionAndSchedule((int) $subscription->id, (int) $issue->id);

            $this->subscriptionIssueFulfilmentRepository
                ->createFromSchedule((int) $subscription->id, $issue);

            if ($alreadyExists) {
                $existing++;
                continue;
            }

            $created++;
        }

        return compact('created', 'existing', 'skipped');
    }

    private function issueCanBeScheduledForSubscription(Subscription $subscription, IssueDelivery $issue): bool
    {
        $issueDate = $this->normaliseDate($issue->on_sale_date ?? null)
            ?? $this->normaliseDate($issue->estimated_delivery_date ?? null);

        if (!$issueDate) {
            return false;
        }

        $startDate = $this->normaliseDate($subscription->start_date ?? null);
        if ($startDate && $issueDate < $startDate) {
            return false;
        }

        return true;
    }

    private function normaliseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d H:i:s'));
        }

        if (is_string($value) && trim($value) !== '') {
            return new \DateTimeImmutable($value);
        }

        return null;
    }
}
