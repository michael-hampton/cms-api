<?php

namespace App\Actions\Traits;

trait UpdateRegionSetActiveStatus
{
    private function bulkUpdateActiveStatus(array $regionSetIds, bool $isActive): array
    {
        return $this->database->transaction(function() use ($regionSetIds, $isActive) {
            $updated = [];
            $failed = [];

            foreach ($regionSetIds as $regionSetId) {
                try {
                    $regionSet = $this->repository->find($regionSetId);

                    if (!$regionSet) {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Region set not found'];
                        continue;
                    }

                    $updatedRegionSet = $this->repository->update($regionSetId, ['is_active' => $isActive]);

                    if ($updatedRegionSet) {
                        $updated[] = $regionSetId;
                    } else {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $regionSetId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($regionSetIds)
            ];
        });
    }
}