<?php

namespace App\Actions\Reward;

use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardDefinitionRepository;

class BulkDeactivateRewardDefinitions
{
    public function __construct(
        private readonly Database                   $database,
        private readonly RewardDefinitionRepository $repository
    )
    {
    }

    public function handle(array $rewardDefinitionIds): array
    {
        return $this->database->transaction(function () use ($rewardDefinitionIds) {
            $updated = [];
            $failed = [];

            foreach ($rewardDefinitionIds as $rewardDefinitionId) {
                try {
                    $rewardDefinition = $this->repository->find($rewardDefinitionId);

                    if (!$rewardDefinition) {
                        $failed[] = ['id' => $rewardDefinitionId, 'reason' => 'Reward definition not found'];
                        continue;
                    }

                    $updatedRewardDefinition = $this->repository->update($rewardDefinitionId, ['is_active' => false]);

                    if ($updatedRewardDefinition) {
                        $updated[] = $rewardDefinitionId;
                    } else {
                        $failed[] = ['id' => $rewardDefinitionId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $rewardDefinitionId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($rewardDefinitionIds)
            ];
        });
    }
}