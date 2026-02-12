<?php

namespace App\Services\Product;

use App\Framework\Database\Database;
use App\Repositories\Product\MerchantRepository;

class MerchantTransactionService
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly Database           $database
    )
    {
    }

    public function credit(int $merchantId, float $netAmount, int $orderId): void
    {
        $this->database->transaction(function () use ($merchantId, $netAmount, $orderId) {
            $merchant = $this->merchantRepository->find($merchantId);

            if (!$merchant) {
                throw new \RuntimeException("Merchant {$merchantId} not found");
            }

            $newBalance = ($merchant->balance ?? 0) + $netAmount;

            $this->merchantRepository->updateBalance($merchantId, $newBalance);

            $this->merchantRepository->createTransaction([
                'merchant_id' => $merchantId,
                'type' => 'sale',
                'amount' => $netAmount,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'order_id' => $orderId,
                'reference' => "Order #{$orderId}",
                'metadata' => json_encode([
                    'order_id' => $orderId,
                    'transaction_type' => 'sale_credit'
                ])
            ]);
        });
    }
}