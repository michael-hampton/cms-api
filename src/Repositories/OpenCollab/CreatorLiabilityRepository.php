<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\CreatorLiabilityStatus;
use App\Framework\Support\Collection;
use App\Models\CreatorLiability;
use App\Models\Model;
use App\Repositories\Repository;

class CreatorLiabilityRepository extends Repository
{
    public function createOpenLiability(
        int $userId,
        int $siteId,
        string $sourceType,
        ?int $sourceId,
        int $amount,
        string $currency,
        string $reason,
        ?int $createdBy = null,
    ): Model {
        return $this->create([
            'user_id' => $userId,
            'site_id' => $siteId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
            'remaining_amount' => $amount,
            'currency' => $currency,
            'status' => CreatorLiabilityStatus::Open->value,
            'reason' => $reason,
            'created_by' => $createdBy,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function openAmountForContributor(int $userId, int $siteId): int
    {
        return (int) CreatorLiability::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->whereIn('status', [
                CreatorLiabilityStatus::Open->value,
                CreatorLiabilityStatus::PartiallyRecovered->value,
            ])
            ->sum('remaining_amount');
    }

    public function findOpenForContributor(int $userId, int $siteId): Collection
    {
        return CreatorLiability::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->whereIn('status', [
                CreatorLiabilityStatus::Open->value,
                CreatorLiabilityStatus::PartiallyRecovered->value,
            ])
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int $liabilityId): Model
    {
        $liability = $this->find($liabilityId);

        if (!$liability) {
            throw new \InvalidArgumentException("Creator liability [{$liabilityId}] not found.");
        }

        return $liability;
    }

    public function openAmountForSite(int $siteId): int
    {
        return (int) CreatorLiability::where('site_id', $siteId)
            ->whereIn('status', [
                CreatorLiabilityStatus::Open->value,
                CreatorLiabilityStatus::PartiallyRecovered->value,
            ])
            ->sum('remaining_amount');
    }

    protected function getModelClass(): string
    {
        return CreatorLiability::class;
    }
}