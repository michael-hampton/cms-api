<?php

namespace App\Services\Rewards;

use App\Framework\Database\Database;
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
            'expires_at' => $expiresAt?->toDateTimeString() ?? null
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

    public function getRewardDefinitionStatistics(int $definitionId): array
    {
        $definition = RewardDefinition::find($definitionId);

        if (!$definition) {
            throw new Exception('Reward definition not found');
        }

        $memberRewards = MemberReward::where('reward_definition_id', $definitionId)->get();

        $totalRewards = $memberRewards->count();
        $claimedRewards = $memberRewards->where('status', 'claimed')->count();
        $pendingRewards = $memberRewards->where('status', 'pending')->count();
        $expiredRewards = $memberRewards->where('status', 'expired')->count();
        $declinedRewards = $memberRewards->where('status', 'declined')->count();

        // Get click statistics
        $totalClicks = DB::table('reward_clicks')
            ->whereIn('member_reward_id', $memberRewards->pluck('id'))
            ->count();

        $uniqueClickers = DB::table('reward_clicks')
            ->whereIn('member_reward_id', $memberRewards->pluck('id'))
            ->distinct('member_id')
            ->count('member_id');

        // Breakdown by action type
        $clicksByAction = DB::table('reward_clicks')
            ->whereIn('member_reward_id', $memberRewards->pluck('id'))
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        // Click through rate
        $clickThroughRate = $totalRewards > 0
            ? round(($uniqueClickers / $totalRewards) * 100, 2)
            : 0;

        // Recent clicks
        $recentClicks = Database::table('reward_clicks as rc')
            ->join('member_rewards as mr', 'rc.member_reward_id', '=', 'mr.id')
            ->join('members as m', 'rc.member_id', '=', 'm.id')
            ->whereIn('rc.member_reward_id', $memberRewards->pluck('id')->toArray())
            ->select(
                'rc.created_at',
                'rc.action',
                'm.first_name',
                'm.last_name',
                'm.email',
                'mr.id as reward_id'
            )
            ->orderByDesc('rc.created_at')
            ->limit(10)
            ->get();

        return [
            'definition_id' => $definitionId,
            'definition_name' => $definition->name,
            'total_rewards' => $totalRewards,
            'claimed' => $claimedRewards,
            'pending' => $pendingRewards,
            'expired' => $expiredRewards,
            'declined' => $declinedRewards,
            'claim_rate' => $totalRewards > 0 ? round(($claimedRewards / $totalRewards) * 100, 2) : 0,

            // Click statistics
            'total_clicks' => $totalClicks,
            'unique_clickers' => $uniqueClickers,
            'click_through_rate' => $clickThroughRate,
            'clicks_by_action' => $clicksByAction,
            'recent_clicks' => $recentClicks->map(fn($click) => [
                'clicked_at' => $click->created_at,
                'action' => $click->action,
                'member_name' => "{$click->first_name} {$click->last_name}",
                'member_email' => $click->email,
                'reward_id' => $click->reward_id
            ])->toArray(),
        ];
    }
}