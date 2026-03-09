<?php

namespace App\Actions\Offers;

use App\Framework\Database\Database;
use App\Repositories\Offers\ProductOfferRepository;
use Exception;

class BulkPublishOffers
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
     * @param int $publishedByUserId
     */
    public function handle(array $offerIds, int $publishedByUserId): array
    {
        if (empty($offerIds)) {
            throw new Exception('No offer IDs provided');
        }

        return $this->database->transaction(function () use ($offerIds, $publishedByUserId) {
            $published = [];
            $failed = [];

            foreach ($offerIds as $offerId) {
                try {
                    $offer = $this->offerRepository->find($offerId);

                    if (!$offer) {
                        $failed[] = ['id' => $offerId, 'reason' => 'Offer not found'];
                        continue;
                    }

                    if ($offer->status !== 'pending') {
                        $failed[] = [
                            'id' => $offerId,
                            'reason' => "Offer cannot be published from status '{$offer->status}'",
                        ];
                        continue;
                    }

                    $this->offerRepository->update($offerId, [
                        'status' => 'published',
                        'published_at' => now(),
                        'published_by' => $publishedByUserId,
                    ]);

                    $published[] = $offerId;
                } catch (Exception $e) {
                    $failed[] = ['id' => $offerId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'published' => $published,
                'failed' => $failed,
                'total' => count($offerIds),
            ];
        });
    }
}