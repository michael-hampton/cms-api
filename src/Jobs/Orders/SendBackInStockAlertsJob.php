<?php

namespace App\Jobs\Orders;

use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductStockAlertRepository;

class SendBackInStockAlertsJob extends BaseJob
{
    public function __construct(
        private readonly ProductRepository           $productRepository,
        private readonly ProductStockAlertRepository $alertRepository,
        private readonly MailManager                 $mailManager,
        private readonly Logger                      $logger
    )
    {
    }

    public function handle(int $productId): void
    {
        $product = $this->productRepository->find($productId);

        if (!$product || $product->stock_quantity <= 0) {
            return;
        }

        $alerts = $this->alertRepository->getPendingAlerts($productId);

        foreach ($alerts as $alert) {
            try {
                $email = $alert->email ?? $alert->user?->email;

                if ($email) {
                    $this->mailManager->to($email)->send(
                        new BackInStockNotification($product, $alert)
                    );

                    $this->alertRepository->markAsNotified($alert->id);
                }
            } catch (\Exception $e) {
                // Log but don't fail entire job
                $this->logger->error('Failed to send back-in-stock alert', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}