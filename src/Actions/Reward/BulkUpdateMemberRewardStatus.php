<?php

namespace App\Actions\Reward;

use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardsRepository;

class BulkUpdateMemberRewardStatus
{
    public function __construct(
        private readonly Database          $database,
        private readonly RewardsRepository $repository
    )
    {
    }

    public function handle(array $rewardIds, string $status): array
    {
        $validStatuses = ['pending', 'claimed', 'expired', 'declined'];

        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status value');
        }

        return $this->database->transaction(function () use ($rewardIds, $status) {
            $updated = [];
            $failed = [];

            foreach ($rewardIds as $rewardId) {
                try {
                    $reward = $this->repository->find($rewardId);

                    if (!$reward) {
                        $failed[] = ['id' => $rewardId, 'reason' => 'Member reward not found'];
                        continue;
                    }

                    $updateData = ['status' => $status];

                    // Set appropriate timestamp based on status
                    if ($status === 'claimed' && !$reward->claimed_at) {
                        $updateData['claimed_at'] = now_datetime()->format('Y-m-d H:i:s');
                    } elseif ($status === 'declined' && !$reward->declined_at) {
                        $updateData['declined_at'] = now_datetime()->format('Y-m-d H:i:s');
                    }

                    $updatedReward = $this->repository->update($rewardId, $updateData);

                    if ($updatedReward) {
                        $updated[] = $rewardId;
                    } else {
                        $failed[] = ['id' => $rewardId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $rewardId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($rewardIds)
            ];
        });
    }
}