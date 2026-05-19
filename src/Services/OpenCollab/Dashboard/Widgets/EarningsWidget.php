<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Models\User;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;
use App\Services\OpenCollab\EarningsService;

/**
 * Earnings widget — lifetime total, pending payout, per-article breakdown,
 * and transaction history.
 *
 * Amounts are in pence/cents (integers). Formatting belongs to the JS renderer.
 *
 * Data shape:
 * {
 *   total:           int        — lifetime gross in pence
 *   pending:         int        — balance awaiting payout in pence
 *   breakdown:       [{page_id, title, total}]
 *   transactions:    [{page_title, amount, status, created_at}]
 *   payment_details: {email}|null  — connected Stripe account, or null
 * }
 */
final class EarningsWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly EarningsService           $earningsService,
        private readonly ArticlePaymentRepository  $paymentRepository,
    ) {}

    public function key(): string
    {
        return 'earnings';
    }

    public function title(): string
    {
        return 'Earnings';
    }

    public function visibleFor(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        $total = $this->earningsService->totalEarningsForContributor($user->id);

        return [
            'total'           => $total,
            // TODO: replace with PayoutService::pendingBalance() when available.
            // For now pending mirrors total — same as the old DashboardPageController TODO.
            'pending'         => $total,
            'breakdown'       => $this->earningsService->earningsBreakdownForContributor($user->id),
            'transactions'    => $this->paymentRepository->transactionsForContributor($user->id),
            // TODO: inject ContributorProfileRepository and resolve Stripe account details.
            'payment_details' => null,
        ];
    }
}