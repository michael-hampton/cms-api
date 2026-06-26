<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\CreatorBalanceService;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

final class EarningsWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly CreatorBalanceService              $creatorBalanceService,
        private readonly EarningsLedgerRepository           $ledgerRepository,
        private readonly ContributorPayoutAccountRepository $payoutAccountRepository,
    )
    {
    }

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
        $userId = (int)$user->id;
        $siteId = SiteContext::getId();

        $balances = $this->creatorBalanceService->balances(
            userId: $userId,
            siteId: $siteId,
        );

        $payoutAccount = $this->payoutAccountRepository->findByUserId($userId, 'stripe');

        $paymentDetails = null;

        if ($payoutAccount) {
            $paymentDetails = [
                'provider' => 'stripe',
                'method' => 'bank_account',
                'connected' => !empty($payoutAccount->stripe_account_id),
                'status' => $payoutAccount->payouts_enabled ? 'enabled' : 'incomplete',
                'payouts_enabled' => (bool)$payoutAccount->payouts_enabled,
                'verification_required' => (array)($payoutAccount->requirements_due_json ?? []),
            ];
        }

        return [
            'total' => $this->ledgerRepository->totalEarningsForContributor($userId, $siteId),

            'estimated' => $balances['estimated_balance'] ?? 0,
            'confirmed' => $balances['confirmed_balance'] ?? 0,
            'settled' => $balances['settled_balance'] ?? 0,
            'withdrawn' => $balances['withdrawn_balance'] ?? 0,
            'reversed' => $balances['reversed_balance'] ?? 0,

            'open_liabilities' => $balances['open_liabilities'] ?? 0,
            'pending_payouts' => $balances['in_flight_payouts'] ?? 0,
            'available_to_withdraw' => $balances['available_to_withdraw'] ?? 0,

            // Backwards-compatible aliases for old dashboard renderers.
            'pending' => $balances['available_to_withdraw'] ?? 0,
            'available' => $balances['available_to_withdraw'] ?? 0,

            'breakdown' => $this->ledgerRepository->earningsBreakdownForContributor($userId, $siteId),
            'transactions' => $this->ledgerRepository->transactionHistoryForContributor($userId, $siteId),
            'payment_details' => $paymentDetails,
        ];
    }
}
