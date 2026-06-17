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
        try {
            $member = $event->member;
            $newRewards = $this->rewardsService->checkAndAwardRewards($member, $member->site_id);

            if (!empty($newRewards)) {
                $_SESSION['new_rewards_earned'] = count($newRewards);
            }
        } catch (\Exception $e) {
            Logger::error('Failed to check rewards after badge award', [
                'member_id' => $event->member->id,
                'badge_id' => $event->badge->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
