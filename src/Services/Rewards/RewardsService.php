<?php

namespace App\Services\Rewards;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;

class RewardsService
{
    public function __construct(
        private readonly RewardsRepository $rewardsRepository
    )
    {
    }

    public function checkAndAwardRewards(Member $member, int $siteId): array
    {
        $awarded = [];
        $definitions = $this->rewardsRepository->getActiveRewardDefinitions($siteId);

        foreach ($definitions as $definition) {
            if ($this->shouldAwardReward($member, $definition)) {
                try {
                    $reward = $this->awardReward($member, $definition, $siteId);
                    if ($reward) {
                        $awarded[] = $reward;
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to award reward', [
                        'member_id' => $member->id,
                        'reward_definition_id' => $definition->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $awarded;
    }

    private function shouldAwardReward(Member $member, RewardDefinition $definition): bool
    {
        // Check if already earned max allowed times
        $earnedCount = $this->rewardsRepository->countMemberRewards(
            $member->id,
            $definition->id
        );

        if ($earnedCount >= $definition->max_claims_per_member) {
            return false;
        }

        // Check if criteria is met
        return $definition->checkCriteria($member);
    }

    private function awardReward(Member $member, RewardDefinition $definition, int $siteId): ?MemberReward
    {
        $rewardData = [];
        $expiresAt = null;

        // Handle different reward types
        switch ($definition->reward_type) {
            case 'voucher':
                $voucher = $this->rewardsRepository->getAvailableVoucher($definition->id);

                if (!$voucher) {
                    Logger::warning('No available vouchers for reward', [
                        'reward_definition_id' => $definition->id
                    ]);
                    return null;
                }

                $rewardData = [
                    'voucher_code' => $voucher->voucher_code,
                    'provider' => $voucher->provider,
                    'value' => $voucher->value,
                    'currency' => $voucher->currency
                ];

                // Set expiration if configured
                $expiryDays = $definition->reward_config['expiry_days'] ?? 90;
                $expiresAt = now_datetime()->modify("+{$expiryDays} days");
                break;

            case 'discount':
                $rewardData = [
                    'discount_type' => $definition->reward_config['discount_type'] ?? 'percentage',
                    'discount_value' => $definition->reward_config['discount_value'] ?? 10
                ];

                $expiryDays = $definition->reward_config['expiry_days'] ?? 30;
                $expiresAt = now_datetime()->modify("+{$expiryDays} days");
                break;

            case 'points':
                $rewardData = [
                    'points' => $definition->reward_config['points'] ?? 100
                ];
                break;
        }

        $reward = $this->rewardsRepository->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
            'site_id' => $siteId,
            'reward_data' => $rewardData,
            'expires_at' => $expiresAt
        ]);

        // Assign voucher if applicable
        if ($definition->reward_type === 'voucher' && isset($voucher)) {
            $voucher->assign($member->id, $reward->id);
        }

        return $reward;
    }

    public function getUnclaimedRewards(Member $member, int $siteId): Collection
    {
        return $this->rewardsRepository->getMemberRewards($member->id, $siteId, 'pending');
    }

    public function getMemberRewards(Member $member, int $siteId): Collection
    {
        return $this->rewardsRepository->getMemberRewards($member->id, $siteId);
    }

    public function claimReward(int $rewardId, Member $member): array
    {
        $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

        if (!$reward || $reward->member_id !== $member->id) {
            return [
                'success' => false,
                'message' => 'Reward not found'
            ];
        }

        if ($reward->isExpired()) {
            return [
                'success' => false,
                'message' => 'This reward has expired'
            ];
        }

        if ($reward->isClaimed()) {
            // Track view even if already claimed
            $this->rewardsRepository->trackClick(
                $reward->id,
                $member->id,
                $reward->site_id,
                'view',
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            return [
                'success' => true,
                'already_claimed' => true,
                'reward' => $reward,
                'message' => 'This reward has already been claimed'
            ];
        }

        $reward->claim();

        // Track claim
        $this->rewardsRepository->trackClick(
            $reward->id,
            $member->id,
            $reward->site_id,
            'claim',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        return [
            'success' => true,
            'reward' => $reward,
            'message' => 'Reward claimed successfully!'
        ];
    }
}