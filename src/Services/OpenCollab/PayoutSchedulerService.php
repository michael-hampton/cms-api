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
 *   5. Skip groups that already have an in-flight payout for that same currency
 *      (coarse business guard — an admin-approved payout, a manual request,
 *      etc. for the same currency also blocks a new scheduler payout).
 *   6. Create one pending Payout per (user, currency) group, keyed by a
 *      deterministic idempotency key (user + site + currency + cutoff date)
 *      via PayoutService::makeScheduledPayoutIdempotencyKey(). This closes
 *      the check-then-act race step 5 alone cannot close: if two scheduler
 *      runs overlap, the DB's unique index on idempotency_key rejects the
 *      second insert instead of creating a duplicate payout.
 *   7. Each payout creation is wrapped in its own transaction — a failure
 *      for one user does not block others.
 *
 * One payout per: user + currency + cutoff window.
 */
class PayoutSchedulerService
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly PayoutRepository         $payoutRepository,
        private readonly PaymentTermsService      $paymentTermsService,
        private readonly Database                 $database,
        private readonly Logger                   $logger,
        private readonly PayoutService            $payoutService,
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

                // Coarse business guard: don't schedule a new payout for this
                // currency while one is already pending/approved (manual or
                // scheduler-created) for the contributor.
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

                $idempotencyKey = $this->payoutService->makeScheduledPayoutIdempotencyKey(
                    $userId,
                    $siteId,
                    $currency,
                    $cutoff,
                );

                // Fast-path skip: this run's window has already produced a
                // payout for this (user, site, currency). The DB's unique
                // index on idempotency_key is the actual race-closing
                // mechanism below — this check just avoids an unnecessary
                // failed-insert round trip in the common case.
                if ($this->payoutRepository->findByIdempotencyKey($idempotencyKey)) {
                    $this->logger->info('Skipping scheduler payout — already created for this window.', [
                        'user_id' => $userId,
                        'currency' => $currency,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                    continue;
                }

                try {
                    $this->database->transaction(function () use ($userId, $siteId, $currency, $totalPence, $idempotencyKey): void {
                        $this->payoutRepository->createWithIdempotency([
                            'user_id' => $userId,
                            'site_id' => $siteId,
                            'amount' => $totalPence,
                            'currency' => strtoupper($currency),
                            'status' => PayoutStatus::Pending->value,
                            'method' => 'bank_transfer',
                            'idempotency_key' => $idempotencyKey,
                        ]);
                    });

                    $created++;

                    $this->logger->info('Scheduler created payout.', [
                        'user_id' => $userId,
                        'currency' => $currency,
                        'amount' => $totalPence,
                    ]);
                } catch (\Throwable $e) {
                    // A duplicate-key failure here means a concurrent scheduler
                    // run won the race for this exact window — not a real error.
                    if ($this->payoutRepository->findByIdempotencyKey($idempotencyKey)) {
                        $this->logger->info('Scheduler payout already created by a concurrent run.', [
                            'user_id' => $userId,
                            'currency' => $currency,
                            'idempotency_key' => $idempotencyKey,
                        ]);
                        continue;
                    }

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