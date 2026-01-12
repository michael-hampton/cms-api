<?php

namespace App\Actions\Reward;

use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardsRepository;

class BulkDeleteRewards
{
    public function __construct(
        private readonly Database          $database,
        private readonly RewardsRepository $repository
    )
    {
    }

    public function handle(array $rewardIds): array
    {
        return $this->database->transaction(function () use ($rewardIds) {
            $deleted = [];
            $failed = [];

            foreach ($rewardIds as $rewardId) {
                try {
                    $reward = $this->repository->find($rewardId);

                    if (!$reward) {
                        $failed[] = ['id' => $rewardId, 'reason' => 'Reward not found'];
                        continue;
                    }

                    // Only allow deletion of pending or declined rewards
                    if (!in_array($reward->status, ['pending', 'declined'])) {
                        $failed[] = [
                            'id' => $rewardId,
                            'reason' => 'Can only delete pending or declined rewards'
                        ];
                        continue;
                    }

                    if ($this->repository->delete($rewardId)) {
                        $deleted[] = $rewardId;
                    } else {
                        $failed[] = ['id' => $rewardId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $rewardId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($rewardIds)
            ];
        });
    }
}