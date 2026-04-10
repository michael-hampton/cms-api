<?php

namespace App\Services\OpenCollab;

use App\Repositories\OpenCollab\ArticlePaymentRepository;

/**
 * Read-only earnings aggregations for the contributor dashboard.
 *
 * Amounts are returned in pence/cents (integers). Formatting for display
 * is the responsibility of the resource layer — never here.
 */
class EarningsService
{
    public function __construct(
        private readonly ArticlePaymentRepository $paymentRepository,
    )
    {
    }

    /**
     * Returns total earnings in pence/cents for a contributor.
     */
    public function totalEarningsForContributor(int $contributorId): int
    {
        return $this->paymentRepository->sumSucceededAmountForContributor($contributorId);
    }

    /**
     * Returns a per-page earnings breakdown for the dashboard.
     * [ ['page_id' => int, 'total' => int], ... ]
     */
    public function earningsBreakdownForContributor(int $contributorId): array
    {
        return $this->paymentRepository->earningsBreakdownForContributor($contributorId);
    }
}