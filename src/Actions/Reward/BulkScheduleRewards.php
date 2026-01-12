<?php

namespace App\Actions\Reward;

use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardsRepository;

class BulkScheduleRewards
{
    public function __construct(
        private readonly Database          $database,
        private readonly RewardsRepository $repository
    )
    {
    }

    /**
     * @param array $schedules Array of ['reward_id' => int, 'expires_at' => string]
     */
    public function handle(array $schedules): array
    {
        return $this->database->transaction(function () use ($schedules) {
            $results = [];

            foreach ($schedules as $schedule) {
                $rewardId = $schedule['reward_id'];
                $expiresAt = $schedule['expires_at'];

                try {
                    $reward = $this->repository->find($rewardId);

                    if (!$reward) {
                        $results[$rewardId] = [
                            'success' => false,
                            'error' => 'Reward not found'
                        ];
                        continue;
                    }

                    if ($reward->status !== 'pending') {
                        $results[$rewardId] = [
                            'success' => false,
                            'error' => 'Can only schedule pending rewards'
                        ];
                        continue;
                    }

                    $updated = $this->repository->update($rewardId, [
                        'expires_at' => $expiresAt
                    ]);

                    if ($updated) {
                        $results[$rewardId] = [
                            'success' => true,
                            'expires_at' => $expiresAt
                        ];
                    } else {
                        $results[$rewardId] = [
                            'success' => false,
                            'error' => 'Failed to update reward'
                        ];
                    }
                } catch (\Exception $e) {
                    $results[$rewardId] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $results;
        });
    }
}