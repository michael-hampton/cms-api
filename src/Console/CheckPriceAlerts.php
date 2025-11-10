<?php

namespace App\Console;

use App\Services\PriceAlertService;

class CheckPriceAlerts
{
    private PriceAlertService $priceAlertService;

    public function __construct()
    {
        $this->priceAlertService = new PriceAlertService();
    }

    public function handle(): void
    {
        echo "Checking price alerts...\n";

        $triggeredCount = $this->priceAlertService->checkAlerts();

        echo "Triggered {$triggeredCount} price alerts\n";

        $stats = $this->priceAlertService->getAlertStats();
        echo "Active alerts: {$stats['active_alerts']}\n";
        echo "Triggered (not notified): {$stats['triggered_alerts']}\n";
    }
}