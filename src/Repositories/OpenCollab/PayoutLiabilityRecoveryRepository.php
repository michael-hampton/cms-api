<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PayoutLiabilityRecovery;
use App\Repositories\Repository;

class PayoutLiabilityRecoveryRepository extends Repository
{
    public function record(
        int $payoutId,
        int $creatorLiabilityId,
        int $amount,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $reason = null,
    ): Model {
        return $this->create([
            'payout_id' => $payoutId,
            'creator_liability_id' => $creatorLiabilityId,
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reason' => $reason,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function forPayout(int $payoutId): Collection
    {
        return PayoutLiabilityRecovery::where('payout_id', $payoutId)
            ->orderBy('id')
            ->get();
    }

    protected function getModelClass(): string
    {
        return PayoutLiabilityRecovery::class;
    }
}