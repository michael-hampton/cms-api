<?php

namespace App\Actions\Offers;

use App\Framework\Database\Database;
use App\Repositories\Offers\ProductOfferBundleRepository;
use Exception;

class BulkPublishBundles
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
     * @param int $publishedByUserId
     */
    public function handle(array $bundleIds, int $publishedByUserId): array
    {
        if (empty($bundleIds)) {
            throw new Exception('No bundle IDs provided');
        }

        return $this->database->transaction(function () use ($bundleIds, $publishedByUserId) {
            $published = [];
            $failed = [];

            foreach ($bundleIds as $bundleId) {
                try {
                    $bundle = $this->bundleRepository->find($bundleId);

                    if (!$bundle) {
                        $failed[] = ['id' => $bundleId, 'reason' => 'Bundle not found'];
                        continue;
                    }

                    if ($bundle->status !== 'pending') {
                        $failed[] = [
                            'id' => $bundleId,
                            'reason' => "Bundle cannot be published from status '{$bundle->status}'",
                        ];
                        continue;
                    }

                    $this->bundleRepository->update($bundleId, [
                        'status' => 'published',
                        'published_at' => now(),
                        'published_by' => $publishedByUserId,
                    ]);

                    $published[] = $bundleId;
                } catch (Exception $e) {
                    $failed[] = ['id' => $bundleId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'published' => $published,
                'failed' => $failed,
                'total' => count($bundleIds),
            ];
        });
    }
}