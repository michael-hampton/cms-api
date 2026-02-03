<?php

namespace App\Services\Rewards\Handlers;

use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;

class VoucherRewardHandler implements RewardTypeHandlerInterface
{
    private const DEFAULT_EXPIRY_DAYS = 90;

    public function __construct(
        private readonly RewardsRepository $rewardsRepository
    )
    {
    }

    public function handle(Member $member, RewardDefinition $definition, int $siteId): ?array
    {
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
            'currency' => $voucher->currency,
            'voucher' => $voucher
        ];

        $expiryDays = $definition->reward_config['expiry_days'] ?? self::DEFAULT_EXPIRY_DAYS;
        $expiresAt = now_datetime()->modify("+{$expiryDays} days");

        return [
            'reward_data' => $rewardData,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s')
        ];
    }
}