<?php

namespace App\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\MemberReward;
use App\Repositories\Rewards\RewardsRepository;

class RewardVisibilityResolver
{
    public function __construct(
        public readonly RewardsRepository $rewardsRepository
    )
    {
    }

    public function resolveForMember(int $memberId, int $siteId, RenderContext $context): array
    {
        $rewards = $this->rewardsRepository->getMemberRewards($memberId, $siteId, 'pending');
        $decisions = [];

        foreach ($rewards as $reward) {
            $decision = $this->resolve($reward, $context);
            if ($decision->shouldRender) {
                $decisions[] = [
                    'reward' => $reward,
                    'decision' => $decision,
                ];
            }
        }

        return $decisions;
    }

    public function resolve(MemberReward $reward, RenderContext $context): VisibilityDecision
    {
        // Must be authenticated
        if (!$context->memberId) {
            return VisibilityDecision::hide(SuppressionReason::NOT_AUTHENTICATED);
        }

        // Must belong to this member
        if ($reward->member_id !== $context->memberId) {
            return VisibilityDecision::hide(SuppressionReason::WRONG_MEMBER);
        }

        // Check reward state
        if ($reward->isClaimed()) {
            return VisibilityDecision::hide(SuppressionReason::ALREADY_CLAIMED);
        }

        if ($reward->isExpired()) {
            return VisibilityDecision::hide(SuppressionReason::REWARD_EXPIRED);
        }

        if ($reward->isDeclined()) {
            return VisibilityDecision::hide(SuppressionReason::REWARD_DECLINED);
        }

        if (!$reward->isPending()) {
            return VisibilityDecision::hide(SuppressionReason::INVALID_STATUS);
        }

        return VisibilityDecision::show([
            'reward_id' => $reward->id,
            'reward_name' => $reward->rewardDefinition?->name ?? 'Member Reward',
            'voucher_code' => $reward->voucherCode?->voucher_code,
            'deal_id' => $reward->rewardDefinition?->deal_id,
        ]);
    }
}