<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\SetOffResult;
use App\Framework\Database\Database;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;

class SetOffService
{
    public function __construct(
        private readonly CreatorLiabilityRepository $liabilityRepository,
        private readonly CreatorLiabilityService $liabilityService,
        private readonly Database $database,
    ) {
    }

    public function apply(int $userId, int $siteId, int $settledAmount): SetOffResult
    {
        if ($settledAmount < 0) {
            throw new \InvalidArgumentException('Settled amount cannot be negative.');
        }

        return $this->database->transaction(function () use ($userId, $siteId, $settledAmount): SetOffResult {
            $remainingAvailable = $settledAmount;
            $deductedAmount = 0;
            $deductions = [];

            $liabilities = $this->liabilityRepository->findOpenForContributor($userId, $siteId);

            foreach ($liabilities as $liability) {
                if ($remainingAvailable <= 0) {
                    break;
                }

                $liabilityRemaining = (int) $liability->remaining_amount;

                if ($liabilityRemaining <= 0) {
                    continue;
                }

                $recoveryAmount = min($remainingAvailable, $liabilityRemaining);

                $this->liabilityService->recover((int) $liability->id, $recoveryAmount);

                $remainingAvailable -= $recoveryAmount;
                $deductedAmount += $recoveryAmount;

                $deductions[] = [
                    'liability_id' => (int) $liability->id,
                    'amount' => $recoveryAmount,
                    'source_type' => $liability->source_type,
                    'source_id' => $liability->source_id,
                    'reason' => $liability->reason,
                ];
            }

            return new SetOffResult(
                grossAmount: $settledAmount,
                deductedAmount: $deductedAmount,
                netAmount: max(0, $settledAmount - $deductedAmount),
                deductions: $deductions,
            );
        });
    }
}