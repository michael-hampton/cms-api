<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\Cms\TerritoryRepository;

class BulkDeleteTerritories
{
    public function __construct(
        private readonly Database            $database,
        private readonly TerritoryRepository $repository,
    )
    {
    }

    public function handle(array $territoryIds): array
    {
        return $this->database->transaction(function() use ($territoryIds) {
            $deleted = [];
            $failed = [];

            foreach ($territoryIds as $territoryId) {
                try {
                    $territory = $this->repository->find($territoryId);

                    if (!$territory) {
                        $failed[] = ['id' => $territoryId, 'reason' => 'Territory not found'];
                        continue;
                    }

                    $pageCount = $territory->getPageCount();

                    if ($pageCount > 0) {
                        $failed[] = [
                            'id' => $territoryId,
                            'reason' => "Territory has {$pageCount} associated pages"
                        ];
                        continue;
                    }

                    if ($this->repository->delete($territoryId)) {
                        $deleted[] = $territoryId;
                    } else {
                        $failed[] = ['id' => $territoryId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $territoryId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($territoryIds)
            ];
        });
    }
}