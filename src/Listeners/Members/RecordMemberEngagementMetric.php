<?php

namespace App\Listeners\Members;

use App\Events\Members\ArticleGiftedByMember;
use App\Events\Members\CommentPostedByMember;
use App\Events\Members\OrderCreatedByMember;
use App\Events\Members\PageLikedByMember;
use App\Events\Members\PageUnlikedByMember;
use App\Events\Members\PageViewedByMember;
use App\Events\Members\RewardClaimedByMember;
use App\Repositories\Members\MemberStatRepository;

class RecordMemberEngagementMetric
{
    public function __construct(
        private readonly MemberStatRepository $statRepository,
    )
    {
    }

    public function handlePageView(PageViewedByMember $event): void
    {
        $this->statRepository->increment($event->memberId, $event->siteId, 'view_count');
    }

    public function handlePageLike(PageLikedByMember $event): void
    {
        $this->statRepository->increment($event->memberId, $event->siteId, 'like_count');
    }

    /**
     * Decrement like_count when a member removes a previously-placed like.
     * The repository clamps at zero, so double-unlikes are safe.
     */
    public function handlePageUnlike(PageUnlikedByMember $event): void
    {
        $this->statRepository->decrement($event->memberId, $event->siteId, 'like_count');
    }

    public function handleComment(CommentPostedByMember $event): void
    {
        $this->statRepository->increment($event->memberId, $event->siteId, 'comment_count');
    }

    public function handleOrderCreated(OrderCreatedByMember $event): void
    {
        $this->statRepository->increment($event->memberId, $event->siteId, 'order_count');
    }

    public function handleRewardClaimed(RewardClaimedByMember $event): void
    {
        $this->statRepository->increment($event->memberId, $event->siteId, 'reward_claimed_count');
    }

    public function handleArticleGifted(ArticleGiftedByMember $event): void
    {
        // The sender always gets credit for gifting.
        $this->statRepository->increment($event->giftedByMemberId, $event->siteId, 'articles_gifted_count');

        // Recipient credit is only recorded when the gift has a known member on the receiving end.
        // Gifts sent to an email address that hasn't been claimed yet have no recipient_member_id.
        if ($event->recipientMemberId !== null) {
            $this->statRepository->increment($event->recipientMemberId, $event->siteId, 'articles_received_count');
        }
    }
}