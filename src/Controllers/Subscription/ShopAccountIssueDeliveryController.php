<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

final class ShopAccountIssueDeliveryController extends Controller
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository,
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

        $issues = $this->issueDeliveryRepository
            ->findAvailableEditionsForSubscriptionPlan(
                (int) $subscription->plan_id,
                new \DateTime('today'),
            );

        $issueIds = $issues->pluck('id')->toArray();
        $fulfilments = $this->issuesDeliveredRepository->getForSubscriptionAndIssues(
            (int) $subscription->id,
            $issueIds
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
