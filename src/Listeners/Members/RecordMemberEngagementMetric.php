<?php

namespace App\Listeners\Members;

use App\Enums\MemberMetricType;
use App\Events\Members\CommentPostedByMember;
use App\Events\Members\OrderCreatedByMember;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageViewedByMember;
use App\Events\Members\RewardClaimedByMember;
use App\Repositories\Members\MemberEngagementMetricRepository;

class RecordMemberEngagementMetric
{
    public function __construct(
        private readonly MemberEngagementMetricRepository $metricRepository,
    )
    {
    }

    public function handlePageView(PageViewedByMember $event): void
    {
        $this->record($event->memberId, $event->siteId, MemberMetricType::PageView, $event->pageId);
    }

    private function record(int $memberId, int $siteId, MemberMetricType $type, ?int $entityId): void
    {
        $this->metricRepository->record($memberId, $siteId, $type, $entityId);
    }

    public function handlePageLike(PageLikedByMember $event): void
    {
        $this->record($event->memberId, $event->siteId, MemberMetricType::PageLike, $event->pageId);
    }

    public function handleComment(CommentPostedByMember $event): void
    {
        $this->record($event->memberId, $event->siteId, MemberMetricType::CommentPosted, $event->entityId);
    }

    public function handleOrderCreated(OrderCreatedByMember $event): void
    {
        $this->record($event->memberId, $event->siteId, MemberMetricType::OrderCreated, $event->orderId);
    }

    public function handleRewardClaimed(RewardClaimedByMember $event): void
    {
        $this->record($event->memberId, $event->siteId, MemberMetricType::RewardClaimed, $event->rewardId);
    }
}