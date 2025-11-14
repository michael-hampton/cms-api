<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\RegionSetRepository;

class BulkDeleteRegionSet
{
    private Database $database;

    public function __construct(
        Database                                 $database,
        private readonly RegionSetRepository     $repository,
    ) {
        $this->database = $database ?? Database::getInstance();
    }
    public function handle(array $regionSetIds): array
    {
        return $this->database->transaction(function() use ($regionSetIds) {
            $deleted = [];
            $failed = [];

            foreach ($regionSetIds as $regionSetId) {
                try {
                    $regionSet = $this->repository->find($regionSetId);

                    if (!$regionSet) {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Region set not found'];
                        continue;
                    }

                    $territoryCount = $regionSet->getTerritoryCount();
                    $pageCount = $regionSet->getPageCount();

                    if ($territoryCount > 0 || $pageCount > 0) {
                        $failed[] = [
                            'id' => $regionSetId,
                            'reason' => "Region set has {$territoryCount} territories and {$pageCount} pages"
                        ];
                        continue;
                    }

                    if ($this->repository->delete($regionSetId)) {
                        $deleted[] = $regionSetId;
                    } else {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $regionSetId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($regionSetIds)
            ];
        });
    }
}