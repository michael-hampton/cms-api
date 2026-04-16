<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Enums\MemberMetricType;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Members\MemberEngagementMetricRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class MemberStatsApiController extends Controller
{
    public function __construct(
        private readonly MemberEngagementMetricRepository $metricRepository,
        private readonly SubscriptionRepository           $subscriptionRepository,
        private readonly OrderRepository                  $orderRepository,
        private readonly SubscriberRepository             $subscriberRepository
    )
    {
        parent::__construct();
    }

    public function stats(): JsonResponse
    {
        $member = MemberAuth::getMember();

        $siteId = SiteContext::getId();

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'stats' => [
                    'orders' => $this->metricRepository->countByType($member->id, $siteId, MemberMetricType::OrderCreated),
                    'subscriptions' => $this->subscriptionRepository->countActiveSubscriptions($member->id, $siteId),
                    'newsletters' => $this->subscriberRepository->getNewslettersForMember($member->email, $siteId)->count(),
                    'addresses' => Address::where('member_id', $member->id)->count(),
                    'comments' => $this->metricRepository->countByType($member->id, $siteId, MemberMetricType::CommentPosted),
                    'pages_read' => $this->metricRepository->countByType($member->id, $siteId, MemberMetricType::PageView),
                    'likes' => $this->metricRepository->countByType($member->id, $siteId, MemberMetricType::PageLike),
                ],
            ],
        ]);
    }
}