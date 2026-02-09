<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class RewardBlockData extends BaseBlockData
{
    public function __construct(
        public readonly int $rewardId
    )
    {
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['reward_id'])) {
            throw new \InvalidArgumentException('Missing required field: reward_id');
        }

        return new self((int)$data['reward_id']);
    }
}