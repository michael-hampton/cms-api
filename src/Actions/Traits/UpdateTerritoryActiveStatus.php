<?php

namespace App\Actions\Traits;

trait UpdateTerritoryActiveStatus
{
    private function bulkUpdateActiveStatus(array $territoryIds, bool $isActive): array
    {
        return $this->database->transaction(function() use ($territoryIds, $isActive) {
            $updated = [];
            $failed = [];

            foreach ($territoryIds as $territoryId) {
                try {
                    $territory = $this->repository->find($territoryId);

                    if (!$territory) {
                        $failed[] = ['id' => $territoryId, 'reason' => 'Territory not found'];
                        continue;
                    }

                    $updatedTerritory = $this->repository->update($territoryId, ['is_active' => $isActive]);

                    if ($updatedTerritory) {
                        $updated[] = $territoryId;
                    } else {
                        $failed[] = ['id' => $territoryId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $territoryId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($territoryIds)
            ];
        });
    }
}