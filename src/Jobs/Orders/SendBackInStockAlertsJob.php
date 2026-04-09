<?php

namespace App\Jobs\Orders;

use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductStockAlertRepository;

class SendBackInStockAlertsJob extends BaseJob
{
    private ProductRepository $productRepository;
    private ProductStockAlertRepository $alertRepository;
    private MailManager $mailManager;
    private Logger $logger;

    public function __construct(
        private readonly int $productId,
    )
    {
    }

    public function handle(): void
    {
        $product = $this->productRepository->find($this->productId);

        if (!$product || $product->stock_quantity <= 0) {
            return;
        }

        $alerts = $this->alertRepository->getPendingAlerts($this->productId);

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