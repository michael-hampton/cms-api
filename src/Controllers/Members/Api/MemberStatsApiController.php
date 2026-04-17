<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Repositories\Members\MemberEngagementMetricRepository;
use App\Repositories\Members\MemberStatRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class MemberStatsApiController extends Controller
{
    public function __construct(
        private readonly MemberStatRepository   $memberStatRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriberRepository   $subscriberRepository,
    )
    {
        parent::__construct();
    }

    public function stats(): JsonResponse
    {
        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $stat = $this->memberStatRepository->getForMember($member->id, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'stats' => [
                    // Aggregated engagement counts — single row read.
                    'orders' => $stat?->order_count ?? 0,
                    'comments' => $stat?->comment_count ?? 0,
                    'pages_read' => $stat?->view_count ?? 0,
                    'likes' => $stat?->like_count ?? 0,
                    'rewards_claimed' => $stat?->reward_claimed_count ?? 0,
                    'articles_gifted' => $stat?->articles_gifted_count ?? 0,
                    'articles_received' => $stat?->articles_received_count ?? 0,

                    // Live counts — cheap single-table queries, not worth pre-aggregating.
                    'subscriptions' => $this->subscriptionRepository->countActiveSubscriptions($member->id, $siteId),
                    'newsletters' => $this->subscriberRepository->getNewslettersForMember($member->email, $siteId)->count(),
                    'addresses' => Address::where('member_id', $member->id)->count(),

                    // Computed behavioural profile — populated by BuildMemberStats command.
                    'summary' => $stat?->summary ?? [],
                    'scores' => $stat?->scores ?? [],
                    'behaviour' => $stat?->behaviour ?? [],
                    'trends' => $stat?->trends ?? [],
                    'interests' => $stat?->interests ?? [],
                    'flags' => $stat?->flags ?? [],
                ],
            ],
        ]);
    }
}