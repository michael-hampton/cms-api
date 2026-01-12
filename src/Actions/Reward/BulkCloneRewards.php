<?php

namespace App\Actions\Reward;

use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Services\Cms\ImageUploadService;

class BulkCloneRewards
{
    public function __construct(
        private readonly Database                   $database,
        private readonly RewardDefinitionRepository $repository,
        private readonly ImageUploadService         $imageUploadService
    )
    {
    }

    public function handle(array $rewardIds, array $options = []): array
    {
        return $this->database->transaction(function () use ($rewardIds, $options) {
            $results = [];
            $withPrefix = $options['withPrefix'] ?? true;

            foreach ($rewardIds as $rewardId) {
                try {
                    $originalReward = $this->repository->find($rewardId);

                    if (!$originalReward) {
                        $results[$rewardId] = [
                            'success' => false,
                            'error' => 'Reward not found'
                        ];
                        continue;
                    }

                    $newName = $withPrefix
                        ? $originalReward->name . ' (Copy)'
                        : $originalReward->name;

                    $data = [
                        'site_id' => $originalReward->site_id,
                        'name' => $newName,
                        'slug' => $this->generateUniqueSlug($newName, $originalReward->site_id),
                        'description' => $originalReward->description,
                        'reward_type' => $originalReward->reward_type,
                        'criteria' => $originalReward->criteria,
                        'reward_config' => $originalReward->reward_config,
                        'max_claims_per_member' => $originalReward->max_claims_per_member,
                        'is_active' => false,
                        'sort_order' => $originalReward->sort_order
                    ];

                    $newReward = $this->repository->create($data);

                    $originalReward->addCloneRecord('cloned_to', $newReward->id, null);
                    $newReward->addCloneRecord('cloned_from', $originalReward->id, null);

                    $results[$rewardId] = [
                        'success' => true,
                        'cloned_reward_id' => $newReward->id
                    ];
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

    private function generateUniqueSlug(string $name, int $siteId): string
    {
        $baseSlug = \App\Framework\Support\Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->repository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}