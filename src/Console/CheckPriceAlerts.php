<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Services\Offers\PriceAlertService;

class CheckPriceAlerts extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'offers:check-price-alerts';
    public $description = 'Checks and triggers price alerts for users.';

    public function __construct(
        private readonly PriceAlertService $priceAlertService
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('offers:check-price-alerts');

        try {
            $triggeredCount = $this->priceAlertService->checkAlerts();
            $stats = $this->priceAlertService->getAlertStats();

            $result->incrementSucceeded();
            $result->addMessage("Triggered {$triggeredCount} price alerts.");
            $result->addMessage("Stats - Active: {$stats['active_alerts']}, Pending Notification: {$stats['triggered_alerts']}");
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "Failed to process price alerts: {$e->getMessage()}",
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}