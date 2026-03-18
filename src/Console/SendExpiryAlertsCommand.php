<?php

declare(strict_types=1);

namespace App\Console;

use App\Enums\Alerts\ExpiryAlertThreshold;
use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Services\Alerts\OfferExpiryAlertService;

/**
 * Sends expiry alerts for offers, bundles, and gift promotions
 * at each configured threshold (48h and 24h before expiry).
 *
 * Schedule: $schedule->command(SendExpiryAlertsCommand::class)->hourly();
 *
 * Running hourly ensures no entity slips through the window between
 * the threshold boundary and the next daily run.
 */
class SendExpiryAlertsCommand extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'offers:send-expiry-alerts';
    public $description = 'Sends expiry alerts to merchants and members for offers, bundles, and promotions approaching their end date.';

    public function __construct(
        private readonly OfferExpiryAlertService $service,
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('offers:send-expiry-alerts');

        foreach (ExpiryAlertThreshold::ordered() as $threshold) {
            $this->processEntityType(
                result: $result,
                label: 'ProductOffer',
                threshold: $threshold,
                callback: fn() => $this->service->processOffers($threshold),
            );

            $this->processEntityType(
                result: $result,
                label: 'ProductOfferBundle',
                threshold: $threshold,
                callback: fn() => $this->service->processBundles($threshold),
            );

            $this->processEntityType(
                result: $result,
                label: 'GiftPromotion',
                threshold: $threshold,
                callback: fn() => $this->service->processPromotions($threshold),
            );
        }

        $this->reportResult($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function processEntityType(
        mixed                $result,
        string               $label,
        ExpiryAlertThreshold $threshold,
        callable             $callback,
    ): void
    {
        try {
            $stats = $callback();

            $result->incrementSucceeded();
            $result->addMessage(
                "[{$threshold->value}h] {$label}: "
                . "{$stats['processed']} alerted, {$stats['skipped']} skipped."
            );
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "[{$threshold->value}h] {$label} failed: {$e->getMessage()}",
                context: ['threshold_hours' => $threshold->value, 'entity' => $label],
                throwable: $e,
            );
        }
    }
}