<?php

namespace App\Services\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\Members\GiftedArticleRepository;
use App\Repositories\Rewards\RewardsRepository;

class NotificationService
{
    public function __construct(
        private readonly RewardsRepository       $rewardsRepository,
        private readonly GiftedArticleRepository $giftedArticleRepository
    )
    {
    }

    /**
     * Get total notification count
     */
    public function getNotificationCount(Member $member, int $siteId): int
    {
        $notifications = $this->getNotifications($member, $siteId);
        return array_sum(array_column($notifications, 'count'));
    }

    /**
     * Get all notifications for a member
     */
    public function getNotifications(Member $member, int $siteId): array
    {
        $notifications = [];

        // Unclaimed rewards
        $unclaimedRewards = $this->rewardsRepository->getMemberRewards($member->id, $siteId, 'pending');
        if ($unclaimedRewards->count() > 0) {
            $notifications[] = [
                'type' => 'reward',
                'icon' => '🎁',
                'title' => 'Unclaimed Rewards',
                'message' => "You have {$unclaimedRewards->count()} reward" . ($unclaimedRewards->count() > 1 ? 's' : '') . " waiting",
                'count' => $unclaimedRewards->count(),
                'url' => '/' . \App\Framework\Support\SiteContext::slug() . '/member/rewards',
                'priority' => 'high',
                'color' => 'success'
            ];
        }

        // Pending gifted articles
        $pendingGifts = $this->giftedArticleRepository->getPendingGiftsForMember($member->id, $member->email);
        if ($pendingGifts && $pendingGifts->count() > 0) {
            $notifications[] = [
                'type' => 'gift',
                'icon' => '💝',
                'title' => 'Gifted Articles',
                'message' => "You have {$pendingGifts->count()} unclaimed gift" . ($pendingGifts->count() > 1 ? 's' : ''),
                'count' => $pendingGifts->count(),
                'url' => '/' . \App\Framework\Support\SiteContext::slug() . '/member/gifted-articles',
                'priority' => 'high',
                'color' => 'info'
            ];
        }

        // Expiring rewards (within 7 days)
        $expiringRewards = $this->getExpiringRewards($member->id, $siteId);
        if ($expiringRewards->count() > 0) {
            $notifications[] = [
                'type' => 'expiring_reward',
                'icon' => '⏰',
                'title' => 'Rewards Expiring Soon',
                'message' => "{$expiringRewards->count()} reward" . ($expiringRewards->count() > 1 ? 's' : '') . " expiring in 7 days",
                'count' => $expiringRewards->count(),
                'url' => '/' . \App\Framework\Support\SiteContext::slug() . '/member/rewards',
                'priority' => 'medium',
                'color' => 'warning'
            ];
        }

        // Newly earned badges (from session)
        if (isset($_SESSION['new_badges_earned']) && $_SESSION['new_badges_earned'] > 0) {
            $notifications[] = [
                'type' => 'badge',
                'icon' => '🏆',
                'title' => 'New Badges Earned',
                'message' => "You earned {$_SESSION['new_badges_earned']} new badge" . ($_SESSION['new_badges_earned'] > 1 ? 's' : '') . "!",
                'count' => $_SESSION['new_badges_earned'],
                'url' => '/' . \App\Framework\Support\SiteContext::slug() . '/member/activity/badges',
                'priority' => 'high',
                'color' => 'success'
            ];
        }

        // Email verification reminder
        if (!$member->isEmailVerified()) {
            $notifications[] = [
                'type' => 'verification',
                'icon' => '⚠️',
                'title' => 'Verify Your Email',
                'message' => 'Please verify your email to unlock all features',
                'count' => 1,
                'url' => '/' . \App\Framework\Support\SiteContext::slug() . '/member/dashboard',
                'priority' => 'high',
                'color' => 'warning'
            ];
        }

        return $notifications;
    }

    /**
     * Get rewards expiring within days
     */
    private function getExpiringRewards(int $memberId, int $siteId, int $days = 7): Collection
    {
        $rewards = $this->rewardsRepository->getMemberRewards($memberId, $siteId, 'pending');

        $expiringDate = now_datetime()->modify("+{$days} days");

        return $rewards->filter(function ($reward) use ($expiringDate) {
            return $reward->expires_at &&
                $reward->expires_at <= $expiringDate &&
                $reward->expires_at > now_datetime();
        });
    }

    /**
     * Mark notification as seen (store in session)
     */
    public function markAsSeen(string $type): void
    {
        $key = "notification_seen_{$type}";
        $_SESSION[$key] = time();
    }

    /**
     * Check if notification was recently seen
     */
    public function wasRecentlySeen(string $type, int $seconds = 3600): bool
    {
        $key = "notification_seen_{$type}";
        if (!isset($_SESSION[$key])) {
            return false;
        }

        return (time() - $_SESSION[$key]) < $seconds;
    }
}