<?php

namespace App\Services\Members;

use App\Repositories\MemberInsights\MemberActivityRepository;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\CrmMemberRepository;
use App\Repositories\Members\MemberStatRepository;
use App\Services\Members\Consents\ConsentQueryService;

class CrmMemberProfileService
{
    public function __construct(
        private readonly CrmMemberRepository      $crmMemberRepository,
        private readonly AddressRepository        $addressRepository,
        private readonly MemberStatRepository     $memberStatRepository,
        private readonly MemberActivityRepository $memberActivityRepository,
        private readonly ConsentQueryService      $consentQueryService,
    )
    {
    }

    public function buildDetailPayload(object $member, int $siteId): array
    {
        $addresses = $this->addressRepository->getAddressesForMember($member->id);
        $subscriptions = $this->crmMemberRepository->getRecentSubscriptionsForMember($member->id, $siteId);
        $orders = $this->crmMemberRepository->getRecentOrdersForMember($member->id, $siteId);
        $orderSummary = $this->crmMemberRepository->getOrderSummaryForMember($member->id, $siteId);
        $memberStats = $this->memberStatRepository->getForMember($member->id, $siteId);
        $recentActivities = $this->memberActivityRepository->getMemberActivities($member->id, 12);

        $activeSubscription = $subscriptions->first(function ($subscription) {
            return $subscription->isActive() || $subscription->isTrialing();
        });

        return [
            ...$member->toArray(),
            'full_name' => $member->full_name,
            'created_at' => $member->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $member->updated_at?->format('Y-m-d H:i:s'),
            'email_verified_at' => $member->email_verified_at?->format('Y-m-d H:i:s'),
            'last_login_at' => $member->last_login_at?->format('Y-m-d H:i:s'),
            'consents' => $this->groupConsents($this->consentQueryService->getMemberConsents($member)),
            'activity' => [
                'stat_cards' => [
                    ['key' => 'orders', 'label' => 'Orders', 'value' => $memberStats?->order_count ?? 0],
                    ['key' => 'comments', 'label' => 'Comments', 'value' => $memberStats?->comment_count ?? 0],
                    ['key' => 'pages_read', 'label' => 'Pages Read', 'value' => $memberStats?->view_count ?? 0],
                    ['key' => 'likes', 'label' => 'Likes', 'value' => $memberStats?->like_count ?? 0],
                    ['key' => 'rewards_claimed', 'label' => 'Rewards', 'value' => $memberStats?->reward_claimed_count ?? 0],
                    ['key' => 'articles_gifted', 'label' => 'Gifts Sent', 'value' => $memberStats?->articles_gifted_count ?? 0],
                ],
                'orders_total' => $orderSummary['total'],
                'member_days' => $member->created_at ? now_datetime()->diffInDays($member->created_at) : 0,
                'recent_activities' => $recentActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'activity_type' => $activity->activity_type,
                        'entity_type' => $activity->entity_type,
                        'entity_id' => $activity->entity_id,
                        'metadata' => $activity->metadata ?? [],
                        'points' => $activity->points,
                        'activity_date' => $activity->activity_date?->format('Y-m-d H:i:s'),
                        'created_at' => $activity->created_at?->format('Y-m-d H:i:s'),
                    ];
                })->values(),
            ],
            'subscription_summary' => [
                'active' => $activeSubscription ? [
                    'id' => $activeSubscription->id,
                    'plan_name' => $activeSubscription->plan_name,
                    'status' => $activeSubscription->status,
                    'type' => $activeSubscription->type,
                    'price' => $activeSubscription->price,
                    'currency' => $activeSubscription->currency,
                    'start_date' => $activeSubscription->start_date?->format('Y-m-d H:i:s'),
                    'end_date' => $activeSubscription->end_date?->format('Y-m-d H:i:s'),
                    'next_billing_date' => $activeSubscription->next_billing_date?->format('Y-m-d H:i:s'),
                    'auto_renew' => (bool)$activeSubscription->auto_renew,
                ] : null,
                'recent' => $subscriptions->map(function ($subscription) {
                    return [
                        'id' => $subscription->id,
                        'plan_name' => $subscription->plan_name,
                        'status' => $subscription->status,
                        'type' => $subscription->type,
                        'price' => $subscription->price,
                        'currency' => $subscription->currency,
                        'start_date' => $subscription->start_date?->format('Y-m-d H:i:s'),
                        'end_date' => $subscription->end_date?->format('Y-m-d H:i:s'),
                    ];
                })->values(),
            ],
            'subscriptions' => $subscriptions->map(function ($subscription) {
                return array_merge($subscription->toArray(), [
                    'created_at' => $subscription->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $subscription->updated_at?->format('Y-m-d H:i:s'),
                    'start_date' => $subscription->start_date?->format('Y-m-d H:i:s'),
                    'end_date' => $subscription->end_date?->format('Y-m-d H:i:s'),
                    'next_billing_date' => $subscription->next_billing_date?->format('Y-m-d H:i:s'),
                ]);
            }),
            'recent_orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => (float)$order->total,
                    'currency' => $order->currency,
                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                ];
            })->values(),
            'addresses' => $addresses->map(function ($address) {
                return [
                    ...$address->toArray(),
                    'created_at' => $address->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $address->updated_at?->format('Y-m-d H:i:s'),
                ];
            })->values(),
        ];
    }

    private function groupConsents(array $consents): array
    {
        $grouped = [];

        foreach ($consents as $consent) {
            $type = $consent['consent_type'];
            $isLocked = $type['category'] === 'essential' || !empty($type['required']);
            $consent['is_locked'] = $isLocked;

            if ($isLocked) {
                $consent['is_granted'] = true;
            }

            $grouped[$type['category']] ??= [];
            $grouped[$type['category']][] = $consent;
        }

        return $grouped;
    }
}
