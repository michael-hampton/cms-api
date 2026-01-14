<?php

namespace App\Services\Product;

use App\Repositories\Product\PriceAlertRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;

class PriceAlertService
{
    private PriceAlertRepository $repository;
    private ProductRepository $productRepository;

    public function __construct(?PriceAlertRepository $repository = null, ?ProductRepository $productRepository = null)
    {
        $this->repository = $repository ?? new PriceAlertRepository();
        $this->productRepository = $productRepository ?? new ProductRepository(new ProductSpecificationGroupRepository());
    }

    public function createAlert(array $data): array
    {
        // Validate product exists
        $product = $this->productRepository->find($data['product_id']);
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        // Validate email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        // Validate target price
        if (!isset($data['target_price']) || $data['target_price'] <= 0) {
            return ['success' => false, 'message' => 'Valid target price is required'];
        }

        // Get current best price
        $currentPrice = $this->getCurrentPrice(
            $product,
            $data['variant_id'] ?? null,
            $data['merchant_id'] ?? null
        );

        // Check if target price is reasonable
        if ($data['target_price'] >= $currentPrice) {
            return [
                'success' => false,
                'message' => 'Target price must be lower than current price (£' . number_format($currentPrice, 2) . ')'
            ];
        }

        // Check for duplicate active alerts
        $existingAlert = $this->repository->findActiveAlertByEmailAndProduct(
            $data['email'],
            $data['product_id']
        );

        if ($existingAlert) {
            // Update existing alert
            $this->repository->update($existingAlert, [
                'variant_id' => $data['variant_id'] ?? null,
                'merchant_id' => $data['merchant_id'] ?? null,
                'target_price' => $data['target_price'],
                'current_price' => $currentPrice,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'message' => 'Price alert updated successfully',
                'alert' => $existingAlert
            ];
        }

        // Create new alert
        $alert = $this->repository->create([
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'],
            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? null,
            'target_price' => $data['target_price'],
            'current_price' => $currentPrice,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        return [
            'success' => true,
            'message' => 'Price alert created successfully. We\'ll notify you when the price drops!',
            'alert' => $alert
        ];
    }

    private function getCurrentPrice($product, $variantId = null, $merchantId = null): float
    {
        $bestPrice = $product->sale_price ?? $product->price;

        // If specific variant requested
        if ($variantId) {
            $variant = $this->repository->findVariant($variantId);
            if ($variant) {
                $bestPrice = $variant->sale_price > 0 ? $variant->sale_price : $variant->price;

                // If specific merchant requested for this variant
                if ($merchantId) {
                    $merchant = $this->repository->findMerchantForVariant($merchantId, $variantId);

                    if ($merchant && $merchant->effective_sale_price) {
                        $bestPrice = $merchant->effective_sale_price;
                    }
                }
            }
        } elseif ($merchantId) {
            // Specific merchant but no variant
            $merchant = $this->productRepository->findMerchantForProduct($product->id, $merchantId);

            if ($merchant && $merchant->effective_sale_price) {
                $bestPrice = $merchant->effective_sale_price;
            }
        }

        return (float) $bestPrice;
    }

    public function checkAlerts(): int
    {
        $alerts = $this->repository->getUntriggeredAlerts();

        $triggeredCount = 0;

        foreach ($alerts as $alertData) {
            $alert = $this->repository->findById($alertData['id']);
            if (!$alert) continue;

            $product = $this->repository->getProductWithVariantMerchant($alert->product_id);

            if (!$product) {
                continue;
            }

            $currentPrice = $this->getCurrentPrice(
                $product,
                $alert->variant_id,
                $alert->merchant_id
            );

            if ($currentPrice <= $alert->target_price) {
                $this->repository->update($alert, [
                    'is_triggered' => true,
                    'triggered_at' => date('Y-m-d H:i:s'),
                    'current_price' => $currentPrice
                ]);

                // Send notification
                $this->sendPriceAlert($alert, $currentPrice);

                $triggeredCount++;
            } else {
                // Update current price even if not triggered
                $this->repository->update($alert, ['current_price' => $currentPrice]);
            }
        }

        return $triggeredCount;
    }

    private function sendPriceAlert($alert, $newPrice): bool
    {
        try {
            $product = $this->productRepository->find($alert->product_id);
            if (!$product) {
                die('no');
                return false;
            }

            $subject = "Price Alert: {$product->name} is now £" . number_format($newPrice, 2);

            $message = "Great news! The price has dropped!\n\n";
            $message .= "Product: {$product->name}\n";
            $message .= "Your Target Price: £" . number_format($alert->target_price, 2) . "\n";
            $message .= "Current Price: £" . number_format($newPrice, 2) . "\n";
            $message .= "You Save: £" . number_format($alert->current_price - $newPrice, 2) . "\n\n";
            $message .= "View Product: " . url("/products/{$product->slug}") . "\n";

            // HTML version
            $htmlMessage = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #232f3e; color: white; padding: 20px; text-align: center; }
                        .content { background: #f9f9f9; padding: 20px; }
                        .price-box { background: white; border: 2px solid #ff9900; padding: 15px; margin: 15px 0; }
                        .old-price { text-decoration: line-through; color: #999; }
                        .new-price { color: #b12704; font-size: 24px; font-weight: bold; }
                        .button { display: inline-block; background: #ff9900; color: #0f1111; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🎉 Price Alert Triggered!</h1>
                        </div>
                        <div class='content'>
                            <h2>{$product->name}</h2>
                            <div class='price-box'>
                                <p><strong>Your Target Price:</strong> £" . number_format($alert->target_price, 2) . "</p>
                                <p><strong>Previous Price:</strong> <span class='old-price'>£" . number_format($alert->current_price, 2) . "</span></p>
                                <p><strong>Current Price:</strong> <span class='new-price'>£" . number_format($newPrice, 2) . "</span></p>
                                <p><strong>You Save:</strong> £" . number_format($alert->current_price - $newPrice, 2) . "</p>
                            </div>
                            <p style='text-align: center;'>
                                <a href='" . url("/products/{$product->slug}") . "' class='button'>View Deal Now</a>
                            </p>
                            <p style='font-size: 12px; color: #666; margin-top: 20px;'>
                                This is an automated price alert. You're receiving this because you set up a price alert for this product.
                            </p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            // Send email (implement based on your email service)
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Price Alerts <noreply@" . $host . ">\r\n";

            $sent = mail($alert->email, $subject, $htmlMessage, $headers);

            if ($sent) {
                $this->repository->update($alert, [
                    'is_notified' => true,
                    'notified_at' => date('Y-m-d H:i:s')
                ]);
            }

            return $sent;
        } catch (\Exception $e) {
            error_log("Failed to send price alert email: " . $e->getMessage());
            return false;
        }
    }

    public function getUserAlerts(int $userId): array
    {
        return $this->repository->getUserAlerts($userId);
    }

    public function deleteAlert(int $alertId, ?int $userId = null): bool
    {
        $alert = $this->repository->findById($alertId, $userId);

        if (!$alert) {
            return false;
        }

        return $this->repository->delete($alert);
    }

    public function getAlertStats(): array
    {
        return [
            'total_alerts' => $this->repository->getTotalCount(),
            'active_alerts' => $this->repository->getActiveCount(),
            'triggered_alerts' => $this->repository->getTriggeredCount(),
            'notified_alerts' => $this->repository->getNotifiedCount(),
        ];
    }
}