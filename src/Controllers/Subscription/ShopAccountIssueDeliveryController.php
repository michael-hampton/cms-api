<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

final class ShopAccountIssueDeliveryController extends Controller
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
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

        $deliveries = $this->issueDeliveryRepository
            ->findAvailableEditionsForSubscriptionPlan(
                (int) $subscription->plan_id,
                new \DateTime(),
            )
            ->map(static fn($delivery) => [
                'id' => $delivery->id,
                'issue_number' => $delivery->issue_number,
                'issue_title' => $delivery->issue_title,
                'estimated_delivery_date' => $delivery->estimated_delivery_date?->format('Y-m-d'),
                'status' => $delivery->status,
                'tracking_number' => $delivery->tracking_info['tracking_number'] ?? null,
                'tracking_url' => $delivery->tracking_info['tracking_url'] ?? null,
            ])
            ->toArray();

        return $this->jsonResponse([
            'success' => true,
            'deliveries' => array_slice($deliveries, 0, 6),
        ]);
    }
}
