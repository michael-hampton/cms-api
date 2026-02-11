<?php

namespace App\Services\Vouchers\Providers;

use App\Repositories\Rewards\RewardsRepository;
use App\Services\Vouchers\Contracts\DiscountProvider;
use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext;

class RewardDiscountProvider implements DiscountProvider
{
    public function __construct(
        private readonly ?int               $rewardId = null,
        private readonly ?RewardsRepository $rewardRepository = null
    )
    {
    }

    public function priority(): int
    {
        return 40; // After vouchers, before store credit
    }

    public function apply(DiscountContext $context): ?DiscountApplicationResult
    {
        if (!$this->supports($context)) {
            return null;
        }

        $reward = $this->rewardRepository->find($this->rewardId);

        if (!$reward || !$reward->isPending() || $reward->isExpired()) {
            return null;
        }

        // Verify reward belongs to member
        if ($reward->member_id !== $context->member->id) {
            return null;
        }


        $definition = $reward->rewardDefinition;

        if (!$definition) {

            return null;
        }

        // Check subscription compatibility
        if ($context->isSubscription && !$this->isApplicableToSubscription($definition, $context)) {
            return null;
        }

        $rewardConfig = $definition->reward_config;
        $rewardType = $definition->reward_type;

        $discountCents = match ($rewardType) {
            'percentage_discount' => $this->calculatePercentageDiscount($context, $rewardConfig),
            'fixed_discount' => $this->calculateFixedDiscount($context, $rewardConfig),
            default => 0
        };

        if ($discountCents === 0) {
            return null;
        }

        return new DiscountApplicationResult(
            discountAmountCents: $discountCents,
            affectedItemIds: array_map(fn($item) => $item['id'] ?? $item['product_id'], $context->items),
            stackable: true, // Rewards are typically stackable
            fundingSource: 'platform', // Platform funds rewards
            type: 'reward',
            metadata: [
                'reward_id' => $this->rewardId,
                'reward_definition_id' => $definition->id,
                'reward_type' => $rewardType
            ]
        );
    }

    public function supports(DiscountContext $context): bool
    {
        if ($this->rewardId === null || $context->member === null) {
            return false;
        }

        return true;
    }

    private function isApplicableToSubscription(object $definition, DiscountContext $context): bool
    {
        $config = $definition->reward_config ?? [];
        $appliesTo = $config['applies_to'] ?? 'one_time';

        if ($appliesTo === 'one_time') {
            return false;
        }

        if ($appliesTo === 'subscription_first_cycle' && !$context->isFirstSubscriptionCycle) {
            return false;
        }

        return true;
    }

    private function calculatePercentageDiscount(DiscountContext $context, array $config): int
    {
        $percentage = $config['percentage'] ?? 0;

        if ($percentage <= 0 || $percentage > 100) {
            return 0;
        }

        return (int)round($context->currentSubtotalCents * ($percentage / 100));
    }

    private function calculateFixedDiscount(DiscountContext $context, array $config): int
    {
        $amount = $config['amount'] ?? 0;

        if ($amount <= 0) {
            return 0;
        }

        $amountCents = (int)round($amount * 100);

        // Cap at current subtotal
        return min($amountCents, $context->currentSubtotalCents);
    }
}