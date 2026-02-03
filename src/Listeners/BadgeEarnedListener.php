<?php

namespace App\Listeners;

use App\Events\Badges\BadgeEarnedEvent;
use App\Framework\Support\Logger;
use App\Services\Rewards\RewardsService;

class BadgeEarnedListener
{
    public function __construct(private readonly RewardsService $rewardsService)
    {

    }

    public function handle(BadgeEarnedEvent $event): void
    {

        // Set session flag for modal display
        $badge = $event->badge;
        if (!isset($_SESSION['badge_modal_shown_' . $badge->id])) {
            $_SESSION['show_badge_modal'] = true;
            $_SESSION['new_badge_data'] = [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon ?? '🏆',
                'points' => $badge->points,
                'earned_at' => now_datetime()->format('Y-m-d H:i:s')
            ];
        }

        try {
            $member = $event->member;
            $newRewards = $this->rewardsService->checkAndAwardRewards($member, $member->site_id);

            if (!empty($newRewards)) {
                // Store in session to show notification
                $_SESSION['new_rewards_earned'] = count($newRewards);
            }
        } catch (\Exception $e) {
            Logger::error('Failed to check rewards after badge award', [
                'member_id' => $member->id,
                'badge_id' => $badge->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}