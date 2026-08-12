<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutRepository;

/**
 * Automated payout scheduling. Runs as a daily cron job.
 *
 * Algorithm:
 *   1. Fetch eligible ledger entries per site (past payout_delay_days, not yet paid).
 *   2. Group by user_id then by currency.
 *   3. For each (user, currency) group: sum the amount.
 *   4. Skip groups below the site minimum threshold.
 *   5. Skip groups that already have an in-flight payout for that same currency.
 *   6. Create one pending Payout per (user, currency) group.
 *   7. Each payout creation is wrapped in its own transaction — a failure
 *      for one user does not block others.
 *
 * One payout per: user + currency. This is enforced by checking in-flight
 * totals scoped by currency before creating, mirroring the manual requestPayout guard.
 */
class PayoutSchedulerService
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly PayoutRepository         $payoutRepository,
        private readonly PaymentTermsService      $paymentTermsService,
        private readonly Database                 $database,
        private readonly Logger                   $logger,
    )
    {
    }

    /**
     * Run the scheduler for a site.
     *
     * Returns the number of payouts created.
     */
    public function run(int $siteId): int
    {
        $terms = $this->paymentTermsService->forSite($siteId);
        $cutoff = (new \DateTime())->modify("-{$terms->payout_delay_days} days");

        $eligibleByUser = $this->ledgerRepository->eligibleGroupedBySiteAndUser($siteId, $cutoff);

        $created = 0;

        foreach ($eligibleByUser as $userId => $entriesByUser) {
            $byCurrency = $this->groupByCurrency($entriesByUser);

            foreach ($byCurrency as $currency => $entries) {
                $totalPence = array_sum(array_map(
                    fn($e) => (int)($e['amount'] ?? 0),
                    $entries
                ));

                // Enforce minimum threshold.
                if ($totalPence < $terms->minimum_payout_amount) {
                    $this->logger->info('Skipping scheduler payout — below minimum threshold.', [
                        'user_id' => $userId,
                        'currency' => $currency,
                        'amount' => $totalPence,
                        'minimum' => $terms->minimum_payout_amount,
                    ]);
                    continue;
                }

                // Prevent duplicate in-flight payout scoped to this currency.
                $inFlight = $this->payoutRepository->hasInFlightForContributorAndCurrency(
                    $userId,
                    $currency,
                    $siteId,
                );
                if ($inFlight) {
                    $this->logger->info('Skipping scheduler payout — in-flight payout exists.', [
                        'user_id' => $userId,
                        'currency' => $currency,
                    ]);
                    continue;
                }

                try {
                    $this->database->transaction(function () use ($userId, $siteId, $currency, $totalPence): void {
                        $this->payoutRepository->create([
                            'user_id' => $userId,
                            'site_id' => $siteId,
                            'amount' => $totalPence,
                            'currency' => strtoupper($currency),
                            'status' => PayoutStatus::Pending->value,
                            'method' => 'bank_transfer',
                        ]);
                    });

                    $created++;

                    $this->logger->info('Scheduler created payout.', [
                        'user_id' => $userId,
                        'currency' => $currency,
                        'amount' => $totalPence,
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Scheduler failed to create payout for user.', [
                        'user_id' => $userId,
                        'currency' => $currency,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $created;
    }

    /**
     * Groups an array of ledger entry arrays by currency value.
     *
     * @param array<int, array{amount: int, currency: string}> $entries
     * @return array<string, array>
     */
    private function groupByCurrency(array $entries): array
    {
        $grouped = [];
        foreach ($entries as $entry) {
            $currency = strtoupper($entry['currency'] ?? 'GBP');
            $grouped[$currency][] = $entry;
        }
        return $grouped;
    }
}