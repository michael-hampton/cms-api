<?php

namespace App\Actions\Reward;

use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardsRepository;

class BulkDeclineRewards
{
    public function __construct(
        private readonly Database          $database,
        private readonly RewardsRepository $repository
    )
    {
    }

    public function handle(array $rewardIds, string $reason = null): array
    {
        return $this->database->transaction(function () use ($rewardIds, $reason) {
            $updated = [];
            $failed = [];

            foreach ($rewardIds as $rewardId) {
                try {
                    $reward = $this->repository->find($rewardId);

                    if (!$reward) {
                        $failed[] = ['id' => $rewardId, 'reason' => 'Reward not found'];
                        continue;
                    }

                    if ($reward->status !== 'pending') {
                        $failed[] = [
                            'id' => $rewardId,
                            'reason' => 'Can only decline pending rewards'
                        ];
                        continue;
                    }

                    $updateData = [
                        'status' => 'declined',
                        'declined_at' => now_datetime()->format('Y-m-d H:i:s')
                    ];

                    if ($reason) {
                        $updateData['decline_reason'] = $reason;
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