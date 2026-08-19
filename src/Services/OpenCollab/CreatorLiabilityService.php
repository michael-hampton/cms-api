<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\CreatorLiabilityStatus;
use App\Framework\Database\Database;
use App\Models\Model;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;

class CreatorLiabilityService
{
    public function __construct(
        private readonly CreatorLiabilityRepository $liabilityRepository,
        private readonly Database $database,
    ) {
    }

    public function create(
        int $userId,
        int $siteId,
        string $sourceType,
        ?int $sourceId,
        int $amount,
        string $currency,
        string $reason,
        ?int $createdBy = null,
    ): Model {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Creator liability amount must be greater than zero.');
        }

        if (trim($sourceType) === '') {
            throw new \InvalidArgumentException('Creator liability source type is required.');
        }

        if (trim($currency) === '') {
            throw new \InvalidArgumentException('Creator liability currency is required.');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Creator liability reason is required.');
        }

        return $this->liabilityRepository->createOpenLiability(
            userId: $userId,
            siteId: $siteId,
            sourceType: $sourceType,
            sourceId: $sourceId,
            amount: $amount,
            currency: strtoupper($currency),
            reason: $reason,
            createdBy: $createdBy,
        );
    }

    /**
     * Reduces remaining_amount by $amount, marking the liability Recovered
     * once it hits zero or PartiallyRecovered otherwise.
     *
     * The read-then-write on remaining_amount is done inside a transaction,
     * re-fetching the row immediately before computing the new amount —
     * mirroring PaymentRetryService::retry()'s guard against the same class
     * of problem (concurrent recover() calls racing on a stale read).
     */
    public function recover(int $liabilityId, int $amount): Model
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Recovery amount must be greater than zero.');
        }

        return $this->database->transaction(function () use ($liabilityId, $amount): Model {
            // Read happens inside the transaction, immediately before the
            // write it informs — guards against a concurrent recover() call
            // racing on a stale remaining_amount read.
            $liability = $this->liabilityRepository->findOrFail($liabilityId);

            if ((int) $liability->remaining_amount <= 0) {
                return $liability;
            }

            $newRemainingAmount = max(0, (int) $liability->remaining_amount - $amount);

            $status = $newRemainingAmount === 0
                ? CreatorLiabilityStatus::Recovered
                : CreatorLiabilityStatus::PartiallyRecovered;

            $updates = [
                'remaining_amount' => $newRemainingAmount,
                'status' => $status->value,
                'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
            ];

            if ($status === CreatorLiabilityStatus::Recovered) {
                $updates['settled_at'] = now_datetime()->format('Y-m-d H:i:s');
            }

            $this->liabilityRepository->update($liabilityId, $updates);

            return $this->liabilityRepository->findOrFail($liabilityId);
        });
    }

    public function writeOff(int $liabilityId, ?int $actorId, string $reason): Model
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A write-off reason is required.');
        }

        $liability = $this->liabilityRepository->findOrFail($liabilityId);

        if ((int) $liability->remaining_amount <= 0) {
            return $liability;
        }

        $this->liabilityRepository->update($liabilityId, [
            'remaining_amount' => 0,
            'status' => CreatorLiabilityStatus::WrittenOff->value,
            'settled_at' => now_datetime()->format('Y-m-d H:i:s'),
            'written_off_by' => $actorId,
            'write_off_reason' => $reason,
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        return $this->liabilityRepository->findOrFail($liabilityId);
    }
}