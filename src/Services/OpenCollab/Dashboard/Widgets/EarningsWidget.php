<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Services\OpenCollab\CreatorBalanceService;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;
use App\Services\OpenCollab\EarningsService;

final class EarningsWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly EarningsService                    $earningsService,
        private readonly CreatorBalanceService              $creatorBalanceService,
        private readonly ArticlePaymentRepository           $paymentRepository,
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
        $siteId = SiteContext::getId();

        $balances = $this->creatorBalanceService->balances(
            userId: (int)$user->id,
            siteId: $siteId,
        );

        $payoutAccount = $this->payoutAccountRepository->findByUserId((int)$user->id, 'stripe');

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

        dd($this->earningsService->earningsBreakdownForContributor($user->id));

        return [
            'total' => $this->earningsService->totalEarningsForContributor($user->id),

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

            'breakdown' => $this->earningsService->earningsBreakdownForContributor($user->id),
            'transactions' => $this->paymentRepository->transactionsForContributor($user->id),
            'payment_details' => $paymentDetails,
        ];
    }
}