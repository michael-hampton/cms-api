<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\LedgerEntryType;
use App\Models\EarningsLedger;
use App\Repositories\Repository;

class EarningsLedgerRepository extends Repository
{
    public function recordSale(
        int    $userId,
        int    $articleId,
        int    $amount,
        string $currency,
        string $referenceId,
    ): EarningsLedger
    {
        return $this->create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => LedgerEntryType::Sale->value,
            'amount' => $amount,
            'currency' => $currency,
            'reference_id' => $referenceId,
        ]);
    }

    public function recordRefund(
        int    $userId,
        int    $articleId,
        int    $amount,
        string $currency,
        string $referenceId,
    ): EarningsLedger
    {
        return $this->create([
            'user_id' => $userId,
            'article_id' => $articleId,
            'type' => LedgerEntryType::Refund->value,
            'amount' => -abs($amount), // refunds are negative
            'currency' => $currency,
            'reference_id' => $referenceId,
        ]);
    }

    public function balanceForContributor(int $userId): int
    {
        return (int)EarningsLedger::where('user_id', $userId)->sum('amount');
    }

    protected function getModelClass(): string
    {
        return EarningsLedger::class;
    }
}