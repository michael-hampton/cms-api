<?php

namespace App\Actions\Offers;

use App\Framework\Database\Database;
use App\Repositories\Offers\ProductOfferRepository;
use Exception;

class BulkDeleteOffers
{
    private Database $database;

    public function __construct(
        private readonly ProductOfferRepository $offerRepository,
        ?Database                               $database = null
    )
    {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * @param int[] $offerIds
     */
    public function handle(array $offerIds): array
    {
        if (empty($offerIds)) {
            throw new Exception('No offer IDs provided');
        }

        return $this->database->transaction(function () use ($offerIds) {
            $deleted = [];
            $failed = [];

            foreach ($offerIds as $offerId) {
                try {
                    $offer = $this->offerRepository->find($offerId);

                    if (!$offer) {
                        $failed[] = ['id' => $offerId, 'reason' => 'Offer not found'];
                        continue;
                    }

                    if ($this->offerRepository->delete($offerId)) {
                        $deleted[] = $offerId;
                    } else {
                        $failed[] = ['id' => $offerId, 'reason' => 'Delete failed'];
                    }
                } catch (Exception $e) {
                    $failed[] = ['id' => $offerId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($offerIds),
            ];
        });
    }
}