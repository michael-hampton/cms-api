<?php

namespace App\Services\OpenCollab;

use App\Models\Model;
use App\Models\PaymentTerms;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PaymentTermsRepository;

/**
 * Governs payment terms configuration and enforces them during eligibility checks.
 *
 * Payment terms are per-site only. No per-contributor overrides.
 *
 * Responsibilities:
 *   - CRUD for site payment terms
 *   - Determining which ledger entries are eligible for payout
 *     (respects payout_delay_days and minimum_payout_amount)
 */
class PaymentTermsService
{
    /**
     * Defaults applied when no payment terms row exists for a site.
     */
    private const DEFAULT_DELAY_DAYS = 7;
    private const DEFAULT_MINIMUM_PENCE = 5000; // £50.00

    public function __construct(
        private readonly PaymentTermsRepository   $paymentTermsRepository,
        private readonly EarningsLedgerRepository $ledgerRepository,
    )
    {
    }

    /**
     * Admin saves or updates payment terms for a site.
     *
     * @throws \InvalidArgumentException on invalid values
     */
    public function save(int $siteId, int $payoutDelayDays, int $minimumPayoutAmount): Model
    {
        if ($payoutDelayDays < 0) {
            throw new \InvalidArgumentException('Payout delay days cannot be negative.');
        }

        if ($minimumPayoutAmount < 0) {
            throw new \InvalidArgumentException('Minimum payout amount cannot be negative.');
        }

        return $this->paymentTermsRepository->upsertForSite(
            $siteId,
            $payoutDelayDays,
            $minimumPayoutAmount,
        );
    }

    /**
     * Returns ledger entries for a contributor that are past the payout delay
     * and not yet included in a paid payout.
     *
     * "Eligible" = created more than payout_delay_days ago AND not yet paid_at.
     */
    public function eligibleLedgerEntries(int $userId, int $siteId): \App\Framework\Support\Collection
    {
        $terms = $this->forSite($siteId);
        $cutoff = (new \DateTime())->modify("-{$terms->payout_delay_days} days");

        return $this->ledgerRepository->eligibleForPayout($userId, $cutoff);
    }

    /**
     * Returns the configured terms for a site, falling back to sensible defaults.
     */
    public function forSite(int $siteId): PaymentTerms
    {
        $terms = $this->paymentTermsRepository->forSite($siteId);

        if ($terms) {
            return $terms;
        }

        // Return an unsaved default model so callers always get a usable object.
        $default = new PaymentTerms([
            'site_id' => $siteId,
            'payout_delay_days' => self::DEFAULT_DELAY_DAYS,
            'minimum_payout_amount' => self::DEFAULT_MINIMUM_PENCE,
        ]);

        return $default;
    }

    /**
     * Whether a contributor meets the minimum payout threshold for a site.
     * Takes the eligible balance (after delay) into account.
     */
    public function meetsMinimumThreshold(int $userId, int $siteId, int $eligibleBalancePence): bool
    {
        $terms = $this->forSite($siteId);

        return $eligibleBalancePence >= $terms->minimum_payout_amount;
    }
}