<?php

namespace App\Imports;

use App\Enums\Vouchers\VoucherType;
use App\Enums\VoucherStatus;
use App\Framework\Database\Database;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Vouchers\VoucherRepository;

final class MerchantVoucherImport extends BaseMerchantImport
{
    public function __construct(
        Database                                    $database,
        CsvParser                                   $csvParser,
        private readonly VoucherRepository          $voucherRepository,
        private readonly RewardDefinitionRepository $rewardDefinitionRepository
    )
    {
        parent::__construct($database, $csvParser);
    }

    protected function requiredColumns(): array
    {
        return ['code', 'type', 'value', 'start_date', 'end_date', 'usage_limit'];
    }

    protected function importRow(array $row): void
    {
        $code = strtoupper(trim($row['code']));

        $type = VoucherType::tryFrom(strtolower(trim($row['type'])));
        if ($type === null) {
            throw new SkippableRowException(
                "Invalid voucher type '{$row['type']}'. Allowed: percentage, fixed."
            );
        }

        $value = $this->parseNonNegativeFloat($row['value'], 'value');
        $startDate = $this->parseDate($row['start_date'], 'start_date');
        $endDate = $this->parseDate($row['end_date'], 'end_date');
        $usageLimit = $this->parseNonNegativeInt($row['usage_limit'], 'usage_limit');

        if ($startDate > $endDate) {
            throw new SkippableRowException("start_date must be before or equal to end_date.");
        }

        $minimumOrderValue = isset($row['minimum_order_value']) && $row['minimum_order_value'] !== ''
            ? $this->parseNonNegativeFloat($row['minimum_order_value'], 'minimum_order_value')
            : null;

        $maximumDiscount = isset($row['maximum_discount']) && $row['maximum_discount'] !== ''
            ? $this->parseNonNegativeFloat($row['maximum_discount'], 'maximum_discount')
            : null;

        $rewardDefinitionId = $this->resolveRewardDefinitionId($row);

        $existing = $this->voucherRepository->findByCodeAndMerchant($code, $this->importOptions->merchantId);

        $data = [
            'code' => $code,
            'type' => $type->value,
            'value' => $value,
            'starts_at' => $startDate->format('Y-m-d H:i:s'),
            'expires_at' => $endDate->format('Y-m-d H:i:s'),
            'usage_limit' => $usageLimit,
            'usage_count' => $existing ? $existing->usage_count : 0,
            'status' => VoucherStatus::ACTIVE->value,
            'site_id' => $this->importOptions->siteId,
            'merchant_id' => $this->importOptions->merchantId,
            'minimum_order_value' => $minimumOrderValue,
            'maximum_discount' => $maximumDiscount,
            'name' => $row['name'] ?? $code,
            'description' => $row['description'] ?? null,
            'reward_definition_id' => $rewardDefinitionId,
        ];

        if ($existing) {
            if (!$this->importOptions->updateExisting) {
                throw new SkippableRowException(
                    "Voucher code '{$code}' already exists for this merchant. Skipping (updateExisting=false)."
                );
            }

            $this->voucherRepository->update($existing->id, $data);
            return;
        }

        $this->voucherRepository->create($data);
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