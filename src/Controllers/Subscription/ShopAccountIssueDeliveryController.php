<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\PlanIssueScheduleRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

final class ShopAccountIssueDeliveryController extends Controller
{
    public function __construct(
        private readonly PlanIssueScheduleRepository $planIssueScheduleRepository,
        private readonly SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function __invoke(int $id): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription || $subscription->member_id !== $member->id || !$subscription->isPrint()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $today = new \DateTime('today');
        $subscriptionFulfilments = $this->subscriptionIssueFulfilmentRepository->getForSubscription(
            (int) $subscription->id
        );

        $includedIssueIds = [];
        $fulfilments = [];

        foreach ($subscriptionFulfilments as $fulfilment) {
            $fulfilments[(int) $fulfilment->issue_delivery_id] = $fulfilment;
            $effectiveDate = $fulfilment->deferred_until ?? $fulfilment->scheduled_for;

            if ($effectiveDate instanceof \DateTimeInterface && $effectiveDate >= $today) {
                $includedIssueIds[] = (int) $fulfilment->issue_delivery_id;
            }
        }

        $issues = $this->planIssueScheduleRepository->findForAccount(
            (int) $subscription->plan_id,
            $today,
            $includedIssueIds
        );

        $deliveries = $issues
            ->map(static function ($issue) use ($fulfilments) {
                $fulfilment = $fulfilments[(int) $issue->id] ?? null;
                $scheduledDate = $fulfilment?->scheduled_for ?? $issue->estimated_delivery_date;
                $effectiveDate = $fulfilment?->deferred_until ?? $scheduledDate;

                return [
                    'id' => $issue->id,
                    'issue_number' => $issue->issue_number,
                    'issue_title' => $issue->issue_title,
                    'estimated_delivery_date' => $effectiveDate?->format('Y-m-d'),
                    'scheduled_delivery_date' => $scheduledDate?->format('Y-m-d'),
                    'deferred_until' => $fulfilment?->deferred_until?->format('Y-m-d'),
                    'status' => $issue->status,
                    'fulfilment_status' => $fulfilment?->status,
                    'tracking_number' => $issue->tracking_info['tracking_number'] ?? null,
                    'tracking_url' => $issue->tracking_info['tracking_url'] ?? null,
                ];
            })
            ->toArray();

        return $this->jsonResponse([
            'success' => true,
            'deliveries' => array_slice($deliveries, 0, 6),
        ]);
    }
}
