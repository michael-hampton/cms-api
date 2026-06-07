<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Repositories\OpenCollab\CreatorLiabilityRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutRepository;

class CreatorBalanceService
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly CreatorLiabilityRepository $liabilityRepository,
        private readonly PayoutRepository $payoutRepository,
    ) {
    }

    public function balances(int $userId, int $siteId): array
    {
        $ledgerBalances = $this->ledgerRepository->balancesByStatusForContributor($userId, $siteId);

        $estimated = $this->statusAmount($ledgerBalances, AccrualStatus::Estimated);
        $confirmed = $this->statusAmount($ledgerBalances, AccrualStatus::Confirmed);
        $settled = $this->statusAmount($ledgerBalances, AccrualStatus::Settled);
        $withdrawn = $this->statusAmount($ledgerBalances, AccrualStatus::Withdrawn);
        $reversed = $this->statusAmount($ledgerBalances, AccrualStatus::Reversed);

        $openLiabilities = $this->liabilityRepository->openAmountForContributor($userId, $siteId);
        $inFlightPayouts = $this->payoutRepository->totalInFlightForContributor($userId, $siteId);

        return [
            'estimated_balance' => $estimated,
            'confirmed_balance' => $confirmed,
            'settled_balance' => $settled,
            'withdrawn_balance' => $withdrawn,
            'reversed_balance' => $reversed,
            'open_liabilities' => $openLiabilities,
            'in_flight_payouts' => $inFlightPayouts,
            'available_to_withdraw' => max(0, $settled - $openLiabilities - $inFlightPayouts),
        ];
    }

    public function availableToWithdraw(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['available_to_withdraw'];
    }

    public function estimatedBalance(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['estimated_balance'];
    }

    public function confirmedBalance(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['confirmed_balance'];
    }

    public function settledBalance(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['settled_balance'];
    }

    public function withdrawnBalance(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['withdrawn_balance'];
    }

    public function reversedBalance(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['reversed_balance'];
    }

    public function openLiabilities(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['open_liabilities'];
    }

    public function inFlightPayouts(int $userId, int $siteId): int
    {
        return $this->balances($userId, $siteId)['in_flight_payouts'];
    }

    private function statusAmount(array $balances, AccrualStatus $status): int
    {
        return (int) ($balances[$status->value] ?? 0);
    }
}
