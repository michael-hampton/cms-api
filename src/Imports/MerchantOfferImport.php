<?php

namespace App\Imports;

use App\Enums\Offers\OfferStatus;
use App\Framework\Database\Database;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\MerchantProductRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;

final class MerchantOfferImport extends BaseMerchantImport
{
    public function __construct(
        Database                                    $database,
        CsvParser                                   $csvParser,
        private readonly ProductOfferRepository     $offerRepository,
        private readonly MerchantProductRepository  $merchantProductRepository,
        private readonly RewardDefinitionRepository $rewardDefinitionRepository
    )
    {
        parent::__construct($database, $csvParser);
    }

    protected function requiredColumns(): array
    {
        return ['product_id', 'sale_price', 'start_date', 'end_date'];
    }

    protected function importRow(array $row): void
    {
        $productId = $this->parseNonNegativeInt($row['product_id'], 'product_id');
        $salePrice = $this->parseNonNegativeFloat($row['sale_price'], 'sale_price');
        $startDate = $this->parseDate($row['start_date'], 'start_date');
        $endDate = $this->parseDate($row['end_date'], 'end_date');

        if ($startDate > $endDate) {
            throw new SkippableRowException("start_date must be before or equal to end_date.");
        }

        if (!$this->merchantProductRepository->existsForMerchant($productId, $this->importOptions->merchantId)) {
            throw new SkippableRowException(
                "Product ID {$productId} is not in the catalog for merchant {$this->importOptions->merchantId}."
            );
        }

        $rewardDefinitionId = $this->resolveRewardDefinitionId($row);

        $existing = $this->offerRepository->findByProductAndMerchant($productId, $this->importOptions->merchantId);

        $data = [
            'product_id' => $productId,
            'merchant_id' => $this->importOptions->merchantId,
            'sale_price' => $salePrice,
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
            'status' => OfferStatus::PENDING->value,
            'is_active' => true,
            'reward_definition_id' => $rewardDefinitionId,
        ];

        if ($existing) {
            if (!$this->importOptions->updateExisting) {
                throw new SkippableRowException(
                    "An offer for product ID {$productId} already exists for this merchant. Skipping (updateExisting=false)."
                );
            }

            $this->offerRepository->update($existing->id, $data);
            return;
        }

        $this->offerRepository->create($data);
    }

    private function resolveRewardDefinitionId(array $row): ?int
    {
        if (empty($row['reward_id'])) {
            return null;
        }

        $rewardId = (int)$row['reward_id'];
        $definition = $this->rewardDefinitionRepository->findRewardDefinitionById($rewardId);

        if ($definition === null) {
            throw new SkippableRowException("Reward ID {$rewardId} does not exist.");
        }

        if ((int)$definition->site_id !== $this->importOptions->siteId) {
            throw new SkippableRowException(
                "Reward ID {$rewardId} does not belong to this site."
            );
        }

        return $rewardId;
    }
}