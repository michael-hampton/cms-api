<?php

namespace App\Services\Rewards;

use App\Enums\RewardClickAction;
use App\Enums\RewardStatus;
use App\Enums\RewardType;
use App\Events\Rewards\RewardAwardedEvent;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Rewards\Handlers\RewardTypeHandlerFactory;

class RewardsService
{
    public function __construct(
        private readonly RewardsRepository        $rewardsRepository,
        private readonly RewardTypeHandlerFactory $handlerFactory,
        private readonly Database                 $database
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
                    throw $e; // Bubble up instead of silent failure
                }
            }
        }

        return $awarded;
    }

    private function shouldAwardReward(Member $member, $definition): bool
    {
        $earnedCount = $this->rewardsRepository->countMemberRewards(
            $member->id,
            $definition->id
        );

        if ($earnedCount >= $definition->max_claims_per_member) {
            return false;
        }

        return $definition->checkCriteria($member);
    }

    private function awardReward(Member $member, $definition, int $siteId): ?MemberReward
    {
        return $this->database->transaction(function () use ($member, $definition, $siteId) {
            $rewardType = RewardType::from($definition->reward_type);
            $handler = $this->handlerFactory->make($rewardType);

            $result = $handler->handle($member, $definition, $siteId);

            if (!$result) {
                return null;
            }

            $reward = $this->rewardsRepository->createMemberReward([
                'member_id' => $member->id,
                'reward_definition_id' => $definition->id,
                'site_id' => $siteId,
                'reward_data' => $result['reward_data'],
                'expires_at' => $result['expires_at'] ?? null
            ]);

            // Assign voucher if applicable
            if ($rewardType === RewardType::VOUCHER && isset($result['reward_data']['voucher'])) {
                $result['reward_data']['voucher']->assign($member->id, $reward->id);
            }

            event(new RewardAwardedEvent($member, $reward));

            return $reward;
        });
    }

    public function getUnclaimedRewards(Member $member, int $siteId): Collection
    {
        return $this->rewardsRepository->getMemberRewards(
            $member->id,
            $siteId,
            RewardStatus::PENDING->value
        );
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
            $this->trackRewardClick(
                $reward,
                $member,
                RewardClickAction::VIEW
            );

            return [
                'success' => true,
                'already_claimed' => true,
                'reward' => $reward,
                'message' => 'This reward has already been claimed'
            ];
        }

        return $this->database->transaction(function () use ($reward, $member) {
            $reward->claim();

            $this->trackRewardClick(
                $reward,
                $member,
                RewardClickAction::CLAIM
            );

            return [
                'success' => true,
                'reward' => $reward,
                'message' => 'Reward claimed successfully!'
            ];
        });
    }

    private function trackRewardClick(
        MemberReward      $reward,
        Member            $member,
        RewardClickAction $action
    ): void
    {
        $this->rewardsRepository->trackClick(
            $reward->id,
            $member->id,
            $reward->site_id,
            $action->value,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }


    public function getTopRewards(Member $member, int $siteId): Collection
    {
        $allDefinitions = $this->rewardsRepository->getActiveRewardDefinitions($siteId);
        $earnedRewardIds = $this->rewardsRepository
            ->getMemberRewards($member->id, $siteId)
            ->pluck('reward_definition_id')
            ->toArray();

        return $allDefinitions->filter(function ($definition) use ($earnedRewardIds, $member) {
            // Filter out already earned rewards
            if (in_array($definition->id, $earnedRewardIds)) {
                return false;
            }

            // Only show rewards the member hasn't qualified for yet
            return !$definition->checkCriteria($member);
        })->take(5);
    }

    public function getRewardStats(Member $member, int $siteId): array
    {
        $allRewards = $this->rewardsRepository->getMemberRewards($member->id, $siteId);

        $activeRewards = $allRewards->filter(function ($reward) {
            return !in_array($reward->status, ['declined', 'expired']) && !$reward->isClaimed();
        });

        $claimedRewards = $allRewards->filter(fn($r) => $r->isClaimed());

        // Calculate total gift card value from claimed rewards
        $giftCardTotal = $claimedRewards->reduce(function ($carry, $reward) {
            $rewardData = $reward->reward_data;

            // Make sure reward_data is an array
            if (!is_array($rewardData)) {
                return $carry;
            }

            // Check for voucher value
            if (isset($rewardData['value']) && is_numeric($rewardData['value'])) {
                return $carry + (float)$rewardData['value'];
            }

            // Check for discount value (if fixed amount)
            if (isset($rewardData['discount_value']) &&
                isset($rewardData['discount_type']) &&
                $rewardData['discount_type'] === 'fixed' &&
                is_numeric($rewardData['discount_value'])) {
                return $carry + (float)$rewardData['discount_value'];
            }

            return $carry;
        }, 0);

        // Get currency from first claimed reward or default to GBP
        $currency = 'GBP';
        $currencySymbol = '£';

        $firstClaimed = $claimedRewards->first();
        if ($firstClaimed && is_array($firstClaimed->reward_data) && isset($firstClaimed->reward_data['currency'])) {
            $currency = $firstClaimed->reward_data['currency'];
            $currencySymbol = $this->getCurrencySymbol($currency);
        }

        return [
            'active_count' => $activeRewards->count(),
            'claimed_count' => $claimedRewards->count(),
            'gift_card_total' => $giftCardTotal,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol
        ];
    }

    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€',
        ];

        return $symbols[$currency] ?? $currency;
    }
}