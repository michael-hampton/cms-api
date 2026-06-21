<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use DateInterval;
use DateTimeImmutable;

class SubscriptionIssueExtensionService
{
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionIssueFulfilmentRepository $fulfilmentRepository;
    private StripeSubscriptionGatewayInterface $stripeGateway;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
        StripeSubscriptionGatewayInterface $stripeGateway
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->fulfilmentRepository = $fulfilmentRepository;
        $this->stripeGateway = $stripeGateway;
    }

    public function extendByOneIssue(Subscription $subscription): SubscriptionIssueFulfilment
    {
        $nextIssue = $this->resolveNextIssue($subscription);

        if (!$nextIssue) {
            throw new \InvalidArgumentException('No future issue is available to extend this subscription.');
        }

        $scheduledFor = $this->resolveScheduledDate($nextIssue);

        $fulfilment = $this->fulfilmentRepository->createForSubscription(
            (int) $subscription->id,
            (int) $nextIssue->id,
            $scheduledFor
        );

        $newEndDate = $this->calculateNewEndDate($subscription, $scheduledFor);

        $this->updateLocalEndDates($subscription, $newEndDate);
        $this->updateStripeEndDate($subscription, $newEndDate);

        return $fulfilment;
    }

    private function resolveNextIssue(Subscription $subscription): ?IssueDelivery
    {
        $lastFulfilment = SubscriptionIssueFulfilment::where('subscription_id', (int) $subscription->id)
            ->orderBy('scheduled_for', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $query = IssueDelivery::where('subscription_plan_id', (int) $subscription->plan_id)
            ->where('site_id', (int) $subscription->site_id)
            ->orderBy('estimated_delivery_date', 'asc')
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('id', 'asc');

        if ($lastFulfilment && $lastFulfilment->issue_delivery_id) {
            $lastIssue = IssueDelivery::find((int) $lastFulfilment->issue_delivery_id);
            $lastDate = $lastIssue ? $this->resolveScheduledDate($lastIssue) : null;

            if ($lastDate) {
                $query->where(function ($builder) use ($lastDate) {
                    $date = $lastDate->format('Y-m-d H:i:s');
                    $builder->where('estimated_delivery_date', '>', $date)
                        ->orWhere('on_sale_date', '>', $date);
                });
            } else {
                $query->where('id', '>', (int) $lastFulfilment->issue_delivery_id);
            }
        }

        $issues = $query->get();

        foreach ($issues as $issue) {
            $exists = $this->fulfilmentRepository->existsForSubscriptionAndSchedule(
                (int) $subscription->id,
                (int) $issue->id
            );

            if (!$exists) {
                return $issue;
            }
        }

        return null;
    }

    private function resolveScheduledDate(IssueDelivery $issue): ?DateTimeImmutable
    {
        if ($issue->estimated_delivery_date instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($issue->estimated_delivery_date);
        }

        if ($issue->on_sale_date instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($issue->on_sale_date);
        }

        return null;
    }

    private function calculateNewEndDate(Subscription $subscription, ?DateTimeImmutable $scheduledFor): DateTimeImmutable
    {
        if ($scheduledFor) {
            return $scheduledFor;
        }

        if ($subscription->end_date instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($subscription->end_date)->add(new DateInterval('P7D'));
        }

        if ($subscription->current_period_end instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($subscription->current_period_end)->add(new DateInterval('P7D'));
        }

        return (new DateTimeImmutable())->add(new DateInterval('P7D'));
    }

    private function updateLocalEndDates(Subscription $subscription, DateTimeImmutable $newEndDate): void
    {
        $formatted = $newEndDate->format('Y-m-d H:i:s');

        $this->subscriptionRepository->update((int) $subscription->id, [
            'end_date' => $formatted,
            'current_period_end' => $formatted,
            'stripe_sync_status' => 'pending',
            'stripe_sync_error' => null,
        ]);
    }

    private function updateStripeEndDate(Subscription $subscription, DateTimeImmutable $newEndDate): void
    {
        $stripeSubscriptionId = $subscription->getStripeSubscriptionId();

        if (!$stripeSubscriptionId) {
            return;
        }

        try {
            $this->stripeGateway->moveEndDate($stripeSubscriptionId, $newEndDate);

            $this->subscriptionRepository->update((int) $subscription->id, [
                'stripe_sync_status' => 'synced',
                'stripe_sync_error' => null,
                'stripe_synced_at' => now_datetime()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $this->subscriptionRepository->update((int) $subscription->id, [
                'stripe_sync_status' => 'failed',
                'stripe_sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
