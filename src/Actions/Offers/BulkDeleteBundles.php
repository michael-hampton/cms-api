<?php

namespace App\Actions\Offers;

use App\Framework\Database\Database;
use App\Repositories\Offers\ProductOfferBundleRepository;
use Exception;

class BulkDeleteBundles
{
    private Database $database;

    public function __construct(
        private readonly ProductOfferBundleRepository $bundleRepository,
        ?Database                                     $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * @param int[] $bundleIds
     */
    public function handle(array $bundleIds): array
    {
        if (empty($bundleIds)) {
            throw new Exception('No bundle IDs provided');
        }

        return $this->database->transaction(function () use ($bundleIds) {
            $deleted = [];
            $failed = [];

            foreach ($bundleIds as $bundleId) {
                try {
                    $bundle = $this->bundleRepository->find($bundleId);

                    if (!$bundle) {
                        $failed[] = ['id' => $bundleId, 'reason' => 'Bundle not found'];
                        continue;
                    }

                    if ($this->bundleRepository->delete($bundleId)) {
                        $deleted[] = $bundleId;
                    } else {
                        $failed[] = ['id' => $bundleId, 'reason' => 'Delete failed'];
                    }
                } catch (Exception $e) {
                    $failed[] = ['id' => $bundleId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($bundleIds),
            ];
        });
    }
}